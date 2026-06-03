<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Services\Clinic\VisitCostingService;
use App\Services\Insurance\InsuranceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

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
                        ->minValue(0.001)
                        ->rule('gt:0')
                        ->default(function (RelationManager $livewire) {
                            $visit = $livewire->getOwnerRecord();
                            if (! $visit instanceof Visit) {
                                return null;
                            }

                            try {
                                $bal = app(VisitCostingService::class)->getRemainingBalance($visit);

                                return $bal > 0 ? $bal : null;
                            } catch (\Throwable $e) {
                                // Don't silently default to 0 — that would create a 0 KD
                                // payment that "looks paid". Let the field be empty so
                                // required+gt:0 force the user to enter the real amount.
                                report($e);

                                return null;
                            }
                        })
                        ->disabled() // "So the doctor don't lie" - User Request
                        ->dehydrated()
                        ->helperText('Auto-calculated as fees + packages + items − discount − already paid.'),
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
                    ->after(function (RelationManager $livewire) {
                        // Sync visit snapshot + doctor comp ledger after the new
                        // payment row is in the DB. Matches the other Collect
                        // Payment actions (#27 drift fix).
                        $visit = $livewire->getOwnerRecord();
                        if ($visit instanceof Visit) {
                            app(VisitCostingService::class)
                                ->compute($visit, (int) (auth()->id() ?? 0));
                        }

                        Notification::make()
                            ->title('Payment recorded')
                            ->success()
                            ->send();
                    }),

                // -----------------------------------------------------------------
                // Apply Insurance — integration point with the Insurance module.
                // Auto-creates VisitPayment rows for the insurer's portion across
                // covered kinds, then (optionally) drafts a claim. Spec'd to be
                // additive only — does not touch the existing Add Payment flow.
                // -----------------------------------------------------------------
                Tables\Actions\Action::make('applyInsurance')
                    ->label('Apply Insurance')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->modalHeading('Apply Insurance Coverage')
                    ->modalSubmitActionLabel('Apply')
                    ->modalWidth('4xl')
                    ->visible(function (RelationManager $livewire): bool {
                        if (! (auth()->user()?->can('insurance_manage_policies') ?? false)) {
                            return false;
                        }

                        $visit = $livewire->getOwnerRecord();
                        if (! $visit instanceof Visit) {
                            return false;
                        }

                        return ! empty($visit->patient_id);
                    })
                    ->disabled(function (RelationManager $livewire): bool {
                        $visit = $livewire->getOwnerRecord();
                        if (! $visit instanceof Visit || empty($visit->patient_id)) {
                            return true;
                        }

                        return PatientInsurancePolicy::query()
                            ->where('patient_id', $visit->patient_id)
                            ->active()
                            ->count() === 0;
                    })
                    ->tooltip(function (RelationManager $livewire): ?string {
                        $visit = $livewire->getOwnerRecord();
                        if (! $visit instanceof Visit || empty($visit->patient_id)) {
                            return null;
                        }

                        $count = PatientInsurancePolicy::query()
                            ->where('patient_id', $visit->patient_id)
                            ->active()
                            ->count();

                        return $count === 0 ? 'No active policies on file' : null;
                    })
                    ->form(function (RelationManager $livewire): array {
                        $visit = $livewire->getOwnerRecord();
                        $estimate = ['by_kind' => [], 'totals' => ['gross' => 0, 'patient_total' => 0, 'insurer_total' => 0]];
                        $policies = collect();

                        if ($visit instanceof Visit && $visit->patient_id) {
                            $policies = PatientInsurancePolicy::query()
                                ->where('patient_id', $visit->patient_id)
                                ->active()
                                ->with(['insurer', 'plan'])
                                ->orderBy('priority')
                                ->get();

                            try {
                                $estimate = app(InsuranceService::class)->estimateForVisit($visit);
                            } catch (\Throwable $e) {
                                Log::error('[VisitPaymentsRelationManager.applyInsurance] estimate failed', [
                                    'visit_id' => $visit->id,
                                    'msg' => $e->getMessage(),
                                ]);
                            }
                        }

                        // Build the read-only summary HTML inline so we don't
                        // have to ship a new Blade view for this first pass.
                        $policiesHtml = '<div class="text-sm">';
                        if ($policies->isEmpty()) {
                            $policiesHtml .= '<em>No active policies.</em>';
                        } else {
                            $policiesHtml .= '<table class="w-full text-left text-xs"><thead><tr>'
                                .'<th class="py-1 pr-3">Priority</th>'
                                .'<th class="py-1 pr-3">Insurer</th>'
                                .'<th class="py-1 pr-3">Plan</th>'
                                .'<th class="py-1 pr-3">Policy #</th>'
                                .'</tr></thead><tbody>';
                            foreach ($policies as $p) {
                                $policiesHtml .= '<tr>'
                                    .'<td class="py-1 pr-3">'.e((string) ($p->priority ?? '-')).'</td>'
                                    .'<td class="py-1 pr-3">'.e($p->insurer?->name ?? '-').'</td>'
                                    .'<td class="py-1 pr-3">'.e($p->plan?->name ?? '-').'</td>'
                                    .'<td class="py-1 pr-3">'.e((string) ($p->policy_number ?? '-')).'</td>'
                                    .'</tr>';
                            }
                            $policiesHtml .= '</tbody></table>';
                        }
                        $policiesHtml .= '</div>';

                        $byKind = $estimate['by_kind'] ?? [];
                        $coverageHtml = '<div class="text-sm">';
                        if (empty($byKind)) {
                            $coverageHtml .= '<em>No coverage estimate available.</em>';
                        } else {
                            $coverageHtml .= '<table class="w-full text-left text-xs"><thead><tr>'
                                .'<th class="py-1 pr-3">Kind</th>'
                                .'<th class="py-1 pr-3">Gross</th>'
                                .'<th class="py-1 pr-3">Insurer Portion</th>'
                                .'<th class="py-1 pr-3">Patient Copay</th>'
                                .'</tr></thead><tbody>';
                            foreach ($byKind as $kind => $bucket) {
                                $gross = (float) ($bucket['gross'] ?? 0);
                                $insurer = round((float) array_sum(array_column($bucket['insurer_portions'] ?? [], 'amount')), 3);
                                $copay = (float) ($bucket['patient_copay'] ?? 0);
                                $coverageHtml .= '<tr>'
                                    .'<td class="py-1 pr-3">'.e(ucfirst((string) $kind)).'</td>'
                                    .'<td class="py-1 pr-3">'.number_format($gross, 3).'</td>'
                                    .'<td class="py-1 pr-3">'.number_format($insurer, 3).'</td>'
                                    .'<td class="py-1 pr-3">'.number_format($copay, 3).'</td>'
                                    .'</tr>';
                            }
                            $totals = $estimate['totals'] ?? [];
                            $coverageHtml .= '<tr class="font-semibold border-t">'
                                .'<td class="py-1 pr-3">Total</td>'
                                .'<td class="py-1 pr-3">'.number_format((float) ($totals['gross'] ?? 0), 3).'</td>'
                                .'<td class="py-1 pr-3">'.number_format((float) ($totals['insurer_total'] ?? 0), 3).'</td>'
                                .'<td class="py-1 pr-3">'.number_format((float) ($totals['patient_total'] ?? 0), 3).'</td>'
                                .'</tr>';
                            $coverageHtml .= '</tbody></table>';
                        }
                        $coverageHtml .= '</div>';

                        // Only offer kinds where the insurer actually pays >0.
                        $checkboxOptions = [];
                        $defaultKinds = [];
                        foreach ($byKind as $kind => $bucket) {
                            $insurer = round((float) array_sum(array_column($bucket['insurer_portions'] ?? [], 'amount')), 3);
                            if ($insurer > 0) {
                                $checkboxOptions[$kind] = ucfirst((string) $kind).' ('.number_format($insurer, 3).' KWD)';
                                $defaultKinds[] = $kind;
                            }
                        }

                        return [
                            Forms\Components\Section::make('Patient Policies')
                                ->schema([
                                    Forms\Components\Placeholder::make('policies_summary')
                                        ->label('')
                                        ->content(new HtmlString($policiesHtml)),
                                ]),
                            Forms\Components\Section::make('Coverage Estimate')
                                ->schema([
                                    Forms\Components\Placeholder::make('coverage_summary')
                                        ->label('')
                                        ->content(new HtmlString($coverageHtml)),
                                ]),
                            Forms\Components\Section::make('Confirm Split')
                                ->schema([
                                    Forms\Components\CheckboxList::make('apply_kinds')
                                        ->label('Apply insurer payment for these kinds')
                                        ->options($checkboxOptions)
                                        ->default($defaultKinds)
                                        ->columns(2)
                                        ->helperText(empty($checkboxOptions)
                                            ? 'No kinds have an insurer portion > 0.'
                                            : 'Uncheck any kind you do not want to record an insurer payment for.'),
                                    Forms\Components\Toggle::make('create_claim')
                                        ->label('Also create a draft claim')
                                        ->default(true),
                                    Forms\Components\Textarea::make('notes')
                                        ->label('Notes (optional)')
                                        ->rows(2)
                                        ->nullable(),
                                ]),
                        ];
                    })
                    ->action(function (array $data, RelationManager $livewire) {
                        try {
                            $visit = $livewire->getOwnerRecord();
                            if (! $visit instanceof Visit) {
                                Notification::make()->title('Visit not found.')->danger()->send();

                                return;
                            }

                            $user = auth()->user();
                            $service = app(InsuranceService::class);
                            $estimate = $service->estimateForVisit($visit);
                            $primary = $service->primaryPolicyFor($visit->patient);

                            if (! $primary) {
                                Notification::make()->title('No active primary policy.')->danger()->send();

                                return;
                            }

                            $byKind = $estimate['by_kind'] ?? [];
                            $kindsToApply = $data['apply_kinds'] ?? array_keys($byKind);
                            $createdPayments = 0;

                            DB::transaction(function () use ($visit, $byKind, $kindsToApply, $user, $service, $primary, $data, &$createdPayments) {
                                foreach ($kindsToApply as $kind) {
                                    $bucket = $byKind[$kind] ?? null;
                                    if (! $bucket) {
                                        continue;
                                    }
                                    $insurerAmount = array_sum(array_column($bucket['insurer_portions'] ?? [], 'amount'));
                                    $insurerAmount = round((float) $insurerAmount, 3);
                                    if ($insurerAmount <= 0) {
                                        continue;
                                    }

                                    // Skip if a VisitPayment already exists for
                                    // (visit, kind, method=insurance, status in paid/pending)
                                    $exists = VisitPayment::query()
                                        ->where('visit_id', $visit->id)
                                        ->where('kind', $kind)
                                        ->where('method', 'insurance')
                                        ->whereIn('status', ['paid', 'pending'])
                                        ->exists();
                                    if ($exists) {
                                        continue;
                                    }

                                    VisitPayment::create([
                                        'visit_id' => $visit->id,
                                        'amount' => $insurerAmount,
                                        'method' => 'insurance',
                                        'kind' => $kind,
                                        'status' => 'paid',
                                        'paid_at' => now(),
                                        'collected_by_user_id' => $user?->id,
                                        'meta' => [
                                            'insurance' => [
                                                'policy_id' => $primary->id,
                                                'insurer_id' => $primary->insurer_id,
                                                'plan_id' => $primary->plan_id,
                                                'note' => $data['notes'] ?? null,
                                            ],
                                        ],
                                    ]);
                                    $createdPayments++;
                                }

                                // Recompute visit totals so the relation manager
                                // refreshes paid/balance values.
                                app(VisitCostingService::class)
                                    ->compute($visit->fresh(), (int) (auth()->id() ?? 0));

                                if (! empty($data['create_claim'])) {
                                    $service->createClaimFromVisit($visit->fresh(), $primary, $user);
                                }
                            });

                            Notification::make()
                                ->title("Applied insurance: {$createdPayments} payment row(s) created.")
                                ->success()
                                ->send();

                            $livewire->dispatch('refresh');
                        } catch (\Throwable $e) {
                            Log::error('[VisitPaymentsRelationManager.applyInsurance] failed', [
                                'visit_id' => $livewire->getOwnerRecord()?->id,
                                'msg' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('Apply Insurance failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
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
                    ->visible(fn (VisitPayment $record) => (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false) && ($record->status ?? null) === 'paid')
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                            $record->update(['status' => 'refunded']);

                            // Recompute the visit's financial snapshot so fees_total/profit_total
                            // and the doctor compensation ledger reflect the new paid total.
                            if ($record->visit) {
                                app(VisitCostingService::class)
                                    ->compute($record->visit, (int) (auth()->id() ?? 0));
                            }
                        });
                        Notification::make()->title('Payment refunded, visit recomputed')->success()->send();
                    }),

                Tables\Actions\Action::make('markVoid')
                    ->label('Void')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    // FIX: Restrict to Admin ID 1 ONLY
                    ->visible(fn (VisitPayment $record) => (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false) && ($record->status ?? null) !== 'void')
                    ->requiresConfirmation()
                    ->action(function (VisitPayment $record) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                            $record->update(['status' => 'void']);

                            if ($record->visit) {
                                app(VisitCostingService::class)
                                    ->compute($record->visit, (int) (auth()->id() ?? 0));
                            }
                        });
                        Notification::make()->title('Payment voided, visit recomputed')->success()->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    // FIX: Restrict to Admin ID 1 AND prevent deleting online payments
                    ->visible(fn (VisitPayment $record) => (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false) && ! in_array($record->method, ['link', 'myfatoorah', 'tap', 'stripe']))
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
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($records) {
                            $visitIds = [];
                            foreach ($records as $record) {
                                $record->update(['status' => 'void']);
                                if ($record->visit_id) {
                                    $visitIds[(int) $record->visit_id] = true;
                                }
                            }
                            // Recompute each affected visit once, not per-payment.
                            foreach (array_keys($visitIds) as $vid) {
                                $visit = \App\Models\Visit::find($vid);
                                if ($visit) {
                                    app(VisitCostingService::class)
                                        ->compute($visit, (int) (auth()->id() ?? 0));
                                }
                            }
                        });
                        Notification::make()->title('Selected payments voided, visits recomputed')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('id', 'desc');
    }
}
