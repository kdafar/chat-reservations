<?php

namespace App\Wa\Services\Restaurant;

use App\Wa\Hub\Models\Cuisine;
use App\Wa\Hub\Models\RestaurantKeyword;
use App\Wa\Hub\Models\Vendors;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RestaurantSearchService
{
    /** Very light locale guess: if it contains Arabic script, use 'ar', else 'en'. */
    public function detectLocale(string $text): string
    {
        $locale = preg_match('/\p{Arabic}/u', $text) ? 'ar' : 'en';
        Log::debug('[Search] detectLocale', ['input' => $text, 'locale' => $locale]);

        return $locale;
    }

    /**
     * Split → normalize → filter stop words → dedupe → keep tokens >=2 chars.
     */
    public function tokens(string $text): array
    {
        // Get the comma-separated string from settings, or an empty string as a fallback.
        $stopWordsString = \setting('whatsapp.stop_words', '');
        // Convert the string into a clean array.
        $stopWords = empty($stopWordsString) ? [] : array_map('trim', explode(',', $stopWordsString));

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = collect($parts)
            ->map(fn ($t) => RestaurantKeyword::normalize($t))
            ->filter(fn ($t) => mb_strlen($t) >= 2)
            // This now uses the dynamic list from your admin panel
            ->filter(fn ($t) => ! in_array($t, $stopWords, true))
            ->unique()
            ->values()
            ->all();

        Log::debug('[Search] tokens', [
            'raw' => $text,
            'parts' => $parts,
            'tokens' => $tokens,
            'stop_words_used' => $stopWords, // Optional: for debugging
        ]);

        return $tokens;
    }

    /**
     * Free-text search with optional city constraint.
     *
     * Returns:
     * [
     *   'locale'  => 'en'|'ar',
     *   'exact'   => ?Vendors,
     *   'ranked'  => Collection<['restaurant'=>Vendors,'score'=>int]>,
     *   'cuisine' => ?Cuisine
     * ]
     */
    public function search(string $text, ?int $cityId = null, ?string $forceLocale = null): array
    {
        $locale = $forceLocale ?: $this->detectLocale($text);
        $cleanedText = trim($text);

        Log::debug('[Search] start', [
            'input' => $text,
            'cleaned' => $cleanedText,
            'locale' => $locale,
            'cityId' => $cityId,
        ]);

        if ($cleanedText === '') {
            Log::debug('[Search] empty input, early return');

            return ['locale' => $locale, 'exact' => null, 'ranked' => collect(), 'cuisine' => null];
        }

        // 1) Direct restaurant name match (highest priority)
        $directMatches = $this->directNameMatches($cleanedText, $cityId);
        Log::debug('[Search] directNameMatches', [
            'count' => $directMatches->count(),
            'ids' => $directMatches->pluck('id'),
            'names' => $directMatches->map(fn ($r) => [
                'id' => $r->id,
                'en' => $r->getTranslation('name', 'en'),
                'ar' => $r->getTranslation('name', 'ar'),
            ]),
        ]);

        if ($directMatches->count() === 1) {
            $exactRestaurant = $directMatches->first();
            Log::debug('[Search] exact by direct name', ['id' => $exactRestaurant->id]);

            return [
                'locale' => $locale,
                'exact' => $exactRestaurant,
                'ranked' => collect([['restaurant' => $exactRestaurant, 'score' => 99]]),
                'cuisine' => null,
            ];
        }

        // 2) Tokenize
        $tokens = $this->tokens($text);
        if (empty($tokens)) {
            Log::debug('[Search] no tokens, early return');

            return ['locale' => $locale, 'exact' => null, 'ranked' => collect(), 'cuisine' => null];
        }

        // 3) Exact keyword hits (IN)
        $rankedRows = $this->keywordHitQuery($tokens, $cityId)->get();
        Log::debug('[Search] keywordHitQuery result', [
            'tokens' => $tokens,
            'rows' => $rankedRows->map(fn ($r) => ['restaurant_id' => $r->restaurant_id, 'matches' => $r->matches]),
            'count' => $rankedRows->count(),
        ]);

        // 4) Fallback LIKE hits if nothing exact matched
        if ($rankedRows->isEmpty()) {
            $likeTokens = array_slice($tokens, 0, 5);
            $rankedRows = $this->keywordLikeQuery($likeTokens, $cityId)->get();
            Log::debug('[Search] keywordLikeQuery result', [
                'tokens' => $likeTokens,
                'rows' => $rankedRows->map(fn ($r) => ['restaurant_id' => $r->restaurant_id, 'matches' => $r->matches]),
                'count' => $rankedRows->count(),
            ]);
        }

        // 5) Load restaurants & score
        $scored = $this->loadRestaurantsWithScores($rankedRows);
        Log::debug('[Search] scored restaurants', [
            'count' => $scored->count(),
            'list' => $scored->map(fn ($x) => [
                'id' => $x['restaurant']->id,
                'en' => $x['restaurant']->getTranslation('name', 'en'),
                'ar' => $x['restaurant']->getTranslation('name', 'ar'),
                'score' => $x['score'],
            ]),
        ]);

        // 6) Decide if we have a confident "exact" from scored
        $exact = null;
        if ($scored->count() === 1 || (($scored->first()['score'] ?? 0) >= 2)) {
            $exact = $scored->first()['restaurant'];
            Log::debug('[Search] exact by keyword score', ['id' => $exact->id, 'score' => $scored->first()['score'] ?? null]);
        }

        // 7) Cuisine fallback
        $cuisine = null;
        if (! $exact && $scored->isEmpty()) {
            $cuisine = $this->matchCuisineByTokens($tokens, $locale);
            Log::debug('[Search] cuisine fallback', [
                'found' => (bool) $cuisine,
                'cuisine' => $cuisine ? [
                    'id' => $cuisine->id,
                    'en' => $cuisine->getTranslation('name', 'en'),
                    'ar' => $cuisine->getTranslation('name', 'ar'),
                ] : null,
            ]);
        }

        Log::debug('[Search] end', [
            'exactId' => $exact?->id,
            'rankedCount' => $scored->count(),
            'hasCuisine' => (bool) $cuisine,
        ]);

        return [
            'locale' => $locale,
            'exact' => $exact,
            'ranked' => $scored,
            'cuisine' => $cuisine,
        ];
    }

    /* ---------- internals ---------- */

    protected function keywordHitQuery(array $tokens, ?int $cityId)
    {
        $q = RestaurantKeyword::query()
            ->selectRaw('vendor_id, COUNT(*) as matches')
            ->whereIn('keyword', $tokens);

        if ($cityId) {
            $q->whereHas('restaurant.branches.deliveryAreas', fn ($qq) => $qq->where('city_id', $cityId));
        }

        return $q->groupBy('vendor_id')->orderByDesc('matches');
    }

    protected function keywordLikeQuery(array $tokens, ?int $cityId)
    {
        $q = RestaurantKeyword::query()
            ->selectRaw('vendor_id, COUNT(*) as matches')
            ->where(function ($qq) use ($tokens) {
                foreach ($tokens as $t) {
                    $qq->orWhere('keyword', 'like', "%{$t}%");
                }
            });

        if ($cityId) {
            $q->whereHas('restaurant.branches.deliveryAreas', fn ($qq) => $qq->where('city_id', $cityId));
        }

        return $q->groupBy('vendor_id')->orderByDesc('matches');
    }

    protected function loadRestaurantsWithScores($rows): Collection
    {
        $byId = collect($rows)->pluck('matches', 'vendor_id');

        Log::debug('[Search] loadRestaurantsWithScores input', [
            'ids' => $byId->keys()->values(),
            'matches' => $byId,
        ]);

        $restaurants = Vendors::query()
            ->whereIn('id', $byId->keys())
            ->where('is_visible_on_whatsapp', true)
            ->orderByRaw('COALESCE(whatsapp_sort_order, 1000000) ASC')
            ->get()
            ->filter->is_open;

        Log::debug('[Search] loadRestaurantsWithScores fetched', [
            'count' => $restaurants->count(),
            'ids' => $restaurants->pluck('id'),
        ]);

        $scored = $restaurants
            ->map(fn (Vendors $r) => [
                'restaurant' => $r,
                'score' => (int) $byId[$r->id],
            ])
            ->sortByDesc('score')
            ->values();

        return $scored;
    }

    /** Very light cuisine fallback: compare normalized cuisine names against tokens */
    protected function matchCuisineByTokens(array $tokens, string $locale): ?Cuisine
    {
        $best = null;
        $bestScore = 0;

        foreach (Cuisine::all() as $c) {
            $name = RestaurantKeyword::normalize((string) ($c->getTranslation('name', $locale) ?? ''));
            if ($name === '') {
                continue;
            }

            $score = 0;
            foreach ($tokens as $t) {
                if ($t === $name || Str::contains($name, $t)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $c;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    private function directNameMatches(string $token, ?int $cityId): \Illuminate\Support\Collection
    {
        $like = '%'.mb_strtolower($token, 'UTF-8').'%';

        $q = Vendors::query()
            ->where('is_visible_on_whatsapp', true)
            ->when($cityId, fn ($qq) => $qq->whereHas('branches', fn ($b) => $b->where('is_active', true)
                ->whereHas('deliveryAreas', fn ($da) => $da->where('city_id', $cityId))))
            ->where(function ($w) use ($like) {
                $w->where('name->en', 'like', $like)
                    ->orWhere('name->ar', 'like', $like);
            })
            ->orderByRaw('COALESCE(whatsapp_sort_order, 1000000) ASC');

        $results = $q->get()->filter->is_open->values();

        Log::debug('[Search] directNameMatches query done', [
            'token' => $token,
            'like' => $like,
            'count' => $results->count(),
            'ids' => $results->pluck('id'),
        ]);

        return $results;
    }
}
