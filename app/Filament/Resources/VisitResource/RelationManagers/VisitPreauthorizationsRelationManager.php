<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\Insurance\InsurancePreauthorization;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VisitPreauthorizationsRelationManager extends RelationManager
{
    protected static string $relationship = 'preauthorizations';

    protected static ?string $title = 'Pre-authorizations';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Visit || ! $ownerRecord->patient_id) {
            return false;
        }

        return PatientInsurancePolicy::query()
            ->where('patient_id', $ownerRecord->patient_id)
            ->active()
            ->exists();
    }

    /** @return array<int, string> */
    protected function policyOptions(): array
    {
        $visit = $this->getOwnerRecord();
        if (! $visit instanceof Visit || ! $visit->patient_id) {
            return [];
        }

        return PatientInsurancePolicy::query()
            ->where('patient_id', $visit->patient_id)
            ->active()
            ->with(['insurer', 'plan'])
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->get()
            ->mapWithKeys(function (PatientInsurancePolicy $p) {
                $insurer = $p->insurer?->name ?? 'Insurer #'.$p->insurer_id;
                $plan = $p->plan?->name ?? '—';

                return [$p->id => "{$insurer} · {$plan} · {$p->policy_number}"];
            })
            ->toArray();
    }

    protected static function statusOptions(): array
    {
        return [
            InsurancePreauthorization::STATUS_DRAFT => 'Draft',
            InsurancePreauthorization::STATUS_SUBMITTED => 'Submitted',
            InsurancePreauthorization::STATUS_UNDER_REVIEW => 'Under Review',
            InsurancePreauthorization::STATUS_APPROVED => 'Approved',
            InsurancePreauthorization::STATUS_PARTIALLY_APPROVED => 'Partially Approved',
            InsurancePreauthorization::STATUS_REJECTED => 'Rejected',
            InsurancePreauthorization::STATUS_EXPIRED => 'Expired',
        ];
    }

    protected static function statusColor(string $state): string
    {
        return match ($state) {
            InsurancePreauthorization::STATUS_DRAFT => 'gray',
            InsurancePreauthorization::STATUS_SUBMITTED => 'info',
            InsurancePreauthorization::STATUS_UNDER_REVIEW => 'warning',
            InsurancePreauthorization::STATUS_APPROVED => 'success',
            InsurancePreauthorization::STATUS_PARTIALLY_APPROVED => 'warning',
            InsurancePreauthorization::STATUS_REJECTED => 'danger',
            InsurancePreauthorization::STATUS_EXPIRED => 'gray',
            default => 'gray',
        };
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('patient_policy_id')
                        ->label('Patient Policy')
                        ->required()
                        ->options(fn () => $this->policyOptions())
                        ->default(function () {
                            $visit = $this->getOwnerRecord();
                            if (! $visit instanceof Visit || ! $visit->patient_id) {
                                return null;
                            }

                            return PatientInsurancePolicy::query()
                                ->where('patient_id', $visit->patient_id)
                                ->active()
                                ->orderByDesc('is_primary')
                                ->orderBy('priority')
                                ->value('id');
                        })
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('reference_no')
                        ->label('Insurer Reference No.')
                        ->maxLength(64),

                    Forms\Components\DateTimePicker::make('requested_at')
                        ->label('Requested At')
                        ->default(now())
                        ->seconds(false)
                        ->required(),
                ]),

            Forms\Components\Section::make('Services Requested')
                ->columns(1)
                ->schema([
                    Forms\Components\Repeater::make('services')
                        ->label('Services')
                        ->defaultItems(1)
                        ->reorderable()
                        ->columns(3)
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->required()
                                ->maxLength(191)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('estimated_amount')
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0)
                                ->default(0)
                                ->prefix('KWD'),
                        ]),

                    Forms\Components\TextInput::make('estimated_total')
                        ->label('Estimated Total (KWD)')
                        ->numeric()
                        ->step(0.001)
                        ->minValue(0)
                        ->prefix('KWD')
                        ->helperText('Sum across services. Adjust manually if needed.'),
                ]),

            Forms\Components\Section::make('Decision')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(self::statusOptions())
                        ->default(InsurancePreauthorization::STATUS_DRAFT)
                        ->required(),

                    Forms\Components\TextInput::make('approved_amount')
                        ->label('Approved Amount (KWD)')
                        ->numeric()
                        ->step(0.001)
                        ->minValue(0)
                        ->prefix('KWD')
                        ->nullable(),

                    Forms\Components\DatePicker::make('valid_from')->native(false),
                    Forms\Components\DatePicker::make('valid_until')->native(false),

                    Forms\Components\FileUpload::make('approval_letter_path')
                        ->label('Approval Letter')
                        ->disk('public')
                        ->directory('insurance/preauth')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('decision_notes')
                        ->rows(2)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('patientPolicy.insurer.name')
                    ->label('Insurer')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('estimated_total')
                    ->label('Estimated')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),

                Tables\Columns\TextColumn::make('approved_amount')
                    ->label('Approved')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 3) : '—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => self::statusColor($state))
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('decided_at')
                    ->label('Decided')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Request Pre-authorization')
                    ->icon('heroicon-o-document-check')
                    ->mutateFormDataUsing(function (array $data): array {
                        $visit = $this->getOwnerRecord();
                        $data['visit_id'] = $visit->id;
                        $data['branch_id'] = $visit->branch_id;
                        $data['requested_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;

                        // Recompute estimated_total from services if user didn't
                        // override it (cheap safety net).
                        $services = $data['services'] ?? [];
                        $sum = 0.0;
                        foreach ($services as $row) {
                            $sum += (float) ($row['estimated_amount'] ?? 0);
                        }
                        if (empty($data['estimated_total'])) {
                            $data['estimated_total'] = round($sum, 3);
                        }

                        return $data;
                    })
                    ->after(function () {
                        Notification::make()
                            ->title('Pre-authorization request created')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('markDecision')
                    ->label('Record Decision')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (InsurancePreauthorization $r) => in_array($r->status, [
                        InsurancePreauthorization::STATUS_SUBMITTED,
                        InsurancePreauthorization::STATUS_UNDER_REVIEW,
                        InsurancePreauthorization::STATUS_DRAFT,
                    ], true))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Decision')
                            ->options([
                                InsurancePreauthorization::STATUS_APPROVED => 'Approved',
                                InsurancePreauthorization::STATUS_PARTIALLY_APPROVED => 'Partially Approved',
                                InsurancePreauthorization::STATUS_REJECTED => 'Rejected',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('approved_amount')
                            ->label('Approved Amount (KWD)')
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0)
                            ->prefix('KWD'),

                        Forms\Components\Textarea::make('decision_notes')
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (InsurancePreauthorization $r, array $data) {
                        $r->forceFill([
                            'status' => $data['status'],
                            'approved_amount' => $data['approved_amount'] ?? $r->approved_amount,
                            'decision_notes' => $data['decision_notes'] ?? $r->decision_notes,
                            'decided_at' => now(),
                            'decided_by_user_id' => auth()->id(),
                        ])->save();

                        Notification::make()
                            ->title('Decision recorded')
                            ->body('Pre-auth #'.$r->id.' set to '.$data['status'])
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (InsurancePreauthorization $r) => $r->status === InsurancePreauthorization::STATUS_DRAFT),
            ])
            ->emptyStateHeading('No pre-authorization requests')
            ->emptyStateDescription('Request insurer approval for high-cost services before delivery.')
            ->emptyStateIcon('heroicon-o-document-check');
    }
}
