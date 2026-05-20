<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\Visit;
use App\Models\VisitPayment;
use App\Services\Clinic\VisitCostingService;
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
                            try {
                                return app(VisitCostingService::class)->getRemainingBalance($visit);
                            } catch (\Throwable $e) {
                                return 0;
                            }
                        })
                        ->disabled() // "So the doctor don't lie" - User Request
                        ->dehydrated() // CRITICAL: Ensures the disabled value is saved
                        ->helperText('Auto-calculated based on Doctor Fee + Items - Paid Amount.'),
                    // -----------------------------------------------------

                    // FIX: Dynamic Payment Methods (Matches BookingResource logic)
                    Forms\Components\Select::make('method')
                        ->label('Method')
                        ->options(function (RelationManager $livewire) {
                            $visit = $livewire->getOwnerRecord();

                            // 1. Standard Manual / POS Options (Always Available)
                            $options = [
                                'cash' => 'Cash',
                                'knet' => 'KNET (POS)',
                                'visa' => 'Credit Card (POS)',
                                'link' => 'Payment Link (Manual)',
                                'transfer' => 'Bank Transfer',
                                'insurance' => 'Insurance',
                            ];

                            // 2. Fetch Dynamic Configurations
                            if ($visit instanceof Visit && $visit->branch_id) {
                                $branchId = $visit->branch_id;
                                // Load partner via relation if possible, else query
                                $partnerId = $visit->branch?->partner_id
                                    ?? \App\Models\Branch::find($branchId)?->partner_id;

                                // We search for System, Partner, or Branch accounts
                                $accounts = \App\Models\GatewayAccount::query()
                                    ->with('gateway')
                                    ->where('is_active', true)
                                    ->where(function ($q) use ($branchId, $partnerId) {
                                        $q->where(function ($sq) use ($branchId) {
                                            $sq->where('owner_type', 'branch')->where('branch_id', $branchId);
                                        })
                                            ->orWhere(function ($sq) use ($partnerId) {
                                                if ($partnerId) {
                                                    $sq->where('owner_type', 'partner')->where('partner_id', $partnerId);
                                                }
                                            })
                                            ->orWhere('owner_type', 'system');
                                    })
                                    ->get();

                                foreach ($accounts as $acc) {
                                    $key = $acc->gateway?->driver ?? 'other';
                                    // Don't duplicate keys we already hardcoded
                                    if (in_array($key, ['cash', 'knet', 'visa', 'link'])) {
                                        continue;
                                    }

                                    $label = $acc->display_name ?: ($acc->gateway?->name ?? ucfirst($key));
                                    $options[$key] = $label;
                                }
                            }

                            return $options;
                        })
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

                    // NEW: Kind/Type Field (Required by user)
                    Forms\Components\Select::make('kind')
                        ->label('Type')
                        ->options([
                            'consultation' => 'Consultation',
                            'services' => 'Services',
                            'medicines' => 'Medicines',
                            'other' => 'Other',
                        ])
                        ->default('consultation')
                        ->required(),

                    Forms\Components\TextInput::make('reference_no')
                        ->label('Reference No.')
                        ->maxLength(191)
                        ->nullable()
                        ->columnSpan(1),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Paid At')
                        ->seconds(false)
                        ->helperText('If empty, system will set it automatically.')
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

                Tables\Columns\TextColumn::make('kind')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '-')
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
                // FIX: View action is always visible so staff can see details even if editing is locked
                Tables\Actions\ViewAction::make(),

                // FIX: Add Print Action for Staff
                Tables\Actions\Action::make('print_receipt')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(function (VisitPayment $record) {
                        // Ensure we have a booking to link to (Legacy safety)
                        $bookingId = $record->visit->booking_id ?? null;
                        if (! $bookingId) {
                            return null;
                        }

                        return route('bookings.receipt.show', [
                            'booking' => $bookingId,
                            'payment_id' => $record->id,
                        ]);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (VisitPayment $record) => ($record->status ?? null) === 'paid' && ! empty($record->visit->booking_id)),

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    // FIX: Prevent editing online payments (link, myfatoorah, etc) to avoid corruption
                    ->visible(fn (VisitPayment $record) => ! in_array($record->method, ['link', 'myfatoorah', 'tap', 'stripe']))
                    ->mutateFormDataUsing(function (array $data, VisitPayment $record): array {
                        // If switching to paid and paid_at is empty, set it.
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
                    // FIX: Restrict to Admin ID 1 ONLY
                    ->visible(fn (VisitPayment $record) => auth()->id() === 1 && ($record->status ?? null) === 'paid')
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        $record->update(['status' => 'refunded']);
                        Notification::make()->title('Payment marked as refunded')->success()->send();
                    }),

                Tables\Actions\Action::make('markVoid')
                    ->label('Void')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    // FIX: Restrict to Admin ID 1 ONLY
                    ->visible(fn (VisitPayment $record) => auth()->id() === 1 && ($record->status ?? null) !== 'void')
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        $record->update(['status' => 'void']);
                        Notification::make()->title('Payment voided')->success()->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    // FIX: Restrict to Admin ID 1 AND prevent deleting online payments
                    ->visible(fn (VisitPayment $record) => auth()->id() === 1 && ! in_array($record->method, ['link', 'myfatoorah', 'tap', 'stripe']))
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        $record->delete();
                        Notification::make()->title('Payment removed (soft deleted)')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkVoid')
                    ->label('Void selected')
                    ->icon('heroicon-o-no-symbol')
                    // FIX: Restrict Bulk actions to Admin ID 1
                    ->visible(fn () => auth()->id() === 1)
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['status' => 'void']);
                        }
                        Notification::make()->title('Selected payments voided')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('id', 'desc');
    }
}
