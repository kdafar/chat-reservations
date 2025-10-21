<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionsController extends Controller
{
    public function index(Request $req)
    {
        $branchId = $req->integer('branch_id');
        $serviceId = $req->integer('service_id');
        $partnerId = $req->integer('partner_id');

        $promos = Promotion::query()
            ->active()
            ->when($serviceId, fn ($q) => $q->where(function ($qq) use ($serviceId) {
                $qq->whereNull('service_id')->orWhere('service_id', $serviceId);
            }))
            ->when($partnerId, fn ($q) => $q->where(function ($qq) use ($partnerId) {
                $qq->whereNull('partner_id')->orWhere('partner_id', $partnerId);
            }))
            ->when($branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderByDesc('priority')
            ->with(['conditions', 'actions'])
            ->limit(50)
            ->get();

        $data = $promos->map(function ($p) {
            $image = $p->image_path ? asset('storage/'.$p->image_path) : null;

            // --- Build CTA ---
            $cta = null;

            // BXGY same item → add (buy_qty + get_qty) of that item (engine will discount the free part)
            if ($cond = $p->conditions->firstWhere('condition_type', 'bxgy_same_item')) {
                $itemId = (int) ($cond->payload['item_id'] ?? 0);
                $buy = max(1, (int) ($cond->payload['buy_qty'] ?? 1));
                $get = max(1, (int) ($cond->payload['get_qty'] ?? 1));
                if ($itemId) {
                    $qty = $buy + $get;
                    $cta = [
                        'action' => 'add_items',
                        'items' => [['item_id' => $itemId, 'qty' => $qty]],
                        'label' => [
                            'en' => "Add {$qty} to apply",
                            'ar' => "أضف {$qty} لتفعيل العرض",
                        ],
                        'kind' => 'bxgy',
                        'meta' => compact('buy', 'get', 'itemId'),
                    ];
                }
            }

            // Bundle price → add exactly the bundle set once
            if ($p->type === 'bundle') {
                $bundleCond = $p->conditions->firstWhere('condition_type', 'has_items_set');
                $items = collect($bundleCond->payload['items'] ?? [])->map(fn ($i) => [
                    'item_id' => (int) $i['item_id'],
                    'qty' => (int) ($i['qty'] ?? 1),
                ])->filter(fn ($i) => $i['item_id'] > 0 && $i['qty'] > 0)->values()->all();

                if (! empty($items)) {
                    $cta = [
                        'action' => 'add_items',
                        'items' => $items,
                        'label' => [
                            'en' => 'Add bundle',
                            'ar' => 'أضف الباقة',
                        ],
                        'kind' => 'bundle',
                    ];
                }
            }

            // Cart-level offers → no CTA (informational only)
            if ($p->type === 'cart') {
                $cta = null;
            }

            return [
                'id' => $p->id,
                'title' => $p->title,     // translatable array
                'summary' => $p->summary,   // translatable array
                'type' => $p->type,
                'image' => $image,
                'cta' => $cta,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
