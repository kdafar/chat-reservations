<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\ChartOfAccountResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'accounting/chart-of-accounts';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.chart_of_account.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.chart_of_account.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.chart_of_account.label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('accounting.chart_of_account.section_account'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('accounting.chart_of_account.code'))
                        ->required()
                        ->maxLength(16)
                        ->disabled(fn (?Account $record) => $record?->is_system)
                        ->helperText(__('accounting.chart_of_account.code_helper')),

                    Forms\Components\TextInput::make('name')
                        ->label(__('accounting.chart_of_account.name'))
                        ->required()
                        ->maxLength(191)
                        ->disabled(fn (?Account $record) => $record?->is_system),

                    Forms\Components\Select::make('type')
                        ->label(__('accounting.chart_of_account.type'))
                        ->required()
                        ->disabled(fn (?Account $record) => $record?->is_system)
                        ->options([
                            Account::TYPE_ASSET => __('accounting.chart_of_account.type_asset'),
                            Account::TYPE_LIABILITY => __('accounting.chart_of_account.type_liability'),
                            Account::TYPE_EQUITY => __('accounting.chart_of_account.type_equity'),
                            Account::TYPE_REVENUE => __('accounting.chart_of_account.type_revenue'),
                            Account::TYPE_COGS => __('accounting.chart_of_account.type_cogs'),
                            Account::TYPE_EXPENSE => __('accounting.chart_of_account.type_expense'),
                            Account::TYPE_CONTRA_ASSET => __('accounting.chart_of_account.type_contra_asset'),
                            Account::TYPE_CONTRA_LIABILITY => __('accounting.chart_of_account.type_contra_liability'),
                            Account::TYPE_CONTRA_REVENUE => __('accounting.chart_of_account.type_contra_revenue'),
                        ]),

                    Forms\Components\Select::make('parent_id')
                        ->label(__('accounting.chart_of_account.parent_account'))
                        ->relationship('parent', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('branch_id')
                        ->label(__('accounting.chart_of_account.branch_optional'))
                        ->options(fn () => Branch::pluck('name', 'id'))
                        ->nullable()
                        ->helperText(__('accounting.chart_of_account.branch_helper')),

                    Forms\Components\TextInput::make('currency')
                        ->label(__('accounting.chart_of_account.currency'))
                        ->default('KWD')
                        ->maxLength(3)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('accounting.chart_of_account.is_active'))
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label(__('accounting.chart_of_account.description'))
                        ->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('accounting.chart_of_account.code'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('accounting.chart_of_account.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Account $r) => $r->parent?->name),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('accounting.chart_of_account.type'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Account::TYPE_ASSET => 'success',
                        Account::TYPE_LIABILITY => 'danger',
                        Account::TYPE_EQUITY => 'primary',
                        Account::TYPE_REVENUE => 'info',
                        Account::TYPE_COGS => 'warning',
                        Account::TYPE_EXPENSE => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('accounting.chart_of_account.type_'.$state)),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('accounting.chart_of_account.branch'))
                    ->placeholder(__('accounting.chart_of_account.placeholder_dash'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label(__('accounting.chart_of_account.balance_kwd'))
                    ->getStateUsing(fn (Account $r) => number_format($r->balanceAt(), 3))
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->weight('semibold'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('accounting.chart_of_account.is_active'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('accounting.chart_of_account.system'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->color(fn (bool $state) => $state ? 'gray' : 'success')
                    ->tooltip(fn (bool $state) => $state
                        ? __('accounting.chart_of_account.system_tooltip')
                        : __('accounting.chart_of_account.user_managed_tooltip')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('accounting.chart_of_account.type'))
                    ->options([
                        Account::TYPE_ASSET => __('accounting.chart_of_account.type_asset'),
                        Account::TYPE_LIABILITY => __('accounting.chart_of_account.type_liability'),
                        Account::TYPE_EQUITY => __('accounting.chart_of_account.type_equity'),
                        Account::TYPE_REVENUE => __('accounting.chart_of_account.type_revenue'),
                        Account::TYPE_COGS => __('accounting.chart_of_account.type_cogs'),
                        Account::TYPE_EXPENSE => __('accounting.chart_of_account.type_expense'),
                        Account::TYPE_CONTRA_REVENUE => __('accounting.chart_of_account.type_contra_revenue'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('accounting.chart_of_account.is_active')),
                Tables\Filters\TernaryFilter::make('is_system')->label(__('accounting.chart_of_account.system')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Account $r) => ! $r->is_system),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('parent', 'branch');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChartOfAccounts::route('/'),
            'create' => Pages\CreateChartOfAccount::route('/create'),
            'edit' => Pages\EditChartOfAccount::route('/{record}/edit'),
        ];
    }
}
