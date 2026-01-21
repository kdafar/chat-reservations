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
    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $navigationLabel = 'Clinic Locations';

    protected static ?string $modelLabel = 'Clinic Location';

    protected static ?string $pluralModelLabel = 'Clinic Locations';

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make('Clinic Location')
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label('Clinic')
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
                        ->label('Location name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->label('Location slug')
                        ->helperText('Unique slug for URLs.')
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug((string) $state))),
                ])->columns(3),

            Forms\Components\Section::make('Brand & Media')
                ->description('Upload a wide cover (16:9) and a square logo (1:1).')
                ->schema([
                    FileUpload::make('cover_image_path')
                        ->label('Cover image')
                        ->image()
                        ->disk('public')
                        ->directory('branches/covers')
                        ->imageCropAspectRatio('16:9')
                        ->imageResizeMode('contain')
                        ->maxSize(2048)
                        ->hint('Recommended: 1600×900px'),

                    FileUpload::make('logo_path')
                        ->label('Logo')
                        ->image()
                        ->disk('public')
                        ->directory('branches/logos')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeMode('contain')
                        ->maxSize(1024)
                        ->hint('Recommended: 512×512px'),
                ])->columns(2),

            Forms\Components\Section::make('Contact & Address')
                ->schema([
                    Forms\Components\TextInput::make('phone')->label('Phone')->tel(),

                    // NEW: Email & License for Print
                    Forms\Components\TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->placeholder('branch@clinic.com'),

                    Forms\Components\TextInput::make('license_number')
                        ->label('Branch License #')
                        ->placeholder('e.g. MOH-B-123'),

                    Forms\Components\Textarea::make('address')
                        ->label('Address / Directions')
                        ->rows(2)
                        ->columnSpan(3), // Span full width of this row section

                    Forms\Components\Select::make('city_id')
                        ->label('City')
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
                        ->label('Area / Block')
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

                    Forms\Components\TextInput::make('latitude')->label('Latitude')->numeric()->step('0.000001'),
                    Forms\Components\TextInput::make('longitude')->label('Longitude')->numeric()->step('0.000001'),
                ])->columns(3), // Adjusted columns to fit new fields nicely

            Forms\Components\Section::make('Availability & Policies')
                ->schema([
                    Forms\Components\Toggle::make('is_available')->label('Location available')->default(true),
                    Forms\Components\Toggle::make('open_for_delivery')->label('Home visit / delivery')->default(true),
                    Forms\Components\Toggle::make('open_for_pickup')->label('Walk-in / pickup')->default(true),

                    Forms\Components\TextInput::make('delivery_fee')
                        ->label('Service fee')
                        ->numeric()
                        ->default(0)
                        ->rule('gte:0'),

                    Forms\Components\TextInput::make('min_order_amount')
                        ->label('Minimum charge')
                        ->numeric()
                        ->default(0)
                        ->rule('gte:0'),

                    Forms\Components\TextInput::make('max_booking_days')
                        ->label('Max booking days ahead')
                        ->numeric()
                        ->integer()
                        ->default(60)
                        ->minValue(1)
                        ->helperText('Days in advance a patient can book (e.g., 60).')
                        ->required(),
                ])->columns(6),

            Forms\Components\Section::make('Specialties & Categories')
                ->schema([
                    Forms\Components\Select::make('services')
                        ->label('Specialties / Services')
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
                        ->helperText('Select specialties available at this clinic location.')
                        ->native(false),

                    Forms\Components\Select::make('cuisines')
                        ->label('Tags / Categories')
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
                    ->label('Logo')
                    ->disk('public')
                    ->circular()
                    ->height(36)
                    ->width(36)
                    ->toggleable(),

                Tables\Columns\ImageColumn::make('cover_image_path')
                    ->label('Cover')
                    ->disk('public')
                    ->height(48)
                    ->width(86)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Clinic')
                    ->searchable(query: function (Builder $q, string $s) use ($locale) {
                        return $q->whereHas('partner', fn ($qq) => $qq->where("name->{$locale}", 'like', "%{$s}%"));
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Location')
                    ->searchable(query: fn (Builder $q, string $s) => $q->where("name->{$locale}", 'like', "%{$s}%"))
                    ->sortable(),

                // localized city/block display
                Tables\Columns\TextColumn::make('city_id')
                    ->label('City')
                    ->formatStateUsing(fn ($state, Branch $record) => $record->city?->getTranslation('name', $locale))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('block_id')
                    ->label('Area / Block')
                    ->formatStateUsing(fn ($state, Branch $record) => $record->block?->getTranslation('name', $locale))
                    ->toggleable(),

                // NEW: License Number in Table
                Tables\Columns\TextColumn::make('license_number')
                    ->label('License')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_available')->boolean()->label('Available'),
                Tables\Columns\IconColumn::make('open_for_delivery')->boolean()->label('Home visit'),
                Tables\Columns\IconColumn::make('open_for_pickup')->boolean()->label('Walk-in'),

                Tables\Columns\TextColumn::make('max_booking_days')
                    ->label('Max days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label('Fee')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label('Minimum')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->alignRight(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('Clinic')
                    ->relationship('partner', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Partner $p) => (string) $p->getTranslation('name', $locale))
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_available')->label('Available'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edit location'),
                Tables\Actions\DeleteAction::make()->label('Delete location'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Delete selected'),
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
