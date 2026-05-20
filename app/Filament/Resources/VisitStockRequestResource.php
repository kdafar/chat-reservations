<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitStockRequestResource\Pages;
use App\Filament\Resources\VisitStockRequestResource\RelationManagers\LinesRelationManager;
use App\Models\VisitStockRequest;
use App\Services\Clinic\VisitStockRequestService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

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
            // [Legacy Architect] Refresh list every 30s so stock levels stay accurate
            ->poll('30s')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('visit.booking_code')
                    ->label('Visit')
                    ->badge()
                    ->searchable()
                    ->url(fn (VisitStockRequest $r) => $r->visit_id ? \App\Filament\Resources\VisitResource::getUrl('edit', ['record' => $r->visit_id]) : null),

                Tables\Columns\TextColumn::make('branch.localized_name')
                    ->label('Branch')
                    ->toggleable(),

                // [Legacy Architect] Detailed Items Column with Stock Check
                Tables\Columns\TextColumn::make('items_summary')
                    ->label('Items & Availability')
                    ->html()
                    ->wrap()
                    ->state(function (VisitStockRequest $record) {
                        if ($record->lines->isEmpty()) {
                            return '<span class="text-gray-400 text-xs italic">No items</span>';
                        }

                        $stockSvc = app(\App\Services\Clinic\ClinicStockService::class);
                        $branchId = (int) $record->branch_id;
                        $isPending = ($record->status === VisitStockRequest::STATUS_PENDING);

                        $html = '<div class="space-y-1 text-xs">';

                        foreach ($record->lines as $line) {
                            $item = $line->clinicItem;
                            if (! $item) {
                                continue;
                            }

                            $name = e($item->localized_name);
                            $reqQty = (float) $line->qty_base;

                            // Check live stock
                            $availQty = $stockSvc->availableBase($branchId, $item->id);

                            // Visual Logic:
                            // - If not pending (fulfilled/cancelled), just show what was asked.
                            // - If pending AND stock is low, show RED.
                            // - If pending AND stock is good, show GREEN.
                            if (! $isPending) {
                                $style = 'text-gray-600';
                                $info = "{$reqQty}";
                            } elseif ($availQty >= $reqQty) {
                                $style = 'text-success-600 font-medium'; // Filament success color
                                $info = "{$reqQty} <span class='text-gray-400'>/ {$availQty} OK</span>";
                            } else {
                                $style = 'text-danger-600 font-bold'; // Filament danger color
                                $info = "{$reqQty} <span class='text-danger-500'>/ Only {$availQty}!</span>";
                            }

                            $html .= "<div class='{$style}'>• {$name}: {$info}</div>";
                        }

                        $html .= '</div>';

                        return $html;
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        VisitStockRequest::STATUS_PENDING => 'warning',
                        VisitStockRequest::STATUS_FULFILLED => 'success',
                        VisitStockRequest::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Req By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('h:i A') // Shorter format for table
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
                    // [Legacy Architect] Smart Default Logic maintained from previous step
                    ->mountUsing(function (Forms\ComponentContainer $form, VisitStockRequest $record) {
                        $visit = $record->visit;
                        $isStarted = $visit && ($visit->accepted_at || $visit->accepted_by_user_id || $visit->service_started_at);

                        $form->fill([
                            'resume_status' => $isStarted ? 'in_progress' : 'awaiting_doctor',
                        ]);
                    })
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Fulfillment Notes')
                            ->rows(3)
                            ->nullable(),

                        Forms\Components\Select::make('resume_status')
                            ->label('Resume Visit Status')
                            ->options([
                                'awaiting_doctor' => 'Awaiting Doctor (Queue)',
                                'in_progress' => 'In Progress (Room)',
                            ])
                            ->required()
                            ->helperText('Where should the patient go after this stock arrives?'),
                    ])
                    ->action(function (VisitStockRequest $record, array $data) {
                        $trace = 'ADM-FULFILL-'.now()->format('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6);

                        Log::info('[VisitStockRequestResource][fulfill] start', [
                            'trace' => $trace,
                            'req_id' => (int) $record->id,
                            'visit_id' => (int) $record->visit_id,
                            'current_status' => (string) ($record->status ?? ''),
                            'admin_user_id' => (int) (auth()->id() ?? 0),
                            'resume_target' => (string) ($data['resume_status'] ?? ''),
                        ]);

                        try {
                            $req = app(VisitStockRequestService::class)->fulfill(
                                $record,
                                (int) (auth()->id() ?? 0),
                                $data['notes'] ?? null,
                                (string) ($data['resume_status'] ?? 'awaiting_doctor'),
                            );

                            $req->refresh();
                            $visit = $req->visit()->first();

                            Log::info('[VisitStockRequestResource][fulfill] success', [
                                'trace' => $trace,
                                'req_id' => (int) $req->id,
                                'req_status_final' => (string) ($req->status ?? ''),
                                'visit_status_final' => (string) ($visit?->status ?? ''),
                            ]);

                            Notification::make()
                                ->title('Stock request fulfilled')
                                ->body('Items consumed and visit updated.')
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Log::error('[VisitStockRequestResource][fulfill] exception', [
                                'trace' => $trace,
                                'req_id' => (int) $record->id,
                                'message' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);

                            Notification::make()
                                ->title('Fulfillment failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            $this->halt();
                        }
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
