<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CheckInScanner;
use App\Filament\Pages\Dashboard as AdminDashboard;
use App\Filament\Pages\DoctorSchedule;
use App\Filament\Pages\ReservationsDashboard;
use App\Filament\Pages\WhatsAppCampaignSettings;
use App\Filament\Pages\WhatsAppRateLimitSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->databaseNotifications()
            ->databaseNotificationsPolling('5s')
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => view('filament.hooks.notification-sound')->render(),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.hooks.user-branch-badge')->render(),
            )
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->colors([
                'primary' => Color::hex('#b19860'),
                'gray' => [
                    900 => '#1b2134',
                    950 => '#1b2134',
                ],
            ])

            ->resources([
                \App\Filament\Resources\BookingResource::class,
                \App\Filament\Resources\BranchAvailabilityRuleResource::class,
                \App\Filament\Resources\BranchBlackoutResource::class,
                \App\Filament\Resources\BranchResource::class,
                \App\Filament\Resources\UserResource::class,
                \App\Filament\Resources\RoleResource::class,

                \App\Filament\Resources\WACommandResource::class,
                \App\Filament\Resources\WAMessageResource::class,
                \App\Filament\Resources\SystemSettingResource::class,
                \App\Filament\Resources\WhatsappSessionResource::class,
                \App\Filament\Resources\WAMessageLogResource::class,
                \App\Filament\Resources\MessageTextResource::class,
                \App\Filament\Resources\BulkInviteCampaignResource::class,
                \App\Filament\Resources\AudienceMetricResource::class,
                \App\Filament\Resources\WhatsappTriggerResource::class,

                \App\Filament\Resources\RestaurantTableResource::class,
                \App\Filament\Resources\ReservationTermResource::class,

                \App\Filament\Resources\PartnerResource::class,
                \App\Filament\Resources\GatewayAccountResource::class,

                // --- New Clinic Resources ---
                \App\Filament\Resources\DoctorResource::class,
                \App\Filament\Resources\PatientResource::class,
                \App\Filament\Resources\VisitResource::class,
                \App\Filament\Resources\ClinicItemResource::class,
                \App\Filament\Resources\DoctorCompensationProfileResource::class,
                \App\Filament\Resources\DoctorCompensationLedgerResource::class,
                \App\Filament\Resources\FollowUpPlanResource::class,
                \App\Filament\Resources\ClinicItemStockResource::class,
                \App\Filament\Resources\ClinicStockMovementResource::class,
                \App\Filament\Resources\VisitStockRequestResource::class,
                \App\Filament\Resources\ClinicPackageResource::class,

                // --- Accounting (Phase 1) ---
                \App\Filament\Resources\Accounting\ChartOfAccountResource::class,
                \App\Filament\Resources\Accounting\JournalEntryResource::class,

                // --- Accounting (Phase 2) ---
                \App\Filament\Resources\Accounting\VendorResource::class,
                \App\Filament\Resources\Accounting\ExpenseResource::class,
                \App\Filament\Resources\Accounting\AccountingPeriodResource::class,
                \App\Filament\Resources\Accounting\BankReconciliationResource::class,
            ])
            ->pages([
                \App\Filament\Pages\AdminDashboardRoute::class,
                \App\Filament\Pages\ClinicReportingDashboard::class,
                // AdminDashboard::class,
                ReservationsDashboard::class,
                CheckInScanner::class,
                WhatsAppRateLimitSettings::class,
                WhatsAppCampaignSettings::class,
                DoctorSchedule::class,
                \App\Filament\Pages\ClinicReports::class,
                \App\Filament\Pages\DailyClosingReport::class,
                // \App\Filament\Pages\InvestorDashboard::class,
                // \App\Filament\Pages\DailyBusinessReport::class,
                \App\Filament\Pages\ExecutiveDashboard::class,
                \App\Filament\Pages\WaitingPatients::class,
                // \App\Filament\Pages\NurseStation::class,
                \App\Filament\Pages\DailyReconciliationReport::class,

                // --- Accounting (Phase 1) ---
                \App\Filament\Pages\Accounting\TrialBalance::class,

                // --- Accounting (Phase 2) reports ---
                \App\Filament\Pages\Accounting\GeneralLedger::class,
                \App\Filament\Pages\Accounting\ProfitAndLossReport::class,
                \App\Filament\Pages\Accounting\BalanceSheetReport::class,
                \App\Filament\Pages\Accounting\CashFlowReport::class,
            ])
            ->widgets([
                \App\Filament\Widgets\WhatsAppStatusWidget::class,
                \App\Filament\Widgets\BookingFunnel::class,
                \App\Filament\Widgets\DailyHourlyChart::class,
                \App\Filament\Widgets\InvestorStatsOverview::class,
                \App\Filament\Widgets\RevenueTrendChart::class,
                \App\Filament\Widgets\DoctorUtilizationChart::class,
                \App\Filament\Widgets\RevenueCompositionChart::class,
                \App\Filament\Widgets\Clinic\ClinicProfitOverview::class,
                \App\Filament\Widgets\Clinic\ClinicProfitTrend::class,
                \App\Filament\Widgets\Clinic\ClinicMarginTrend::class,
                \App\Filament\Widgets\Clinic\ClinicDoctorCutTrend::class,
                \App\Filament\Widgets\Clinic\ClinicTopDoctors::class,
                \App\Filament\Widgets\Clinic\ClinicTopItems::class,
            ])

            // ORDER GROUPS HERE (TOP → BOTTOM)
            // Make sure these labels match your resources/pages navigationGroup strings.
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_operations')),
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_scheduling')),
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_setup')),
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_inventory')),
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_reports')),
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_finance')),
                NavigationGroup::make()->label(fn () => __('common.nav.accounting')),
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_tools')),
                NavigationGroup::make()->label(fn () => __('common.nav.clinic_compliance')),

                NavigationGroup::make()->label('Access Control'),
                NavigationGroup::make()->label('Messaging'),
                NavigationGroup::make()->label('WhatsApp'),
                NavigationGroup::make()->label('System'),
            ])

            // Optional: If you want *only* these groups and nothing else auto-created:
            // ->navigationGroups([...])->navigationGroupsAreCollapsible(true)

            ->plugin(FilamentApexChartsPlugin::make())
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar'])
            )

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                \App\Http\Middleware\SetLocaleFromUser::class,
            ])
            ->userMenuItems([
                // Locale switcher: persists to users.preferred_locale.
                'switch-locale' => \Filament\Navigation\MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'ar'
                        ? __('common.locale.switch_to_english')
                        : __('common.locale.switch_to_arabic'))
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale.switch', [
                        'lang' => app()->getLocale() === 'ar' ? 'en' : 'ar',
                    ])),
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
