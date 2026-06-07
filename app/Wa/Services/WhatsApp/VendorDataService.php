<?php

namespace App\Wa\Services\WhatsApp;

use App\Wa\Hub\Models\BusinessType;
use App\Wa\Hub\Models\Cuisine;
use App\Wa\Hub\Models\MenuCategory;
use App\Wa\Hub\Models\MenuItem;
use App\Wa\Hub\Models\Rating;
use App\Wa\Hub\Models\Vendors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class VendorDataService
{
    /**
     * Fetches the list of available business types.
     */
    public function getBusinessTypes(string $locale): array
    {
        return BusinessType::where('is_active', 1)
            ->get()
            ->map(fn (BusinessType $bt) => [
                'id' => 'bt_'.$bt->id,
                'title' => $bt->getTranslation('name', $locale),
                'description' => $bt->getTranslation('description', $locale) ?? '',
                'image' => $this->getBase64FromStorage($bt->image_url),
            ])->toArray();
    }

    /**
     * Fetches categories (formerly Cuisines) for a specific business type.
     */
    public function getCategoriesForBusinessType(int $businessTypeId, string $locale): array
    {
        return Cuisine::where('is_active', 1)
            ->where('business_type_id', $businessTypeId) // <-- Filters by the selected business type
            ->get()
            ->map(fn ($c) => [
                'id' => 'category_'.$c->id, // Generic ID
                'title' => $c->getTranslation('name', $locale),
                'description' => $c->getTranslation('description', $locale) ?? '',
                'image' => $this->getBase64FromStorage($c->image_url),
            ])->toArray();
    }

    /**
     * Fetches vendors for a specific category.
     */
    public function getVendorsForCategory(int $categoryId, string $locale, ?int $cityId = null): array
    {
        $cityId = $cityId ? (int) $cityId : null;

        $q = Vendors::query()
            ->where('is_visible_on_whatsapp', true)
            ->whereHas('cuisines', fn ($qq) => $qq->where('cuisine_id', $categoryId)); // Logic remains the same

        if ($cityId) {
            $q->whereHas('branches', function ($b) use ($cityId) {
                $b->where('is_active', true)
                    ->whereHas('deliveryAreas', fn ($da) => $da->where('city_id', $cityId));
            });
        }

        $q->orderByRaw('COALESCE(whatsapp_sort_order, 1000000) ASC');

        $vendors = $q->get()->filter->is_open;

        $ids = $vendors->pluck('id')->all();
        $ratings = $this->getVendorRatings($ids);

        return $vendors->map(function (Vendors $v) use ($locale, $ratings) {
            $name = $v->getTranslation('name', $locale) ?? '—';
            $desc = $v->getTranslation('description', $locale) ?? '';

            if ($v->badge_active) {
                $fallback = $locale === 'ar' ? 'en' : 'ar';
                $badgeLabel = $v->getTranslation('badge_label', $locale)
                    ?: $v->getTranslation('badge_label', $fallback);

                if (! empty($v->badge_emoji)) {
                    $name = trim("{$v->badge_emoji} {$name}");
                }
                if (! empty($badgeLabel)) {
                    $name .= " ({$badgeLabel})";
                }
            }

            $short = $this->ratingShort($ratings[$v->id] ?? null, $locale);
            if ($short !== '') {
                $name .= $short;
            }

            return [
                'id' => 'vendor_'.$v->id, // Generic ID
                'title' => $name,
                'description' => $desc,
                'image' => $this->getBase64FromStorage($v->logo_url),
            ];
        })->values()->toArray();
    }

    /**
     * Fetches menu categories for a specific vendor.
     */
    public function getMenuCategoriesForVendor(int $vendorId, string $locale): array
    {
        $vendor = Vendors::find($vendorId);
        if (! $vendor) {
            return [];
        }

        return MenuCategory::where('vendor_id', $vendorId) // <-- Corrected column name
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($cat) use ($locale, $vendor) {
                return [
                    'id' => 'category_'.$cat->external_id,
                    'title' => $cat->getTranslation('name', $locale),
                    'description' => $cat->getTranslation('description', $locale) ?? $this->t('View items', 'عرض الأصناف', $locale),
                    'image' => $this->getBase64FromStorage($cat->local_image_path)
                               ?: $this->getBase64FromStorage($vendor->logo_url)
                               ?: $this->getPlaceholderImage(),
                ];
            })->toArray();
    }

    /**
     * Fetches a simplified list of items for a category, for use in Flows.
     */
    public function getItemsForCategorySimple(string $categoryIdRaw, string $vendorIdRaw, string $locale): array
    {
        $categoryId = (int) \Str::after($categoryIdRaw, 'category_');
        $vendorId = (int) \Str::after($vendorIdRaw, 'vendor_');

        $vendor = Vendors::find($vendorId);
        if (! $vendor) {
            return [];
        }

        $category = MenuCategory::where('vendor_id', $vendorId) // <-- Corrected column name
            ->where('external_id', $categoryId)
            ->first();

        if (! $category) {
            return [];
        }

        return MenuItem::where('menu_category_id', $category->id)
            ->orderBy('id', 'asc')
            ->get()->map(function ($item) use ($vendor, $locale) {
                return [
                    'id' => 'item_'.$item->external_id,
                    'title' => $item->getTranslation('name', $locale),
                    'description' => ($item->getTranslation('description', $locale) ?? '').' - '.$item->price.' KWD',
                    'image' => $this->getBase64FromStorage($item->local_image_path)
                               ?: $this->getBase64FromStorage($vendor->logo_url)
                               ?: $this->getPlaceholderImage(),
                ];
            })->toArray();
    }

    /**
     * Fetches detailed information for a single item.
     */
    public function getItemBasic(string $itemIdRaw, string $vendorIdRaw, string $locale): ?array
    {
        $itemId = (int) \Str::after($itemIdRaw, 'item_');
        $vendorId = (int) \Str::after($vendorIdRaw, 'vendor_');

        $vendor = Vendors::find($vendorId);
        if (! $vendor) {
            return null;
        }

        $item = MenuItem::where('vendor_id', $vendorId) // <-- Corrected column name
            ->where('external_id', $itemId)
            ->with('addonGroups.addons')
            ->first();

        if (! $item) {
            return null;
        }

        return [
            'id' => 'item_'.$item->external_id,
            'title' => $item->getTranslation('name', $locale),
            'price' => (float) $item->price,
            'addon_groups' => $item->addonGroups->toArray(),
            'description' => $item->getTranslation('description', $locale) ?? '',
            'image' => $this->getBase64FromStorage($item->local_image_path, true)
                       ?: $this->getBase64FromStorage($vendor->logo_url)
                       ?: $this->getPlaceholderImage(),
        ];
    }

    /**
     * Validates a promo code against a vendor's API.
     */
    public function validatePromoCode(string $vendorUiId, string $promoCode, array $items, string $locale): array
    {
        $vendorId = (int) \Str::after($vendorUiId, 'vendor_');
        $vendor = Vendors::find($vendorId);
        if (! $vendor) {
            return [
                'valid' => false,
                'discount_amount' => 0.0,
                'message' => $this->t('Restaurant not found', 'المطعم غير موجود', $locale),
            ];
        }

        $url = rtrim($vendor->api_base_url, '/').'/v1/promocode/validate';

        try {
            $resp = Http::withHeaders([
                'Authorization' => 'Bearer '.$vendor->api_key,
                'Accept' => 'application/json',
            ])->post($url, [
                'promo_code' => $promoCode,
                'lang' => $locale,
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            Log::error('[VendorDataService] Promo HTTP error', ['exception' => $e->getMessage()]);

            return [
                'valid' => false, 'discount_amount' => 0.0,
                'message' => $this->t('Could not connect to promo service', 'خطأ في التواصل مع الخادم', $locale),
            ];
        }

        if (! $resp->successful()) {
            return [
                'valid' => false, 'discount_amount' => 0.0,
                'message' => $this->t('Promo validation failed', 'فشل التحقق من كود الخصم', $locale),
            ];
        }

        $data = $resp->json();

        return [
            'valid' => $data['valid'] ?? false,
            'discount_amount' => (float) ($data['discount_amount'] ?? 0.0),
            'message' => $data['message'] ?? '',
        ];
    }

    /**
     * A generic helper to get a base64 encoded image from a storage path.
     */
    private function getBase64FromStorage(?string $path, bool $fullQuality = false): string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return $this->getPlaceholderImage();
        }

        $lastModified = Storage::disk('public')->lastModified($path);
        $cacheKey = 'storage_image_b64_'.md5($path).'_'.$lastModified.($fullQuality ? '_full' : '_thumb');

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($path, $fullQuality) {
            try {
                $contents = Storage::disk('public')->get($path);
                $img = Image::make($contents);

                if (! $fullQuality) {
                    // Correctly apply constraints for thumbnails
                    $img->resize(240, 240, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->encode('jpg', 50);
                } else {
                    $img->encode('webp', 90);
                }

                return base64_encode($img->__toString());
            } catch (\Exception $e) {
                Log::error('Failed to process image from storage', ['path' => $path, 'error' => $e->getMessage()]);

                return $this->getPlaceholderImage();
            }
        });
    }

    private function getPlaceholderImage(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    private function t(string $en, string $ar, string $locale): string
    {
        return $locale === 'ar' ? $ar : $en;
    }

    private function getVendorRatings(array $vendorIds): array
    {
        if (empty($vendorIds)) {
            return [];
        }

        // Corrected foreign key from restaurant_id to vendor_id
        $rows = Rating::query()
            ->selectRaw('vendor_id, AVG(rating) as avg_rating, COUNT(*) as ratings_count')
            ->whereIn('vendor_id', $vendorIds)
            ->groupBy('vendor_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $avg = max(1.0, min(5.0, (float) $r->avg_rating));
            $out[(int) $r->vendor_id] = ['avg' => $avg, 'cnt' => (int) $r->ratings_count];
        }

        return $out;
    }

    private function ratingShort(?array $rating, string $locale): string
    {
        if (! $rating) {
            return '';
        }
        $avg = number_format((float) ($rating['avg'] ?? 0), 1);
        $cnt = (int) ($rating['cnt'] ?? 0);
        $inside = $cnt > 0 ? "{$avg}★ · {$cnt}" : "{$avg}★";

        return "  ({$inside})";
    }
}
