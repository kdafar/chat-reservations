<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAsset;
use App\Models\Branch;
use App\Services\Accounting\DepreciationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fixed-asset register (accountant-managed). Capitalisation is assumed already
 * booked (via a purchase/expense entry); this register drives straight-line
 * depreciation — the monthly run posts Dr depreciation expense / Cr accumulated
 * depreciation. See DepreciationService + the accounting:run-depreciation cron.
 */
class FixedAssetsController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('view_any_accounting_accounts'), 403, 'Not authorized to view fixed assets.');
    }

    protected function authorizeEdit(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('update_accounting_accounts'), 403, 'Not authorized to manage fixed assets.');
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $status = $request->input('status', 'all');
        $query = FixedAsset::query()->with(['branch:id,name', 'assetAccount:id,code,name'])
            ->when(in_array($status, ['active', 'fully_depreciated', 'disposed'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id');

        $page = $query->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (FixedAsset $a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'category' => $a->category,
            'branch' => $a->branch?->localized_name,
            'cost' => (float) $a->cost,
            'accumulated_depreciation' => (float) $a->accumulated_depreciation,
            'net_book_value' => $a->netBookValue(),
            'monthly_charge' => $a->monthlyCharge(),
            'useful_life_months' => $a->useful_life_months,
            'in_service_date' => optional($a->in_service_date)->toDateString(),
            'last_depreciated_on' => optional($a->last_depreciated_on)->toDateString(),
            'status' => $a->status,
        ]);

        return Inertia::render('FixedAssets/Index', [
            'filters' => ['status' => $status],
            'page' => $page,
            'summary' => [
                'cost' => (float) FixedAsset::query()->sum('cost'),
                'accumulated' => (float) FixedAsset::query()->sum('accumulated_depreciation'),
                'nbv' => round((float) FixedAsset::query()->sum('cost') - (float) FixedAsset::query()->sum('accumulated_depreciation'), 3),
                'active' => FixedAsset::query()->where('status', 'active')->count(),
            ],
            'can_edit' => (bool) $request->user()?->can('update_accounting_accounts'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeEdit($request);

        return Inertia::render('FixedAssets/Form', array_merge(['mode' => 'create', 'asset' => null], $this->formSupport()));
    }

    public function edit(Request $request, FixedAsset $fixedAsset): Response
    {
        $this->authorizeEdit($request);

        return Inertia::render('FixedAssets/Form', array_merge([
            'mode' => 'edit',
            'asset' => [
                'id' => $fixedAsset->id,
                'code' => $fixedAsset->code,
                'name' => $fixedAsset->name,
                'category' => $fixedAsset->category,
                'branch_id' => $fixedAsset->branch_id,
                'asset_account_id' => $fixedAsset->asset_account_id,
                'accumulated_depreciation_account_id' => $fixedAsset->accumulated_depreciation_account_id,
                'depreciation_expense_account_id' => $fixedAsset->depreciation_expense_account_id,
                'cost' => (float) $fixedAsset->cost,
                'salvage_value' => (float) $fixedAsset->salvage_value,
                'useful_life_months' => $fixedAsset->useful_life_months,
                'in_service_date' => optional($fixedAsset->in_service_date)->toDateString(),
                'notes' => $fixedAsset->notes,
                'status' => $fixedAsset->status,
                'accumulated_depreciation' => (float) $fixedAsset->accumulated_depreciation,
            ],
        ], $this->formSupport()));
    }

    protected function formSupport(): array
    {
        return [
            'asset_accounts' => Account::postableOptions([Account::TYPE_ASSET]),
            'accum_accounts' => Account::postableOptions([Account::TYPE_CONTRA_ASSET]),
            'expense_accounts' => Account::postableOptions([Account::TYPE_EXPENSE]),
            'branches' => Branch::query()->orderBy('id')->get(['id', 'name'])->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all(),
            'categories' => ['medical_equipment', 'furniture', 'it', 'leasehold', 'software', 'other'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $this->validateData($request);

        $asset = new FixedAsset($data);
        $asset->code = 'FA-'.str_pad((string) (FixedAsset::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        $asset->created_by_user_id = $request->user()->id;
        $asset->save();

        return redirect()->route('v2.fixed-assets.index')
            ->with('flash', ['type' => 'success', 'message' => 'Fixed asset added.']);
    }

    public function update(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $this->validateData($request);

        // Don't let editing reduce useful life below what's already depreciated
        // into nonsense — but allow correcting cost/life; accumulated is preserved.
        $fixedAsset->fill($data)->save();

        return redirect()->route('v2.fixed-assets.index')
            ->with('flash', ['type' => 'success', 'message' => 'Fixed asset updated.']);
    }

    public function runDepreciation(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $month = $request->input('month') ? Carbon::parse($request->input('month').'-01') : now(config('app.timezone'))->startOfMonth();
        $r = app(DepreciationService::class)->runForMonth($month, $request->user()->id);

        return back()->with('flash', ['type' => 'success', 'message' => "Depreciation {$r['period']}: posted {$r['posted']} of {$r['assets']} assets (".number_format($r['total'], 3).' KWD).']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:64'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            // Enforce the account TYPE server-side (the UI already filters, but a
            // crafted request must not be able to wire a wrong-type account).
            'asset_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('type', Account::TYPE_ASSET)->where('is_active', true)],
            'accumulated_depreciation_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('type', Account::TYPE_CONTRA_ASSET)->where('is_active', true)],
            'depreciation_expense_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('type', Account::TYPE_EXPENSE)->where('is_active', true)],
            'cost' => ['required', 'numeric', 'min:0.001'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'in_service_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
