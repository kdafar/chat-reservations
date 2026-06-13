<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\VisitStockRequest;
use App\Services\Clinic\ClinicStockService;
use App\Services\Clinic\VisitStockRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visit Stock Requests — v2 replacement for the Filament VisitStockRequestResource.
 *
 * Pharmacy worklist: requests raised from the visit console for stock items.
 * Each row shows its line items checked against LIVE branch stock (green = in
 * stock, red = short) so the dispenser can decide before fulfilling. Fulfil /
 * cancel delegate to VisitStockRequestService (atomic stock movement + visit
 * status resume). Branch scoping is automatic via BelongsToBranchScope.
 */
class VisitStockRequestsController extends Controller
{
    public function __construct(
        protected VisitStockRequestService $svc,
        protected ClinicStockService $stock,
    ) {}

    protected function authorizeView(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_visit_stock_request')) {
            abort(403, 'Not authorized to view stock requests.');
        }
    }

    protected function authorizeAct(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('update_visit_stock_request')) {
            abort(403, 'Not authorized to act on stock requests.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeView($request);
        $status = $request->input('status', VisitStockRequest::STATUS_PENDING);
        $query = VisitStockRequest::query()->with(['visit:id,booking_code', 'branch', 'requestedBy:id,name']);
        if (in_array($status, [VisitStockRequest::STATUS_PENDING, VisitStockRequest::STATUS_FULFILLED, VisitStockRequest::STATUS_CANCELLED], true)) { $query->where('status', $status); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Visit', 'Branch', 'Requested by', 'Status', 'Resume status', 'Notes'],
                fn ($r) => [$r->id, $r->visit?->booking_code, $r->branch?->localized_name, $r->requestedBy?->name, $r->status, $r->resume_status, $r->notes],
                'Visit Stock Requests',
                app()->getLocale() === 'ar',
            ),
            'visit-stock-requests-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $status = $request->input('status', VisitStockRequest::STATUS_PENDING);

        $query = VisitStockRequest::query()
            ->with(['visit:id,booking_code,patient_id', 'visit.patient:id,name', 'branch', 'requestedBy:id,name', 'lines.clinicItem']);

        if (in_array($status, [VisitStockRequest::STATUS_PENDING, VisitStockRequest::STATUS_FULFILLED, VisitStockRequest::STATUS_RECEIVED, VisitStockRequest::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        $rows = $query->orderByDesc('id')->limit(300)->get()
            ->map(fn (VisitStockRequest $r) => $this->present($r))->all();

        return Inertia::render('VisitStockRequests/Index', [
            'filters' => ['status' => $status],
            'rows' => $rows,
            'canAct' => (bool) $request->user()?->can('update_visit_stock_request'),
            'counts' => [
                'pending' => VisitStockRequest::query()->where('status', VisitStockRequest::STATUS_PENDING)->count(),
                'fulfilled' => VisitStockRequest::query()->where('status', VisitStockRequest::STATUS_FULFILLED)->count(),
                'received' => VisitStockRequest::query()->where('status', VisitStockRequest::STATUS_RECEIVED)->count(),
                'cancelled' => VisitStockRequest::query()->where('status', VisitStockRequest::STATUS_CANCELLED)->count(),
            ],
        ]);
    }

    public function fulfill(Request $request, VisitStockRequest $visitStockRequest): RedirectResponse
    {
        $this->authorizeAct($request);

        if ($visitStockRequest->status !== VisitStockRequest::STATUS_PENDING) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Request is no longer pending.']);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'resume_status' => ['required', 'in:awaiting_doctor,in_progress'],
        ]);

        try {
            // Store issues the stock but does NOT bill — the patient is billed
            // only once the doctor confirms receipt (see receive()).
            $this->svc->fulfill(
                $visitStockRequest,
                (int) ($request->user()->id ?? 0),
                $data['notes'] ?? null,
                $data['resume_status'],
                false, // autoReceive: defer billing to the doctor's receipt
            );

            return back()->with('flash', ['type' => 'success', 'message' => 'Stock issued — awaiting doctor receipt.']);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Doctor confirms receipt of an issued request. Bills the patient for the
     * confirmed quantities (per line, or full when omitted) and returns any
     * short-received remainder to stock.
     */
    public function receive(Request $request, VisitStockRequest $visitStockRequest): RedirectResponse
    {
        $this->authorizeAct($request);

        if ($visitStockRequest->status !== VisitStockRequest::STATUS_FULFILLED) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Request is not awaiting receipt.']);
        }

        $data = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Map [line_id => qty]; lines omitted entirely default to full issued qty.
        $lineQtys = [];
        foreach ($data['lines'] ?? [] as $row) {
            $lineQtys[(int) $row['line_id']] = (float) $row['qty'];
        }

        try {
            $this->svc->receive(
                $visitStockRequest,
                (int) ($request->user()->id ?? 0),
                $lineQtys,
                $data['notes'] ?? null,
            );

            return back()->with('flash', ['type' => 'success', 'message' => 'Receipt confirmed and items billed.']);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(Request $request, VisitStockRequest $visitStockRequest): RedirectResponse
    {
        $this->authorizeAct($request);

        if ($visitStockRequest->status !== VisitStockRequest::STATUS_PENDING) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Request is no longer pending.']);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->svc->cancel($visitStockRequest, (int) ($request->user()->id ?? 0), $data['reason']);

            return back()->with('flash', ['type' => 'success', 'message' => 'Request cancelled.']);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    protected function present(VisitStockRequest $r): array
    {
        $isPending = $r->status === VisitStockRequest::STATUS_PENDING;
        $branchId = (int) $r->branch_id;

        $lines = $r->lines->map(function ($line) use ($isPending, $branchId) {
            $item = $line->clinicItem;
            $req = (float) $line->qty_base;
            // Only hit the live-stock service for pending rows; historical rows
            // just show what was requested.
            $avail = ($isPending && $item) ? $this->stock->availableBase($branchId, $item->id) : null;

            return [
                'id' => (int) $line->id,
                'name' => $item?->localized_name ?? ('#'.$line->clinic_item_id),
                'qty' => $req,
                'received_qty' => $line->received_qty !== null ? (float) $line->received_qty : null,
                'available' => $avail,
                'short' => $avail !== null && $avail < $req,
            ];
        })->all();

        return [
            'id' => $r->id,
            'visit_id' => $r->visit_id,
            'visit_code' => $r->visit?->booking_code ?? ('#'.$r->visit_id),
            'patient_name' => $r->visit?->patient?->name,
            'branch' => $r->branch?->localized_name ?? ('#'.$r->branch_id),
            'requested_by' => $r->requestedBy?->name,
            'created_at' => optional($r->created_at)->format('Y-m-d h:i A'),
            'status' => $r->status,
            'notes' => $r->notes,
            'lines' => $lines,
            'any_short' => collect($lines)->contains('short', true),
        ];
    }
}
