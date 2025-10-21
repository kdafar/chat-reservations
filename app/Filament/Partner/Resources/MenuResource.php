<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\ScopesToActivePartner;
use App\Filament\Partner\Resources\MenuResource\Pages;
use App\Filament\Partner\Resources\MenuResource\RelationManagers\SectionsRelationManager;
use App\Models\Branch;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    use ScopesToActivePartner;

    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make(__('Menu'))
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label(__('Branch'))
                        ->required()
                        ->options(
                            Branch::query()
                                ->where('partner_id', (int) session('active_partner_id'))
                                ->orderBy("name->$locale")
                                ->get(['id', 'name'])
                                ->mapWithKeys(fn (Branch $b) => [$b->id => $b->name])
                                ->toArray()
                        )
                        ->searchable(),

                    Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                    Forms\Components\Textarea::make('description')->label(__('Description'))->rows(2),
                    Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')->label(__('Branch'))
                    ->formatStateUsing(fn ($state, Menu $m) => $m->branch?->name ?? '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label(__('Active'))->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label(__('Updated')),
            ])
            ->defaultSort('id', 'desc')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class, // manage sections under a menu
        ];
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
