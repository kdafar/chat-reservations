<?php

namespace App\Wa\Filament\WhatsApp\Pages;

use App\Wa\Models\WhatsApp\WaNumber;
use App\Wa\Services\WhatsApp\Tenant\TenantWhatsAppService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SendTemplateMessage extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Send Template';

    protected static ?string $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.whatsapp.pages.send-template-message';

    /** Form states */
    public ?array $sendTestData = [];

    /** Select options (templates from Meta) */
    public array $templateOptions = [];

    /**
     * Raw template definitions keyed by "name|language"
     *
     * @var array<string, array>
     */
    public array $templateDefinitions = [];

    public function getTitle(): string
    {
        return 'Send Template Message';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        // NEW: Check for a template from the URL query parameter
        $templateFromUrl = request()->query('template');

        $defaultNumber = WaNumber::where('status', 'connected')->first();
        $this->templateOptions = $this->loadTemplateOptions($defaultNumber);

        // NEW: Use the template from the URL if it exists, otherwise use the first
        $defaultTemplateKey = ($templateFromUrl && array_key_exists($templateFromUrl, $this->templateOptions))
            ? $templateFromUrl
            : array_key_first($this->templateOptions);

        $defaultVariables = $this->buildVariableDefaultsForTemplate($defaultTemplateKey);

        $defaultHeaderType = null;
        if ($defaultTemplateKey) {
            $tpl = $this->templateDefinitions[$defaultTemplateKey] ?? null;
            $header = $this->findComponent($tpl, 'HEADER');
            $defaultHeaderType = $header['format'] ?? null;
        }

        // The form will now be pre-filled with the template from the URL
        $this->sendTestForm->fill([
            'wa_number_id' => $defaultNumber?->id,
            'phone' => '',
            'template' => $defaultTemplateKey,
            'header_type' => $defaultHeaderType,
            'variables' => $defaultVariables,
        ]);
    }

    protected function loadTemplateOptions(?WaNumber $number = null): array
    {
        $this->templateDefinitions = [];
        try {
            /** @var TenantWhatsAppService $wa */
            $wa = app(TenantWhatsAppService::class);
            if ($number) {
                $wa = $wa->forNumber($number);
            }
            $templates = $wa->listTemplates('APPROVED');
            $options = [];
            foreach ($templates as $tpl) {
                $name = $tpl['name'] ?? null;
                $lang = $tpl['language'] ?? null;
                if (! $name || ! $lang) {
                    continue;
                }
                $key = $name.'|'.$lang;
                $label = "{$name} · {$lang} · {$tpl['category']} · {$tpl['status']}";
                $options[$key] = $label;
                $this->templateDefinitions[$key] = $tpl;
            }

            return $options;
        } catch (\Throwable $e) {
            \Log::error('[SendTemplateMessage] Failed to fetch templates', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function getForms(): array
    {
        return [
            'sendTestForm' => $this->makeForm()
                ->schema([
                    Forms\Components\Section::make('Send Test Template Message')
                        ->description('Use an existing template to send a test message to a WhatsApp number.')
                        ->schema([
                            Forms\Components\Select::make('wa_number_id')
                                ->label('WhatsApp Number')
                                ->options(
                                    WaNumber::query()
                                        ->where('status', 'connected')
                                        ->pluck('display_phone_number', 'id')
                                        ->toArray()
                                )
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('phone')
                                    ->label('Recipient WhatsApp Number')
                                    ->placeholder('e.g. 9655XXXXXXX')
                                    ->helperText('Full international format without + or 00.')
                                    ->required(),

                                Forms\Components\Select::make('template')
                                    ->label('Template')
                                    ->options(fn () => $this->templateOptions)
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('variables', $this->buildVariableDefaultsForTemplate($state));
                                        $tpl = $this->templateDefinitions[$state] ?? null;
                                        $header = $this->findComponent($tpl, 'HEADER');
                                        $headerType = $header['format'] ?? null;
                                        $set('header_type', $headerType);
                                        $set('header_image_url', null);
                                        $set('header_video_url', null);
                                        $set('header_document_url', null);
                                        $set('header_document_filename', null);
                                    })
                                    ->required(),
                            ]),

                            Forms\Components\Hidden::make('header_type')->reactive(),

                            Forms\Components\TextInput::make('header_image_url')
                                ->label('Header Image URL')
                                ->helperText('A public URL (https://...) to the image.')
                                ->url()
                                ->reactive()
                                ->hidden(fn (Get $get) => $get('header_type') !== 'IMAGE')
                                ->required(fn (Get $get) => $get('header_type') === 'IMAGE'),

                            Forms\Components\TextInput::make('header_video_url')
                                ->label('Header Video URL')
                                ->helperText('A public URL (https://...) to the video.')
                                ->url()
                                ->reactive()
                                ->hidden(fn (Get $get) => $get('header_type') !== 'VIDEO')
                                ->required(fn (Get $get) => $get('header_type') === 'VIDEO'),

                            Forms\Components\Grid::make(2)
                                ->hidden(fn (Get $get) => $get('header_type') !== 'DOCUMENT')
                                ->schema([
                                    Forms\Components\TextInput::make('header_document_url')
                                        ->label('Header Document URL')
                                        ->helperText('A public URL (https://...) to the document.')
                                        ->url()
                                        ->reactive()
                                        ->required(fn (Get $get) => $get('header_type') === 'DOCUMENT'),
                                    Forms\Components\TextInput::make('header_document_filename')
                                        ->label('Filename (Optional)')
                                        ->helperText('e.g. invoice.pdf')
                                        ->reactive(),
                                ]),

                            Forms\Components\Placeholder::make('preview')
                                ->label('Live Preview')
                                ->columnSpanFull()
                                ->content(fn (Get $get): HtmlString => $this->renderPreview($get)),

                            Forms\Components\Repeater::make('variables')
                                ->label('Template variables')
                                ->helperText('One row per {{n}} placeholder in the template (header, body, buttons).')
                                ->schema([
                                    Forms\Components\TextInput::make('index')
                                        ->label('#')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('value')
                                        ->label('Value')
                                        ->placeholder('Value for this placeholder')
                                        ->reactive()
                                        ->columnSpan(3),
                                    Forms\Components\TextInput::make('hint')
                                        ->label('Used in')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(4),
                                ])
                                ->columns(8)
                                ->default([])
                                ->columnSpanFull(),
                        ]),
                ])
                ->statePath('sendTestData'),
        ];
    }

    public function sendTestMessage(): void
    {
        $data = $this->sendTestForm->getState();
        $phone = trim((string) ($data['phone'] ?? ''));
        $templateKey = $data['template'] ?? null;
        $waNumberId = $data['wa_number_id'] ?? null;

        if (! $phone || ! $templateKey || ! $waNumberId) {
            Notification::make()->title('Missing required fields')->danger()->send();

            return;
        }

        $number = WaNumber::find($waNumberId);
        if (! $number) {
            Notification::make()->title('Selected WhatsApp number not found')->danger()->send();

            return;
        }

        [$name, $lang] = array_pad(explode('|', $templateKey), 2, 'en');

        try {
            // NOTE: This is where you would dispatch a Job for multi-user sending
            // SendWhatsAppTemplateJob::dispatch($number, $phone, $templateKey, $data);

            $components = $this->buildComponentsForTemplatePayload($templateKey, $data);
            $templatePayload = ['name' => $name, 'language' => ['code' => $lang]];
            if (! empty($components)) {
                $templatePayload['components'] = $components;
            }

            /** @var TenantWhatsAppService $wa */
            $wa = app(TenantWhatsAppService::class)->forNumber($number);
            $wa->sendTemplate($phone, $templatePayload);

            Notification::make()
                ->title('Template message sent')
                ->body("Sent '{$name}' to {$phone}.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error sending template')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ====================================================================
    //  ALL HELPER FUNCTIONS
    //  (buildVariableDefaults, findComponent, buildComponents, renderPreview)
    // ====================================================================

    protected function buildVariableDefaultsForTemplate(?string $templateKey): array
    {
        if (! $templateKey || ! isset($this->templateDefinitions[$templateKey])) {
            return [];
        }
        $tpl = $this->templateDefinitions[$templateKey];
        $components = $tpl['components'] ?? [];
        $indicesByContext = [];

        $scan = function (string $text, string $context) use (&$indicesByContext): void {
            if (preg_match_all('/\{\{(\d+)\}\}/', $text, $matches)) {
                foreach ($matches[1] as $idx) {
                    $i = (int) $idx;
                    $indicesByContext[$i] ??= ['contexts' => []];
                    $indicesByContext[$i]['contexts'][] = $context;
                }
            }
        };

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');
            if (in_array($type, ['HEADER', 'BODY'], true) && isset($component['text'])) {
                $scan($component['text'], strtolower($type));
            }
            if ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $btnIndex => $button) {
                    if (($button['type'] ?? '') === 'URL' && isset($button['url'])) {
                        $label = $button['text'] ?? ('Button '.($btnIndex + 1));
                        $scan($button['url'], "button URL ({$label})");
                    }
                }
            }
        }

        if (empty($indicesByContext)) {
            return [];
        }
        ksort($indicesByContext);
        $rows = [];
        foreach ($indicesByContext as $index => $info) {
            $rows[] = [
                'index' => $index,
                'value' => '',
                'hint' => implode(' · ', array_unique($info['contexts'])),
            ];
        }

        return $rows;
    }

    protected function findComponent(?array $template, string $type): ?array
    {
        if (empty($template['components'])) {
            return null;
        }
        foreach ($template['components'] as $component) {
            if (strtoupper($component['type'] ?? '') === $type) {
                return $component;
            }
        }

        return null;
    }

    protected function buildComponentsForTemplatePayload(string $templateKey, array $formData): array
    {
        if (! isset($this->templateDefinitions[$templateKey])) {
            return [];
        }
        $tpl = $this->templateDefinitions[$templateKey];
        $components = $tpl['components'] ?? [];
        if (empty($components)) {
            return [];
        }

        $valueMap = [];
        foreach ($formData['variables'] ?? [] as $row) {
            $idx = (int) ($row['index'] ?? 0);
            $val = trim((string) ($row['value'] ?? ''));
            if ($idx > 0 && $val !== '') {
                $valueMap[$idx] = $val;
            }
        }

        $scanIndices = static fn (string $text): array => preg_match_all('/\{\{(\d+)\}\}/', $text, $matches) ? array_values(array_unique(array_map('intval', $matches[1]))) : [];

        $buildParams = static function (array $indices, array $valueMap): array {
            sort($indices);
            $params = [];
            foreach ($indices as $i) {
                $params[] = ['type' => 'text', 'text' => $valueMap[$i] ?? ''];
            }

            return $params;
        };

        $messageComponents = [];
        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');

            if ($type === 'HEADER') {
                $format = strtoupper($component['format'] ?? 'TEXT');
                if ($format === 'TEXT') {
                    if ($indices = $scanIndices($component['text'])) {
                        $messageComponents[] = ['type' => 'header', 'parameters' => $buildParams($indices, $valueMap)];
                    }
                } elseif ($format === 'IMAGE' && ! empty($formData['header_image_url'])) {
                    $messageComponents[] = ['type' => 'header', 'parameters' => [['type' => 'image', 'image' => ['link' => $formData['header_image_url']]]]];
                } elseif ($format === 'VIDEO' && ! empty($formData['header_video_url'])) {
                    $messageComponents[] = ['type' => 'header', 'parameters' => [['type' => 'video', 'video' => ['link' => $formData['header_video_url']]]]];
                } elseif ($format === 'DOCUMENT' && ! empty($formData['header_document_url'])) {
                    $doc = ['link' => $formData['header_document_url']];
                    if (! empty($formData['header_document_filename'])) {
                        $doc['filename'] = $formData['header_document_filename'];
                    }
                    $messageComponents[] = ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => $doc]]];
                }
            } elseif ($type === 'BODY' && isset($component['text'])) {
                if ($indices = $scanIndices($component['text'])) {
                    $messageComponents[] = ['type' => 'body', 'parameters' => $buildParams($indices, $valueMap)];
                }
            } elseif ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $btnIndex => $button) {
                    if (strtoupper($button['type'] ?? '') === 'URL' && isset($button['url'])) {
                        if ($indices = $scanIndices($button['url'])) {
                            $messageComponents[] = ['type' => 'button', 'sub_type' => 'url', 'index' => (string) $btnIndex, 'parameters' => $buildParams($indices, $valueMap)];
                        }
                    }
                }
            }
        }

        return $messageComponents;
    }

    protected function renderPreview(Get $get): HtmlString
    {
        $templateKey = $get('template');
        if (! $templateKey || ! isset($this->templateDefinitions[$templateKey])) {
            return new HtmlString('<strong><small>Select a template to see a preview.</small></strong>');
        }
        $template = $this->templateDefinitions[$templateKey];
        $components = $template['components'] ?? [];

        $valueMap = [];
        foreach ($get('variables') ?? [] as $row) {
            $idx = (int) ($row['index'] ?? 0);
            $val = trim((string) ($row['value'] ?? ''));
            if ($idx > 0) {
                $valueMap[$idx] = $val;
            }
        }

        $replacer = function ($text) use ($valueMap) {
            $text = htmlspecialchars($text);
            foreach ($valueMap as $index => $value) {
                $placeholder = '{{'.$index.'}}';
                $replacement = $value !== ''
                    ? '<strong style="color: #007bff;">'.htmlspecialchars($value).'</strong>'
                    : '<span style="color: #fd7e14; background: #fff8e1; padding: 1px 4px; border-radius: 3px;">'.$placeholder.'</span>';
                $text = str_replace(htmlspecialchars($placeholder), $replacement, $text);
            }
            $text = preg_replace('/\\*(.*?)\\*/', '<strong>$1</strong>', $text);
            $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
            $text = preg_replace('/~(.*?)~/', '<del>$1</del>', $text);

            return nl2br($text);
        };

        $html = '<div style="background-color: #f0f0f0; border-radius: 8px; padding: 8px; max-width: 450px;">';
        $html .= '<div style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">';
        $headerHtml = '';
        $bodyHtml = '';
        $footerHtml = '';
        $buttonsHtml = '';

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');
            $format = strtoupper($component['format'] ?? '');
            if ($type === 'HEADER') {
                if ($format === 'IMAGE') {
                    $url = $get('header_image_url');
                    $headerHtml = $url ? '<img src="'.htmlspecialchars($url).'" alt="Preview" style="width: 100%; border-top-left-radius: 8px; border-top-right-radius: 8px; max-height: 200px; object-fit: cover;">' : '<div style="background: #e9ecef; padding: 20px; text-align: center; color: #6c757d; border-top-left-radius: 8px; border-top-right-radius: 8px;">[Image Header: Add URL above]</div>';
                } elseif ($format === 'VIDEO') {
                    $headerHtml = '<div style="background: #e9ecef; padding: 20px; text-align: center; color: #6c757d; border-top-left-radius: 8px; border-top-right-radius: 8px;">[Video Header: Add URL above]</div>';
                } elseif ($format === 'DOCUMENT') {
                    $headerHtml = '<div style="background: #e9ecef; padding: 20px; text-align: center; color: #6c757d; border-top-left-radius: 8px; border-top-right-radius: 8px;">[Document Header: Add URL above]</div>';
                } elseif ($format === 'TEXT' && isset($component['text'])) {
                    $headerHtml = '<div style="padding: 12px 16px; font-size: 1.1em; font-weight: bold; border-bottom: 1px solid #f0f0f0;">'.$replacer($component['text']).'</div>';
                }
            } elseif ($type === 'BODY' && isset($component['text'])) {
                $bodyHtml = '<div style="padding: 12px 16px; font-size: 0.95em; word-wrap: break-word;">'.$replacer($component['text']).'</div>';
            } elseif ($type === 'FOOTER' && isset($component['text'])) {
                $footerHtml = '<div style="padding: 8px 16px 12px; font-size: 0.85em; color: #6c757d;">'.$replacer($component['text']).'</div>';
            } elseif ($type === 'BUTTONS') {
                $buttonsHtml = '<div style="border-top: 1px solid #f0f0f0; text-align: center;">';
                foreach ($component['buttons'] ?? [] as $button) {
                    $btnText = $button['text'] ?? 'Button';
                    $btnType = $button['type'] ?? '';
                    $icon = $btnType === 'URL' ? '🔗 ' : ($btnType === 'QUICK_REPLY' ? '💬 ' : '');
                    $buttonsHtml .= '<div style="padding: 12px 16px; color: #007bff; font-weight: 500;">'.$icon.htmlspecialchars($btnText).'</div>';
                    if ($btnType === 'URL' && isset($button['url'])) {
                        $buttonsHtml .= '<div style="padding: 0 16px 12px; font-size: 0.8em; color: #6c757d; word-break: break-all;">'.$replacer($button['url']).'</div>';
                    }
                }
                $buttonsHtml .= '</div>';
            }
        }
        $html .= $headerHtml.$bodyHtml.$footerHtml.'</div>'.$buttonsHtml;
        $html .= '</div>';

        return new HtmlString($html);
    }
}
