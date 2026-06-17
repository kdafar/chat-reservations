<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Models\StockTransfer;
use App\Services\Clinic\StockTransferService;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * v2 inter-branch stock transfers (hub → branch dispatch). Clinic-isolated by
 * the StockTransfer partner scope. Request = create_stock_transfers; dispatch
 * (moves stock) = update_stock_transfers.
 */
class StockTransfersController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeView(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('view_any_stock_transfers'), 403, 'Not authorized to view stock transfers.');
    }

    protected function canRequest(Request $request): bool
    {
        return (bool) $request->user()?->can('create_stock_transfers');
    }

    protected function canDispatch(Request $request): bool
    {
        return (bool) $request->user()?->can('update_stock_transfers');
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $locale = app()->getLocale();
        $status = $request->input('status', 'all');

        $query = StockTransfer::query()
            ->with(['fromBranch:id,name', 'toBranch:id,name', 'lines', 'requester:id,name', 'dispatcher:id,name'])
            ->when(in_array($status, ['pending', 'dispatched', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id');

        $page = $query->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (StockTransfer $t) => [
            'id' => $t->id,
            'status' => $t->status,
            'from' => $t->fromBranch?->getTranslation('name', $locale, true) ?? ('#'.$t->from_branch_id),
            'to' => $t->toBranch?->getTranslation('name', $locale, true) ?? ('#'.$t->to_branch_id),
            'lines_count' => $t->lines->count(),
            'qty_total' => (float) $t->lines->sum('qty_base'),
            'visit_id' => $t->visit_id,
            'requested_by' => $t->requester?->name,
            'dispatched_by' => $t->dispatcher?->name,
            'dispatched_at' => optional($t->dispatched_at)->toIso8601String(),
            'created_at' => optional($t->created_at)->toIso8601String(),
        ]);

        // A transfer is intra-clinic, so the page operates within ONE clinic.
        // Resolve it: a non-admin's own clinic, or — for a global admin who
        // bypasses BelongsToPartnerScope — the clinic that owns a hub branch,
        // else the first clinic. Without this anchor the admin would see every
        // clinic's branches and the catalog repeated once per clinic.
        $partnerId = $this->accessiblePartnerIds()[0] ?? null; // null = global admin
        if ($partnerId === null) {
            $partnerId = (int) (\App\Models\Branch::query()->withoutGlobalScopes()->where('is_hub', true)->value('partner_id')
                ?: \App\Models\Partner::query()->orderBy('id')->value('id')) ?: null;
        }
        $hubId = app(StockTransferService::class)->hubBranchId($partnerId);
        $hubStock = $hubId
            ? ClinicItemStock::query()->where('branch_id', $hubId)->pluck('qty_on_hand_base', 'clinic_item_id')
            : collect();

        // The resolved clinic's stockable catalog (its own items + shared globals),
        // de-duped by name — preferring the copy that actually carries hub stock —
        // so the same item never appears twice in the picker.
        $nameOf = fn ($i) => is_array($i->name) ? ($i->name[$locale] ?? $i->name['en'] ?? reset($i->name)) : $i->name;
        $items = ClinicItem::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)->where('is_stockable', true)
            ->when($partnerId, fn ($q) => $q->where(fn ($w) => $w->where('partner_id', $partnerId)->orWhereNull('partner_id')))
            ->orderBy('name')->get(['id', 'name'])
            ->sortByDesc(fn ($i) => (float) ($hubStock[$i->id] ?? 0))
            ->unique($nameOf)
            ->sortBy($nameOf)
            ->values();

        return Inertia::render('StockTransfers/Index', [
            'filters' => ['status' => $status],
            'page' => $page,
            'branches' => $this->accessibleBranches()->when($partnerId, fn ($c) => $c->where('partner_id', $partnerId))->values()->all(),
            'hub_branch_id' => $hubId,
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'name' => is_array($i->name) ? ($i->name[$locale] ?? $i->name['en'] ?? reset($i->name)) : $i->name,
                'hub_on_hand' => (float) ($hubStock[$i->id] ?? 0),
            ])->all(),
            'can_request' => $this->canRequest($request),
            'can_dispatch' => $this->canDispatch($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canRequest($request), 403, 'Not authorized to request transfers.');

        $data = $request->validate([
            'from_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.clinic_item_id' => ['required', 'integer', 'exists:clinic_items,id'],
            'lines.*.qty_base' => ['required', 'numeric', 'min:0.0001'],
        ]);

        // Clinic of the destination branch (must be one the user can act in).
        $partnerId = (int) \App\Models\Branch::query()->withoutGlobalScopes()->whereKey($data['to_branch_id'])->value('partner_id');
        $accessible = $this->accessiblePartnerIds();
        if ($accessible !== null && ! in_array($partnerId, $accessible, true)) {
            abort(403, 'That branch is not in your clinic.');
        }

        try {
            app(StockTransferService::class)->create(
                $partnerId,
                $data['from_branch_id'] ?? null,
                (int) $data['to_branch_id'],
                $data['lines'],
                (int) (auth()->id() ?? 0),
                null,
                $data['notes'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Transfer created.']);
    }

    public function dispatchTransfer(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canDispatch($request), 403, 'Not authorized to dispatch transfers.');

        try {
            app(StockTransferService::class)->dispatch($transfer, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Transfer dispatched — stock moved to the branch.']);
    }

    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canRequest($request) || $this->canDispatch($request), 403);

        try {
            app(StockTransferService::class)->cancel($transfer);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Transfer cancelled.']);
    }
}
