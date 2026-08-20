<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared bilingual clinic Chart of Accounts.
 *
 * Shipped with the product and identical on every install — it is the shared
 * layer, not tenant data. Clinics rename accounts in v2 > Chart of Accounts;
 * those edits survive re-seeding (see Pass 1).
 *
 * Numbering:
 *   1xxx Assets        1100 current · 1200 fixed · 1300 intangible
 *   2xxx Liabilities   2100 current · 2200 non-current
 *   3xxx Equity        partner capital / current accounts / drawings / retained
 *   4xxx Revenue       4100 clinical · 4200 other · 4300 contra (discounts/refunds)
 *   5xxx COGS          consumables, doctor fees, commissions
 *   6xxx Operating     6100 payroll · 6200 occupancy · 6300 marketing · 6400 admin · 6600 dep/amort
 *   7xxx Other income & expense
 *
 * 'Group' rows are headers/totals — not posted to (is_system = true). Detail
 * 'Account' rows are postable. The posting engine + reports resolve these codes
 * directly (see App\Services\Accounting\ChartOfAccounts), so the codes here are a
 * contract — keep them in sync with that resolver.
 *
 * English name -> `name`, Arabic name -> `description`. Idempotent.
 */
class AccountingChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding the shared Chart of Accounts...');

        $A = Account::TYPE_ASSET;
        $CA = Account::TYPE_CONTRA_ASSET;
        $L = Account::TYPE_LIABILITY;
        $E = Account::TYPE_EQUITY;
        $R = Account::TYPE_REVENUE;
        $CR = Account::TYPE_CONTRA_REVENUE;
        $C = Account::TYPE_COGS;
        $X = Account::TYPE_EXPENSE;

        // [code, en, ar, type, parent_code, is_group_or_system]
        $accounts = [
            // ===================== ASSETS =====================
            ['1000', 'Assets', 'الأصول', $A, null, true],
            ['1100', 'Current Assets', 'الأصول المتداولة', $A, '1000', true],
            ['1110', 'Cash on Hand / Petty Cash', 'النقدية بالصندوق / المصروف النثري', $A, '1100', true],
            ['1120', 'Bank — Current Account', 'البنك — الحساب الجاري', $A, '1100', true],
            ['1130', 'KNET / Card Settlement Clearing', 'تحصيلات كي نت / البطاقات تحت التسوية', $A, '1100', true],
            ['1140', 'Accounts Receivable — Patients / Insurance', 'ذمم مدينة — مرضى / تأمين', $A, '1100', true],
            ['1150', 'Inventory — Injectables & Consumables', 'المخزون — الحقن والمستهلكات', $A, '1100', true],
            ['1160', 'Prepaid Rent', 'إيجار مدفوع مقدماً', $A, '1100', false],
            ['1170', 'Prepaid Expenses & Refundable Deposits', 'مصاريف مدفوعة مقدماً وتأمينات قابلة للاسترداد', $A, '1100', false],
            ['1180', 'Staff Advances', 'سلف الموظفين', $A, '1100', true],
            ['1200', 'Fixed Assets (Non-Current)', 'الأصول الثابتة (غير المتداولة)', $A, '1000', true],
            ['1210', 'Medical Equipment & Devices', 'المعدات والأجهزة الطبية', $A, '1200', true],
            ['1215', 'Accum. Depreciation — Medical Equipment', 'مجمع إهلاك المعدات الطبية', $CA, '1200', false],
            ['1220', 'Furniture, Fixtures & Decoration', 'الأثاث والتجهيزات والديكور', $A, '1200', false],
            ['1225', 'Accum. Depreciation — Furniture & Fixtures', 'مجمع إهلاك الأثاث والتجهيزات', $CA, '1200', false],
            ['1230', 'Computers & IT Hardware', 'أجهزة الحاسب وتقنية المعلومات', $A, '1200', false],
            ['1235', 'Accum. Depreciation — Computers & IT', 'مجمع إهلاك أجهزة الحاسب', $CA, '1200', false],
            ['1240', 'Leasehold Improvements / Fit-Out', 'تحسينات على المأجور / التجهيز الداخلي', $A, '1200', false],
            ['1245', 'Accum. Depreciation — Leasehold Improvements', 'مجمع إهلاك تحسينات المأجور', $CA, '1200', false],
            ['1300', 'Intangible Assets', 'الأصول غير الملموسة', $A, '1000', true],
            ['1310', 'Clinic App & ERP System (Software)', 'تطبيق العيادة ونظام ERP (برمجيات)', $A, '1300', false],
            ['1315', 'Accum. Amortization — Software', 'مجمع إطفاء البرمجيات', $CA, '1300', false],
            ['1320', 'Branding & Website', 'العلامة التجارية والموقع الإلكتروني', $A, '1300', false],
            ['1325', 'Accum. Amortization — Branding & Website', 'مجمع إطفاء العلامة التجارية والموقع', $CA, '1300', false],
            ['1330', 'Licenses & Registration (Capitalized)', 'التراخيص والتسجيل (مرسملة)', $A, '1300', false],

            // ===================== LIABILITIES =====================
            ['2000', 'Liabilities', 'الالتزامات', $L, null, true],
            ['2100', 'Current Liabilities', 'الالتزامات المتداولة', $L, '2000', true],
            ['2110', 'Accounts Payable — Suppliers', 'ذمم دائنة — موردون', $L, '2100', true],
            ['2120', 'Equipment Installments Payable — Current', 'أقساط معدات مستحقة — جزء متداول', $L, '2100', false],
            ['2130', 'Accrued Salaries & Wages', 'رواتب وأجور مستحقة', $L, '2100', true],
            ['2140', 'Accrued Expenses', 'مصاريف مستحقة', $L, '2100', false],
            ['2150', 'Rent Payable', 'إيجار مستحق', $L, '2100', false],
            ['2160', 'Staff Leave Provision', 'مخصص إجازات الموظفين', $L, '2100', false],
            ['2170', 'Patient Deposits & Package Liability (Unearned)', 'دفعات المرضى المقدمة / التزام الباقات (غير مكتسبة)', $L, '2100', true],
            ['2180', 'Loyalty Points Liability', 'التزام نقاط الولاء', $L, '2100', false],
            ['2190', 'Other Payables', 'ذمم دائنة أخرى', $L, '2100', true],
            ['2200', 'Non-Current Liabilities', 'الالتزامات غير المتداولة', $L, '2000', true],
            ['2210', 'Equipment Installments Payable — Long Term', 'أقساط معدات مستحقة — طويلة الأجل', $L, '2200', false],
            ['2220', 'End-of-Service Indemnity Provision', 'مخصص مكافأة نهاية الخدمة', $L, '2200', true],

            // ===================== EQUITY =====================
            ['3000', 'Equity', 'حقوق الملكية', $E, null, true],
            ['3100', 'Partner Capital — Eng. Ali Mubarak', 'رأس مال الشريك — م. علي مبارك', $E, '3000', true],
            ['3110', 'Partner Capital — Ahmad Al-Qenaei', 'رأس مال الشريك — أحمد القناعي', $E, '3000', true],
            ['3200', 'Partner Current Account — Ali', 'الحساب الجاري للشريك — علي', $E, '3000', false],
            ['3210', 'Partner Current Account — Ahmad', 'الحساب الجاري للشريك — أحمد', $E, '3000', false],
            ['3300', 'Partner Drawings', 'مسحوبات الشركاء', $E, '3000', false],
            ['3400', 'Retained Earnings', 'الأرباح المُحتجزة', $E, '3000', true],
            ['3500', 'Current Year Profit / (Loss)', 'أرباح / (خسائر) السنة الحالية', $E, '3000', true],

            // ===================== REVENUE =====================
            ['4000', 'Revenue', 'الإيرادات', $R, null, true],
            ['4100', 'Clinical Services Revenue', 'إيرادات الخدمات الإكلينيكية', $R, '4000', true],
            ['4110', 'Clinical Services — General', 'إيرادات الخدمات الإكلينيكية — عام', $R, '4100', true],
            ['4120', 'Injectables (Botox & Fillers)', 'إيرادات الحقن (بوتوكس وفيلر)', $R, '4100', false],
            ['4130', 'Laser & Device Treatments', 'إيرادات الليزر والأجهزة', $R, '4100', false],
            ['4140', 'Skincare / Facials (Aesthetician)', 'إيرادات العناية بالبشرة / الفيشل', $R, '4100', false],
            ['4150', 'Plastic Surgery — Visiting Doctors', 'إيرادات جراحة التجميل — الأطباء الزائرون', $R, '4100', false],
            ['4160', 'Laser Hair Removal', 'إيرادات إزالة الشعر بالليزر', $R, '4100', false],
            ['4200', 'Other Operating Revenue', 'إيرادات تشغيلية أخرى', $R, '4000', true],
            ['4210', 'Product / Retail Sales', 'مبيعات المنتجات / التجزئة', $R, '4200', true],
            ['4290', 'Other Income', 'إيرادات أخرى', $R, '4200', true],
            ['4300', 'Contra-Revenue (Discounts & Refunds)', 'إيرادات مقابلة (خصومات ومردودات)', $CR, '4000', true],
            ['4310', 'Discounts & Promotions', 'الخصومات والعروض', $CR, '4300', true],
            ['4320', 'Refunds to Patients', 'مبالغ مردودة للمرضى', $CR, '4300', true],

            // ===================== COST OF SERVICES (COGS) =====================
            ['5000', 'Cost of Services (COGS)', 'تكلفة الخدمات (المبيعات)', $C, null, true],
            ['5110', 'Injectables & Fillers Consumed', 'تكلفة الحقن والفيلر المستهلكة', $C, '5000', true],
            ['5120', 'Medical Consumables & Disposables', 'تكلفة المستهلكات والمواد الطبية', $C, '5000', true],
            ['5130', 'Doctor Fees & Commissions — Visiting', 'أتعاب وعمولات الأطباء الزائرين', $C, '5000', true],
            ['5140', 'Lead Doctor Cost (Direct)', 'تكلفة الطبيب الرئيسي (مباشرة)', $C, '5000', false],
            ['5150', 'Skincare Products Consumed', 'تكلفة منتجات العناية المستهلكة', $C, '5000', false],
            ['5160', 'Sales Commission — Doctors', 'عمولة مبيعات — الأطباء', $C, '5000', false],
            ['5170', 'Lab / External Clinical Services', 'تكلفة المختبر / خدمات إكلينيكية خارجية', $C, '5000', true],

            // ===================== OPERATING EXPENSES =====================
            ['6000', 'Operating Expenses', 'المصاريف التشغيلية', $X, null, true],
            ['6100', 'Payroll & Staff', 'الرواتب والموظفون', $X, '6000', true],
            ['6110', 'Salaries & Wages — Administrative', 'الرواتب والأجور — الإدارية', $X, '6100', true],
            ['6115', 'Nursing & Clinical Staff Salaries', 'رواتب التمريض والطاقم الإكلينيكي', $X, '6100', false],
            ['6120', 'End-of-Service Indemnity Expense', 'مصروف مكافأة نهاية الخدمة', $X, '6100', true],
            ['6130', 'Leave Pay Expense', 'مصروف بدل الإجازات', $X, '6100', false],
            ['6140', 'Staff Visa & Residency', 'مصاريف الإقامات والتأشيرات', $X, '6100', false],
            ['6150', 'Staff Accommodation', 'سكن الموظفين', $X, '6100', false],
            ['6160', 'Staff Hospitality / Kitchen', 'مطبخ وضيافة الطاقم', $X, '6100', false],
            ['6200', 'Occupancy', 'مصاريف الإشغال (العقارية)', $X, '6000', true],
            ['6210', 'Rent — Clinic', 'إيجار العيادة', $X, '6200', true],
            ['6220', 'Electricity & Water', 'كهرباء ومياه', $X, '6200', true],
            ['6230', 'Building Maintenance', 'صيانة المبنى', $X, '6200', false],
            ['6300', 'Marketing', 'التسويق', $X, '6000', true],
            ['6310', 'Advertising & Social Media', 'الإعلان ووسائل التواصل', $X, '6300', true],
            ['6320', 'Sponsored Ads & Google', 'حملات ممولة وجوجل', $X, '6300', false],
            ['6330', 'Influencers', 'المؤثرون', $X, '6300', false],
            ['6340', 'Marketing — Doctor Personal Brand', 'تسويق — العلامة الشخصية للطبيب', $X, '6300', false],
            ['6400', 'Administrative & General', 'عمومية وإدارية', $X, '6000', true],
            ['6410', 'Telephone & Internet', 'هاتف وانترنت', $X, '6400', false],
            ['6420', 'IT & Software Subscriptions (ERP)', 'اشتراكات تقنية وبرمجيات (ERP)', $X, '6400', false],
            ['6430', 'Printing & Stationery', 'طباعة وقرطاسية', $X, '6400', true],
            ['6440', 'Cleaning', 'النظافة', $X, '6400', false],
            ['6450', 'Transportation — Visiting Medical Staff', 'نقل الطاقم الطبي الزائر', $X, '6400', false],
            ['6460', 'Travel — Air Tickets (Visiting Doctors)', 'سفر — تذاكر طيران (الأطباء الزائرون)', $X, '6400', false],
            ['6470', 'Hotel Accommodation — Visiting Staff', 'فنادق الطاقم الزائر', $X, '6400', false],
            ['6480', 'Legal & Contract Consultation', 'استشارات قانونية وعقود', $X, '6400', false],
            ['6490', 'Auditing & Accounting', 'تدقيق ومحاسبة', $X, '6400', false],
            ['6500', 'Government Fees & Licenses', 'رسوم حكومية وتراخيص', $X, '6400', false],
            ['6510', 'Clinic & Medical Malpractice Insurance', 'تأمين العيادة والمسؤولية الطبية', $X, '6400', true],
            ['6520', 'Bank Charges & KNET Fees', 'رسوم بنكية ورسوم كي نت', $X, '6400', true],
            ['6530', 'Miscellaneous Expenses', 'مصاريف متنوعة', $X, '6400', true],
            ['6600', 'Depreciation & Amortization', 'الإهلاك والإطفاء', $X, '6000', true],
            ['6610', 'Depreciation Expense', 'مصروف الإهلاك', $X, '6600', false],
            ['6620', 'Amortization Expense', 'مصروف الإطفاء', $X, '6600', false],

            // ===================== OTHER INCOME & EXPENSE =====================
            ['7000', 'Other Income & Expense', 'إيرادات ومصاريف أخرى', $X, null, true],
            ['7110', 'Interest / Finance Charges', 'أعباء تمويلية / فوائد', $X, '7000', false],
            ['7190', 'Other Non-Operating Income / Expense', 'إيرادات / مصاريف أخرى غير تشغيلية', $X, '7000', false],
        ];

        // Pass 1: upsert by code (no parent yet).
        //
        // The CODE is the contract — the posting engine resolves accounts by it
        // (see ChartOfAccounts + PostingAccountMap::DEFAULTS). The NAME is just
        // a label, and every clinic renames some of these to match how it
        // actually reports. So names are seeded on CREATE only; on re-run we
        // sync the structural columns and leave the clinic's wording alone.
        foreach ($accounts as [$code, $en, $ar, $type, $_parent, $isSystem]) {
            $structure = [
                'type' => $type,
                'currency' => 'KWD',
                'is_active' => true,
                'is_system' => $isSystem,
            ];

            $account = Account::firstOrNew(['code' => $code]);

            if (! $account->exists) {
                $account->name = $en;
                $account->description = $ar;
            }

            $account->fill($structure)->save();
        }

        // Pass 2: wire parents
        $byCode = Account::query()->get()->keyBy('code');
        foreach ($accounts as [$code, $_e, $_a, $_t, $parentCode, $_s]) {
            if ($parentCode === null) {
                continue;
            }
            $parent = $byCode->get($parentCode);
            $self = $byCode->get($code);
            if ($parent && $self && $self->parent_id !== $parent->id) {
                $self->update(['parent_id' => $parent->id]);
            }
        }

        // Pass 3: per-branch petty-cash sub-accounts ("1110-<branchId>") so
        // branch-scoped cash postings resolve to their own account.
        $cashParent = Account::where('code', '1110')->first();
        if ($cashParent) {
            foreach (Branch::query()->get() as $branch) {
                Account::updateOrCreate(
                    ['code' => '1110-'.$branch->id],
                    [
                        'name' => 'Cash on Hand — '.($branch->localized_name ?? ('Branch '.$branch->id)),
                        'type' => Account::TYPE_ASSET,
                        'parent_id' => $cashParent->id,
                        'branch_id' => $branch->id,
                        'currency' => 'KWD',
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }
        }

        $this->command?->info('Seeded '.Account::count().' accounts.');
    }
}
