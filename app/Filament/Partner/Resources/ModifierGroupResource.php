<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\ScopesToActivePartner;
use App\Filament\Partner\Resources\ModifierGroupResource\Pages;
use App\Filament\Partner\Resources\ModifierGroupResource\RelationManagers\OptionsRelationManager;
use App\Models\Branch;
use App\Models\ModifierGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ModifierGroupResource extends Resource
{
    use ScopesToActivePartner;

    protected static ?string $model = ModifierGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Select::make('branch_id')
                ->label(__('Branch'))
                ->required()
                ->options(
                    Branch::query()
                        ->where('partner_id', (int) session('active_partner_id'))
                        ->orderBy("name->$locale")
                        ->get(['id', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->name])->toArray()
                )
                ->searchable(),

            Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
            Forms\Components\Toggle::make('is_required')->label(__('Required'))->default(false),
            Forms\Components\TextInput::make('min_choices')->label(__('Min choices'))->numeric()->default(0),
            Forms\Components\TextInput::make('max_choices')->label(__('Max choices'))->numeric()->default(1),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->formatStateUsing(fn (ModifierGroup $record) => $record->branch?->name ?? '—'),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
                Tables\Columns\IconColumn::make('is_required')->label(__('Required'))->boolean(),
                Tables\Columns\TextColumn::make('min_choices')->label(__('Min')),
                Tables\Columns\TextColumn::make('max_choices')->label(__('Max')),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            OptionsRelationManager::class,
        ];
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
