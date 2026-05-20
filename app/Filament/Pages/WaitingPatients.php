<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VisitResource;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\Doctor;
use App\Models\Visit;
// [Legacy Architect] Added for status constants
use App\Services\Clinic\ClinicStockService;
use App\Services\Clinic\VisitChargeService;
use App\Services\Clinic\VisitStockRequestService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WaitingPatients extends Page
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $title = 'Room Console';

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament.pages.waiting-patients';

    protected ?string $maxContentWidth = 'full';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_waiting_patients');
    }

    public function getViewData(): array
    {
        $visits = $this->queueQuery()
            ->orderByRaw("FIELD(status, 'awaiting_doctor', 'in_progress', 'awaiting_stock')")
            ->orderBy('queued_at')
            ->get();

        return [
            'visits' => $visits,
        ];
    }

    protected function queueQuery(): Builder
    {
        $q = Visit::query()
            ->with(['patient', 'doctor', 'branch', 'room'])
            ->whereIn('status', ['awaiting_doctor', 'in_progress', 'awaiting_stock']);

        $user = auth()->user();

        if ($this->isAdminUser()) {
            return $q;
        }

        $doctorId = $this->resolveDoctorIdForUserId((int) ($user?->id ?? 0));

        return $doctorId ? $q->where('doctor_id', $doctorId) : $q->whereRaw('1=0');
    }

    protected function isAdminUser(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && method_exists($user, 'hasRole')
            && ($user->hasRole('super_admin') || $user->hasRole('admin')));
    }

    protected function resolveDoctorIdForUserId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $id = (int) (Doctor::query()->where('user_id', $userId)->value('id') ?: 0);

        return $id > 0 ? $id : null;
    }

    protected function canOperateVisit(Visit $visit): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        $userId = (int) (auth()->id() ?? 0);
        $doctorId = $this->resolveDoctorIdForUserId($userId);

        return $doctorId > 0 && (int) $visit->doctor_id === (int) $doctorId;
    }

    protected function getActions(): array
    {
        return [
            $this->acceptVisitAction(),
            $this->historyAction(),
            $this->addPackagesAction(),
            $this->addExtraChargeAction(),
            $this->requestStockAction(),
            $this->fulfillStockAction(),
            $this->completeVisitAction(), // [Legacy Architect] Added this
            $this->openVisitAction(),
        ];
    }

    // [Legacy Architect] New Action to Finish Treatment
    protected function completeVisitAction(): Action
    {
        return Action::make('completeVisit')
            ->label('Finish Treatment')
            ->color('success')
            ->icon('heroicon-o-arrow-right-start-on-rectangle') // Icon indicating leaving the room
            ->requiresConfirmation()
            ->modalHeading('Finish Consultation')
            ->modalDescription('Are you done with this patient? They will be moved to the "Payment Pending" list for reception, and the room will be freed.')
            ->visible(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->find($visitId) : null;

                return $visit
                    && $this->canOperateVisit($visit)
                    && $visit->status === 'in_progress';
            })
            ->action(function (array $arguments) {
                $trace = 'FINISH-TREAT-'.now()->format('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6);
                $visitId = (int) ($arguments['record'] ?? 0);
                $userId = (int) (auth()->id() ?? 0);

                if ($visitId <= 0) {
                    return;
                }

                Log::info('[WaitingPatients][completeVisit] start', [
                    'trace' => $trace,
                    'visit_id' => $visitId,
                    'user_id' => $userId,
                ]);

                try {
                    DB::transaction(function () use ($visitId, $trace) {
                        /** @var Visit $visit */
                        $visit = Visit::query()->lockForUpdate()->with('pendingStockRequest')->findOrFail($visitId);

                        if ($visit->status !== 'in_progress') {
                            Log::warning('[WaitingPatients][completeVisit] wrong status', ['trace' => $trace, 'status' => $visit->status]);

                            return;
                        }

                        // 1. Safety: Prevent leaving if stock requests are hanging
                        if ($visit->pendingStockRequest) {
                            throw new \RuntimeException('Cannot finish: There is a pending stock request. Please fulfill or cancel it first.');
                        }

                        // 2. Free the Room (Crucial for Operations)
                        $oldTableId = $visit->restaurant_table_id;
                        if ($oldTableId) {
                            \App\Models\RestaurantTable::where('id', $oldTableId)->update(['status' => 'available']);

                            // Also clear room from Booking so it doesn't look occupied in other views
                            if ($visit->booking_id) {
                                \App\Models\Booking::where('id', $visit->booking_id)->update(['table_id' => null]);
                            }
                        }

                        // 3. Update Status to "awaiting_payment"
                        // This removes them from the "Waiting/In Progress" queues in this Console,
                        // but keeps them "Open" in BookingResource so Reception can charge them.
                        $visit->status = 'awaiting_payment';
                        $visit->restaurant_table_id = null; // Detach room from visit history snapshot

                        // Optional: Track when the doctor actually finished
                        // $visit->service_ended_at = now(config('app.timezone', 'Asia/Kuwait'));

                        $visit->save();

                        Log::info('[WaitingPatients][completeVisit] success', [
                            'trace' => $trace,
                            'visit_id' => $visitId,
                            'freed_room_id' => $oldTableId,
                        ]);
                    });

                    Notification::make()
                        ->title('Treatment Finished')
                        ->body('Patient moved to billing. Room is now available.')
                        ->success()
                        ->send();

                    $this->dispatch('$refresh');

                } catch (\Throwable $e) {
                    report($e);
                    Log::error('[WaitingPatients][completeVisit] error', ['trace' => $trace, 'msg' => $e->getMessage()]);

                    Notification::make()
                        ->title('Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected function acceptVisitAction(): Action
    {
        return Action::make('acceptVisit')
            ->label('Accept')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }

                /** @var Visit $record */
                $record = Visit::query()->findOrFail($visitId);

                if (! $this->canOperateVisit($record)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                $userId = (int) (auth()->id() ?? 0);
                if ($userId <= 0) {
                    return;
                }

                $isAdmin = $this->isAdminUser();
                $doctorId = $isAdmin ? null : $this->resolveDoctorIdForUserId($userId);

                if (! $isAdmin && ! $doctorId) {
                    Notification::make()->title('Not allowed')->body('Your user is not linked to a doctor.')->danger()->send();

                    return;
                }

                DB::transaction(function () use ($record, $userId, $isAdmin, $doctorId) {
                    /** @var Visit $fresh */
                    $fresh = Visit::query()->lockForUpdate()->findOrFail($record->id);

                    if (($fresh->status ?? null) !== 'awaiting_doctor') {
                        return;
                    }

                    if (! $isAdmin && (int) $fresh->doctor_id !== (int) $doctorId) {
                        throw new \RuntimeException('You cannot accept a visit assigned to another doctor.');
                    }

                    if ($fresh->accepted_at || $fresh->accepted_by_user_id) {
                        return;
                    }

                    $now = now(config('app.timezone', 'Asia/Kuwait'));

                    $fresh->accepted_by_user_id = $userId;
                    $fresh->accepted_at = $now;

                    if (! $fresh->service_started_at) {
                        $fresh->service_started_at = $now;
                    }

                    $fresh->status = 'in_progress';
                    $fresh->save();
                });

                Notification::make()->title('Accepted')->success()->send();
                $this->dispatch('$refresh');
            });
    }

    protected function openVisitAction(): Action
    {
        return Action::make('openVisit')
            ->label('Open Visit')
            ->action(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }
                $this->redirect(VisitResource::getUrl('edit', ['record' => $visitId]));
            });
    }

    protected function historyAction(): Action
    {
        return Action::make('history')
            ->label('History')
            ->modalHeading('Patient History')
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->with(['patient', 'doctor', 'branch'])->find($visitId) : null;
                $patientId = (int) ($visit?->patient_id ?? 0);

                $visits = $patientId
                    ? Visit::query()
                        ->where('patient_id', $patientId)
                        ->whereKeyNot($visitId)
                        ->with(['doctor', 'branch', 'visitItems.clinicItem', 'payments', 'followUpPlans'])
                        ->latest('id')
                        ->limit(5)
                        ->get()
                    : collect();

                return view('filament.clinic.partials.patient-history-preview', [
                    'v' => $visit,
                    'visits' => $visits,
                ]);
            });
    }

    protected function packageOptionsForVisit(Visit $visit): array
    {
        $branchId = (int) ($visit->branch_id ?? 0);

        return ClinicPackage::query()
            ->where('is_active', true)
            ->when($branchId > 0, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('id', 'desc')
            ->get()
            ->mapWithKeys(fn (ClinicPackage $p) => [$p->id => $p->localized_name])
            ->all();
    }

    protected function addPackagesAction(): Action
    {
        return Action::make('addPackages')
            ->label('Add Service/Package')
            ->modalHeading('Add Services / Packages')
            ->modalSubmitActionLabel('Apply')
            ->visible(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->find($visitId) : null;

                return $visit && $this->canOperateVisit($visit)
                    && in_array(($visit->status ?? null), ['awaiting_doctor', 'in_progress', 'awaiting_stock'], true);
            })
            ->form(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->find($visitId) : null;
                $singleItemOptions = $this->clinicItemOptionsForVisit($visit);

                return [
                    Forms\Components\Section::make('Packages')
                        ->columns(1)
                        ->schema([
                            Forms\Components\Repeater::make('packages')
                                ->label('Packages')
                                ->schema([
                                    Forms\Components\Select::make('clinic_package_id')
                                        ->label('Package')
                                        ->options($visit ? $this->packageOptionsForVisit($visit) : [])
                                        ->searchable()->preload()->required(),
                                    Forms\Components\TextInput::make('qty')
                                        ->label('Qty')->numeric()->step('1')->minValue(1)->default(1)->required(),
                                ])
                                ->columns(2)->minItems(1)->defaultItems(1)->live(),
                            Forms\Components\ViewField::make('package_items_preview')
                                ->view('filament.clinic.partials.package-items-preview-table')
                                ->viewData(fn (Forms\Get $get) => ['rows' => $this->packageItemsPreview((array) ($get('packages') ?? []))])
                                ->dehydrated(false),
                        ]),
                    Forms\Components\Section::make('Single Items (Optional)')
                        ->schema([
                            Forms\Components\Repeater::make('single_items')
                                ->label('Items')
                                ->schema([
                                    Forms\Components\Select::make('clinic_item_id')
                                        ->label('Item')->options($singleItemOptions)->searchable()->preload()->required(),
                                    Forms\Components\TextInput::make('qty_base')
                                        ->label('Qty (Base)')->numeric()->step('0.0001')->minValue(0.0001)->default(1)->required(),
                                ])->columns(2)->defaultItems(0)->addActionLabel('Add another item'),
                        ]),
                    Forms\Components\Textarea::make('notes')->label('Notes for inventory')->rows(3),
                ];
            })
            ->action(function (array $data, array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }

                $trace = 'ROOM-PKG-'.now()->format('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6);
                $userId = (int) (auth()->id() ?? 0);
                $notes = $data['notes'] ?? null;

                /** @var Visit $visit */
                $visit = Visit::query()->findOrFail($visitId);

                // ... (Auth checks omitted for brevity, keeping your existing logic) ...
                if (! $this->canOperateVisit($visit)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                // 1. Prepare Lines
                $pkgLines = collect($data['packages'] ?? [])
                    ->map(fn ($r) => ['clinic_package_id' => (int) ($r['clinic_package_id'] ?? 0), 'qty' => (float) ($r['qty'] ?? 1)])
                    ->filter(fn ($r) => $r['clinic_package_id'] > 0 && $r['qty'] > 0)->values()->all();

                $singleLines = collect($data['single_items'] ?? [])
                    ->map(fn ($r) => ['clinic_item_id' => (int) ($r['clinic_item_id'] ?? 0), 'qty_base' => (float) ($r['qty_base'] ?? 0)])
                    ->filter(fn ($r) => $r['clinic_item_id'] > 0 && $r['qty_base'] > 0)->values()->all();

                if (empty($pkgLines) && empty($singleLines)) {
                    Notification::make()->title('Select at least one package or item')->danger()->send();

                    return;
                }

                try {
                    // 1) Apply packages (Pricing logic)
                    if (! empty($pkgLines)) {
                        $pkgSvc = app(\App\Services\Clinic\VisitPackageService::class);
                        if (method_exists($pkgSvc, 'applyPackagesOnly')) {
                            $pkgSvc->applyPackagesOnly($visit, $pkgLines, $userId, $notes, $trace);
                        } else {
                            // Fallback to old methods
                            try {
                                $pkgSvc->applyPackages($visit, $pkgLines, $userId, $notes, $trace);
                            } catch (\ArgumentCountError) {
                                $pkgSvc->applyPackages($visit, $pkgLines, $userId, $notes);
                            }
                        }
                    }
                    $visit->refresh();

                    // 2) Build Requirements
                    $requirements = [];
                    $pkgSvc = app(\App\Services\Clinic\VisitPackageService::class);
                    if (method_exists($pkgSvc, 'requirementsForVisit')) {
                        $requirements = $pkgSvc->requirementsForVisit($visit);
                    } else {
                        $previewRows = $this->packageItemsPreview((array) ($data['packages'] ?? []));
                        $requirements = collect($previewRows)
                            ->map(fn ($r) => ['clinic_item_id' => (int) ($r['clinic_item_id'] ?? 0), 'qty_base' => (float) ($r['qty_base'] ?? 0)])
                            ->filter(fn ($r) => $r['clinic_item_id'] > 0 && $r['qty_base'] > 0)->values()->all();
                    }
                    // Merge Single Items
                    $requirements = collect($requirements)
                        ->concat(collect($singleLines)->map(fn ($r) => ['clinic_item_id' => (int) $r['clinic_item_id'], 'qty_base' => (float) $r['qty_base']]))
                        ->groupBy('clinic_item_id')
                        ->map(fn ($g, $itemId) => ['clinic_item_id' => (int) $itemId, 'qty_base' => (float) $g->sum('qty_base')])
                        ->values()->all();

                    // 3) [Legacy Architect Fix] Check Stock BEFORE Creating Request
                    $stockSvc = app(ClinicStockService::class);
                    $reqSvc = app(VisitStockRequestService::class);
                    $branchId = (int) ($visit->branch_id ?? 0);

                    // Check shortages
                    $shortages = $stockSvc->shortagesForRequirements($branchId, $requirements);
                    $result = [];

                    if (empty($shortages) && $stockSvc->enabled()) {
                        // HAPPY PATH: Consume immediately
                        DB::transaction(function () use ($stockSvc, $branchId, $requirements, $userId, $notes, $visit) {
                            $itemIds = collect($requirements)->pluck('clinic_item_id')->unique();
                            $itemsMap = ClinicItem::whereIn('id', $itemIds)->get()->keyBy('id');

                            foreach ($requirements as $req) {
                                $item = $itemsMap->get($req['clinic_item_id']);
                                if ($item && $item->is_stockable) {
                                    $stockSvc->consume($branchId, $item, (float) $req['qty_base'], $userId, $notes, $visit);
                                }
                            }
                        });
                        $result = ['mode' => 'issued', 'request_id' => 0];
                    } else {
                        // UNHAPPY PATH: Request Stock
                        if (method_exists($reqSvc, 'issueOrRequestForVisit')) {
                            $result = (array) $reqSvc->issueOrRequestForVisit($visit, $requirements, $userId, $notes, $trace);
                        } else {
                            $req = $reqSvc->createForVisit($visit, $requirements, $userId, $notes, true);
                            $result = ['mode' => 'request', 'request_id' => (int) ($req?->id ?? 0)];
                        }
                    }

                    $visit->refresh();

                    // 4) Notification
                    if (($result['mode'] ?? null) === 'issued') {
                        Notification::make()->title('Applied')->body('Items issued directly from stock.')->success()->send();
                    } else {
                        Notification::make()->title('Stock requested')->body('Insufficient stock. Visit moved to awaiting stock.')->success()->send();
                    }

                    $this->dispatch('$refresh');

                } catch (\Throwable $e) {
                    Log::error('[WaitingPatients][addPackages] failed', ['trace' => $trace, 'message' => $e->getMessage()]);
                    report($e);
                    Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                }
            });
    }

    protected function clinicItemOptionsForVisit(?Visit $visit): array
    {
        $branchId = (int) ($visit?->branch_id ?? 0);

        return ClinicItem::query()
            ->where('is_stockable', true)
            ->where('is_active', true)
            ->when($branchId > 0, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('id', 'desc')
            ->get()
            ->mapWithKeys(fn (ClinicItem $it) => [$it->id => $it->localized_name])
            ->all();
    }

    protected function packageItemsPreview(array $packagesState): array
    {
        $lines = collect($packagesState ?? [])
            ->map(fn ($r) => ['clinic_package_id' => (int) ($r['clinic_package_id'] ?? 0), 'qty' => (float) ($r['qty'] ?? 1)])
            ->filter(fn ($r) => $r['clinic_package_id'] > 0 && $r['qty'] > 0)->values();

        if ($lines->isEmpty()) {
            return [];
        }

        $pkgIds = $lines->pluck('clinic_package_id')->unique()->values()->all();
        $pkgs = ClinicPackage::query()->whereIn('id', $pkgIds)->with(['items.clinicItem'])->get()->keyBy('id');
        $acc = [];

        foreach ($lines as $ln) {
            $pkg = $pkgs->get((int) $ln['clinic_package_id']);
            if (! $pkg) {
                continue;
            }
            $pkgQty = (float) $ln['qty'];

            foreach (($pkg->items ?? []) as $it) {
                $item = $it->clinicItem;
                if (! $item) {
                    continue;
                }
                $delta = (float) ($it->qty_base ?? 0) * $pkgQty;
                if ($delta <= 0) {
                    continue;
                }

                $key = (int) $item->id;
                if (! isset($acc[$key])) {
                    $acc[$key] = ['name' => (string) ($item->localized_name ?? ('#'.$key)), 'qty_base' => 0.0];
                }
                $acc[$key]['qty_base'] += $delta;
            }
        }

        return collect($acc)->sortBy('name')->values()->map(fn ($r) => ['name' => $r['name'], 'qty_base' => round((float) $r['qty_base'], 4)])->all();
    }

    protected function addExtraChargeAction(): Action
    {
        return Action::make('addExtraCharge')
            ->label('Extra Charge')
            ->modalHeading('Add Extra Charge')
            ->modalSubmitActionLabel('Add')
            ->visible(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->find($visitId) : null;

                return $visit && $this->canOperateVisit($visit)
                    && in_array(($visit->status ?? null), ['awaiting_doctor', 'in_progress', 'awaiting_stock'], true);
            })
            ->form([
                Forms\Components\TextInput::make('label')->label('Charge label')->required()->maxLength(255)->placeholder('e.g., Extra procedure'),
                Forms\Components\TextInput::make('qty')->label('Qty')->numeric()->step('1')->minValue(1)->default(1)->required(),
                Forms\Components\TextInput::make('unit_price')->label('Unit price')->numeric()->step('0.001')->minValue(0)->default(0)->required(),
                Forms\Components\Placeholder::make('hint')->label('')->content('This is a snapshot charge for reception payment. It does not affect inventory.'),
            ])
            ->action(function (array $data, array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }
                $visit = Visit::query()->findOrFail($visitId);

                if (! $this->canOperateVisit($visit)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                try {
                    app(VisitChargeService::class)->addCharge($visit, (string) ($data['label'] ?? ''), (float) ($data['qty'] ?? 1), (float) ($data['unit_price'] ?? 0), (int) (auth()->id() ?? 0));
                    Notification::make()->title('Charge added')->success()->send();
                    $this->dispatch('$refresh');
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Failed to add charge')->body($e->getMessage())->danger()->send();
                }
            });
    }

    protected function requestStockAction(): Action
    {
        return Action::make('requestStock')
            ->label('Request Stock')
            ->modalHeading('Request Items from Stock')
            ->modalSubmitActionLabel('Create Request')
            ->visible(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->find($visitId) : null;

                return $visit && $this->canOperateVisit($visit)
                    && in_array(($visit->status ?? null), ['awaiting_doctor', 'in_progress', 'awaiting_stock'], true);
            })
            ->form(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->find($visitId) : null;
                $branchId = (int) ($visit?->branch_id ?? 0);
                $itemOptions = ClinicItem::query()->where('is_active', true)->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId))->where('is_stockable', true)->orderBy('id', 'desc')->get()->mapWithKeys(fn (ClinicItem $i) => [$i->id => $i->localized_name])->all();

                return [
                    Forms\Components\Repeater::make('items')->label('Items')->schema([
                        Forms\Components\Select::make('clinic_item_id')->label('Item')->options($itemOptions)->searchable()->preload()->required(),
                        Forms\Components\TextInput::make('qty_base')->label('Qty (Base)')->numeric()->step('0.0001')->minValue(0.0001)->default(1)->required(),
                    ])->columns(2)->minItems(1)->defaultItems(1)->reorderable(false)->addActionLabel('Add another item'),
                    Forms\Components\Textarea::make('notes')->label('Notes')->rows(3)->nullable()->placeholder('Optional: urgent / substitute allowed / doctor waiting / etc.'),
                ];
            })
            ->action(function (array $data, array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }
                $visit = Visit::query()->findOrFail($visitId);

                if (! $this->canOperateVisit($visit)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                $lines = collect($data['items'] ?? [])
                    ->map(fn ($r) => ['clinic_item_id' => (int) ($r['clinic_item_id'] ?? 0), 'qty_base' => (float) ($r['qty_base'] ?? 0)])
                    ->filter(fn ($r) => $r['clinic_item_id'] > 0 && $r['qty_base'] > 0)->values()->all();

                if (empty($lines)) {
                    Notification::make()->title('Add at least one item')->danger()->send();

                    return;
                }

                try {
                    app(VisitStockRequestService::class)->createForVisit($visit, $lines, (int) (auth()->id() ?? 0), $data['notes'] ?? null, true);
                    Notification::make()->title('Stock requested')->body('Patient moved to awaiting stock until items are fulfilled.')->success()->send();
                    $this->dispatch('$refresh');
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Failed to request stock')->body($e->getMessage())->danger()->send();
                }
            });
    }

    protected function fulfillStockAction(): Action
    {
        return Action::make('fulfillStock')
            ->label('Stock Arrived')
            ->modalHeading('Fulfill Stock Request')
            ->modalSubmitActionLabel('Fulfill & Resume')
            // [Legacy Architect] Allow visible so we can return errors instead of silent fails
            ->visible(true)
            ->form(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->with(['pendingStockRequest.lines.clinicItem'])->find($visitId) : null;
                $req = $visit?->pendingStockRequest;

                if (! $req) {
                    return [
                        Forms\Components\Placeholder::make('error')->label('Error')->content('❌ No pending stock request found for this patient. Items may have been auto-issued.'),
                    ];
                }

                $linesText = $req->lines->map(fn ($ln) => "{$ln->clinicItem?->localized_name} × ".($ln->qty_base ?? '0'))->implode("\n");

                return [
                    Forms\Components\Placeholder::make('req_info')->label('Requested items')->content($linesText),
                    Forms\Components\Textarea::make('notes')->label('Notes')->rows(3)->nullable()->placeholder('Optional notes for fulfillment / movements'),
                ];
            })
            ->action(function (array $data, array $arguments) {
                $trace = 'FULFILL-'.now()->format('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6);
                $visitId = (int) ($arguments['record'] ?? 0);

                Log::info('[WaitingPatients][fulfillStock] start', [
                    'trace' => $trace,
                    'visit_id' => $visitId,
                    'user_id' => auth()->id(),
                ]);

                if ($visitId <= 0) {
                    return;
                }

                $visit = Visit::query()->with(['pendingStockRequest'])->find($visitId);

                if (! $visit || ! $this->canOperateVisit($visit)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                $req = $visit->pendingStockRequest;
                if (! $req) {
                    Notification::make()->title('No Pending Request')->body('Items may have been auto-issued or cancelled.')->warning()->send();

                    return;
                }

                $resume = ($visit->accepted_at || $visit->accepted_by_user_id || $visit->service_started_at) ? 'in_progress' : 'awaiting_doctor';

                try {
                    app(VisitStockRequestService::class)->fulfill($req, (int) (auth()->id() ?? 0), $data['notes'] ?? null, $resume);
                    Log::info('[WaitingPatients][fulfillStock] success', ['trace' => $trace]);
                    Notification::make()->title('Stock fulfilled')->body('Patient resumed.')->success()->send();
                    $this->dispatch('$refresh');
                } catch (\Throwable $e) {
                    Log::error('[WaitingPatients][fulfillStock] exception', ['trace' => $trace, 'message' => $e->getMessage()]);
                    report($e);
                    Notification::make()->title('Failed to fulfill')->body($e->getMessage())->danger()->send();
                }
            });
    }

    // [Legacy Architect] Real-time Poller for wire:poll in Blade
    public function checkRemoteUpdates(): void
    {
        $userId = auth()->id();
        $doctorId = $this->resolveDoctorIdForUserId((int) $userId);

        if (! $doctorId) {
            return;
        }

        // Check for any visits assigned to me that were updated in the last 12 seconds by SOMEONE ELSE.
        $recentUpdates = Visit::query()
            ->where('doctor_id', $doctorId)
            ->where('updated_at', '>=', now()->subSeconds(12))
            ->when($userId, fn ($q) => $q->where('updated_by_user_id', '!=', $userId))
            ->exists();

        // Also check if any stock requests linked to my visits were updated
        $stockUpdates = DB::table('visit_stock_requests')
            ->join('visits', 'visits.id', '=', 'visit_stock_requests.visit_id')
            ->where('visits.doctor_id', $doctorId)
            ->where('visit_stock_requests.updated_at', '>=', now()->subSeconds(12))
            ->where('visit_stock_requests.updated_by_user_id', '!=', $userId)
            ->exists();

        if ($recentUpdates || $stockUpdates) {
            Notification::make()->title('Update Received')->body('Admin has updated a visit or stock request.')->success()->send();
            $this->dispatch('$refresh');
            $this->dispatch('play-notification-sound');
        }
    }
}
