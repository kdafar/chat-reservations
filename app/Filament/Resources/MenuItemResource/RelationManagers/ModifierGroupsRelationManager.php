<?php

namespace App\Filament\Resources\MenuItemResource\RelationManagers;

use Filament\Resources\RelationManagers\Concerns\Translatable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModifierGroupsRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'modifierGroups';

    protected static ?string $title = 'Modifier Groups';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // Spatie: name resolves to current locale
                TextColumn::make('name')->label(__('Group'))->searchable()->sortable(),
                IconColumn::make('is_required')->label(__('Required'))->boolean(),
                TextColumn::make('min_choices')->label(__('Min')),
                TextColumn::make('max_choices')->label(__('Max')),
                TextColumn::make('options_count')->counts('options')->label(__('Options')),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label(__('Attach Group'))
                    ->recordSelectSearchColumns(['name']) // searches current locale
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        // Limit to groups from same branch as the owner item
                        $item = $this->getOwnerRecord(); // MenuItem

                        return $query->where('branch_id', $item->branch_id);
                    })
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}
