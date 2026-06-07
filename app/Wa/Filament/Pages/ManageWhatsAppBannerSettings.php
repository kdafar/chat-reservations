<?php

namespace App\Wa\Filament\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Wave\Setting;

class ManageWhatsAppBannerSettings extends BasePermissionPage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $permission = 'view_manage_whats_app_banner_settings';

    protected static ?string $navigationIcon = 'phosphor-whatsapp-logo-duotone';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $title = 'WhatsApp Bot Settings';

    protected static string $view = 'filament.pages.manage-whatsapp-banner-settings';

    public ?array $data = [];

    protected array $keyMap = [
        'entry_mode' => 'whatsapp.entry_mode',

        // Greeting (Banner Mode)
        'intro_en' => 'whatsapp.banner_greeting_en',
        'intro_ar' => 'whatsapp.banner_greeting_ar',

        // Donations (Previously Pricing)
        'pricing_reply_en' => 'whatsapp.pricing_reply_en',
        'pricing_reply_ar' => 'whatsapp.pricing_reply_ar',

        // Privacy
        'privacy_reply_en' => 'whatsapp.privacy_reply_en',
        'privacy_reply_ar' => 'whatsapp.privacy_reply_ar',

        // About Us
        'about_reply_en' => 'whatsapp.about_reply_en',
        'about_reply_ar' => 'whatsapp.about_reply_ar',

        // General Fallback
        'fallback_reply_en' => 'whatsapp.fallback_reply_en',
        'fallback_reply_ar' => 'whatsapp.fallback_reply_ar',

        // Flow Welcome Message (Restaurant Mode)
        'flow_welcome_en' => 'whatsapp.flow_welcome_en',
        'flow_welcome_ar' => 'whatsapp.flow_welcome_ar',

        // Stop Keywords (JSON Array)
        'stop_keywords' => 'whatsapp.detailed_requirement_keywords',
    ];

    public function mount(): void
    {
        $state = [];

        foreach ($this->keyMap as $field => $settingKey) {
            // We use direct DB query to avoid cache issues when manually updating SQL
            $value = Setting::where('key', $settingKey)->value('value');

            // FIX: If this is the keywords field, decode JSON string to Array
            if ($field === 'stop_keywords') {
                $decoded = json_decode($value, true);
                $state[$field] = is_array($decoded) ? $decoded : [];
            } else {
                $state[$field] = $value;
            }
        }

        // Sensible default
        $state['entry_mode'] ??= 'banner';

        $this->form->fill($state);
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Changes')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1. Mode Selection
                Forms\Components\Section::make('Bot Configuration')
                    ->description('Choose how the bot behaves when a user first says Hello.')
                    ->schema([
                        Forms\Components\Select::make('entry_mode')
                            ->label('Entry Mode')
                            ->options([
                                'banner' => 'Charity / Info Mode (Auto-replies)',
                                'flow' => 'Restaurant Order Flow (Interactive Menu)',
                            ])
                            ->required()
                            ->helperText('Charity Mode uses the auto-replies below. Restaurant Flow uses the interactive menu system.'),
                    ]),

                // 2. Charity / Info Mode Settings
                Forms\Components\Section::make('Charity Mode Messages')
                    ->description('These messages are used when Entry Mode is set to "Charity Mode".')
                    ->collapsible()
                    ->schema([

                        // Intro
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('intro_en')
                                ->label('Welcome / Intro (English)')
                                ->rows(4),
                            Forms\Components\Textarea::make('intro_ar')
                                ->label('Welcome / Intro (Arabic)')
                                ->rows(4),
                        ]),

                        // Donations
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('pricing_reply_en')
                                ->label('Donations / Projects (English)')
                                ->helperText('Triggered by: donate, zakat, wells, cost'),
                            Forms\Components\Textarea::make('pricing_reply_ar')
                                ->label('Donations / Projects (Arabic)')
                                ->helperText('Triggered by: تبرع، زكاة، آبار، مشاريع'),
                        ]),

                        // About Us
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('about_reply_en')
                                ->label('About Us (English)')
                                ->helperText('Triggered by: who are you, website, license'),
                            Forms\Components\Textarea::make('about_reply_ar')
                                ->label('About Us (Arabic)')
                                ->helperText('Triggered by: من انتم، ترخيص، موقع'),
                        ]),

                        // Privacy
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('privacy_reply_en')
                                ->label('Privacy Reply (English)')
                                ->helperText('Triggered by: privacy, where did you get my number'),
                            Forms\Components\Textarea::make('privacy_reply_ar')
                                ->label('Privacy Reply (Arabic)')
                                ->helperText('Triggered by: خصوصية، من وين جبتو رقمي'),
                        ]),

                        // Fallback
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('fallback_reply_en')
                                ->label('General Fallback (English)')
                                ->helperText('Sent when the bot does not understand the message.'),
                            Forms\Components\Textarea::make('fallback_reply_ar')
                                ->label('General Fallback (Arabic)')
                                ->helperText('Sent when the bot does not understand the message.'),
                        ]),
                    ]),

                // 3. Restaurant / Flow Settings
                Forms\Components\Section::make('Restaurant Flow Messages')
                    ->description('These messages are used when Entry Mode is set to "Restaurant Order Flow".')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('flow_welcome_en')
                                ->label('Flow Welcome Message (English)')
                                ->helperText('The text shown above the "Browse Services" button.'),
                            Forms\Components\Textarea::make('flow_welcome_ar')
                                ->label('Flow Welcome Message (Arabic)')
                                ->helperText('The text shown above the "Browse Services" button.'),
                        ]),
                    ]),

                // 4. Advanced Logic (Stop Keywords)
                Forms\Components\Section::make('Advanced Bot Logic')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TagsInput::make('stop_keywords')
                            ->label('Stop Bot Keywords (Complex Requests)')
                            ->helperText('If a user message contains these words, the bot will NOT auto-reply (useful for handling complex human queries).')
                            ->placeholder('Add keyword...')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $formData = $this->form->getState();

        foreach ($this->keyMap as $field => $settingKey) {
            $value = $formData[$field] ?? null;

            // FIX: If it's an array (like stop_keywords), encode back to JSON
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            Setting::updateOrCreate(
                ['key' => $settingKey],
                [
                    'display_name' => ucfirst(str_replace('_', ' ', $field)).' (WA)',
                    'value' => $value,
                    'type' => 'text',
                    'group' => 'WhatsApp',
                ]
            );

            Cache::forget('settings.'.$settingKey);
        }

        Notification::make()->title('Settings Saved')->success()->send();
    }
}
