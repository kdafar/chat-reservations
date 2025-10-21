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
    use Translatable; // <-- 2. Use the trait to make the resource translatable

    protected static ?string $model = Partner::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Partner')
                ->schema([
                    // 3. Combined separate EN/AR fields into one translatable input
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug((string) $state))),

                    // 4. Removed duplicate TextInput for logo_path, kept FileUpload
                    FileUpload::make('logo_path')
                        ->label('Logo')
                        ->disk('public')
                        ->directory('partner-logos')
                        ->image()
                        ->imageEditor()
                        ->columnSpanFull(),

                    // 5. Removed duplicate is_active toggle
                    Toggle::make('is_active')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Services')
                ->schema([
                    // 6. Updated 'services' Select to use the robust getSearchResultsUsing pattern
                    Forms\Components\Select::make('services')
                        ->label('Service Types')
                        ->multiple()
                        ->relationship('services')
                        ->getOptionLabelFromRecordUsing(fn (Service $record) => $record->getTranslation('name', app()->getLocale()))
                        ->getSearchResultsUsing(function (string $search) {
                            return Service::query()
                                ->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck('name->'.app()->getLocale(), 'id');
                        })
                        ->searchable()
                        ->helperText('Select all service types this partner offers (e.g., Restaurant, Grocery, Pharmacy).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')->label('Logo')->disk('public')->circular(),

                // 7. Updated 'name' column to display and search the current locale
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('name->'.app()->getLocale(), 'like', "%{$search}%");
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('services_list')
                    ->label('Services')
                    ->getStateUsing(function (Partner $record) {
                        return $record->services
                            ->map(fn ($s) => $s->getTranslation('name', app()->getLocale()))
                            ->implode(', ');
                    })
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_active')->boolean(),

                Tables\Columns\TextColumn::make('branches_count')
                    ->counts('branches')
                    ->label('Branches')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\PartnerResource\RelationManagers\UsersRelationManager::class,
        ];
    }

    // 8. Added the required method for the Translatable trait
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
