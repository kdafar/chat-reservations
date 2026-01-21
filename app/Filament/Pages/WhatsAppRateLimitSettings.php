<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Support\Settings as Sys;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatsAppRateLimitSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Rate Limit / Cooldown';

    protected static ?string $title = 'WhatsApp Rate Limit / Cooldown';

    protected static ?int $navigationSort = 99;

    // keep if you use a wrapper blade; otherwise remove this line
    protected static string $view = 'filament.pages.generic-form-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_whats-app-rate-limit-settings');
    }

    private const MISS = '__@@MISS@@__'; // sentinel to detect missing bucket values

    public function mount(): void
    {
        $this->form->fill([
            // rate-limit (bucket under "whatsapp")
            'enabled' => (bool) Sys::get('whatsapp.rate_limit.enabled', true),
            'window_seconds' => (int) Sys::get('whatsapp.rate_limit.window_seconds', 20),
            'limit' => (int) Sys::get('whatsapp.rate_limit.limit', 3),
            'cooldown_seconds' => (int) Sys::get('whatsapp.rate_limit.cooldown_seconds', 30),
            'message_en' => (string) Sys::get('whatsapp.rate_limit.message_en', 'You’re sending messages too quickly. Please try again in {seconds}s.'),
            'message_ar' => (string) Sys::get('whatsapp.rate_limit.message_ar', 'تم تقييد الرسائل مؤقتًا بسبب كثرة الإرسال. الرجاء المحاولة بعد {seconds} ثانية.'),

            // flow toggle — prefer bucket (whatsapp.flow.enabled), but fall back to flat row if present
            'flow' => [
                'enabled' => (bool) $this->getWithFlatFallback('whatsapp.flow.enabled', true),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Rate Limit / Cooldown')
                    ->description('Control anti-spam behavior for inbound WhatsApp messages.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable rate limit')
                            ->default(true),

                        Forms\Components\TextInput::make('window_seconds')
                            ->label('Window (seconds)')
                            ->numeric()->minValue(5)->maxValue(300)->required(),

                        Forms\Components\TextInput::make('limit')
                            ->label('Allowed messages in window')
                            ->numeric()->minValue(1)->maxValue(50)->required(),

                        Forms\Components\TextInput::make('cooldown_seconds')
                            ->label('Cooldown (seconds)')
                            ->numeric()->minValue(5)->maxValue(600)->required(),
                    ])->columns(4),

                Forms\Components\Section::make('WhatsApp Flow Settings')
                    ->description('Control Booking Flow behavior for WhatsApp messages.')
                    ->schema([
                        Forms\Components\Toggle::make('flow.enabled')
                            ->label('Enable Booking Flow')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Messages')
                    ->description('Use {seconds} placeholder to show remaining/total seconds.')
                    ->schema([
                        Forms\Components\Textarea::make('message_en')
                            ->label('Cooldown message (EN)')
                            ->rows(2)->maxLength(500),

                        Forms\Components\Textarea::make('message_ar')
                            ->label('Cooldown message (AR)')
                            ->rows(2)->maxLength(500),
                    ])->columns(2),

                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('save')
                        ->label('Save settings')
                        ->submit('save'),
                ])->alignLeft(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Bucketed saves (all under key "whatsapp")
        Sys::set('whatsapp.rate_limit.enabled', (bool) data_get($data, 'enabled', true));
        Sys::set('whatsapp.rate_limit.window_seconds', (int) data_get($data, 'window_seconds', 20));
        Sys::set('whatsapp.rate_limit.limit', (int) data_get($data, 'limit', 3));
        Sys::set('whatsapp.rate_limit.cooldown_seconds', (int) data_get($data, 'cooldown_seconds', 30));
        Sys::set('whatsapp.rate_limit.message_en', (string) data_get($data, 'message_en', ''));
        Sys::set('whatsapp.rate_limit.message_ar', (string) data_get($data, 'message_ar', ''));

        // Flow toggle — save to bucket AND mirror to the existing flat row for compatibility
        $flowEnabled = (bool) data_get($data, 'flow.enabled', true);
        Sys::set('whatsapp.flow.enabled', $flowEnabled);
        SystemSetting::updateOrCreate(
            ['key' => 'whatsapp.flow.enabled'],
            ['value' => $flowEnabled] // model casts value->array, but scalars will still json_encode/decode fine
        );

        Notification::make()->title('Saved')->success()->send();
    }

    /**
     * Prefer bucketed "whatsapp.*" key via Settings; if missing, fall back to the flat row.
     */
    private function getWithFlatFallback(string $key, mixed $default = null): mixed
    {
        $v = Sys::get($key, self::MISS);
        if ($v !== self::MISS) {
            return $v;
        }

        $row = SystemSetting::where('key', $key)->first();

        return $row?->value ?? $default; // with cast=['value'=>'array'], scalars (true/false/"text") still round-trip
    }
}
