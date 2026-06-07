<?php

namespace App\Wa\Filament\Resources;

use App\Wa\Filament\Resources\BulkSenderCampaignResource\Pages;
use App\Wa\Filament\Resources\BulkSenderCampaignResource\RelationManagers\RecipientsRelationManager;
use App\Wa\Filament\Resources\BulkSenderCampaignResource\Widgets\BulkSenderStatsOverview;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Jobs\SendPromotionalCampaignMessage;
use App\Wa\Services\WhatsApp\WhatsAppService;
use App\Wa\Services\WhatsAppTemplateCatalog;
use App\Wa\Support\Phone;
use App\Wa\Support\Curator\CuratorPicker;
use App\Wa\Support\Curator\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class BulkSenderCampaignResource extends Resource
{
    protected static ?string $model = PromotionalCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    public static function getNavigationGroup(): string
    {
        return __('Promotions');
    }

    public static function getNavigationLabel(): string
    {
        return __('Bulk WhatsApp Sender');
    }

    public static function getModelLabel(): string
    {
        return __('Bulk WhatsApp Campaign');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bulk WhatsApp Campaigns');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'recipients',

                'recipients as pending_count' => function (Builder $query) {
                    $query->where('status', 'pending');
                },
                'recipients as sent_count' => function (Builder $query) {
                    $query->where('status', 'sent');
                },
                'recipients as delivered_count' => function (Builder $query) {
                    $query->where('status', 'delivered');
                },
                'recipients as read_count' => function (Builder $query) {
                    $query->where('status', 'read');
                },
                'recipients as failed_count' => function (Builder $query) {
                    $query->where('status', 'failed');
                },
                'recipients as limited_count' => function (Builder $query) {
                    $query->where('status', 'limited');
                },
                'recipients as undeliverable_count' => function (Builder $query) {
                    $query->where('status', 'undeliverable');
                },
                'recipients as experiment_blocked_count' => function (Builder $query) {
                    $query->where('status', 'experiment_blocked');
                },
            ]);
    }

    /**
     * Lock campaign edits once it has ANY recipients or started sending,
     * OR status is sending/completed.
     */
    public static function isLocked(?PromotionalCampaign $record): bool
    {
        if (! $record) {
            return false;
        }

        if (in_array($record->status, ['sending', 'completed', 'paused'], true)) {
            return true;
        }

        if (! empty($record->sent_at)) {
            return true;
        }

        // if recipients exist (imported / queued / sent), lock
        try {
            return $record->recipients()->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // SECTION 1: LIVE PREVIEW
            Forms\Components\Section::make('Preview')
                ->schema([
                    Forms\Components\Placeholder::make('preview')
                        ->content(function (Get $get) {
                            $mediaState = $get('header_media_id');
                            $resolvedMediaUrl = null;

                            $wa = app(WhatsAppService::class);
                            $health = $wa->getCurrentNumberHealth();

                            $businessName = (string) ($health['verified_name'] ?? $health['name'] ?? '');
                            $phoneLabel = (string) ($health['display_phone_number'] ?? '');

                            $mediaId = static::extractCuratorMediaId($mediaState);

                            if ($mediaId) {
                                $mediaItem = Media::query()->whereKey($mediaId)->first();

                                if ($mediaItem) {
                                    $disk = $mediaItem->disk ?: config('curator.disk', 'public');

                                    $path = ltrim((string) $mediaItem->path, '/');

                                    if (Storage::disk($disk)->exists($path)) {
                                        $resolvedMediaUrl = Storage::disk($disk)->url($path);
                                    }
                                }
                            }

                            // fallback to legacy path (header_image_path)
                            if (! $resolvedMediaUrl) {
                                $legacyPath = $get('header_image_path');
                                if ($legacyPath) {
                                    $legacyPath = ltrim((string) $legacyPath, '/');

                                    $disk = config('curator.disk', 'public');
                                    if (Storage::disk($disk)->exists($legacyPath)) {
                                        $resolvedMediaUrl = Storage::disk($disk)->url($legacyPath);
                                    } elseif (Storage::disk('public')->exists($legacyPath)) {
                                        $resolvedMediaUrl = Storage::disk('public')->url($legacyPath);
                                    }
                                }
                            }

                            return new HtmlString(
                                view('filament.campaigns.preview', [
                                    'get' => $get,
                                    'resolvedMediaUrl' => $resolvedMediaUrl,
                                    'wa_business_name' => $businessName,
                                    'wa_phone_label' => $phoneLabel,
                                ])->render()
                            );
                        })
                        ->columnSpan('full'),
                ]),

            // SECTION 2: BASICS
            Forms\Components\Section::make(__('Campaign Basics'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Campaign Name'))
                        ->required()
                        ->maxLength(160)
                        ->live(onBlur: true)
                        ->disabled(fn (?PromotionalCampaign $record) => self::isLocked($record)),

                    Forms\Components\Select::make('template_name')
                        ->label(__('Meta Template'))
                        ->options(fn () => app(WhatsAppTemplateCatalog::class)->options())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(debounce: 300)
                        ->disabled(fn (?PromotionalCampaign $record) => self::isLocked($record))

                        // hydrate existing record (edit/view)
                        ->afterStateHydrated(function (Get $get, Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }

                            $details = $get('template_details');
                            if (! $details) {
                                $details = app(WhatsAppTemplateCatalog::class)->find($state) ?? [];
                                $set('template_details', $details);
                            }

                            $tplLang = (string) (data_get($details, 'language', 'en'));
                            $set('default_locale', $tplLang);

                            // ensure variable keys exist based on BODY text
                            $vars = (array) ($get('template_variables') ?? []);
                            $body = collect(data_get($details, 'components', []))->firstWhere('type', 'BODY');
                            $text = (string) data_get($body, 'text', '');

                            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
                            $indexes = collect($m[1] ?? [])
                                ->map(fn ($i) => (string) ((int) $i))
                                ->unique()
                                ->sort()
                                ->values();

                            $needSet = false;
                            foreach ($indexes as $i) {
                                if (! array_key_exists($i, $vars)) {
                                    $vars[$i] = '';
                                    $needSet = true;
                                }
                            }
                            if ($needSet) {
                                $set('template_variables', $vars);
                            }
                        })

                        // 🔥 MAIN: prefill variables + header media from DB template row
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?PromotionalCampaign $record) {

                            if (self::isLocked($record)) {
                                return;
                            }

                            // Reset template-related state
                            $set('template_details', null);
                            $set('template_variables', []);
                            $set('header_variable', null); // Reset header variable
                            $set('default_locale', null);

                            // Reset header state
                            $set('header_media_id', null);
                            $set('header_image_path', null);

                            if (! $state) {
                                return;
                            }

                            // 1) Meta details
                            $details = app(WhatsAppTemplateCatalog::class)->find($state) ?? [];
                            if (empty($details)) {
                                return;
                            }

                            $set('template_details', $details);

                            $tplLang = (string) (data_get($details, 'language', 'en'));
                            $set('default_locale', $tplLang);

                            // 2) Prefill template variables from META example (if any)
                            $defaults = self::extractBodyVarDefaults((array) ($details['components'] ?? []));
                            $vars = [];
                            foreach ($defaults as $k => $v) {
                                $vars[$k] = $v; // can be empty when no example
                            }
                            $set('template_variables', $vars);

                            // 3) Prefill header media from DB message_templates row
                            $dbTpl = MessageTemplate::query()->where('name', $state)->first();

                            if ($dbTpl) {
                                // Prefer campaign_media_url then header_sample_path
                                $prefillPath = $dbTpl->campaign_media_url ?: $dbTpl->header_sample_path;

                                if ($prefillPath) {
                                    $mediaId = self::findCuratorMediaIdFromPath((string) $prefillPath);

                                    if ($mediaId) {
                                        $media = Media::query()->whereKey($mediaId)->first();

                                        if ($media) {
                                            // enforce matching format (image/video/doc)
                                            self::assertMediaMatchesHeaderFormat(self::getHeaderFormat($get), $media);

                                            //  LOGGING
                                            Log::info('Prefilling header_media_id from DB template', ['mediaId' => $mediaId]);

                                            //  FIX 3.0: Set FULL media object array. View expects {id, type, ...}
                                            $set('header_media_id', [$media->toArray()]);
                                            $set('header_image_path', ltrim((string) $media->path, '/'));
                                        }
                                    } else {
                                        // fallback to path only (preview can still render)
                                        $normalized = self::normalizeToStoragePath((string) $prefillPath);
                                        $set('header_image_path', $normalized);
                                    }
                                }
                            }
                        }),

                    Forms\Components\Placeholder::make('template_language_display')
                        ->label(__('Template Language'))
                        ->content(fn (Get $get) => (string) ($get('template_details.language') ?? '—')),

                    Forms\Components\Hidden::make('default_locale'),
                    Forms\Components\Hidden::make('template_details'),
                ]),

            // SECTION 3: HEADER MEDIA
            Forms\Components\Section::make(__('Header Media'))
                ->visible(fn (Get $get): bool => in_array(self::getHeaderFormat($get), ['IMAGE', 'VIDEO', 'DOCUMENT'], true))
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('header_info')
                        ->content(function (Get $get) {
                            $format = self::getHeaderFormat($get);

                            return __('This template requires a :type header.', [
                                'type' => strtolower($format ?: 'media'),
                            ]);
                        })
                        ->columnSpanFull(),

                    CuratorPicker::make('header_media_id')
                        ->label(__('Header Media'))
                        ->buttonLabel(__('Select from Library'))
                        ->color('primary')
                        ->disk(config('curator.disk', 'public'))
                        ->directory('whatsapp/campaigns/headers')
                        ->visibility('public')
                        //  FIX: Keep dehydrated false.
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Set $set, Get $get, $state) {
                            //  LOGGING
                            Log::info('CuratorPicker: Hydrating', ['state' => $state, 'type' => gettype($state)]);

                            // Helper to fetch full media object for the View
                            $hydrateMedia = function ($id) {
                                $m = Media::query()->whereKey($id)->first();

                                return $m ? [$m->toArray()] : [];
                            };

                            // 1. If state looks like a full media object array (has type), leave it.
                            if (is_array($state) && isset($state[0]['type'])) {
                                return;
                            }

                            // 2. If state is numeric ID
                            if (is_numeric($state)) {
                                Log::info('CuratorPicker: Converting int to full media array', ['id' => $state]);
                                $set('header_media_id', $hydrateMedia($state));

                                return;
                            }

                            // 3. If blank, try to recover ID from path
                            if (blank($state)) {
                                $path = $get('header_image_path');
                                if ($path) {
                                    $mediaId = self::findCuratorMediaIdFromPath($path);
                                    if ($mediaId) {
                                        Log::info('CuratorPicker: Recovered ID from path', ['mediaId' => $mediaId]);
                                        $set('header_media_id', $hydrateMedia($mediaId));
                                    }
                                }
                            }
                        })
                        ->acceptedFileTypes(fn (Get $get) => self::acceptedMimeTypesForHeaderFormat(self::getHeaderFormat($get)))
                        ->multiple(false)
                        ->required(fn (Get $get) => self::headerIsRequired($get))
                        ->live()
                        ->disabled(fn (?PromotionalCampaign $record) => self::isLocked($record))
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            //  LOGGING
                            Log::info('CuratorPicker: Updated', ['state' => $state]);

                            if (blank($state)) {
                                $set('header_image_path', null);

                                return;
                            }

                            $media = self::resolveMediaFromState($state);

                            if (! $media) {
                                $set('header_image_path', null);

                                return;
                            }

                            self::assertMediaMatchesHeaderFormat(self::getHeaderFormat($get), $media);

                            $set('header_image_path', ltrim((string) $media->path, '/'));
                        })
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('header_image_path'),

                    Forms\Components\Placeholder::make('header_preview')
                        ->label(__('File Status'))
                        ->content(function (Get $get) {
                            $mediaId = self::extractCuratorMediaId($get('header_media_id'));

                            if (! $mediaId) {
                                if ($get('header_image_path')) {
                                    return new HtmlString('<span class="text-success-600 text-sm font-medium">✓ Header path prefilled</span>');
                                }

                                return new HtmlString('<span class="text-gray-400 text-sm italic">No file selected</span>');
                            }

                            return new HtmlString('<span class="text-success-600 text-sm font-medium">✓ File selected</span>');

                        })
                        ->columnSpanFull(),
                ]),

            // SECTION 4: TEMPLATE VARIABLES (Includes Header Text Variable)
            Forms\Components\Section::make(__('Template Variables'))
                ->description(__('Provide values for placeholders required by the selected template.'))
                ->visible(function (Get $get): bool {
                    $details = $get('template_details');
                    if (! $details) {
                        return false;
                    }

                    // Check Body variables
                    $components = $details['components'] ?? [];
                    $body = collect($components)->firstWhere('type', 'BODY');
                    $bodyText = (string) data_get($body, 'text', '');
                    $hasBodyVars = $bodyText !== '' && str_contains($bodyText, '{{');

                    // Check Header variables
                    $header = collect($components)->firstWhere('type', 'HEADER');
                    $headerFormat = (string) data_get($header, 'format', '');
                    $headerText = (string) data_get($header, 'text', '');
                    $hasHeaderVar = $headerFormat === 'TEXT' && str_contains($headerText, '{{');

                    return $hasBodyVars || $hasHeaderVar;
                })
                ->schema(function (Get $get): array {
                    $components = $get('template_details.components') ?? [];
                    $fields = [];

                    // 1. Header Variable Logic
                    $header = collect($components)->firstWhere('type', 'HEADER');
                    if ($header && data_get($header, 'format') === 'TEXT') {
                        $headerText = (string) data_get($header, 'text', '');
                        if (str_contains($headerText, '{{')) {
                            // Text headers usually have {{1}}
                            $fields[] = Forms\Components\TextInput::make('header_variable')
                                ->label(__('Header Variable ({{1}})'))
                                ->placeholder(__('Enter value for header variable'))
                                ->helperText("Template Header: \"$headerText\"")
                                ->required()
                                ->live(debounce: 500)
                                ->disabled(fn (?PromotionalCampaign $record) => self::isLocked($record));
                        }
                    }

                    // 2. Body Variables Logic
                    $body = collect($components)->firstWhere('type', 'BODY');
                    $text = (string) data_get($body, 'text', '');

                    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
                    $numbers = collect($m[1] ?? [])
                        ->map(fn ($i) => (int) $i)
                        ->unique()
                        ->sort()
                        ->values();

                    if ($numbers->isNotEmpty()) {
                        if (! empty($fields)) {
                            // Add separator if we have both header and body vars
                            $fields[] = Forms\Components\Section::make('Body Variables')
                                ->heading('Body Variables')
                                ->compact()
                                ->schema([]);
                        }

                        foreach ($numbers as $n) {
                            $fields[] = Forms\Components\TextInput::make("template_variables.{$n}")
                                ->label("Body Variable {{$n}}")
                                ->required()
                                ->helperText("Replaces {{{{$n}}}} in the template body.")
                                ->live(debounce: 500)
                                ->disabled(fn (?PromotionalCampaign $record) => self::isLocked($record));
                        }
                    }

                    if (empty($fields)) {
                        return [
                            Forms\Components\Placeholder::make('no_vars')
                                ->content(__('This template does not contain any editable variables.')),
                        ];
                    }

                    return $fields;
                }),

            // SECTION 5: SCHEDULE & THROTTLE
            Forms\Components\Section::make(__('Schedule & Throttle'))
                ->columns(3)
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label(__('Schedule At'))
                        ->seconds(false)
                        ->timezone(config('app.timezone'))
                        ->disabled(fn (?PromotionalCampaign $record) => self::isLocked($record)),

                    Forms\Components\TextInput::make('send_rate_per_min')
                        ->label(__('Max Sends / Minute'))
                        ->numeric()
                        ->default(600)
                        ->minValue(60)
                        ->disabled(fn (?PromotionalCampaign $record) => self::isLocked($record)),

                    Forms\Components\TextInput::make('status')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText(__('Status will update automatically.'))
                        ->default('draft'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Campaign Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('template_name')
                    ->label(__('Template'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('default_locale')
                    ->label(__('Locale'))
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'primary' => 'draft',
                        'info' => 'scheduled',
                        'warning' => 'sending',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'paused',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('status_breakdown')
                    ->label(__('Delivery Breakdown'))
                    ->html()
                    ->state(fn (PromotionalCampaign $record) => self::getStatusBreakdownForRow($record)),

                Tables\Columns\TextColumn::make('recipients_count')
                    ->label(__('Total'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->since(),
            ])
            ->actions([
                // NEW: Full Page Analytics
                Tables\Actions\Action::make('analytics')
                    ->label(__('Analytics'))
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->url(fn (PromotionalCampaign $record) => Pages\CampaignAnalytics::getUrl(['record' => $record])),

                Tables\Actions\Action::make('sendTest')
                    ->label(__('Send Test'))
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\TextInput::make('test_msisdn')
                            ->label(__('Test phone (E.164 or local)'))
                            ->helperText(__('Accepts GCC + Egypt. Local numbers are parsed with Kuwait rules first.'))
                            ->required(),
                        Forms\Components\Select::make('preferred_region')
                            ->label(__('Preferred region for local numbers'))
                            ->options([
                                'KW' => 'Kuwait',
                                'SA' => 'Saudi Arabia',
                                'AE' => 'United Arab Emirates',
                                'QA' => 'Qatar',
                                'BH' => 'Bahrain',
                                'OM' => 'Oman',
                                'EG' => 'Egypt',
                            ])
                            ->default('KW')
                            ->native(false),
                    ])
                    ->action(function (PromotionalCampaign $record, array $data) {
                        $e164 = Phone::parseToE164AcrossRegions(
                            (string) $data['test_msisdn'],
                            ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'],
                            (string) ($data['preferred_region'] ?? 'KW'),
                            true
                        );

                        if (! $e164) {
                            Notification::make()
                                ->title(__('Invalid phone'))
                                ->body(__('Please enter a valid mobile number from Kuwait, Saudi, UAE, Qatar, Bahrain, Oman, or Egypt.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $recipient = PromotionalCampaignRecipient::firstOrNew(
                            ['promotional_campaign_id' => $record->id, 'msisdn' => $e164],
                            ['status' => 'pending']
                        );

                        $recipient->status = 'pending';
                        $recipient->error_message = null;
                        $recipient->wa_message_id = null;
                        $recipient->save();

                        //  Send with bypass=true for tests
                        dispatch(new SendPromotionalCampaignMessage($record->id, $recipient->id, true));

                        Notification::make()
                            ->title(__('Test Sent'))
                            ->body(__('A test message has been queued for sending to ').$e164)
                            ->success()
                            ->send();
                    }),

                //  NEW: PAUSE ACTION
                Tables\Actions\Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (PromotionalCampaign $record) => $record->status === 'sending')
                    ->action(function (PromotionalCampaign $record) {
                        $record->update(['status' => 'paused']);
                        Notification::make()->title('Campaign Paused')->warning()->send();
                    }),

                //  NEW: RESUME ACTION
                Tables\Actions\Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PromotionalCampaign $record) => $record->status === 'paused')
                    ->action(function (PromotionalCampaign $record) {
                        $record->update(['status' => 'sending']);
                        Notification::make()->title('Campaign Resumed')->success()->send();
                    }),

                Tables\Actions\Action::make('validateAndQueue')
                    ->label(__('Validate & Queue'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('Queue All Recipients'))
                    ->modalDescription(__('Are you sure you want to queue all pending or failed recipients for this campaign?'))
                    ->visible(fn (PromotionalCampaign $record) => in_array($record->status, ['draft', 'failed', 'sending']))
                    ->action(function (PromotionalCampaign $record) {

                        if (! $record->template_name || ! $record->template_details) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('A valid template must be selected.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        if (in_array($record->status, ['completed'], true)) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('Campaign is already sending or completed.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $components = $record->template_details['components'] ?? [];
                        $header = collect($components)->firstWhere('type', 'HEADER');

                        if ($header) {
                            $format = strtoupper((string) ($header['format'] ?? ''));

                            if (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && ! $record->header_image_path) {
                                Notification::make()
                                    ->title(__('Validation Failed'))
                                    ->body(__('This template requires a header file, but none is selected.'))
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        $body = collect($components)->firstWhere('type', 'BODY');
                        $text = (string) data_get($body, 'text', '');

                        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
                        $indexes = collect($m[1] ?? [])
                            ->map(fn ($i) => (string) ((int) $i))
                            ->unique();

                        if ($indexes->isNotEmpty()) {
                            if (empty($record->template_variables)) {
                                Notification::make()
                                    ->title(__('Validation Failed'))
                                    ->body(__('This template requires variables, but none are set.'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            foreach ($indexes as $i) {
                                if (empty(Arr::get($record->template_variables, $i))) {
                                    Notification::make()
                                        ->title(__('Validation Failed'))
                                        ->body(__("Template variable {{$i}} is required but is empty."))
                                        ->danger()
                                        ->send();

                                    return;
                                }
                            }
                        }

                        if ($record->recipients()->count() === 0) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('This campaign has no recipients. Please import recipients first.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $recipientsToQueueIds = $record->recipients()
                            ->whereIn('status', ['pending', 'failed'])
                            ->pluck('id');

                        if ($recipientsToQueueIds->isEmpty()) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('This campaign has no recipients in a "pending" or "failed" state to send to.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            DB::transaction(function () use ($record, $recipientsToQueueIds) {
                                $record->status = $record->scheduled_at ? 'scheduled' : 'sending';
                                $record->save();

                                $record->recipients()
                                    ->whereIn('id', $recipientsToQueueIds)
                                    ->update([
                                        'status' => 'pending',
                                        'error_message' => null,
                                        'wa_message_id' => null,
                                    ]);
                            });
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('Database Error'))
                                ->body(__('Could not queue jobs. Error: ').$e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        foreach ($recipientsToQueueIds as $recipientId) {
                            dispatch(new SendPromotionalCampaignMessage($record->id, $recipientId));
                        }

                        Notification::make()
                            ->title(__('Campaign Queued'))
                            ->body(__("All checks passed. Status set to ':status' and :count recipient(s) have been queued for sending.", [
                                'status' => $record->status,
                                'count' => $recipientsToQueueIds->count(),
                            ]))
                            ->success()
                            ->send();
                    }),

                // KEEPING OLD DEEP DIVE MODAL as backup
                Tables\Actions\Action::make('deepDive')
                    ->label(__('Deep Dive (Modal)'))
                    ->icon('heroicon-o-chart-pie')
                    ->modalHeading(fn (PromotionalCampaign $record) => __('Campaign Deep Dive: :name', ['name' => $record->name]))
                    ->modalWidth('6xl')
                    ->modalContent(function (PromotionalCampaign $record) {
                        $summary = $record->recipients()
                            ->selectRaw('status, COUNT(*) as total')
                            ->groupBy('status')
                            ->pluck('total', 'status')
                            ->toArray();

                        $total = array_sum($summary);

                        $failedRecipients = $record->recipients()
                            ->whereIn('status', ['failed', 'limited', 'undeliverable', 'experiment_blocked'])
                            ->latest()
                            ->take(15)
                            ->get();

                        return view('filament.campaigns.deep-dive', [
                            'campaign' => $record,
                            'summary' => $summary,
                            'total' => $total,
                            'failedRecipients' => $failedRecipients,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn ($action) => $action->label(__('Close'))),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function getStatusBreakdownForRow(PromotionalCampaign $record): string
    {
        $chips = [
            [
                'count' => (int) ($record->pending_count ?? 0),
                'label' => 'Pending',
                'icon' => '⏳',
                'class' => 'bg-amber-50 text-amber-700 ring-amber-100',
            ],
            [
                'count' => (int) ($record->sent_count ?? 0),
                'label' => 'Sent',
                'icon' => '📤',
                'class' => 'bg-sky-50 text-sky-700 ring-sky-100',
            ],
            [
                'count' => (int) ($record->delivered_count ?? 0),
                'label' => 'Delivered',
                'icon' => '📬',
                'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            ],
            [
                'count' => (int) ($record->read_count ?? 0),
                'label' => 'Read',
                'icon' => '👁',
                'class' => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
            ],
            [
                'count' => (int) ($record->failed_count ?? 0),
                'label' => 'Failed',
                'icon' => '⚠️',
                'class' => 'bg-rose-50 text-rose-700 ring-rose-100',
            ],
            [
                'count' => (int) ($record->limited_count ?? 0)
                    + (int) ($record->undeliverable_count ?? 0)
                    + (int) ($record->experiment_blocked_count ?? 0),
                'label' => 'Other',
                'icon' => '🔔',
                'class' => 'bg-slate-50 text-slate-700 ring-slate-100',
            ],
        ];

        $total = array_sum(array_column($chips, 'count'));

        $html = '<div class="flex flex-wrap gap-1">';

        foreach ($chips as $chip) {
            if ($chip['count'] <= 0) {
                continue;
            }

            $html .= sprintf(
                '<div class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 %s">
                    <span>%s</span>
                    <span>%s</span>
                    <span class="uppercase tracking-wide text-[0.65rem]">%s</span>
                </div>',
                e($chip['class']),
                e($chip['icon']),
                number_format($chip['count']),
                e($chip['label'])
            );
        }

        if ($total === 0) {
            $html .= '<span class="text-gray-400 text-xs">-</span>';
        }

        $html .= '</div>';

        return $html;
    }

    protected static function getHeaderFormat(Get $get): string
    {
        $components = $get('template_details.components') ?? [];
        $header = collect($components)->firstWhere('type', 'HEADER');

        return strtoupper((string) data_get($header, 'format', ''));
    }

    protected static function headerIsRequired(Get $get): bool
    {
        return in_array(self::getHeaderFormat($get), ['IMAGE', 'VIDEO', 'DOCUMENT'], true);
    }

    protected static function acceptedMimeTypesForHeaderFormat(string $format): array
    {
        return match ($format) {
            'IMAGE' => ['image/jpeg', 'image/png', 'image/webp'],
            'VIDEO' => ['video/mp4', 'video/quicktime'],
            'DOCUMENT' => ['application/pdf'],
            'default' => [],
        };
    }

    protected static function assertMediaMatchesHeaderFormat(string $format, Media $media): void
    {
        $type = (string) $media->type;

        $ok = match ($format) {
            'IMAGE' => str_starts_with($type, 'image/'),
            'VIDEO' => str_starts_with($type, 'video/'),
            'DOCUMENT' => $type === 'application/pdf',
            'default' => true,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'header_media_id' => __('Selected media does not match required header type (:type).', [
                    'type' => strtolower($format ?: 'media'),
                ]),
            ]);
        }
    }

    protected static function resolveMediaFromState($state): ?Media
    {
        $id = static::extractCuratorMediaId($state);

        if (! $id) {
            return null;
        }

        return Media::query()->whereKey($id)->first();
    }

    public static function extractCuratorMediaId($state): ?int
    {
        if (blank($state)) {
            return null;
        }

        // Curator sometimes stores: ['id'=>123]
        if (is_array($state) && isset($state['id']) && is_numeric($state['id'])) {
            return (int) $state['id'];
        }

        // Sometimes: [123] or [['id'=>123]]
        if (is_array($state)) {
            $last = Arr::last($state);

            return static::extractCuratorMediaId($last);
        }

        // numeric string or int (legacy)
        if (is_numeric($state)) {
            return (int) $state;
        }

        return null;
    }

    /**
     * Extract BODY variables and their defaults from META "example".
     * Returns array keys as strings: ['1' => '...', '2' => '...']
     */
    protected static function extractBodyVarDefaults(array $components): array
    {
        $body = collect($components)->firstWhere('type', 'BODY');
        $text = (string) data_get($body, 'text', '');

        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
        $numbers = collect($m[1] ?? [])
            ->map(fn ($i) => (int) $i)
            ->unique()
            ->sort()
            ->values();

        // Meta example usually: example.body_text = [["val1","val2",...]]
        $exampleRow = data_get($body, 'example.body_text.0', []);
        if (! is_array($exampleRow)) {
            $exampleRow = [];
        }

        $defaults = [];
        foreach ($numbers as $n) {
            $defaults[(string) $n] = (string) ($exampleRow[$n - 1] ?? '');
        }

        return $defaults;
    }

    /**
     * Tries to map an existing stored media path/url to a Curator media id.
     * Works if your Media::path matches the storage relative path.
     */
    protected static function findCuratorMediaIdFromPath(?string $pathOrUrl): ?int
    {
        if (! $pathOrUrl) {
            return null;
        }

        $p = self::normalizeToStoragePath($pathOrUrl);

        $media = Media::query()
            ->where('path', $p)
            ->orWhere('path', ltrim($p, '/'))
            ->latest('id')
            ->first();

        return $media?->id;
    }

    /**
     * Normalize any of:
     * - full url: https://domain.com/storage/xxx.mp4
     * - asset url: /storage/xxx.mp4
     * - raw path: campaign-media/xxx.mp4
     * into a storage relative path: xxx.mp4 (without leading slash)
     */
    protected static function normalizeToStoragePath(string $pathOrUrl): string
    {
        $p = trim($pathOrUrl);

        // strip domain
        $p = preg_replace('#^https?://[^/]+#', '', $p);

        // strip /storage/ prefix
        $p = preg_replace('#^/storage/#', '', $p);

        // some code stores "storage/xxx"
        $p = preg_replace('#^storage/#', '', $p);

        return ltrim($p, '/');
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            BulkSenderStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkSenderCampaigns::route('/'),
            'create' => Pages\CreateBulkSenderCampaign::route('/create'),
            'view' => Pages\ViewBulkSenderCampaign::route('/{record}'),
            'edit' => Pages\EditBulkSenderCampaign::route('/{record}/edit'),
            // NEW PAGE
            'analytics' => Pages\CampaignAnalytics::route('/{record}/analytics'),
        ];
    }
}
