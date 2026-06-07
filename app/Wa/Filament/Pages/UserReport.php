<?php

namespace App\Wa\Filament\Pages;

use App\Wa\Filament\Widgets\UserReportStatsWidget;
use App\Wa\Hub\Models\Vendors;
use App\Wa\Hub\Models\WhatsappSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static string $view = 'filament.pages.user-report';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return false;
    }

    /**
     * This now returns a simple array of widget classes, which fixes the error.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            UserReportStatsWidget::class,
        ];
    }

    public static function getNavigationGroup(): string
    {
        return __('Promotions');
    }

    public static function getNavigationLabel(): string
    {
        return __('User Report');
    }

    public function getTitle(): string
    {
        return __('Inactive User Report');
    }

    /**
     * On page load, we dispatch an event to update the widget with initial data.
     */
    public function mount(): void
    {
        $this->form->fill();
        $this->dispatch('updateReportStats', filters: $this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('inactivity_period')
                    ->label('Inactive For (Days)')
                    ->options([
                        '7' => '7+ days',
                        '14' => '14+ days',
                        '30' => '30+ days',
                        '90' => '90+ days',
                    ]),

                Select::make('restaurant_id')
                    ->label('Last Restaurant Ordered From (Optional)')
                    ->options(Vendors::pluck('name', 'id'))
                    ->searchable(),
            ])
            ->statePath('data');
    }

    /**
     * When the filter button is clicked, we dispatch an event to tell the
     * stats widget to update itself with the new filter data.
     */
    public function filter(): void
    {
        $this->dispatch('updateReportStats', filters: $this->data);
    }

    protected function getTableQuery(): Builder
    {
        $query = WhatsappSession::query()
            ->whereNotNull('last_interacted_at')
            ->with(['restaurant', 'customerProfile']);

        if ($days = $this->data['inactivity_period'] ?? null) {
            $query->where('last_interacted_at', '<=', now()->subDays((int) $days));
        }

        if ($restaurantId = $this->data['restaurant_id'] ?? null) {
            $query->where('selected_vendor_id', $restaurantId);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('customer_phone_number')
                    ->label('Customer Phone')
                    ->searchable(),
                TextColumn::make('customerProfile.full_name')
                    ->label('Customer Name')
                    ->searchable()
                    ->default('N/A'),
                TextColumn::make('restaurant.name')
                    ->label('Last Restaurant')
                    ->default('N/A'),
                TextColumn::make('last_interacted_at')
                    ->label('Last Interaction')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated()
            ->defaultSort('last_interacted_at', 'asc');
    }
}
