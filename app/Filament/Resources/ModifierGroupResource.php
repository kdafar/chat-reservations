<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModifierGroupResource\Pages;
use App\Filament\Resources\ModifierGroupResource\RelationManagers\OptionsRelationManager;
use App\Models\Branch;
use App\Models\ModifierGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModifierGroupResource extends Resource
{
    use Translatable;

    protected static ?string $model = ModifierGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('branch_id')
                ->relationship('branch') // Use the simple relationship name
                ->required()
                ->searchable()
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

            Forms\Components\Toggle::make('is_required'),
            Forms\Components\TextInput::make('min_choices')->numeric()->default(0),
            Forms\Components\TextInput::make('max_choices')->numeric()->default(1),
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
                ->label('Group')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    // Custom search for the translated field on this model
                    return $query->where('name->'.app()->getLocale(), 'like', "%{$search}%");
                })
                ->sortable(),

            Tables\Columns\IconColumn::make('is_required')->boolean(),
            Tables\Columns\TextColumn::make('min_choices'),
            Tables\Columns\TextColumn::make('max_choices'),
            Tables\Columns\TextColumn::make('options_count')->counts('options')->label('Options'),
        ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [OptionsRelationManager::class];
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModifierGroups::route('/'),
            'create' => Pages\CreateModifierGroup::route('/create'),
            'edit' => Pages\EditModifierGroup::route('/{record}/edit'),
        ];
    }
}
