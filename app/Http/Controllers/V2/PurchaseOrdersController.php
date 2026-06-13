<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Vendor;
use App\Models\ClinicItem;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchasePayment;
use App\Models\Purchasing\PurchaseReceipt;
use App\Services\Clinic\PurchaseService;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * v2 purchase orders (procurement). A clinic raises a PO to a vendor, receives
 * goods into a branch's stock (partial allowed), and pays the vendor. Receiving
 * posts Dr Inventory / Cr Accounts Payable; paying posts Dr AP / Cr Cash/Bank.
 * Branch-isolated via PurchaseOrder's branch scope. See PurchaseService.
 *
 * Create / edit / view are DEDICATED PAGES (not modals), matching the WhatsApp
 * v2 module convention: Purchases/Form (create+edit) and Purchases/Show.
 */
class PurchaseOrdersController extends Controller
{
    use ResolvesAccessibleClinics;

    public function __construct(protected PurchaseService $svc) {}

    protected function authorizeView(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('view_any_purchase_orders'), 403, 'Not authorized to view purchase orders.');
    }

    protected function canCreate(Request $request): bool
    {
        return (bool) $request->user()?->can('create_purchase_orders');
    }

    protected function canManage(Request $request): bool
    {
        return (bool) $request->user()?->can('update_purchase_orders');
    }

    /** Approve / reject a PO — segregated from create + operate. */
    protected function canApprove(Request $request): bool
    {
        return (bool) $request->user()?->can('approve_purchase_orders');
    }

    /** Pay a vendor / void a payment — finance gate, segregated from ops. */
    protected function canPay(Request $request): bool
    {
        return (bool) $request->user()?->can('pay_purchase_orders');
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $locale = app()->getLocale();
        $status = $request->input('status', 'all');
        $search = trim((string) $request->input('q', ''));

        $statuses = [
            PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING_APPROVAL,
            PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_REJECTED,
            PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_ACKNOWLEDGED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED,
        ];

        $query = PurchaseOrder::query()
            ->with(['vendor:id,name,code', 'branch:id,name', 'lines:id,purchase_order_id', 'receipts:id,purchase_order_id,total_amount', 'payments:id,purchase_order_id,amount'])
            ->when(in_array($status, $statuses, true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('code', 'like', "%{$search}%"))
            ->orderByDesc('id');

        $page = $query->paginate(20)->withQueryString();
        $page->getCollection()->transform(function (PurchaseOrder $po) use ($locale) {
            $received = (float) $po->receipts->sum('total_amount');
            $paid = (float) $po->payments->sum('amount');

            return [
                'id' => $po->id,
                'code' => $po->code,
                'status' => $po->status,
                'vendor' => $po->vendor?->name,
                'branch' => $po->branch?->getTranslation('name', $locale, true) ?? ('#'.$po->branch_id),
                'order_date' => optional($po->order_date)->toDateString(),
                'eta' => optional($po->eta)->toDateString(),
                'currency' => $po->currency,
                'is_foreign' => strtoupper((string) $po->currency) !== 'KWD',
                'lines_count' => $po->lines->count(),
                'total' => (float) $po->total,
                'outstanding' => round($received - $paid, 3),
            ];
        });

        // Status-monitoring KPIs over the user's full (scoped) PO set.
        $counts = PurchaseOrder::query()
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $openOutstanding = (float) PurchaseOrder::query()
            ->whereNotIn('status', [PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])
            ->withSum('receipts as r_sum', 'total_amount')
            ->withSum('payments as p_sum', 'amount')->get()
            ->sum(fn ($po) => max(0, (float) $po->r_sum - (float) $po->p_sum));
        $inTransit = (float) PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_ACKNOWLEDGED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
            ->sum('total');

        $stats = [
            'total' => (int) $counts->sum(),
            'awaiting_approval' => (int) ($counts[PurchaseOrder::STATUS_PENDING_APPROVAL] ?? 0),
            'open' => (int) $counts->except([PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_RECEIVED])->sum(),
            'in_transit_value' => round($inTransit, 3),
            'outstanding_ap' => round($openOutstanding, 3),
            'by_status' => $counts->all(),
        ];

        return Inertia::render('Purchases/Index', [
            'filters' => ['status' => $status, 'q' => $search],
            'page' => $page,
            'stats' => $stats,
            'can_create' => $this->canCreate($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeView($request);
        abort_unless($this->canCreate($request), 403, 'Not authorized to create purchase orders.');

        return Inertia::render('Purchases/Form', array_merge($this->pickerData(), [
            'mode' => 'create',
            'order' => null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canCreate($request), 403, 'Not authorized to create purchase orders.');

        $data = $request->validate(array_merge([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'order_date' => ['nullable', 'date'],
        ], $this->headerRules(), $this->lineRules()));

        $this->guardBranch((int) $data['branch_id']);

        try {
            $po = $this->svc->create(
                (int) $data['vendor_id'],
                (int) $data['branch_id'],
                $data['lines'],
                array_merge($this->headerAttrsFrom($data), ['order_date' => $data['order_date'] ?? null]),
                (int) (auth()->id() ?? 0),
            );
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('v2.purchase-orders.show', ['order' => $po->id])
            ->with('flash', ['type' => 'success', 'message' => 'Purchase order created.']);
    }

    public function show(Request $request, PurchaseOrder $order): Response
    {
        $this->authorizeView($request);

        return Inertia::render('Purchases/Show', [
            'order' => $this->poDetail($order),
            'pay_accounts' => $this->payAccounts(),
            'can_create' => $this->canCreate($request),
            'can_manage' => $this->canManage($request),
            'can_approve' => $this->canApprove($request),
            'can_pay' => $this->canPay($request),
        ]);
    }

    public function edit(Request $request, PurchaseOrder $order): Response
    {
        $this->authorizeView($request);
        abort_unless($this->canCreate($request), 403, 'Not authorized to edit purchase orders.');

        if (! $order->isEditable()) {
            return redirect()->route('v2.purchase-orders.show', ['order' => $order->id])
                ->with('flash', ['type' => 'error', 'message' => 'Only a draft purchase order can be edited.']);
        }

        return Inertia::render('Purchases/Form', array_merge($this->pickerData(), [
            'mode' => 'edit',
            'order' => $this->poDetail($order),
        ]));
    }

    public function update(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canCreate($request), 403, 'Not authorized to edit purchase orders.');

        $data = $request->validate(array_merge($this->headerRules(), $this->lineRules()));

        try {
            $this->svc->update($order, $data['lines'], $this->headerAttrsFrom($data));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('v2.purchase-orders.show', ['order' => $order->id])
            ->with('flash', ['type' => 'success', 'message' => 'Purchase order updated.']);
    }

    public function approve(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canApprove($request), 403, 'Not authorized to approve purchase orders.');

        try {
            $this->svc->approve($order, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Purchase order approved.']);
    }

    public function submit(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canCreate($request), 403, 'Not authorized.');

        try {
            $this->svc->submit($order, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Submitted for approval.']);
    }

    public function reject(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canApprove($request), 403, 'Not authorized to reject purchase orders.');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        try {
            $this->svc->reject($order, $data['reason'] ?? null, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Purchase order rejected.']);
    }

    public function send(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canManage($request), 403, 'Not authorized to send purchase orders.');

        try {
            $this->svc->send($order, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Purchase order sent to the vendor.']);
    }

    public function acknowledge(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canManage($request), 403, 'Not authorized.');

        $data = $request->validate(['vendor_reference' => ['nullable', 'string', 'max:191']]);

        try {
            $this->svc->acknowledge($order, $data['vendor_reference'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Vendor acknowledgement recorded.']);
    }

    public function close(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canManage($request), 403, 'Not authorized.');

        try {
            $this->svc->close($order);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Purchase order closed.']);
    }

    /** Branded, print-ready PO document (browser print → PDF). */
    public function print(Request $request, PurchaseOrder $order)
    {
        $this->authorizeView($request);

        return response()->view('pdf.purchase-order', [
            'po' => $this->poDetail($order),
            'clinic' => $this->clinicHeader($order),
            'locale' => app()->getLocale(),
        ]);
    }

    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canManage($request), 403, 'Not authorized to receive goods.');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->svc->receive($order, $data['lines'], (int) (auth()->id() ?? 0), $data['notes'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Goods received — stock updated and posted to Accounts Payable.']);
    }

    public function pay(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canPay($request), 403, 'Not authorized to pay vendors.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.001'],
            'method' => ['required', 'string', 'max:32'],
            'payment_date' => ['nullable', 'date'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'reference_no' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->svc->pay($order, $data, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Vendor payment recorded.']);
    }

    public function cancel(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canManage($request), 403, 'Not authorized to cancel purchase orders.');

        try {
            $this->svc->cancel($order);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Purchase order cancelled.']);
    }

    public function voidPayment(Request $request, PurchasePayment $payment): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canPay($request), 403, 'Not authorized to void payments.');

        try {
            $this->svc->voidPayment($payment);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Payment voided and reversed.']);
    }

    public function reverseReceipt(Request $request, PurchaseReceipt $receipt): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canManage($request), 403, 'Not authorized to reverse receipts.');

        try {
            $this->svc->reverseReceipt($receipt, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Receipt reversed — stock pulled back and the entry reversed.']);
    }

    public function shortClose(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeView($request);
        abort_unless($this->canManage($request), 403, 'Not authorized to close purchase orders.');

        try {
            $this->svc->shortClose($order, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Purchase order short-closed.']);
    }

    /** Vendor / branch / item picker data for the create+edit form. */
    protected function pickerData(): array
    {
        $vendors = Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'code' => $v->code])->all();

        $items = ClinicItem::query()->where('is_active', true)->where('is_stockable', true)
            ->orderBy('name')->get(['id', 'name', 'default_cost', 'partner_id'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->localized_name,
                'default_cost' => (float) ($i->default_cost ?? 0),
                // Scopes the picker to the chosen branch's clinic so an admin
                // (who sees every clinic's catalog) doesn't get duplicates.
                'partner_id' => $i->partner_id,
            ])->all();

        $vendorsFull = Vendor::query()->active()->orderBy('name')->get(['id', 'default_currency', 'country', 'default_payment_terms_days'])
            ->keyBy('id');

        return [
            'vendors' => array_map(fn ($v) => array_merge($v, [
                'default_currency' => $vendorsFull[$v['id']]->default_currency ?? null,
                'country' => $vendorsFull[$v['id']]->country ?? null,
                'default_payment_terms_days' => (int) ($vendorsFull[$v['id']]->default_payment_terms_days ?? 0),
            ]), $vendors),
            'branches' => $this->accessibleBranches()->all(),
            'items' => $items,
            'currencies' => ['KWD', 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'INR', 'CNY', 'JPY', 'EGP', 'TRY'],
            'incoterms' => ['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'],
        ];
    }

    /** Validation rules shared by store + update for the PO header (intl + landed). */
    protected function headerRules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
            'incoterm' => ['nullable', 'string', 'max:16'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'expected_date' => ['nullable', 'date'],
            'ship_date' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'carrier' => ['nullable', 'string', 'max:191'],
            'tracking_no' => ['nullable', 'string', 'max:191'],
            'container_no' => ['nullable', 'string', 'max:191'],
            'vendor_reference' => ['nullable', 'string', 'max:191'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'customs_amount' => ['nullable', 'numeric', 'min:0'],
            'clearance_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charges_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.clinic_item_id' => ['required', 'integer', 'exists:clinic_items,id'],
            'lines.*.qty_ordered' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_type' => ['nullable', 'in:percent,amount'],
            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.country_of_origin' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** Pull the header attrs the service expects out of validated data. */
    protected function headerAttrsFrom(array $d): array
    {
        return collect([
            'currency', 'exchange_rate', 'incoterm', 'payment_terms_days', 'expected_date', 'ship_date', 'eta',
            'carrier', 'tracking_no', 'container_no', 'vendor_reference', 'notes',
            'freight_amount', 'customs_amount', 'clearance_amount', 'insurance_amount', 'other_charges_amount',
        ])->mapWithKeys(fn ($k) => [$k => $d[$k] ?? null])->all();
    }

    /** Letterhead data for the printable PO. */
    protected function clinicHeader(PurchaseOrder $order): array
    {
        $partner = $order->branch?->partner;

        return [
            'name' => $partner?->name ?? config('app.name', 'Clinic'),
            'branch' => $order->branch?->getTranslation('name', app()->getLocale(), true) ?? '',
        ];
    }

    /** Cash/Bank accounts a vendor payment can credit. */
    protected function payAccounts(): array
    {
        return Account::query()
            ->where(fn ($q) => $q->where('code', 'like', '101%')->orWhere('code', 'like', '102%'))
            ->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name])->all();
    }

    /** Full PO detail used by the Show + Edit pages. */
    protected function poDetail(PurchaseOrder $order): array
    {
        $locale = app()->getLocale();
        $order->load(['vendor:id,name,code', 'branch:id,name,partner_id', 'lines.clinicItem', 'receipts', 'payments']);
        $received = $order->amountReceived();
        $paid = $order->amountPaid();

        return [
            'id' => $order->id,
            'code' => $order->code,
            'status' => $order->status,
            'vendor' => $order->vendor?->name,
            'vendor_id' => $order->vendor_id,
            'vendor_code' => $order->vendor?->code,
            'branch' => $order->branch?->getTranslation('name', $locale, true) ?? ('#'.$order->branch_id),
            'branch_id' => $order->branch_id,
            'partner_id' => $order->branch?->partner_id,
            'order_date' => optional($order->order_date)->toDateString(),
            'expected_date' => optional($order->expected_date)->toDateString(),
            'notes' => $order->notes,
            // Currency / FX
            'currency' => $order->currency,
            'exchange_rate' => (float) $order->exchange_rate,
            'is_foreign' => $order->isForeign(),
            'incoterm' => $order->incoterm,
            'payment_terms_days' => (int) $order->payment_terms_days,
            'payment_due_date' => optional($order->payment_due_date)->toDateString(),
            'days_until_due' => $order->daysUntilDue(),
            'is_overdue' => $order->isOverdue(),
            // Money
            'goods_total' => (float) $order->goods_total,
            'goods_total_kwd' => (float) $order->goods_total_kwd,
            'freight_amount' => (float) $order->freight_amount,
            'customs_amount' => (float) $order->customs_amount,
            'clearance_amount' => (float) $order->clearance_amount,
            'insurance_amount' => (float) $order->insurance_amount,
            'other_charges_amount' => (float) $order->other_charges_amount,
            'landed_total' => (float) $order->landed_total,
            'total' => (float) $order->total,
            'received' => round($received, 3),
            'paid' => round($paid, 3),
            'outstanding' => round($received - $paid, 3),
            // Shipment / logistics
            'carrier' => $order->carrier,
            'tracking_no' => $order->tracking_no,
            'container_no' => $order->container_no,
            'ship_date' => optional($order->ship_date)->toDateString(),
            'eta' => optional($order->eta)->toDateString(),
            'vendor_reference' => $order->vendor_reference,
            // Lifecycle
            'is_editable' => $order->isEditable(),
            'is_receivable' => $order->isReceivable(),
            'is_cancellable' => $order->isCancellable(),
            'rejection_reason' => $order->rejection_reason,
            'submitted_at' => optional($order->submitted_at)->toIso8601String(),
            'approved_at' => optional($order->approved_at)->toIso8601String(),
            'rejected_at' => optional($order->rejected_at)->toIso8601String(),
            'sent_at' => optional($order->sent_at)->toIso8601String(),
            'acknowledged_at' => optional($order->acknowledged_at)->toIso8601String(),
            'closed_at' => optional($order->closed_at)->toIso8601String(),
            'lines' => $order->lines->map(fn ($l) => [
                'id' => $l->id,
                'clinic_item_id' => $l->clinic_item_id,
                'name' => $l->clinicItem?->localized_name,
                'country_of_origin' => $l->country_of_origin,
                'qty_ordered' => (float) $l->qty_ordered,
                'qty_received' => (float) $l->qty_received,
                'qty_remaining' => $l->qtyRemaining(),
                'unit_cost' => (float) $l->unit_cost,
                'discount_type' => $l->discount_type,
                'discount_value' => (float) $l->discount_value,
                'discount_amount' => (float) $l->discount_amount,
                'line_total' => (float) $l->line_total,
            ])->all(),
            'receipts' => $order->receipts->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'total' => (float) $r->total_amount,
                'landed' => (float) $r->landed_amount,
                'received_at' => optional($r->received_at)->toIso8601String(),
                'reversed' => (bool) $r->reversed_at,
            ])->all(),
            'po_payments' => $order->payments->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'reference_no' => $p->reference_no,
                'payment_date' => optional($p->payment_date)->toDateString(),
            ])->all(),
        ];
    }

    /** Reject acting on a branch outside the user's clinic. */
    protected function guardBranch(int $branchId): void
    {
        $accessible = $this->accessibleBranchIds(); // null = global admin
        if ($accessible !== null && ! in_array($branchId, $accessible, true)) {
            abort(403, 'That branch is not in your clinic.');
        }
    }
}
