<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class ClinicDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $slug = 'clinic-dashboard';

    protected static ?int $navigationSort = -999;

    protected static string $view = 'filament.pages.clinic-dashboard';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public array $stats = [];

    public array $todayBookings = [];

    public function mount(): void
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $todayStart = Carbon::now($tz)->startOfDay();
        $todayEnd = Carbon::now($tz)->endOfDay();

        // Determine safe columns (prevents "unknown column" crashes if schema differs)
        $bookingDateCol = Schema::hasColumn('bookings', 'booking_date') ? 'booking_date' : (Schema::hasColumn('bookings', 'scheduled_at') ? 'scheduled_at' : 'created_at');
        $bookingStatusCol = Schema::hasColumn('bookings', 'status') ? 'status' : null;

        $visitDateCol = Schema::hasColumn('visits', 'visit_date') ? 'visit_date' : (Schema::hasColumn('visits', 'started_at') ? 'started_at' : 'created_at');

        // --- KPIs (lightweight) ---
        $bookingsToday = Booking::query()
            ->when($bookingDateCol, fn ($q) => $q->whereBetween($bookingDateCol, [$todayStart, $todayEnd]))
            ->count();

        $completedVisitsToday = Visit::query()
            ->when($visitDateCol, fn ($q) => $q->whereBetween($visitDateCol, [$todayStart, $todayEnd]))
            ->when(Schema::hasColumn('visits', 'status'), fn ($q) => $q->where('status', 'completed'))
            ->count();

        $activeDoctors = Doctor::query()
            ->when(Schema::hasColumn('doctors', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->count();

        $patientsTotal = Patient::query()->count();

        // Optional: Pending/Confirmed counts if you have booking status
        $pendingBookings = null;
        $confirmedBookings = null;

        if ($bookingStatusCol) {
            $pendingBookings = Booking::query()
                ->when($bookingDateCol, fn ($q) => $q->whereBetween($bookingDateCol, [$todayStart, $todayEnd]))
                ->where('status', 'pending')
                ->count();

            $confirmedBookings = Booking::query()
                ->when($bookingDateCol, fn ($q) => $q->whereBetween($bookingDateCol, [$todayStart, $todayEnd]))
                ->whereIn('status', ['confirmed', 'approved'])
                ->count();
        }

        $this->stats = [
            'bookings_today' => $bookingsToday,
            'visits_completed_today' => $completedVisitsToday,
            'active_doctors' => $activeDoctors,
            'patients_total' => $patientsTotal,
            'pending_today' => $pendingBookings,
            'confirmed_today' => $confirmedBookings,
            'today_label' => Carbon::now($tz)->toFormattedDateString(),
        ];

        // --- Today list (small, capped) ---
        $this->todayBookings = Booking::query()
            ->select(['id'])
            ->when(Schema::hasColumn('bookings', 'patient_id'), fn ($q) => $q->addSelect('patient_id'))
            ->when(Schema::hasColumn('bookings', 'doctor_id'), fn ($q) => $q->addSelect('doctor_id'))
            ->when($bookingDateCol, fn ($q) => $q->addSelect($bookingDateCol))
            ->when($bookingStatusCol, fn ($q) => $q->addSelect('status'))
            ->when($bookingDateCol, fn ($q) => $q->whereBetween($bookingDateCol, [$todayStart, $todayEnd]))
            ->latest($bookingDateCol)
            ->limit(8)
            ->get()
            ->map(function ($b) use ($bookingDateCol) {
                return [
                    'id' => $b->id,
                    'status' => $b->status ?? null,
                    'time' => isset($b->{$bookingDateCol}) ? Carbon::parse($b->{$bookingDateCol})->format('H:i') : null,
                    'patient_id' => $b->patient_id ?? null,
                    'doctor_id' => $b->doctor_id ?? null,
                ];
            })
            ->all();
    }
}
