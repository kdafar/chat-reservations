<?php

namespace App\Providers;

use App\Models\Accounting\Expense;
use App\Models\Booking;
use App\Models\ClinicStockMovement;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Observers\Accounting\ClinicStockMovementAccountingObserver;
use App\Observers\Accounting\DoctorCompensationLedgerAccountingObserver;
use App\Observers\Accounting\ExpenseAccountingObserver;
use App\Observers\Accounting\VisitPaymentAccountingObserver;
use App\Observers\BookingObserver;
use App\Observers\DoctorObserver;
use App\Observers\VisitObserver;
use App\Observers\VisitPaymentObserver;
use App\Services\Accounting\ChartOfAccounts;
use App\Services\WhatsAppApiServiceFactory;
use Filament\Facades\Filament;
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
}
