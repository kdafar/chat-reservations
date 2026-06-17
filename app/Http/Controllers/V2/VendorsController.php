<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendors — v2 replacement for the Filament Accounting\VendorResource.
 *
 * Payees/suppliers the clinic incurs expenses with. Each vendor can pin a
 * default expense + payable account so creating an expense is one click.
 */
class VendorsController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_accounting_vendors')) {
            abort(403, 'Not authorized to view vendors.');
        }
    }

    protected function authorizeWrite(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('create_accounting_vendors')) {
            abort(403, 'Not authorized to manage vendors.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeView($request);
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');
        $query = Vendor::query();
        if ($q !== '') { $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhere('contact_name', 'like', "%{$q}%")); }
        if ($status === 'active') { $query->where('is_active', true); } elseif ($status === 'inactive') { $query->where('is_active', false); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('name'),
                ['ID', 'Name', 'Code', 'Contact', 'Phone', 'Email', 'Tax number', 'Active'],
                fn ($v) => [$v->id, $v->name, $v->code, $v->contact_name, $v->phone, $v->email, $v->tax_number, $v->is_active ? 'Yes' : 'No'],
                'Vendors',
                app()->getLocale() === 'ar',
            ),
            'vendors-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
        ];

        $query = Vendor::query()->with(['defaultAccount:id,code,name', 'defaultPayableAccount:id,code,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }
        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderBy('name')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (Vendor $v) => $this->present($v));

        return Inertia::render('Vendors/Index', [
            'filters' => $filters,
            'page' => $page,
            'counts' => [
                'total' => Vendor::query()->count(),
                'active' => Vendor::query()->where('is_active', true)->count(),
                'inactive' => Vendor::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    /** Expense + payable account pickers shared by the create/edit page. */
    protected function pickerData(): array
    {
        return [
            'expenseAccounts' => $this->accountOptions([Account::TYPE_EXPENSE, Account::TYPE_COGS]),
            'payableAccounts' => $this->accountOptions([Account::TYPE_LIABILITY]),
        ];
    }

    /** Dedicated create page (replaces the old modal). */
    public function create(Request $request): Response
    {
        $this->authorizeWrite($request);

        return Inertia::render('Vendors/Form', array_merge($this->pickerData(), [
            'mode' => 'create',
            'vendor' => null,
        ]));
    }

    /** Dedicated edit page (replaces the old modal). */
    public function edit(Request $request, Vendor $vendor): Response
    {
        $this->authorizeWrite($request);

        return Inertia::render('Vendors/Form', array_merge($this->pickerData(), [
            'mode' => 'edit',
            'vendor' => $this->present($vendor),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validateData($request, null);

        Vendor::create($data);

        return redirect()->route('v2.accounting.vendors.index')
            ->with('flash', ['type' => 'success', 'message' => 'Vendor created.']);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validateData($request, $vendor);

        $vendor->update($data);

        return redirect()->route('v2.accounting.vendors.index')
            ->with('flash', ['type' => 'success', 'message' => 'Vendor updated.']);
    }

    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeWrite($request);

        // Soft-delete keeps the FK on historical expenses intact.
        $vendor->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Vendor archived.']);
    }

    protected function validateData(Request $request, ?Vendor $vendor): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('vendors', 'code')->ignore($vendor?->id)->whereNull('deleted_at')],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:191'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:1000'],
            'default_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_payable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
    }

    /** @return array<int, array{id:int, label:string}> */
    protected function accountOptions(array $types): array
    {
        return Account::query()
            ->whereIn('type', $types)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $a) => ['id' => $a->id, 'label' => "{$a->code} — {$a->name}"])
            ->all();
    }

    protected function present(Vendor $v): array
    {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'code' => $v->code,
            'contact_name' => $v->contact_name,
            'phone' => $v->phone,
            'email' => $v->email,
            'tax_number' => $v->tax_number,
            'address' => $v->address,
            'default_account_id' => $v->default_account_id,
            'default_account_label' => $v->defaultAccount ? "{$v->defaultAccount->code} — {$v->defaultAccount->name}" : null,
            'default_payable_account_id' => $v->default_payable_account_id,
            'notes' => $v->notes,
            'is_active' => (bool) $v->is_active,
        ];
    }
}
