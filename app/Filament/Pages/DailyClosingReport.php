<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Services\Clinic\DailyClosingReportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;

final class DailyClosingReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Clinic — Reports';

    protected static ?string $navigationLabel = 'Daily Closing';

    protected static ?string $title = 'Daily Closing Report';

    // Set to high priority for the Clinic group
    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.clinic.daily-closing-report';

    public ?string $date = null;

    /** @var int[] */
    public array $branch_ids = [];

    /** @var array<string,mixed> */
    public array $report = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_clinic_Closing_reports');
    }

    public function mount(): void
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $this->date = Carbon::now($tz)->toDateString();

        $this->refreshReport();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Report Configuration')
                ->description('Filter by date and branch to generate the daily snapshot.')
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('date')
                        ->label('Closing Date')
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->refreshReport()),

                    Forms\Components\Select::make('branch_ids')
                        ->label('Branches')
                        ->placeholder('All Branches')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => Branch::query()
                            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                            ->get()
                            ->mapWithKeys(fn ($b) => [$b->id => $b->localized_name ?? ('#'.$b->id)])
                            ->toArray()
                        )
                        ->live()
                        ->afterStateUpdated(fn () => $this->refreshReport()),

                    Forms\Components\Placeholder::make('timezone_hint')
                        ->label('Timezone')
                        ->content(config('app.timezone', 'Asia/Kuwait')),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->color('gray')
                ->icon('heroicon-m-arrow-path')
                ->action(fn () => $this->refreshReport()),

            Action::make('print')
                ->label('Print PDF')
                ->icon('heroicon-m-printer')
                ->color('warning')
                ->action(fn () => $this->dispatch('print-report')),
        ];
    }

    /**
     * Senior Architect Note: Using the updated Service logic to retrieve chart payloads.
     */
    public function refreshReport(): void
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $day = Carbon::parse((string) ($this->date ?: Carbon::now($tz)->toDateString()), $tz);

        // Standard Null Defense: Service handles business logic and chart aggregation
        $this->report = app(DailyClosingReportService::class)->build($day, $this->branch_ids);
    }
}
