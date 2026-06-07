<?php

namespace App\Wa\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Wave\Setting;

class ManageWhatsAppSettings extends BasePermissionPage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $permission = 'view_manage_whats_app_settings';

    protected static ?string $navigationIcon = 'phosphor-whatsapp-logo-duotone';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $view = 'filament.pages.manage-whatsapp-settings';

    public ?array $data = [];

    public function mount(): void
    {
        // Load existing settings into the form
        $this->form->fill([
            'about_reply_en' => setting('whatsapp.about_reply_en'),
            'about_reply_ar' => setting('whatsapp.about_reply_ar'),
            'stop_words' => setting('whatsapp.stop_words'),

            // Load whitelist as array for TagsInput
            'frequency_cap_whitelist' => $this->getTagsFromSetting('whatsapp.frequency_cap_whitelist'),
        ]);
    }

    protected function getTagsFromSetting($key): array
    {
        $val = setting($key);
        if (! $val) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $val)));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bot Replies')
                    ->schema([
                        Forms\Components\Textarea::make('about_reply_en')->label('About Reply (English)')->required(),
                        Forms\Components\Textarea::make('about_reply_ar')->label('About Reply (Arabic)')->required(),
                    ]),

                Forms\Components\Section::make('Search Settings')
                    ->schema([
                        Forms\Components\Textarea::make('stop_words')
                            ->label('Search Stop Words')
                            ->helperText('A comma-separated list of words to ignore in search queries.')
                            ->required(),
                    ]),

                Forms\Components\Section::make('Campaign Safeguards')
                    ->schema([
                        Forms\Components\TagsInput::make('frequency_cap_whitelist')
                            ->label('Frequency Cap Whitelist')
                            // 👇 Here is the example and instruction you asked for
                            ->helperText('Enter numbers in E.164 format (e.g., +96590000000). Type a number and press Enter, Comma, or Space to add multiple.')
                            ->placeholder('Add phone number...')
                            ->splitKeys(['Tab', ' ', ','])
                            ->reorderable(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $formData = $this->form->getState();

        foreach ($formData as $key => $value) {
            // Handle array values (TagsInput) by converting to comma-separated string
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $fullKey = 'whatsapp.'.$key;

            Setting::updateOrCreate(
                ['key' => $fullKey],
                [
                    'display_name' => ucfirst(str_replace('_', ' ', $key)).' (WhatsApp)',
                    'value' => $value,
                    'type' => 'text',
                    'group' => 'WhatsApp',
                ]
            );

            // Bust the cache for this specific key if used by helpers
            Cache::forget('settings.'.$fullKey);
        }

        // Also clear the main Wave settings cache
        Cache::forget('wave_settings');

        Notification::make()->title('Settings saved successfully!')->success()->send();
    }
}
