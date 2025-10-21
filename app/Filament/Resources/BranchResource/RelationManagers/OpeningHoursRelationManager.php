<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Validation\Rules\Unique;

class OpeningHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'openingHours';

    protected static ?string $title = 'Opening Hours';

    public function form(Forms\Form $form): Forms\Form
    {
        $days = [
            0 => __('Sunday'),
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
        ];

        return $form->schema([
            Grid::make(12)->schema([
                Select::make('day_of_week')
                    ->label(__('Day'))
                    ->options($days)
                    ->required()
                    ->native(false)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule) {
                            // ensure one row per day per branch
                            return $rule->where('branch_id', $this->getOwnerRecord()->getKey());
                        }
                    )
                    ->columnSpan(3),

                TimePicker::make('opens_at')
                    ->label(__('Opens at'))
                    ->seconds(false)
                    ->visible(fn (Forms\Get $get) => ! (bool) $get('is_closed'))
                    ->columnSpan(3),

                TimePicker::make('closes_at')
                    ->label(__('Closes at'))
                    ->seconds(false)
                    ->visible(fn (Forms\Get $get) => ! (bool) $get('is_closed'))
                    ->columnSpan(3),

                Toggle::make('is_closed')
                    ->label(__('Closed'))
                    ->inline(false)
                    ->default(false)
                    ->columnSpan(3),
            ]),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        $dayName = fn (int $d) => [__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')][$d] ?? $d;

        return $table
            ->columns([
                TextColumn::make('day_of_week')
                    ->label(__('Day'))
                    ->formatStateUsing(fn ($state) => $dayName((int) $state))
                    ->sortable(),
                TextColumn::make('opens_at')->label(__('Opens'))->toggleable(),
                TextColumn::make('closes_at')->label(__('Closes'))->toggleable(),
                IconColumn::make('is_closed')->label(__('Closed'))->boolean(),
                TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->label(__('Updated')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Action::make('generateWeekTemplate')
                    ->label(__('Generate 7 Days'))
                    ->requiresConfirmation()
                    ->action(function () {
                        $branch = $this->getOwnerRecord();
                        $existing = $branch->openingHours()->pluck('day_of_week')->all();
                        $missing = collect(range(0, 6))->diff($existing);
                        foreach ($missing as $d) {
                            $branch->openingHours()->create([
                                'day_of_week' => $d,
                                'opens_at' => '09:00:00',
                                'closes_at' => '23:00:00',
                                'is_closed' => false,
                            ]);
                        }

                        // 👇 2. Replace $this->notify() with the Notification builder
                        Notification::make()
                            ->title(__('Week template generated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
