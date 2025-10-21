<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeoController extends Controller
{
    public function states(Request $request)
    {
        $locale = app()->getLocale();
        $State = app(\App\Models\State::class);

        $rows = $State->newQuery()
            ->orderBy("name->{$locale}")
            ->get(['id', 'name']);

        return response()->json($rows->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->getTranslation('name', $locale),
        ]));
    }

    public function cities(Request $request)
    {
        $request->validate(['state_id' => 'required|integer|exists:states,id']);
        $locale = app()->getLocale();
        $City = app(\App\Models\City::class);

        $rows = $City->newQuery()
            ->where('state_id', $request->integer('state_id'))
            ->orderBy("name->{$locale}")
            ->get(['id', 'name', 'latitude as lat', 'longitude as lng']);

        return response()->json($rows->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->getTranslation('name', $locale),
            'lat' => $r->lat,
            'lng' => $r->lng,
        ]));
    }

    public function blocks(Request $request)
    {
        $request->validate(['city_id' => 'required|integer|exists:cities,id']);
        $locale = app()->getLocale();
        $Block = app(\App\Models\Block::class);

        $rows = $Block->newQuery()
            ->where('city_id', $request->integer('city_id'))
            ->orderBy("name->{$locale}")
            ->get(['id', 'name', 'latitude as lat', 'longitude as lng']);

        return response()->json($rows->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->getTranslation('name', $locale),
            'lat' => $r->lat,
            'lng' => $r->lng,
        ]));
    }

    public function nearest(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        // nearest block with coordinates
        $sql = '
            SELECT
                b.id AS block_id,
                b.name AS block_name,
                b.latitude AS block_lat,
                b.longitude AS block_lng,
                c.id AS city_id,
                c.name AS city_name,
                s.id AS state_id,
                s.name AS state_name,
                (6371 * ACOS(LEAST(1,
                    COS(RADIANS(?)) * COS(RADIANS(b.latitude)) * COS(RADIANS(b.longitude) - RADIANS(?))
                    + SIN(RADIANS(?)) * SIN(RADIANS(b.latitude))
                ))) AS distance_km
            FROM blocks b
            JOIN cities c ON c.id = b.city_id
            JOIN states s ON s.id = c.state_id
            WHERE b.latitude IS NOT NULL AND b.longitude IS NOT NULL
            ORDER BY distance_km ASC
            LIMIT 1
        ';

        $row = collect(DB::select($sql, [$data['lat'], $data['lng'], $data['lat']]))->first();

        if (! $row) {
            return response()->json(['found' => false]);
        }

        $locale = app()->getLocale();

        // If name columns are translatable (json), translate here via model if needed.
        // For speed we return raw; UI can still show it.
        return response()->json([
            'found' => true,
            'state' => ['id' => $row->state_id, 'name' => $row->state_name],
            'city' => ['id' => $row->city_id, 'name' => $row->city_name],
            'block' => ['id' => $row->block_id, 'name' => $row->block_name],
            'distance_km' => round($row->distance_km, 3),
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
        ]);
    }
}
