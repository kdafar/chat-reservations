<?php

namespace App\Wa\Services\WhatsApp;

use App\Wa\Hub\Models\Block;
use App\Wa\Hub\Models\BusinessType;
use App\Wa\Hub\Models\City;
use App\Wa\Hub\Models\CustomerProfile;
use App\Wa\Hub\Models\DeliveryArea;
use App\Wa\Hub\Models\State;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Services\Order\OrderHistoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppFlowService
{
    // ------------------------------------------------------------------
    // Dependencies
    // ------------------------------------------------------------------

    private WhatsAppService $whatsAppService;

    private VendorDataService $vendorDataService;

    private OrderHistoryService $orderHistoryService;

    private FlowImageService $FlowImageService;

    public function __construct(
        WhatsAppService $whatsAppService,
        VendorDataService $vendorDataService,
        OrderHistoryService $orderHistoryService,
        FlowImageService $FlowImageService
    ) {
        $this->whatsAppService = $whatsAppService;
        $this->vendorDataService = $vendorDataService;
        $this->orderHistoryService = $orderHistoryService;
        $this->FlowImageService = $FlowImageService;
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower((string) $locale);

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
    }

    // ------------------------------------------------------------------
    // Entry point
    // ------------------------------------------------------------------

    public function buildFlowResponse(array $payload, $session = null, string $locale = 'en'): array
    {
        Log::debug('[Flow] Received raw payload', ['payload' => $payload]);

        // FIX: unwrap payload if it is nested, which can happen on some callbacks
        if (isset($payload['payload']) && is_array($payload['payload'])) {
            $payload = $payload['payload'];
        }

        $meta = $this->toArrayRecursive($payload);
        $rootScreen = $meta['screen'] ?? null;        // ← top-level for callbacks
        $rootAction = $meta['action'] ?? null;

        $data = $this->toArrayRecursive($meta['data'] ?? []);
        $form = $this->toArrayRecursive($data['data'] ?? $data); // navigate payloads
        $flow = $this->toArrayRecursive($form['flow_data'] ?? []);

        // prefer nested (navigate), fall back to top-level (data_exchange)
        $screen = $form['screen'] ?? $rootScreen;
        $form['action'] = $form['action'] ?? $rootAction;

        $this->rehydrateAddressFromSession($flow, $session);
        $locale = $flow['locale'] ?? $session?->locale ?? $locale;

        if ($session && empty($flow['customer_phone'])) {
            $flow['customer_phone'] = $session->customer_phone_number;
        }

        if (! $screen) {
            return $this->bootBusinessTypeStep($locale, $flow, null, $session);
        }

        // Normalize some known keys that can arrive as arrays
        foreach ([
            'action', 'next_action',
            'order_type', 'address_type', 'order_mode',
            'item_action',
            'business_type_id', 'category_id', 'vendor_category_id',
            'item_id', 'cart_item_id', 'qty',
        ] as $key) {
            if (isset($form[$key])) {
                $form[$key] = $this->firstValue($form[$key]);
            }
        }

        try {
            switch ($screen) {
                case 'SELECT_BUSINESS_TYPE':
                    return $this->handleSelectBusinessType($form, $flow, $session, $locale);

                case 'ADDRESS_SAVED':
                    return $this->handleAddressSaved($form, $flow, $session, $locale);

                case 'SELECT_ORDER':
                    return $this->handleSelectOrder($form, $flow, $session, $locale);

                case 'SELECT_VENDOR_CATEGORY':
                    return $this->handleSelectVendorCategory($form, $flow, $session, $locale);

                case 'SELECT_VENDOR':
                    return $this->handleSelectVendor($form, $flow, $session, $locale);

                case 'SELECT_CATEGORY':
                    return $this->handleSelectCategory($form, $flow, $session, $locale);

                case 'SELECT_ITEM':
                    return $this->handleSelectItem($form, $flow, $session, $locale);

                case 'ITEM_QTY':
                    return $this->handleItemQty($form, $flow, $session, $locale);

                case 'CART_SCREEN':
                    return $this->handleCartScreen($form, $flow, $session, $locale);

                case 'CHECKOUT_START':
                    return $this->handleCheckoutStart($form, $flow, $session, $locale);

                case 'ADDRESS_BLOCK':
                    return $this->handleAddressBlock($form, $flow, $session, $locale);

                case 'CONFIRMATION_SCREEN':
                    return ['data' => ['status' => 'complete']];

                default:
                    if (($payload['action'] ?? null) === 'ping') {
                        return ['data' => ['status' => 'active']];
                    }

                    return $this->bootBusinessTypeStep($locale, [], '⚠️ An unknown error occurred. Please start over.', $session);
            }
        } catch (\Throwable $e) {
            Log::error('Flow logic error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return $this->bootBusinessTypeStep($locale, [], '⚠️ Sorry, something went wrong. Please try again.', $session);
        }
    }

    // ------------------------------------------------------------------
    // Flow Handlers
    // ------------------------------------------------------------------

    private function handleSelectBusinessType(array $form, array $flow, ?WhatsappSession $session, string $locale): array
    {
        // --- START: NEW AUTO-NAVIGATION LOGIC ---
        // Check if the flow was started with a pre-selected business type
        if (isset($flow['intent_business_type_id'])) {
            $businessTypeId = $flow['intent_business_type_id'];

            // Set the actual business_type_id in the flow state for the rest of the session
            $flow['business_type_id'] = $businessTypeId;

            // Remove the temporary key now that we've used it
            unset($flow['intent_business_type_id']);

            // Immediately proceed to the next step: Address Selection
            return $this->bootAddressStep($session, $locale, $flow);
        }
        // --- END: NEW AUTO-NAVIGATION LOGIC ---

        // Original logic for when a user manually selects a business type
        $businessTypeIdRaw = $form['business_type_id'] ?? null;
        if (! $businessTypeIdRaw) {
            return $this->bootBusinessTypeStep($locale, $flow, '⚠️ Please make a selection to continue.', $session);
        }

        $flow['business_type_id'] = (int) Str::after($businessTypeIdRaw, 'bt_');

        return $this->bootAddressStep($session, $locale, $flow);
    }

    private function handleAddressSaved(array $form, array $flow, WhatsappSession $session, string $locale): array
    {
        // FIX: Prioritize the user's explicit choice for reordering. If they select 'reorder',
        // that is the only action we should take.
        if (isset($form['order_mode']) && $form['order_mode'] === 'reorder') {
            $flow['order_mode'] = 'reorder';

            return $this->_buildSelectOrderScreen($session, $flow, $locale);
        }

        // FIX: If not reordering, explicitly set the mode to 'new_order' to prevent
        // a "sticky" reorder state from a previous interaction.
        $flow['order_mode'] = 'new_order';

        $choice = $form['address_slug'] ?? null;
        $stateId = $form['state_id'] ?? null;
        $cityId = $form['city_id'] ?? null;

        // 1) Saved address selected → use it and continue
        if ($choice && $choice !== 'ADD_NEW') {
            return $this->handleSavedAddressSelection($choice, $flow, $session, $locale);
        }

        // 2) "New address" chosen, or user has no saved addresses → run new-address flow
        if ($choice === 'ADD_NEW' || empty($flow['addr_map']) || $stateId || $cityId) {
            return $this->handleNewAddressFlow($form, $flow, $session, $locale);
        }

        // 3) Nothing selected (and not reorder) → re-render with error, still hiding state/city
        $profile = $session?->customerProfile;
        $addresses = $profile?->addresses ?? [];
        [$opts, $map] = $this->buildAddressOptions($addresses, $locale);
        $flow['addr_map'] = $map;

        return $this->buildScreenResponse('ADDRESS_SAVED', [
            'addressOptions' => $opts,
            'states' => $this->getStates($locale),
            'cities' => [],
            'show_addresses' => true,
            'show_states' => false,
            'show_city' => false,
            'footer_label' => $locale === 'ar' ? 'متابعة' : 'Continue',
            'error_message' => '⚠️ Please pick a saved address or choose "New address".',
            'show_error_message' => true,
            'flow_data' => $flow,
        ]);
    }

    private function handleSelectOrder(array $form, array $flow, ?WhatsappSession $session, string $locale): array
    {
        $selected = $form['order_id'] ?? $form['action'] ?? null;

        if (! $selected) {
            // No selection — re-show screen (if we have any orders), otherwise proceed to standard path
            return $this->_buildSelectOrderScreen($session, $flow, $locale);
        }

        if ($selected === 'new_order') {
            $flow['order_mode'] = 'new_order';

            return $this->_getNextScreenAfterAddress($session, $flow, $locale);
        }

        if (str_starts_with((string) $selected, 'order_')) {
            return $this->handleReorderSelection($session, $selected, $flow, $locale);
        }

        // Unknown value, just reload the orders list
        return $this->_buildSelectOrderScreen($session, $flow, $locale);
    }

    private function handleSelectVendorCategory(array $form, array $flow, ?WhatsappSession $session, string $locale)
    {
        $categoryIdRaw = $form['vendor_category_id'] ?? null;
        $businessTypeId = $flow['business_type_id'] ?? null;

        // Pull localized category label from business_types.category_label
        $catLabel = $this->btLabel(
            is_numeric($businessTypeId) ? (int) $businessTypeId : null,
            'category_label',
            $locale,
            $locale === 'ar' ? 'فئة' : 'category'
        );

        if (! $categoryIdRaw) {
            return $this->buildScreenResponse('SELECT_VENDOR_CATEGORY', [
                'vendor_categories' => $this->vendorDataService->getCategoriesForBusinessType($businessTypeId, $locale),
                'category_selection_prompt' => $locale === 'ar'
                    ? "أولاً، يرجى اختيار {$catLabel}"
                    : "First, please select a {$catLabel}",
                'error_message' => $locale === 'ar'
                    ? "⚠️ يرجى اختيار {$catLabel}."
                    : "⚠️ Please select a {$catLabel}.",
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        $flow['vendor_category_id'] = $categoryIdRaw;
        $cityId = $flow['city_id'] ?? $session?->delivery_city_id;

        if (! $cityId) {
            return $this->bootAddressStep($session, $locale, $flow, '⚠️ Your location is not set. Please start over.');
        }

        $categoryId = (int) Str::after($categoryIdRaw, 'category_');
        $vendors = $this->vendorDataService->getVendorsForCategory($categoryId, $locale, (int) $cityId);

        if (empty($vendors)) {
            return $this->buildScreenResponse('SELECT_VENDOR_CATEGORY', [
                'vendor_categories' => $this->vendorDataService->getCategoriesForBusinessType($businessTypeId, $locale),
                'category_selection_prompt' => $locale === 'ar'
                    ? "أولاً، يرجى اختيار {$catLabel}"
                    : "First, please select a {$catLabel}",
                'error_message' => $locale === 'ar'
                    ? '⚠️ عذراً، لا توجد متاجر توصل لهذه الفئة في منطقتك.'
                    : '⚠️ Sorry, no vendors for this category deliver to your area.',
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        $flow['page'] = 1;
        $pageSize = (int) config('services.whatsapp.flow_vendor_page_size', 19);
        [$pageItems, $hasMore] = $this->paginate($vendors, 1, $pageSize);
        if ($hasMore) {
            $pageItems[] = $this->loadMoreItem($locale);
        }

        return $this->buildScreenResponse('SELECT_VENDOR', [
            'vendors' => $pageItems,
            'vendor_category_id' => $categoryIdRaw,
            'search_value' => '',
            'show_error_message' => false,
            'flow_data' => $flow,
            '__session__' => $session,
        ]);
    }

    private function handleSelectVendor(array $form, array $flow, ?WhatsappSession $session, string $locale)
    {
        $vendorIdRaw = $form['vendor_id'] ?? $flow['vendor_id'] ?? null;
        $vendorCategoryIdRaw = $form['vendor_category_id'] ?? $flow['vendor_category_id'] ?? null;
        $searchTerm = trim((string) ($form['search'] ?? ''));
        $prevSearch = (string) ($flow['search_value'] ?? '');
        $page = (int) ($flow['page'] ?? 1);

        $pageSize = (int) ($flow['force_pager_size'] ?? config('services.whatsapp.flow_restaurant_page_size', 19));
        $btId = is_numeric($flow['business_type_id'] ?? null) ? (int) $flow['business_type_id'] : null;
        $catLabel = $this->btLabel($btId, 'category_label', $locale, $locale === 'ar' ? 'فئة' : 'category');
        if (! $vendorCategoryIdRaw) {
            return $this->buildScreenResponse('SELECT_VENDOR_CATEGORY', [
                'vendor_categories' => $this->vendorDataService->getCategoriesForBusinessType($flow['business_type_id'] ?? null, $locale),
                'category_selection_prompt' => $locale === 'ar'
    ? "أولاً، يرجى اختيار {$catLabel}"
    : "First, please select a {$catLabel}",
                'error_message' => '⚠️ '.($locale === 'ar' ? 'حدث خطأ. يرجى اختيار فئة مرة أخرى.' : 'Something went wrong. Please select a category again.'),
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        if ($vendorIdRaw === 'clear_search') {
            $searchTerm = '';
            $page = 1;
            $vendorIdRaw = null;
        }

        if ($vendorIdRaw === 'back_to_cuisines') {
            $btId = is_numeric($flow['business_type_id'] ?? null) ? (int) $flow['business_type_id'] : null;
            $catLabel = $this->btLabel($btId, 'category_label', $locale, $locale === 'ar' ? 'فئة' : 'category');

            return $this->buildScreenResponse('SELECT_VENDOR_CATEGORY', [
                'vendor_categories' => $this->vendorDataService->getCategoriesForBusinessType($flow['business_type_id'] ?? null, $locale),
                'category_selection_prompt' => $locale === 'ar'
    ? "أولاً، يرجى اختيار {$catLabel}"
    : "First, please select a {$catLabel}",
                'show_error_message' => false,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        if ($searchTerm !== '' && $searchTerm !== $prevSearch) {
            $page = 1;
        }

        if ($vendorIdRaw === 'pager_more') {
            $page++;
            $vendorIdRaw = null;
        }

        // Selection
        if ($vendorIdRaw && $vendorIdRaw !== 'pager_more') {
            $previousVendorId = $flow['vendor_id'] ?? null;

            // Clear cart if user chose a different restaurant
            if ($vendorIdRaw !== $previousVendorId && $session) {
                \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)->delete();
                unset($flow['cart']);
            }

            $flow['vendor_id'] = $vendorIdRaw;
            $flow['vendor_category_id'] = $vendorCategoryIdRaw;
            $flow['search_value'] = $searchTerm;
            $flow['page'] = $page;
            $flow['force_pager_size'] = $pageSize;

            $vendorId = (int) Str::after($vendorIdRaw, 'vendor_');
            $categories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);

            if (empty($categories)) {
                // Fall back to list restaurants again for cuisine
                $vendorCategoryId = (int) Str::after($vendorCategoryIdRaw, 'category_');
                $cityId = $flow['city_id'] ?? $session?->delivery_city_id;
                $base = $this->vendorDataService->getVendorsForCategory($vendorCategoryId, $locale, (int) $cityId) ?? [];
                $list = ($searchTerm !== '') ? $this->filterRestaurantsBySearch($base, $searchTerm) : $base;

                [$pageItems, $hasMore] = $this->paginateRestaurants($list, $page, $pageSize);
                if ($hasMore) {
                    $pageItems[] = $this->loadMoreItem($locale);
                }

                return $this->buildScreenResponse('SELECT_VENDOR', [
                    'vendors' => $pageItems,
                    'vendor_category_id' => $vendorCategoryIdRaw,
                    'search_value' => $searchTerm,
                    'error_message' => '⚠️ '.($locale === 'ar' ? 'تعذّر تحميل قائمة هذا المطعم. يرجى اختيار مطعم آخر.' : "This vendor's menu could not be loaded. Please select another one."),
                    'show_error_message' => true,
                    'flow_data' => array_merge($flow, ['page' => $page, 'force_pager_size' => $pageSize]),
                    '__session__' => $session,
                ]);
            }

            // MODIFIED: Paginate categories before displaying them
            $flow['category_page'] = 1;
            $categoryPageSize = (int) config('services.whatsapp.flow_category_page_size', 19);
            [$pageItems, $hasMore] = $this->paginate($categories, 1, $categoryPageSize);
            if ($hasMore) {
                $pageItems[] = $this->loadMoreItem($locale);
            }

            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $pageItems, // MODIFIED: Pass paginated items
                'vendor_id' => $vendorIdRaw,
                'vendor_category_id' => $vendorCategoryIdRaw,
                'show_error_message' => false,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        // List page
        $vendorCategoryId = (int) Str::after($vendorCategoryIdRaw, 'category_');
        $cityId = $flow['city_id'] ?? $session?->delivery_city_id;
        $base = $this->vendorDataService->getVendorsForCategory($vendorCategoryId, $locale, (int) $cityId) ?? [];
        $list = ($searchTerm !== '') ? $this->filterRestaurantsBySearch($base, $searchTerm) : $base;

        if ($searchTerm !== '' && empty($list)) {
            $flow['search_value'] = $searchTerm;
            $flow['page'] = 1;
            $flow['force_pager_size'] = $pageSize;

            return $this->buildScreenResponse('SELECT_VENDOR', [
                'vendors' => $this->noResultsItems($locale),
                'vendor_category_id' => $vendorCategoryIdRaw,
                'search_value' => $searchTerm,
                'error_message' => '⚠️ '.($locale === 'ar' ? 'لا توجد نتائج. جرّب كتابة الاسم بشكل مختلف.' : 'No results. Try a different spelling.'),
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        [$pageItems, $hasMore] = $this->paginateRestaurants($list, $page, $pageSize);
        if ($hasMore) {
            $pageItems[] = $this->loadMoreItem($locale);
        }

        $flow['search_value'] = $searchTerm;
        $flow['page'] = $page;
        $flow['force_pager_size'] = $pageSize;

        return $this->buildScreenResponse('SELECT_VENDOR', [
            'vendors' => $pageItems,
            'vendor_category_id' => $vendorCategoryIdRaw,
            'search_value' => $searchTerm,
            'show_error_message' => false,
            'flow_data' => $flow,
            '__session__' => $session,
        ]);
    }

    private function handleSelectCategory(array $form, array $flow, ?WhatsappSession $session, string $locale)
    {
        $categoryRaw = $form['category_id'] ?? null;
        $vendorRaw = $flow['vendor_id'] ?? null;
        $vendorId = (int) Str::after($vendorRaw, 'vendor_');
        $categoryPageSize = (int) config('services.whatsapp.flow_category_page_size', 19);

        // ADDED: Handle "Load More" action for categories
        if ($categoryRaw === 'pager_more') {
            $page = ($flow['category_page'] ?? 1) + 1;
            $flow['category_page'] = $page;

            $allCategories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);
            [$pageItems, $hasMore] = $this->paginate($allCategories, $page, $categoryPageSize);
            if ($hasMore) {
                $pageItems[] = $this->loadMoreItem($locale);
            }

            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $pageItems,
                'vendor_id' => $vendorRaw,
                'vendor_category_id' => $flow['vendor_category_id'] ?? null,
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        if (! isset($form['category_id'])) {
            // This case re-renders the screen if no category was selected.
            $allCategories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);
            $page = $flow['category_page'] ?? 1;
            [$pageItems, $hasMore] = $this->paginate($allCategories, $page, $categoryPageSize);
            if ($hasMore) {
                $pageItems[] = $this->loadMoreItem($locale);
            }

            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $pageItems,
                'vendor_id' => $vendorRaw,
                'vendor_category_id' => $flow['vendor_category_id'] ?? null,
                'flow_data' => $flow,
                'show_error_message' => true, // Show error as no selection was made
                'error_message' => '⚠️ Please make a selection.',
                '__session__' => $session,
            ]);
        }

        if (! $categoryRaw || ! $vendorRaw) {
            $vendorCategoryId = (int) Str::after($flow['vendor_category_id'], 'category_');
            $cityId = $flow['city_id'] ?? $session?->delivery_city_id;
            $vendors = $this->vendorDataService->getVendorsForCategory($vendorCategoryId, $locale, (int) $cityId);

            return $this->buildScreenResponse('SELECT_VENDOR', [
                'vendors' => $vendors,
                'error_message' => '⚠️ Something went wrong. Please select a vendor again.',
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        $flow = array_merge($flow, [
            'category_id' => (int) Str::after($categoryRaw, 'category_'),
            'category_id_raw' => $categoryRaw,
        ]);

        $items = $this->vendorDataService->getItemsForCategorySimple($categoryRaw, $vendorRaw, $locale);
        if (empty($items)) {
            $categories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);
            [$pageItems, $hasMore] = $this->paginate($categories, 1, $categoryPageSize); // MODIFIED
            if ($hasMore) {
                $pageItems[] = $this->loadMoreItem($locale);
            }

            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $pageItems, // MODIFIED
                'error_message' => '⚠️ No items found in this category. Please select another.',
                'show_error_message' => true,
                'vendor_id' => $vendorRaw,
                'vendor_category_id' => $flow['vendor_category_id'] ?? null,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        $cacheKey = 'flow_items:'.uniqid('', true);
        Cache::put($cacheKey, $items, now()->addMinutes(10));
        $flow['items_cache_key'] = $cacheKey;

        // ADDED: Paginate items before showing them the first time
        $flow['item_page'] = 1;
        $itemPageSize = (int) config('services.whatsapp.flow_item_page_size', 19);
        [$pageItems, $hasMore] = $this->paginate($items, 1, $itemPageSize);
        if ($hasMore) {
            $pageItems[] = $this->loadMoreItem($locale);
        }

        return $this->buildScreenResponse('SELECT_ITEM', [
            'items' => $pageItems, // MODIFIED
            'flow_data' => $flow,
            'search_value' => '',
            'show_error_message' => false,
            '__session__' => $session,
        ]);
    }

    private function handleSelectItem(array $form, array $flow, ?WhatsappSession $session, string $locale)
    {
        $itemIdsUi = $form['item_ids'] ?? [];
        if (! is_array($itemIdsUi)) {
            $itemIdsUi = $itemIdsUi ? [$itemIdsUi] : [];
        }

        // ADDED: Handle "Back to categories" action
        if (in_array('back_to_categories', $itemIdsUi)) {
            $vendorId = (int) Str::after($flow['vendor_id'], 'vendor_');
            $categories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);

            // Paginate the categories list based on its last known page state
            $page = $flow['category_page'] ?? 1;
            $categoryPageSize = (int) config('services.whatsapp.flow_category_page_size', 19);
            [$pageItems, $hasMore] = $this->paginate($categories, $page, $categoryPageSize);
            if ($hasMore) {
                $pageItems[] = $this->loadMoreItem($locale);
            }

            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $pageItems,
                'vendor_id' => $flow['vendor_id'],
                'vendor_category_id' => $flow['vendor_category_id'],
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        $searchTerm = trim((string) ($form['search'] ?? ''));
        $prevSearch = (string) ($flow['search_value'] ?? '');
        $itemPageSize = (int) config('services.whatsapp.flow_item_page_size', 19);

        // ADDED: Handle "Clear search" action
        if (in_array('clear_search', $itemIdsUi)) {
            $searchTerm = '';
            $itemIdsUi = []; // Clear selection to prevent further processing
        }

        if ($searchTerm !== '' && $searchTerm !== $prevSearch) {
            $flow['item_page'] = 1;
        }
        $flow['search_value'] = $searchTerm;

        $cacheKey = $flow['items_cache_key'] ?? null;
        $allItems = $cacheKey ? Cache::get($cacheKey, []) : [];

        if (empty($allItems)) {
            $categoryRaw = $flow['category_id_raw'] ?? null;
            $vendorRaw = $flow['vendor_id'] ?? null;
            if ($categoryRaw && $vendorRaw) {
                $allItems = $this->vendorDataService->getItemsForCategorySimple($categoryRaw, $vendorRaw, $locale);
                $cacheKey = 'flow_items:'.uniqid('', true);
                Cache::put($cacheKey, $allItems, now()->addMinutes(10));
                $flow['items_cache_key'] = $cacheKey;
            }
        }

        $filteredItems = $this->filterListBySearch($allItems, $searchTerm);

        // ADDED: Display the "No results" screen if search is active and results are empty
        if ($searchTerm !== '' && empty($filteredItems)) {
            return $this->buildScreenResponse('SELECT_ITEM', [
                'items' => $this->noItemResultsItems($locale),
                'flow_data' => $flow,
                'search_value' => $searchTerm,
                'error_message' => '⚠️ '.($locale === 'ar' ? 'لا توجد نتائج. جرّب البحث بكلمة أخرى.' : 'No results. Try a different search term.'),
                'show_error_message' => true,
                '__session__' => $session,
            ]);
        }

        if (in_array('pager_more', $itemIdsUi)) {
            $page = ($flow['item_page'] ?? 1) + 1;
            $flow['item_page'] = $page;

            [$pageItems, $hasMore] = $this->paginate($filteredItems, $page, $itemPageSize);
            if ($hasMore) {
                $pageItems[] = $this->loadMoreItem($locale);
            }

            return $this->buildScreenResponse('SELECT_ITEM', [
                'items' => $pageItems,
                'flow_data' => $flow,
                'search_value' => $searchTerm,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        if (! empty($itemIdsUi)) {
            $flow['item_ids_raw'] = $itemIdsUi;

            $selectedIds = collect($itemIdsUi)
                ->map(fn ($id) => (int) preg_replace('/\D+/', '', $id))
                ->all();

            $this->removeItemsOfCategoryNotInSelection($session, $selectedIds, $flow, $locale);

            $selected = collect($allItems)->filter(function ($item) use ($selectedIds) {
                $intId = (int) preg_replace('/\D+/', '', $item['id']);

                return in_array($intId, $selectedIds, true);
            })->values();

            if ($selected->isEmpty()) {
                [$pageItems, $hasMore] = $this->paginate($filteredItems, $flow['item_page'] ?? 1, $itemPageSize);
                if ($hasMore) {
                    $pageItems[] = $this->loadMoreItem($locale);
                }

                return $this->buildScreenResponse('SELECT_ITEM', [
                    'items' => $pageItems,
                    'error_message' => '⚠️ '.$this->getFlowMessage('items_not_found', $locale),
                    'show_error_message' => true,
                    'search_value' => $searchTerm,
                    'flow_data' => $flow,
                    '__session__' => $session,
                ]);
            }

            // Prepare for ITEM_QTY screen
            $first = $selected->first();
            $flow['pending_items'] = $selected->slice(1)->all();

            $itemData = $this->vendorDataService->getItemBasic($first['id'], $flow['vendor_id'], $locale);

            $flow['current_addon_groups'] = $itemData['addon_groups'] ?? [];
            $flow['locale'] = $locale;
            $flattenedAddons = $this->flattenAddonGroups($flow['current_addon_groups'], $locale);

            return $this->buildScreenResponse('ITEM_QTY', [
                'item_title' => $itemData['title'] ?? ($first['title'] ?? 'Item'),
                'item_description' => trim(($itemData['description'] ?? '')."\n**Price:** ".number_format((float) ($itemData['price'] ?? 0), 3).' KWD'),
                'image_src' => (string) ($itemData['image'] ?? ''),
                'item_prompt' => $this->getFlowMessage('how_many', $locale),
                'item_id' => $first['id'],
                'qty_options' => $this->buildQtyOptions(),
                'addons' => $flattenedAddons,
                'show_addons' => ! empty($flattenedAddons),
                'has_multiple_actions' => true,
                'item_actions' => $this->removeActions($locale),
                'footer_label' => $locale === 'ar' ? 'أضف إلى السلة' : 'Add to Cart',
                'footer_action_id' => 'save',
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        // Default display: Paginate and show the filtered list
        $this->removeItemsOfCategoryNotInSelection($session, [], $flow, $locale);

        $page = $flow['item_page'] ?? 1;
        [$pageItems, $hasMore] = $this->paginate($filteredItems, $page, $itemPageSize);
        if ($hasMore) {
            $pageItems[] = $this->loadMoreItem($locale);
        }

        return $this->buildScreenResponse('SELECT_ITEM', [
            'items' => $pageItems,
            'flow_data' => $flow,
            'search_value' => $searchTerm,
            'show_error_message' => false,
            '__session__' => $session,
        ]);
    }

    private function handleItemQty(array $form, array $flow, ?WhatsappSession $session, string $locale): array
    {
        $itemIdUi = is_array($form['item_id'] ?? null) ? ($form['item_id'][0] ?? null) : ($form['item_id'] ?? null);
        $qty = (int) ($form['qty'] ?? 0);
        $cartItemId = $form['cart_item_id'] ?? null;

        $addonIds = $form['addon_ids'] ?? [];
        if (! is_array($addonIds)) {
            $addonIds = $addonIds ? [$addonIds] : [];
        }

        $selectedAction = $form['item_action'] ?? ($form['action'] ?? null);
        // Ignore transport-y values if they slip in.
        $transport = ['data_exchange', 'navigate', 'complete', 'ping', '', null];
        if (in_array($selectedAction, $transport, true)) {
            $selectedAction = null;
        }
        $singleAction = $form['single_action'] ?? null;
        $finalAction = $selectedAction ?: $singleAction ?: 'save';

        $itemId = (int) preg_replace('/\D+/', '', (string) $itemIdUi);
        $addonsDetailed = $this->mapAddonIdsToDetails($addonIds, $flow['current_addon_groups'] ?? [], $locale);
        usort($addonsDetailed, fn ($a, $b) => strcmp($a['id'], $b['id']));

        $reloadWithError = function (string $msg) use ($form, $flow, $locale, $itemIdUi, $session) {
            $itemData = $this->vendorDataService->getItemBasic($itemIdUi, $flow['vendor_id'], $locale) ?? [];

            $flattenedAddons = $this->flattenAddonGroups($flow['current_addon_groups'] ?? [], $locale);

            return $this->buildScreenResponse('ITEM_QTY', [
                'item_title' => $itemData['title'] ?? 'Item',
                'item_description' => trim(($itemData['description'] ?? '')."\n**Price:** ".number_format((float) ($itemData['price'] ?? 0), 3).' KWD'),
                'image_src' => (string) ($itemData['image'] ?? ''),
                'item_prompt' => $this->getFlowMessage('how_many', $locale),
                'item_id' => $form['item_id'] ?? $itemIdUi,
                'cart_item_id' => $form['cart_item_id'] ?? null,
                'qty_options' => $this->buildQtyOptions(),
                'addons' => $flattenedAddons,
                'show_addons' => ! empty($flattenedAddons),
                'has_multiple_actions' => true,
                'item_actions' => $this->removeActions($locale),
                'footer_label' => $locale === 'ar' ? 'أضف إلى السلة' : 'Add to Cart',
                'footer_action_id' => 'save',
                'error_message' => '⚠️ '.$msg,
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        };

        if ($finalAction === 'remove') {
            if ($cartItemId) {
                \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)
                    ->where('id', $cartItemId)
                    ->delete();
            } else {
                $itemToRemove = \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)
                    ->where('item_id_from_restaurant', $itemId)
                    ->get()
                    ->first(function ($ci) use ($addonsDetailed) {
                        $stored = $ci->variations ?? [];
                        usort($stored, fn ($x, $y) => strcmp($x['id'], $y['id']));

                        return count($stored) === count($addonsDetailed)
                            && ! array_diff(array_column($stored, 'id'), array_column($addonsDetailed, 'id'));
                    });

                if ($itemToRemove) {
                    $itemToRemove->delete();
                } else {
                    // Fallback: if there is only one candidate for this item, remove it.
                    $candidates = \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)
                        ->where('item_id_from_restaurant', $itemId)
                        ->get();
                    if ($candidates->count() === 1) {
                        $candidates->first()->delete();
                    }
                }
            }
        } elseif ($finalAction === 'save') {
            if (! $itemIdUi || $qty < 1) {
                return $reloadWithError($this->getFlowMessage('missing_item_id_or_qty', $locale));
            }

            if ($cartItemId) {
                $cartLine = \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)
                    ->where('id', $cartItemId)
                    ->first();

                if ($cartLine) {
                    $cartLine->quantity = $qty;
                    $cartLine->variations = ! empty($addonsDetailed) ? $addonsDetailed : ($cartLine->variations ?? []);
                    $cartLine->save();
                } else {
                    $candidates = \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)
                        ->where('item_id_from_restaurant', $itemId)
                        ->get();
                    $existing = $candidates->first(function ($ci) use ($addonsDetailed) {
                        $stored = $ci->variations ?? [];
                        usort($stored, fn ($x, $y) => strcmp($x['id'], $y['id']));

                        return count($stored) === count($addonsDetailed)
                            && ! array_diff(array_column($stored, 'id'), array_column($addonsDetailed, 'id'));
                    });
                    if ($existing) {
                        $existing->quantity = $qty;
                        $existing->save();
                    } else {
                        $itemData = $this->vendorDataService->getItemBasic($itemIdUi, $flow['vendor_id'], $locale);
                        if ($itemData) {
                            \App\Wa\Hub\Models\CartItem::create([
                                'whatsapp_session_id' => $session->id,
                                'item_id_from_restaurant' => $itemId,
                                'item_name' => $itemData['title'],
                                'quantity' => $qty,
                                'price' => (float) $itemData['price'],
                                'variations' => $addonsDetailed,
                            ]);
                        }
                    }
                }
            } else {
                $candidates = \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)
                    ->where('item_id_from_restaurant', $itemId)
                    ->get();

                $existing = $candidates->first(function ($ci) use ($addonsDetailed) {
                    $stored = $ci->variations ?? [];
                    usort($stored, fn ($x, $y) => strcmp($x['id'], $y['id']));

                    return count($stored) === count($addonsDetailed)
                        && ! array_diff(array_column($stored, 'id'), array_column($addonsDetailed, 'id'));
                });

                if ($existing) {
                    $existing->quantity = $qty;
                    $existing->save();
                } else {
                    $itemData = $this->vendorDataService->getItemBasic($itemIdUi, $flow['vendor_id'], $locale);
                    if ($itemData) {
                        \App\Wa\Hub\Models\CartItem::create([
                            'whatsapp_session_id' => $session->id,
                            'item_id_from_restaurant' => $itemId,
                            'item_name' => $itemData['title'],
                            'quantity' => $qty,
                            'price' => (float) $itemData['price'],
                            'variations' => $addonsDetailed,
                        ]);
                    }
                }
            }
        }

        // Next pending item?
        $pending = $flow['pending_items'] ?? [];
        if (! empty($pending)) {
            $next = array_shift($pending);
            $flow['pending_items'] = $pending;

            $nextData = $this->vendorDataService->getItemBasic($next['id'], $flow['vendor_id'], $locale);

            $flow['current_addon_groups'] = $nextData['addon_groups'] ?? [];
            $flattenedAddons = $this->flattenAddonGroups($flow['current_addon_groups'], $locale);

            return $this->buildScreenResponse('ITEM_QTY', [
                'item_title' => $next['title'],
                'item_description' => trim(($nextData['description'] ?? '')."\n**Price:** ".number_format((float) ($nextData['price'] ?? 0), 3).' KWD'),
                'image_src' => (string) ($nextData['image'] ?? ''),
                'item_prompt' => $this->getFlowMessage('how_many', $locale),
                'item_id' => $next['id'],
                'cart_item_id' => $next['cart_item_id'] ?? null,
                'qty_options' => $this->buildQtyOptions(),
                'addons' => $flattenedAddons,
                'show_addons' => ! empty($flattenedAddons),
                'has_multiple_actions' => true,
                'item_actions' => $this->removeActions($locale),
                'footer_label' => $locale === 'ar' ? 'حفظ التغييرات' : 'Save changes',
                'footer_action_id' => 'save',
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        // Return to cart
        unset($flow['pending_items'], $flow['current_addon_groups']);

        $summary = $this->summarizeCart($session, $locale);
        [$minMsg, $actions] = $this->cartActionsAndMinMsg($session, $flow, $locale);
        $hasMultipleActions = count($actions) > 1;
        $footerLabel = $hasMultipleActions
            ? ($locale === 'ar' ? 'متابعة' : 'Continue')
            : ($locale === 'ar' ? '➕ إضافة المزيد' : '➕ Add more items');

        return $this->buildScreenResponse('CART_SCREEN', [
            'summary_text' => $summary,
            'min_order_msg' => $minMsg,
            'actions' => $actions,
            'has_multiple_actions' => $hasMultipleActions,
            'footer_label' => $footerLabel,
            'footer_action_id' => $hasMultipleActions ? '' : 'add_more',
            'show_error_message' => false,
            'flow_data' => $flow,
            '__session__' => $session,
        ]);
    }

    private function handleCartScreen(array $form, array $flow, ?WhatsappSession $session, string $locale): array
    {
        $transport = ['data_exchange', 'navigate', 'complete', 'ping', null, ''];
        $finalAction = $form['action'] ?? $form['single_action'] ?? null;
        if (in_array($finalAction, $transport, true)) {
            $finalAction = $form['single_action'] ?? null;
        }

        return $this->handleCartAction($finalAction, $flow, $session, $locale);
    }

    private function handleCartAction($finalAction, array $flow, ?WhatsappSession $session, string $locale)
    {
        if ($finalAction === 'add_more') {
            $vendorId = (int) Str::after($flow['vendor_id'], 'vendor_');
            $categories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);

            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $categories,
                'vendor_id' => $flow['vendor_id'],
                'vendor_category_id' => $flow['vendor_category_id'],
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        if ($finalAction === 'checkout') {
            $flow['state_id'] ??= $session->delivery_state_id;
            $flow['city_id'] ??= $session->delivery_city_id;

            return $this->buildScreenResponse('CHECKOUT_START', [
                'user_name' => $session->customerProfile->full_name ?? '',
                'error_message' => '',
                'show_error_message' => false,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        $summary = $this->summarizeCart($session, $locale);
        [$minMsg, $actions] = $this->cartActionsAndMinMsg($session, $flow, $locale);
        $hasMultipleActions = count($actions) > 1;

        $footerLabel = $hasMultipleActions
            ? ($locale === 'ar' ? 'متابعة' : 'Continue')
            : ($locale === 'ar' ? '➕ إضافة المزيد' : '➕ Add more items');

        $footerActionId = $hasMultipleActions ? '' : 'add_more';

        return $this->buildScreenResponse('CART_SCREEN', [
            'summary_text' => $summary,
            'min_order_msg' => $minMsg,
            'actions' => $actions,
            'has_multiple_actions' => $hasMultipleActions,
            'footer_label' => $footerLabel,
            'footer_action_id' => $footerActionId,
            'error_message' => '⚠️ Please select an action to continue.',
            'show_error_message' => true,
            'flow_data' => $flow,
            '__session__' => $session,
        ]);
    }

    private function handleCheckoutStart(array $form, array $flow, ?WhatsappSession $session, string $locale)
    {
        $flow = array_merge($flow, [
            'name' => $form['name'] ?? $flow['name'] ?? $session->customerProfile->full_name ?? '',
            'order_type' => $form['order_type'] ?? $flow['order_type'] ?? 'delivery',
            'promo_code' => $form['promo_code'] ?? $flow['promo_code'] ?? null,
            'notes' => $form['notes'] ?? $flow['notes'] ?? '',
            'locale' => $locale,
        ]);

        $flow['state_id'] ??= $session->delivery_state_id;
        $flow['city_id'] ??= $session->delivery_city_id;

        $promoError = $this->validateAndApplyPromo($flow, $session, $flow['locale']);
        if ($promoError) {
            return $this->buildScreenResponse('CHECKOUT_START', [
                'user_name' => $flow['name'],
                'error_message' => $promoError,
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        if ($flow['order_type'] === 'pickup') {
            $summary = $this->makeOrderSummary($session, $flow, $locale);

            return $this->buildScreenResponse('CONFIRMATION_SCREEN', [
                'summary_text' => $summary,
                'final_payload' => $flow,
                'show_error_message' => false,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        if ($this->hasFullAddress($flow)) {
            $vendorId = (int) Str::after($flow['vendor_id'] ?? '', 'vendor_');
            $cityId = (int) ($flow['city_id'] ?? $session->delivery_city_id);
            $flow['delivery_fee'] = $this->deliveryFee($vendorId, $cityId);
            $summary = $this->makeOrderSummary($session, $flow, $locale);

            return $this->buildScreenResponse('CONFIRMATION_SCREEN', [
                'summary_text' => $summary,
                'final_payload' => $flow,
                'show_error_message' => false,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        if (empty($flow['block_id']) || empty($flow['street'])) {
            $blocks = $this->getBlocksForCity($flow['city_id'], $locale);

            return $this->buildScreenResponse('ADDRESS_BLOCK', [
                'blocks' => $blocks,
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        return $this->bootAddressStep($session, $locale, $flow, '⚠️ Your location details are missing. Please start over.');
    }

    private function handleAddressBlock(array $form, array $flow, ?WhatsappSession $session, string $locale)
    {
        $flow = array_merge($flow, [
            'block_id' => $form['block_id'] ?? $flow['block_id'] ?? null,
            'address_type' => $form['address_type'] ?? $flow['address_type'] ?? null,
            'street' => $form['street'] ?? $flow['street'] ?? null,
            'house_no' => $form['house_no'] ?? $flow['house_no'] ?? null,
            'locale' => $locale,
        ]);

        if (empty($flow['block_id']) || empty($flow['street'])) {
            return $this->buildScreenResponse('ADDRESS_BLOCK', [
                'blocks' => $this->getBlocksForCity($flow['city_id'], $locale),
                'error_message' => '⚠️ Please complete all required address fields.',
                'show_error_message' => true,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        $vendorId = (int) Str::after($flow['vendor_id'] ?? '', 'vendor_');
        $cityId = (int) ($flow['city_id'] ?? $session->delivery_city_id);
        $fee = $this->deliveryFee($vendorId, $cityId);
        $flow['delivery_fee'] = $fee;

        if (! empty($flow['address_type'])) {
            $flow['address_type_label'] = $this->addressTypeLabel($flow['address_type'], $locale);
        }

        $this->persistAddressFromFlow($session, $flow);

        $summary = $this->makeOrderSummary($session, $flow, $locale);

        return $this->buildScreenResponse('CONFIRMATION_SCREEN', [
            'summary_text' => $summary,
            'final_payload' => $flow,
            'show_error_message' => false,
            'flow_data' => $flow,
            '__session__' => $session,
        ]);
    }

    // ------------------------------------------------------------------
    // Reorder helpers
    // ------------------------------------------------------------------

    private function _buildSelectOrderScreen(?WhatsappSession $session, array $flow, string $locale): array
    {
        // ADDED: Get the current business_type_id from the flow state.
        $businessTypeId = $flow['business_type_id'] ?? null;

        // MODIFIED: Pass the $businessTypeId to the service call to filter the orders.
        // We'll fetch up to 10 recent orders for this category.
        $recentOrders = $this->orderHistoryService->fetchRecentOrders($session->customer_phone_number, 10, $businessTypeId);

        if ($recentOrders->isEmpty()) {
            // MODIFIED: Made the "no orders" text more specific.
            $flow['no_orders_text'] = $locale === 'ar'
                ? 'لا توجد لديك طلبات سابقة لهذه الفئة. يرجى بدء طلب جديد.'
                : 'You have no previous orders for this category. Please start a new one.';

            return $this->_getNextScreenAfterAddress($session, $flow, $locale);
        }

        $isAr = ($locale === 'ar');
        $curr = $isAr ? 'د.ك' : 'KWD';
        $fallback = $isAr ? 'en' : 'ar';

        $formattedOrders = $recentOrders->map(function ($order) use ($curr, $isAr, $locale, $fallback) {
            $itemSummary = $order->items
                ->map(fn ($item) => "{$item->quantity}x {$item->item_name}")
                ->take(2)
                ->implode(', ');
            if ($order->items->count() > 2) {
                $itemSummary .= '...';
            }

            $total = $this->orderTotalKwd($order);
            $totalStr = number_format((float) $total, 3);
            $dateStr = $isAr
                ? $order->created_at->locale('ar')->translatedFormat('d MMM، Y')
                : $order->created_at->format('M d, Y');

            $logoRaw = $this->imageRawBase64FromMixed($order->restaurant->logo_url ?? null);
            $totalLbl = $isAr ? 'الإجمالي' : 'Total';

            $r = $order->restaurant;
            $name = $r
                ? ($r->getTranslation('name', $locale)
                    ?: $r->getTranslation('name', $fallback)
                    ?: ($r->name ?? ($isAr ? 'مطعم غير معروف' : 'Unknown Restaurant')))
                : ($isAr ? 'مطعم غير معروف' : 'Unknown Restaurant');

            return [
                'id' => 'order_'.$order->id,
                'title' => $name,
                'description' => sprintf("%s\n**%s: %s %s**\n🗓️ %s", $itemSummary, $totalLbl, $totalStr, $curr, $dateStr),
                'image' => $logoRaw, // raw base64
            ];
        })->values()->toArray();

        return $this->buildScreenResponse('SELECT_ORDER', [
            'previous_orders' => $formattedOrders,
            'has_previous_orders' => true,
            'show_no_orders' => false,
            'flow_data' => $flow,
            'show_error_message' => false,
            '__session__' => $session,
        ]);
    }

    private function handleReorderSelection(WhatsappSession $session, string $orderIdRaw, array $flow, string $locale): array
    {
        $orderId = (int) Str::after($orderIdRaw, 'order_');
        $orderToRecreate = $this->orderHistoryService->fetchOrderForReorder($orderId);

        if (! $orderToRecreate || ! $orderToRecreate->restaurant) {
            Log::critical('[Flow Reorder] Order or its restaurant not found.', ['order_id' => $orderId]);

            return $this->bootAddressStep(
                $session,
                $locale,
                [],
                '⚠️ Sorry, this order cannot be reordered as the restaurant is no longer available.'
            );
        }

        $vendor = $orderToRecreate->restaurant;

        // Ensure cuisine id exists
        $firstCuisine = $vendor->cuisines()->first();
        if (! $firstCuisine) {
            Log::critical('[Flow Reorder] Vendor has no associated cuisines.', ['vendor_id' => $vendor->id]);

            return $this->bootAddressStep(
                $session,
                $locale,
                [],
                '⚠️ Sorry, there was a configuration issue with this vendor. Please start a new order.'
            );
        }
        $cuisineId = (int) $firstCuisine->id;

        // Reset cart and recreate
        $session->cartItems()->delete();
        foreach ($orderToRecreate->items as $item) {
            $session->cartItems()->create([
                'item_id_from_restaurant' => $item->item_id_from_restaurant,
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'variations' => $item->addons,
            ]);
        }

        // Persist context
        $flow['vendor_id'] = 'vendor_'.$vendor->id;
        $flow['vendor_category_id'] = 'category_'.$cuisineId;

        // Build pending queue
        $cartLines = $session->cartItems()->get();
        if ($cartLines->isEmpty()) {
            $summary = $this->summarizeCart($session, $locale);
            [$minMsg, $actions] = $this->cartActionsAndMinMsg($session, $flow, $locale);
            $hasMultipleActions = count($actions) > 1;
            $footerLabel = $hasMultipleActions
                ? ($locale === 'ar' ? 'متابعة' : 'Continue')
                : ($locale === 'ar' ? '➕ إضافة المزيد' : '➕ Add more items');

            return $this->buildScreenResponse('CART_SCREEN', [
                'summary_text' => $summary,
                'min_order_msg' => $minMsg,
                'actions' => $actions,
                'has_multiple_actions' => $hasMultipleActions,
                'footer_label' => $footerLabel,
                'footer_action_id' => $hasMultipleActions ? '' : 'add_more',
                'show_error_message' => false,
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        $pending = [];
        foreach ($cartLines as $ci) {
            $pending[] = [
                'id' => 'item_'.$ci->item_id_from_restaurant,
                'title' => $ci->item_name,
                'cart_item_id' => (string) $ci->id,
            ];
        }

        $first = array_shift($pending);
        $flow['pending_items'] = $pending;

        $vendorIdInt = (int) Str::after($flow['vendor_id'], 'vendor_');
        $firstItemId = (int) preg_replace('/\D+/', '', $first['id']);
        $itemData = $this->vendorDataService->getItemBasic($firstItemId, $vendorIdInt, $locale);

        $flow['current_addon_groups'] = $itemData['addon_groups'] ?? [];
        $flow['locale'] = $locale;

        $flattenedAddons = $this->flattenAddonGroups($flow['current_addon_groups'], $locale);

        return $this->buildScreenResponse('ITEM_QTY', [
            'item_title' => $itemData['title'] ?? ($first['title'] ?? 'Item'),
            'item_description' => trim(($itemData['description'] ?? '')."\n**Price:** ".number_format((float) ($itemData['price'] ?? 0), 3).' KWD'),
            'image_src' => (string) ($itemData['image'] ?? ''),
            'item_prompt' => $this->getFlowMessage('how_many', $locale),
            'item_id' => $first['id'],
            'cart_item_id' => $first['cart_item_id'],
            'qty_options' => $this->buildQtyOptions(),
            'addons' => $flattenedAddons,
            'show_addons' => ! empty($flattenedAddons),
            'has_multiple_actions' => true,
            'item_actions' => $this->removeActions($locale),
            'footer_label' => $locale === 'ar' ? 'حفظ التغييرات' : 'Save changes',
            'footer_action_id' => 'save',
            'flow_data' => $flow,
            'show_error_message' => false,
            '__session__' => $session,
        ]);
    }

    private function handleSavedAddressSelection(string $choice, array $flow, ?WhatsappSession $session, string $locale): array
    {
        $addr = data_get($flow, "addr_map.$choice");
        if (! $addr) {
            return $this->bootAddressStep($session, $locale, $flow, '⚠️ Address not found. Please select again.');
        }

        $session->update([
            'delivery_state_id' => $addr['state_id'] ?? null,
            'delivery_city_id' => $addr['city_id'] ?? null,
            'delivery_address' => json_encode($addr),
            'flow_street' => $addr['street'] ?? null,
            'flow_block_id' => $addr['block_id'] ?? null,
            'flow_house_no' => $addr['house_no'] ?? null,
        ]);

        $flow = array_merge($flow, [
            'state_id' => $addr['state_id'] ?? null,
            'city_id' => $addr['city_id'] ?? null,
        ]);

        // FIX: The reorder decision is now made in the parent `handleAddressSaved` method.
        // This method is now only responsible for setting the address and moving to the next step of a new order.
        return $this->_getNextScreenAfterAddress($session, $flow, $locale);
    }

    // ------------------------------------------------------------------
    // Address / boot helpers
    // ------------------------------------------------------------------

    private function handleNewAddressFlow(array $form, array $flow, ?WhatsappSession $session, string $locale): array
    {
        $stateId = $form['state_id'] ?? null;
        $cityId = $form['city_id'] ?? null;
        $states = $this->getStates($locale);
        $flow['show_states'] = true;

        if ($stateId && $cityId) {
            if (City::where('id', $cityId)->where('state_id', $stateId)->exists()) {
                $session->update(['delivery_state_id' => $stateId, 'delivery_city_id' => $cityId]);
                $flow = array_merge($flow, ['state_id' => $stateId, 'city_id' => $cityId, 'locale' => $locale]);

                return $this->_getNextScreenAfterAddress($session, $flow, $locale);
            }

            return $this->buildScreenResponse('ADDRESS_SAVED', [
                'reorder_options' => $this->reorderOptions($locale),
                'addressOptions' => [],
                'states' => $states,
                'cities' => $this->getCitiesForState($stateId, $locale),
                'error_message' => '⚠️ Invalid state or city selection. Please try again.',
                'show_error_message' => true,
                'show_addresses' => false,
                'show_states' => true,
                'show_city' => true,
                'footer_label' => 'Next',
                'flow_data' => $flow,
                '__session__' => $session,
            ]);
        }

        if ($stateId) {
            $flow['show_city'] = true;
            $flow['state_id'] = $stateId;

            return $this->buildScreenResponse('ADDRESS_SAVED', [
                'reorder_options' => $this->reorderOptions($locale),
                'addressOptions' => [],
                'states' => $states,
                'cities' => $this->getCitiesForState($stateId, $locale),
                'show_addresses' => false,
                'show_states' => true,
                'show_city' => true,
                'footer_label' => 'Next',
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        return $this->buildScreenResponse('ADDRESS_SAVED', [
            'reorder_options' => $this->reorderOptions($locale),
            'addressOptions' => [],
            'states' => $states,
            'cities' => [],
            'show_addresses' => false,
            'show_states' => true,
            'show_city' => false,
            'footer_label' => 'Next',
            'flow_data' => $flow,
            'show_error_message' => false,
            '__session__' => $session,
        ]);
    }

    private function _getNextScreenAfterAddress(?WhatsappSession $session, array $flow, string $locale): array
    {
        // Case 1: A specific vendor was found via Smart Search.
        if ($session && $session->direct_intent_restaurant_id) { // UPDATED
            $vendorId = $session->direct_intent_restaurant_id; // UPDATED
            $categories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);

            // Populate the flow context from the session
            $flow['business_type_id'] = $session->direct_intent_business_type_id;
            $flow['vendor_category_id'] = 'category_'.$session->direct_intent_cuisine_id; // UPDATED
            $flow['vendor_id'] = 'vendor_'.$vendorId;

            // Clear the intent now that it has been used
            $session->update([
                'direct_intent_restaurant_id' => null, // UPDATED
                'direct_intent_cuisine_id' => null, // UPDATED
                'direct_intent_business_type_id' => null,
            ]);

            // JUMP DIRECTLY to that vendor's own menu categories
            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $categories,
                'vendor_id' => $flow['vendor_id'],
                'vendor_category_id' => $flow['vendor_category_id'],
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        // Case 2: A cuisine/vendor category was found
        if ($session && $session->direct_intent_cuisine_id) { // UPDATED
            $vendorCategoryId = $session->direct_intent_cuisine_id; // UPDATED
            $cityId = $session->delivery_city_id;
            $vendors = $this->vendorDataService->getVendorsForCategory($vendorCategoryId, $locale, (int) $cityId);

            $flow['business_type_id'] = $session->direct_intent_business_type_id;
            $flow['vendor_category_id'] = 'category_'.$vendorCategoryId;

            $session->update([
                'direct_intent_restaurant_id' => null, // UPDATED
                'direct_intent_cuisine_id' => null, // UPDATED
                'direct_intent_business_type_id' => null,
            ]);

            // Jump to the list of vendors in that category
            return $this->buildScreenResponse('SELECT_VENDOR', [
                'vendors' => $vendors,
                'flow_data' => $flow,
                'show_error_message' => false,
                '__session__' => $session,
            ]);
        }

        // Case 3: Default flow (no smart search was used)
        $btId = is_numeric($flow['business_type_id'] ?? null) ? (int) $flow['business_type_id'] : null;
        $catLabel = $this->btLabel($btId, 'category_label', $locale, $locale === 'ar' ? 'فئة' : 'category');

        return $this->buildScreenResponse('SELECT_VENDOR_CATEGORY', [
            'vendor_categories' => $this->vendorDataService->getCategoriesForBusinessType($flow['business_type_id'] ?? null, $locale),
            'category_selection_prompt' => $locale === 'ar'
                ? "أولاً، يرجى اختيار {$catLabel}"
                : "First, please select a {$catLabel}",
            'flow_data' => $flow,
            'show_error_message' => false,
            '__session__' => $session,
        ]);
    }

    private function bootAddressStep(
        ?WhatsappSession $session,
        string $locale,
        array $flow,
        ?string $error = null
    ): array {
        $profile = $session?->customerProfile;
        $addresses = $profile?->addresses ?? [];
        $states = $this->getStates($locale);
        $showReorder = $this->hasPreviousOrders($session, $flow);

        // No saved addresses → user must add a new one now
        if (empty($addresses)) {
            return $this->buildScreen('ADDRESS_SAVED', [
                'addressOptions' => [],
                'states' => $states,
                'cities' => [],
                'show_addresses' => false,
                'show_states' => true,    // ← only here
                'show_city' => false,
                'footer_label' => $locale === 'ar' ? 'التالي' : 'Next',
                'flow_data' => $flow,
                'error_message' => $error ?: '',
                'show_error_message' => (bool) $error,
                'show_reorder_option' => $showReorder,
                'reorder_options' => $showReorder ? $this->reorderOptions($locale) : [],
            ]);
        }

        // Saved addresses exist → list them first; hide state/city for now
        [$opts, $map] = $this->buildAddressOptions($addresses, $locale);
        $flow['addr_map'] = $map;

        return $this->buildScreen('ADDRESS_SAVED', [
            'addressOptions' => $opts,
            'states' => $states,
            'cities' => [],
            'show_addresses' => true,
            'show_states' => false,  // ← changed
            'show_city' => false,  // ← changed
            'footer_label' => $locale === 'ar' ? 'متابعة' : 'Continue',
            'flow_data' => $flow,
            'error_message' => $error ?: '',
            'show_error_message' => (bool) $error,
            'show_reorder_option' => $showReorder,
            'reorder_options' => $showReorder ? $this->reorderOptions($locale) : [],
        ]);
    }

    private function bootBusinessTypeStep(string $locale, array $flow, ?string $error = null, ?WhatsappSession $session = null): array
    {
        return $this->buildScreen('SELECT_BUSINESS_TYPE', [
            'business_types' => $this->vendorDataService->getBusinessTypes($locale),
            'error_message' => $error ?? '',
            'show_error_message' => (bool) $error,
            'flow_data' => $flow,
            '__session__' => $session,
        ]);
    }

    // ------------------------------------------------------------------
    // Promo / cart / summary helpers
    // ------------------------------------------------------------------

    private function validateAndApplyPromo(array &$flow, WhatsappSession $session, string $locale): ?string
    {
        if (empty($flow['promo_code'])) {
            return null;
        }

        $items = $session->cartItems
            ->map(fn ($ci) => ['id' => $ci->item_id_from_restaurant, 'qty' => $ci->quantity])
            ->toArray();

        $vendorIdInt = (int) Str::after($flow['vendor_id'] ?? '', 'vendor_');
        $result = $this->vendorDataService->validatePromoCode($vendorIdInt, $flow['promo_code'], $items, $locale);

        if (! ($result['valid'] ?? false)) {
            return '⚠️ '.$result['message'];
        }

        $flow['discount_amount'] = $result['discount_amount'] ?? 0.0;

        return null;
    }

    protected function buildQtyOptions(): array
    {
        return collect(range(1, 10))->map(fn ($i) => ['id' => (string) $i, 'title' => (string) $i])->toArray();
    }

    private function hasFullAddress(array $f): bool
    {
        if (($f['order_type'] ?? 'delivery') !== 'delivery') {
            return true;
        }

        return ! empty($f['state_id']) && ! empty($f['city_id']) && ! empty($f['block_id']) && ! empty($f['street']);
    }

    private function cartActionsAndMinMsg(WhatsappSession $session, array $flow, string $locale): array
    {
        $items = $session->cartItems()->get();
        $isAr = $locale === 'ar';
        $curr = $isAr ? 'د.ك' : 'KWD';

        $total = $items->sum(function ($ci) {
            $addons = collect($ci->variations)->sum('price');

            return ($ci->price + $addons) * $ci->quantity;
        });

        $vendorId = (int) Str::after($flow['vendor_id'] ?? '', 'vendor_');
        $minOrder = (float) $this->minOrderForCity($session->delivery_city_id, $vendorId);
        $remaining = ($minOrder > 0) ? max(0, $minOrder - $total) : 0;

        $minMsg = '';
        $actions = [];

        if ($remaining > 0) {
            $minOrderF = '**'.number_format($minOrder, 3)." {$curr}**";
            $remainingF = '**'.number_format($remaining, 3)." {$curr}**";
            $minMsg = $isAr
                ? "الحد الأدنى للطلب هو {$minOrderF}. أضف {$remainingF} لإتمام الطلب."
                : "Minimum order is {$minOrderF}. Add {$remainingF} more to proceed.";
        } else {
            $minMsg = ($minOrder > 0)
                ? ($isAr ? ' **لقد وصلت إلى الحد الأدنى للطلب!**' : ' **You have met the minimum order!**')
                : '';
            $actions = [
                ['id' => 'add_more', 'title' => $isAr ? '➕ إضافة المزيد' : '➕ Add more items'],
                ['id' => 'checkout', 'title' => $isAr ? ' إتمام الطلب' : ' Checkout'],
            ];
        }

        return [$minMsg, $actions];
    }

    private function makeOrderSummary(WhatsappSession $session, array $flow, string $locale): string
    {
        $isAr = $locale === 'ar';
        $curr = $isAr ? 'د.ك' : 'KWD';
        $lines = [];
        $total = 0.0;

        $lines[] = '📋 **'.($isAr ? 'ملخص طلبك' : 'Your Order Summary').'**';
        $lines[] = '';

        foreach ($session->cartItems as $ci) {
            $basePrice = (float) $ci->price;
            $addons = $ci->variations ?? [];
            $addonTotal = collect($addons)->sum('price');
            $lineTotal = ($basePrice + $addonTotal) * $ci->quantity;
            $total += $lineTotal;

            $lines[] = "🛒 **{$ci->quantity}× {$ci->item_name}** (@ ".number_format($basePrice, 3)." {$curr})";

            foreach ($addons as $ad) {
                $cleanTitle = $this->cleanAddonTitle($ad['title']);
                $lines[] = "  + {$cleanTitle} (+ ".number_format($ad['price'], 3)." {$curr})";
            }
            $lines[] = '';
        }

        $subtotal = $total;
        $fee = (float) ($flow['delivery_fee'] ?? 0);
        $discount = (float) ($flow['discount_amount'] ?? 0);
        $grand = max(0, $subtotal + $fee - $discount);

        $lines[] = '--------------------';
        $lines[] = '💰 '.($isAr ? 'المجموع الفرعي' : 'Subtotal').': **'.number_format($subtotal, 3)." {$curr}**";
        if ($fee > 0) {
            $lines[] = '🚚 '.($isAr ? 'رسوم التوصيل' : 'Delivery Fee').': **'.number_format($fee, 3)." {$curr}**";
        }
        if ($discount > 0) {
            $lines[] = '🎟️ '.($isAr ? 'الخصم' : 'Discount').': **-'.number_format($discount, 3)." {$curr}**";
        }
        $lines[] = '💳 **'.($isAr ? 'الإجمالي النهائي' : 'Grand Total').': '.number_format($grand, 3)." {$curr}**";
        $lines[] = '';
        $lines[] = '👤 **'.($isAr ? 'بيانات العميل' : 'Customer Details').'**';
        $lines[] = '--------------------';
        $lines[] = '**'.($isAr ? 'الاسم' : 'Name').':** *'.($flow['name'] ?? 'N/A').'*';

        $phone = $flow['customer_phone'] ?? $session->customer_phone_number;
        if ($phone) {
            $lines[] = '**'.($isAr ? 'الهاتف' : 'Phone').':** '.$phone;
        }

        if (($flow['order_type'] ?? 'delivery') === 'delivery') {
            $lines[] = '';
            $lines[] = '🚚 **'.($isAr ? 'عنوان التوصيل' : 'Delivery Address').'**';
            $lines[] = '--------------------';
            $state = $this->getStateName($flow['state_id'] ?? null, $locale);
            $city = $this->getCityName($flow['city_id'] ?? null, $locale);
            $block = $this->getBlockName($flow['block_id'] ?? null, $locale);

            $lines[] = implode(', ', array_filter([$state, $city, $block]));
            $lines[] = '**'.($isAr ? 'الشارع' : 'Street').':** *'.($flow['street'] ?? 'N/A').'*';
            if (! empty($flow['house_no'])) {
                $lines[] = '**'.($isAr ? 'المنزل/الشقة' : 'House/Apt').':** '.$flow['house_no'];
            }
        } else {
            $lines[] = '';
            $lines[] = '🛍️ **'.($isAr ? 'نوع الطلب' : 'Order Type').':** '.($isAr ? 'استلام' : 'Pickup');
        }

        if (! empty($flow['notes'])) {
            $lines[] = '';
            $lines[] = '📝 **'.($isAr ? 'ملاحظات' : 'Notes').'**';
            $lines[] = '> '.$flow['notes'];
        }

        return implode("\n", $lines);
    }

    private function summarizeCart(WhatsappSession $session, string $locale = 'en'): string
    {
        $cartItems = $session->cartItems()->get();
        if ($cartItems->isEmpty()) {
            return $locale === 'ar' ? 'السلة فارغة' : 'Cart is empty';
        }

        $isAr = $locale === 'ar';
        $curr = $isAr ? 'د.ك' : 'KWD';
        $lines = [];
        $total = 0;

        foreach ($cartItems as $item) {
            $basePrice = (float) $item->price;
            $addons = $item->variations ?? [];
            $addonTotal = collect($addons)->sum('price');
            $lineTotal = ($basePrice + $addonTotal) * $item->quantity;
            $total += $lineTotal;

            $lines[] = "**{$item->quantity}× {$item->item_name}** (@ ".number_format($basePrice, 3)." {$curr})";

            foreach ($addons as $addon) {
                $cleanTitle = $this->cleanAddonTitle($addon['title']);
                $lines[] = "  + {$cleanTitle} (+ ".number_format($addon['price'], 3)." {$curr})";
            }

            $lines[] = '';
        }

        $lines[] = '--------------------';
        $lines[] = '**'.($isAr ? 'المجموع: ' : 'Total: ').'**'.number_format($total, 3)." {$curr}";

        return implode("\n", $lines);
    }

    // ------------------------------------------------------------------
    // Address + lookup helpers
    // ------------------------------------------------------------------

    private function persistAddressFromFlow(WhatsappSession $session, array $flow): void
    {
        if (! $profile = $session->customerProfile) {
            return;
        }

        $locale = $flow['locale'] ?? $session->locale ?? 'en';
        $label = $flow['address_type_label']
   ?? $this->addressTypeLabel($flow['address_type'] ?? null, $locale);

        $addr = [
            'address_type' => $flow['address_type'] ?? 1,
            'address' => $label,
            'state_id' => (int) ($flow['state_id'] ?? 0),
            'city_id' => (int) ($flow['city_id'] ?? 0),
            'block_id' => $flow['block_id'] ?? null,
            'street' => $flow['street'] ?? '',
            'house_no' => $flow['house_no'] ?? '',
        ];

        $profile->saveAddress($addr);
    }

    private function rehydrateAddressFromSession(array &$flow, ?WhatsappSession $session): void
    {
        if (! $session) {
            return;
        }

        $flow['state_id'] ??= $session->delivery_state_id;
        $flow['city_id'] ??= $session->delivery_city_id;
        $flow['street'] ??= $session->flow_street;
        $flow['block_id'] ??= $session->flow_block_id;
        $flow['house_no'] ??= $session->flow_house_no;
    }

    public function getStates($locale = 'en')
    {
        return State::orderBy('state_name')->get()->map(
            fn ($s) => ['id' => (string) $s->id, 'title' => $locale === 'ar' ? $s->state_name_ar : $s->state_name]
        )->toArray();
    }

    public function getCitiesForState($stateId, $locale = 'en')
    {
        return City::where('state_id', $stateId)->orderBy('city_name')->get()->map(
            fn ($c) => ['id' => (string) $c->id, 'title' => $locale === 'ar' ? $c->city_name_ar : $c->city_name]
        )->toArray();
    }

    public function getBlocksForCity($cityId, $locale = 'en')
    {
        return Block::where('city_id', $cityId)->orderBy('name_en')->get()->map(
            fn ($b) => ['id' => (string) $b->id, 'title' => $locale === 'ar' ? $b->name_ar : $b->name_en]
        )->toArray();
    }

    public function getStateName($stateId, $locale = 'en')
    {
        if (! $stateId) {
            return null;
        }
        $state = State::find($stateId);

        return $state ? ($locale === 'ar' ? $state->state_name_ar : $state->state_name) : null;
    }

    public function getCityName($cityId, $locale = 'en')
    {
        if (! $cityId) {
            return null;
        }
        $city = City::find($cityId);

        return $city ? ($locale === 'ar' ? $city->city_name_ar : $city->city_name) : null;
    }

    public function getBlockName($blockId, $locale = 'en')
    {
        if (! $blockId) {
            return null;
        }
        $block = Block::find($blockId);

        return $block ? ($locale === 'ar' ? $block->name_ar : $block->name_en) : null;
    }

    private function minOrderForCity(?int $cityId, ?int $vendorId = null): ?float
    {
        if (! $cityId || ! $vendorId) {
            return null;
        }

        $area = DeliveryArea::where('city_id', $cityId)->whereHas('branch', function ($q) use ($vendorId) {
            $q->where('vendor_id', $vendorId)->where('is_active', true);
        })->first();

        return $area?->min_order_value !== null ? (float) $area->min_order_value : null;
    }

    private function deliveryFee(?int $vendorId, ?int $cityId): ?float
    {
        if (! $cityId || ! $vendorId) {
            return null;
        }

        $area = DeliveryArea::where('city_id', $cityId)->whereHas('branch', function ($q) use ($vendorId) {
            $q->where('vendor_id', $vendorId)->where('is_active', true);
        })->first();

        return $area?->delivery_fee !== null ? (float) $area->delivery_fee : null;
    }

    // ------------------------------------------------------------------
    // Addon / Item helpers
    // ------------------------------------------------------------------

    private function flattenAddonGroups(array $groups, string $locale): array
    {
        $flatList = [];
        if (empty($groups)) {
            return [];
        }

        foreach ($groups as $group) {
            foreach ($group['addons'] ?? [] as $option) {
                $price = (float) ($option['price'] ?? 0);

                // Get the array of name translations
                $nameTranslations = $option['name'] ?? [];

                // Select the title based on the locale, with a fallback to 'en'
                $title = $nameTranslations[$locale] ?? ($nameTranslations['en'] ?? 'Addon');
                $isAr = $locale === 'ar';
                $curr = $isAr ? 'د.ك' : 'KWD';
                $flatList[] = [
                    'id' => "{$group['id']}-{$option['id']}",
                    'title' => $title, // <-- This is now guaranteed to be a string

                    'description' => $price > 0
                       ? '+ '.number_format($price, 3).' '.$curr
                       : ($isAr ? 'مجاني' : 'Included'),
                ];
            }
        }

        return $flatList;
    }

    // ------------------------------------------------------------------
    // Images / UI helpers
    // ------------------------------------------------------------------

    private function cleanAddonTitle(string $t): string
    {
        $pattern = '/\s*\+\s*[\d\.,]+\s*(KWD|KD|د\.?\s*ك)\s*$/iu';
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $t = str_replace($ar, $en, $t);

        return preg_replace($pattern, '', $t);
    }

    private function imageRawBase64FromMixed(?string $raw): string
    {
        if (! $raw) {
            return '';
        }

        if (str_starts_with($raw, 'data:')) {
            return $this->stripDataUriPrefix($raw);
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $dataUri = $this->FlowImageService->dataUriFromUrl($raw);

            return $this->stripDataUriPrefix($dataUri ?: '');
        }

        $dataUri = $this->FlowImageService->dataUriFromStorage($raw);

        return $this->stripDataUriPrefix($dataUri ?: '');
    }

    private function resolveItemImageUrl(?string $raw): string
    {
        if (! $raw) {
            return 'https://via.placeholder.com/800';
        }
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://') || str_starts_with($raw, 'data:')) {
            return $raw;
        }
        $url = $this->FlowImageService->absoluteUrlFromStorage($raw);

        return $url ?: 'https://via.placeholder.com/800';
    }

    private function removeIconB64(): string
    {
        $base = rtrim(config('app.url') ?: 'https://bannerkw.com', '/');
        $url = $base.'/images/flow/remove.png';

        return $this->FlowImageService->dataUriFromStorage('flow/remove.png')
            ?? $this->FlowImageService->dataUriFromUrl($url)
            ?? '';
    }

    private function reorderIconB64(): string
    {
        $base = rtrim(config('app.url') ?: 'https://bannerkw.com', '/');
        $url = $base.'/images/flow/reorder.png';

        return $this->FlowImageService->dataUriFromStorage('flow/reorder.png')
            ?? $this->FlowImageService->dataUriFromUrl($url)
            ?? '';
    }

    private function loadMoreIconB64(): string
    {
        $base = rtrim(config('app.url') ?: 'https://bannerkw.com', '/');
        $url = $base.'/images/flow/load-more.png';

        return $this->FlowImageService->dataUriFromStorage('flow/load-more.png')
            ?? $this->FlowImageService->dataUriFromUrl($url)
            ?? '';
    }

    private function stripDataUriPrefix(?string $s): string
    {
        if (! $s) {
            return '';
        }
        $pos = strpos($s, ',');
        if ($pos !== false && str_starts_with($s, 'data:image/')) {
            return substr($s, $pos + 1);
        }

        return $s;
    }

    private function removeActions(string $locale): array
    {
        $imgRaw = $this->stripDataUriPrefix($this->removeIconB64());

        return [[
            'id' => 'remove',
            'title' => $locale === 'ar' ? '🗑️ إزالة من السلة' : '🗑️ Remove from cart',
            'description' => $locale === 'ar' ? 'احذف هذا العنصر من سلة الشراء.' : 'Delete this item from your cart.',
            'alt-text' => 'Remove from cart',
            'image' => $imgRaw,
        ]];
    }

    private function reorderOptions(string $locale): array
    {
        $imgRawB64 = $this->stripDataUriPrefix($this->reorderIconB64());
        $isAr = ($locale === 'ar');

        $title = $isAr ? '🕒 إعادة طلب' : '🕒 Reorder';
        $desc = $isAr ? 'اعرض وأعِد طلب آخر مشترياتك بسرعة.' : 'Quickly re-order your recent purchases.';
        $alt = $isAr ? 'إعادة طلب' : 'Reorder option';

        return [[
            'id' => 'reorder',
            'title' => $title,
            'description' => $desc,
            'alt-text' => $alt,
            'image' => $imgRawB64,
        ]];
    }

    private function loadMoreItem(string $locale): array
    {
        $imgRaw = $this->stripDataUriPrefix($this->loadMoreIconB64());

        $item = [
            'id' => 'pager_more',
            'title' => $locale === 'ar' ? 'عرض المزيد' : 'Load more results',
            'description' => $locale === 'ar'
                ? 'اعرض مطاعم إضافية للمطبخ المختار.'
                : 'Show additional restaurants for this cuisine.',
        ];

        if ($imgRaw !== '') {
            $item['image'] = $imgRaw;
        }

        return $item;
    }

    private function noResultsItems(string $locale): array
    {
        return [
            [
                'id' => 'clear_search',
                'title' => $locale === 'ar' ? 'مسح البحث' : 'Clear search',
                'description' => $locale === 'ar'
                    ? 'اعرض جميع المطاعم لهذا المطبخ.'
                    : 'Show all restaurants for this cuisine.',
            ],
            [
                'id' => 'back_to_cuisines',
                'title' => $locale === 'ar' ? 'العودة لاختيار المطبخ' : 'Back to cuisines',
                'description' => $locale === 'ar'
                    ? 'الرجوع خطوة للخلف لاختيار مطبخ آخر.'
                    : 'Go back to pick a different cuisine.',
            ],
        ];
    }

    private function shortTitle(array $addressData, string $locale): string
    {
        $mapEn = [
            '1' => '🏠 Home', 'home' => '🏠 Home',
            '2' => '🏢 Office', 'office' => '🏢 Office',
            '3' => '🏬 Apartment', 'apartment' => '🏬 Apartment',
        ];
        $mapAr = [
            '1' => '🏠 المنزل', 'home' => '🏠 المنزل',
            '2' => '🏢 المكتب', 'office' => '🏢 المكتب',
            '3' => '🏬 شقة',    'apartment' => '🏬 شقة',
        ];

        $map = $locale === 'ar' ? $mapAr : $mapEn;
        $key = null;

        if (! empty($addressData['address_type'])) {
            $key = (string) $addressData['address_type'];
        } elseif (! empty($addressData['slug'])) {
            $key = strtolower($addressData['slug']);
        }

        $label = $map[$key] ?? ($locale === 'ar' ? '📍 العنوان' : '📍 Address');

        $parts = array_filter([
            $label,
            $addressData['state_name'] ?? null,
            $addressData['city_name'] ?? null,
            ($addressData['block_id'] ?? null)
                ? ($locale === 'ar' ? "قطعة {$addressData['block_id']}" : "Block {$addressData['block_id']}")
                : null,
            ($addressData['street'] ?? null)
                ? ($locale === 'ar' ? "شارع {$addressData['street']}" : "St {$addressData['street']}")
                : null,
        ]);

        return implode(' • ', $parts);
    }

    private function buildAddressOptions(array $addresses, string $locale): array
    {
        $opts = [];
        $map = [];

        foreach ($addresses as $i => $raw) {
            if (blank(data_get($raw, 'street'))) {
                continue;
            }

            if (! isset($raw['state_name']) && isset($raw['state_id'])) {
                $raw['state_name'] = $this->getStateName($raw['state_id'], $locale);
            }
            if (! isset($raw['city_name']) && isset($raw['city_id'])) {
                $raw['city_name'] = $this->getCityName($raw['city_id'], $locale);
            }

            $slug = data_get($raw, 'slug');
            if (blank($slug)) {
                $slug = Str::slug($raw['label'] ?? 'addr_'.$i);
                $raw['slug'] = $slug;
            }

            $id = "addr_{$slug}_{$i}";
            $title = $this->shortTitle($raw, $locale);

            $opts[] = ['id' => $id, 'title' => $title];
            $map[$id] = $raw;
        }

        $opts[] = ['id' => 'ADD_NEW', 'title' => $locale === 'ar' ? '➕ عنوان جديد' : '➕ New address'];

        return [$opts, $map];
    }

    // ------------------------------------------------------------------
    // Text normalization / filtering
    // ------------------------------------------------------------------

    private function filterRestaurantsBySearch(array $restaurants, string $term): array
    {
        return $this->filterListBySearch($restaurants, $term);
    }

    private function filterListBySearch(array $list, string $term): array
    {
        $needle = $this->normalizeText($term);
        if ($needle === '') {
            return $list;
        }
        $out = [];

        foreach ($list as $item) {
            $title = $this->normalizeText((string) ($item['title'] ?? ''));
            $desc = $this->normalizeText((string) ($item['description'] ?? ''));

            if (mb_strpos($title, $needle, 0, 'UTF-8') !== false ||
                mb_strpos($desc, $needle, 0, 'UTF-8') !== false) {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function normalizeText(string $s): string
    {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);

        $map = [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
            'ئ' => 'ي',
            'ؤ' => 'و',
            'ﻻ' => 'لا', 'لأ' => 'لا', 'لإ' => 'لا', 'لآ' => 'لا',
        ];
        $s = strtr($s, $map);

        $arDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $enDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $s = str_replace($arDigits, $enDigits, $s);

        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }

    // ------------------------------------------------------------------
    // Pagination helpers
    // ------------------------------------------------------------------

    private function paginateRestaurants(array $list, int $page, int $pageSize = 19): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $pageSize;
        $slice = array_slice($list, $offset, $pageSize);
        $hasMore = ($offset + $pageSize) < count($list);

        return [$slice, $hasMore];
    }

    private function paginate(array $list, int $page, int $pageSize): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $pageSize;
        $slice = array_slice($list, $offset, $pageSize);
        $hasMore = ($offset + $pageSize) < count($list);

        return [$slice, $hasMore];
    }

    // ------------------------------------------------------------------
    // Totals / promos
    // ------------------------------------------------------------------

    private function orderTotalKwd(\App\Wa\Models\Order $order): float
    {
        $t = (float) ($order->total ?? 0);
        if ($t > 0) {
            return $t;
        }

        $sum = 0.0;
        foreach ($order->items as $it) {
            $base = (float) ($it->price ?? 0);
            $addons = 0.0;

            $rawAddons = $it->addons ?? [];
            if (is_array($rawAddons)) {
                foreach ($rawAddons as $ad) {
                    $addons += is_array($ad) ? (float) ($ad['price'] ?? 0) : (float) $ad;
                }
            }

            $qty = (int) ($it->quantity ?? 0);
            $sum += ($base + $addons) * $qty;
        }

        $delivery = (float) ($order->delivery_fee ?? 0);
        $discount = (float) ($order->discount ?? 0);

        return max(0.0, $sum + $delivery - $discount);
    }

    private function mapAddonIdsToDetails(array $selectedCompositeIds, array $allAddonGroupsFromApi, string $locale): array
    {
        if (empty($selectedCompositeIds) || empty($allAddonGroupsFromApi)) {
            return [];
        }

        $lookup = array_flip($selectedCompositeIds);
        $detailedAddons = [];

        foreach ($allAddonGroupsFromApi as $group) {
            // CORRECTED: The key should be 'addons' to match the data structure
            foreach ($group['addons'] ?? [] as $option) {
                $currentCompositeId = "{$group['id']}-{$option['id']}";
                if (isset($lookup[$currentCompositeId])) {

                    // Get the array of name translations
                    $nameTranslations = $option['name'] ?? [];

                    // Select the title based on the locale, with a fallback to 'en'
                    $title = $nameTranslations[$locale] ?? ($nameTranslations['en'] ?? 'Addon');

                    $detailedAddons[] = [
                        'id' => $currentCompositeId,
                        'title' => $title, // <-- Use the selected string title
                        'price' => $option['price'] ?? 0,
                    ];
                }
            }
        }

        return $detailedAddons;
    }

    private function removeItemsOfCategoryNotInSelection(WhatsappSession $session, array $selectedIds, array $flow, string $locale): void
    {
        $categoryIdRaw = $flow['category_id_raw'] ?? $flow['category_id'] ?? null;
        $vendorIdRaw = $flow['vendor_id'] ?? null;

        if (! $categoryIdRaw || ! $vendorIdRaw) {
            return;
        }

        $allIdsInCat = collect($this->vendorDataService->getItemsForCategorySimple($categoryIdRaw, $vendorIdRaw, $locale))
            ->map(fn ($i) => (int) preg_replace('/\D+/', '', $i['id']))
            ->all();

        $toDelete = array_diff($allIdsInCat, $selectedIds);
        if (empty($toDelete)) {
            return;
        }

        \App\Wa\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)
            ->whereIn('item_id_from_restaurant', $toDelete)
            ->delete();
    }

    // ------------------------------------------------------------------
    // Outbound flows (sending)
    // ------------------------------------------------------------------

    private function flowIdFor(string $locale): string
    {
        $locale = $this->normalizeLocale($locale);

        $key = $locale === 'ar'
            ? 'services.whatsapp.menu_flow_id_ar'
            : 'services.whatsapp.menu_flow_id_en';

        $flowId = config($key);

        // HARD GUARD: never return null
        if (! is_string($flowId) || trim($flowId) === '') {
            Log::critical('[WhatsApp Flow] Missing flow id config', [
                'locale' => $locale,
                'config_key' => $key,
                'menu_flow_id_ar' => config('services.whatsapp.menu_flow_id_ar'),
                'menu_flow_id_en' => config('services.whatsapp.menu_flow_id_en'),
            ]);

            // If you want: fall back to the other locale if available
            $fallbackKey = $locale === 'ar'
                ? 'services.whatsapp.menu_flow_id_en'
                : 'services.whatsapp.menu_flow_id_ar';

            $fallback = config($fallbackKey);
            if (is_string($fallback) && trim($fallback) !== '') {
                return $fallback;
            }

            // Final: return a safe empty string or throw (I recommend throw)
            throw new \RuntimeException("WhatsApp Flow ID is not configured for locale: {$locale}");
        }

        return trim($flowId);
    }

    public function sendInitialFlow(string $to, string $locale): void
    {
        $locale = $this->normalizeLocale($locale);

        try {
            $flowId = $this->flowIdFor($locale);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp Flow] Cannot send flow (missing config)', [
                'to' => $to,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            // Optional: send a normal text instead of crashing webhook
            $this->whatsAppService->sendTextMessage(
                $to,
                $locale === 'ar'
                    ? '⚠️ الخدمة غير مهيأة حالياً. يرجى التواصل مع الدعم.'
                    : '⚠️ Service is not configured yet. Please contact support.'
            );

            return;
        }

        $flowToken = Str::uuid()->toString();

        $session = WhatsappSession::updateOrCreate(
            ['customer_phone_number' => $to],
            ['flow_token' => $flowToken, 'status' => 'active', 'locale' => $locale]
        );

        $screenData = [
            'business_types' => $this->vendorDataService->getBusinessTypes($locale),
            'flow_data' => $this->coerceFlowData([], $session),
        ];

        // UPDATED: More engaging and professional text with emojis
        $this->sendFlowNavigatePayload(
            $to, $flowId, $flowToken, $locale, 'SELECT_BUSINESS_TYPE', $screenData,
            '🛒 ابدأ طلبك', '🛒 Start Your Order',
            'اضغط أدناه لاستكشاف خدماتنا، من توصيل الطعام اللذيذ إلى مستلزمات الصيدلية والمزيد.', 'Tap below to explore our services, from food delivery to pharmacy essentials and more.',
            ' تصفح الخدمات', ' Browse Services'
        );
    }

    public function sendAddressFlow(string $to, string $locale): void
    {
        $flowId = $this->flowIdFor($locale);
        $flowToken = Str::uuid()->toString();

        $session = WhatsappSession::updateOrCreate(
            ['customer_phone_number' => $to],
            ['flow_token' => $flowToken, 'status' => 'active', 'locale' => $locale]
        );

        $profile = $session->customerProfile;
        $addresses = $profile?->addresses ?? [];
        [$options, $map] = $this->buildAddressOptions($addresses, $locale);

        $headerText = $locale === 'ar' ? '📍 أين تريد توصيل طلبك؟' : '📍 Where should we deliver your order?';
        $bodyText = $locale === 'ar' ? 'اختر عنواناً محفوظاً أو أضف عنواناً جديداً للاستمرار في الطلب.' : 'Tap a saved address or add a new one to continue.';
        $buttonText = $locale === 'ar' ? 'اختر العنوان' : 'Select Address';

        $flowData = $this->coerceFlowData([], $session);
        $showReorder = $this->hasPreviousOrders($session, $flowData);

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => ['type' => 'text', 'text' => $headerText],
                'body' => ['text' => $bodyText],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_id' => $flowId,
                        'flow_cta' => $buttonText,
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => 'ADDRESS_SAVED',
                            'data' => [
                                'addressOptions' => $options,
                                'states' => $this->getStates($locale),
                                'cities' => [],
                                'show_addresses' => true,
                                'show_states' => false,
                                'show_city' => false,
                                'footer_label' => $buttonText,
                                'flow_data' => $this->coerceFlowData(['addr_map' => $map], $session),
                                'show_error_message' => false,
                                'show_reorder_option' => $showReorder,
                                'reorder_options' => $showReorder ? $this->reorderOptions($locale) : [],
                            ],
                        ],
                        'flow_token' => $flowToken,
                    ],
                ],
            ],
        ];

        $this->whatsAppService->send($to, $payload);
    }

    public function sendCheckoutFlow(string $to, array $cartSummary, string $locale = 'en')
    {
        $states = $this->getStates($locale);
        $customer = CustomerProfile::where('phone_number', $to)->first();
        $saved = $customer?->addresses ?? [];

        $defaultStateId = $cartSummary['state_id'] ?? ($states[0]['id'] ?? null);
        $cities = $defaultStateId ? $this->getCitiesForState($defaultStateId, $locale) : [];
        $defaultCityId = $cartSummary['city_id'] ?? ($cities[0]['id'] ?? null);
        $blocks = $defaultCityId ? $this->getBlocksForCity($defaultCityId, $locale) : [];

        $flowId = config('services.whatsapp.checkout_flow_id');
        $flowData = [
            'cart_summary_text' => $cartSummary['cart_summary_text'] ?? '',
            'flow_data' => $cartSummary['flow_data'] ?? [],
            'states' => $states,
            'cities' => $cities,
            'blocks' => $blocks,
            'full_name' => $customer->full_name ?? '',
            'phone_number' => $to,
            'saved_addresses' => $saved,
            'locale' => $locale,
        ];

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => ['type' => 'text', 'text' => $locale === 'ar' ? 'الدفع' : 'Checkout'],
                'body' => ['text' => $locale === 'ar' ? 'يرجى إكمال تفاصيل الدفع.' : 'Please complete your checkout.'],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_id' => $flowId,
                        'flow_cta' => $locale === 'ar' ? 'التالي' : 'Next',
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => 'CHECKOUT_START',
                            'data' => $flowData + [
                                // Ensure flow_data is object-like if present
                                'flow_data' => $this->coerceFlowData($flowData['flow_data'] ?? [], null),
                            ],
                        ],
                        'flow_token' => (string) Str::uuid(),
                    ],
                ],
            ],
        ];

        $this->whatsAppService->send($to, $payload);
    }

    // ------------------------------------------------------------------
    // Wire format helpers (!!! key fix here !!!)
    // ------------------------------------------------------------------

    /**
     * Force `flow_data` to be an associative array (JSON object), never []
     * and never a nested {"stdClass":{...}} shape.
     */
    private function coerceFlowData($flow, ?WhatsappSession $session = null): array
    {
        if (is_object($flow)) {
            $flow = get_object_vars($flow);
        }
        if (! is_array($flow)) {
            $flow = [];
        }

        // Baseline keys ensure it's never empty -> encodes as {}
        $baseline = [
            'state_id' => $flow['state_id'] ?? $session?->delivery_state_id ?? null,
            'city_id' => $flow['city_id'] ?? $session?->delivery_city_id ?? null,
            'street' => $flow['street'] ?? $session?->flow_street ?? null,
            'block_id' => $flow['block_id'] ?? $session?->flow_block_id ?? null,
            'house_no' => $flow['house_no'] ?? $session?->flow_house_no ?? null,
            'customer_phone' => $flow['customer_phone'] ?? $session?->customer_phone_number ?? null,
        ];

        foreach ($flow as $k => $v) {
            $baseline[$k] = $v;
        }

        return $baseline;
    }

    private function buildScreen(string $id, array $data = []): array
    {
        $session = $data['__session__'] ?? null;
        unset($data['__session__']);

        $data['flow_data'] = $this->coerceFlowData($data['flow_data'] ?? [], $session);

        return [
            'version' => '7.2',
            'data_api_version' => '3.0',
            'screen' => $id,
            'data' => $data,
        ];
    }

    private function buildScreenResponse(string $screen, array $data): array
    {
        $session = $data['__session__'] ?? null;
        unset($data['__session__']);

        $data['flow_data'] = $this->coerceFlowData($data['flow_data'] ?? [], $session);

        return [
            'version' => '7.2',
            'data_api_version' => '3.0',
            'screen' => $screen,
            'data' => $data,
        ];
    }

    private function buildErrorResponseData(string $message): array
    {
        return $this->buildScreenResponse('ERROR_SCREEN', ['error_message' => $message]);
    }

    private function firstValue($value)
    {
        return is_array($value) ? ($value[0] ?? null) : $value;
    }

    private function toArrayRecursive($v)
    {
        if (is_array($v)) {
            return array_map([$this, 'toArrayRecursive'], $v);
        }
        if (is_object($v)) {
            return $this->toArrayRecursive(get_object_vars($v));
        }

        return $v;
    }

    // ------------------------------------------------------------------
    // Misc
    // ------------------------------------------------------------------

    private function getFlowMessage(string $key, string $locale): string
    {
        // Simple stub for messages
        $messages = [
            'en' => [
                'items_not_found' => 'The selected items could not be found. They may no longer be available.',
                'how_many' => 'How many would you like?',
                'missing_item_id_or_qty' => 'Please select an item and quantity.',
            ],
            'ar' => [
                'items_not_found' => 'لا يمكن العثور على العناصر المحددة. قد لا تكون متاحة بعد الآن.',
                'how_many' => 'كم العدد الذي تريده؟',
                'missing_item_id_or_qty' => 'يرجى تحديد عنصر وكمية.',
            ],
        ];

        return $messages[$locale][$key] ?? 'An error occurred.';
    }

    // MODIFIED: Method now accepts the $flow array to access the current business type
    private function hasPreviousOrders(?WhatsappSession $session, array $flow): bool
    {
        if (! $session || ! $session->customer_phone_number) {
            return false;
        }

        // ADDED: Get the current business_type_id from the flow state
        $businessTypeId = $flow['business_type_id'] ?? null;

        try {
            return $this->orderHistoryService
                // MODIFIED: Pass the businessTypeId to the service method
                ->fetchRecentOrders($session->customer_phone_number, 1, $businessTypeId)
                ->isNotEmpty();
        } catch (\Throwable $e) {
            Log::warning('[Flow] hasPreviousOrders check failed', [
                'phone' => $session->customer_phone_number ?? null,
                'err' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function addressTypeLabel($type, string $locale): string
    {
        $isAr = ($locale === 'ar');

        // Accept both numeric ids and words (defensive)
        $t = is_string($type) ? strtolower($type) : (string) $type;

        $mapEn = [
            '1' => 'Home', 'home' => 'Home',
            '2' => 'Apartment', 'apartment' => 'Apartment',
            '3' => 'Office', 'office' => 'Office',
        ];
        $mapAr = [
            '1' => 'المنزل', 'home' => 'المنزل',
            '2' => 'شقة',    'apartment' => 'شقة',
            '3' => 'المكتب', 'office' => 'المكتب',
        ];

        $map = $isAr ? $mapAr : $mapEn;

        return $map[$t] ?? ($isAr ? 'عنوان' : 'Address');
    }

    private function btLabel($businessTypeId, string $key, string $locale, string $fallback): string
    {
        try {
            Log::debug('[Flow][btLabel] enter', [
                'in_businessTypeId' => $businessTypeId,
                'in_type' => gettype($businessTypeId),
                'key' => $key,
                'locale' => $locale,
                'fallback' => $fallback,
            ]);

            // Normalize ID: allow ["1"], "bt_1", "1"
            if (is_array($businessTypeId)) {
                $businessTypeId = $businessTypeId[0] ?? null;
                Log::debug('[Flow][btLabel] normalized from array', ['businessTypeId' => $businessTypeId]);
            }
            if (is_string($businessTypeId)) {
                $normalized = (int) Str::after($businessTypeId, 'bt_');
                Log::debug('[Flow][btLabel] normalized from string', ['raw' => $businessTypeId, 'normalized' => $normalized]);
                $businessTypeId = $normalized;
            }
            if (! is_numeric($businessTypeId) || ! $businessTypeId) {
                Log::warning('[Flow][btLabel] invalid/missing businessTypeId → fallback', ['businessTypeId' => $businessTypeId]);

                return $fallback;
            }

            $bt = BusinessType::find((int) $businessTypeId);
            if (! $bt) {
                Log::warning('[Flow][btLabel] BusinessType not found → fallback', ['businessTypeId' => (int) $businessTypeId]);

                return $fallback;
            }

            // Prefer Spatie Translatable if available
            if (method_exists($bt, 'getTranslation')) {
                $label = $bt->getTranslation($key, $locale);
                if ($label) {
                    Log::debug('[Flow][btLabel] spatie:getTranslation hit', ['bt_id' => $bt->id, 'label' => $label]);

                    return $label;
                }
                $labelEn = $bt->getTranslation($key, 'en');
                if ($labelEn) {
                    Log::debug('[Flow][btLabel] spatie:getTranslation fallback=en', ['bt_id' => $bt->id, 'label' => $labelEn]);

                    return $labelEn;
                }
                // As a last try with Spatie: full map
                if (method_exists($bt, 'getTranslations')) {
                    $map = $bt->getTranslations($key);
                    Log::debug('[Flow][btLabel] spatie:getTranslations map', ['bt_id' => $bt->id, 'keys' => array_keys($map)]);
                    $label = $map[$locale] ?? $map['en'] ?? null;
                    if ($label) {
                        return $label;
                    }
                }
            }

            // Non-Spatie paths
            $raw = $bt->{$key} ?? null; // could be string/array/json-string
            Log::debug('[Flow][btLabel] loaded field', [
                'bt_id' => $bt->id,
                'raw_type' => gettype($raw),
                'raw_preview' => is_string($raw) ? mb_substr($raw, 0, 200) : (is_array($raw) ? json_encode($raw) : gettype($raw)),
            ]);

            // If it's already an array (attribute cast), use it
            if (is_array($raw)) {
                $label = $raw[$locale] ?? $raw['en'] ?? null;
                if ($label) {
                    Log::debug('[Flow][btLabel] array cast resolved', ['bt_id' => $bt->id, 'label' => $label]);

                    return $label;
                }
                Log::warning('[Flow][btLabel] array cast missing locale → fallback', ['bt_id' => $bt->id, 'keys' => array_keys($raw)]);

                return $fallback;
            }

            // If it's a string, try JSON decode; if not JSON, use string as-is
            if (is_string($raw)) {
                $arr = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
                    $label = $arr[$locale] ?? $arr['en'] ?? null;
                    if ($label) {
                        Log::debug('[Flow][btLabel] json decoded string resolved', ['bt_id' => $bt->id, 'label' => $label]);

                        return $label;
                    }
                    Log::warning('[Flow][btLabel] json decoded but missing locale → fallback', ['bt_id' => $bt->id, 'keys' => array_keys($arr)]);

                    return $fallback;
                }

                // Plain scalar string like "Cuisine" → accept it
                $str = trim($raw);
                if ($str !== '') {
                    Log::debug('[Flow][btLabel] raw scalar string used', ['bt_id' => $bt->id, 'label' => $str]);

                    return $str;
                }

                Log::warning('[Flow][btLabel] empty string → fallback', ['bt_id' => $bt->id]);

                return $fallback;
            }

            Log::warning('[Flow][btLabel] unsupported field type → fallback', ['bt_id' => $bt->id, 'type' => gettype($raw)]);

            return $fallback;

        } catch (\Throwable $e) {
            Log::error('[Flow][btLabel] exception → fallback', ['err' => $e->getMessage()]);

            return $fallback;
        }
    }

    private function noItemResultsItems(string $locale): array
    {
        return [
            [
                'id' => 'clear_search',
                'title' => $locale === 'ar' ? 'مسح البحث' : 'Clear search',
                'description' => $locale === 'ar'
                    ? 'اعرض جميع الأصناف في هذه الفئة.'
                    : 'Show all items in this category.',
            ],
            [
                'id' => 'back_to_categories',
                'title' => $locale === 'ar' ? 'العودة إلى الفئات' : 'Back to categories',
                'description' => $locale === 'ar'
                    ? 'الرجوع لاختيار فئة مختلفة.'
                    : 'Go back to pick a different category.',
            ],
        ];
    }

    private function sendFlowNavigatePayload(string $to, string $flowId, string $flowToken, string $locale, string $screen, array $data, string $headerAr, string $headerEn, string $bodyAr, string $bodyEn, string $ctaAr, string $ctaEn): void
    {
        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => ['type' => 'text', 'text' => $locale === 'ar' ? $headerAr : $headerEn],
                'body' => ['text' => $locale === 'ar' ? $bodyAr : $bodyEn],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_id' => $flowId,
                        'flow_cta' => $locale === 'ar' ? $ctaAr : $ctaEn,
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => $screen,
                            'data' => $data,
                        ],
                        'flow_token' => $flowToken,
                    ],
                ],
            ],
        ];

        $this->whatsAppService->send($to, $payload);
    }
}
