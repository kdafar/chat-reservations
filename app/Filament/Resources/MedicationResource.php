<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MedicationResource\Pages;
use App\Models\Branch;
use App\Models\Medication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MedicationResource extends Resource
{
    protected static ?string $model = Medication::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Clinical Library';

    protected static ?string $navigationLabel = 'Drug Formulary';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make('Drug')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(191),
                Forms\Components\TextInput::make('strength')->placeholder('500mg, 5mg/5ml')->maxLength(64),
                Forms\Components\TextInput::make('form')->placeholder('cap, tab, syrup')->maxLength(48),
                Forms\Components\TextInput::make('route')->placeholder('PO, IM, topical')->maxLength(32),
            ])->columns(2),

            Forms\Components\Section::make('Default dosing (the builder pre-fills these)')->schema([
                Forms\Components\TextInput::make('default_dose')->label('Dose')->placeholder('1, 2')->maxLength(64),
                Forms\Components\TextInput::make('default_frequency')->label('Frequency')->placeholder('q8h, BID')->maxLength(64),
                Forms\Components\TextInput::make('default_duration')->label('Duration')->placeholder('7 days')->maxLength(64),
                Forms\Components\TextInput::make('default_instructions')->label('Instructions')->placeholder('after food')->maxLength(191),
            ])->columns(2),

            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('branch_id')
                    ->label('Branch (blank = all branches)')
                    ->options(fn () => Branch::all()->mapWithKeys(fn ($b) => [$b->id => (string) $b->getTranslation('name', $locale)]))
                    ->searchable(),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('strength')->toggleable(),
                Tables\Columns\TextColumn::make('form')->toggleable(),
                Tables\Columns\TextColumn::make('default_frequency')->label('Freq')->toggleable(),
                Tables\Columns\TextColumn::make('default_duration')->label('Duration')->toggleable(),
                Tables\Columns\TextColumn::make('usage_count')->label('Used')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('usage_count', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedications::route('/'),
            'create' => Pages\CreateMedication::route('/create'),
            'edit' => Pages\EditMedication::route('/{record}/edit'),
        ];
    }
}
