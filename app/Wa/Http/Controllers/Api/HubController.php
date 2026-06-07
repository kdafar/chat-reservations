<?php

namespace App\Wa\Http\Controllers\Api;

use App\Wa\Http\Controllers\Controller;
use App\Wa\Hub\Models\Cuisine;
use App\Wa\Hub\Models\Vendors;
use App\Wa\Hub\Models\WhatsappSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HubController extends Controller
{
    private function t(string $en, string $ar, string $locale): string
    {
        return $locale === 'ar' ? $ar : $en;
    }

    /**
     * PHASE 1 CHANGE: Rewritten to fetch from the restaurant's API
     * and build a categorized product list message.
     */
    public function getRestaurantMenu(Vendors $restaurant, Request $request): JsonResponse
    {
        $locale = $request->input('lang', 'en');

        // Fetch the full menu from the restaurant's own API endpoint
        $menuResponse = Http::withHeaders([
            'Authorization' => 'Bearer '.$restaurant->api_key,
            'Accept' => 'application/json',
        ])->get($restaurant->api_base_url.'/v1/menu', ['lang' => $locale]);

        if ($menuResponse->failed() || empty($menuResponse->json('data'))) {
            return response()->json([
                'type' => 'text',
                'text' => ['body' => $this->t('The menu is temporarily unavailable.', 'القائمة غير متاحة حالياً.', $locale)],
            ]);
        }

        $menuCategories = $menuResponse->json('data');
        $sections = [];

        foreach ($menuCategories as $category) {
            if (empty($category['items'])) {
                continue;
            }

            $productItems = array_map(function ($item) {
                // The retailer_id must be unique and is used to identify the product
                return ['product_retailer_id' => $item['retailer_id']];
            }, $category['items']);

            $sections[] = [
                'title' => Str::limit($category['name'], 24),
                'product_items' => $productItems,
            ];
        }

        if (empty($sections)) {
            return response()->json([
                'type' => 'text',
                'text' => ['body' => $this->t('No items found on the menu.', 'لا توجد عناصر في القائمة.', $locale)],
            ]);
        }

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'product_list',
                'header' => ['type' => 'text', 'text' => $restaurant->getTranslation('name', $locale)],
                'body' => ['text' => $this->t('Please select from our menu below.', 'الرجاء الاختيار من قائمتنا أدناه.', $locale)],
                'action' => [
                    'catalog_id' => config('services.whatsapp.catalog_id'),
                    'sections' => array_slice($sections, 0, 10), // Max 10 sections
                ],
            ],
        ];

        return response()->json($payload);
    }

    public function getCategoryItems(Vendors $restaurant, int $categoryId, Request $request): JsonResponse
    {
        $locale = $request->input('lang', 'en');
        $menuResponse = Http::withHeaders([
            'Authorization' => 'Bearer '.$restaurant->api_key,
            'Accept' => 'application/json',
        ])->get($restaurant->api_base_url.'/v1/menu', ['lang' => $locale]);

        if ($menuResponse->failed()) {
            return response()->json(['type' => 'text', 'text' => ['body' => 'Could not load items.']]);
        }

        // Find the specific category from the full menu response
        $category = collect($menuResponse->json('data'))->firstWhere('id', $categoryId);

        if (! $category || empty($category['items'])) {
            return response()->json(['type' => 'text', 'text' => ['body' => 'No items found in this category.']]);
        }

        $items = $category['items'];
        $restaurantId = $restaurant->id;
        $sections = [];

        // This handles the 30-item limit by splitting a large category into multiple sections.
        foreach (array_chunk($items, 30) as $index => $chunk) {
            $productItems = array_map(function ($item) use ($restaurantId) {
                return ['product_retailer_id' => 'item_res_'.$restaurantId.'_'.$item['id']];
            }, $chunk);

            $sectionTitle = Str::limit($category['name'], 24);
            if (count($items) > 30) {
                $sectionTitle .= ' (Page '.($index + 1).')';
            }

            $sections[] = ['title' => $sectionTitle, 'product_items' => $productItems];
        }

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'product_list',
                'header' => ['type' => 'text', 'text' => $restaurant->getTranslation('name', $locale)],
                'body' => ['text' => $this->t('Please select from our menu below.', 'الرجاء الاختيار من قائمتنا أدناه.', $locale)],
                'action' => [
                    'catalog_id' => config('services.whatsapp.catalog_id'),
                    'sections' => $sections,
                ],
            ],
        ];

        return response()->json($payload);
    }

    public function listCuisines(Request $request): JsonResponse
    {
        $locale = $request->input('lang', 'en');
        $cuisines = Cuisine::where('is_active', 1)->get();

        if ($cuisines->isEmpty()) {
            return response()->json(['type' => 'text', 'text' => ['body' => $this->t('No cuisines available.', 'لا توجد أنواع مأكولات متاحة.', $locale)]]);
        }

        $rows = $cuisines->map(fn ($c) => [
            'id' => 'select_cuisine_'.$c->id,
            'title' => Str::limit($c->getTranslation('name', $locale), 24),
        ])->all();

        return response()->json(['type' => 'interactive', 'interactive' => [
            'type' => 'list',
            'header' => ['type' => 'text', 'text' => $this->t('Our Cuisines', 'أنواع المأكولات', $locale)],
            'body' => ['text' => $this->t('Select a cuisine to see restaurants.', 'اختر نوع المطابخ لعرض المطاعم.', $locale)],
            'action' => ['button' => $this->t('Cuisines', 'الأنواع', $locale), 'sections' => [['rows' => $rows]]],
        ]]);
    }

    public function listRestaurants(Request $request, ?int $cuisineId = null): JsonResponse
    {
        $locale = $request->input('lang', 'en');
        $query = Vendors::where('is_visible_on_whatsapp', true);

        if ($cuisineId) {
            $query->whereHas('cuisines', fn ($q) => $q->where('cuisines.id', $cuisineId));
        }
        $restaurants = $query->get();

        if ($restaurants->isEmpty()) {
            return response()->json(['type' => 'text', 'text' => ['body' => $this->t('No restaurants found.', 'لم يتم العثور على مطاعم.', $locale)]]);
        }

        $rows = $restaurants->map(fn ($r) => [
            'id' => 'restaurant_'.$r->id,
            'title' => Str::limit($r->getTranslation('name', $locale), 24),
        ])->all();

        return response()->json(['type' => 'interactive', 'interactive' => [
            'type' => 'list',
            'header' => ['type' => 'text', 'text' => $this->t('Choose a Restaurant', 'اختر مطعماً', $locale)],
            'body' => ['text' => $this->t('Select one to view its menu.', 'اختر واحداً لعرض القائمة.', $locale)],
            'action' => ['button' => $this->t('Restaurants', 'المطاعم', $locale), 'sections' => [['rows' => $rows]]],
        ]]);
    }

    public function listMenuCategories(Vendors $restaurant, Request $request): JsonResponse
    {
        $locale = $request->input('lang', 'en');
        $menuResponse = Http::withHeaders([
            'Authorization' => 'Bearer '.$restaurant->api_key,
            'Accept' => 'application/json',
        ])->get($restaurant->api_base_url.'/v1/menu', ['lang' => $locale]);

        if ($menuResponse->failed() || empty($menuResponse->json('data'))) {
            return response()->json(['type' => 'text', 'text' => ['body' => $this->t('Menu is unavailable.', 'القائمة غير متاحة.', $locale)]]);
        }

        $rows = [];
        foreach ($menuResponse->json('data') as $category) {
            if (empty($category['items'])) {
                continue;
            }

            $rows[] = [
                'id' => 'show_category_'.$category['id'],
                'title' => Str::limit($category['name'], 24),
                'description' => Str::limit($category['description'] ?? '', 72),
            ];
        }

        if (empty($rows)) {
            return response()->json(['type' => 'text', 'text' => ['body' => $this->t('No items found on the menu.', 'لا توجد عناصر في القائمة.', $locale)]]);
        }

        return response()->json(['type' => 'interactive', 'interactive' => [
            'type' => 'list',
            'header' => ['type' => 'text', 'text' => $this->t('Menu Categories', 'فئات القائمة', $locale)],
            'body' => ['text' => $this->t('Please select a category.', 'الرجاء اختيار فئة.', $locale)],
            'action' => ['button' => $this->t('View Categories', 'عرض الفئات', $locale), 'sections' => [['rows' => $rows]]],
        ]]);
    }

    public function addItemToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_phone' => 'required|string',
            'restaurant_id' => 'required|exists:restaurants,id',
            'item_id' => 'required|string', // retailer_id
        ]);

        $locale = $request->input('lang', 'en');
        $restaurant = Vendors::find($validated['restaurant_id']);
        $itemIdFromRetailer = last(explode('_', $validated['item_id']));

        $response = Http::withHeaders(['Authorization' => 'Bearer '.$restaurant->api_key])
            ->get($restaurant->api_base_url.'/v1/items/'.$itemIdFromRetailer);

        if ($response->failed()) {
            return response()->json(['success' => false, 'message' => 'Error adding item.']);
        }

        $itemData = $response->json('data');
        $session = WhatsappSession::firstOrCreate(['customer_phone_number' => $validated['customer_phone']]);
        $cartItem = $session->cartItems()->where('item_id_from_restaurant', $itemData['id'])->first();

        if ($cartItem) {
            $cartItem->increment('quantity');
        } else {
            $session->cartItems()->create([
                'item_id_from_restaurant' => $itemData['id'],
                'item_retailer_id' => $validated['item_id'],
                'item_name' => $itemData['name'],
                'price' => $itemData['price'],
                'quantity' => $request->input('quantity', 1),
            ]);
        }

        // Return empty/success for silent batch
        return response()->json(['success' => true]);
    }

    public function getCuisineCarouselData($locale = 'en')
    {
        return Cuisine::where('is_active', 1)->get()->map(function ($c) use ($locale) {
            return [
                'name' => $c->getTranslation('name', $locale),
                'description' => $c->getTranslation('description', $locale),
                'whatsapp_media_id' => $c->whatsapp_media_id,
            ];
        })->values()->toArray();
    }

    // PHASE 1 CHANGE: The following methods are now obsolete and have been REMOVED.
    // - saveProfile()
    // - confirmOrder()
    // - initiateCheckoutFlow()
    // - all carousel and other unused list methods
}
