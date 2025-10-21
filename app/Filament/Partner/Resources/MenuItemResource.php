<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\ScopesToActivePartner;
use App\Filament\Partner\Resources\MenuItemResource\Pages;
use App\Filament\Partner\Resources\MenuItemResource\RelationManagers\ModifierGroupsRelationManager; // Assuming this exists in Partner namespace
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection; // Don't forget to import ModifierGroup
use App\Models\ModifierGroup; // Don't forget to import Branch for options
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable; // Import Translatable
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // Import Builder for type hinting

class MenuItemResource extends Resource
{
    // Use the Translatable concern
    use ScopesToActivePartner;
    use Translatable; // And your existing partner scope

    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    // Make sure these are properly translated in your language files
    protected static ?string $modelLabel = 'Menu Item';

    protected static ?string $pluralModelLabel = 'Menu Items';

    protected static ?string $navigationLabel = 'Menu Items';

    /** Used by the LocaleSwitcher on the pages */
    public static function getTranslatableLocales(): array
    {
        // Define your supported locales here, or get from config
        return config('app.locales', ['en', 'ar']);
    }

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();
        $activePartnerId = (int) session('active_partner_id');

        return $form->schema([
            Forms\Components\Section::make(__('Item Basics'))
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->relationship('branch', 'name') // Assuming Branch model also uses Spatie Translatable
                        ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->getTranslation('name', $locale)) // Localize Branch name
                        ->label(__('Branch'))
                        ->searchable()
                        ->required()
                        ->live()
                        // Scope to active partner's branches
                        ->options(function () use ($activePartnerId, $locale) {
                            return Branch::query()
                                ->where('partner_id', $activePartnerId)
                                ->get()
                                ->mapWithKeys(fn (Branch $branch) => [$branch->id => $branch->getTranslation('name', $locale)])
                                ->toArray();
                        }),

                    Forms\Components\Select::make('menu_section_id')
                        ->label(__('Section'))
                        ->options(function (callable $get) use ($locale) {
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
                                    fn (MenuSection $s) => [$s->id => $s->getTranslation('name', $locale)] // Use current locale
                                )
                                ->toArray();
                        })
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('sku')
                        ->label('SKU') // Changed to `__('SKU')` for consistency if you want it translatable
                        ->maxLength(100),

                    Forms\Components\FileUpload::make('image_path')
                        ->image()
                        ->directory('menu-items')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->label(__('Image')),
                ])
                ->columns(2),

            // When `Translatable` concern is used, `TextInput::make('name')` automatically
            // handles loading and saving the current locale's translation based on the locale switcher.
            // No need for separate tabs for each locale here.
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

            // If ModifierGroups are also translatable, ensure their names are handled:
            Forms\Components\Section::make(__('Modifier Groups'))
                ->schema([
                    Forms\Components\Select::make('modifierGroups')
                        ->label(__('Attach Modifier Groups'))
                        ->multiple()
                        ->relationship('modifierGroups', 'name')
                        // Scope modifier groups to the selected branch if available
                        ->options(function (callable $get) use ($locale) {
                            $branchId = (int) $get('branch_id');
                            if (! $branchId) {
                                return [];
                            }

                            return ModifierGroup::query()
                                ->where('branch_id', $branchId) // Assuming ModifierGroup has a branch_id
                                ->get()
                                ->mapWithKeys(fn (ModifierGroup $g) => [$g->id => $g->getTranslation('name', $locale)])
                                ->toArray();
                        })
                        ->preload()
                        ->searchable()
                        ->helperText(__('Optional: attach modifier groups to this item.')),
                ])->hidden(fn (callable $get) => ! $get('branch_id')), // Hide until a branch is selected

        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $locale = app()->getLocale();

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->circular()->label(__('Image')), // Label translatable
                // When `Translatable` concern is used, Filament automatically displays the correct locale
                // for TextColumn::make('name') and its relationships.
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('section.menu.branch.name')
                    ->label(__('Branch'))
                    ->formatStateUsing(fn (?string $state) => $state ? (json_decode($state)?->{$locale} ?? $state) : null) // Assuming Branch name is translatable
                    ->toggleable(),
                Tables\Columns\TextColumn::make('section.name')
                    ->label(__('Section'))
                    ->formatStateUsing(fn (?string $state) => $state ? (json_decode($state)?->{$locale} ?? $state) : null) // Assuming Section name is translatable
                    ->toggleable(),
                Tables\Columns\TextColumn::make('price')->label(__('Price'))->money('kwd', true),
                Tables\Columns\IconColumn::make('is_available')->label(__('Available'))->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label(__('Updated')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    // Scope branch filter options to the active partner
                    ->options(function () use ($activePartnerId, $locale) {
                        return Branch::query()
                            ->where('partner_id', $activePartnerId)
                            ->get()
                            ->mapWithKeys(fn (Branch $branch) => [$branch->id => $branch->getTranslation('name', $locale)])
                            ->toArray();
                    })
                    ->label(__('Branch')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('Edit')), // Translatable label
                Tables\Actions\DeleteAction::make()->label(__('Delete')), // Translatable label
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label(__('Delete Selected')), // Translatable label
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
