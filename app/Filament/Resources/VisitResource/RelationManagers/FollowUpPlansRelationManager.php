<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\Booking;
use App\Models\FollowUpPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class FollowUpPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'followUpPlans';

    protected static ?string $title = 'Follow-up Plans';

    protected static function hasCol(string $col): bool
    {
        static $cache = [];

        $table = (new FollowUpPlan)->getTable();
        $key = $table.':'.$col;

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasColumn($table, $col);
        }

        return (bool) $cache[$key];
    }

    public function form(Form $form): Form
    {
        // Read-only: created by service hook from Visit.
        return $form->schema([
            Forms\Components\DateTimePicker::make('suggested_at')
                ->label('Suggested At')
                ->seconds(false)
                ->disabled(),

            Forms\Components\Toggle::make('auto_create_booking')
                ->label('Auto-create Booking')
                ->disabled(),

            Forms\Components\TextInput::make('booking_id')
                ->label('Created Booking ID')
                ->disabled()
                ->visible(fn () => static::hasCol('booking_id')),

            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(4)
                ->disabled()
                ->visible(fn () => static::hasCol('notes')),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('suggested_at')
                    ->label('Suggested')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),

                Tables\Columns\IconColumn::make('auto_create_booking')
                    ->label('Auto Booking')
                    ->boolean(),

                Tables\Columns\TextColumn::make('booking_id')
                    ->label('Booking')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }

                        $b = Booking::query()->find($state);

                        return $b?->booking_code ?? ('Booking #'.$state);
                    })
                    ->visible(fn () => static::hasCol('booking_id'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d h:i A')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->headerActions([]) // no manual creation
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }
}
