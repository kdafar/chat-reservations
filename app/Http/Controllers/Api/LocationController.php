<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function states(Request $request)
    {
        $locale = $request->get('locale', app()->getLocale() ?? 'en');

        $items = State::query()
            ->where('is_active', 1)
            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"$locale\"')) ASC")
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->getTranslation('name', $locale),
                'slug' => $s->slug,
                'is_active' => (bool) $s->is_active,
            ]);

        return response()->json(['data' => $items]);
    }

    public function cities(Request $request)
    {
        $locale = $request->get('locale', app()->getLocale() ?? 'en');
        $stateId = $request->integer('state_id');

        $query = City::query()->where('is_active', 1);
        if ($stateId) {
            $query->where('state_id', $stateId);
        }

        $items = $query
            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"$locale\"')) ASC")
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'state_id' => $c->state_id,
                'name' => $c->getTranslation('name', $locale),
                'slug' => $c->slug,
                'latitude' => $c->latitude,
                'longitude' => $c->longitude,
                'is_active' => (bool) $c->is_active,
            ]);

        return response()->json(['data' => $items]);
    }

    public function blocks(Request $request)
    {
        $locale = $request->get('locale', app()->getLocale() ?? 'en');
        $cityId = $request->integer('city_id');

        $query = Block::query()->where('is_active', 1);
        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        $items = $query
            ->orderBy('code')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'city_id' => $b->city_id,
                'name' => $b->getTranslation('name', $locale),
                'code' => $b->code,
                'latitude' => $b->latitude,
                'longitude' => $b->longitude,
                'is_active' => (bool) $b->is_active,
            ]);

        return response()->json(['data' => $items]);
    }
}
