<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\PostingAccountMap;
use App\Services\Accounting\ChartOfAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Auto-Posting Accounts — lets the accountant see and control which chart
 * account every automated posting role uses (cash, bank, AR, inventory, COGS,
 * AP, revenue, payroll…). account_id NULL on a row = use the EVA default code.
 *
 * The posting engine (App\Services\Accounting\ChartOfAccounts) reads these
 * overrides, falling back to the built-in defaults.
 */
class PostingAccountsController extends Controller
{
    /**
     * UI catalogue: role => [group, label{en,ar}, help{en,ar}].
     * The default code lives on the PostingAccountMap row (PostingAccountMap::DEFAULTS).
     */
    private const CATALOG = [
        // Money in
        'cash' => ['receipts', ['en' => 'Cash received', 'ar' => 'النقد المستلم'], ['en' => 'Cash/petty-cash payments taken from patients.', 'ar' => 'المدفوعات النقدية المستلمة من المرضى.']],
        'card_clearing' => ['receipts', ['en' => 'KNET / card receipts', 'ar' => 'مدفوعات كي نت / البطاقات'], ['en' => 'Card & KNET payments, held until the bank settles them.', 'ar' => 'مدفوعات البطاقات وكي نت، تُحتجز حتى تسوية البنك.']],
        'bank' => ['receipts', ['en' => 'Bank transfers', 'ar' => 'التحويلات البنكية'], ['en' => 'Payments received or paid by bank transfer.', 'ar' => 'المدفوعات المستلمة أو المدفوعة عبر التحويل البنكي.']],
        'ar' => ['receipts', ['en' => 'Patient / insurance receivable', 'ar' => 'ذمم المرضى / التأمين'], ['en' => 'Amounts owed by patients and insurers (AR).', 'ar' => 'المبالغ المستحقة على المرضى وشركات التأمين.']],
        // Revenue
        'revenue_services' => ['revenue', ['en' => 'Services revenue', 'ar' => 'إيراد الخدمات'], ['en' => 'Income from treatments & consultations.', 'ar' => 'الإيراد من العلاجات والاستشارات.']],
        'revenue_products' => ['revenue', ['en' => 'Product / retail revenue', 'ar' => 'إيراد المنتجات'], ['en' => 'Income from products sold to patients.', 'ar' => 'الإيراد من بيع المنتجات للمرضى.']],
        'revenue_other' => ['revenue', ['en' => 'Other income', 'ar' => 'إيرادات أخرى'], ['en' => 'Miscellaneous / non-operating income.', 'ar' => 'إيرادات متنوعة / غير تشغيلية.']],
        // Inventory & cost
        'inventory' => ['inventory', ['en' => 'Inventory (stock value)', 'ar' => 'المخزون'], ['en' => 'Value of consumables/products in stock.', 'ar' => 'قيمة المستهلكات/المنتجات في المخزون.']],
        'cogs' => ['inventory', ['en' => 'Cost of goods used', 'ar' => 'تكلفة المستهلكات'], ['en' => 'Cost of consumables used during visits.', 'ar' => 'تكلفة المستهلكات المستخدمة في الزيارات.']],
        // Payables
        'ap' => ['payables', ['en' => 'Accounts payable', 'ar' => 'الذمم الدائنة'], ['en' => 'Money owed to suppliers/vendors.', 'ar' => 'المبالغ المستحقة للموردين.']],
        'import_payable' => ['payables', ['en' => 'Import / landed costs payable', 'ar' => 'مستحقات الاستيراد'], ['en' => 'Shipping/customs costs owed on purchases.', 'ar' => 'تكاليف الشحن/الجمارك المستحقة على المشتريات.']],
        'accrued_salaries' => ['payables', ['en' => 'Salaries & commission payable', 'ar' => 'الرواتب والعمولات المستحقة'], ['en' => 'Staff salaries and doctor commission owed.', 'ar' => 'رواتب الموظفين وعمولات الأطباء المستحقة.']],
        // Payroll & staff
        'salaries_expense' => ['payroll', ['en' => 'Salaries expense', 'ar' => 'مصروف الرواتب'], ['en' => 'Staff salary cost.', 'ar' => 'تكلفة رواتب الموظفين.']],
        'doctor_fees' => ['payroll', ['en' => 'Doctor fees & commission', 'ar' => 'أتعاب وعمولات الأطباء'], ['en' => 'Doctor commission expense.', 'ar' => 'مصروف عمولات الأطباء.']],
        'eos_expense' => ['payroll', ['en' => 'End-of-service expense', 'ar' => 'مصروف نهاية الخدمة'], ['en' => 'Kuwait end-of-service indemnity cost.', 'ar' => 'تكلفة مكافأة نهاية الخدمة.']],
        'staff_advances' => ['payroll', ['en' => 'Staff loans & advances', 'ar' => 'سلف الموظفين'], ['en' => 'Loans/advances given to staff (receivable).', 'ar' => 'القروض/السلف الممنوحة للموظفين.']],
        // Other
        'bad_debt' => ['other', ['en' => 'Bad debt / write-offs', 'ar' => 'الديون المعدومة'], ['en' => 'Uncollectible balances written off.', 'ar' => 'الأرصدة غير القابلة للتحصيل المشطوبة.']],
        'retained_earnings' => ['other', ['en' => 'Retained earnings', 'ar' => 'الأرباح المحتجزة'], ['en' => 'Year-end profit/loss moved to equity on period close.', 'ar' => 'الأرباح/الخسائر المرحّلة لحقوق الملكية عند إغلاق الفترة.']],
    ];

