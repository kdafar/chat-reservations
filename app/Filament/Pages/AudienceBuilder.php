<?php

namespace App\Filament\Pages;

use App\Jobs\SendCampaignInvite;
use App\Models\Branch;
use App\Models\BulkInviteCampaign;
use App\Services\WhatsAppTemplateCatalog;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AudienceBuilder extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $title = 'Audience Builder';

    protected static string $view = 'filament.pages.audience-builder';

    /** Filters + UI state (backed by Filament forms via statePath). */
    public array $filters = [
        'last_booking_from' => null,
        'last_booking_to' => null,
        'min_bookings' => null,
        'lapsed_days' => null,
        'last_branch_id' => [],
        'wa_recent_days' => null,
        'wa_inbound_since' => null,
        'wa_outbound_since' => null,
        'source_kind' => null,
    ];

    public int $page = 1;

    public int $perPage = 25;

    public int $resultsCount = 0;

    public array $results = [];

    public array $branchNames = [];

    /** Add-to-campaign form state */
    public bool $showAddForm = false;

    public array $addForm = [
        'campaign_id' => null,
        'default_locale' => 'en',
        'queue_now' => false,
    ];

    /** Create-campaign form state */
    public bool $showCreateForm = false;

    public array $createForm = [
        'name' => null,
        'template_name' => null,
        'template_details' => [],
        'template_variables' => [],
        'default_locale' => 'en',
        'header_image_path' => null,
        'scheduled_at' => null,
        'send_rate_per_min' => 600,
    ];

    /** Choices for selects (computed in mount) */
    public array $campaignOptions = [];

    public array $templateOptions = [];

    public function mount(): void
    {
        $this->branchNames = Branch::query()->orderBy('name')->pluck('name', 'id')->all();
        $this->campaignOptions = BulkInviteCampaign::query()->orderByDesc('id')->pluck('name', 'id')->all();
        $this->templateOptions = app(WhatsAppTemplateCatalog::class)->options();

        // Seed forms with initial state
        $this->filtersForm->fill($this->filters);
        $this->addToCampaignForm->fill($this->addForm);
        $this->createCampaignForm->fill($this->createForm);

        $this->loadResults();
    }

    /** ---------- Filament Forms (3 separate forms) ---------- */
    protected function getForms(): array
    {
        return [
            'filtersForm' => $this->makeForm()
                ->schema($this->filtersSchema())
                ->statePath('filters'),

            'addToCampaignForm' => $this->makeForm()
                ->schema($this->addToCampaignSchema())
                ->statePath('addForm'),

            'createCampaignForm' => $this->makeForm()
                ->schema($this->createCampaignSchema())
                ->statePath('createForm'),
        ];
    }

    protected function filtersSchema(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\DatePicker::make('last_booking_from')->label('Booking from'),
                Forms\Components\DatePicker::make('last_booking_to')->label('Booking to'),

                Forms\Components\TextInput::make('min_bookings')
                    ->label('Min bookings')->numeric()->minValue(0),

                Forms\Components\TextInput::make('lapsed_days')
                    ->label('No booking since (days)')->numeric()->minValue(1)->hint('e.g. 60'),

                Forms\Components\TextInput::make('wa_recent_days')
                    ->label('WA active within (days)')->numeric()->minValue(1),

                Forms\Components\TextInput::make('wa_inbound_since')
                    ->label('Inbound within (days)')->numeric()->minValue(1),

                Forms\Components\TextInput::make('wa_outbound_since')
                    ->label('Outbound within (days)')->numeric()->minValue(1),

                Forms\Components\Select::make('source_kind')
                    ->label('Source Kind')
                    ->options([
                        '' => 'All',
                        'booked_only' => 'Booked only',
                        'wa_only' => 'WA only (no bookings)',
                        'both' => 'Both WA & bookings',
                    ])
                    ->native(false),

                Forms\Components\Select::make('last_branch_id')
                    ->label('Last Branch')
                    ->multiple()
                    ->options($this->branchNames)
                    ->native(false)
                    ->columnSpanFull()
                    ->helperText('Hold CTRL/Cmd to select multiple.'),
            ]),
        ];
    }

    protected function addToCampaignSchema(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('campaign_id')
                    ->label('Campaign')
                    ->options($this->campaignOptions)
                    ->searchable()
                    ->required()
                    ->native(false),

                Forms\Components\Select::make('default_locale')
                    ->label('Default Locale')
                    ->options(['en' => 'English', 'ar' => 'العربية'])
                    ->required()
                    ->native(false),

                Forms\Components\Toggle::make('queue_now')
                    ->label('Queue send jobs now')
                    ->inline(false),
            ]),
        ];
    }

    protected function createCampaignSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Campaign Name')->required()->maxLength(160),

                Forms\Components\Select::make('template_name')
                    ->label('Meta Template')
                    ->options($this->templateOptions)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        $set('template_details', $state ? (app(WhatsAppTemplateCatalog::class)->find($state) ?? []) : []);
                    }),

                Forms\Components\Hidden::make('template_details'),

                Forms\Components\Select::make('default_locale')
                    ->label('Default Locale')
                    ->options(['en' => 'English', 'ar' => 'العربية'])
                    ->required()
                    ->native(false),

                Forms\Components\TextInput::make('send_rate_per_min')
                    ->label('Send rate / minute')->numeric()->minValue(60)->default(600),

                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label('Schedule At')->seconds(false)->timezone(config('app.timezone')),
            ]),

            // Simple KV for template variables (optional)
            Forms\Components\KeyValue::make('template_variables')
                ->keyLabel('Index (e.g., 1)')
                ->valueLabel('Value')
                ->addButtonLabel('Add variable')
                ->visible(function (Forms\Get $get) {
                    $body = collect(data_get($get('template_details'), 'components', []))->firstWhere('type', 'BODY');
                    $text = (string) data_get($body, 'text', '');

                    return $text !== '' && str_contains($text, '{{');
                })
                ->columnSpanFull(),
        ];
    }

    /** ---------- Result handling ---------- */
    public function updatedPage(): void
    {
        $this->loadResults();
    }

    public function updatedPerPage(): void
    {
        $this->page = 1;
        $this->loadResults();
    }

    public function applyFilters(): void
    {
        // State already lives in $this->filters via statePath
        $this->page = 1;
        $this->loadResults();
    }

    public function clearFilters(): void
    {
        $this->filters = [
            'last_booking_from' => null,
            'last_booking_to' => null,
            'min_bookings' => null,
            'lapsed_days' => null,
            'last_branch_id' => [],
            'wa_recent_days' => null,
            'wa_inbound_since' => null,
            'wa_outbound_since' => null,
            'source_kind' => null,
        ];
        $this->filtersForm->fill($this->filters);
        $this->page = 1;
        $this->loadResults();
    }

    public function loadResults(): void
    {
        $query = $this->buildQuery();
        $this->resultsCount = (clone $query)->count();

        $offset = max(0, ($this->page - 1) * $this->perPage);

        $this->results = $query
            ->offset($offset)
            ->limit($this->perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    protected function buildQuery(): \Illuminate\Database\Query\Builder
    {
        /** @var Builder $query */
        $query = DB::table('vw_audience_metrics');

        $f = $this->filters;

        if (! empty($f['last_booking_from'])) {
            $query->where('last_booking_at', '>=', $f['last_booking_from'].' 00:00:00');
        }
        if (! empty($f['last_booking_to'])) {
            $query->where('last_booking_at', '<=', $f['last_booking_to'].' 23:59:59');
        }
        if (! empty($f['min_bookings'])) {
            $query->where('bookings_count', '>=', (int) $f['min_bookings']);
        }
        if (! empty($f['lapsed_days'])) {
            $cut = Carbon::now()->subDays((int) $f['lapsed_days']);
            $query->where(fn ($q) => $q->whereNull('last_booking_at')->orWhere('last_booking_at', '<', $cut));
        }
        if (! empty($f['last_branch_id'])) {
            $query->whereIn('last_branch_id', (array) $f['last_branch_id']);
        }
        if (! empty($f['wa_recent_days'])) {
            $query->where('last_interaction_at', '>=', now()->subDays((int) $f['wa_recent_days']));
        }
        if (! empty($f['wa_inbound_since'])) {
            $query->where('last_wa_in_at', '>=', now()->subDays((int) $f['wa_inbound_since']));
        }
        if (! empty($f['wa_outbound_since'])) {
            $query->where('last_wa_out_at', '>=', now()->subDays((int) $f['wa_outbound_since']));
        }
        if (! empty($f['source_kind'])) {
            $query = match ($f['source_kind']) {
                'booked_only' => $query->where('bookings_count', '>', 0)
                    ->whereNull('last_wa_in_at')
                    ->whereNull('last_wa_out_at')
                    ->whereNull('session_last_interacted_at'),
                'wa_only' => $query->where('bookings_count', '=', 0)
                    ->where(fn ($x) => $x->whereNotNull('last_wa_in_at')
                        ->orWhereNotNull('last_wa_out_at')
                        ->orWhereNotNull('session_last_interacted_at')),
                'both' => $query->where('bookings_count', '>', 0)
                    ->where(fn ($x) => $x->whereNotNull('last_wa_in_at')
                        ->orWhereNotNull('last_wa_out_at')
                        ->orWhereNotNull('session_last_interacted_at')),
                default => $query,
            };
        }

        return $query->orderBy('last_interaction_at', 'desc');
    }

    /** ---------- Toggle forms ---------- */
    public function openAddForm(): void
    {
        $this->showAddForm = true;
        $this->addToCampaignForm->fill($this->addForm);
    }

    public function closeAddForm(): void
    {
        $this->showAddForm = false;
    }

    public function openCreateForm(): void
    {
        $this->showCreateForm = true;
        $this->createCampaignForm->fill($this->createForm);
    }

    public function closeCreateForm(): void
    {
        $this->showCreateForm = false;
    }

    /** ---------- Actions ---------- */
    public function submitAddForm(): void
    {
        $this->addForm = $this->addToCampaignForm->getState();
        $this->addToCampaign($this->addForm);
    }

    public function addToCampaign(array $data): void
    {
        $campaign = BulkInviteCampaign::find($data['campaign_id'] ?? null);
        if (! $campaign) {
            Notification::make()->title('Missing campaign')->danger()->send();

            return;
        }

        $msisdns = $this->buildQuery()->pluck('msisdn')->toArray();
        if (empty($msisdns)) {
            Notification::make()->title('No results')->body('Your current filters returned 0 contacts.')->danger()->send();

            return;
        }

        $rows = [];
        foreach ($msisdns as $msisdn) {
            $rows[] = [
                'bulk_invite_campaign_id' => $campaign->id,
                'msisdn' => $msisdn,
                'name' => null,
                'locale' => $data['default_locale'] ?? 'en',
                'source' => 'system',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($rows, $campaign, $data) {
            DB::table('bulk_invite_campaign_recipients')->upsert(
                $rows,
                ['bulk_invite_campaign_id', 'msisdn'],
                ['name', 'locale', 'source', 'status', 'updated_at']
            );

            if (! empty($data['queue_now'])) {
                $campaign->status = $campaign->scheduled_at ? 'scheduled' : 'running';
                $campaign->save();

                $ids = $campaign->recipients()
                    ->whereIn('msisdn', Arr::pluck($rows, 'msisdn'))
                    ->pluck('id');

                foreach ($ids as $rid) {
                    dispatch(new SendCampaignInvite($campaign->id, $rid));
                }
            }
        });

        Notification::make()
            ->title('Recipients added')
            ->body('Audience results added to the selected campaign'.(! empty($data['queue_now']) ? ' and queued.' : '.'))
            ->success()
            ->send();

        $this->closeAddForm();
    }

    public function submitCreateForm(): RedirectResponse
    {
        $this->createForm = $this->createCampaignForm->getState();

        return $this->createCampaignFromResults($this->createForm);
    }

    public function createCampaignFromResults(array $data): RedirectResponse
    {
        $details = $data['template_details'] ?? [];
        $components = data_get($details, 'components', []);
        $body = collect($components)->firstWhere('type', 'BODY');
        $text = (string) data_get($body, 'text', '');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
        $reqIndexes = collect($m[1] ?? [])->map(fn ($i) => (string) ((int) $i))->unique();

        if ($reqIndexes->isNotEmpty()) {
            foreach ($reqIndexes as $i) {
                if (empty(Arr::get($data['template_variables'] ?? [], $i))) {
                    Notification::make()->title('Variable missing')->body("Template variable {{$i}} is required.")->danger()->send();

                    return back();
                }
            }
        }

        $msisdns = $this->buildQuery()->pluck('msisdn')->toArray();
        if (empty($msisdns)) {
            Notification::make()->title('No audience')->body('Your current filters returned 0 contacts.')->danger()->send();

            return back();
        }

        $campaign = DB::transaction(function () use ($data, $details) {
            $c = new BulkInviteCampaign;
            $c->name = $data['name'];
            $c->template_name = $data['template_name'];
            $c->template_details = $details;
            $c->template_variables = $data['template_variables'] ?? [];
            $c->default_locale = $data['default_locale'] ?? 'en';
            $c->header_image_path = $data['header_image_path'] ?? null;
            $c->scheduled_at = $data['scheduled_at'] ?? null;
            $c->send_rate_per_min = $data['send_rate_per_min'] ?? 600;
            $c->status = $c->scheduled_at ? 'scheduled' : 'running';
            $c->save();

            return $c;
        });

        $rows = [];
        foreach ($msisdns as $msisdn) {
            $rows[] = [
                'bulk_invite_campaign_id' => $campaign->id,
                'msisdn' => $msisdn,
                'name' => null,
                'locale' => $campaign->default_locale,
                'source' => 'system',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($rows, $campaign) {
            DB::table('bulk_invite_campaign_recipients')->upsert(
                $rows,
                ['bulk_invite_campaign_id', 'msisdn'],
                ['name', 'locale', 'source', 'status', 'updated_at']
            );

            $ids = $campaign->recipients()
                ->whereIn('msisdn', Arr::pluck($rows, 'msisdn'))
                ->pluck('id');

            foreach ($ids as $rid) {
                dispatch(new SendCampaignInvite($campaign->id, $rid));
            }
        });

        Notification::make()
            ->title('Campaign created & queued')
            ->body("Created '{$campaign->name}' and queued ".count($msisdns).' recipients.')
            ->success()
            ->send();

        $this->closeCreateForm();

        return redirect(\App\Filament\Resources\BulkInviteCampaignResource::getUrl('edit', ['record' => $campaign]));
    }
}
