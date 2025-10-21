<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers\CouponsRelationManager;
use App\Filament\Resources\BranchResource\RelationManagers\CoverageRelationManager;
use App\Filament\Resources\BranchResource\RelationManagers\GatewayAccountsRelationManager;
use App\Filament\Resources\BranchResource\RelationManagers\OpeningHoursRelationManager;
use App\Models\Block;
use App\Models\Branch;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\Partner;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BranchResource extends Resource
{
    use Translatable;

    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make('Basic')
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label('Partner')
                        ->relationship('partner')
                        ->getOptionLabelFromRecordUsing(fn (Partner $p) => (string) $p->getTranslation('name', $locale))
                        ->getSearchResultsUsing(function (string $search) use ($locale) {
                            return Partner::query()
                                ->where("name->$locale", 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck("name->$locale", 'id');
                        })
                        ->preload()
                        ->searchable()
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->helperText('Unique slug for URLs.')
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug((string) $state))),
                ])->columns(3),

            Forms\Components\Section::make('Brand & Media')
                ->description('Upload a wide cover (16:9) and a square logo (1:1).')
                ->schema([
                    FileUpload::make('cover_image_path')
                        ->label('Cover Image')
                        ->image()
                        ->disk('public')
                        ->directory('branches/covers')
                        ->imageCropAspectRatio('16:9')
                        ->imageResizeMode('contain')
                        ->maxSize(2048) // 2MB
                        ->hint('Recommended: 1600×900px'),

                    FileUpload::make('logo_path')
                        ->label('Logo')
                        ->image()
                        ->disk('public')
                        ->directory('branches/logos')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeMode('contain')
                        ->maxSize(1024) // 1MB
                        ->hint('Recommended: 512×512px'),
                ])->columns(2),

            Forms\Components\Section::make('Contact & Location')
                ->schema([
                    Forms\Components\TextInput::make('phone')->tel(),
                    Forms\Components\Textarea::make('address')->rows(2)->columnSpan(2),

                    Forms\Components\Select::make('city_id')
                        ->label('City')
                        ->options(fn () => City::query()
                            ->orderBy("name->$locale")
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => (string) $c->getTranslation('name', $locale)]))
                        ->required()
                        ->live()
                        ->searchable()
                        ->native(false),

                    Forms\Components\Select::make('block_id')
                        ->label('Block')
                        ->options(function (Forms\Get $get) use ($locale) {
                            $cityId = $get('city_id');
                            if (! $cityId) {
                                return [];
                            }

                            return Block::query()
                                ->where('city_id', $cityId)
                                ->orderBy("name->$locale")
                                ->get()
                                ->mapWithKeys(fn ($b) => [$b->id => (string) $b->getTranslation('name', $locale)]);
                        })
                        ->searchable()
                        ->native(false),

                    Forms\Components\TextInput::make('latitude')->numeric()->step('0.000001'),
                    Forms\Components\TextInput::make('longitude')->numeric()->step('0.000001'),
                ])->columns(6),

            Forms\Components\Section::make('Operations')
                ->schema([
                    Forms\Components\Toggle::make('is_available')->label('Branch Available')->default(true),
                    Forms\Components\Toggle::make('open_for_delivery')->default(true),
                    Forms\Components\Toggle::make('open_for_pickup')->default(true),
                    Forms\Components\TextInput::make('delivery_fee')->numeric()->default(0)->rule('gte:0'),
                    Forms\Components\TextInput::make('min_order_amount')->numeric()->default(0)->rule('gte:0'),
                ])->columns(5),

            Forms\Components\Section::make('Services & Cuisines')
                ->schema([
                    Forms\Components\Select::make('services')
                        ->label('Services')
                        ->multiple()
                        ->relationship('services')
                        ->getOptionLabelFromRecordUsing(fn (Service $s) => (string) $s->getTranslation('name', $locale))
                        ->getSearchResultsUsing(function (string $search) use ($locale) {
                            return Service::query()
                                ->where("name->$locale", 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck("name->$locale", 'id');
                        })
                        ->searchable()
                        ->helperText('Select service types this branch serves (Restaurant, Grocery, Pharmacy, …).')
                        ->native(false),

                    Forms\Components\Select::make('cuisines')
                        ->label('Cuisines')
                        ->multiple()
                        ->relationship('cuisines')
                        ->getOptionLabelFromRecordUsing(fn (Cuisine $c) => (string) $c->getTranslation('name', $locale))
                        ->getSearchResultsUsing(function (string $search) use ($locale) {
                            return Cuisine::query()
                                ->where("name->$locale", 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck("name->$locale", 'id');
                        })
                        ->searchable()
                        ->native(false),
                ])->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Thumbnail logo
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->circular()
                    ->height(36)
                    ->width(36)
                    ->toggleable(),

                // Optional: small cover preview
                Tables\Columns\ImageColumn::make('cover_image_path')
                    ->label('Cover')
                    ->disk('public')
                    ->height(48)
                    ->width(86)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Partner')
                    ->searchable(query: fn (Builder $q, $s) => $q->whereHas('partner', fn ($q) => $q->where('name->'.app()->getLocale(), 'like', "%{$s}%")
                    )
                    ),

                Tables\Columns\TextColumn::make('name')
                    ->label('Branch')
                    ->searchable(query: fn (Builder $q, $s) => $q->where('name->'.app()->getLocale(), 'like', "%{$s}%")
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('city.name')->label('City')->toggleable(),
                Tables\Columns\TextColumn::make('block.name')->label('Block')->toggleable(),

                Tables\Columns\IconColumn::make('is_available')->boolean()->label('Available'),
                Tables\Columns\IconColumn::make('open_for_delivery')->boolean()->label('Delivery'),
                Tables\Columns\IconColumn::make('open_for_pickup')->boolean()->label('Pickup'),

                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label('Fee')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label('Min')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->alignRight(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name->'.app()->getLocale())
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_available')->label('Available'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OpeningHoursRelationManager::class,
            CoverageRelationManager::class,
            CouponsRelationManager::class,
            GatewayAccountsRelationManager::class,
        ];
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
