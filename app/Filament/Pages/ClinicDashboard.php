<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Models\Booking;
use App\Models\Visit;
use App\Models\VisitPayment;
use Carbon\Carbon;
use Filament\Pages\Page;

class ClinicDashboard extends Page
{
    use HasHelpAction;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'clinic-dashboard';

    protected static ?int $navigationSort = -999;

    protected static string $view = 'filament.pages.clinic-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.clinic_dashboard.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.clinic_dashboard.title');
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.clinic_dashboard.what.heading'), 'body' => __('help.pages.clinic_dashboard.what.body')],
            ['heading' => __('help.pages.clinic_dashboard.how.heading'), 'items' => (array) trans('help.pages.clinic_dashboard.how.items')],
            ['heading' => __('help.pages.clinic_dashboard.faq.heading'), 'items' => (array) trans('help.pages.clinic_dashboard.faq.items')],
        ];
    }

    public array $stats = [];

    public array $todayBookings = [];

    public function mount(): void
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $today = Carbon::now($tz)->toDateString();
        $todayStart = Carbon::now($tz)->startOfDay();
        $todayEnd = Carbon::now($tz)->endOfDay();

        // --- KPIs ---
        $bookingsToday = Booking::query()
            ->whereDate('res_date', $today)
            ->count();

        $completedToday = Visit::query()
            ->whereDate('completed_at', $today)
            ->where('status', Visit::STATUS_COMPLETED)
            ->count();

        $awaitingNow = Visit::query()
            ->whereIn('status', [
                Visit::STATUS_AWAITING_DOCTOR,
                Visit::STATUS_IN_PROGRESS,
                Visit::STATUS_AWAITING_STOCK,
                Visit::STATUS_AWAITING_PAYMENT,
            ])
            ->count();

        $revenueToday = (float) VisitPayment::query()
            ->whereDate('paid_at', $today)
            ->where('status', 'paid')
            ->sum('amount');

        $pendingPayments = Visit::query()
            ->where('status', Visit::STATUS_AWAITING_PAYMENT)
            ->count();

        $confirmedToday = Booking::query()
            ->whereDate('res_date', $today)
            ->where('status', 'confirmed')
            ->whereNull('checked_in_at')
            ->count();

        // --- Today's appointments (with names, not IDs) ---
        $this->todayBookings = Booking::query()
            ->with(['patient:id,name,phone', 'doctor:id,name', 'branch:id,name'])
            ->whereDate('res_date', $today)
            ->orderBy('res_time')
            ->limit(12)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'code' => $b->booking_code,
                'status' => (string) ($b->status ?? '—'),
                'time' => $b->res_time
                    ? Carbon::parse($b->res_time)->format('h:i A')
                    : ($b->res_start ? $b->res_start->timezone(config('app.timezone', 'Asia/Kuwait'))->format('h:i A') : '—'),
                'patient_name' => $b->patient?->name ?? '—',
                'patient_phone' => $b->patient?->phone ?? $b->msisdn ?? null,
                'doctor_name' => $b->doctor?->name ?? '—',
                'branch_name' => $b->branch?->localized_name ?? '—',
                'checked_in' => $b->checked_in_at !== null,
            ])
            ->all();

        $this->stats = [
            'bookings_today' => $bookingsToday,
            'completed_today' => $completedToday,
            'awaiting_now' => $awaitingNow,
            'revenue_today' => $revenueToday,
            'pending_payments' => $pendingPayments,
            'confirmed_today' => $confirmedToday,
            'today_label' => Carbon::now($tz)->isoFormat('dddd, D MMMM YYYY'),
        ];
    }
}
