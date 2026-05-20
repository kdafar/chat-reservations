<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\VisitPayment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class DailyReconciliationReport extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Clinic — Reports';

    protected static ?string $title = 'Daily Shift Reconciliation';

    protected static string $view = 'filament.pages.daily-reconciliation-report';

    protected static ?int $navigationSort = 100;

    protected ?string $maxContentWidth = 'full';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'date' => now()->toDateString(),
            'branch_id' => null, // Default to "All My Branches"
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('date')
                    ->label('Report Date')
                    ->default(now())
                    ->required()
                    ->live(), // Auto-refresh on change

                Select::make('branch_id')
                    ->label('Branch')
                    // FIX: Call get() before pluck() so the 'localized_name' accessor works
                    ->options(fn () => Branch::forUser(auth()->user())
                        ->get()
                        ->pluck('localized_name', 'id')
                    )
                    ->placeholder('All My Branches')
                    ->live(),
            ])
            ->columns(4)
            ->statePath('data');
    }

    /**
     * Compute stats for the view.
     * This logic is strictly read-only and safe.
     */
    protected function getViewData(): array
    {
        $date = $this->data['date'] ?? now()->toDateString();
        $selectedBranchId = $this->data['branch_id'] ?? null;
        $user = auth()->user();

        // 1. Base Query: Valid Payments on selected date
        $query = VisitPayment::query()
            ->with(['visit.branch', 'visit.patient', 'visit.doctor', 'collectedBy'])
            ->whereDate('paid_at', $date)
            ->where('status', 'paid'); // Only confirmed money

        // 2. Apply Security Scopes via Relationship
        $query->whereHas('visit', function ($q) use ($user, $selectedBranchId) {

            // A. Branch Scope (Staff/Managers)
            // Only fetch visits in branches the user is allowed to access
            $allowedBranchIds = Branch::forUser($user)->pluck('id');
            $q->whereIn('branch_id', $allowedBranchIds);

            // B. User Filter (Specific Branch Selection)
            if ($selectedBranchId) {
                $q->where('branch_id', $selectedBranchId);
            }

            // C. Doctor Scope (If user is a doctor, only show THEIR revenue)
            // We check if the user is linked to a doctor profile
            if (! $user->hasRole(['admin', 'super_admin'])) {
                $doctorProfile = Doctor::where('user_id', $user->id)->first();
                if ($doctorProfile) {
                    $q->where('doctor_id', $doctorProfile->id);
                }
            }
        });

        $payments = $query->orderBy('paid_at', 'desc')->get();

        // 3. Aggregations (Collections)
        $totalCollected = $payments->sum('amount');

        // Group by Method (Cash, KNET, Link)
        $byMethod = $payments->groupBy(fn ($p) => strtolower($p->method ?? 'unknown'))
            ->map(fn ($rows) => $rows->sum('amount'));

        // Group by Collector (Who took the money?)
        // If collected_by is null, it's usually "System/Online"
        $byCollector = $payments->groupBy(fn ($p) => $p->collectedBy->name ?? 'System (Online)')
            ->map(fn ($rows) => $rows->sum('amount'));

        return [
            'payments' => $payments,
            'totalCollected' => $totalCollected,
            'byMethod' => $byMethod,
            'byCollector' => $byCollector,
            'reportDate' => Carbon::parse($date),
            'isBranchUser' => ! $user->hasRole('admin'), // For UI hints
        ];
    }
}
