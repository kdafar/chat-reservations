<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Models\Branch;
use App\Models\Doctor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ClinicReports extends Page
{
    use HasHelpAction;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'clinic-reports-v2';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.clinic-reports';

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.clinic_reports.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.clinic_reports.title');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_clinic_reports');
    }

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.clinic_reports.what.heading'), 'body' => __('help.pages.clinic_reports.what.body')],
            ['heading' => __('help.pages.clinic_reports.how.heading'), 'items' => (array) trans('help.pages.clinic_reports.how.items')],
            ['heading' => __('help.pages.clinic_reports.faq.heading'), 'items' => (array) trans('help.pages.clinic_reports.faq.items')],
        ];
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