    private const GROUPS = [
        'receipts' => ['en' => 'Money received', 'ar' => 'المقبوضات'],
        'revenue' => ['en' => 'Revenue', 'ar' => 'الإيرادات'],
        'inventory' => ['en' => 'Inventory & cost', 'ar' => 'المخزون والتكلفة'],
        'payables' => ['en' => 'Payables', 'ar' => 'الذمم الدائنة'],
        'payroll' => ['en' => 'Payroll & staff', 'ar' => 'الرواتب والموظفون'],
        'other' => ['en' => 'Other', 'ar' => 'أخرى'],
    ];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_accounting_accounts')) {
            abort(403, 'Not authorized to view posting accounts.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_accounting_accounts');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $accounts = Account::query()->get(['id', 'code', 'name', 'type', 'parent_id', 'is_active']);
        $byCode = $accounts->keyBy('code');
        $parentIds = $accounts->pluck('parent_id')->filter()->unique()->flip(); // accounts that are headers

        // Postable accounts for the picker: active, and not a parent/header row.
        $picker = $accounts
            ->filter(fn ($a) => $a->is_active && ! $parentIds->has($a->id))
            ->sortBy('code')
            ->map(fn ($a) => ['value' => $a->id, 'label' => $a->code.' — '.$a->name])
            ->values()->all();

        $maps = PostingAccountMap::query()->get()->keyBy('role');

        $rows = [];
        foreach (self::CATALOG as $role => [$group, $label, $help]) {
            $map = $maps->get($role);
            $defaultCode = $map->default_code ?? (PostingAccountMap::DEFAULTS[$role] ?? null);
            $defaultAccount = $defaultCode ? $byCode->get($defaultCode) : null;
            $overrideId = $map?->account_id;
            $overrideAccount = $overrideId ? $accounts->firstWhere('id', $overrideId) : null;

            $rows[] = [
                'role' => $role,
                'group' => $group,
                'label' => $label,
                'help' => $help,
                'default_code' => $defaultCode,
                'default_label' => $defaultAccount ? $defaultAccount->code.' — '.$defaultAccount->name : $defaultCode,
                'account_id' => $overrideId,                 // null = using default
                'is_overridden' => (bool) $overrideId,
                'effective_label' => $overrideAccount
                    ? $overrideAccount->code.' — '.$overrideAccount->name
                    : ($defaultAccount ? $defaultAccount->code.' — '.$defaultAccount->name : '—'),
            ];
        }

        return Inertia::render('PostingAccounts/Index', [
            'rows' => $rows,
            'groups' => self::GROUPS,
            'accounts' => $picker,
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->canEdit($request), 403);

        $validated = $request->validate([
            'map' => ['required', 'array'],
            'map.*.role' => ['required', 'string'],
            'map.*.account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        foreach ($validated['map'] as $entry) {
            $role = $entry['role'];
            if (! array_key_exists($role, PostingAccountMap::DEFAULTS)) {
                continue; // ignore unknown roles
            }
            PostingAccountMap::where('role', $role)->update([
                'account_id' => $entry['account_id'] ?: null,
            ]);
        }

        // Drop the in-memory account cache so the new map applies immediately.
        app(ChartOfAccounts::class)->refresh();

        return redirect()->route('v2.accounting.posting.index')
            ->with('flash', ['type' => 'success', 'message' => 'Posting accounts updated.']);
    }
}
