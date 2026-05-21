<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\VendorResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'accounting/vendors';

    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.vendor.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.vendor.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.vendor.label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Vendor')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('code')
                        ->label('Code')
                        ->maxLength(32)
                        ->helperText('Optional short reference (e.g. LANDLORD-A).')
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('contact_name')
                        ->label('Contact Name')
                        ->maxLength(191),

                    Forms\Components\TextInput::make('phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(64),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('tax_number')
                        ->label('Tax / Commercial Reg. No.')
                        ->maxLength(64),

                    Forms\Components\Textarea::make('address')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Defaults')
                ->description('Suggested accounts when creating an expense for this vendor.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('default_account_id')
                        ->label('Default Expense Account')
                        ->options(fn () => Account::query()
                            ->whereIn('type', [Account::TYPE_EXPENSE, Account::TYPE_COGS])
                            ->where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('default_payable_account_id')
                        ->label('Default Payable Account')
                        ->options(fn () => Account::query()
                            ->where('type', Account::TYPE_LIABILITY)
                            ->where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Used when billing this vendor on account (typically 2010 Accounts Payable).'),
                ]),

            Forms\Components\Section::make('Other')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')->default(true),

                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Vendor $r) => $r->code),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contact')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—')
                    ->copyable(),

                Tables\Columns\TextColumn::make('defaultAccount.name')
                    ->label('Default Account')
                    ->placeholder('—')
                    ->description(fn (Vendor $r) => $r->defaultAccount?->code)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('defaultAccount');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
