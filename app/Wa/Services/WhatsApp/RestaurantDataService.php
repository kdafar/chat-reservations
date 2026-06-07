<?php

namespace App\Wa\Services\WhatsApp;

use App\Wa\Hub\Models\Cuisine;
use App\Wa\Hub\Models\MenuCategory;
use App\Wa\Hub\Models\MenuItem;
use App\Wa\Hub\Models\Rating;
use App\Wa\Hub\Models\Vendors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class RestaurantDataService
{
    public function getCuisines(string $locale): array
    {
        $cuisines = Cuisine::where('is_active', 1)
            ->get()
            ->map(fn ($c) => [
                'id' => 'cuisine_'.$c->id,
                'title' => $c->getTranslation('name', $locale),
                'description' => $c->getTranslation('description', $locale) ?? $this->t('Authentic local flavors', 'نكهات محلية أصيلة', $locale),
                'image' => $this->getBase64CuisineImage($c), // 👈 Use the method below!
            ])->toArray();

        return $cuisines;
    }

    private function getBase64CuisineImage(Cuisine $cuisine): string
    {
        $imgField = $cuisine->image_path ?: $cuisine->image_url;
        $path = $imgField ? public_path('storage/'.ltrim($imgField, '/')) : null;
        $lastModified = ($path && file_exists($path)) ? filemtime($path) : 'no-file';
        $cacheKey = 'cuisine_image_b64_'.$cuisine->id.'_'.$lastModified;

        // Just get the value from the cache. If it's not there, fallback.
        // The background job is responsible for populating it.
        return Cache::get($cacheKey) ?? $this->getPlaceholderImage();
    }

    public function getRestaurantsForCuisine(int $cuisineId, string $locale, ?int $cityId = null): array
    {
        $cityId = $cityId ? (int) $cityId : null;

        $q = Vendors::query()
            ->where('is_visible_on_whatsapp', true)
            ->whereHas('cuisines', fn ($qq) => $qq->where('cuisine_id', $cuisineId));

        if ($cityId) {
            $q->whereHas('branches', function ($b) use ($cityId) {
                $b->where('is_active', true)
                    ->whereHas('deliveryAreas', fn ($da) => $da->where('city_id', $cityId));
            });
        }

        // Admin sort: NULLs last
        $q->orderByRaw('COALESCE(whatsapp_sort_order, 1000000) ASC');

        // filter by computed accessor
        $restaurants = $q->get()->filter->is_open;

        // ---- NEW: fetch ratings for all listed restaurants in one query
        $ids = $restaurants->pluck('id')->all();
        $ratings = $this->getRestaurantRatings($ids); // [restaurant_id => ['avg'=>..., 'cnt'=>...]]

        $out = $restaurants->map(function (Vendors $r) use ($locale, $ratings) {
            $name = $r->getTranslation('name', $locale) ?? '—';
            $desc = $r->getTranslation('description', $locale) ?? '';

            if ($r->badge_active) {
                $fallback = $locale === 'ar' ? 'en' : 'ar';
                $badgeLabel = $r->getTranslation('badge_label', $locale)
                    ?: $r->getTranslation('badge_label', $fallback);

                if (! empty($r->badge_emoji)) {
                    $name = trim("{$r->badge_emoji} {$name}");
                }
                if (! empty($badgeLabel)) {
                    $name .= " ({$badgeLabel})";
                }
            }

            // compact rating AFTER badges, wrapped in parentheses with spacing
            $short = $this->ratingShort($ratings[$r->id] ?? null, $locale);
            if ($short !== '') {
                $name .= $short;
            }

            return [
                'id' => 'restaurant_'.$r->id,
                'title' => $name,
                'description' => $desc, // keep description clean
                'image' => $this->getBase64RestaurantImage($r),
            ];
        })->values()->toArray();

        \Log::debug('[RestaurantDataService] Returning restaurants', ['count' => count($out)]);

        return $out;
    }

    private function getBase64RestaurantImage(Vendors $restaurant): string
    {
        $logoField = $restaurant->logo_url;
        if (! $logoField) {
            return $this->getPlaceholderImage();
        }

        $path = public_path('storage/'.ltrim($logoField, '/'));
        $lastModified = file_exists($path) ? filemtime($path) : 'no-file';
        $cacheKey = 'restaurant_image_b64_'.$restaurant->id.'_'.$lastModified;

        // Just get the value from the cache. The job is responsible for populating it.
        return Cache::get($cacheKey) ?? $this->getPlaceholderImage();
    }

    public function getCategoriesForRestaurant(int $restaurantId, string $locale): array
    {
        $restaurant = Vendors::find($restaurantId);
        if (! $restaurant) {
            return [];
        }

        $categories = MenuCategory::where('restaurant_id', $restaurantId)
            ->orderBy('id', 'asc')
            ->get()
            // ⬇️ import $restaurant into the closure
            ->map(function ($cat) use ($locale, $restaurant) {
                // (optional) keep this if you want remote image as another fallback
                // $base64 = $this->downloadImageAsBase64($cat->image_url);

                return [
                    'id' => 'category_'.$cat->external_id,
                    'title' => $cat->name,
                    'description' => $cat->description ?? $this->t('View items', 'عرض الأصناف', $locale),
                    'image' => $this->getLocalImageAsBase64($cat->local_image_path, false)
                        // (optional) add remote fallback one line below if you want
                        // ?: $this->downloadImageAsBase64($cat->image_url, false)
                        ?: $this->getBase64RestaurantImage($restaurant)
                        ?: $this->getPlaceholderImage(),
                ];
            })->toArray();

        return $categories;
    }

    private function downloadImageAsBase64(?string $url, bool $fullQuality = false): ?string
    {
        if (! $url) {
            return null;
        }

        //  UPDATE: Add a suffix to the cache key to store different image versions
        $qualitySuffix = $fullQuality ? '_full' : '_thumb';
        $cacheKey = 'remote_image_b64_'.hash('sha256', $url).$qualitySuffix;

        return Cache::remember($cacheKey, now()->addDay(), function () use ($url, $fullQuality) {
            try {
                $response = Http::timeout(15)->get($url);

                if ($response->failed()) {
                    Log::warning('[WhatsApp] Failed to download image', ['url' => $url]);

                    return null;
                }

                $img = Image::make($response->body());

                //  UPDATE: Check the flag to decide whether to resize and compress
                if (! $fullQuality) {
                    // Low-quality thumbnail for lists
                    $img->resize(240, 240, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->encode('jpg', 50);
                } else {
                    // High-quality for single item view
                    $img->encode('jpg', 90);
                }

                return base64_encode($img->__toString());
            } catch (\Exception $e) {
                Log::warning('[WhatsApp] Exception downloading image', ['url' => $url, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    public function getPlaceholderImage(): string
    {
        // This is a transparent 1x1 PNG
        return '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';
    }

    /**
     * Reads a locally stored image, optimizes it, and converts it to a base64 string.
     */
    private function getLocalImageAsBase64(?string $localPath, bool $fullQuality = false): ?string
    {
        if (! $localPath || ! Storage::disk('public')->exists($localPath)) {
            return null;
        }

        try {
            $contents = Storage::disk('public')->get($localPath);
            $img = Image::make($contents);

            if (! $fullQuality) {
                // Create a small, low-quality thumbnail for list views
                $img->resize(240, 240, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->encode('jpg', 50); // Encode as JPG for better small-file-size compression
            } else {
                // Use high quality for single-item views
                $img->encode('webp', 90); // Keep as WebP for high quality
            }

            return base64_encode($img->__toString());
        } catch (\Exception $e) {
            Log::error('Failed to read and process local image for base64 encoding', [
                'path' => $localPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getItemsForCategory(Vendors $restaurant, int $categoryId, Request $request): JsonResponse
    {
        $locale = $request->input('lang', 'en');

        //  FIXED: Use the same cached data source as the rest of the app.
        $menuData = $this->getMenuFromApi($restaurant, $locale);

        if (is_null($menuData)) {
            $body = $this->t('Could not load items.', 'لا يمكن تحميل الأصناف.', $locale);

            return response()->json(['type' => 'text', 'text' => ['body' => $body]]);
        }

        // Find the specific category from the full menu response
        $category = collect($menuData)->firstWhere('id', $categoryId);

        if (! $category || empty($category['items'])) {
            $body = $this->t('No items found in this category.', 'لا توجد أصناف في هذه الفئة.', $locale);

            return response()->json(['type' => 'text', 'text' => ['body' => $body]]);
        }

        $items = $category['items'];
        $restaurantId = $restaurant->id;
        $sections = [];

        // This handles the 30-item limit by splitting a large category into multiple sections.
        foreach (array_chunk($items, 30) as $index => $chunk) {
            $productItems = array_map(function ($item) use ($restaurantId) {
                // Note: Make sure this ID format is handled correctly by your catalog
                return ['product_retailer_id' => 'item_res_'.$restaurantId.'_'.$item['id']];
            }, $chunk);

            $sectionTitle = \Illuminate\Support\Str::limit($category['name'], 24);
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

    /**
     * Return an array of items (id, title, description, image) for Flow JSON.
     * Accepts *raw* IDs like “category_1”, “restaurant_7”.
     */
    public function getItemsForCategorySimple(string $categoryIdRaw, string $restaurantIdRaw, string $locale): array
    {
        $categoryId = (int) \Illuminate\Support\Str::after($categoryIdRaw, 'category_');
        $restaurantId = (int) \Illuminate\Support\Str::after($restaurantIdRaw, 'restaurant_');

        $restaurant = Vendors::find($restaurantId);
        if (! $restaurant) {
            return [];
        }

        $category = MenuCategory::where('restaurant_id', $restaurantId)
            ->where('external_id', $categoryId)
            ->first();

        if (! $category) {
            Log::warning('[WhatsApp] Category not found in DB', ['cat_ext_id' => $categoryId, 'rest_id' => $restaurantId]);

            return [];
        }

        return MenuItem::where('menu_category_id', $category->id)
            ->orderBy('id', 'asc')
            // ⬇️ import $restaurant into the closure
            ->get()->map(function ($item) use ($restaurant) {
                return [
                    'id' => 'item_'.$item->external_id,
                    'title' => $item->name,
                    'description' => ($item->description ?? '').' - '.$item->price.' KWD',
                    'image' => $this->getLocalImageAsBase64($item->local_image_path, false)
                        ?: $this->getBase64RestaurantImage($restaurant)
                        ?: $this->getPlaceholderImage(),
                ];
            })->toArray();
    }

    /**
     * Get basic details (title, price) for a single item.
     * Fetches the entire menu (and caches it) to find the item.
     * Accepts *raw* IDs like "item_123" and "restaurant_7".
     */
    public function getItemBasic(string $itemIdRaw, string $restaurantIdRaw, string $locale): ?array
    {
        $itemId = (int) \Illuminate\Support\Str::after($itemIdRaw, 'item_');
        $restaurantId = (int) \Illuminate\Support\Str::after($restaurantIdRaw, 'restaurant_');

        $restaurant = Vendors::find($restaurantId);
        if (! $restaurant) {
            return null;
        }
        // A single, highly efficient query to get the exact item.
        $item = MenuItem::where('restaurant_id', $restaurantId)
            ->where('external_id', $itemId)
            ->with('addonGroups.addons') // Eager load add-ons for better performance
            ->first();

        if (! $item) {
            Log::warning('[WhatsApp] Item not found in DB', ['item_ext_id' => $itemId, 'rest_id' => $restaurantId]);

            return null;
        }

        return [
            'id' => 'item_'.$item->external_id,
            'title' => $item->name,
            'price' => (float) $item->price,
            'addon_groups' => $item->addonGroups->toArray(), // Addon data is now available from the query
            'description' => $item->description ?? '',
            'image' => $this->getLocalImageAsBase64($item->local_image_path, true) // true for full quality
               ?: $this->getBase64RestaurantImage($restaurant) // Fallback 1: Restaurant Logo
               ?: $this->getPlaceholderImage(),
        ];
    }

    private function getMenuFromApi(Vendors $restaurant, string $locale): ?array
    {
        // Define a single, consistent cache key
        $cacheKey = "menu_api_response_{$restaurant->id}_{$locale}";

        // Remember the API response for 10 minutes
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($restaurant, $locale) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$restaurant->api_key,
                'Accept' => 'application/json',
            ])->get($restaurant->api_base_url.'/v1/menu', ['lang' => $locale]);

            // Check if the request was successful (status code 2xx)
            if (! $response->successful()) {
                Log::error('Menu API call failed', [
                    'restaurant_id' => $restaurant->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Return null to indicate failure
                return null;
            }

            // If successful, return the JSON data
            return $response->json('data');
        });
    }

    private function t(string $en, string $ar, string $locale): string
    {
        return $locale === 'ar' ? $ar : $en;
    }

    public function getRestaurantName($rawId, string $locale): ?string
    {
        $id = (int) preg_replace('/\D+/', '', $rawId);
        $r = Vendors::find($id);

        return $r ? ($locale === 'ar' ? $r->name_ar : $r->name) : null;
    }

    public function validatePromoCode(string $restaurantUiId, string $promoCode, array $items, string $locale): array
    {
        // extract numeric restaurant ID
        $restaurantId = (int) \Str::after($restaurantUiId, 'restaurant_');

        // fetch Restaurant to get its base URL and API key
        $restaurant = Vendors::find($restaurantId);
        if (! $restaurant) {
            Log::error('[RestaurantDataService] Promo: Restaurant not found', ['id' => $restaurantUiId]);

            return [
                'valid' => false,
                'discount_amount' => 0.0,
                'message' => $locale === 'ar'
                    ? 'المطعم غير موجود'
                    : 'Restaurant not found',
            ];
        }

        // build the promo-validate URL using the restaurant's api_base_url
        $url = rtrim($restaurant->api_base_url, '/').'/v1/promocode/validate';

        try {
            $resp = Http::withHeaders([
                'Authorization' => 'Bearer '.$restaurant->api_key,
                'Accept' => 'application/json',
            ])
                ->post($url, [
                    'promo_code' => $promoCode,
                    'lang' => $locale,
                    'items' => $items,
                ]);
        } catch (\Throwable $e) {
            Log::error('[RestaurantDataService] Promo HTTP error', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'discount_amount' => 0.0,
                'message' => $locale === 'ar'
                    ? 'خطأ في التواصل مع الخادم'
                    : 'Could not connect to promo service',
            ];
        }

        if (! $resp->successful()) {
            Log::warning('[RestaurantDataService] Promo API failure', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return [
                'valid' => false,
                'discount_amount' => 0.0,
                'message' => $locale === 'ar'
                    ? 'فشل التحقق من كود الخصم'
                    : 'Promo validation failed',
            ];
        }

        // return the restaurant's JSON response directly
        $data = $resp->json();

        return [
            'valid' => $data['valid'] ?? false,
            'discount_amount' => isset($data['discount_amount'])
                ? (float) $data['discount_amount']
                : 0.0,
            'message' => $data['message'] ?? '',
        ];
    }

    private function getRestaurantRatings(array $restaurantIds): array
    {
        if (empty($restaurantIds)) {
            return [];
        }

        $rows = Rating::query()
            ->selectRaw('restaurant_id, AVG(rating) as avg_rating, COUNT(*) as ratings_count')
            ->whereIn('restaurant_id', $restaurantIds)
            ->groupBy('restaurant_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            // clamp avg to [1,5]
            $avg = max(1.0, min(5.0, (float) $r->avg_rating));
            $out[(int) $r->restaurant_id] = [
                'avg' => $avg,
                'cnt' => (int) $r->ratings_count,
            ];
        }

        return $out;
    }

    private function stars5(float $avg): string
    {
        // show half star if .25–.75
        $filled = (int) floor($avg);
        $fraction = $avg - $filled;
        $half = ($fraction >= 0.25 && $fraction < 0.75) ? 1 : 0;
        $empty = 5 - $filled - $half;

        // Use Unicode: ★ = filled, ☆ = empty, ⯨ (half) is not widely supported; use ½ overlay instead
        // Safer: show half as '✩' to stand out, or just round. Here we’ll use '✩'.
        $s = str_repeat('★', $filled);
        if ($half) {
            $s .= '✩';
        }
        $s .= str_repeat('☆', $empty);

        return $s;
    }

    private function ratingLine(?array $rating, string $locale): string
    {
        if (! $rating) {
            return $locale === 'ar' ? 'بدون تقييم' : 'No ratings yet';
        }
        $avg = $rating['avg'];
        $cnt = $rating['cnt'];
        $stars = $this->stars5($avg);
        $avgTxt = number_format($avg, 1);

        // e.g. ★★★✩☆ 4.3 (127)
        return "{$stars} {$avgTxt} ({$cnt})";
    }

    private function ratingShort(?array $rating, string $locale): string
    {
        if (! $rating) {
            return '';
        }

        $avg = number_format((float) ($rating['avg'] ?? 0), 1); // e.g., 4.3
        $cnt = (int) ($rating['cnt'] ?? 0);                    // e.g., 127

        // inside the parentheses use a subtle middle dot; omit count if 0
        $inside = $cnt > 0 ? "{$avg}★ · {$cnt}" : "{$avg}★";

        // prefix with two spaces to visually separate from the title
        return "  ({$inside})";
    }
}
