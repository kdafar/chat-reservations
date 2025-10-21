<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Filament\Resources\MenuResource\RelationManagers\SectionsRelationManager;
use App\Models\Branch;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuResource extends Resource
{
    use Translatable;

    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('branch_id')
                ->relationship('branch') // Use the simple relationship name
                ->required()
                // Use getSearchResultsUsing for translatable search
                ->getSearchResultsUsing(fn (string $search) => Branch::query()
                    ->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck('name->'.app()->getLocale(), 'id')
                )
                // Use getOptionLabelFromRecordUsing for translatable display
                ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->getTranslation('name', app()->getLocale())),

            // BEFORE: Two separate fields for name.en and name.ar
            // AFTER: One field with the translatable() method
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->required(),

            // BEFORE: Two separate fields for description.en and description.ar
            // AFTER: One field with the translatable() method
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->rows(2),

            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            // BEFORE: branch.name->en (hardcoded locale)
            // AFTER: branch.name (automatically uses current locale)
            Tables\Columns\TextColumn::make('branch.name')
                ->label('Branch')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    // Custom search for the translated relationship field
                    return $query->whereHas('branch', fn (Builder $q) => $q->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                    );
                }),

            // BEFORE: name->en (hardcoded locale)
            // AFTER: name (automatically uses current locale)
            Tables\Columns\TextColumn::make('name')
                ->label('Menu')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    // Custom search for the translated field on this model
                    return $query->where('name->'.app()->getLocale(), 'like', "%{$search}%");
                })
                ->sortable(),

            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->since(),
        ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
        ];
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
