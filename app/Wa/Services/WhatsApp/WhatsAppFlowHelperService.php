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
use Illuminate\Support\Str;

class WhatsAppFlowHelperService
{
    public WhatsAppService $whatsAppService;

    public VendorDataService $vendorDataService;

    private OrderHistoryService $orderHistoryService;

    private FlowImageService $flowImageService;

    public function __construct(
        WhatsAppService $whatsAppService,
        VendorDataService $vendorDataService,
        OrderHistoryService $orderHistoryService,
        FlowImageService $flowImageService
    ) {
        $this->whatsAppService = $whatsAppService;
        $this->vendorDataService = $vendorDataService;
        $this->orderHistoryService = $orderHistoryService;
        $this->flowImageService = $flowImageService;
    }

    // ------------------------------------------------------------------
    // Navigation & Bootstrapping Helpers
    // ------------------------------------------------------------------

    public function bootBusinessTypeStep(string $locale, array $flow, ?string $error = null, ?WhatsappSession $session = null): array
    {
        return $this->buildScreen('SELECT_BUSINESS_TYPE', [
            'business_types' => $this->vendorDataService->getBusinessTypes($locale),
            'error_message' => $error ?? '',
            'show_error_message' => (bool) $error,
            'flow_data' => $flow,
            '__session__' => $session,
        ]);
    }

    public function bootAddressStep(?WhatsappSession $session, string $locale, array $flow, ?string $error = null): array
    {
        $profile = $session?->customerProfile;
        $addresses = $profile?->addresses ?? [];
        $states = $this->getStates($locale);
        $showReorder = $this->hasPreviousOrders($session);

        if (empty($addresses)) {
            return $this->buildScreen('ADDRESS_SAVED', [
                'addressOptions' => [], 'states' => $states, 'cities' => [],
                'show_addresses' => false, 'show_states' => true, 'show_city' => false,
                'footer_label' => $locale === 'ar' ? 'التالي' : 'Next', 'flow_data' => $flow,
                'error_message' => $error ?: '', 'show_error_message' => (bool) $error,
                'show_reorder_option' => $showReorder, 'reorder_options' => $showReorder ? $this->reorderOptions($locale) : [],
            ]);
        }

        [$opts, $map] = $this->buildAddressOptions($addresses, $locale);
        $flow['addr_map'] = $map;

        return $this->buildScreen('ADDRESS_SAVED', [
            'addressOptions' => $opts, 'states' => $states, 'cities' => [],
            'show_addresses' => true, 'show_states' => false, 'show_city' => false,
            'footer_label' => $locale === 'ar' ? 'متابعة' : 'Continue', 'flow_data' => $flow,
            'error_message' => $error ?: '', 'show_error_message' => (bool) $error,
            'show_reorder_option' => $showReorder, 'reorder_options' => $showReorder ? $this->reorderOptions($locale) : [],
        ]);
    }

    public function _getNextScreenAfterAddress(?WhatsappSession $session, array $flow, string $locale): array
    {
        if ($session && $session->direct_intent_vendor_id) {
            $vendorId = $session->direct_intent_vendor_id;
            $categories = $this->vendorDataService->getMenuCategoriesForVendor($vendorId, $locale);
            $flow['vendor_id'] = 'vendor_'.$vendorId;
            $btId = is_numeric($flow['business_type_id'] ?? null) ? (int) $flow['business_type_id'] : null;
            $catLabel = $this->btLabel($btId, 'category_label', $locale, $locale === 'ar' ? 'فئة' : 'category');
            $session->update(['direct_intent_vendor_id' => null, 'direct_intent_vendor_category_id' => null]);

            return $this->buildScreenResponse('SELECT_CATEGORY', [
                'categories' => $categories, 'vendor_id' => $flow['vendor_id'],
                'category_selection_prompt' => $locale === 'ar' ? "أولاً، يرجى اختيار {$catLabel}" : "First, please select a {$catLabel}",
                'flow_data' => $flow, 'show_error_message' => false, '__session__' => $session,
            ]);
        }

        if ($session && $session->direct_intent_vendor_category_id) {
            $vendorCategoryId = $session->direct_intent_vendor_category_id;
            $cityId = $session->delivery_city_id;
            $vendors = $this->vendorDataService->getVendorsForCategory($vendorCategoryId, $locale, (int) $cityId);
            $flow['vendor_category_id'] = 'category_'.$vendorCategoryId;
            $session->update(['direct_intent_vendor_id' => null, 'direct_intent_vendor_category_id' => null]);

            return $this->buildScreenResponse('SELECT_VENDOR', [
                'vendors' => $vendors, 'flow_data' => $flow,
                'show_error_message' => false, '__session__' => $session,
            ]);
        }

        $btId = is_numeric($flow['business_type_id'] ?? null) ? (int) $flow['business_type_id'] : null;
        $catLabel = $this->btLabel($btId, 'category_label', $locale, $locale === 'ar' ? 'فئة' : 'category');

        return $this->buildScreenResponse('SELECT_VENDOR_CATEGORY', [
            'vendor_categories' => $this->vendorDataService->getCategoriesForBusinessType($flow['business_type_id'] ?? null, $locale),
            'category_selection_prompt' => $locale === 'ar' ? "أولاً، يرجى اختيار {$catLabel}" : "First, please select a {$catLabel}",
            'flow_data' => $flow, 'show_error_message' => false, '__session__' => $session,
        ]);
    }

