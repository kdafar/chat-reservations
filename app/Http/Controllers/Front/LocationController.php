<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        // Validate only what the client sends
        $data = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'block_id' => ['nullable', 'integer', 'exists:blocks,id'],
        ]);

        $cityId = $data['city_id'] ?? null;
        $blockId = $data['block_id'] ?? null;

        // If we only got a block, derive its city
        if ($blockId && ! $cityId) {
            $cityId = Block::whereKey($blockId)->value('city_id');
        }

        // Guard: ensure the block (if any) belongs to the chosen/derived city
        if ($blockId && $cityId) {
            $belongs = Block::where('id', $blockId)->where('city_id', $cityId)->exists();
            if (! $belongs) {
                return response()->json([
                    'message' => 'Selected block does not belong to the selected city.',
                    'errors' => ['block_id' => ['Block/city mismatch.']],
                ], 422);
            }
        }

        // Derive state_id from the city if that column exists
        $stateId = null;
        if ($cityId && Schema::hasColumn('cities', 'state_id')) {
            $stateId = City::whereKey($cityId)->value('state_id');
        }

        // Save to session (this is what your browse page reads back)
        session([
            'location' => [
                'state_id' => $stateId,
                'city_id' => $cityId,
                'block_id' => $blockId,
            ],
        ]);

        // 204 = success, no content
        return response()->noContent();
    }
}
