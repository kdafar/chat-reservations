<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\Visit; // Import Visit Model
use App\Models\VisitPayment;
use App\Services\Clinic\VisitCostingService; // Import the service
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitPaymentsRelationManager extends RelationManager
{
    /**
     * IMPORTANT:
     * Your Visit model MUST have:
     * public function payments() { return $this->hasMany(VisitPayment::class, 'visit_id'); }
     */
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    protected static ?string $recordTitleAttribute = 'method';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Payment')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->step('0.001')
                        ->required()
                        // -----------------------------------------------------
                        // LEGACY UPDATE: Smart Calculation & Lock
                        // -----------------------------------------------------
                        ->default(function (RelationManager $livewire) {
                            /** @var Visit $visit */
                            $visit = $livewire->getOwnerRecord();

                            // Defensive: If no visit found, return 0
                            if (! $visit) {
                                return 0;
                            }

                            // Use the Service to ensure logic matches "Recompute Financials"
                            // We calculate (Fees + Items) - (Already Paid)
                            try {
                                return app(VisitCostingService::class)->getRemainingBalance($visit);
                            } catch (\Throwable $e) {
                                // Fallback if service fails, prevents form crash
                                return 0;
                            }
                        })
                        ->disabled() // "So the doctor don't lie" - User Request
                        ->dehydrated() // CRITICAL: Ensures the disabled value is still saved to DB
                        ->helperText('Auto-calculated based on Doctor Fee + Items - Paid Amount.'),
                    // -----------------------------------------------------

                    Forms\Components\Select::make('method')
                        ->label('Method')
                        ->options([
                            'cash' => 'Cash',
                            'knet' => 'KNET',
                            'card' => 'Card',
                            'transfer' => 'Bank Transfer',
                            'link' => 'Payment Link',
                            'insurance' => 'Insurance',
                        ])
                        ->native(false)
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'paid' => 'Paid',
                            'pending' => 'Pending',
                            'refunded' => 'Refunded',
                            'void' => 'Void',
                        ])
                        ->native(false)
                        ->default('paid')
                        ->required(),

                    Forms\Components\TextInput::make('reference_no')
                        ->label('Reference No.')
                        ->maxLength(191)
                        ->nullable()
                        ->columnSpan(2),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Paid At')
                        ->seconds(false)
                        ->helperText('If empty, system will set it automatically when status is Paid.')
                        ->nullable(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutTrashed())
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'refunded' => 'info',
                        'void' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Reference')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('collectedBy.name')
                    ->label('Collected By')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d h:i A')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Payment')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Snapshot collector and paid_at safely
                        $data['collected_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;

                        if (($data['status'] ?? 'paid') === 'paid' && empty($data['paid_at'])) {
                            $data['paid_at'] = now();
                        }

                        return $data;
                    })
                    ->after(function () {
                        Notification::make()
                            ->title('Payment recorded')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->mutateFormDataUsing(function (array $data, VisitPayment $record): array {
                        /**
                         * Audit-safe behavior:
                         * If it was already paid, do not auto-overwrite paid_at.
                         * If switching to paid and paid_at is empty, set it.
                         */
                        if (($data['status'] ?? null) === 'paid' && empty($data['paid_at']) && empty($record->paid_at)) {
                            $data['paid_at'] = now();
                        }

                        // Keep original collector if already set; otherwise set now.
                        if (empty($record->collected_by_user_id)) {
                            $data['collected_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
                        } else {
                            $data['collected_by_user_id'] = $record->collected_by_user_id;
                        }

                        return $data;
                    }),

                Tables\Actions\Action::make('markRefunded')
                    ->label('Mark Refunded')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->visible(fn (VisitPayment $record) => ($record->status ?? null) === 'paid')
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        $record->update(['status' => 'refunded']);

                        Notification::make()
                            ->title('Payment marked as refunded')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('markVoid')
                    ->label('Void')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->visible(fn (VisitPayment $record) => ($record->status ?? null) !== 'void')
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        $record->update(['status' => 'void']);

                        Notification::make()
                            ->title('Payment voided')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        // Soft delete only
                        $record->delete();

                        Notification::make()
                            ->title('Payment removed (soft deleted)')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkVoid')
                    ->label('Void selected')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            /** @var VisitPayment $record */
                            $record->update(['status' => 'void']);
                        }

                        Notification::make()
                            ->title('Selected payments voided')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('id', 'desc');
    }
}
