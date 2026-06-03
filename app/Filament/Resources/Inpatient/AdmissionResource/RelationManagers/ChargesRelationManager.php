<?php

namespace App\Filament\Resources\Inpatient\AdmissionResource\RelationManagers;

use App\Models\Inpatient\AdmissionCharge;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $title = 'Bed-day charges';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        // Only manual one-off charges are added here. Bed-day charges
        // come from the nightly cron and are immutable.
        return $form->schema([
            Forms\Components\DatePicker::make('charge_date')->required()->default(today()),
            Forms\Components\TextInput::make('description')->required(),
            Forms\Components\TextInput::make('amount')->numeric()->required()->step(0.001)->prefix(config('app.currency', 'KWD')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('charge_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('charge_date')->date('M j')->sortable(),
                Tables\Columns\TextColumn::make('description')->searchable()->wrap(),
                Tables\Columns\BadgeColumn::make('source')->colors([
                    'gray' => AdmissionCharge::SOURCE_BED_DAY,
                    'warning' => AdmissionCharge::SOURCE_MANUAL,
                ]),
                Tables\Columns\TextColumn::make('amount')->money(config('app.currency', 'KWD'))->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money(config('app.currency', 'KWD'))->label('Total')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add manual charge')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['source'] = AdmissionCharge::SOURCE_MANUAL;
                        $data['created_by_user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AdmissionCharge $r) => $r->source === AdmissionCharge::SOURCE_MANUAL),
            ])
            ->bulkActions([]);
    }
}
