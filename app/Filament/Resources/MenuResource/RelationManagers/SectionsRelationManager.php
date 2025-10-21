<?php

namespace App\Filament\Resources\MenuResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\Concerns\Translatable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class SectionsRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'sections';

    protected static ?string $title = 'Sections';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Name')
                ->required(),

            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->label('Section')
                ->sortable()
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where('name->'.$this->getActiveLocale(), 'like', "%{$search}%");
                }),
            TextColumn::make('sort_order')->sortable(),
            TextColumn::make('created_at')->dateTime('Y-m-d H:i')->label('Created'),
        ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                // This is the only LocaleSwitcher you need
                Tables\Actions\LocaleSwitcher::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // The modalHeaderActions() call has been removed from here
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('sort_order');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }
}
