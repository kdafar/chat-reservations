<?php

namespace App\Filament\Resources\Lab;

use App\Filament\Resources\Lab\LabTestResource\Pages;
use App\Models\Lab\LabTest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LabTestResource extends Resource
{
    protected static ?string $model = LabTest::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_operations') ?: 'Clinic Operations';
    }

    public static function getNavigationLabel(): string
    {
        return 'Lab Tests';
    }

    public static function getModelLabel(): string
    {
        return 'Lab Test';
    }

    /**
     * Catalog management is an admin task. Reception + doctors can READ the
     * test list via the LabOrders repeater dropdown (which queries the model
     * directly, not the resource), but they don't need this navigation entry
     * or the catalog edit page. Gating on `update_lab_tests` so only roles
     * that can write to the catalog see the resource at all.
     */
    public static function canAccess(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->can('update_lab_tests'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Test')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(32)
                        ->helperText('Short code (e.g. CBC, GLU, HBA1C).'),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(191),

                    Forms\Components\Select::make('branch_id')
                        ->label('Branch (leave empty = all branches)')
                        ->relationship('branch', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\TextInput::make('specimen_type')
                        ->maxLength(64)
                        ->placeholder('Blood, Urine, Swab...'),

                    Forms\Components\TextInput::make('unit')
                        ->maxLength(32)
                        ->placeholder('mg/dL, %, g/L'),

                    Forms\Components\TextInput::make('reference_range')
                        ->maxLength(191)
                        ->placeholder('70-100 mg/dL'),

                    Forms\Components\TextInput::make('default_price')
                        ->numeric()
                        ->step('0.001')
                        ->minValue(0)
                        ->default(0)
                        ->prefix('KWD'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('specimen_type')->toggleable(),
                Tables\Columns\TextColumn::make('unit')->toggleable(),
                Tables\Columns\TextColumn::make('reference_range')->toggleable(),
                Tables\Columns\TextColumn::make('default_price')->numeric(3)->label('Price (KWD)'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->placeholder('All'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription(function (\App\Models\Lab\LabTest $record): string {
                        $count = $record->orderItems()->count();
                        $base = 'Archives this test so it no longer appears when ordering new lab work.';
                        if ($count > 0) {
                            return $base." It will still show on the {$count} historical result line(s) — use Restore to bring it back.";
                        }
                        return $base;
                    }),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)
                    ->before(function (\App\Models\Lab\LabTest $record, Tables\Actions\ForceDeleteAction $action) {
                        $count = $record->orderItems()->count();
                        if ($count > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot permanently delete this test')
                                ->body("It's referenced by {$count} historical lab result line(s). Use Archive (soft-delete) instead.")
                                ->danger()
                                ->persistent()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ])
            ->emptyStateHeading('No lab tests defined')
            ->emptyStateDescription('Add tests to your catalog before ordering them on visits.')
            ->emptyStateIcon('heroicon-o-beaker');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabTests::route('/'),
            'create' => Pages\CreateLabTest::route('/create'),
            'edit' => Pages\EditLabTest::route('/{record}/edit'),
        ];
    }
}
