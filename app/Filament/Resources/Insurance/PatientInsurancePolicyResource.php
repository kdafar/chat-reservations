<?php

namespace App\Filament\Resources\Insurance;

use App\Filament\Resources\Insurance\PatientInsurancePolicyResource\Pages;
use App\Models\Insurance\InsurancePlan;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PatientInsurancePolicyResource extends Resource
{
    protected static ?string $model = PatientInsurancePolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'insurance/policies';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.insurance');
    }

    public static function getNavigationLabel(): string
    {
        return 'Patient Policies';
    }

    public static function getModelLabel(): string
    {
        return 'Patient Policy';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Patient Policies';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Policy')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('patient_id')
                        ->label('Patient')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return Patient::query()
                                ->where(function ($q) use ($search) {
                                    $q->where('name', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                                })
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (Patient $p) => [
                                    $p->id => trim(($p->name ?? '—').' ('.($p->phone ?? 'no phone').')'),
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            $p = Patient::find($value);
                            if (! $p) {
                                return null;
                            }

                            return trim(($p->name ?? '—').' ('.($p->phone ?? 'no phone').')');
                        }),

                    Forms\Components\Select::make('insurer_id')
                        ->label('Insurer')
                        ->required()
                        ->options(fn () => Insurer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('plan_id', null)),

                    Forms\Components\Select::make('plan_id')
                        ->label('Plan')
                        ->options(function (Forms\Get $get) {
                            $insurerId = $get('insurer_id');
                            if (! $insurerId) {
                                return [];
                            }

                            return InsurancePlan::query()
                                ->where('insurer_id', $insurerId)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (InsurancePlan $pl) => [$pl->id => "{$pl->code} — {$pl->name}"])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Pick an insurer first.'),

                    Forms\Components\TextInput::make('policy_number')
                        ->required()
                        ->maxLength(64),

                    Forms\Components\TextInput::make('member_id')
                        ->maxLength(64),

                    Forms\Components\TextInput::make('card_number')
                        ->maxLength(64),
                ]),

            Forms\Components\Section::make('Holder')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('holder_relationship')
                        ->label('Holder Relationship')
                        ->options([
                            PatientInsurancePolicy::REL_SELF => 'Self',
                            PatientInsurancePolicy::REL_SPOUSE => 'Spouse',
                            PatientInsurancePolicy::REL_CHILD => 'Child',
                            PatientInsurancePolicy::REL_PARENT => 'Parent',
                            PatientInsurancePolicy::REL_OTHER => 'Other',
                        ])
                        ->default(PatientInsurancePolicy::REL_SELF)
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('holder_name')
                        ->label('Holder Name')
                        ->maxLength(191)
                        ->visible(fn (Forms\Get $get) => $get('holder_relationship') && $get('holder_relationship') !== PatientInsurancePolicy::REL_SELF)
                        ->helperText('Required when policy holder is not the patient.'),
                ]),

            Forms\Components\Section::make('Status & Validity')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            PatientInsurancePolicy::STATUS_ACTIVE => 'Active',
                            PatientInsurancePolicy::STATUS_EXPIRED => 'Expired',
                            PatientInsurancePolicy::STATUS_SUSPENDED => 'Suspended',
                            PatientInsurancePolicy::STATUS_CANCELLED => 'Cancelled',
                        ])
                        ->default(PatientInsurancePolicy::STATUS_ACTIVE)
                        ->required(),

                    Forms\Components\Toggle::make('is_primary')
                        ->label('Primary')
                        ->default(false),

                    Forms\Components\TextInput::make('priority')
                        ->numeric()
                        ->minValue(1)
                        ->default(1),

                    Forms\Components\DatePicker::make('effective_from')->native(false),

                    Forms\Components\DatePicker::make('effective_until')->native(false),

                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('policy_number')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('insurer.name')
                    ->label('Insurer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('plan.code')
                    ->label('Plan')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        PatientInsurancePolicy::STATUS_ACTIVE => 'success',
                        PatientInsurancePolicy::STATUS_EXPIRED => 'danger',
                        PatientInsurancePolicy::STATUS_SUSPENDED => 'warning',
                        PatientInsurancePolicy::STATUS_CANCELLED => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),

                Tables\Columns\TextColumn::make('priority')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_until')
                    ->label('Expires')
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PatientInsurancePolicy::STATUS_ACTIVE => 'Active',
                        PatientInsurancePolicy::STATUS_EXPIRED => 'Expired',
                        PatientInsurancePolicy::STATUS_SUSPENDED => 'Suspended',
                        PatientInsurancePolicy::STATUS_CANCELLED => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('insurer_id')
                    ->label('Insurer')
                    ->options(fn () => Insurer::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_primary')->label('Primary'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No patient policies yet')
            ->emptyStateDescription('Link patients to their insurance plans here.')
            ->emptyStateIcon('heroicon-o-identification');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['patient', 'insurer', 'plan']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolicies::route('/'),
            'create' => Pages\CreatePolicy::route('/create'),
            'edit' => Pages\EditPolicy::route('/{record}/edit'),
        ];
    }
}
