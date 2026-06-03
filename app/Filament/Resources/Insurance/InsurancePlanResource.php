<?php

namespace App\Filament\Resources\Insurance;

use App\Filament\Resources\Insurance\InsurancePlanResource\Pages;
use App\Filament\Resources\Insurance\InsurancePlanResource\RelationManagers\CoverageRulesRelationManager;
use App\Models\Insurance\InsurancePlan;
use App\Models\Insurance\Insurer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InsurancePlanResource extends Resource
{
    protected static ?string $model = InsurancePlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'insurance/plans';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.insurance');
    }

    public static function getNavigationLabel(): string
    {
        return 'Plans';
    }

    public static function getModelLabel(): string
    {
        return 'Insurance Plan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Insurance Plans';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Plan')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('insurer_id')
                        ->label('Insurer')
                        ->relationship('insurer', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('tier')
                        ->options([
                            'platinum' => 'Platinum',
                            'gold' => 'Gold',
                            'silver' => 'Silver',
                            'bronze' => 'Bronze',
                        ])
                        ->nullable(),

                    Forms\Components\TextInput::make('name')
                        ->label('Name (English)')
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('name_ar')
                        ->label('Name (Arabic)')
                        ->maxLength(191),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(32)
                        ->helperText('E.g. GIG-GOLD-2026.'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\DatePicker::make('effective_from')->native(false),

                    Forms\Components\DatePicker::make('effective_until')->native(false),

                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('insurer.name')
                    ->label('Insurer')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tier')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'platinum' => 'info',
                        'gold' => 'warning',
                        'silver' => 'gray',
                        'bronze' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('coverage_rules_count')
                    ->label('Rules')
                    ->alignEnd()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('effective_from')
                    ->date('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('effective_until')
                    ->date('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('insurer_id')
                    ->label('Insurer')
                    ->options(fn () => Insurer::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),

                Tables\Filters\SelectFilter::make('tier')
                    ->options([
                        'platinum' => 'Platinum',
                        'gold' => 'Gold',
                        'silver' => 'Silver',
                        'bronze' => 'Bronze',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No plans yet')
            ->emptyStateDescription('Define the policy tiers offered by each insurer.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('insurer')
            ->withCount('coverageRules');
    }

    public static function getRelations(): array
    {
        return [
            CoverageRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
