<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WAMessageResource\Pages;
use App\Models\WAMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WAMessageResource extends Resource
{
    protected static ?string $model = WAMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Message Catalog';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('key')
                    ->datalist([
                        'booking.active_found', 'booking.ask_branch', 'booking.ask_size', 'booking.ask_date', 'booking.ask_time',
                        'booking.no_slots', 'booking.hold_taken', 'booking.confirmed_text',
                        'system.template_pending',
                    ])
                    ->required()
                    ->columnSpan(2),
                Forms\Components\Select::make('language')
                    ->options(['en' => 'English', 'ar' => 'Arabic'])
                    ->required(),
            ]),
            Forms\Components\Textarea::make('text')
                ->rows(8)
                ->helperText('Use variables like {branch}, {date}, {time}, {party_size}, {code}')
                ->required(),
            Forms\Components\Toggle::make('enabled')->default(true),
            Forms\Components\Section::make('Preview')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('preview.branch')->default('Salmiya')->label('branch'),
                    Forms\Components\TextInput::make('preview.date')->default(now()->toDateString())->label('date'),
                    Forms\Components\TextInput::make('preview.time')->default('19:30')->label('time'),
                    Forms\Components\TextInput::make('preview.party_size')->default('4')->label('party_size'),
                    Forms\Components\TextInput::make('preview.code')->default('BF-7X3Q')->label('code'),
                    Forms\Components\Placeholder::make('rendered')
                        ->label('Rendered')
                        ->content(function ($get) {
                            $txt = (string) $get('text');
                            $vars = (array) $get('preview') ?: [];
                            foreach ($vars as $k => $v) {
                                $txt = str_replace('{'.$k.'}', (string) $v, $txt);
                            }

                            return nl2br(e($txt));
                        }),
                ])->columns(6),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('language')->sortable(),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('language')->options(['en' => 'English', 'ar' => 'Arabic']),
                Tables\Filters\TernaryFilter::make('enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()->excludeAttributes(['key']),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWAMessages::route('/'),
            'create' => Pages\CreateWAMessage::route('/create'),
            'edit' => Pages\EditWAMessage::route('/{record}/edit'),
        ];
    }
}
