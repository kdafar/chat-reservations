<?php

namespace App\Wa\Services\About;

use App\Wa\Hub\Models\Vendors;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AboutService
{
    public function __construct(private AboutRepositoryInterface $repo) {}

    /** Build Hub "card" = ['image_url'=>?string, 'caption'=>string] */
    public function buildHubCard(string $locale = 'en', string $channel = 'whatsapp'): ?array
    {
        $profile = $this->repo->getActiveHubProfile($channel);
        if (! $profile) {
            return null;
        }

        $tpl = $this->repo->getTemplate('hub', $locale);
        $vars = [
            'hub_name' => $profile->getTranslation('name', $locale) ?? '',
            'hub_about' => $profile->getTranslation('about', $locale) ?? '',
            'hub_open_hours' => $profile->getTranslation('open_hours', $locale) ?? '',
            'hub_site' => $profile->site_url ?? '',
            'hub_phone' => $profile->phone ?? '',
            'hub_email' => $profile->email ?? '',
        ];

        $caption = $tpl ? $this->expand($tpl->body_template, $vars, $tpl->max_caption_length)
                        : $this->fallbackHubCaption($vars);

        $imageUrl = $this->resolveImageUrl($profile->logo_path);

        return ['image_url' => $imageUrl, 'caption' => $caption];
    }

    /** Build Restaurant "card" */
    public function buildRestaurantCard(Vendors $r, string $locale = 'en', ?array $options = []): array
    {
        $tpl = $this->repo->getRestaurantTemplate($r, $locale);
        $name = method_exists($r, 'getTranslation') ? $r->getTranslation('name', $locale) : ($r->name ?? '');

        // Optional extras to show (min_order, delivery_fee, etc.) can be provided in $options.
        $vars = [
            'name' => $name,
            'desc' => $this->transOrValue($r->about_desc ?? null, $locale)
                             ?? $this->transOrValue($r->description ?? null, $locale) ?? '',
            'cuisines' => $this->cuisineList($r, $locale),
            'min_order' => $options['min_order'] ?? '',
            'delivery_fee' => $options['delivery_fee'] ?? '',
            'open_hours' => $this->transOrValue($r->open_hours ?? null, $locale) ?? '',
            'website' => $r->website ?? '',
            'phone' => $r->phone ?? '',
        ];

        $caption = $tpl ? $this->expand($tpl->body_template, $vars, $tpl->max_caption_length)
                        : $this->fallbackRestaurantCaption($vars);

        $imageUrl = $this->resolveImageUrl($r->about_logo_path ?? null, $r->logo ?? null);

        return ['image_url' => $imageUrl, 'caption' => $caption];
    }

    // ---------- helpers ----------

    private function resolveImageUrl(?string ...$candidates): ?string
    {
        foreach ($candidates as $path) {
            if (! $path) {
                continue;
            }
            if (Str::startsWith($path, ['http://', 'https://'])) {
                return $path;
            }
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path);
            }
        }

        return null;
    }

    private function expand(string $template, array $vars, int $limit = 900): string
    {
        $out = preg_replace_callback('/\{(\w+)\}/', fn ($m) => trim((string) ($vars[$m[1]] ?? '')), $template);
        // Remove empty lines & excessive whitespace
        $out = preg_replace("/[ \t]+$/m", '', $out);
        $out = preg_replace("/\n{3,}/", "\n\n", trim($out));
        if (mb_strlen($out) > $limit) {
            $out = mb_substr($out, 0, $limit - 1).'…';
        }

        return $out;
    }

    private function fallbackHubCaption(array $v): string
    {
        return trim("*{$v['hub_name']}*\n"
            .($v['hub_about'] ? "{$v['hub_about']}\n" : '')
            .($v['hub_site'] ? "Website: {$v['hub_site']}\n" : '')
            .($v['hub_phone'] ? "Phone: {$v['hub_phone']}\n" : '')
            .($v['hub_open_hours'] ? "Hours: {$v['hub_open_hours']}" : '')
        );
    }

    private function fallbackRestaurantCaption(array $v): string
    {
        return trim("*{$v['name']}*\n"
            .($v['desc'] ? "{$v['desc']}\n" : '')
            .($v['cuisines'] ? "Cuisines: {$v['cuisines']}\n" : '')
            .(($v['min_order'] || $v['delivery_fee']) ? "Min order: {$v['min_order']} · Delivery: {$v['delivery_fee']}\n" : '')
            .($v['open_hours'] ? "Hours: {$v['open_hours']}\n" : '')
            .($v['website'] ? "Website: {$v['website']}\n" : '')
            .($v['phone'] ? "Phone: {$v['phone']}" : '')
        );
    }

    private function transOrValue($value, string $locale): ?string
    {
        if (is_array($value)) {
            return $value[$locale] ?? ($value['en'] ?? reset($value) ?? null);
        }

        return $value;
    }

    private function cuisineList(Vendors $r, string $locale): string
    {
        try {
            return $r->cuisines()->get()
                ->map(fn ($c) => is_array($c->name) ? ($c->name[$locale] ?? ($c->name['en'] ?? '')) : ($c->name ?? ''))
                ->filter()->implode(', ');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
