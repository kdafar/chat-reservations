<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantTableResource\Pages;
use App\Models\RestaurantTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RestaurantTableResource extends Resource
{
    protected static ?string $model = RestaurantTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    // UI rename only
    protected static ?string $navigationGroup = 'Clinic — Tools';

    protected static ?string $navigationLabel = 'Rooms';

    protected static ?string $pluralModelLabel = 'Rooms';

    protected static ?string $modelLabel = 'Room';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('branch_id')
                ->label('Clinic Branch')
                // keep relationship so Filament saves FK
                ->relationship(name: 'branch', titleAttribute: 'id')
                ->required()
                ->searchable()
                ->preload()
                ->getOptionLabelFromRecordUsing(fn (\App\Models\Branch $record) => $record->localized_name)
                ->getSearchResultsUsing(function (string $search) {
                    return \App\Models\Branch::query()
                        ->where('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")
                        ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"')) IS NULL, JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn ($b) => [$b->id => $b->localized_name])
                        ->toArray();
                })
                ->options(function () {
                    return \App\Models\Branch::query()
                        ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"')) IS NULL, JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                        ->get()
                        ->mapWithKeys(fn ($b) => [$b->id => $b->localized_name])
                        ->toArray();
                }),

            Forms\Components\TextInput::make('name')
                ->label('Room Name / Code')
                ->placeholder('R1, OPD-05, LAB-2, etc.')
                ->required()
                ->maxLength(50),

            Forms\Components\TextInput::make('capacity')
                ->label('Patient Capacity')
                ->numeric()
                ->minValue(1)
                ->maxValue(20)
                ->required(),

            Forms\Components\Select::make('status')
                ->label('Room Status')
                ->options([
                    'available' => 'Available',
                    'occupied' => 'In Use',
                    'out_of_service' => 'Maintenance',
                ])
                ->native(false)
                ->default('available')
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Clinic Branch')
                    ->formatStateUsing(fn ($state, $record) => $record->branch?->localized_name)
                    ->sortable(query: function ($query, string $direction) {
                        // Sort by branch name->en (fallback-safe)
                        $expr = "JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))";

                        return $query->whereHas('branch', fn ($q) => $q)
                            ->orderByRaw("(SELECT {$expr} FROM branches WHERE branches.id = restaurant_tables.branch_id) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'available' => 'Available',
                        'occupied' => 'In Use',
                        'out_of_service' => 'Maintenance',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'available',
                        'warning' => 'occupied',
                        'gray' => 'out_of_service',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'available',
                        'heroicon-o-user-group' => 'occupied',
                        'heroicon-o-wrench-screwdriver' => 'out_of_service',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('Y-m-d H:i')
                    ->label('Updated')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Clinic Branch')
                    ->options(fn () => \App\Models\Branch::query()
                        ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"')) IS NULL, JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                        ->get()
                        ->mapWithKeys(fn ($b) => [$b->id => $b->localized_name])
                        ->toArray()
                    ),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Available',
                        'occupied' => 'In Use',
                        'out_of_service' => 'Maintenance',
                    ]),

                Filter::make('min_capacity')
                    ->label('Capacity range')
                    ->form([
                        Forms\Components\TextInput::make('min')->numeric()->label('Min capacity'),
                        Forms\Components\TextInput::make('max')->numeric()->label('Max capacity'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['min'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', (int) $v))
                            ->when($data['max'] ?? null, fn ($q, $v) => $q->where('capacity', '<=', (int) $v));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edit'),

                Action::make('setAvailable')
                    ->label('Mark Available')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RestaurantTable $r) => $r->status !== 'available')
                    ->action(fn (RestaurantTable $r) => $r->update(['status' => 'available'])),

                Action::make('setOccupied')
                    ->label('Mark In Use')
                    ->icon('heroicon-o-user-group')
                    ->color('warning')
                    ->visible(fn (RestaurantTable $r) => $r->status !== 'occupied')
                    ->action(fn (RestaurantTable $r) => $r->update(['status' => 'occupied'])),

                Action::make('setOos')
                    ->label('Mark Maintenance')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('gray')
                    ->visible(fn (RestaurantTable $r) => $r->status !== 'out_of_service')
                    ->action(fn (RestaurantTable $r) => $r->update(['status' => 'out_of_service'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkAvailable')
                    ->label('Mark Available')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn ($records) => $records->each->update(['status' => 'available'])),

                Tables\Actions\BulkAction::make('bulkOccupied')
                    ->label('Mark In Use')
                    ->icon('heroicon-o-user-group')
                    ->color('warning')
                    ->action(fn ($records) => $records->each->update(['status' => 'occupied'])),

                Tables\Actions\BulkAction::make('bulkOos')
                    ->label('Mark Maintenance')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('gray')
                    ->action(fn ($records) => $records->each->update(['status' => 'out_of_service'])),

                Tables\Actions\DeleteBulkAction::make()->label('Delete'),
            ])
            ->defaultSort('branch_id')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No rooms yet')
            ->emptyStateDescription('Create clinic rooms to assign during appointment check-in.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestaurantTables::route('/'),
            'create' => Pages\CreateRestaurantTable::route('/create'),
            'edit' => Pages\EditRestaurantTable::route('/{record}/edit'),
        ];
    }
}
