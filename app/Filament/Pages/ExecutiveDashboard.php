<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\DoctorShift;
use App\Models\FollowUpPlan;
use App\Models\Visit;
use App\Models\VisitItem;
use App\Models\VisitPayment;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class ExecutiveDashboard extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?string $navigationGroup = null;

    protected static string $view = 'filament.pages.executive-dashboard';

    protected static ?int $navigationSort = -110;

    protected ?string $maxContentWidth = 'full';

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.executive_dashboard.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.executive_dashboard.title');
    }

    public array $dashboardData = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_executive-dashboard');
    }

    public function mount(): void
    {
        if (! is_array($this->filters)) {
            $this->filters = [];
        }

        $this->filters = array_merge([
            'period' => 'month',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'branch_id' => null,
        ], $this->filters);

        $this->dashboardData = [
            'meta' => [],
            'kpis' => [],
            'revenue_trend' => [],
            'payment_mix' => [],
            'branch_performance' => [],
            'doctor_performance' => [],
            'item_profitability' => [],
            'cancellation_analysis' => [],
            'followup_funnel' => [],
            'utilization' => [],
            'booking_sources' => [], // Added new key
        ];

        $this->updateDashboard();
    }

    public function updatedFilters(): void
    {
        $this->updateDashboard();
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->live()
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('period')
                            ->label('Time Period')
                            ->options([
                                'today' => 'Today',
                                'week' => 'This Week',
                                'month' => 'This Month',
                                'quarter' => 'This Quarter',
                                'year' => 'This Year',
                                'custom' => 'Custom Range',
                            ])
                            ->default('month')
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->updateDashboard();
                            }),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->visible(fn (Get $get) => $get('period') === 'custom')
                            ->default(now()->startOfMonth())
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->updateDashboard();
                            }),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->visible(fn (Get $get) => $get('period') === 'custom')
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->updateDashboard();
                            }),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(function () {
                                try {
                                    return Branch::query()
                                        ->get()
                                        ->mapWithKeys(function ($branch) {
                                            $label = $branch->name;
                                            if (is_array($label)) {
                                                $label = $label['en'] ?? reset($label) ?? 'Unknown Branch';
                                            } elseif (is_string($label) && (str_starts_with(trim($label), '{') || str_starts_with(trim($label), '['))) {
                                                $decoded = json_decode($label, true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                    $label = $decoded['en'] ?? reset($decoded) ?? $label;
                                                }
                                            }

                                            return [$branch->id => (string) $label];
                                        })
                                        ->prepend('All Branches', '')
                                        ->toArray();
                                } catch (\Throwable $e) {
                                    Log::error('ExecutiveDashboard Branch Load Error: '.$e->getMessage());

                                    return ['' => 'All Branches (Error Loading)'];
                                }
                            })
                            ->default(null)
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->updateDashboard();
                            }),
                    ])
                    ->columns(4)
                    ->compact(),
            ]);
    }

    public function updateDashboard(): void
    {
        $period = (string) ($this->filters['period'] ?? 'month');
        $branchId = $this->filters['branch_id'] ?? null;

        if ($branchId === '' || $branchId === 'null') {
            $branchId = null;
        } else {
            $branchId = $branchId ? (int) $branchId : null;
        }

        [$startDate, $endDate] = $this->getDateRange($period);

        $this->dashboardData = [
            'meta' => [
                'period' => $period,
                'period_label' => (string) $this->getPeriodLabel($period, $startDate, $endDate),
                'generated_at' => now()->format('d M Y, H:i'),
            ],
            'kpis' => $this->getKPIs($startDate, $endDate, $branchId),
            'revenue_trend' => $this->getRevenueTrend($startDate, $endDate, $branchId),
            'payment_mix' => $this->getPaymentMix($startDate, $endDate, $branchId),
            'branch_performance' => $this->getBranchPerformance($startDate, $endDate),
            'doctor_performance' => $this->getDoctorPerformance($startDate, $endDate, $branchId),
            'item_profitability' => $this->getItemProfitability($startDate, $endDate, $branchId),
            'cancellation_analysis' => $this->getCancellationAnalysis($startDate, $endDate, $branchId),
            'followup_funnel' => $this->getFollowUpFunnel($startDate, $endDate, $branchId),
            'utilization' => $this->getDoctorUtilization($startDate, $endDate, $branchId),
            'booking_sources' => $this->getBookingSources($startDate, $endDate, $branchId), // New Method Call
        ];

        // Force Livewire to re-render
        $this->dispatch('dashboard-updated', dashboardData: $this->dashboardData);
    }

    protected function getDateRange(string $period): array
    {
        try {
            if ($period === 'custom') {
                $startRaw = $this->filters['start_date'] ?? null;
                $endRaw = $this->filters['end_date'] ?? null;

                $start = $startRaw ? Carbon::parse($startRaw)->startOfDay() : now()->startOfMonth();
                $end = $endRaw ? Carbon::parse($endRaw)->endOfDay() : now()->endOfDay();

                return [$start, $end];
            }

            return match ($period) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'week' => [now()->startOfWeek(), now()->endOfWeek()],
                'month' => [now()->startOfMonth(), now()->endOfMonth()],
                'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
                'year' => [now()->startOfYear(), now()->endOfYear()],
                default => [now()->startOfMonth(), now()->endOfMonth()],
            };
        } catch (\Throwable $e) {
            Log::error('ExecutiveDashboard Date Parse Error: '.$e->getMessage());

            return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    protected function getPeriodLabel(string $period, Carbon $startDate, Carbon $endDate): string
    {
        return match ($period) {
            'today' => 'Today - '.$startDate->format('d M Y'),
            'week' => 'Week of '.$startDate->format('d M'),
            'month' => $startDate->format('F Y'),
            'quarter' => 'Q'.$startDate->quarter.' '.$startDate->year,
            'year' => (string) $startDate->year,
            'custom' => $startDate->format('d M Y').' - '.$endDate->format('d M Y'),
            default => $startDate->format('F Y'),
        };
    }

    protected function getKPIs(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        $query = Visit::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Revenue = fees + packages + items − discount (full bill, not just fees).
        $revenueExpr = 'SUM(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0))';

        $currentRevenue = (float) (clone $query)->selectRaw("$revenueExpr as r")->value('r');
        $currentProfit = (clone $query)->sum('profit_total');
        $currentVisits = (clone $query)->count();
        $currentAvgTx = $currentVisits > 0 ? $currentRevenue / $currentVisits : 0;

        $periodLength = max(1, $endDate->diffInDays($startDate));
        $prevStart = (clone $startDate)->subDays($periodLength + 1);
        $prevEnd = (clone $startDate)->subDay();

        $prevQuery = Visit::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$prevStart, $prevEnd]);

        if ($branchId) {
            $prevQuery->where('branch_id', $branchId);
        }

        $prevRevenue = (float) (clone $prevQuery)->selectRaw("$revenueExpr as r")->value('r');
        $prevProfit = $prevQuery->sum('profit_total');
        $prevVisits = $prevQuery->count();

        $bookingQuery = Booking::query()
            ->whereBetween('res_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($branchId) {
            $bookingQuery->where('branch_id', $branchId);
        }

        $totalBookings = (clone $bookingQuery)->whereIn('status', ['completed', 'no_show'])->count();
        $completedBookings = (clone $bookingQuery)->where('status', 'completed')->count();
        $showRate = $totalBookings > 0 ? ($completedBookings / $totalBookings) * 100 : 0;

        return [
            'revenue' => [
                'value' => (float) $currentRevenue,
                'change' => $prevRevenue > 0 ? (($currentRevenue - $prevRevenue) / $prevRevenue) * 100 : 0,
                'trend' => $currentRevenue >= $prevRevenue ? 'up' : 'down',
            ],
            'profit' => [
                'value' => (float) $currentProfit,
                'change' => $prevProfit > 0 ? (($currentProfit - $prevProfit) / $prevProfit) * 100 : 0,
                'trend' => $currentProfit >= $prevProfit ? 'up' : 'down',
            ],
            'margin' => [
                'value' => $currentRevenue > 0 ? ($currentProfit / $currentRevenue) * 100 : 0,
                'change' => 0,
                'trend' => 'neutral',
            ],
            'avg_transaction' => [
                'value' => (float) $currentAvgTx,
                'change' => 0,
                'trend' => 'neutral',
            ],
            'visits' => [
                'value' => (int) $currentVisits,
                'change' => $prevVisits > 0 ? (($currentVisits - $prevVisits) / $prevVisits) * 100 : 0,
                'trend' => $currentVisits >= $prevVisits ? 'up' : 'down',
            ],
            'show_rate' => [
                'value' => (float) $showRate,
                'change' => 0,
                'trend' => 'neutral',
            ],
        ];
    }

    protected function getRevenueTrend(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        // Full revenue per day = fees + packages + items − discount.
        $query = Visit::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->selectRaw('DATE(completed_at) as date')
            ->selectRaw('SUM(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0)) as revenue')
            ->selectRaw('SUM(profit_total) as profit')
            ->groupBy('date')
            ->orderBy('date');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get()->map(fn ($row) => [
            'date' => Carbon::parse($row->date)->format('d M'),
            'revenue' => (float) $row->revenue,
            'profit' => (float) $row->profit,
        ])->values()->toArray();
    }

    protected function getPaymentMix(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        $query = VisitPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method');

        if ($branchId) {
            $query->whereHas('visit', fn ($q) => $q->where('branch_id', $branchId));
        }

        $data = $query->get();
        $totalAmount = (float) $data->sum('total');

        return $data->map(fn ($row) => [
            'name' => strtoupper((string) ($row->method ?: 'Unknown')),
            'value' => (float) $row->total,
            'percentage' => $totalAmount > 0 ? ((float) $row->total / $totalAmount) * 100 : 0,
        ])->values()->toArray();
    }

    // NEW METHOD for Booking Sources
    protected function getBookingSources(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        $query = Booking::query()
            ->whereBetween('res_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $data = $query->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->get();

        $totalCount = (int) $data->sum('total');

        return $data->map(fn ($row) => [
            'name' => ucfirst((string) ($row->source ?: 'Unknown')),
            'value' => (int) $row->total,
            'percentage' => $totalCount > 0 ? ((int) $row->total / $totalCount) * 100 : 0,
        ])->values()->toArray();
    }

    protected function getBranchPerformance(Carbon $startDate, Carbon $endDate): array
    {
        $branches = Visit::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->selectRaw('branch_id')
            ->selectRaw('SUM(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0)) as revenue')
            ->selectRaw('SUM(profit_total) as profit')
            ->selectRaw('COUNT(*) as visits')
            ->selectRaw('AVG(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0)) as avg_tx')
            ->groupBy('branch_id')
            ->get();

        return $branches->map(function ($row) use ($startDate, $endDate) {
            $branch = Branch::find($row->branch_id);
            $margin = $row->revenue > 0 ? ($row->profit / $row->revenue) * 100 : 0;

            $totalBookings = Booking::query()
                ->where('branch_id', $row->branch_id)
                ->whereBetween('res_date', [$startDate, $endDate])
                ->whereIn('status', ['completed', 'no_show'])
                ->count();

            $completedBookings = Booking::query()
                ->where('branch_id', $row->branch_id)
                ->whereBetween('res_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->count();

            $showRate = $totalBookings > 0 ? ($completedBookings / $totalBookings) * 100 : 0;

            $name = $branch?->name;
            if (is_array($name)) {
                $name = $name['en'] ?? array_values($name)[0] ?? 'Unknown';
            } elseif (is_string($name) && str_starts_with(trim($name), '{')) {
                $decoded = json_decode($name, true);
                $name = $decoded['en'] ?? array_values($decoded)[0] ?? 'Unknown';
            }

            return [
                'branch' => (string) ($name ?? 'Unknown'),
                'revenue' => (float) $row->revenue,
                'profit' => (float) $row->profit,
                'margin' => (float) $margin,
                'visits' => (int) $row->visits,
                'avg_tx' => (float) $row->avg_tx,
                'show_rate' => (float) $showRate,
            ];
        })->values()->toArray();
    }

    protected function getDoctorPerformance(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        $query = Visit::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->selectRaw('doctor_id')
            ->selectRaw('COUNT(*) as visits')
            ->selectRaw('SUM(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0)) as revenue')
            ->groupBy('doctor_id');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $visits = $query->get();

        return $visits->map(function ($row) use ($startDate, $endDate) {
            $doctor = Doctor::find($row->doctor_id);

            $compensation = DoctorCompensationLedger::query()
                ->where('doctor_id', $row->doctor_id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('doctor_cut_amount');

            $totalShiftMinutes = DoctorShift::query()
                ->where('doctor_id', $row->doctor_id)
                ->whereBetween('shift_date', [$startDate, $endDate])
                ->where('is_cancelled', 0)
                ->get()
                ->sum(function ($shift) {
                    $dateStr = $shift->shift_date instanceof Carbon
                        ? $shift->shift_date->format('Y-m-d')
                        : substr((string) $shift->shift_date, 0, 10);

                    $start = Carbon::parse($dateStr.' '.$shift->start_time);
                    $end = Carbon::parse($dateStr.' '.$shift->end_time);

                    return max(0, $end->diffInMinutes($start) - (int) ($shift->break_minutes ?? 0));
                });

            $bookedMinutes = Booking::query()
                ->where('doctor_id', $row->doctor_id)
                ->whereBetween('res_date', [$startDate, $endDate])
                ->whereIn('status', ['confirmed', 'completed'])
                ->get()
                ->sum(function ($booking) {
                    $start = Carbon::parse($booking->res_start);
                    $end = Carbon::parse($booking->res_end);

                    return max(0, $end->diffInMinutes($start));
                });

            $utilization = $totalShiftMinutes > 0 ? ($bookedMinutes / $totalShiftMinutes) * 100 : 0;

            $name = (string) ($doctor?->name ?? 'Unknown');

            return [
                'name' => $name,
                'visits' => (int) $row->visits,
                'revenue' => (float) $row->revenue,
                'compensation' => (float) $compensation,
                'net_profit' => (float) $row->revenue - (float) $compensation,
                'utilization' => (float) min(100, $utilization),
            ];
        })->sortByDesc('revenue')->values()->toArray();
    }

    protected function getItemProfitability(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        $query = VisitItem::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('clinic_item_id')
            ->selectRaw('SUM(qty) as units_sold')
            ->selectRaw('SUM(line_price_total) as revenue')
            ->selectRaw('SUM(line_cost_total) as cost')
            ->groupBy('clinic_item_id');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $items = $query->get();

        return $items->map(function ($row) {
            $item = ClinicItem::find($row->clinic_item_id);
            $profit = (float) $row->revenue - (float) $row->cost;
            $margin = (float) $row->revenue > 0 ? ($profit / (float) $row->revenue) * 100 : 0;

            $name = $item?->name;
            if (is_array($name)) {
                $name = $name['en'] ?? array_values($name)[0] ?? 'Unknown';
            } elseif (is_string($name) && str_starts_with(trim($name), '{')) {
                $decoded = json_decode($name, true);
                $name = $decoded['en'] ?? array_values($decoded)[0] ?? 'Unknown';
            }

            return [
                'type' => (string) ($item?->type ?? 'Unknown'),
                'name' => (string) ($name ?? 'Unknown'),
                'revenue' => (float) $row->revenue,
                'cost' => (float) $row->cost,
                'profit' => (float) $profit,
                'margin' => (float) $margin,
                'units_sold' => (float) $row->units_sold,
            ];
        })->sortByDesc('profit')->values()->take(10)->toArray();
    }

    protected function getCancellationAnalysis(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        $query = Booking::query()
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$startDate, $endDate])
            ->selectRaw('cancellation_reason_code, COUNT(*) as count')
            ->groupBy('cancellation_reason_code');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $data = $query->get();
        $total = (int) $data->sum('count');

        return $data->map(fn ($row) => [
            'reason' => (string) ($row->cancellation_reason_code ?? 'No Reason'),
            'count' => (int) $row->count,
            'percentage' => $total > 0 ? ((int) $row->count / $total) * 100 : 0,
        ])->sortByDesc('count')->values()->toArray();
    }

    protected function getFollowUpFunnel(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        $query = FollowUpPlan::query()
            ->whereBetween('suggested_at', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $suggested = (clone $query)->count();
        $booked = (clone $query)->whereNotNull('booking_id')->count();
        $completed = (clone $query)->where('status', 'completed')->count();

        return [
            [
                'stage' => 'Suggested',
                'count' => (int) $suggested,
                'percentage' => 100,
            ],
            [
                'stage' => 'Booked',
                'count' => (int) $booked,
                'percentage' => $suggested > 0 ? ($booked / $suggested) * 100 : 0,
            ],
            [
                'stage' => 'Completed',
                'count' => (int) $completed,
                'percentage' => $suggested > 0 ? ($completed / $suggested) * 100 : 0,
            ],
        ];
    }

    protected function getDoctorUtilization(Carbon $startDate, Carbon $endDate, ?int $branchId): array
    {
        return [];
    }
}
