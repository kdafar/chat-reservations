<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchBlackoutResource\Pages;
use App\Models\Branch;
use App\Models\BranchBlackout;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BranchBlackoutResource extends Resource
{
    protected static ?string $model = BranchBlackout::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    // UI rename only
    protected static ?string $navigationGroup = null;

    // nicer wording in sidebar
    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'branch-blackouts';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_scheduling');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.branch_blackout.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.branch_blackout.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.branch_blackout.label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('clinic_misc.branch_blackout.section'))
                ->description(__('clinic_misc.branch_blackout.section_desc'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label(__('clinic_misc.branch_blackout.branch'))
                        ->relationship('branch', 'id')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->getOptionLabelFromRecordUsing(fn (Branch $b) => $b->localized_name)
                        ->options(fn () => Branch::query()
                            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                            ->get()
                            ->mapWithKeys(fn ($b) => [$b->id => $b->localized_name])
                            ->toArray()
                        ),

                    Forms\Components\DatePicker::make('date')
                        ->label(__('clinic_misc.branch_blackout.date'))
                        ->required(),

                    Forms\Components\TextInput::make('reason')
                        ->label(__('clinic_misc.branch_blackout.reason'))
                        ->placeholder(__('clinic_misc.branch_blackout.reason_placeholder'))
                        ->maxLength(120),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Clinic Branch')
                    ->formatStateUsing(fn ($state, $record) => $record->branch?->localized_name)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Clinic Branch')
                    ->options(fn () => Branch::query()
                        ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                        ->get()
                        ->mapWithKeys(fn ($b) => [$b->id => $b->localized_name])
                        ->toArray()
                    ),

                Tables\Filters\Filter::make('date_range')
                    ->label('Date range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('date', '>=', $from))
                        ->when($data['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('date', '<=', $to))
                    ),

                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming closures')
                    ->query(fn (Builder $query) => $query->where('date', '>=', today())),
            ])
            ->actions([
                // If you want to keep create hidden, leave visible(false).
                Tables\Actions\CreateAction::make()
                    ->label('Add unavailable day')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(false),

                Tables\Actions\EditAction::make()->label('Edit'),

                Tables\Actions\DeleteAction::make()->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Delete selected'),
                ]),
            ])
            ->emptyStateHeading(__('resources.branch_blackout.empty_heading'))
            ->emptyStateDescription(__('resources.branch_blackout.empty_description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranchBlackouts::route('/'),
            'create' => Pages\CreateBranchBlackout::route('/create'),
            'edit' => Pages\EditBranchBlackout::route('/{record}/edit'),
        ];
    }
}
