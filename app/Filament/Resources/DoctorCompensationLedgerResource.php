<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoctorCompensationLedgerResource\Pages;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorCompensationLedgerResource extends Resource
{
    protected static ?string $model = DoctorCompensationLedger::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Clinic — Finance';

    protected static ?string $modelLabel = 'Doctor Compensation Ledger';

    protected static ?string $pluralModelLabel = 'Doctor Compensation Ledgers';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        // Read-only
        return $form->schema([
            Forms\Components\Section::make('Ledger (Read Only)')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('visit_id')->disabled(),
                    Forms\Components\TextInput::make('doctor_id')->disabled(),
                    Forms\Components\TextInput::make('branch_id')->disabled(),

                    Forms\Components\TextInput::make('type_snapshot')->disabled(),
                    Forms\Components\TextInput::make('basis_snapshot')->disabled(),
                    Forms\Components\TextInput::make('rate_snapshot')->disabled(),

                    Forms\Components\TextInput::make('fees_snapshot')->disabled(),
                    Forms\Components\TextInput::make('discount_snapshot')->disabled(),
                    Forms\Components\TextInput::make('cost_snapshot')->disabled(),
                    Forms\Components\TextInput::make('profit_snapshot')->disabled(),

                    Forms\Components\TextInput::make('doctor_cut_amount')->disabled(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('visit_id')
                    ->label('Visit')
                    ->formatStateUsing(function ($state) {
                        $v = Visit::query()->find($state);

                        return $v?->booking_code ?? ('Visit #'.$state);
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('doctor_id')
                    ->label('Doctor')
                    ->formatStateUsing(fn ($state) => Doctor::query()->find($state)?->name ?? ('Doctor #'.$state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('type_snapshot')->badge(),
                Tables\Columns\TextColumn::make('basis_snapshot')->badge(),

                Tables\Columns\TextColumn::make('fees_snapshot')->label('Fees')->numeric(3),
                Tables\Columns\TextColumn::make('profit_snapshot')->label('Profit')->numeric(3),
                Tables\Columns\TextColumn::make('doctor_cut_amount')->label('Doctor Cut')->numeric(3),

                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('doctor_id')
                    ->label('Doctor')
                    ->options(fn () => Doctor::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($d) => [$d->id => ($d->name ?? ('Doctor #'.$d->id))])
                        ->all()
                    ),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDoctorCompensationLedgers::route('/'),
            'view' => Pages\ViewDoctorCompensationLedger::route('/{record}'),
        ];
    }
}
