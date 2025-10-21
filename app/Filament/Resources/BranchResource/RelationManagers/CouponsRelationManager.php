<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class CouponsRelationManager extends RelationManager
{
    protected static string $relationship = 'coupons';

    protected static ?string $title = 'Coupons';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->searchable(),
            Tables\Columns\TextColumn::make('discount_type'),
            Tables\Columns\TextColumn::make('discount_amount')->label('Amt'),
            Tables\Columns\TextColumn::make('discount_percent')->label('%'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->headerActions([
            Tables\Actions\AttachAction::make()->preloadRecordSelect()->recordSelectSearchColumns(['code']),
        ])->actions([
            Tables\Actions\DetachAction::make(),
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\DetachBulkAction::make(),
        ]);
    }
}
