<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make('Basics')->schema([
                Forms\Components\KeyValue::make('name')->label('Name (Translations)')
                    ->keyLabel('Locale')->valueLabel('Text')->nullable(),
                Forms\Components\TextInput::make('code')->label('Code')
                    ->required()->unique(ignoreRecord: true)->maxLength(64)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $s) => $set('code', Str::upper((string) $s))),
                Forms\Components\Select::make('discount_type')
                    ->options(['amount' => 'Fixed Amount', 'percent' => 'Percent'])
                    ->default('amount')->required()->live(),
                Forms\Components\TextInput::make('discount_amount')
                    ->numeric()->rule('gte:0')
                    ->visible(fn (Forms\Get $get) => $get('discount_type') === 'amount'),
                Forms\Components\TextInput::make('discount_percent')
                    ->numeric()->rule('gte:0')->rule('lte:100')
                    ->visible(fn (Forms\Get $get) => $get('discount_type') === 'percent'),
                Forms\Components\TextInput::make('min_order_amount')->numeric()->default(0)->rule('gte:0'),
                Forms\Components\Select::make('allowed_order_type')
                    ->options(['any' => 'Any', 'delivery' => 'Delivery', 'pickup' => 'Pickup'])->default('any'),
                Forms\Components\Select::make('apply_to')
                    ->options(['matching_items' => 'Matching Items', 'order' => 'Entire Order'])->default('matching_items'),
                Forms\Components\TextInput::make('item_limit')->numeric()->minValue(1)->nullable()
                    ->hint('Discount top-N eligible lines by value.'),
                Forms\Components\TextInput::make('max_discount_amount')->numeric()->minValue(0)
                    ->hint('Absolute cap per order.'),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\DateTimePicker::make('starts_at')->native(false),
                Forms\Components\DateTimePicker::make('ends_at')->native(false),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Branch Restriction')->schema([
                Forms\Components\Select::make('branches')
                    ->multiple()
                    ->relationship('branches') // no "name->{$locale}" here
                    ->getOptionLabelFromRecordUsing(fn (Branch $b) => (string) $b->getTranslation('name', $locale)
                    )
                    ->getSearchResultsUsing(fn (string $search) => Branch::query()
                        ->where("name->$locale", 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck("name->$locale", 'id'))
                    ->preload()->searchable()->native(false)
                    ->hint('Leave empty for GLOBAL coupon'),
            ]),

            Forms\Components\Section::make('Scope (Menus / Sections / Items)')->schema([
                Forms\Components\Select::make('menus')
                    ->multiple()
                    ->relationship('menus')
                    ->getOptionLabelFromRecordUsing(fn (Menu $m) => (string) $m->getTranslation('name', $locale)
                    )
                    ->getSearchResultsUsing(fn (string $search) => Menu::query()
                        ->where("name->$locale", 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck("name->$locale", 'id'))
                    ->preload()->searchable()->native(false),

                Forms\Components\Select::make('sections')->label('Menu Sections')
                    ->multiple()
                    ->relationship('sections')
                    ->getOptionLabelFromRecordUsing(fn (MenuSection $s) => (string) $s->getTranslation('name', $locale)
                    )
                    ->getSearchResultsUsing(fn (string $search) => MenuSection::query()
                        ->where("name->$locale", 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck("name->$locale", 'id'))
                    ->preload()->searchable()->native(false),

                Forms\Components\Select::make('items')->label('Menu Items')
                    ->multiple()
                    ->relationship('items')
                    ->getOptionLabelFromRecordUsing(fn (MenuItem $i) => (string) $i->getTranslation('name', $locale)
                    )
                    ->getSearchResultsUsing(fn (string $search) => MenuItem::query()
                        ->where("name->$locale", 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck("name->$locale", 'id'))
                    ->preload()->searchable()->native(false),
            ])->columns(3)->description('Items > Sections > Menus precedence. Leave all empty to apply to all items.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('discount_type')->colors(['primary'])->sortable(),
                Tables\Columns\TextColumn::make('discount_amount')->label('Amt')->toggleable(),
                Tables\Columns\TextColumn::make('discount_percent')->label('%')->toggleable(),
                Tables\Columns\TextColumn::make('apply_to')->label('Apply To'),
                Tables\Columns\TextColumn::make('item_limit')->label('Item Limit'),
                Tables\Columns\TextColumn::make('max_discount_amount')->label('Cap'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->since(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
