<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use App\Models\Gateway;
use Filament\Forms;
use Filament\Forms\Components as F;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns as C;

class GatewayAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'gatewayAccounts';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            F\Select::make('gateway_id')
                ->label('Gateway')
                ->options(Gateway::query()->pluck('name', 'id'))
                ->searchable()
                ->required(),

            F\TextInput::make('display_name')
                ->label('Display Name')
                ->required(),

            F\KeyValue::make('credentials')
                ->label('Credentials (JSON)')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->reorderable(),

            F\TextInput::make('currency')
                ->default('KWD')
                ->required(),

            F\Toggle::make('is_active')->default(true),
            F\Toggle::make('is_default')->default(false),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                C\TextColumn::make('display_name')->searchable()->label('Account'),
                C\TextColumn::make('gateway.name')->label('Gateway'),
                C\TextColumn::make('currency'),
                C\IconColumn::make('is_active')->boolean(),
                C\IconColumn::make('is_default')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['owner_type'] = 'branch';
                        $data['branch_id'] = $this->ownerRecord->id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
