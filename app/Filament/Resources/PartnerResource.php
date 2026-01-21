<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Partner;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PartnerResource extends Resource
{
    use Translatable;

    protected static ?string $model = Partner::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    // UI rename only
    protected static ?string $navigationGroup = 'Clinic — Setup';

    // Optional (nice): rename sidebar label/title
    protected static ?string $navigationLabel = 'Clinics';

    protected static ?string $modelLabel = 'Clinic';

    protected static ?string $pluralModelLabel = 'Clinics';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Clinic Details')
                ->tabs([
                    // Tab 1: Basic Info
                    Forms\Components\Tabs\Tab::make('General Info')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Clinic Name')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('slug')
                                ->label('Clinic Code / Slug')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug((string) $state)))
                                ->helperText('Used in links/URLs. Auto-generated from clinic name.'),

                            FileUpload::make('logo_path')
                                ->label('Clinic Logo')
                                ->disk('public')
                                ->directory('partner-logos')
                                ->image()
                                ->imageEditor()
                                ->columnSpanFull(),

                            Forms\Components\Section::make('Specialties')
                                ->schema([
                                    Forms\Components\Select::make('services')
                                        ->label('Medical Services')
                                        ->multiple()
                                        ->relationship('services')
                                        ->getOptionLabelFromRecordUsing(
                                            fn (Service $record) => $record->getTranslation('name', app()->getLocale())
                                        )
                                        ->getSearchResultsUsing(function (string $search) {
                                            $locale = app()->getLocale();

                                            return Service::query()
                                                ->where("name->{$locale}", 'like', "%{$search}%")
                                                ->limit(50)
                                                ->pluck("name->{$locale}", 'id')
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->helperText('Select the clinic specialties (e.g., Dental, Dermatology, Pediatrics).'),
                                ]),

                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),

                    // Tab 2: Print & Legal Details (For Letterheads)
                    Forms\Components\Tabs\Tab::make('Print & Legal')
                        ->icon('heroicon-o-printer')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('website')
                                        ->prefix('https://')
                                        ->placeholder('www.myclinic.com'),

                                    Forms\Components\TextInput::make('email')
                                        ->email()
                                        ->placeholder('info@myclinic.com'),

                                    Forms\Components\TextInput::make('license_number')
                                        ->label('MOH / Commercial License')
                                        ->placeholder('Lic-12345'),
                                ]),

                            Forms\Components\Textarea::make('footer_text')
                                ->label('Print Footer / Disclaimer')
                                ->rows(3)
                                ->placeholder('e.g. "We care for your health. For emergencies call 112."')
                                ->helperText('This text will appear at the bottom of prescriptions and invoices.')
                                ->columnSpanFull(),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Clinic')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $locale = app()->getLocale();

                        return $query->where("name->{$locale}", 'like', "%{$search}%");
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('license_number')
                    ->label('License')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('services_list')
                    ->label('Specialties')
                    ->getStateUsing(function (Partner $record) {
                        return $record->services
                            ->map(fn ($s) => $s->getTranslation('name', app()->getLocale()))
                            ->implode(', ');
                    })
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('branches_count')
                    ->counts('branches')
                    ->label('Branches')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edit clinic'),
                Tables\Actions\DeleteAction::make()->label('Delete clinic'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Delete selected'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\PartnerResource\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }
}
