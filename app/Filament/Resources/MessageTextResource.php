<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageTextResource\Pages;
use App\Models\MessageText;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessageTextResource extends Resource
{
    protected static ?string $model = MessageText::class;

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Message Catalog';

    protected static ?string $pluralModelLabel = 'Message Catalog';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('key')
                ->label('Key')
                ->placeholder('e.g. branch.choose_header')
                ->required()
                ->unique(ignoreRecord: true)
                ->columnSpanFull(),

            Select::make('locale')
                ->options(['en' => 'English', 'ar' => 'Arabic'])
                ->required()
                ->default('en'),

            Textarea::make('value')
                ->rows(6)
                ->placeholder('Text (supports {placeholders})')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->searchable()->sortable()->limit(40),
            TextColumn::make('locale')->badge(),
            TextColumn::make('value')->label('Text')->limit(80)->toggleable(),
            TextColumn::make('updated_at')->since()->sortable(),
        ])->filters([])->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ])->bulkActions([
            Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageTexts::route('/'),
            'create' => Pages\CreateMessageText::route('/create'),
            'edit' => Pages\EditMessageText::route('/{record}/edit'),
        ];
    }
}
