<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomepageSectionResource\Pages;
use App\Filament\Resources\HomepageSectionResource\RelationManagers\FeaturedCitiesRelationManager;
use App\Models\HomepageSection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HomepageSectionResource extends Resource
{
    use Translatable;

    protected static ?string $model = HomepageSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('Content & Media'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('Title'))
                        ->required()
                        ->maxLength(160),

                    TextInput::make('subtitle')
                        ->label(__('Subtitle'))
                        ->maxLength(200),

                    FileUpload::make('hero_image_path')
                        ->label(__('Hero Image'))
                        ->image()
                        ->directory('homepage')
                        ->visibility('public')
                        ->imageEditor()
                        ->imageEditorAspectRatios(['16:9', '3:1'])
                        ->nullable()
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make(__('Visible Sections'))
                ->description(__('Control which dynamic sections appear on the homepage.'))
                ->schema([
                    Toggle::make('show_featured_cuisines')->label(__('Show Featured Cuisines'))->default(true),
                    Toggle::make('show_featured_partners')->label(__('Show Featured Partners'))->default(true),
                    Toggle::make('show_trending_items')->label(__('Show Trending Items'))->default(true),
                ])->columns(3),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Eager load the count of featured cities for the table column
        return parent::getEloquentQuery()
            ->withCount('featuredCityLinks');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('hero_image_path')->label(__('Hero'))->square(),

                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('title->'.app()->getLocale(), 'like', "%{$search}%");
                    }),

                // Column to show the count of featured cities
                TextColumn::make('featured_city_links_count')
                    ->label(__('Featured Cities'))
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                IconColumn::make('show_featured_cuisines')->boolean()->label(__('Cuisines')),
                IconColumn::make('show_featured_partners')->boolean()->label(__('Partners')),
                IconColumn::make('show_trending_items')->boolean()->label(__('Trending')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label(__('Create Homepage Content')),
            ]);
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getRelations(): array
    {
        return [
            FeaturedCitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageHomepage::route('/'),
            'edit' => Pages\EditHomepageSection::route('/{record}/edit'),
        ];
    }
}
