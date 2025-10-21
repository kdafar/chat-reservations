<?php

namespace App\Filament\Resources\BranchIntegrationResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Sync Logs';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('status')->badge()
                ->colors(['success' => 'success', 'danger' => 'failed', 'warning' => 'running']),
            Tables\Columns\TextColumn::make('categories')->label('Cats'),
            Tables\Columns\TextColumn::make('items')->label('Items'),
            Tables\Columns\TextColumn::make('started_at')->since()->label('Started'),
            Tables\Columns\TextColumn::make('finished_at')->since()->label('Finished'),
            Tables\Columns\TextColumn::make('message')->limit(60),
        ])
            ->defaultSort('id', 'desc');
    }
}