    public function _buildSelectOrderScreen(?WhatsappSession $session, array $flow, string $locale): array
    {
        $recentOrders = $this->orderHistoryService->fetchRecentOrders($session->customer_phone_number);

        if ($recentOrders->isEmpty()) {
            $flow['no_orders_text'] = $locale === 'ar'
                ? 'لا توجد لديك طلبات سابقة. يرجى بدء طلب جديد.'
                : 'You have no previous orders. Please start a new one.';

            return $this->_getNextScreenAfterAddress($session, $flow, $locale);
        }

        $isAr = ($locale === 'ar');
        $curr = $isAr ? 'د.ك' : 'KWD';
        $fallback = $isAr ? 'en' : 'ar';

        $formattedOrders = $recentOrders->map(function ($order) use ($curr, $isAr, $locale, $fallback) {
            $itemSummary = $order->items->map(fn ($item) => "{$item->quantity}x {$item->item_name}")->take(2)->implode(', ');
            if ($order->items->count() > 2) {
                $itemSummary .= '...';
            }
            $total = $this->orderTotalKwd($order);
            $totalStr = number_format((float) $total, 3);
            $dateStr = $isAr ? $order->created_at->locale('ar')->translatedFormat('d MMM، Y') : $order->created_at->format('M d, Y');
            $logoRaw = $this->imageRawBase64FromMixed($order->restaurant->logo_url ?? null);
            $totalLbl = $isAr ? 'الإجمالي' : 'Total';
            $r = $order->restaurant;
            $name = $r ? ($r->getTranslation('name', $locale) ?: $r->getTranslation('name', $fallback) ?: ($r->name ?? ($isAr ? 'مطعم غير معروف' : 'Unknown Restaurant'))) : ($isAr ? 'مطعم غير معروف' : 'Unknown Restaurant');

            return [
                'id' => 'order_'.$order->id, 'title' => $name,
                'description' => sprintf("%s\n**%s: %s %s**\n🗓️ %s", $itemSummary, $totalLbl, $totalStr, $curr, $dateStr),
                'image' => $logoRaw,
            ];
        })->values()->toArray();

        return $this->buildScreenResponse('SELECT_ORDER', [
            'previous_orders' => $formattedOrders, 'has_previous_orders' => true,
            'show_no_orders' => false, 'flow_data' => $flow, 'show_error_message' => false,
            '__session__' => $session,
        ]);
    }

    // ------------------------------------------------------------------
    // Outbound Flow Sending
    // ------------------------------------------------------------------

