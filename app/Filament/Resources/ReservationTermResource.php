<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationTermResource\Pages;
use App\Models\Branch;
use App\Models\ReservationTerm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReservationTermResource extends Resource
{
    protected static ?string $model = ReservationTerm::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    // Rename group for clinic
    protected static ?string $navigationGroup = 'Clinic — Compliance';

    protected static ?int $navigationSort = 10;

    // Rename resource label for UI only
    protected static ?string $modelLabel = 'Consent Terms';

    protected static ?string $pluralModelLabel = 'Consent Terms';

    public static function getNavigationLabel(): string
    {
        return 'Consent Terms';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Scope')
                ->description('Choose where these clinic terms apply')
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label('Clinic Branch (optional; leave empty for All Branches)')
                        ->options(Branch::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->rules([
                            fn (Get $get, Form $form): \Illuminate\Contracts\Validation\Rule => function (string $attribute, $value, \Closure $fail) use ($get, $form) {
                                if (! $get('is_active')) {
                                    return;
                                }

                                $query = ReservationTerm::query()
                                    ->where('is_active', true)
                                    ->where('branch_id', $value);

                                $record = $form->getRecord();

                                if ($record) {
                                    $query->where('id', '!=', $record->getKey());
                                }

                                if ($query->exists()) {
                                    $branchName = $value ? Branch::find($value)?->name : 'All Branches';
                                    $fail("An active consent terms entry for '{$branchName}' already exists.");
                                }
                            },
                        ]),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Toggle::make('terms_required')
                        ->label('Require patient acceptance')
                        ->helperText('If enabled, patients must accept these terms before confirming an appointment.')
                        ->default(false),
                ])
                ->columns(3),

            Forms\Components\Section::make('English')
                ->description('Patient-facing consent text in English')
                ->schema([
                    Forms\Components\TextInput::make('label_en')
                        ->label('Checkbox label (EN)')
                        ->required(),

                    Forms\Components\Textarea::make('text_en')
                        ->label('Consent / Terms text (EN)')
                        ->rows(8),
                ]),

            Forms\Components\Section::make('Arabic')
                ->description('Patient-facing consent text in Arabic')
                ->schema([
                    Forms\Components\TextInput::make('label_ar')
                        ->label('Checkbox label (AR)')
                        ->required(),

                    Forms\Components\Textarea::make('text_ar')
                        ->label('Consent / Terms text (AR)')
                        ->rows(8),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Clinic Branch')
                    ->formatStateUsing(fn ($state) => $state ?: 'All Branches')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\IconColumn::make('terms_required')
                    ->boolean()
                    ->label('Required'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Last Updated'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edit'),
                Tables\Actions\DeleteAction::make()->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Delete selected'),
            ])
            ->emptyStateHeading('No consent terms yet')
            ->emptyStateDescription('Create clinic consent terms to show during appointment booking.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReservationTerms::route('/'),
        ];
    }
}
