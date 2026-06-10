<?php

namespace App\Providers;

use App\Models\Accounting\Expense;
use App\Models\Booking;
use App\Models\ClinicStockMovement;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\Insurance\InsuranceClaimPayment;
use App\Models\PatientFile;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Observers\Accounting\ClinicStockMovementAccountingObserver;
use App\Observers\Accounting\DoctorCompensationLedgerAccountingObserver;
use App\Observers\Accounting\ExpenseAccountingObserver;
use App\Observers\Accounting\InsuranceClaimPaymentAccountingObserver;
use App\Observers\Accounting\VisitPaymentAccountingObserver;
use App\Observers\Clinic\ClinicStockMovementObserver;
use App\Observers\Clinic\InsurancePreauthorizationObserver;
use App\Observers\PatientFileObserver;
use App\Observers\BookingObserver;
use App\Observers\DoctorObserver;
use App\Observers\VisitObserver;
use App\Observers\VisitPaymentObserver;
use App\Services\Accounting\ChartOfAccounts;
use App\Services\WhatsAppApiServiceFactory;
use App\Filament\Exports\ExcelExportActions;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Land every successful login on the v2 admin instead of the legacy
        // Filament dashboard.
        $this->app->bind(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class,
        );

        $this->app->singleton(WhatsAppApiServiceFactory::class, function ($app) {
            return new WhatsAppApiServiceFactory;
        });

        // Accounting: single ChartOfAccounts lookup table per request,
        // so dozens of auto-postings don't each SELECT chart_of_accounts.
        $this->app->singleton(ChartOfAccounts::class);

        // 2. Add this new binding
        $this->app->singleton(WhatsAppCloudApi::class, function ($app) {
            $config = $app['config'];

            return new WhatsAppCloudApi([
                // Map your config key to the library's expected key
                'from_phone_number_id' => $config->get('services.whatsapp.phone_id'),

                // Your config key matches the library's expected key
                'access_token' => $config->get('services.whatsapp.access_token'),

                // Pass the graph version from your config
                'graph_version' => $config->get('services.whatsapp.graph_version', 'v20.0'),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerExcelExports();

        // super_admin is the all-powerful role: grant every ability before any
        // policy/permission check. (The `admin` role is granted all permissions
        // explicitly by the permission seeder.) Without this a pure-super_admin
        // user — holding no individual permissions — would 403 on every gated page.
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) ? true : null;
        });

        Visit::observe(VisitObserver::class);
        Booking::observe(BookingObserver::class);
        Doctor::observe(DoctorObserver::class);
        VisitPayment::observe(VisitPaymentObserver::class);

        // Accounting auto-posting: every clinic event that affects money
        // produces a balanced journal entry in the General Ledger.
        VisitPayment::observe(VisitPaymentAccountingObserver::class);
        ClinicStockMovement::observe(ClinicStockMovementAccountingObserver::class);
        DoctorCompensationLedger::observe(DoctorCompensationLedgerAccountingObserver::class);
        Expense::observe(ExpenseAccountingObserver::class);
        InsuranceClaimPayment::observe(InsuranceClaimPaymentAccountingObserver::class);

        // Patient files: log every upload/delete to the access audit table.
        PatientFile::observe(PatientFileObserver::class);

        // In-app notification triggers (Filament database notifications bell).
        ClinicStockMovement::observe(ClinicStockMovementObserver::class);
        \App\Models\Insurance\InsurancePreauthorization::observe(InsurancePreauthorizationObserver::class);

        Filament::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => <<<'HTML'
<script>
window.addEventListener('filament-scroll-to-relations', () => {
  const el =
    document.querySelector('.fi-resource-relation-managers') ||
    document.querySelector('[data-filament-relation-managers]') ||
    document.querySelector('#relation-managers');

  if (!el) return;

  setTimeout(() => {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 50);
});
</script>
HTML
        );

    }

    /**
     * Give every admin table a styled "Export to Excel" header action in one
     * place instead of wiring each resource by hand. Columns, labels and
     * formatting come from each table itself (StyledExcelExport::fromTable),
     * so the .xlsx always mirrors what is on screen (current filters included).
     *
     * Note: this push runs inside Table::make(), before a resource's own
     * table() method. The ~5 resources that define their own ->headerActions()
     * therefore replace it and re-add the action explicitly via
     * ExcelExportActions::header(). Bulk "Export selected" is added per-table
     * on the core clinical resources (it can't be pushed globally because most
     * resources define their own ->bulkActions()).
     *
     * Scoped to the admin panel; the partner panel keeps its own exports.
     */
    protected function registerExcelExports(): void
    {
        Table::configureUsing(function (Table $table): void {
            if (Filament::getCurrentPanel()?->getId() !== 'admin') {
                return;
            }

            $table->pushHeaderActions([
                ExcelExportActions::header(),
            ]);
        });
    }
}
