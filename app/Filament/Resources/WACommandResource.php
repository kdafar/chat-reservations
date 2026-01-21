<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WACommandResource\Pages;
use App\Models\WACommand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WACommandResource extends Resource
{
    protected static ?string $model = WACommand::class;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Commands';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Command')
                ->schema([
                    Forms\Components\TextInput::make('keyword')
                        ->label('Keyword')
                        ->required()
                        ->maxLength(64)
                        ->helperText('e.g., hi, start, reset, help, مرحبا'),

                    Forms\Components\Select::make('language')
                        ->label('Language')
                        ->options(['en' => 'English', 'ar' => 'Arabic'])
                        ->native(false)
                        ->placeholder('Any'),

                    Forms\Components\Select::make('action')
                        ->label('Action')
                        ->options([
                            'reset' => 'Reset & Start',
                            'start' => 'Start',
                            'menu' => 'Show Menu/Help',
                            'jump' => 'Jump to State',
                        ])
                        ->required()
                        ->reactive(),

                    Forms\Components\TextInput::make('params.state')
                        ->label('Target State (for Jump)')
                        ->placeholder('SELECT_BRANCH / PARTY_SIZE / DATE_PICK / ...')
                        ->visible(fn ($get) => $get('action') === 'jump'),

                    Forms\Components\TextInput::make('priority')
                        ->numeric()
                        ->default(100)
                        ->helperText('Lower = matched first'),
                    Forms\Components\Toggle::make('enabled')->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('keyword')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('language')->label('Lang')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('action')->badge()->sortable(),
                Tables\Columns\TextColumn::make('params.state')->label('Jump State')->toggleable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\ToggleColumn::make('enabled')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('language')->options(['en' => 'English', 'ar' => 'Arabic']),
                Tables\Filters\TernaryFilter::make('enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWACommands::route('/'),
            'create' => Pages\CreateWACommand::route('/create'),
            'edit' => Pages\EditWACommand::route('/{record}/edit'),
        ];
    }
}
