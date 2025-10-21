<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Filament\Resources\MenuItemResource\RelationManagers\ModifierGroupsRelationManager;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    use Translatable;

    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Menu Items';

    /** Used by the LocaleSwitcher on the pages */
    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Item Basics')
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->relationship('branch', 'name') // if Branch uses Spatie; if not, change to 'name->en'
                        ->label(__('Branch'))
                        ->searchable()
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('menu_section_id')
                        ->label(__('Section'))
                        ->options(function (callable $get) {
                            $branchId = (int) $get('branch_id');
                            if (! $branchId) {
                                return [];
                            }

                            $menuIds = Menu::query()
                                ->where('branch_id', $branchId)
                                ->pluck('id');

                            return MenuSection::query()
                                ->whereIn('menu_id', $menuIds)
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(
                                    fn ($s) => [$s->id => $s->getTranslation('name', app()->getLocale())]
                                )
                                ->toArray();
                        })
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('sku')
                        ->label('SKU')
                        ->maxLength(100),

                    Forms\Components\FileUpload::make('image_path')
                        ->image()
                        ->directory('menu-items')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->label(__('Image')),
                ])
                ->columns(2),

            // Bind directly to Spatie translatable attributes
            Forms\Components\Section::make(__('Translations'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->required(),
                    Forms\Components\Textarea::make('description')
                        ->label(__('Description'))
                        ->rows(2),
                ])
                ->columns(1),

            Forms\Components\Section::make(__('Pricing & Availability'))
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->numeric()
                        ->step('0.001')
                        ->required()
                        ->label(__('Price (KWD)')),
                    Forms\Components\Toggle::make('is_available')
                        ->label(__('Available'))
                        ->default(true),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->circular()->label('Img'),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('section.menu.branch.name')->label(__('Branch'))->toggleable(),
                Tables\Columns\TextColumn::make('section.name')->label(__('Section'))->toggleable(),
                Tables\Columns\TextColumn::make('price')->label(__('Price'))->money('kwd', true),
                Tables\Columns\IconColumn::make('is_available')->label(__('Available'))->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label(__('Updated')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name') // if Branch isn’t translatable, use 'name->en'
                    ->label(__('Branch')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ModifierGroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
