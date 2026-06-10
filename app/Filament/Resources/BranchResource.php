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
use Filament\Forms\Get;
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

    // UI rename only
    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.branch.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.branch.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.branch.label_plural');
    }

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make(__('clinic_misc.branch.section_location'))
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label(__('clinic_misc.branch.clinic'))
                        // keep relationship for FK saving (same DB)
                        ->relationship(name: 'partner', titleAttribute: 'id')
                        ->getOptionLabelFromRecordUsing(fn (Partner $p) => (string) $p->getTranslation('name', $locale))
                        ->getSearchResultsUsing(function (string $search) use ($locale) {
                            return Partner::query()
                                ->where("name->{$locale}", 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck("name->{$locale}", 'id')
                                ->toArray();
                        })
                        ->preload()
                        ->searchable()
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('name')
                        ->label(__('clinic_misc.branch.location_name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->label(__('clinic_misc.branch.location_slug'))
                        ->helperText(__('clinic_misc.branch.location_slug_help'))
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug((string) $state))),

                    Forms\Components\Toggle::make('is_hub')
                        ->label('Central hub / warehouse')
                        ->helperText('This branch holds bulk stock and dispatches it to the clinic\'s other branches.')
                        ->default(false),
                ])->columns(3),

            Forms\Components\Section::make(__('clinic_misc.branch.section_media'))
                ->description(__('clinic_misc.branch.section_media_desc'))
                ->schema([
                    FileUpload::make('cover_image_path')
                        ->label(__('clinic_misc.branch.cover_image'))
                        ->image()
                        ->disk('public')
                        ->directory('branches/covers')
                        ->imageCropAspectRatio('16:9')
                        ->imageResizeMode('contain')
                        ->maxSize(2048)
                        ->hint(__('clinic_misc.branch.cover_image_hint')),

                    FileUpload::make('logo_path')
                        ->label(__('clinic_misc.branch.logo'))
                        ->image()
                        ->disk('public')
                        ->directory('branches/logos')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeMode('contain')
                        ->maxSize(1024)
                        ->hint(__('clinic_misc.branch.logo_hint')),
                ])->columns(2),

            Forms\Components\Section::make(__('clinic_misc.branch.section_contact'))
                ->schema([
                    Forms\Components\TextInput::make('phone')->label(__('clinic_misc.branch.phone'))->tel(),

                    // NEW: Email & License for Print
                    Forms\Components\TextInput::make('email')
                        ->label(__('clinic_misc.branch.email'))
                        ->email()
                        ->placeholder(__('clinic_misc.branch.email_placeholder')),

                    Forms\Components\TextInput::make('license_number')
                        ->label(__('clinic_misc.branch.license_number'))
                        ->placeholder(__('clinic_misc.branch.license_number_placeholder')),

                    Forms\Components\Textarea::make('address')
                        ->label(__('clinic_misc.branch.address'))
                        ->rows(2)
                        ->columnSpan(3), // Span full width of this row section

                    Forms\Components\Select::make('city_id')
                        ->label(__('clinic_misc.branch.city'))
                        ->options(fn () => City::query()
                            ->orderBy("name->{$locale}")
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => (string) $c->getTranslation('name', $locale)])
                            ->toArray()
                        )
                        ->required()
                        ->live()
                        ->searchable()
                        ->native(false),

                    Forms\Components\Select::make('block_id')
                        ->label(__('clinic_misc.branch.area_block'))
                        ->options(function (Get $get) use ($locale) {
                            $cityId = $get('city_id');
                            if (! $cityId) {
                                return [];
                            }

                            return Block::query()
                                ->where('city_id', $cityId)
                                ->orderBy("name->{$locale}")
                                ->get()
                                ->mapWithKeys(fn ($b) => [$b->id => (string) $b->getTranslation('name', $locale)])
                                ->toArray();
                        })
                        ->searchable()
                        ->native(false),

                    Forms\Components\TextInput::make('latitude')->label(__('clinic_misc.branch.latitude'))->numeric()->step('0.000001'),
                    Forms\Components\TextInput::make('longitude')->label(__('clinic_misc.branch.longitude'))->numeric()->step('0.000001'),
                ])->columns(3), // Adjusted columns to fit new fields nicely

            Forms\Components\Section::make(__('clinic_misc.branch.section_availability'))
                ->schema([
                    Forms\Components\Toggle::make('is_available')->label(__('clinic_misc.branch.location_available'))->default(true),
                    Forms\Components\Toggle::make('open_for_delivery')->label(__('clinic_misc.branch.home_visit_delivery'))->default(true),
                    Forms\Components\Toggle::make('open_for_pickup')->label(__('clinic_misc.branch.walk_in_pickup'))->default(true),

                    Forms\Components\TextInput::make('delivery_fee')
                        ->label(__('clinic_misc.branch.service_fee'))
                        ->numeric()
                        ->default(0)
                        ->rule('gte:0'),

                    Forms\Components\TextInput::make('min_order_amount')
                        ->label(__('clinic_misc.branch.minimum_charge'))
                        ->numeric()
                        ->default(0)
                        ->rule('gte:0'),

                    Forms\Components\TextInput::make('max_booking_days')
                        ->label(__('clinic_misc.branch.max_booking_days'))
                        ->numeric()
                        ->integer()
                        ->default(60)
                        ->minValue(1)
                        ->helperText(__('clinic_misc.branch.max_booking_days_help'))
                        ->required(),
                ])->columns(6),

            Forms\Components\Section::make(__('clinic_misc.branch.section_services'))
                ->schema([
                    Forms\Components\Select::make('services')
                        ->label(__('clinic_misc.branch.specialties'))
                        ->multiple()
                        ->relationship('services')
                        ->getOptionLabelFromRecordUsing(fn (Service $s) => (string) $s->getTranslation('name', $locale))
                        ->getSearchResultsUsing(function (string $search) use ($locale) {
                            return Service::query()
                                ->where("name->{$locale}", 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck("name->{$locale}", 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->helperText(__('clinic_misc.branch.specialties_help'))
                        ->native(false),

                    Forms\Components\Select::make('cuisines')
                        ->label(__('clinic_misc.branch.tags_categories'))
                        ->multiple()
                        ->relationship('cuisines')
                        ->getOptionLabelFromRecordUsing(fn (Cuisine $c) => (string) $c->getTranslation('name', $locale))
                        ->getSearchResultsUsing(function (string $search) use ($locale) {
                            return Cuisine::query()
                                ->where("name->{$locale}", 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck("name->{$locale}", 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->native(false),
                ])->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $locale = app()->getLocale();

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label(__('clinic_misc.branch.logo'))
                    ->disk('public')
                    ->circular()
                    ->height(36)
                    ->width(36)
                    ->toggleable(),

                Tables\Columns\ImageColumn::make('cover_image_path')
                    ->label(__('clinic_misc.branch.cover'))
                    ->disk('public')
                    ->height(48)
                    ->width(86)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('partner.name')
                    ->label(__('clinic_misc.branch.clinic'))
                    ->searchable(query: function (Builder $q, string $s) use ($locale) {
                        return $q->whereHas('partner', fn ($qq) => $qq->where("name->{$locale}", 'like', "%{$s}%"));
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('clinic_misc.branch.location'))
                    ->searchable(query: fn (Builder $q, string $s) => $q->where("name->{$locale}", 'like', "%{$s}%"))
                    ->sortable(),

                // localized city/block display
                Tables\Columns\TextColumn::make('city_id')
                    ->label(__('clinic_misc.branch.city'))
                    ->formatStateUsing(fn ($state, Branch $record) => $record->city?->getTranslation('name', $locale))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('block_id')
                    ->label(__('clinic_misc.branch.area_block'))
                    ->formatStateUsing(fn ($state, Branch $record) => $record->block?->getTranslation('name', $locale))
                    ->toggleable(),

                // NEW: License Number in Table
                Tables\Columns\TextColumn::make('license_number')
                    ->label(__('clinic_misc.branch.license'))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_available')->boolean()->label(__('clinic_misc.branch.available')),
                Tables\Columns\IconColumn::make('open_for_delivery')->boolean()->label(__('clinic_misc.branch.home_visit')),
                Tables\Columns\IconColumn::make('open_for_pickup')->boolean()->label(__('clinic_misc.branch.walk_in')),

                Tables\Columns\TextColumn::make('max_booking_days')
                    ->label(__('clinic_misc.branch.max_days'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label(__('clinic_misc.branch.fee'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label(__('clinic_misc.branch.minimum'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->alignRight(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label(__('clinic_misc.branch.clinic'))
                    ->relationship('partner', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Partner $p) => (string) $p->getTranslation('name', $locale))
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_available')->label(__('clinic_misc.branch.available')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('clinic_misc.branch.edit_location')),
                Tables\Actions\DeleteAction::make()->label(__('clinic_misc.branch.delete_location')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label(__('clinic_misc.branch.delete_selected')),
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
