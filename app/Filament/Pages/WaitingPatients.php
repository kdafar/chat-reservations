<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VisitResource;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\Doctor;
use App\Models\Visit;
use App\Services\Clinic\VisitChargeService;
use App\Services\Clinic\VisitPackageService;
use App\Services\Clinic\VisitStockRequestService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WaitingPatients extends Page
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $title = 'Room Console';

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament.pages.waiting-patients';

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
            $this->openVisitAction(),
        ];
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
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->form(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->with('patient')->find($visitId) : null;

                $patientId = (int) ($visit?->patient_id ?? 0);

                $history = $patientId
                    ? Visit::query()
                        ->where('patient_id', $patientId)
                        ->with(['doctor'])
                        ->latest('id')
                        ->limit(10)
                        ->get()
                    : collect();

                $lines = $history->map(function (Visit $v) {
                    $dt = $v->checked_in_at?->format('Y-m-d h:i A') ?? ('#'.$v->id);
                    $doc = $v->doctor?->name ?? '—';
                    $st = $v->status ?? '—';

                    return "{$dt} • {$doc} • {$st}";
                })->implode("\n");

                return [
                    Forms\Components\Placeholder::make('patient')
                        ->label('Patient')
                        ->content(($visit?->patient?->name ?? '—').' — '.($visit?->patient?->phone ?? '—')),

                    Forms\Components\Textarea::make('history_lines')
                        ->label('Last Visits')
                        ->default($lines ?: 'No history found.')
                        ->disabled()
                        ->rows(10),
                ];
            });
    }

    protected function packageOptionsForVisit(Visit $visit): array
    {
        $branchId = (int) ($visit->branch_id ?? 0);

        return ClinicPackage::query()
            ->where('is_active', true)
            ->when($branchId > 0, fn ($q) => $q->where(function ($qq) use ($branchId) {
                // allow global (branch_id null) + branch specific
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

                return [
                    Forms\Components\Repeater::make('packages')
                        ->label('Packages')
                        ->schema([
                            Forms\Components\Select::make('clinic_package_id')
                                ->label('Package')
                                ->options($visit ? $this->packageOptionsForVisit($visit) : [])
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\TextInput::make('qty')
                                ->label('Qty')
                                ->numeric()
                                ->step('1')
                                ->minValue(1)
                                ->default(1)
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->addActionLabel('Add another package'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes for inventory')
                        ->rows(3)
                        ->nullable()
                        ->placeholder('Optional: urgent / substitute allowed / etc.'),
                ];
            })
            ->action(function (array $data, array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }

                /** @var Visit $visit */
                $visit = Visit::query()->findOrFail($visitId);

                if (! $this->canOperateVisit($visit)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                $lines = collect($data['packages'] ?? [])
                    ->map(fn ($r) => [
                        'clinic_package_id' => (int) ($r['clinic_package_id'] ?? 0),
                        'qty' => (float) ($r['qty'] ?? 1),
                    ])
                    ->filter(fn ($r) => $r['clinic_package_id'] > 0 && $r['qty'] > 0)
                    ->values()
                    ->all();

                if (empty($lines)) {
                    Notification::make()->title('Select at least one package')->danger()->send();

                    return;
                }

                try {
                    app(VisitPackageService::class)->applyPackages(
                        $visit,
                        $lines,
                        (int) (auth()->id() ?? 0),
                        $data['notes'] ?? null
                    );

                    Notification::make()
                        ->title('Packages applied')
                        ->body('Items were requested from stock. Patient moved to awaiting stock.')
                        ->success()
                        ->send();

                    $this->dispatch('$refresh');
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Failed to apply packages')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
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
                Forms\Components\TextInput::make('label')
                    ->label('Charge label')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Extra procedure / Special handling'),

                Forms\Components\TextInput::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->step('1')
                    ->minValue(1)
                    ->default(1)
                    ->required(),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Unit price')
                    ->numeric()
                    ->step('0.001')
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                Forms\Components\Placeholder::make('hint')
                    ->label('')
                    ->content('This is a snapshot charge for reception payment. It does not affect inventory.'),
            ])
            ->action(function (array $data, array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }

                /** @var Visit $visit */
                $visit = Visit::query()->findOrFail($visitId);

                if (! $this->canOperateVisit($visit)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                try {
                    app(VisitChargeService::class)->addCharge(
                        $visit,
                        (string) ($data['label'] ?? ''),
                        (float) ($data['qty'] ?? 1),
                        (float) ($data['unit_price'] ?? 0),
                        (int) (auth()->id() ?? 0),
                    );

                    Notification::make()->title('Charge added')->success()->send();
                    $this->dispatch('$refresh');
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Failed to add charge')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
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

                return $visit
                    && $this->canOperateVisit($visit)
                    && in_array(($visit->status ?? null), ['awaiting_doctor', 'in_progress', 'awaiting_stock'], true);
            })
            ->form(function (array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                $visit = $visitId ? Visit::query()->find($visitId) : null;

                $branchId = (int) ($visit?->branch_id ?? 0);

                $itemOptions = ClinicItem::query()
                    ->where('is_active', true)
                    ->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
                    ->where('is_stockable', true)
                    ->orderBy('id', 'desc')
                    ->get()
                    ->mapWithKeys(fn (ClinicItem $i) => [$i->id => $i->localized_name])
                    ->all();

                return [
                    Forms\Components\Repeater::make('items')
                        ->label('Items')
                        ->schema([
                            Forms\Components\Select::make('clinic_item_id')
                                ->label('Item')
                                ->options($itemOptions)
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\TextInput::make('qty_base')
                                ->label('Qty (Base)')
                                ->numeric()
                                ->step('0.0001')
                                ->minValue(0.0001)
                                ->default(1)
                                ->required()
                                ->helperText('Base qty aligned with ClinicStockService'),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->addActionLabel('Add another item'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->nullable()
                        ->placeholder('Optional: urgent / substitute allowed / doctor waiting / etc.'),
                ];
            })
            ->action(function (array $data, array $arguments) {
                $visitId = (int) ($arguments['record'] ?? 0);
                if ($visitId <= 0) {
                    return;
                }

                /** @var Visit $visit */
                $visit = Visit::query()->findOrFail($visitId);

                if (! $this->canOperateVisit($visit)) {
                    Notification::make()->title('Not allowed')->danger()->send();

                    return;
                }

                $lines = collect($data['items'] ?? [])
                    ->map(fn ($r) => [
                        'clinic_item_id' => (int) ($r['clinic_item_id'] ?? 0),
                        'qty_base' => (float) ($r['qty_base'] ?? 0),
                    ])
                    ->filter(fn ($r) => $r['clinic_item_id'] > 0 && $r['qty_base'] > 0)
                    ->values()
                    ->all();

                if (empty($lines)) {
                    Notification::make()->title('Add at least one item')->danger()->send();

                    return;
                }

                try {
                    app(VisitStockRequestService::class)->createForVisit(
                        $visit,
                        $lines,
                        (int) (auth()->id() ?? 0),
                        $data['notes'] ?? null,
                        true
                    );

                    Notification::make()
                        ->title('Stock requested')
                        ->body('Patient moved to awaiting stock until items are fulfilled.')
                        ->success()
                        ->send();

                    $this->dispatch('$refresh');
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Failed to request stock')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
