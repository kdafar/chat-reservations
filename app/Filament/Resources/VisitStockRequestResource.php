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

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.visit_stock_request.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.visit_stock_request.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.visit_stock_request.label_plural');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        // We generally do not create/edit these manually.
        // Keep form minimal for View page / safety.
        return $form->schema([
            Forms\Components\Section::make(__('clinic_inventory.visit_stock_request.sections.request'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('id')->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            VisitStockRequest::STATUS_PENDING => __('clinic_inventory.visit_stock_request.statuses.pending'),
                            VisitStockRequest::STATUS_FULFILLED => __('clinic_inventory.visit_stock_request.statuses.fulfilled'),
                            VisitStockRequest::STATUS_CANCELLED => __('clinic_inventory.visit_stock_request.statuses.cancelled'),
                        ])
                        ->disabled(),
                    Forms\Components\Textarea::make('notes')->rows(3)->disabled(),

                    Forms\Components\Placeholder::make('visit')
                        ->label(__('clinic_inventory.visit_stock_request.fields.visit'))
                        ->content(fn (VisitStockRequest $r) => (string) ($r->visit?->booking_code ?? (__('clinic_inventory.visit_stock_request.visit_prefix').$r->visit_id))),

                    Forms\Components\Placeholder::make('branch')
                        ->label(__('clinic_inventory.visit_stock_request.fields.branch'))
                        ->content(fn (VisitStockRequest $r) => (string) ($r->branch?->localized_name ?? ('#'.$r->branch_id))),

                    Forms\Components\Placeholder::make('requested_by')
                        ->label(__('clinic_inventory.visit_stock_request.fields.requested_by'))
                        ->content(fn (VisitStockRequest $r) => (string) ($r->requestedBy?->name ?? '—')),

                    Forms\Components\Placeholder::make('fulfilled_by')
                        ->label(__('clinic_inventory.visit_stock_request.fields.fulfilled_by'))
                        ->content(fn (VisitStockRequest $r) => (string) ($r->fulfilledBy?->name ?? '—')),

                    Forms\Components\Placeholder::make('fulfilled_at')
                        ->label(__('clinic_inventory.visit_stock_request.fields.fulfilled_at'))
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
                    ->label(__('clinic_inventory.visit_stock_request.fields.visit'))
                    ->badge()
                    ->searchable()
                    ->url(fn (VisitStockRequest $r) => $r->visit_id ? \App\Filament\Resources\VisitResource::getUrl('edit', ['record' => $r->visit_id]) : null),

                Tables\Columns\TextColumn::make('branch.localized_name')
                    ->label(__('clinic_inventory.visit_stock_request.fields.branch'))
                    ->toggleable(),

                // [Legacy Architect] Detailed Items Column with Stock Check
                Tables\Columns\TextColumn::make('items_summary')
                    ->label(__('clinic_inventory.visit_stock_request.fields.items_availability'))
                    ->html()
                    ->wrap()
                    ->state(function (VisitStockRequest $record) {
                        if ($record->lines->isEmpty()) {
                            return '<span class="text-gray-400 text-xs italic">'.e(__('clinic_inventory.visit_stock_request.empty_items')).'</span>';
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
                    ->formatStateUsing(fn (string $state): string => $state ? __('clinic_inventory.visit_stock_request.statuses.'.$state) : '')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        VisitStockRequest::STATUS_PENDING => 'warning',
                        VisitStockRequest::STATUS_FULFILLED => 'success',
                        VisitStockRequest::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label(__('clinic_inventory.visit_stock_request.fields.req_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('clinic_inventory.visit_stock_request.fields.time'))
                    ->dateTime('h:i A') // Shorter format for table
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        VisitStockRequest::STATUS_PENDING => __('clinic_inventory.visit_stock_request.statuses.pending'),
                        VisitStockRequest::STATUS_FULFILLED => __('clinic_inventory.visit_stock_request.statuses.fulfilled'),
                        VisitStockRequest::STATUS_CANCELLED => __('clinic_inventory.visit_stock_request.statuses.cancelled'),
                    ])
                    ->default(VisitStockRequest::STATUS_PENDING),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('fulfill')
                    ->label(__('clinic_inventory.visit_stock_request.actions.fulfill'))
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
                            ->label(__('clinic_inventory.visit_stock_request.fields.fulfillment_notes'))
                            ->rows(3)
                            ->nullable(),

                        Forms\Components\Select::make('resume_status')
                            ->label(__('clinic_inventory.visit_stock_request.fields.resume_visit_status'))
                            ->options([
                                'awaiting_doctor' => __('clinic_inventory.visit_stock_request.resume_options.awaiting_doctor'),
                                'in_progress' => __('clinic_inventory.visit_stock_request.resume_options.in_progress'),
                            ])
                            ->required()
                            ->helperText(__('clinic_inventory.visit_stock_request.helpers.resume_status')),
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
                                ->title(__('clinic_inventory.visit_stock_request.notifications.fulfilled_title'))
                                ->body(__('clinic_inventory.visit_stock_request.notifications.fulfilled_body'))
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
                                ->title(__('clinic_inventory.visit_stock_request.notifications.fulfill_failed_title'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            $this->halt();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('clinic_inventory.visit_stock_request.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VisitStockRequest $r) => ($r->status ?? null) === VisitStockRequest::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('clinic_inventory.visit_stock_request.fields.reason'))
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
