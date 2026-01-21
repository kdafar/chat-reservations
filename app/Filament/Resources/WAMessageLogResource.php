<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WAMessageLogResource\Pages;
use App\Models\WAMessageLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WAMessageLogResource extends Resource
{
    protected static ?string $model = WAMessageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'WA Logs';

    protected static ?int $navigationSort = 14;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('wa_message_id')->disabled(),
            Forms\Components\TextInput::make('phone')->disabled(),
            Forms\Components\Textarea::make('payload')->rows(16)->formatStateUsing(fn ($state) => json_encode((array) $state, JSON_PRETTY_PRINT))->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('created_at')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wa_message_id')->label('wamid')->copyable()->limit(24),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->since()->label('When'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWAMessageLogs::route('/'),
            'view' => Pages\ViewWAMessageLog::route('/{record}'),
        ];
    }
}
