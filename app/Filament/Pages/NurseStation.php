<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VisitResource;
use App\Models\Visit;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class NurseStation extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $title = 'Nurse Station';

    protected static ?int $navigationSort = 16;

    protected static string $view = 'filament.pages.nurse-station';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_nurse_station');
    }

    protected function tz(): string
    {
        return (string) config('app.timezone', 'Asia/Kuwait');
    }

    protected function baseQuery(): Builder
    {
        $tz = $this->tz();
        $today = Carbon::now($tz)->toDateString();

        // “Nurse work happens around today’s active visits”
        // We prefer checked_in_at; fall back to queued_at; fall back to created_at.
        return Visit::query()
            ->with(['patient', 'doctor', 'branch', 'room'])
            ->where(function (Builder $q) use ($today) {
                $q->whereDate('checked_in_at', $today)
                    ->orWhereDate('queued_at', $today)
                    ->orWhereDate('created_at', $today);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->baseQuery())
            ->defaultSort('queued_at', 'asc')
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('queued_at')
                    ->label('Queued')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked-in')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->description(fn (Visit $r) => $r->patient?->phone)
                    ->wrap(),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch')
                    ->formatStateUsing(fn ($state, Visit $r) => $r->branch?->localized_name ?? ('#'.$state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('room.name')
                    ->label('Room')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'awaiting_doctor' => 'warning',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'no_show' => 'warning',
                        'created' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'awaiting_doctor' => 'Awaiting Doctor',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'created' => 'Created',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No-show',
                    ]),

                Tables\Filters\SelectFilter::make('doctor_id')
                    ->label('Doctor')
                    ->relationship('doctor', 'name'),

                Tables\Filters\Filter::make('today_only')
                    ->label('Today Only')
                    ->default()
                    ->query(fn (Builder $q) => $q), // baseQuery already scopes to today
            ])
            ->actions([
                Tables\Actions\Action::make('open_visit')
                    ->label('Open Visit')
                    ->icon('heroicon-o-folder-open')
                    ->color('primary')
                    ->url(fn (Visit $r) => VisitResource::getUrl('edit', ['record' => $r->id]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('open_items')
                    ->label('Items')
                    ->icon('heroicon-o-archive-box')
                    ->color('success')
                    ->url(fn (Visit $r) => VisitResource::getUrl('edit', ['record' => $r->id])
                            .'?activeRelationManager=0&scrollTo=relations')
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('open_followup')
                    ->label('Follow-up')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->url(fn (Visit $r) => VisitResource::getUrl('edit', ['record' => $r->id])
                            .'?activeRelationManager=2&scrollTo=relations')
                    ->openUrlInNewTab(),
            ]);
    }
}
