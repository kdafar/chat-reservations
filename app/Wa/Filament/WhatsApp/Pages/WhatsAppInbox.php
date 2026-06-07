<?php

namespace App\Wa\Filament\WhatsApp\Pages;

use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Jobs\SendWhatsAppTemplateJob;
use App\Wa\Models\WhatsApp\WaContact;
use App\Wa\Models\WhatsApp\WaConversation;
use App\Wa\Models\WhatsApp\WaMessage;
use App\Wa\Models\WhatsApp\WaNumber;
use App\Wa\Services\GlobalPointService;
use App\Wa\Services\WhatsApp\Tenant\TenantWhatsAppService;
use App\Wa\Services\WhatsApp\WhatsAppTemplateService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

class WhatsAppInbox extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?string $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.whatsapp.pages.whatsapp-inbox';

    // UI state
    public ?int $activeConversationId = null;

    public string $statusFilter = 'all';

    public ?string $search = null;

    public string $replyText = '';

    // Template sending state
    public bool $showTemplateSelector = false;

    public string $templateSearch = '';

    public ?string $selectedTemplateKey = null;

    public ?array $templateData = [];

    public array $templateOptions = [];

    public array $templateDefinitions = [];

    // New chat form state
    public ?string $newChatPhone = null;

    public ?string $newChatName = null;

    // Attachment state
    public bool $showAttachmentMenu = false;

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $first = $this->queryConversations()->first();
        if ($first) {
            $this->activeConversationId = $first->id;
        }

        $this->loadTemplateOptions();
        $this->templateForm->fill();
    }

    protected function queryConversations()
    {
        $query = WaConversation::with(['contact', 'number', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply search filter
        if ($this->search) {
            $query->whereHas('contact', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('phone', 'like', '%'.$this->search.'%')
                    ->orWhere('wa_id', 'like', '%'.$this->search.'%');
            });
        }

        return $query;
    }

    public function getConversationsProperty()
    {
        return $this->queryConversations()
            ->limit(50)
            ->get();
    }

    public function getActiveConversationProperty(): ?WaConversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        return WaConversation::with([
            'contact',
            'number',
            'messages' => fn ($q) => $q
                ->orderBy('sent_at')
                ->orderBy('id'),
        ])->find($this->activeConversationId);
    }

    public function selectConversation(int $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->replyText = '';
        $this->cancelTemplate();
        $this->showAttachmentMenu = false;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->activeConversationId = optional($this->queryConversations()->first())->id;
        $this->cancelTemplate();
    }

    public function sendReply(): void
    {
        if (! $this->activeConversationId) {
            return;
        }

        $text = trim($this->replyText);
        if ($text === '') {
            return;
        }

        // --- 1. BALANCE CHECK ---
        $pointService = app(GlobalPointService::class);
        $replyCost = 1; // Assuming 1 point per reply, change to 0 if replies are free

        if (! $pointService->hasSystemBalance($replyCost)) {
            Notification::make()
                ->title('Insufficient system balance')
                ->body('You must have points in the system to send messages.')
                ->danger()
                ->send();

            return;
        }

        $conversation = WaConversation::with(['contact', 'number', 'account'])
            ->find($this->activeConversationId);

        if (! $conversation || ! $conversation->contact || ! $conversation->number) {
            Notification::make()
                ->title('Conversation not found')
                ->danger()
                ->send();

            return;
        }

        $toRaw = $conversation->contact->phone ?: $conversation->contact->wa_id;
        $to = preg_replace('/\D+/', '', (string) $toRaw);

        if (! $to) {
            Notification::make()
                ->title('No valid phone number for this contact')
                ->danger()
                ->send();

            return;
        }

        try {
            // Send via Service
            $svc = app(TenantWhatsAppService::class)->forNumber($conversation->number);
            $svc->sendTextMessage($to, $text, true);

            // Record Message in DB
            WaMessage::create([
                'wa_account_id' => $conversation->wa_account_id,
                'wa_number_id' => $conversation->wa_number_id,
                'conversation_id' => $conversation->id,
                'direction' => 'out',
                'type' => 'text',
                'body' => $text,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $conversation->update([
                'status' => 'open',
                'last_message_at' => now(),
                'last_outgoing_at' => now(),
            ]);

            // --- 2. DEDUCT POINTS (Usage Tracking) ---
            if ($replyCost > 0) {
                $pointService->deductSystemPoints(
                    auth()->id(),
                    $replyCost,
                    'inbox_reply',
                    [
                        'recipient' => $to,
                        'conversation_id' => $conversation->id,
                    ]
                );
            }

            $this->replyText = '';

            Notification::make()
                ->title('Message sent')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to send message')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resolveConversation(): void
    {
        if (! $this->activeConversationId) {
            return;
        }

        $conv = WaConversation::find($this->activeConversationId);
        if (! $conv) {
            return;
        }

        $conv->update(['status' => 'resolved']);

        Notification::make()
            ->title('Conversation resolved')
            ->success()
            ->send();

        $this->activeConversationId = optional($this->queryConversations()->first())->id;
        $this->cancelTemplate();
    }

    public function reopenConversation(): void
    {
        if (! $this->activeConversationId) {
            return;
        }

        $conv = WaConversation::find($this->activeConversationId);
        if (! $conv) {
            return;
        }

        $conv->update(['status' => 'open']);

        Notification::make()
            ->title('Conversation reopened')
            ->success()
            ->send();
    }

    protected function getForms(): array
    {
        return [
            'templateForm' => $this->makeForm()
                ->schema(fn (): array => $this->buildTemplateFormSchema())
                ->statePath('templateData'),
        ];
    }

    public function toggleTemplateSelector(): void
    {
        if ($this->showTemplateSelector) {
            $this->cancelTemplate();
        } else {
            $this->showTemplateSelector = true;
            $this->showAttachmentMenu = false;
            $this->templateSearch = '';
            $this->selectedTemplateKey = null;
            $this->templateData = [];
            $this->templateForm->fill();
        }
    }

    public function cancelTemplate(): void
    {
        $this->showTemplateSelector = false;
        $this->templateSearch = '';
        $this->selectedTemplateKey = null;
        $this->templateData = [];
    }

    public function loadTemplateOptions(): void
    {
        $number = WaNumber::where('status', 'connected')->first();
        if (! $number) {
            return;
        }

        $this->templateDefinitions = [];
        $this->templateOptions = [];

        try {
            $tplSvc = app(WhatsAppTemplateService::class);
            $result = $tplSvc->loadTemplatesForNumber($number);

            $this->templateDefinitions = $result['definitions'];
            $this->templateOptions = array_map(
                fn ($opt) => $opt['raw'],
                $result['options']
            );
        } catch (\Throwable $e) {
            \Log::error('[WhatsAppInbox] Failed to fetch templates', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getTemplatesProperty()
    {
        if (empty($this->templateSearch)) {
            return $this->templateOptions;
        }

        return array_filter($this->templateOptions, function ($tpl) {
            $search = strtolower($this->templateSearch);
            $name = strtolower($tpl['name'] ?? '');
            $body = strtolower(json_encode($tpl['components'] ?? []));

            return str_contains($name, $search) || str_contains($body, $search);
        });
    }

    public function selectTemplate(string $key): void
    {
        if (! $key) {
            $this->selectedTemplateKey = null;
            $this->templateData = [];
            $this->templateForm->fill([]);

            return;
        }

        $this->selectedTemplateKey = $key;
        $template = $this->templateDefinitions[$key] ?? null;
        if (! $template) {
            return;
        }

        $variables = $this->buildVariableDefaultsForTemplate($template);

        $this->templateForm->fill([
            'variables' => $variables,
        ]);
    }

    public function buildTemplateFormSchema(): array
    {
        if (! $this->selectedTemplateKey) {
            return [];
        }

        $template = $this->templateDefinitions[$this->selectedTemplateKey] ?? null;
        if (! $template) {
            return [];
        }

        $schema = [];
        $header = $this->findComponent($template['components'] ?? [], 'HEADER');
        $headerFormat = $header['format'] ?? null;

        if ($headerFormat === 'IMAGE') {
            $schema[] = Forms\Components\FileUpload::make('header_image_url')
                ->label('Header Image')
                ->image()
                ->maxSize(5120)
                ->acceptedFileTypes(['image/*'])
                ->helperText('Upload an image for the template header');
        }

        if ($headerFormat === 'VIDEO') {
            $schema[] = Forms\Components\FileUpload::make('header_video_url')
                ->label('Header Video')
                ->acceptedFileTypes(['video/*'])
                ->maxSize(16384)
                ->helperText('Upload a video for the template header');
        }

        if ($headerFormat === 'DOCUMENT') {
            $schema[] = Forms\Components\FileUpload::make('header_document_url')
                ->label('Header Document')
                ->acceptedFileTypes(['application/pdf', '.doc', '.docx', '.xls', '.xlsx'])
                ->maxSize(10240)
                ->helperText('Upload a document for the template header');

            $schema[] = Forms\Components\TextInput::make('header_document_filename')
                ->label('Document Filename (Optional)')
                ->helperText('Custom filename for the document');
        }

        $schema[] = Forms\Components\Repeater::make('variables')
            ->label('Template Variables')
            ->schema([
                Forms\Components\TextInput::make('index')
                    ->label('#')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpan(1),
                Forms\Components\Textarea::make('value')
                    ->label('Value')
                    ->rows(2)
                    ->required()
                    ->columnSpan(5),
                Forms\Components\Placeholder::make('hint')
                    ->label('Used in')
                    ->content(fn ($get) => $get('hint'))
                    ->columnSpan(6),
            ])
            ->columns(12)
            ->default([])
            ->columnSpanFull()
            ->addable(false)
            ->deletable(false)
            ->reorderable(false);

        return $schema;
    }

    public function sendTemplateMessage(): void
    {
        if (! $this->activeConversationId || ! $this->selectedTemplateKey) {
            return;
        }

        $template = $this->templateDefinitions[$this->selectedTemplateKey] ?? null;

        if (! $template) {
            Notification::make()
                ->title('Template error')
                ->body('Selected template definition not found.')
                ->danger()
                ->send();

            return;
        }

        // --- 1. DETERMINE COST ---
        // Try to fetch specific cost from DB, fallback to 1
        $localTemplate = MessageTemplate::where('name', $template['name'])->first();
        $cost = $localTemplate ? (int) $localTemplate->points_cost : 1;
        $cost = max(1, $cost); // Ensure at least 1 point for manual sends

        // --- 2. BALANCE CHECK ---
        $pointService = app(GlobalPointService::class);

        if (! $pointService->hasSystemBalance($cost)) {
            Notification::make()
                ->title('Insufficient Points')
                ->body("This template requires {$cost} points. Please top up.")
                ->danger()
                ->send();

            return;
        }

        $conversation = WaConversation::with(['contact', 'number', 'account'])
            ->find($this->activeConversationId);

        $formData = $this->templateForm->getState();

        if (! $conversation || ! $conversation->contact || ! $conversation->number) {
            Notification::make()
                ->title('Missing data')
                ->body('Conversation or contact not found.')
                ->danger()
                ->send();

            return;
        }

        // --- 3. DEDUCT POINTS ---
        $pointService->deductSystemPoints(
            auth()->id(),
            $cost,
            'manual_template_send',
            [
                'template' => $template['name'],
                'recipient' => $conversation->contact->phone ?? $conversation->contact->wa_id,
                'conversation_id' => $conversation->id,
            ]
        );

        $tplSvc = app(WhatsAppTemplateService::class);
        $templatePayload = $tplSvc->buildTemplatePayload($template, $formData);

        $waMessage = WaMessage::create([
            'wa_account_id' => $conversation->wa_account_id,
            'wa_number_id' => $conversation->wa_number_id,
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'type' => 'template',
            'body' => 'Template: '.$template['name'],
            'meta_raw' => ['template_payload' => $templatePayload],
            'status' => 'pending',
            'sent_at' => null,
        ]);

        $conversation->update([
            'status' => 'open',
            'last_message_at' => now(),
            'last_outgoing_at' => now(),
        ]);

        SendWhatsAppTemplateJob::dispatch($waMessage->id);

        $this->cancelTemplate();

        Notification::make()
            ->title('Template queued for sending')
            ->success()
            ->send();
    }

    public function createNewChat(): void
    {
        $data = $this->validate([
            'newChatPhone' => ['required', 'string'],
            'newChatName' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = preg_replace('/\D+/', '', (string) $data['newChatPhone']);

        if (! $phone) {
            Notification::make()
                ->title('Invalid phone number')
                ->body('Please enter a valid phone with country code (e.g. 9655xxxxxxx).')
                ->danger()
                ->send();

            return;
        }

        $number = WaNumber::where('status', 'connected')->first();
        if (! $number) {
            Notification::make()
                ->title('No connected WhatsApp number')
                ->body('Connect at least one WhatsApp number before starting a chat.')
                ->danger()
                ->send();

            return;
        }

        $contact = WaContact::firstOrCreate(
            ['wa_id' => $phone],
            [
                'phone' => $phone,
                'name' => $data['newChatName'] ?: $phone,
            ],
        );

        $conversation = WaConversation::firstOrCreate(
            [
                'wa_number_id' => $number->id,
                'contact_id' => $contact->id,
            ],
            [
                'wa_account_id' => $number->wa_account_id,
                'status' => 'open',
                'last_message_at' => now(),
            ],
        );

        $this->activeConversationId = $conversation->id;
        $this->replyText = '';
        $this->newChatPhone = null;
        $this->newChatName = null;

        $this->dispatch('close-modal', id: 'new-chat-modal');

        Notification::make()
            ->title('Conversation created')
            ->success()
            ->send();
    }

    protected function findComponent(?array $components, string $type): ?array
    {
        if (empty($components)) {
            return null;
        }

        foreach ($components as $component) {
            if (strtoupper($component['type'] ?? '') === $type) {
                return $component;
            }
        }

        return null;
    }

    protected function buildVariableDefaultsForTemplate(?array $template): array
    {
        if (empty($template)) {
            return [];
        }

        $tplSvc = app(WhatsAppTemplateService::class);

        return $tplSvc->buildVariableDefaults($template);
    }

    public function getTemplatePreview(): ?array
    {
        if (! $this->selectedTemplateKey) {
            return null;
        }

        $template = $this->templateDefinitions[$this->selectedTemplateKey] ?? null;
        if (! $template) {
            return null;
        }

        return $template;
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
