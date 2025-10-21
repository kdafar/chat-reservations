<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    /**
     * Resolve a localized label from a translatable array or string.
     */
    private function t(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            $loc = app()->getLocale();

            return (string) ($value[$loc] ?? $value['en'] ?? reset($value) ?? '');
        }

        return (string) $value;
    }

    private function enrichModifiers(MenuItem $item, array $modifiers): array
    {
        // pull-through reserved keys if present
        $reserved = [];
        foreach (['__note', '__offer'] as $k) {
            if (array_key_exists($k, $modifiers)) {
                $reserved[$k] = $modifiers[$k];
                unset($modifiers[$k]);
            }
        }

        // CASE 1: already priced objects (flat array)
        $looksPriced = ! empty($modifiers)
            && array_is_list($modifiers)
            && is_array($modifiers[0] ?? null)
            && (array_key_exists('price', $modifiers[0]) || array_key_exists('price_delta', $modifiers[0]));

        if ($looksPriced) {
            $flat = [];
            foreach ($modifiers as $m) {
                $flat[] = [
                    'group_id' => (int) ($m['group_id'] ?? $m['modifier_group_id'] ?? 0),
                    'option_id' => (int) ($m['option_id'] ?? $m['modifier_option_id'] ?? $m['id'] ?? 0),
                    'name' => (string) ($m['name'] ?? $this->t($m['label'] ?? '')),
                    'price' => (float) ($m['price'] ?? $m['price_delta'] ?? 0),
                ];
            }

            return $flat + $reserved; // ✅ keep reserved keys
        }

        // CASE 2: map: groupId => id|[ids]
        $ids = [];
        foreach ($modifiers as $sel) {
            foreach ((array) $sel as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));

        if (! $ids) {
            return [] + $reserved; // nothing selected but keep reserved keys
        }

        $options = ModifierOption::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($modifiers as $groupId => $sel) {
            foreach ((array) $sel as $id) {
                $id = (int) $id;
                $opt = $options->get($id);
                $out[] = [
                    'group_id' => (int) $groupId,
                    'option_id' => $id,
                    'name' => $opt ? $this->t($opt->name) : '',
                    'price' => (float) ($opt->price_delta ?? 0),
                ];
            }
        }

        return $out + $reserved; // ✅ keep reserved keys
    }

    /**
     * Build one response payload with summary + pre-rendered lines HTML.
     */
    private function cartPayload(): array
    {
        $cart = $this->cartService->getCartDto();
        $html = view('front.cart._fragment', compact('cart'))->render();

        $deliveryFee = (float) ($cart->deliveryFee ?? $cart->delivery_fee ?? 0);
        $discount = (float) ($cart->discount ?? 0);
        $total = (float) ($cart->total ?? ($cart->subtotal + $deliveryFee));

        return [
            'count' => (int) $cart->itemCount,
            'currency' => $cart->currency,
            'subtotal' => (float) $cart->subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'subtotal_formatted' => number_format((float) $cart->subtotal, 3),
            'discount_formatted' => number_format($discount, 3),
            'delivery_fee_formatted' => number_format($deliveryFee, 3),
            'total_formatted' => number_format($total, 3),
            'coupon' => $cart->coupon ?? null,
            'lines_html' => $html,
        ];
    }

    public function index()
    {
        $cart = $this->cartService->getCartDto();

        return view('front.cart.index', compact('cart'));
    }

    /**
     * Render just the lines fragment (releases session lock).
     */
    public function lines()
    {
        $t0 = microtime(true);
        $cart = $this->cartService->getCartDto();
        Session::save();
        $html = view('front.cart._fragment', compact('cart'))->render();
        $ms = (int) ((microtime(true) - $t0) * 1000);

        return response($html)->header('X-Render-MS', (string) $ms);
    }

    /**
     * JSON summary for sidebars.
     */
    public function summary()
    {
        $t0 = microtime(true);
        $cart = $this->cartService->getCartDto();
        Session::save();

        $deliveryFee = (float) ($cart->deliveryFee ?? $cart->delivery_fee ?? 0);
        $discount = (float) ($cart->discount ?? 0);
        $total = (float) ($cart->total ?? ($cart->subtotal + $deliveryFee));

        $payload = [
            'count' => (int) $cart->itemCount,
            'currency' => $cart->currency,
            'subtotal' => (float) $cart->subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'subtotal_formatted' => number_format((float) $cart->subtotal, 3),
            'discount_formatted' => number_format($discount, 3),
            'delivery_fee_formatted' => number_format($deliveryFee, 3),
            'total_formatted' => number_format($total, 3),
            'coupon' => $cart->coupon ?? null,
        ];

        $ms = (int) ((microtime(true) - $t0) * 1000);

        return response()->json($payload)->header('X-Compute-MS', (string) $ms);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
            'modifiers' => ['sometimes', 'array'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'offer' => ['sometimes', 'nullable', 'array'],
            'force' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($data) {
            $branch = Branch::findOrFail($data['branch_id']);
            $item = MenuItem::where('branch_id', $branch->id)->findOrFail($data['item_id']);

            // normalize/enrich selected ids into priced rows
            $rawMods = $data['modifiers'] ?? [];

            // preserve reserved fields BEFORE enrich (UI may send them here)
            $reserved = [
                '__note' => $data['note'] ?? ($rawMods['__note'] ?? null),
                '__offer' => $rawMods['__offer'] ?? null,
            ];
            unset($rawMods['__note'], $rawMods['__offer']);

            $mods = $this->enrichModifiers($item, $rawMods);

            // append reserved keys back so CartService can persist them
            if ($reserved['__note'] !== null) {
                $mods['__note'] = $reserved['__note'];
            }
            if ($reserved['__offer'] !== null) {
                $mods['__offer'] = $reserved['__offer'];
            }

            $result = $this->cartService->addItem(
                $branch,
                $item,
                (int) $data['qty'],
                $mods,
                (bool) ($data['force'] ?? false),
                $data['note'] ?? null,     // <-- pass note
                $data['offer'] ?? null
            );

            if (! $result['ok']) {
                return response()->json([
                    'status' => 'cart_conflict',
                    'conflict' => $result['conflict'],
                ], 409);
            }

            return response()->json(['status' => 'ok'] + $this->cartPayload());
        });
    }

    /**
     * Update qty and/or note.
     */
    public function update(string $rowId, Request $request)
    {
        $data = $request->validate([
            'qty' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($rowId, $data) {
            // qty?
            if (array_key_exists('qty', $data)) {
                if (! $this->cartService->updateItem($rowId, (int) $data['qty'])) {
                    return response()->json(['message' => 'Item not found in cart.'], 404);
                }
            }

            // note? (write directly on the cart item row if present)
            if (array_key_exists('note', $data)) {
                $this->cartService->updateItemNote($rowId, $data['note']);
            }

            return response()->json(['message' => 'Updated'] + $this->cartPayload());
        });
    }

    public function destroy(string $rowId)
    {
        return DB::transaction(function () use ($rowId) {
            if (! $this->cartService->removeItem($rowId)) {
                return response()->json(['message' => 'Item not found in cart.'], 404);
            }

            return response()->json(['message' => 'Removed'] + $this->cartPayload());
        });
    }

    public function clear()
    {
        return DB::transaction(function () {
            $this->cartService->clear();

            return response()->json(['message' => 'Cart cleared'] + $this->cartPayload());
        });
    }

    // in CartController
    public function setAddress(Request $request)
    {
        if (auth()->check()) {
            $data = $request->validate([
                'address_id' => ['required', 'integer', 'exists:addresses,id'],
            ]);
            $ok = $this->cartService->setAddressById((int) $data['address_id']);
            if (! $ok) {
                return response()->json(['message' => 'Address not found for this user'], 422);
            }
        } else {
            $data = $request->validate([
                'city_id' => ['nullable', 'integer'],
                'block_id' => ['nullable', 'integer'],
                'label' => ['nullable', 'string', 'max:40'],
                'street' => ['required', 'string', 'max:190'],
                'building' => ['nullable', 'string', 'max:190'],
                'house' => ['nullable', 'string', 'max:190'],
                'apartment' => ['nullable', 'string', 'max:190'],
                'floor' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string', 'max:500'],
                'latitude' => ['nullable', 'numeric'],
                'longitude' => ['nullable', 'numeric'],
            ]);
            $this->cartService->setGuestAddress($data);
        }

        // Return fresh summary (includes delivery_fee/total)
        return $this->summary();
    }
}
