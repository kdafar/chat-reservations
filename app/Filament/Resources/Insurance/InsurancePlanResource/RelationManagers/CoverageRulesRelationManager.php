<?php

namespace App\Filament\Resources\Insurance\InsurancePlanResource\RelationManagers;

use App\Models\Insurance\InsuranceCoverageRule;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CoverageRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'coverageRules';

    protected static ?string $title = 'Coverage Rules';

    protected static ?string $recordTitleAttribute = 'kind';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('kind')
                ->options([
                    InsuranceCoverageRule::KIND_CONSULTATION => 'Consultation',
                    InsuranceCoverageRule::KIND_SERVICES => 'Services',
                    InsuranceCoverageRule::KIND_MEDICINES => 'Medicines',
                    InsuranceCoverageRule::KIND_OTHER => 'Other',
                ])
                ->required(),

            Forms\Components\Select::make('coverage_type')
                ->options([
                    InsuranceCoverageRule::TYPE_PERCENTAGE => 'Percentage (%)',
                    InsuranceCoverageRule::TYPE_FIXED => 'Fixed Amount',
                    InsuranceCoverageRule::TYPE_COPAY_AMOUNT => 'Patient Copay (fixed)',
                ])
                ->required(),

            Forms\Components\TextInput::make('coverage_value')
                ->label('Value')
                ->numeric()
                ->step(0.001)
                ->minValue(0)
                ->required()
                ->helperText('Percentage 0–100 OR fixed KWD amount, depending on type.'),

            Forms\Components\TextInput::make('max_per_visit')
                ->label('Max per Visit')
                ->numeric()
                ->step(0.001)
                ->minValue(0)
                ->nullable(),

            Forms\Components\TextInput::make('max_annual')
                ->label('Max Annual')
                ->numeric()
                ->step(0.001)
                ->minValue(0)
                ->nullable(),

            Forms\Components\Toggle::make('requires_preauth')
                ->label('Requires Pre-authorization')
                ->default(false),

            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        InsuranceCoverageRule::KIND_CONSULTATION => 'info',
                        InsuranceCoverageRule::KIND_SERVICES => 'success',
                        InsuranceCoverageRule::KIND_MEDICINES => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('coverage_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        InsuranceCoverageRule::TYPE_PERCENTAGE => 'Percentage',
                        InsuranceCoverageRule::TYPE_FIXED => 'Fixed',
                        InsuranceCoverageRule::TYPE_COPAY_AMOUNT => 'Copay',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('coverage_value')
                    ->label('Value')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state, InsuranceCoverageRule $r) => $r->coverage_type === InsuranceCoverageRule::TYPE_PERCENTAGE
                        ? number_format((float) $state, 2).'%'
                        : number_format((float) $state, 3)),

                Tables\Columns\TextColumn::make('max_per_visit')
                    ->label('Max / Visit')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 3) : '—'),

                Tables\Columns\TextColumn::make('max_annual')
                    ->label('Max / Year')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 3) : '—'),

                Tables\Columns\IconColumn::make('requires_preauth')
                    ->label('Pre-auth')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
