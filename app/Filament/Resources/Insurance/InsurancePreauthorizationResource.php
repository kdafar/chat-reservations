<?php

namespace App\Filament\Resources\Insurance;

use App\Filament\Resources\Insurance\InsurancePreauthorizationResource\Pages;
use App\Models\Insurance\InsurancePreauthorization;
use App\Models\Insurance\PatientInsurancePolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InsurancePreauthorizationResource extends Resource
{
    protected static ?string $model = InsurancePreauthorization::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'insurance/preauthorizations';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.insurance');
    }

    public static function getNavigationLabel(): string
    {
        return 'Pre-authorizations';
    }

    public static function getModelLabel(): string
    {
        return 'Pre-authorization';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pre-authorizations';
    }

    /** @return array<string, string> */
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

    /** @return array<int, string> */
    protected static function policyOptions(): array
    {
        return PatientInsurancePolicy::query()
            ->with(['patient', 'plan'])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(function (PatientInsurancePolicy $p) {
                $patient = $p->patient?->name ?? 'Patient #'.$p->patient_id;
                $plan = $p->plan?->code ?? 'no-plan';

                return [$p->id => "{$patient} → {$plan} ({$p->policy_number})"];
            })
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('patient_policy_id')
                        ->label('Patient Policy')
                        ->required()
                        ->options(fn () => self::policyOptions())
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('visit_id')
                        ->label('Visit')
                        ->relationship('visit', 'id')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\TextInput::make('reference_no')
                        ->label('Reference No.')
                        ->maxLength(64),

                    Forms\Components\DateTimePicker::make('requested_at')
                        ->label('Requested At')
                        ->default(now())
                        ->seconds(false),
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patientPolicy.patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('visit_id')
                    ->label('Visit #')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('estimated_total')
                    ->label('Estimated')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->sortable(),

                Tables\Columns\TextColumn::make('approved_amount')
                    ->label('Approved')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 3) : '—')
                    ->toggleable(),

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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::statusOptions()),

                Tables\Filters\Filter::make('requested_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('requested_at', '>=', $d))
                        ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('requested_at', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('markDecision')
                    ->label('Mark Decision')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (InsurancePreauthorization $r) => in_array($r->status, [
                        InsurancePreauthorization::STATUS_SUBMITTED,
                        InsurancePreauthorization::STATUS_UNDER_REVIEW,
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
                        try {
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
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No pre-authorizations yet')
            ->emptyStateDescription('Submit and track insurer approval requests here.')
            ->emptyStateIcon('heroicon-o-document-check');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['patientPolicy.patient', 'visit']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPreauthorizations::route('/'),
            'create' => Pages\CreatePreauthorization::route('/create'),
            'edit' => Pages\EditPreauthorization::route('/{record}/edit'),
        ];
    }
}
