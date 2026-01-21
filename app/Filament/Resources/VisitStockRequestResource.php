<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitStockRequestResource\Pages;
use App\Filament\Resources\VisitStockRequestResource\RelationManagers\LinesRelationManager;
use App\Models\VisitStockRequest;
use App\Services\Clinic\VisitStockRequestService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitStockRequestResource extends Resource
{
    protected static ?string $model = VisitStockRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Clinic — Inventory';

    protected static ?string $modelLabel = 'Stock Request';

    protected static ?string $pluralModelLabel = 'Stock Requests';

    protected static ?int $navigationSort = 30;

    public static function form(Forms\Form $form): Forms\Form
    {
        // We generally do not create/edit these manually.
        // Keep form minimal for View page / safety.
        return $form->schema([
            Forms\Components\Section::make('Request')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('id')->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            VisitStockRequest::STATUS_PENDING => 'Pending',
                            VisitStockRequest::STATUS_FULFILLED => 'Fulfilled',
                            VisitStockRequest::STATUS_CANCELLED => 'Cancelled',
                        ])
                        ->disabled(),
                    Forms\Components\Textarea::make('notes')->rows(3)->disabled(),

                    Forms\Components\Placeholder::make('visit')
                        ->label('Visit')
                        ->content(fn (VisitStockRequest $r) => (string) ($r->visit?->booking_code ?? ('Visit #'.$r->visit_id))),

                    Forms\Components\Placeholder::make('branch')
                        ->label('Branch')
                        ->content(fn (VisitStockRequest $r) => (string) ($r->branch?->localized_name ?? ('#'.$r->branch_id))),

                    Forms\Components\Placeholder::make('requested_by')
                        ->label('Requested By')
                        ->content(fn (VisitStockRequest $r) => (string) ($r->requestedBy?->name ?? '—')),

                    Forms\Components\Placeholder::make('fulfilled_by')
                        ->label('Fulfilled By')
                        ->content(fn (VisitStockRequest $r) => (string) ($r->fulfilledBy?->name ?? '—')),

                    Forms\Components\Placeholder::make('fulfilled_at')
                        ->label('Fulfilled At')
                        ->content(fn (VisitStockRequest $r) => $r->fulfilled_at?->format('Y-m-d h:i A') ?? '—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(static::baseQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('visit.booking_code')
                    ->label('Visit/Code')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('branch.localized_name')
                    ->label('Branch')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        VisitStockRequest::STATUS_PENDING => 'warning',
                        VisitStockRequest::STATUS_FULFILLED => 'success',
                        VisitStockRequest::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        VisitStockRequest::STATUS_PENDING => 'Pending',
                        VisitStockRequest::STATUS_FULFILLED => 'Fulfilled',
                        VisitStockRequest::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->default(VisitStockRequest::STATUS_PENDING),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('fulfill')
                    ->label('Fulfill')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VisitStockRequest $r) => ($r->status ?? null) === VisitStockRequest::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Fulfillment Notes')
                            ->rows(3)
                            ->nullable(),
                        Forms\Components\Select::make('resume_status')
                            ->label('Resume Visit Status')
                            ->options([
                                'awaiting_doctor' => 'Awaiting Doctor',
                                'in_progress' => 'In Progress',
                            ])
                            ->default('awaiting_doctor')
                            ->required(),
                    ])
                    ->action(function (VisitStockRequest $record, array $data) {
                        app(VisitStockRequestService::class)->fulfill(
                            $record,
                            (int) (auth()->id() ?? 0),
                            $data['notes'] ?? null,
                            (string) ($data['resume_status'] ?? 'awaiting_doctor'),
                        );
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VisitStockRequest $r) => ($r->status ?? null) === VisitStockRequest::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (VisitStockRequest $record, array $data) {
                        app(VisitStockRequestService::class)->cancel(
                            $record,
                            (int) (auth()->id() ?? 0),
                            (string) ($data['reason'] ?? 'Cancelled'),
                        );
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    protected static function baseQuery(): Builder
    {
        $q = VisitStockRequest::query()->with(['visit', 'branch', 'requestedBy'])->withCount('lines');

        // If you later want non-admin scoping, add here.
        return $q;
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisitStockRequests::route('/'),
            'view' => Pages\ViewVisitStockRequest::route('/{record}'),
        ];
    }
}
