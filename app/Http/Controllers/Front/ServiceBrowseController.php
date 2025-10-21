<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Branch;
use App\Models\City;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ServiceBrowseController extends Controller
{
    public function index(Service $service, Request $request)
    {
        $locale = app()->getLocale();

        // Respect saved location from session, allow querystring to override
        $cityIdFromSession = (int) session('location.city_id');
        $blockIdFromSession = (int) session('location.block_id');

        $cityId = $request->filled('city_id') ? (int) $request->integer('city_id') : ($cityIdFromSession ?: null);
        $blockId = $request->filled('block_id') ? (int) $request->integer('block_id') : ($blockIdFromSession ?: null);

        // If a block is chosen, align the city to the block's city to avoid conflicts
        if ($blockId) {
            $blockCityId = Block::whereKey($blockId)->value('city_id');
            if ($blockCityId) {
                $cityId = (int) $blockCityId;
            }
        }

        // Base query
        $query = Branch::query()
            ->with([
                'city:id,name',
                // include delivery_fee_override if you use it
                'blocks' => fn ($q) => $q->select('blocks.id', 'blocks.name')
                    ->withPivot(['delivery_fee', 'min_order_amount', 'delivery_fee_override', 'is_active']),
                'openingHours:id,branch_id,day_of_week,opens_at,closes_at,is_closed',
            ])
            ->available()
            ->forService($service->id);

        // Filtering
        if ($blockId) {
            // Filter by the chosen block (and active pivot if present)
            $query->whereHas('blocks', function ($q) use ($blockId, $cityId) {
                $q->where('branch_block.block_id', $blockId)
                    ->when(
                        Schema::hasColumn('branch_block', 'is_active'),
                        fn ($qq) => $qq->where('branch_block.is_active', 1)
                    );
                // Optional extra guard: ensure the block belongs to the (aligned) city
                if ($cityId && Schema::hasColumn('blocks', 'city_id')) {
                    $q->where('blocks.city_id', $cityId);
                }
            });
        } elseif ($cityId) {
            // No block; show branches that cover any block in this city
            $query->whereHas('blocks', fn ($q) => $q->where('blocks.city_id', $cityId));
        }

        // Search
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where(function ($q) use ($searchTerm, $locale) {
                $q->where("name->{$locale}", 'like', "%{$searchTerm}%")
                    ->orWhere('address', 'like', "%{$searchTerm}%");
            });
        }

        // Sorting
        $sort = strtolower($request->get('sort', 'recommended'));
        $hasIsActive = Schema::hasColumn('branch_block', 'is_active');
        $isActiveSQL = $hasIsActive ? ' AND bb.is_active = 1' : '';

        switch ($sort) {
            case 'rating':
                $query->orderByDesc('rating_avg');
                break;

            case 'fee':
                $query->orderByRaw("
                COALESCE(
                    (SELECT bb.delivery_fee
                       FROM branch_block bb
                      WHERE bb.branch_id = branches.id
                        AND bb.block_id = ?{$isActiveSQL}
                      LIMIT 1),
                    branches.delivery_fee
                ) ASC
            ", [$blockId ?: 0])->orderByDesc('rating_avg');
                break;

            case 'minimum':
                $query->orderByRaw("
                COALESCE(
                    (SELECT bb.min_order_amount
                       FROM branch_block bb
                      WHERE bb.branch_id = branches.id
                        AND bb.block_id = ?{$isActiveSQL}
                      LIMIT 1),
                    branches.min_order_amount
                ) ASC
            ", [$blockId ?: 0])->orderByDesc('rating_avg');
                break;

            case 'a_z':
                $query->orderBy("name->{$locale}");
                break;

            case 'z_a':
                $query->orderByDesc("name->{$locale}");
                break;

            default: // 'recommended'
                if (Schema::hasColumn('branches', 'is_featured')) {
                    $query->orderByDesc('is_featured');
                }
                $query->orderByDesc('rating_avg')->orderBy("name->{$locale}");
        }

        // Columns
        $selectColumns = [
            'id', 'partner_id', 'slug', 'name', 'phone', 'address',
            'city_id', 'latitude', 'longitude',
            'rating_avg', 'rating_count',
            'delivery_fee', 'min_order_amount',
            'is_available', 'open_for_delivery', 'open_for_pickup',
            'cover_image_path', 'logo_path',
        ];
        if (Schema::hasColumn('branches', 'is_featured')) {
            $selectColumns[] = 'is_featured';
        }

        // Paginate (keep query string for next pages)
        $branches = $query->paginate(16, $selectColumns)->withQueryString();

        // Append accessors
        $branches->getCollection()->each(function ($branch) {
            $branch->append('tags');
        });

        // JSON (for infinite scroll/front-end fetches)
        if ($request->wantsJson()) {
            return $branches;
        }

        // Cities (for filters)
        $cities = City::query()
            ->orderBy("name->{$locale}")
            ->get(['id', 'name']);

        $mode = in_array($request->get('mode'), ['pickup', 'delivery'], true)
            ? $request->get('mode')
            : 'delivery';

        return view('front.service-browse', [
            'service' => $service,
            'branches' => $branches,
            'cities' => $cities,
            'mode' => $mode,
            'selectedCityId' => $cityId,
            'selectedBlockId' => $blockId,
        ]);
    }
}
