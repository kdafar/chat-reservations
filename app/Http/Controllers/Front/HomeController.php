<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\HomepageSection;
use App\Models\Service;
use App\Models\State;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        $locale = app()->getLocale();

        // 1) Homepage record (latest or your own "active" logic)
        $homepage = Cache::remember("home:section:{$locale}", 300, function () {
            return HomepageSection::query()->latest('id')->first();
        });

        // 2) Services for the grid (localized sort)
        $services = Cache::remember("home:services:{$locale}", 300, function () use ($locale) {
            return Service::query()
                ->where('is_active', true)
                ->orderBy("name->{$locale}")
                ->get(['id', 'name', 'slug', 'icon', 'is_active']);
        });

        // 3) States → Cities → Blocks for the hero location picker
        $states = Cache::remember("home:states:{$locale}", 300, function () use ($locale) {
            return State::query()
                ->where('is_active', true)
                ->orderBy("name->{$locale}")
                ->with([
                    'cities' => function ($q) use ($locale) {
                        $q->where('is_active', true)
                            ->orderBy("name->{$locale}")
                            ->with([
                                'blocks' => fn ($b) => $b->where('is_active', true)
                                    ->orderBy('code')
                                    ->orderBy('id'),
                            ]);
                    },
                ])
                ->get(['id', 'name']);
        });

        // 4) Admin-curated cities (via HomepageSection → featuredCityLinks)
        $homeCities = collect();
        if ($homepage) {
            $homeCities = Cache::remember("home:curatedCities:{$locale}:{$homepage->id}", 300, function () use ($homepage) {
                $links = $homepage->featuredCityLinks()
                    ->with([
                        'city' => fn ($q) => $q->where('is_active', true)->with([
                            'state:id,name',
                            'blocks' => fn ($b) => $b->where('is_active', true)->orderBy('code')->orderBy('id'),
                        ]),
                    ])
                    ->orderBy('sort_order')
                    ->take(8)
                    ->get();

                return $links->pluck('city')->filter()->values();
            });
        }

        // Fallback if nothing curated yet
        if ($homeCities->isEmpty()) {
            $homeCities = Cache::remember("home:fallbackCities:{$locale}", 300, function () use ($locale) {
                return City::query()
                    ->where('is_active', true)
                    ->with([
                        'state:id,name',
                        'blocks' => fn ($b) => $b->where('is_active', true)->orderBy('code')->orderBy('id'),
                    ])
                    ->orderBy("name->{$locale}")
                    ->take(8)
                    ->get();
            });
        }

        return view('front.home', compact('homepage', 'services', 'states', 'homeCities'));
    }
}
