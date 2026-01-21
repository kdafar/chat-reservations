<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\Visit;
use App\Models\VisitPayment;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class DailyBusinessReport extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Daily Intelligence';

    protected static ?string $title = 'Daily Operational Report';

    protected static ?string $navigationGroup = 'Reports';

    protected static string $view = 'filament.pages.daily-business-report';

    public $reportData = [];

    public function mount()
    {
        // Default to today if no filter
        $this->updateReport();
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        DatePicker::make('date')
                            ->label('Report Date')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->closeOnDateSelection()
                            ->live()
                            ->afterStateUpdated(fn () => $this->updateReport()),
                    ])
                    ->compact(),
            ]);
    }

    public function updateReport()
    {
        $date = $this->filters['date'] ?? now();
        $user = Auth::user();

        // 1. Determine Scope based on Role
        // Note: Using the role names from your database dump
        $isDoctor = $user->hasRole('clinic_doctor');
        $isReception = $user->hasRole('clinic_reception');
        $isOwner = $user->hasRole(['partner_owner', 'admin', 'clinic_manager']);

        // 2. Fetch Data
        $this->reportData = [
            'role' => $user->getRoleNames()->first() ?? 'Staff',
            'date' => Carbon::parse($date)->format('l, d M Y'),
            'financials' => $this->getFinancials($date, $user, $isDoctor, $isOwner),
            'bookings' => $this->getBookings($date, $user, $isDoctor),
            'payments' => $this->getPayments($date, $user, $isReception, $isOwner),
        ];
    }

    protected function getFinancials($date, $user, $isDoctor, $isOwner)
    {
        if ($isDoctor) {
            // Doctors only see THEIR earnings
            // Assuming the user is linked to a Doctor model via email or ID
            $doctor = Doctor::where('name', 'like', "%{$user->name}%")->first();
            $doctorId = $doctor?->id ?? 0;

            return [
                'revenue_generated' => DoctorCompensationLedger::whereDate('created_at', $date)
                    ->where('doctor_id', $doctorId)->sum('fees_snapshot'),
                'commission_earned' => DoctorCompensationLedger::whereDate('created_at', $date)
                    ->where('doctor_id', $doctorId)->sum('doctor_cut_amount'),
                'patients_seen' => Visit::whereDate('checked_in_at', $date)
                    ->where('doctor_id', $doctorId)->count(),
            ];
        }

        if ($isOwner) {
            // Owners see EVERYTHING
            // We structure this for a "Waterfall Chart" (Revenue -> Costs -> Profit)
            $revenue = Visit::whereDate('checked_in_at', $date)->sum('fees_total');
            $cogs = Visit::whereDate('checked_in_at', $date)->sum('items_cost_total');
            $doctorShare = DoctorCompensationLedger::whereDate('created_at', $date)->sum('doctor_cut_amount');

            // Hardcoded Logic: Staff Salary Estimation (daily proxy)
            // In a real system, you'd pull this from a 'salaries' table.
            // For now, we assume a fixed daily burn rate (e.g., 200 KD)
            $estimatedFixedCost = 200;

            $netProfit = $revenue - $cogs - $doctorShare - $estimatedFixedCost;

            return [
                'total_revenue' => $revenue,
                'cost_of_goods' => $cogs,
                'doctor_commissions' => $doctorShare,
                'staff_overhead' => $estimatedFixedCost,
                'net_profit' => $netProfit,
                'profit_status' => $netProfit > 0 ? 'Profit' : 'Loss',
                // Data structured for ECharts Waterfall
                'chart_data' => [
                    ['name' => 'Gross Revenue', 'value' => $revenue, 'type' => 'income'],
                    ['name' => 'COGS', 'value' => $cogs, 'type' => 'expense'],
                    ['name' => 'Doc Comm.', 'value' => $doctorShare, 'type' => 'expense'],
                    ['name' => 'Fixed/Staff', 'value' => $estimatedFixedCost, 'type' => 'expense'],
                    ['name' => 'Net Profit', 'value' => $netProfit, 'type' => 'total'],
                ],
            ];
        }

        return null; // Reception doesn't see P&L
    }

    protected function getBookings($date, $user, $isDoctor)
    {
        $query = Booking::whereDate('res_date', $date);

        if ($isDoctor) {
            $doctor = Doctor::where('name', 'like', "%{$user->name}%")->first();
            $query->where('doctor_id', $doctor?->id);
        }

        // Breakdown by Source (Formatted for ECharts Pie/Donut)
        // Returns: [['name' => 'Whatsapp', 'value' => 10], ...]
        $bySource = (clone $query)->selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->get()
            ->map(fn ($row) => [
                'name' => ucfirst($row->source ?: 'Unknown'),
                'value' => $row->count,
            ])->values()->toArray();

        // Breakdown by Status
        $byStatus = (clone $query)->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'name' => ucfirst($row->status ?: 'Unknown'),
                'value' => $row->count,
            ])->values()->toArray();

        return [
            'total' => $query->count(),
            'sources_chart' => $bySource,
            'statuses_chart' => $byStatus,
        ];
    }

    protected function getPayments($date, $user, $isReception, $isOwner)
    {
        if (! $isReception && ! $isOwner) {
            return null;
        }

        $query = VisitPayment::whereDate('paid_at', $date);

        if ($isReception) {
            // Reception only sees what THEY collected
            $query->where('collected_by_user_id', $user->id);
        }

        // Breakdown by Method (Formatted for ECharts)
        $byMethod = (clone $query)->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->get()
            ->map(fn ($row) => [
                'name' => strtoupper($row->method ?: 'OTHER'),
                'value' => (float) $row->total,
            ])->values()->toArray();

        return [
            'total_collected' => $query->sum('amount'),
            'methods_chart' => $byMethod,
        ];
    }
}
