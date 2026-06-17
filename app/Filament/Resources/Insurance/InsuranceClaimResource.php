<?php

namespace App\Filament\Resources\Insurance;

use App\Filament\Resources\Insurance\InsuranceClaimResource\Pages;
use App\Filament\Resources\Insurance\InsuranceClaimResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Insurance\InsuranceClaimResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Insurance\InsuranceClaimResource\RelationManagers\StateLogRelationManager;
use App\Models\Accounting\Account;
use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Services\Insurance\ClaimStateMachine;
use App\Services\Insurance\InsuranceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InsuranceClaimResource extends Resource
{
    protected static ?string $model = InsuranceClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'insurance/claims';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.insurance');
    }

    public static function getNavigationLabel(): string
    {
        return 'Claims';
    }

    public static function getModelLabel(): string
    {
        return 'Claim';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Claims';
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            InsuranceClaim::STATUS_DRAFT => 'Draft',
            InsuranceClaim::STATUS_SUBMITTED => 'Submitted',
            InsuranceClaim::STATUS_UNDER_REVIEW => 'Under Review',
            InsuranceClaim::STATUS_APPROVED => 'Approved',
            InsuranceClaim::STATUS_PARTIALLY_APPROVED => 'Partially Approved',
            InsuranceClaim::STATUS_REJECTED => 'Rejected',
            InsuranceClaim::STATUS_PAID => 'Paid',
            InsuranceClaim::STATUS_VOID => 'Void',
        ];
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            InsuranceClaim::STATUS_DRAFT => 'gray',
            InsuranceClaim::STATUS_SUBMITTED => 'info',
            InsuranceClaim::STATUS_UNDER_REVIEW => 'warning',
            InsuranceClaim::STATUS_APPROVED => 'success',
            InsuranceClaim::STATUS_PARTIALLY_APPROVED => 'warning',
            InsuranceClaim::STATUS_REJECTED => 'danger',
            InsuranceClaim::STATUS_PAID => 'success',
            InsuranceClaim::STATUS_VOID => 'gray',
            default => 'gray',
        };
    }

    /** @return array<int, string> */
    protected static function bankAndCashAccountOptions(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('code', ['1110', '1120', '1130'])
                    ->orWhere('code', 'like', '1110-%')
                    ->orWhere('code', 'like', '1120-%');
            })
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Claim')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('claim_number')
                        ->label('Claim #')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Auto-generated'),

                    Forms\Components\Select::make('status')
                        ->options(self::statusOptions())
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Status is changed via row actions on the list.'),

                    Forms\Components\Select::make('visit_id')
                        ->label('Visit')
                        ->relationship('visit', 'id')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('patient_policy_id')
                        ->label('Patient Policy')
                        ->relationship('patientPolicy', 'policy_number')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('preauth_id')
                        ->label('Pre-authorization')
                        ->relationship('preauthorization', 'reference_no')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->relationship('branch', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\TextInput::make('reference_no')
                        ->label('Insurer Ref. No.')
                        ->maxLength(64),
                ]),

            Forms\Components\Section::make('Amounts (KWD)')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('total_charged')
                        ->label('Total Charged')
                        ->numeric()
                        ->step(0.001)
                        ->minValue(0)
                        ->prefix('KWD'),

                    Forms\Components\TextInput::make('patient_copay')
                        ->label('Patient Copay')
                        ->numeric()
                        ->step(0.001)
                        ->minValue(0)
                        ->prefix('KWD'),

                    Forms\Components\TextInput::make('insurer_payable')
                        ->label('Insurer Payable')
                        ->numeric()
                        ->step(0.001)
                        ->minValue(0)
                        ->prefix('KWD'),

                    Forms\Components\TextInput::make('approved_amount')
                        ->label('Approved')
                        ->numeric()
                        ->step(0.001)
                        ->minValue(0)
                        ->prefix('KWD'),

                    Forms\Components\TextInput::make('rejected_amount')
                        ->label('Rejected')
                        ->numeric()
                        ->step(0.001)
                        ->minValue(0)
                        ->prefix('KWD'),

                    Forms\Components\TextInput::make('paid_amount')
                        ->label('Paid')
                        ->numeric()
                        ->step(0.001)
                        ->disabled()
                        ->dehydrated(false)
                        ->prefix('KWD')
                        ->helperText('Computed from payments.'),

                    Forms\Components\TextInput::make('write_off_amount')
                        ->label('Write-off')
                        ->numeric()
                        ->step(0.001)
                        ->disabled()
                        ->dehydrated(false)
                        ->prefix('KWD')
                        ->helperText('Computed from write-off actions.'),
                ]),

            Forms\Components\Section::make('Documents')
                ->columns(2)
                ->schema([
                    Forms\Components\FileUpload::make('eob_path')
                        ->label('EOB (Explanation of Benefits)')
                        ->disk('public')
                        ->directory('insurance/eob')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('decision_notes')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('claim_number')
                    ->label('Claim #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('visit.id')
                    ->label('Visit')
                    ->url(fn (InsuranceClaim $r) => $r->visit_id
                        ? route('filament.admin.resources.visits.edit', ['record' => $r->visit_id])
                        : null)
                    ->formatStateUsing(fn ($state) => $state ? '#'.$state : '—')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('patientPolicy.patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('patientPolicy.insurer.name')
                    ->label('Insurer')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_charged')
                    ->label('Charged')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->sortable(),

                Tables\Columns\TextColumn::make('insurer_payable')
                    ->label('Payable')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Balance')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->getStateUsing(fn (InsuranceClaim $r) => number_format($r->balanceDue(), 3))
                    ->color(fn (InsuranceClaim $r) => $r->balanceDue() > 0.001 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => self::statusColor($state))
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options(self::statusOptions()),

                Tables\Filters\Filter::make('submitted_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Submitted From'),
                        Forms\Components\DatePicker::make('to')->label('Submitted To'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('submitted_at', '>=', $d))
                        ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('submitted_at', '<=', $d))),

                Tables\Filters\SelectFilter::make('insurer_id')
                    ->label('Insurer')
                    ->options(fn () => Insurer::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(function (Builder $q, array $data) {
                        $insurerId = $data['value'] ?? null;
                        if (! $insurerId) {
                            return $q;
                        }

                        $policyIds = PatientInsurancePolicy::query()
                            ->where('insurer_id', $insurerId)
                            ->pluck('id');

                        return $q->whereIn('patient_policy_id', $policyIds);
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (InsuranceClaim $r) => $r->status === InsuranceClaim::STATUS_DRAFT),

                    Tables\Actions\Action::make('submit')
                        ->label('Submit')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->visible(fn (InsuranceClaim $r) => self::canTransitionTo($r, InsuranceClaim::STATUS_SUBMITTED)
                            && (auth()->user()?->can('insurance_submit_claim') ?? true))
                        ->form([
                            Forms\Components\Textarea::make('notes')->rows(2),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            self::runTransition($r, InsuranceClaim::STATUS_SUBMITTED, $data['notes'] ?? null);
                        }),

                    Tables\Actions\Action::make('markUnderReview')
                        ->label('Mark Under Review')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (InsuranceClaim $r) => self::canTransitionTo($r, InsuranceClaim::STATUS_UNDER_REVIEW)
                            && (auth()->user()?->can('insurance_decide_claim') ?? true))
                        ->form([
                            Forms\Components\Textarea::make('notes')->rows(2),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            self::runTransition($r, InsuranceClaim::STATUS_UNDER_REVIEW, $data['notes'] ?? null);
                        }),

                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (InsuranceClaim $r) => self::canTransitionTo($r, InsuranceClaim::STATUS_APPROVED)
                            && (auth()->user()?->can('insurance_decide_claim') ?? true))
                        ->form([
                            Forms\Components\TextInput::make('approved_amount')
                                ->label('Approved Amount (KWD)')
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0)
                                ->required()
                                ->prefix('KWD'),

                            Forms\Components\TextInput::make('reference_no')
                                ->label('Insurer Ref. No.')
                                ->maxLength(64),

                            Forms\Components\Textarea::make('decision_notes')
                                ->rows(3)
                                ->maxLength(2000),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            self::runTransition($r, InsuranceClaim::STATUS_APPROVED, $data['decision_notes'] ?? null, [
                                'approved_amount' => $data['approved_amount'],
                                'reference_no' => $data['reference_no'] ?? null,
                                'decision_notes' => $data['decision_notes'] ?? null,
                            ]);
                        }),

                    Tables\Actions\Action::make('partiallyApprove')
                        ->label('Partially Approve')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->color('warning')
                        ->visible(fn (InsuranceClaim $r) => self::canTransitionTo($r, InsuranceClaim::STATUS_PARTIALLY_APPROVED)
                            && (auth()->user()?->can('insurance_decide_claim') ?? true))
                        ->form([
                            Forms\Components\TextInput::make('approved_amount')
                                ->label('Approved Amount (KWD)')
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0)
                                ->required()
                                ->prefix('KWD'),

                            Forms\Components\TextInput::make('rejected_amount')
                                ->label('Rejected Amount (KWD)')
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0)
                                ->required()
                                ->prefix('KWD'),

                            Forms\Components\Textarea::make('decision_notes')
                                ->rows(3)
                                ->maxLength(2000),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            self::runTransition($r, InsuranceClaim::STATUS_PARTIALLY_APPROVED, $data['decision_notes'] ?? null, [
                                'approved_amount' => $data['approved_amount'],
                                'rejected_amount' => $data['rejected_amount'],
                                'decision_notes' => $data['decision_notes'] ?? null,
                            ]);
                        }),

                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (InsuranceClaim $r) => self::canTransitionTo($r, InsuranceClaim::STATUS_REJECTED)
                            && (auth()->user()?->can('insurance_decide_claim') ?? true))
                        ->form([
                            Forms\Components\Textarea::make('decision_notes')
                                ->label('Reason')
                                ->rows(3)
                                ->required()
                                ->maxLength(2000),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            self::runTransition($r, InsuranceClaim::STATUS_REJECTED, $data['decision_notes'], [
                                'decision_notes' => $data['decision_notes'],
                            ]);
                        }),

                    Tables\Actions\Action::make('recordPayment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (InsuranceClaim $r) => in_array($r->status, [
                            InsuranceClaim::STATUS_APPROVED,
                            InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                        ], true)
                            && (auth()->user()?->can('insurance_record_payment') ?? true))
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount (KWD)')
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0.001)
                                ->required()
                                ->prefix('KWD'),

                            Forms\Components\Select::make('method')
                                ->options([
                                    'cheque' => 'Cheque',
                                    'transfer' => 'Bank Transfer',
                                    'cash' => 'Cash',
                                ])
                                ->default('transfer')
                                ->required(),

                            Forms\Components\TextInput::make('reference_no')
                                ->label('Reference No.')
                                ->maxLength(64),

                            Forms\Components\Select::make('deposited_to_account_id')
                                ->label('Deposited To')
                                ->options(fn () => self::bankAndCashAccountOptions())
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            $user = auth()->user();
                            if (! $user) {
                                Notification::make()->title('Not authenticated')->danger()->send();

                                return;
                            }

                            try {
                                $payment = app(InsuranceService::class)->recordInsurerPayment(
                                    $r,
                                    (float) $data['amount'],
                                    $data['method'],
                                    $data['reference_no'] ?? null,
                                    $data['deposited_to_account_id'] ?? null,
                                    $user,
                                );

                                Notification::make()
                                    ->title('Payment recorded')
                                    ->body('KWD '.number_format((float) $payment->amount, 3).' applied to '.$r->claim_number)
                                    ->success()->send();
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()->title('Invalid transition')->body($e->getMessage())->danger()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('writeOff')
                        ->label('Write Off')
                        ->icon('heroicon-o-receipt-refund')
                        ->color('danger')
                        ->visible(fn (InsuranceClaim $r) => in_array($r->status, [
                            InsuranceClaim::STATUS_APPROVED,
                            InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                            InsuranceClaim::STATUS_REJECTED,
                        ], true)
                            && (auth()->user()?->can('insurance_writeoff') ?? true))
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount (KWD)')
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0.001)
                                ->required()
                                ->prefix('KWD'),

                            Forms\Components\Textarea::make('reason')
                                ->rows(3)
                                ->required()
                                ->maxLength(2000),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            $user = auth()->user();
                            if (! $user) {
                                Notification::make()->title('Not authenticated')->danger()->send();

                                return;
                            }

                            try {
                                app(InsuranceService::class)->writeOff(
                                    $r,
                                    (float) $data['amount'],
                                    $data['reason'],
                                    $user,
                                );

                                Notification::make()->title('Write-off recorded')->success()->send();
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()->title('Invalid')->body($e->getMessage())->danger()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('void')
                        ->label('Void')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (InsuranceClaim $r) => $r->status !== InsuranceClaim::STATUS_VOID
                            && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->rows(3)
                                ->required()
                                ->maxLength(2000),
                        ])
                        ->action(function (InsuranceClaim $r, array $data) {
                            self::runTransition($r, InsuranceClaim::STATUS_VOID, $data['reason']);
                        }),
                ]),
            ])
            ->emptyStateHeading('No claims yet')
            ->emptyStateDescription('Claims are seeded from visits via the insurance service.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    /**
     * Check the state machine to determine if a transition is allowed.
     */
    protected static function canTransitionTo(InsuranceClaim $claim, string $toStatus): bool
    {
        return app(ClaimStateMachine::class)->canTransition($claim->status, $toStatus);
    }

    /**
     * Run a state transition via the InsuranceService, catching expected errors
     * and surfacing them as Filament notifications.
     */
    protected static function runTransition(InsuranceClaim $claim, string $toStatus, ?string $notes = null, array $payload = []): void
    {
        $user = auth()->user();
        if (! $user) {
            Notification::make()->title('Not authenticated')->danger()->send();

            return;
        }

        try {
            $fresh = app(InsuranceService::class)->transition($claim, $toStatus, $user, $notes, $payload);
            Notification::make()
                ->title('Claim updated')
                ->body($fresh->claim_number.' → '.($toStatus))
                ->success()->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title('Invalid transition')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'visit',
            'patientPolicy.patient',
            'patientPolicy.insurer',
            'patientPolicy.plan',
            'branch',
        ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            PaymentsRelationManager::class,
            StateLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClaims::route('/'),
            'create' => Pages\CreateClaim::route('/create'),
            'view' => Pages\ViewClaim::route('/{record}'),
            'edit' => Pages\EditClaim::route('/{record}/edit'),
        ];
    }
}
