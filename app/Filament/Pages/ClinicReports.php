<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Doctor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ClinicReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Clinic — Reports';

    protected static ?string $navigationLabel = 'Clinic Reports (Filters)';

    protected static ?string $slug = 'clinic-reports-v2';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.clinic-reports';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_clinic_reports');
    }

    /**
     * Filters state (passed into widgets).
     * Keep add-only: widgets already accept public ?array $filters = [].
     */
    public array $filters = [];

    /**
     * Simple tabs for UX
     */
    public string $tab = 'overview';

    public function mount(): void
    {
        // Defensive defaults (snapshot-only)
        $this->filters = [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => null,
            'doctor_id' => null,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\DatePicker::make('filters.from')
                            ->label('From')
                            ->native(false)
                            ->reactive(),

                        Forms\Components\DatePicker::make('filters.to')
                            ->label('To')
                            ->native(false)
                            ->reactive(),

                        Forms\Components\Select::make('filters.branch_id')
                            ->label('Branch')
                            ->options(fn () => Branch::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive(),

                        Forms\Components\Select::make('filters.doctor_id')
                            ->label('Doctor')
                            ->options(fn () => Doctor::query()
                                ->where('is_active', 1)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive(),
                    ])
                    ->columns(4)
                    ->compact(),
            ]);
    }

    /**
     * Used to force widget remount when filters change (safe + reliable).
     */
    public function getFiltersKey(): string
    {
        return md5(json_encode([
            $this->filters['from'] ?? null,
            $this->filters['to'] ?? null,
            $this->filters['branch_id'] ?? null,
            $this->filters['doctor_id'] ?? null,
            $this->tab ?? null,
        ]));
    }

    public function setTab(string $tab): void
    {
        $allowed = ['overview', 'trends', 'doctors', 'items'];
        $this->tab = in_array($tab, $allowed, true) ? $tab : 'overview';
    }
}