    public function sendInitialFlow(string $to, string $locale): void
    {
        $flowId = $this->flowIdFor($locale);
        $flowToken = Str::uuid()->toString();

        WhatsappSession::updateOrCreate(
            ['customer_phone_number' => $to],
            ['flow_token' => $flowToken, 'status' => 'active', 'locale' => $locale]
        );

        $businessTypes = $this->vendorDataService->getBusinessTypes($locale);

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => ['type' => 'text', 'text' => $locale === 'ar' ? 'ابدأ طلبك' : 'Start Your Order'],
                'body' => ['text' => $locale === 'ar' ? 'ما الذي تبحث عنه اليوم؟' : 'What are you looking for today?'],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_id' => $flowId,
                        'flow_cta' => $locale === 'ar' ? 'اختر خدمة' : 'Choose a Service',
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => 'SELECT_BUSINESS_TYPE',
                            'data' => [
                                'business_types' => $businessTypes,
                                'flow_data' => $this->coerceFlowData([], null),
                            ],
                        ],
                        'flow_token' => $flowToken,
                    ],
                ],
            ],
        ];

        $this->whatsAppService->send($to, $payload);
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
        $showReorder = $this->hasPreviousOrders($session);

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => ['type' => 'text', 'text' => $locale === 'ar' ? '📍 أين تريد توصيل طلبك؟' : '📍 Where should we deliver your order?'],
                'body' => ['text' => $locale === 'ar' ? 'اختر عنواناً محفوظاً أو أضف عنواناً جديداً للاستمرار في الطلب.' : 'Tap a saved address or add a new one to continue.'],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_id' => $flowId,
                        'flow_cta' => $locale === 'ar' ? 'اختر العنوان' : 'Select Address',
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => 'ADDRESS_SAVED',
                            'data' => [
                                'addressOptions' => $options,
                                'states' => $this->getStates($locale),
                                'cities' => [], 'show_addresses' => true, 'show_states' => false,
                                'show_city' => false, 'footer_label' => $locale === 'ar' ? 'اختر العنوان' : 'Select Address',
                                'flow_data' => $this->coerceFlowData(['addr_map' => $map], $session),
                                'show_error_message' => false, 'show_reorder_option' => $showReorder,
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

    public function sendCheckoutFlow(string $to, array $cartSummary, string $locale = 'en'): void
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
            'states' => $states, 'cities' => $cities, 'blocks' => $blocks,
            'full_name' => $customer->full_name ?? '', 'phone_number' => $to,
            'saved_addresses' => $saved, 'locale' => $locale,
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
                        'flow_message_version' => '3', 'flow_id' => $flowId,
                        'flow_cta' => $locale === 'ar' ? 'التالي' : 'Next',
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => 'CHECKOUT_START',
                            'data' => $flowData + ['flow_data' => $this->coerceFlowData($flowData['flow_data'] ?? [], null)],
                        ],
                        'flow_token' => (string) Str::uuid(),
                    ],
                ],
            ],
        ];

        $this->whatsAppService->send($to, $payload);
    }

    // ------------------------------------------------------------------
    // Data, UI, and Utility Helpers
    // ------------------------------------------------------------------

    public function validateAndApplyPromo(array &$flow, WhatsappSession $session, string $locale): ?string
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

    public function buildQtyOptions(): array
    {
        return collect(range(1, 10))->map(fn ($i) => ['id' => (string) $i, 'title' => (string) $i])->toArray();
    }

    public function hasFullAddress(array $f): bool
    {
        if (($f['order_type'] ?? 'delivery') !== 'delivery') {
            return true;
        }

        return ! empty($f['state_id']) && ! empty($f['city_id']) && ! empty($f['block_id']) && ! empty($f['street']);
    }

    public function cartActionsAndMinMsg(WhatsappSession $session, array $flow, string $locale): array
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

    public function makeOrderSummary(WhatsappSession $session, array $flow, string $locale): string
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

    public function summarizeCart(WhatsappSession $session, string $locale = 'en'): string
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

    public function persistAddressFromFlow(WhatsappSession $session, array $flow): void
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

    public function rehydrateAddressFromSession(array &$flow, ?WhatsappSession $session): void
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

    public function minOrderForCity(?int $cityId, ?int $vendorId = null): ?float
    {
        if (! $cityId || ! $vendorId) {
            return null;
        }

        $area = DeliveryArea::where('city_id', $cityId)->whereHas('branch', function ($q) use ($vendorId) {
            $q->where('vendor_id', $vendorId)->where('is_active', true);
        })->first();

        return $area?->min_order_value !== null ? (float) $area->min_order_value : null;
    }

    public function deliveryFee(?int $vendorId, ?int $cityId): ?float
    {
        if (! $cityId || ! $vendorId) {
            return null;
        }

        $area = DeliveryArea::where('city_id', $cityId)->whereHas('branch', function ($q) use ($vendorId) {
            $q->where('vendor_id', $vendorId)->where('is_active', true);
        })->first();

        return $area?->delivery_fee !== null ? (float) $area->delivery_fee : null;
    }

    public function flattenAddonGroups(array $groups, string $locale): array
    {
        $flatList = [];
        if (empty($groups)) {
            return [];
        }

        foreach ($groups as $group) {
            foreach ($group['addons'] ?? [] as $option) {
                $price = (float) ($option['price'] ?? 0);
                $nameTranslations = $option['name'] ?? [];
                $title = $nameTranslations[$locale] ?? ($nameTranslations['en'] ?? 'Addon');
                $isAr = $locale === 'ar';
                $curr = $isAr ? 'د.ك' : 'KWD';
                $flatList[] = [
                    'id' => "{$group['id']}-{$option['id']}",
                    'title' => $title,
                    'description' => $price > 0 ? '+ '.number_format($price, 3).' '.$curr : ($isAr ? 'مجاني' : 'Included'),
                ];
            }
        }

        return $flatList;
    }

    public function cleanAddonTitle(string $t): string
    {
        $pattern = '/\s*\+\s*[\d\.,]+\s*(KWD|KD|د\.?\s*ك)\s*$/iu';
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $t = str_replace($ar, $en, $t);

        return preg_replace($pattern, '', $t);
    }

    public function imageRawBase64FromMixed(?string $raw): string
    {
        if (! $raw) {
            return '';
        }

        if (str_starts_with($raw, 'data:')) {
            return $this->stripDataUriPrefix($raw);
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $dataUri = $this->flowImageService->dataUriFromUrl($raw);

            return $this->stripDataUriPrefix($dataUri ?: '');
        }

        $dataUri = $this->flowImageService->dataUriFromStorage($raw);

        return $this->stripDataUriPrefix($dataUri ?: '');
    }

    public function stripDataUriPrefix(?string $s): string
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

    public function removeActions(string $locale): array
    {
        $imgRaw = $this->stripDataUriPrefix($this->flowImageService->dataUriFromStorage('flow/remove.png') ?? '');

        return [['id' => 'remove', 'title' => $locale === 'ar' ? '🗑️ إزالة من السلة' : '🗑️ Remove from cart', 'description' => $locale === 'ar' ? 'احذف هذا العنصر من سلة الشراء.' : 'Delete this item from your cart.', 'alt-text' => 'Remove from cart', 'image' => $imgRaw]];
    }

    public function reorderOptions(string $locale): array
    {
        $imgRawB64 = $this->stripDataUriPrefix($this->flowImageService->dataUriFromStorage('flow/reorder.png') ?? '');
        $isAr = ($locale === 'ar');

        return [['id' => 'reorder', 'title' => $isAr ? '🕒 إعادة طلب' : '🕒 Reorder', 'description' => $isAr ? 'اعرض وأعِد طلب آخر مشترياتك بسرعة.' : 'Quickly re-order your recent purchases.', 'alt-text' => $isAr ? 'إعادة طلب' : 'Reorder option', 'image' => $imgRawB64]];
    }

    public function loadMoreItem(string $locale): array
    {
        $imgRaw = $this->stripDataUriPrefix($this->flowImageService->dataUriFromStorage('flow/load-more.png') ?? '');
        $item = ['id' => 'pager_more', 'title' => $locale === 'ar' ? 'عرض المزيد' : 'Load more results', 'description' => $locale === 'ar' ? 'اعرض مطاعم إضافية للمطبخ المختار.' : 'Show additional restaurants for this cuisine.'];
        if ($imgRaw !== '') {
            $item['image'] = $imgRaw;
        }

        return $item;
    }

    public function noResultsItems(string $locale): array
    {
        return [['id' => 'clear_search', 'title' => $locale === 'ar' ? 'مسح البحث' : 'Clear search', 'description' => $locale === 'ar' ? 'اعرض جميع المطاعم لهذا المطبخ.' : 'Show all restaurants for this cuisine.'], ['id' => 'back_to_cuisines', 'title' => $locale === 'ar' ? 'العودة لاختيار المطبخ' : 'Back to cuisines', 'description' => $locale === 'ar' ? 'الرجوع خطوة للخلف لاختيار مطبخ آخر.' : 'Go back to pick a different cuisine.']];
    }

    public function shortTitle(array $addressData, string $locale): string
    {
        $map = $locale === 'ar' ? ['1' => '🏠 المنزل', '2' => '🏢 المكتب', '3' => '🏬 شقة'] : ['1' => '🏠 Home', '2' => '🏢 Office', '3' => '🏬 Apartment'];
        $key = (string) ($addressData['address_type'] ?? '');
        $label = $map[$key] ?? ($locale === 'ar' ? '📍 العنوان' : '📍 Address');
        $parts = array_filter([$label, $addressData['state_name'] ?? null, $addressData['city_name'] ?? null, ($addressData['block_id'] ?? null) ? ($locale === 'ar' ? "قطعة {$addressData['block_id']}" : "Block {$addressData['block_id']}") : null, ($addressData['street'] ?? null) ? ($locale === 'ar' ? "شارع {$addressData['street']}" : "St {$addressData['street']}") : null]);

        return implode(' • ', $parts);
    }

    public function buildAddressOptions(array $addresses, string $locale): array
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
            $slug = data_get($raw, 'slug') ?: Str::slug($raw['label'] ?? 'addr_'.$i);
            $id = "addr_{$slug}_{$i}";
            $opts[] = ['id' => $id, 'title' => $this->shortTitle($raw, $locale)];
            $map[$id] = $raw;
        }
        $opts[] = ['id' => 'ADD_NEW', 'title' => $locale === 'ar' ? '➕ عنوان جديد' : '➕ New address'];

        return [$opts, $map];
    }

    public function filterRestaurantsBySearch(array $restaurants, string $term): array
    {
        return $this->filterListBySearch($restaurants, $term);
    }

    public function filterListBySearch(array $list, string $term): array
    {
        $needle = $this->normalizeText($term);
        if ($needle === '') {
            return $list;
        }

        return array_filter($list, function ($item) use ($needle) {
            $title = $this->normalizeText((string) ($item['title'] ?? ''));
            $desc = $this->normalizeText((string) ($item['description'] ?? ''));

            return mb_strpos($title, $needle, 0, 'UTF-8') !== false || mb_strpos($desc, $needle, 0, 'UTF-8') !== false;
        });
    }

    public function normalizeText(string $s): string
    {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ﻻ' => 'لا', 'لأ' => 'لا', 'لإ' => 'لا', 'لآ' => 'لا']);
        $s = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $s);
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    public function paginate(array $list, int $page, int $pageSize): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $pageSize;

        return [array_slice($list, $offset, $pageSize), ($offset + $pageSize) < count($list)];
    }

    public function paginateRestaurants(array $list, int $page, int $pageSize = 19): array
    {
        return $this->paginate($list, $page, $pageSize);
    }

    public function orderTotalKwd(\App\Models\Order $order): float
    {
        if ((float) ($order->total ?? 0) > 0) {
            return (float) $order->total;
        }
        $sum = 0.0;
        foreach ($order->items as $it) {
            $base = (float) ($it->price ?? 0);
            $addons = is_array($it->addons) ? collect($it->addons)->sum(fn ($ad) => is_array($ad) ? (float) ($ad['price'] ?? 0) : (float) $ad) : 0.0;
            $sum += ($base + $addons) * (int) ($it->quantity ?? 0);
        }

        return max(0.0, $sum + (float) ($order->delivery_fee ?? 0) - (float) ($order->discount ?? 0));
    }

    public function mapAddonIdsToDetails(array $selectedCompositeIds, array $allAddonGroupsFromApi, string $locale): array
    {
        if (empty($selectedCompositeIds) || empty($allAddonGroupsFromApi)) {
            return [];
        }
        $lookup = array_flip($selectedCompositeIds);
        $detailedAddons = [];
        foreach ($allAddonGroupsFromApi as $group) {
            foreach ($group['addons'] ?? [] as $option) {
                $currentCompositeId = "{$group['id']}-{$option['id']}";
                if (isset($lookup[$currentCompositeId])) {
                    $nameTranslations = $option['name'] ?? [];
                    $title = $nameTranslations[$locale] ?? ($nameTranslations['en'] ?? 'Addon');
                    $detailedAddons[] = ['id' => $currentCompositeId, 'title' => $title, 'price' => $option['price'] ?? 0];
                }
            }
        }

        return $detailedAddons;
    }

    public function removeItemsOfCategoryNotInSelection(WhatsappSession $session, array $selectedIds, array $flow, string $locale): void
    {
        $categoryIdRaw = $flow['category_id_raw'] ?? $flow['category_id'] ?? null;
        $vendorIdRaw = $flow['vendor_id'] ?? null;
        if (! $categoryIdRaw || ! $vendorIdRaw) {
            return;
        }
        $allIdsInCat = collect($this->vendorDataService->getItemsForCategorySimple($categoryIdRaw, $vendorIdRaw, $locale))->map(fn ($i) => (int) preg_replace('/\D+/', '', $i['id']))->all();
        $toDelete = array_diff($allIdsInCat, $selectedIds);
        if (! empty($toDelete)) {
            \App\Hub\Models\CartItem::where('whatsapp_session_id', $session->id)->whereIn('item_id_from_restaurant', $toDelete)->delete();
        }
    }

    public function flowIdFor(string $locale): string
    {
        return $locale === 'ar' ? config('services.whatsapp.menu_flow_id_ar') : config('services.whatsapp.menu_flow_id_en');
    }

    public function coerceFlowData($flow, ?WhatsappSession $session = null): array
    {
        $flow = is_object($flow) ? get_object_vars($flow) : (is_array($flow) ? $flow : []);
        $baseline = [
            'state_id' => $session?->delivery_state_id, 'city_id' => $session?->delivery_city_id,
            'street' => $session?->flow_street, 'block_id' => $session?->flow_block_id,
            'house_no' => $session?->flow_house_no, 'customer_phone' => $session?->customer_phone_number,
        ];

        return array_merge($baseline, $flow);
    }

    public function buildScreen(string $id, array $data = []): array
    {
        $session = $data['__session__'] ?? null;
        unset($data['__session__']);
        $data['flow_data'] = $this->coerceFlowData($data['flow_data'] ?? [], $session);

        return ['version' => '7.2', 'data_api_version' => '3.0', 'screen' => $id, 'data' => $data];
    }

    public function buildScreenResponse(string $screen, array $data): array
    {
        return $this->buildScreen($screen, $data);
    }

    public function firstValue($value)
    {
        return is_array($value) ? ($value[0] ?? null) : $value;
    }

    public function toArrayRecursive($v)
    {
        if (is_array($v)) {
            return array_map([$this, 'toArrayRecursive'], $v);
        }
        if (is_object($v)) {
            return $this->toArrayRecursive(get_object_vars($v));
        }

        return $v;
    }

    public function getFlowMessage(string $key, string $locale): string
    {
        $messages = [
            'en' => ['items_not_found' => 'The selected items could not be found.', 'how_many' => 'How many would you like?', 'missing_item_id_or_qty' => 'Please select an item and quantity.'],
            'ar' => ['items_not_found' => 'لا يمكن العثور على العناصر المحددة.', 'how_many' => 'كم العدد الذي تريده؟', 'missing_item_id_or_qty' => 'يرجى تحديد عنصر وكمية.'],
        ];

        return $messages[$locale][$key] ?? 'An error occurred.';
    }

    public function hasPreviousOrders(?WhatsappSession $session): bool
    {
        return $session && $session->customer_phone_number ? $this->orderHistoryService->fetchRecentOrders($session->customer_phone_number, 1)->isNotEmpty() : false;
    }

    public function addressTypeLabel($type, string $locale): string
    {
        $t = is_string($type) ? strtolower($type) : (string) $type;
        $map = $locale === 'ar' ? ['1' => 'المنزل', '2' => 'شقة', '3' => 'المكتب'] : ['1' => 'Home', '2' => 'Apartment', '3' => 'Office'];

        return $map[$t] ?? ($locale === 'ar' ? 'عنوان' : 'Address');
    }

    public function btLabel(?int $businessTypeId, string $key, string $locale, string $fallback): string
    {
        if (! $businessTypeId || ! $bt = BusinessType::find($businessTypeId)) {
            return $fallback;
        }
        $raw = $bt->{$key} ?? null;
        $arr = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

        return $arr[$locale] ?? $arr['en'] ?? $fallback;
    }
}
