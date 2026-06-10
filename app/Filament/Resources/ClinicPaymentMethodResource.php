<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicPaymentMethodResource\Pages;
use App\Models\Branch;
use App\Models\ClinicPaymentMethod;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClinicPaymentMethodResource extends Resource
{
    protected static ?string $model = ClinicPaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_setup');
    }

    public static function getNavigationLabel(): string
    {
        return 'Payment Methods';
    }

    public static function getModelLabel(): string
    {
        return 'Payment Method';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Payment Methods';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Scope')
                ->description('Leave both empty for a GLOBAL default (all clinics). Set the clinic for a clinic-wide method, and additionally a branch to override just that branch. Most specific wins.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label('Clinic')
                        ->options(fn () => Partner::query()->orderBy('id')->get()
                            ->mapWithKeys(fn ($p) => [$p->id => (is_array($p->name) ? ($p->name['en'] ?? reset($p->name)) : ($p->name ?? ('#'.$p->id)))])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Empty = global default shared by every clinic.'),

                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn () => Branch::query()->orderBy('id')->get()
                            ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Optional within-clinic override. Empty = applies to the whole clinic.'),
                ]),

            Forms\Components\Section::make('Method')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('Key')
                        ->required()
                        ->maxLength(191)
                        ->helperText('Identifier sent to the payment endpoint, e.g. cash, knet, card, transfer, insurance, link.'),

                    Forms\Components\TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(191)
                        ->helperText('Display name shown to staff.'),

                    Forms\Components\Select::make('type')
                        ->label('Type')
                        ->options([
                            'manual' => 'Manual',
                            'online' => 'Online (gateway / link)',
                        ])
                        ->default('manual')
                        ->native(false)
                        ->required(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('requires_reference')
                        ->label('Requires reference')
                        ->helperText('On for card/knet/transfer/link (a transaction/reference id is required). Off for cash.')
                        ->default(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('partner_id')
                    ->label('Clinic')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return 'Global';
                        }
                        $p = Partner::query()->find($state);
                        $name = $p?->name;

                        return is_array($name) ? ($name['en'] ?? reset($name)) : ($name ?? ('#'.$state));
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return 'All branches';
                        }
                        $b = Branch::query()->find($state);

                        return $b?->localized_name ?? ('#'.$state);
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('key')->label('Key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('label')->label('Label')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\IconColumn::make('requires_reference')->label('Ref?')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Sort')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'manual' => 'Manual',
                        'online' => 'Online',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinicPaymentMethods::route('/'),
            'create' => Pages\CreateClinicPaymentMethod::route('/create'),
            'edit' => Pages\EditClinicPaymentMethod::route('/{record}/edit'),
        ];
    }
}
