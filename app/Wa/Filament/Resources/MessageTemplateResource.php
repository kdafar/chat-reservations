<?php

namespace App\Wa\Filament\Resources;

use App\Wa\Filament\Resources\MessageTemplateResource\Pages;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Services\WhatsApp\WhatsAppService;
use App\Wa\Support\Curator\CuratorPicker;
use App\Wa\Support\Curator\Media;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getNavigationGroup(): string
    {
        return __('Promotions');
    }

    public static function getModelLabel(): string
    {
        return __('Message Template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Message Templates');
    }

    public static function getNavigationLabel(): string
    {
        return __('Message Templates');
    }

    public static function form(Form $form): Form
    {
        // Lock condition: already approved on Meta
        $isLocked = fn ($record) => $record?->status === 'APPROVED';

        return $form->schema([
            Forms\Components\Wizard::make([

                /* ───────────────────────── STEP 1: BASICS ───────────────────────── */
                Forms\Components\Wizard\Step::make('Basics')
                    ->description('Name and Category')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Template Name (Slug)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Lowercase + underscores only. Suffix _en/_ar enforced automatically.')
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && ($record->local_status === 'published' || $record->status === 'APPROVED'))
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                $lang = (string) ($get('language') ?? 'en');
                                $normalized = static::normalizeTemplateName((string) ($state ?? ''), $lang);
                                if ($normalized !== (string) ($state ?? '')) {
                                    $set('name', $normalized);
                                }
                            })
                            ->rule(function ($record) {
                                return function (string $attribute, $value, \Closure $fail) use ($record) {
                                    $v = (string) $value;

                                    if (! preg_match('/^[a-z0-9_]+$/', $v)) {
                                        $fail('Meta requires strictly lowercase letters, numbers, and underscores.');

                                        return;
                                    }

                                    // If editing and field is effectively locked, don't block the save
                                    $locked = $record && ($record->local_status === 'published' || $record->status === 'APPROVED');
                                    if ($locked) {
                                        return;
                                    }

                                    if (! preg_match('/_(en|ar)$/', $v)) {
                                        $fail('Name must end with _en or _ar.');
                                    }
                                };
                            })

                            // Check existence on Meta to prevent duplicates
                            ->rule(function ($record) {
                                return function (string $attribute, $value, \Closure $fail) use ($record) {
                                    $value = (string) $value;
                                    if ($value === '' || ! preg_match('/^[a-z0-9_]+$/', $value)) {
                                        return;
                                    }
                                    if ($record && (string) $record->name === $value) {
                                        return; // Name hasn't changed
                                    }

                                    /** @var \App\Services\WhatsApp\WhatsAppService $wa */
                                    $wa = app(\App\Services\WhatsApp\WhatsAppService::class);
                                    if ($wa->doesTemplateExist($value)) {
                                        $fail("A template with the name '{$value}' already exists on your Meta Business Account.");
                                    }
                                };
                            }),

                        Forms\Components\Select::make('category')
                            ->options([
                                'MARKETING' => 'Marketing',
                                'UTILITY' => 'Utility',
                                'AUTHENTICATION' => 'Authentication',
                            ])
                            ->required()
                            ->disabled($isLocked),

                        Forms\Components\Select::make('language')
                            ->options(['en' => 'English', 'ar' => 'Arabic'])
                            ->default('en')
                            ->required()
                            ->live()
                            ->disabled($isLocked)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                $current = (string) ($get('name') ?? '');
                                $normalized = static::normalizeTemplateName($current, (string) ($state ?: 'en'));
                                if ($normalized !== $current) {
                                    $set('name', $normalized);
                                }
                            }),
                    ])->columns(2),

                /* ───────────────────────── STEP 2: CONTENT & BUTTONS ───────────────────────── */
                Forms\Components\Wizard\Step::make('Message Content')
                    ->description('Header, Body, Footer, and Buttons')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            // LEFT: Editor
                            Forms\Components\Group::make()
                                ->columnSpan(2)
                                ->schema([

                                    // HEADER
                                    Forms\Components\Section::make('Header')
                                        ->compact()
                                        ->schema([
                                            Forms\Components\Select::make('header_type')
                                                ->label('Header Type')
                                                ->options([
                                                    'NONE' => 'None',
                                                    'TEXT' => 'Text',
                                                    'IMAGE' => 'Image',
                                                    'VIDEO' => 'Video',
                                                    'DOCUMENT' => 'Document',
                                                    'LOCATION' => 'Location',
                                                ])
                                                ->default('NONE')
                                                ->live()
                                                ->disabled($isLocked),

                                            Forms\Components\Textarea::make('header_text')
                                                ->label('Header Text')
                                                ->rows(2)
                                                ->placeholder('Example: Hello {{1}}')
                                                ->visible(fn (Get $get) => $get('header_type') === 'TEXT')
                                                ->required(fn (Get $get) => $get('header_type') === 'TEXT')
                                                ->maxLength(60)
                                                ->live(debounce: 500)
                                                ->disabled($isLocked)
                                                // Normalize header vars on blur
                                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                    $state = (string) ($state ?? '');
                                                    $normalized = preg_replace('/\{\{\s*(\d+)\s*\}\}/', '{{$1}}', $state);
                                                    if ($normalized !== $state) {
                                                        $set('header_text', $normalized);
                                                    }
                                                })
                                                ->rule(function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $text = (string) $value;
                                                        preg_match_all('/\{\{\s*\d+\s*\}\}/', $text, $m);
                                                        $count = count($m[0] ?? []);
                                                        if ($count > 1) {
                                                            $fail('Header text can include only ONE variable (like {{1}}).');
                                                        }
                                                        if ($count === 1 && ! preg_match('/\{\{\s*1\s*\}\}/', $text)) {
                                                            $fail('Header variable must be {{1}}.');
                                                        }
                                                    };
                                                }),

                                            Forms\Components\TextInput::make('header_example')
                                                ->label('Header Variable Sample (for {{1}})')
                                                ->placeholder('Example: Mustaqeem')
                                                ->visible(fn (Get $get) => $get('header_type') === 'TEXT' && preg_match('/\{\{\s*1\s*\}\}/', (string) $get('header_text')))
                                                ->required(fn (Get $get) => $get('header_type') === 'TEXT' && preg_match('/\{\{\s*1\s*\}\}/', (string) $get('header_text')))
                                                ->disabled($isLocked),

                                            Forms\Components\Hidden::make('header_sample_path'),

                                            CuratorPicker::make('header_sample_media_id')
                                                ->label('Media Sample (Required for Approval)')
                                                ->buttonLabel('Select / Upload Sample')
                                                ->color('primary')
                                                ->disk(config('curator.disk', 'public'))
                                                ->directory('template-samples')
                                                ->visibility('public')
                                                ->helperText('Pick or upload a sample file. This sample is sent to Meta for review.')
                                                ->visible(fn (Get $get) => in_array($get('header_type'), ['IMAGE', 'VIDEO', 'DOCUMENT'], true))
                                                ->required(function (Get $get) {
                                                    return in_array($get('header_type'), ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
                                                        && blank($get('header_sample_path'))
                                                        && blank($get('header_sample_media_id'));
                                                })
                                                ->disabled($isLocked)
                                                ->acceptedFileTypes(fn (Get $get) => match ((string) $get('header_type')) {
                                                    'IMAGE' => ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'],
                                                    'VIDEO' => ['video/mp4', 'video/quicktime', 'video/3gpp'],
                                                    'DOCUMENT' => ['application/pdf'],
                                                    default => [],
                                                })
                                                ->live()
                                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                    if (blank($state)) {
                                                        $set('header_sample_path', null);

                                                        return;
                                                    }

                                                    $media = static::resolveMediaFromState($state);

                                                    if (! $media) {
                                                        $set('header_sample_path', null);

                                                        return;
                                                    }

                                                    static::assertMediaMatchesHeaderType((string) $get('header_type'), $media);

                                                    // Store a stable path (what you will send to Meta later)
                                                    $set('header_sample_path', ltrim((string) $media->path, '/'));
                                                }),

                                        ]),

                                    // BODY
                                    Forms\Components\Section::make('Body')
                                        ->compact()
                                        ->schema([
                                            // CHANGED: Renamed 'body_text' to 'body' to match database column
                                            Forms\Components\Textarea::make('body')
                                                ->label('Message Body')
                                                ->rows(7)
                                                ->placeholder('Hello {{1}}, your order {{2}} is confirmed.')
                                                ->required()
                                                ->live(debounce: 250)
                                                ->disabled($isLocked)
                                                ->hintActions([
                                                    Action::make('b_bold')->label('B')->action(fn (Get $get, Set $set) => $set('body', rtrim((string) $get('body')).' *bold*')),
                                                    Action::make('b_italic')->label('I')->action(fn (Get $get, Set $set) => $set('body', rtrim((string) $get('body')).' _italic_')),
                                                    Action::make('b_var')->label('+ Var')->action(function (Get $get, Set $set) {
                                                        $txt = (string) $get('body');
                                                        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $txt, $m);
                                                        $nums = array_map('intval', $m[1] ?? []);
                                                        $next = empty($nums) ? 1 : (max($nums) + 1);
                                                        $set('body', rtrim($txt).' {{'.$next.'}}');
                                                    }),
                                                ])
                                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                    $state = (string) ($state ?? '');

                                                    // remove leading newlines
                                                    $state = preg_replace('/^\h*\R+/', '', $state);

                                                    // Normalize {{ 1 }} -> {{1}}
                                                    $normalized = preg_replace('/\{\{\s*(\d+)\s*\}\}/', '{{$1}}', $state);
                                                    if ($normalized !== $state) {
                                                        $set('body', $normalized);
                                                        $state = $normalized; // use normalized for sync
                                                    }

                                                    // Sync example rows with body variables
                                                    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $state, $m);
                                                    $nums = collect($m[1] ?? [])->map(fn ($n) => (int) $n)->unique()->sort()->values()->all();
                                                    $targetCount = count($nums);

                                                    $current = $get('body_examples') ?? [];
                                                    if (! is_array($current)) {
                                                        $current = [];
                                                    }

                                                    $new = [];
                                                    for ($i = 0; $i < $targetCount; $i++) {
                                                        $new[] = ['value' => (string) ($current[$i]['value'] ?? '')];
                                                    }
                                                    $set('body_examples', $new);
                                                })
                                                ->rule(function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $text = (string) $value;

                                                        if (mb_strlen($text) > 1024) {
                                                            $fail('Body must be 1024 characters or less.');
                                                        }

                                                        if (preg_match('/^\s*\{\{\s*\d+\s*\}\}/', $text)) {
                                                            $fail('Meta rejects templates starting immediately with a variable. Add a greeting or text before {{1}}.');
                                                        }
                                                        if (preg_match('/\{\{\s*\d+\s*\}\}\s*$/', $text)) {
                                                            $fail('Meta rejects templates ending immediately with a variable. Add punctuation or text after the last variable.');
                                                        }
                                                        if (str_contains($text, "\t")) {
                                                            $fail('Tabs are not allowed.');
                                                        }
                                                        if (preg_match('/ {4,}/', $text)) {
                                                            $fail('Too many consecutive spaces (max 4).');
                                                        }
                                                        if (preg_match('/[\r\n]{3,}/', $text)) {
                                                            $fail('Too many consecutive newlines (max 2).');
                                                        }

                                                        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
                                                        $nums = collect($m[1] ?? [])->map(fn ($n) => (int) $n)->unique()->sort()->values()->all();

                                                        // Max variables check
                                                        $maxVars = 10;
                                                        if (count($nums) > $maxVars) {
                                                            $fail("Meta allows a maximum of {$maxVars} variables in the body.");

                                                            return;
                                                        }

                                                        // Range check
                                                        foreach ($nums as $n) {
                                                            if ($n < 1 || $n > $maxVars) {
                                                                $fail("Variable numbers must be between {{1}} and {{$maxVars}}.");

                                                                return;
                                                            }
                                                        }

                                                        if (! empty($nums)) {
                                                            if ($nums[0] !== 1) {
                                                                $fail('Variables must start with {{1}}.');
                                                            }
                                                            for ($i = 0; $i < count($nums); $i++) {
                                                                if ($nums[$i] !== ($i + 1)) {
                                                                    $fail('Variables must be sequential ({{1}}, {{2}}, ...). Missing {{'.($i + 1).'}}.');
                                                                }
                                                            }
                                                        }
                                                    };
                                                }),

                                            Forms\Components\Repeater::make('body_examples')
                                                ->label('Body Variable Samples')
                                                ->visible(fn (Get $get) => preg_match('/\{\{\s*\d+\s*\}\}/', (string) $get('body')))
                                                ->required(fn (Get $get) => preg_match('/\{\{\s*\d+\s*\}\}/', (string) $get('body')))
                                                ->disabled($isLocked)
                                                ->addable(false)
                                                ->deletable(false)
                                                ->reorderable(false)
                                                ->schema([
                                                    Forms\Components\TextInput::make('value')
                                                        ->required()
                                                        ->placeholder('Sample content'),
                                                ]),
                                        ]),

                                    // FOOTER
                                    Forms\Components\Section::make('Footer')
                                        ->compact()
                                        ->schema([
                                            Forms\Components\TextInput::make('footer_text')
                                                ->label('Footer Text (Optional)')
                                                ->placeholder('e.g. Reply STOP to unsubscribe')
                                                ->maxLength(60)
                                                ->live(onBlur: true)
                                                ->disabled($isLocked)
                                                ->rule(function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        if (preg_match('/\{\{\s*\d+\s*\}\}/', (string) $value)) {
                                                            $fail('Footer cannot contain variables.');
                                                        }
                                                    };
                                                }),
                                        ]),

                                    // BUTTONS (Merged into Message Content Step)
                                    Forms\Components\Section::make('Buttons')
                                        ->compact()
                                        ->schema([
                                            Forms\Components\Repeater::make('buttons_data')
                                                ->label('Buttons')
                                                ->maxItems(3)
                                                ->disabled($isLocked)
                                                ->schema([
                                                    Forms\Components\Select::make('type')
                                                        ->options([
                                                            'QUICK_REPLY' => 'Quick Reply',
                                                            'URL' => 'Website Link',
                                                            'PHONE_NUMBER' => 'Phone Number',
                                                        ])
                                                        ->required()
                                                        ->live()
                                                        ->disabled($isLocked),

                                                    Forms\Components\TextInput::make('text')
                                                        ->label('Button Text')
                                                        ->required()
                                                        ->maxLength(25)
                                                        ->disabled($isLocked)
                                                        ->rule(function () {
                                                            return function (string $attribute, $value, \Closure $fail) {
                                                                if (preg_match('/\{\{\s*\d+\s*\}\}/', (string) $value)) {
                                                                    $fail('Button text cannot contain variables.');
                                                                }
                                                            };
                                                        }),

                                                    Forms\Components\TextInput::make('url')
                                                        ->label('Website URL')
                                                        ->visible(fn (Get $get) => $get('type') === 'URL')
                                                        ->required(fn (Get $get) => $get('type') === 'URL')
                                                        ->url()
                                                        ->placeholder('https://www.example.com')
                                                        ->disabled($isLocked)
                                                        ->rule(function () {
                                                            return function (string $attribute, $value, \Closure $fail) {
                                                                $v = trim((string) $value);
                                                                if ($v === '') {
                                                                    return;
                                                                }

                                                                // Strict HTTPS check
                                                                if (! str_starts_with($v, 'https://')) {
                                                                    $fail('Meta requires URL to start with https://');
                                                                }

                                                                if (preg_match('/\{\{\s*\d+\s*\}\}/', $v)) {
                                                                    $fail('Variables in URL are not supported in this editor.');
                                                                }
                                                            };
                                                        }),

                                                    Forms\Components\TextInput::make('phone_number')
                                                        ->label('Phone Number (CC + Number)')
                                                        ->visible(fn (Get $get) => $get('type') === 'PHONE_NUMBER')
                                                        ->required(fn (Get $get) => $get('type') === 'PHONE_NUMBER')
                                                        ->tel()
                                                        ->disabled($isLocked)
                                                        ->helperText('Enter full number with country code, no "+" sign.')
                                                        ->rule('regex:/^[0-9]{5,20}$/'),
                                                ])
                                                ->rule(function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $rows = is_array($value) ? $value : [];
                                                        $types = collect($rows)->pluck('type')->filter();

                                                        if ($types->contains('QUICK_REPLY') && ($types->contains('URL') || $types->contains('PHONE_NUMBER'))) {
                                                            $fail('Cannot mix Quick Reply buttons with Link/Phone buttons.');
                                                        }
                                                        if ($types->filter(fn ($t) => $t === 'URL')->count() > 1) {
                                                            $fail('Only one Website Link button is allowed.');
                                                        }
                                                        if ($types->filter(fn ($t) => $t === 'PHONE_NUMBER')->count() > 1) {
                                                            $fail('Only one Phone Number button is allowed.');
                                                        }
                                                    };
                                                }),
                                        ]),
                                ]),

                            // RIGHT: Preview
                            Forms\Components\Group::make()
                                ->columnSpan(1)
                                ->schema([
                                    Forms\Components\Section::make('Preview')
                                        ->schema([
                                            Forms\Components\Placeholder::make('preview_render')
                                                ->hiddenLabel()
                                                ->content(fn (Get $get) => new HtmlString(
                                                    view('filament.components.whatsapp-preview-box', [
                                                        'headerType' => $get('header_type'),
                                                        'headerText' => $get('header_text'),
                                                        'bodyText' => $get('body'), // Using correct field 'body'
                                                        'footerText' => $get('footer_text'),
                                                        'buttons' => $get('buttons_data') ?? [],
                                                        'headerSample' => $get('header_sample_path') ?: $get('header_sample_media_id'),
                                                        'headerExample' => $get('header_example'),
                                                        'bodyExamples' => $get('body_examples') ?? [],
                                                    ])->render()
                                                )),
                                        ]),
                                ]),
                        ]),
                    ]),

                /* ───────────────────────── STEP 3: AUTOMATION (Conditional) ───────────────────────── */
                Forms\Components\Wizard\Step::make('Automation')
                    ->description('Auto-Reply & Triggers')
                    ->visible(fn ($record) => $record && $record->status === 'APPROVED') // Visible ONLY if approved
                    ->schema([
                        Forms\Components\Section::make('Internal Configuration')
                            ->description('These settings only affect your local bot and can be changed anytime.')
                            ->schema([
                                Forms\Components\Toggle::make('is_auto_reply')->label('Enable Auto-Reply')->live(),
                                Forms\Components\TagsInput::make('triggers')->label('Keywords / Triggers')->visible(fn (Get $get) => (bool) $get('is_auto_reply')),
                                Forms\Components\Grid::make(1)->visible(fn (Get $get) => (bool) $get('is_auto_reply'))->schema([

                                    // 1. Media Picker (Only if header type allows it)
                                    CuratorPicker::make('campaign_media_id')
                                        ->label('Campaign Media')
                                        ->buttonLabel('Select / Upload Media')
                                        ->disk(config('curator.disk', 'public'))
                                        ->directory('campaign-media')
                                        ->visibility('public')
                                        ->live()
                                        ->visible(fn (Get $get) => in_array($get('header_type'), ['IMAGE', 'VIDEO', 'DOCUMENT'], true))
                                        ->acceptedFileTypes(fn (Get $get) => match ((string) $get('header_type')) {
                                            'IMAGE' => ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'],
                                            'VIDEO' => ['video/mp4', 'video/quicktime', 'video/3gpp'],
                                            'DOCUMENT' => ['application/pdf'],
                                            default => [],
                                        }),

                                    // 2. Dynamic Variable Inputs
                                    Forms\Components\Group::make()
                                        ->schema(function (Get $get) {
                                            $schema = [];

                                            // A. Check Header for {{1}}
                                            // Ensure we check if header_text exists (it might be virtual or null)
                                            if ($get('header_type') === 'TEXT' && preg_match('/\{\{\s*1\s*\}\}/', (string) $get('header_text'))) {
                                                $schema[] = Forms\Components\TextInput::make('auto_reply_data.header_1')
                                                    ->label('Header Value ({{1}})')
                                                    ->placeholder('Value to send for Header {{1}}')
                                                    ->required();
                                            }

                                            // B. Check Body for {{1}}, {{2}}...
                                            // IMPORTANT: We use 'body' now, matching the field name in Step 2 and the DB column
                                            $bodyText = (string) $get('body');
                                            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $matches);
                                            // Get unique numbers and sort them
                                            $vars = collect($matches[1] ?? [])
                                                ->unique()
                                                ->sort()
                                                ->values();

                                            foreach ($vars as $var) {
                                                $schema[] = Forms\Components\TextInput::make("auto_reply_data.body_{$var}")
                                                    ->label("Body Value ({{{$var}}})")
                                                    ->placeholder("Value to send for Body {{{$var}}}")
                                                    ->required();
                                            }

                                            return $schema;
                                        })
                                        ->columns(2),
                                ]),
                            ]),
                    ]),

            ])
                ->columnSpanFull()
                ->submitAction(new HtmlString('<button type="submit" class="fi-btn fi-btn-size-md fi-btn-color-primary relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm outline-none transition duration-75 focus-visible:ring-2 bg-primary-600 text-white hover:bg-primary-500 focus-visible:ring-primary-500/50">Save Changes</button>')),
        ]);
    }

    private static function normalizeTemplateName(string $input, string $lang): string
    {
        $lang = $lang === 'ar' ? 'ar' : 'en';
        $name = strtolower(trim($input));
        $name = preg_replace('/\s+/', '_', $name);
        $name = preg_replace('/[^a-z0-9_]/', '', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        $name = preg_replace('/_(en|ar)$/', '', $name);

        if ($name === '') {
            return '';
        }

        return $name.'_'.$lang;
    }

    public static function extractCuratorMediaId($state): ?int
    {
        if (blank($state)) {
            return null;
        }

        if (is_numeric($state)) {
            return (int) $state;
        }

        if (is_array($state) && array_key_exists('id', $state) && is_numeric($state['id'] ?? null)) {
            return (int) $state['id'];
        }

        if (is_array($state)) {
            return static::extractCuratorMediaId(Arr::last($state));
        }

        return null;
    }

    protected static function resolveMediaFromState($state): ?Media
    {
        $id = static::extractCuratorMediaId($state);

        if (! $id) {
            return null;
        }

        return Media::query()->whereKey($id)->first();
    }

    protected static function assertMediaMatchesHeaderType(string $headerType, Media $media): void
    {
        $headerType = strtoupper($headerType);
        $type = (string) $media->type;

        $ok = match ($headerType) {
            'IMAGE' => str_starts_with($type, 'image/'),
            'VIDEO' => str_starts_with($type, 'video/'),
            'DOCUMENT' => $type === 'application/pdf',
            default => true,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'header_sample_media_id' => "Selected media does not match header type ({$headerType}).",
            ]);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (MessageTemplate $record) => $record->language),

                Tables\Columns\TextColumn::make('category')
                    ->label('Type')
                    ->badge()
                    ->sortable()
                    ->colors([
                        'success' => 'UTILITY',
                        'warning' => 'MARKETING',
                        'gray' => 'AUTHENTICATION',
                    ]),

                Tables\Columns\IconColumn::make('is_auto_reply')
                    ->label('Auto-Reply')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('triggers')
                    ->label('Keywords')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'success' => 'APPROVED',
                        'warning' => 'PENDING',
                        'danger' => 'REJECTED',
                    ]),

                Tables\Columns\TextColumn::make('local_status')
                    ->label('Local')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'primary' => 'published',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'APPROVED' => 'APPROVED',
                        'PENDING' => 'PENDING',
                        'REJECTED' => 'REJECTED',
                    ]),
                Tables\Filters\SelectFilter::make('local_status')
                    ->label('Local Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('syncFromMeta')
                    ->label(__('Sync from Meta'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (WhatsAppService $whatsapp) {
                        $count = $whatsapp->syncTemplatesFromMeta();
                        Notification::make()->title('Templates Synced')->body("{$count} templates updated.")->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publishToMeta')
                    ->label('Publish')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->requiresConfirmation()
                    ->visible(fn (MessageTemplate $record) => blank($record->meta_id) && $record->local_status === 'draft')
                    ->action(function (MessageTemplate $record, WhatsAppService $whatsapp) {
                        try {
                            $whatsapp->publishTemplateToMeta($record);
                            Notification::make()->title('Published to Meta!')->body('Status is now PENDING.')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Publish Failed')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
                Tables\Actions\Action::make('refreshStatus')
                    ->label('Status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (MessageTemplate $record) => filled($record->meta_id))
                    ->action(function (MessageTemplate $record, WhatsAppService $whatsapp) {
                        $whatsapp->refreshTemplateStatus($record);
                        $record->refresh();
                        Notification::make()->title('Status Refreshed')->body("Current status: {$record->status}")->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageTemplates::route('/'),
            'create' => Pages\CreateMessageTemplate::route('/create'),
            'edit' => Pages\EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
