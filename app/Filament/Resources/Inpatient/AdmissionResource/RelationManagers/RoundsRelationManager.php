<?php

namespace App\Filament\Resources\Inpatient\AdmissionResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RoundsRelationManager extends RelationManager
{
    protected static string $relationship = 'rounds';

    protected static ?string $title = 'Daily rounds';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('doctor_id')
                ->relationship('doctor', 'name')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\DatePicker::make('round_date')->default(today())->required(),
            Forms\Components\KeyValue::make('vitals')
                ->keyLabel('Vital')
                ->valueLabel('Reading')
                ->reorderable()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('progress_notes')->rows(3)->columnSpanFull(),
            Forms\Components\Textarea::make('med_changes')->rows(2)->columnSpanFull(),
            Forms\Components\Textarea::make('next_steps')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('round_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('round_date')->date('M j')->sortable(),
                Tables\Columns\TextColumn::make('doctor.name')->sortable(),
                Tables\Columns\TextColumn::make('progress_notes')->wrap()->limit(60),
                Tables\Columns\TextColumn::make('med_changes')->wrap()->limit(40)->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Log round')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by_user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
