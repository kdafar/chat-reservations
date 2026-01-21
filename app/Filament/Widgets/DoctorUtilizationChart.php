<?php

namespace App\Filament\Widgets;

use App\Models\Doctor;
use App\Models\DoctorShift;
use App\Models\Visit;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class DoctorUtilizationChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Doctor Efficiency (Hours Available vs Worked)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $start = $this->filters['startDate'] ?? now()->startOfMonth();
        $end = $this->filters['endDate'] ?? now()->endOfMonth();

        // Get Active Doctors
        $doctors = Doctor::limit(10)->get(); // Limit to prevent UI clutter

        $names = [];
        $availableHours = [];
        $workedHours = [];

        foreach ($doctors as $doctor) {
            $names[] = $doctor->name;

            // 1. Calculate Available Hours (from DoctorShifts)
            // Note: We access the 'duration_hours' accessor we created in Phase 2
            $shifts = DoctorShift::where('doctor_id', $doctor->id)
                ->whereBetween('shift_date', [$start, $end])
                ->get();

            // Summing up the float hours (e.g., 8.0, 7.5)
            $availableHours[] = $shifts->sum('duration_hours');

            // 2. Calculate Worked Hours (from Visits)
            // Logic: Count visits * 30 mins average (0.5 hours)
            // Ideally this uses 'service_started_at' vs 'completed_at', but this is a safe fallback
            $visitCount = Visit::where('doctor_id', $doctor->id)
                ->where('status', 'completed')
                ->whereBetween('checked_in_at', [$start, $end])
                ->count();

            $workedHours[] = $visitCount * 0.5;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Hours Available (Capacity)',
                    'data' => $availableHours,
                    'backgroundColor' => '#E5E7EB', // Gray
                    'barPercentage' => 0.7,
                ],
                [
                    'label' => 'Hours Worked (Billable)',
                    'data' => $workedHours,
                    'backgroundColor' => '#3B82F6', // Blue
                    'barPercentage' => 0.5,
                ],
            ],
            'labels' => $names,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
