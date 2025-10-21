<?php

namespace App\Filament\Partner\Resources\MenuResource\RelationManagers;

use App\Models\MenuSection;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Sections';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
            Forms\Components\TextInput::make('sort_order')->label(__('Sort'))->numeric()->default(0),
        ])->columns(2);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
                Tables\Columns\TextColumn::make('sort_order')->label(__('Sort'))->sortable(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label(__('Items'))->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                // quick link to manage items of this section:
                Tables\Actions\Action::make('manageItems')
                    ->label(__('Manage Items'))
                    ->icon('heroicon-o-squares-2x2')
                    ->url(fn (MenuSection $s) => \App\Filament\Partner\Resources\MenuItemResource::getUrl('index', ['section_id' => $s->id]))
                    ->openUrlInNewTab(),
            ]);
    }
}
