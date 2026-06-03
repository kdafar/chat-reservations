<?php

namespace App\Filament\Resources\Insurance;

use App\Filament\Resources\Insurance\InsurerResource\Pages;
use App\Filament\Resources\Insurance\InsurerResource\RelationManagers\PlansRelationManager;
use App\Models\Insurance\Insurer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InsurerResource extends Resource
{
    protected static ?string $model = Insurer::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'insurance/insurers';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.insurance');
    }

    public static function getNavigationLabel(): string
    {
        return 'Insurers';
    }

    public static function getModelLabel(): string
    {
        return 'Insurer';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Insurers';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Insurer')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name (English)')
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('name_ar')
                        ->label('Name (Arabic)')
                        ->maxLength(191),

                    Forms\Components\TextInput::make('code')
                        ->label('Code')
                        ->required()
                        ->maxLength(32)
                        ->unique(ignoreRecord: true)
                        ->helperText('Short reference (e.g. GIG, WARBA).'),

                    Forms\Components\TextInput::make('tax_id')
                        ->label('Tax ID')
                        ->maxLength(64),

                    Forms\Components\TextInput::make('contact_email')
                        ->label('Contact Email')
                        ->email()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('contact_phone')
                        ->label('Contact Phone')
                        ->tel()
                        ->maxLength(64),

                    Forms\Components\Textarea::make('address')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Settings')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('payment_terms_days')
                        ->label('Payment Terms (days)')
                        ->numeric()
                        ->minValue(0)
                        ->default(30),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Insurer $r) => $r->name_ar),

                Tables\Columns\TextColumn::make('payment_terms_days')
                    ->label('Payment Terms')
                    ->suffix(' days')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('plans_count')
                    ->label('Plans')
                    ->alignEnd()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('policies_count')
                    ->label('Policies')
                    ->alignEnd()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Email')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Phone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No insurers yet')
            ->emptyStateDescription('Add the insurance companies your clinic works with.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['plans', 'policies']);
    }

    public static function getRelations(): array
    {
        return [
            PlansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInsurers::route('/'),
            'create' => Pages\CreateInsurer::route('/create'),
            'edit' => Pages\EditInsurer::route('/{record}/edit'),
        ];
    }
}
