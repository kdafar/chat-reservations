<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Services\MetaHealthService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class WhatsAppCampaignSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Campaign Settings';

    protected static ?string $title = 'WhatsApp Campaign Settings';

    protected static ?int $navigationSort = 98;

    // Use your simple wrapper blade; or remove to use default Page.
    protected static string $view = 'filament.pages.generic-form-page';

    public ?array $data = [];

    public ?array $health = null;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_whats-app-campaign-settings');
    }

    public function mount(): void
    {
        // Load campaign JSON with defaults
        $s = optional(SystemSetting::where('key', 'wa.campaigns')->first())->value ?? [];

        $this->form->fill([
            'batch_size' => (int) ($s['batch_size'] ?? 200),
            'mps' => (int) ($s['mps'] ?? 5),
            'pair_gap_seconds' => (int) ($s['pair_gap_seconds'] ?? 6),
            'quiet_start' => (int) ($s['quiet_start'] ?? 21),
            'quiet_end' => (int) ($s['quiet_end'] ?? 9),
            'sending_paused' => (bool) ($s['sending_paused'] ?? false),
        ]);

        $this->refreshHealth();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Campaign Sending')
                    ->description('Control batch sizing, speed, and quiet hours for WhatsApp outbound campaigns.')
                    ->schema([
                        Forms\Components\TextInput::make('batch_size')
                            ->label('Batch size')
                            ->numeric()->minValue(10)->maxValue(5000)->required()
                            ->helperText('Recipients fetched per scheduler cycle.'),

                        Forms\Components\TextInput::make('mps')
                            ->label('Messages per second (MPS)')
                            ->numeric()->minValue(1)->maxValue(30)->required()
                            ->helperText('Throttle to avoid rate-limit/quality hits.'),

                        Forms\Components\TextInput::make('pair_gap_seconds')
                            ->label('Gap per user (seconds)')
                            ->numeric()->minValue(0)->maxValue(300)->required()
                            ->helperText('Optional spacing between multiple messages to the same user.'),

                        Forms\Components\Select::make('quiet_start')
                            ->label('Quiet start (hour)')
                            ->options($this->hoursOptions())
                            ->required(),

                        Forms\Components\Select::make('quiet_end')
                            ->label('Quiet end (hour)')
                            ->options($this->hoursOptions())
                            ->required(),

                        Forms\Components\Toggle::make('sending_paused')
                            ->label('Pause all campaign sending')
                            ->helperText('Emergency kill-switch for outbound.'),
                    ])->columns(3),

                Forms\Components\Section::make('Meta Health (read-only)')
                    ->description('Live health pulled from Meta Graph for your WhatsApp phone number.')
                    ->schema([
                        Forms\Components\Placeholder::make('display_phone_number')
                            ->label('Display phone')
                            ->content(fn () => $this->hv('data.display_phone_number')),

                        Forms\Components\Placeholder::make('phone_number_id')
                            ->label('Phone Number ID')
                            ->content(fn () => $this->hv('data.phone_number_id')),

                        Forms\Components\Placeholder::make('graph_version')
                            ->label('Graph version')
                            ->content(fn () => $this->hv('data.graph_version')),

                        Forms\Components\Placeholder::make('quality_rating')
                            ->label('Quality rating')
                            ->content(fn () => $this->healthBadge($this->hv('data.quality_rating', 'UNKNOWN'))),

                        Forms\Components\Placeholder::make('name_status')
                            ->label('Name status')
                            ->content(fn () => $this->hv('data.name_status')),

                        Forms\Components\Placeholder::make('verified_name')
                            ->label('Display name (submitted)')
                            ->content(fn () => $this->hv('data.verified_name')),

                        Forms\Components\Placeholder::make('waba_id')
                            ->label('WABA ID')
                            ->content(fn () => $this->hv('data.waba_id')),

                        Forms\Components\Placeholder::make('waba_name')
                            ->label('WABA Name')
                            ->content(fn () => $this->hv('data.waba_name')),

                        Forms\Components\Placeholder::make('is_oba')
                            ->label('Official Business Account')
                            ->content(fn () => match (strtolower($this->hv('data.is_official_business_account', ''))) {
                                '1', 'true' => 'Yes (green tick eligible)',
                                '0', 'false' => 'No',
                                default => 'Unknown',
                            }),

                        Forms\Components\Placeholder::make('templates_total')
                            ->label('Templates (total)')
                            ->content(fn () => $this->hv('data.templates_total', '—')),
                    ])->columns(3),

                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('save')
                        ->label('Save settings')
                        ->submit('save')
                        ->color('primary')
                        ->icon('heroicon-o-check-circle'),

                    Forms\Components\Actions\Action::make('refresh-health')
                        ->label('Refresh Meta Health')
                        ->action('refreshHealth')
                        ->color('gray')
                        ->icon('heroicon-o-arrow-path'),
                ])->alignLeft(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $d = $this->form->getState();

        SystemSetting::updateOrCreate(
            ['key' => 'wa.campaigns'],
            ['value' => [
                'batch_size' => (int) $d['batch_size'],
                'mps' => (int) $d['mps'],
                'pair_gap_seconds' => (int) $d['pair_gap_seconds'],
                'quiet_start' => (int) $d['quiet_start'],
                'quiet_end' => (int) $d['quiet_end'],
                'sending_paused' => (bool) $d['sending_paused'],
            ]]
        );

        cache()->forget('settings.wa.campaigns');

        Notification::make()->title('Saved')->success()->send();
    }

    public function refreshHealth(): void
    {
        $this->health = app(MetaHealthService::class)->fetch();

        if (! ($this->health['ok'] ?? false)) {
            Notification::make()
                ->title('Meta Health fetch failed')
                ->body($this->health['error'] ?? 'Unknown error')
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Meta Health refreshed')->success()->send();
    }

    // ---------- helpers ----------

    private function hoursOptions(): array
    {
        $opts = [];
        for ($h = 0; $h <= 23; $h++) {
            $opts[$h] = str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00';
        }

        return $opts;
    }

    private function hv(string $path, string $default = '—'): string
    {
        return (string) data_get($this->health, $path, $default);
    }

    private function healthBadge(string $rating): HtmlString
    {
        $rating = strtoupper($rating);
        $color = match ($rating) {
            'GREEN' => '#16a34a',
            'YELLOW' => '#ca8a04',
            'RED' => '#dc2626',
            default => '#6b7280',
        };

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:.5rem">'
            .'<span style="width:.55rem;height:.55rem;border-radius:9999px;background:'.$color.'"></span>'
            .'<span style="font-weight:600">'.$rating.'</span>'
            .'</span>'
        );
    }
}
