<?php

namespace App\Wa\Filament\Resources;

use App\Wa\Filament\Resources\PromotionalCampaignResource\Pages;
use App\Wa\Filament\Resources\PromotionalCampaignResource\RelationManagers\RecipientsRelationManager;
use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Jobs\SendPromotionalCampaignMessage;
use App\Wa\Services\WhatsAppTemplateCatalog;
use App\Wa\Support\Phone;
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
use Illuminate\Support\HtmlString;

class PromotionalCampaignResource extends Resource
{
    protected static ?string $model = PromotionalCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    public static function canAccess(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): string
    {
        return __('Promotions');
    }

    public static function getModelLabel(): string
    {
        return __('Promotional Campaign');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Promotional Campaigns');
    }

    public static function getNavigationLabel(): string
    {
        return __('Campaigns');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // SECTION 1: LIVE PREVIEW
            Forms\Components\Section::make('Preview')
                ->schema([
                    Forms\Components\Placeholder::make('preview')
                        ->content(function (Get $get) {
                            $data = [
                                'template_name' => $get('template_name'),
                                'template_details' => $get('template_details'),
                                'template_variables' => $get('template_variables'),
                                'default_locale' => $get('default_locale'),
                                'header_image_path' => $get('header_image_path'),
                            ];

                            return new HtmlString(
                                view('filament.campaigns.preview', $data)->render()
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
                        ->live(onBlur: true),

                    // (Optional) tie to restaurant if your model has restaurant_id
                    Forms\Components\Select::make('restaurant_id')
                        ->label(__('Restaurant'))
                        ->relationship('restaurant', 'name->en') // adjust if your translatable column differs
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('template_name')
                        ->label(__('Meta Template'))
                        ->options(fn () => app(WhatsAppTemplateCatalog::class)->options())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(debounce: 300)
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

                            $vars = $get('template_variables') ?? [];
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
                                if (! array_key_exists($i, (array) $vars)) {
                                    $vars[$i] = '';
                                    $needSet = true;
                                }
                            }
                            if ($needSet) {
                                $set('template_variables', $vars);
                            }
                        })
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            $set('template_details', null);
                            $set('template_variables', []);
                            $set('header_image_path', null);

                            if (! $state) {
                                return;
                            }

                            $details = app(WhatsAppTemplateCatalog::class)->find($state) ?? [];
                            if (empty($details)) {
                                return;
                            }

                            $set('template_details', $details);

                            $tplLang = (string) (data_get($details, 'language', 'en'));
                            $set('default_locale', $tplLang);

                            $body = collect(data_get($details, 'components', []))->firstWhere('type', 'BODY');
                            $text = (string) data_get($body, 'text', '');
                            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
                            $indexes = collect($m[1] ?? [])
                                ->map(fn ($i) => (string) ((int) $i))
                                ->unique()
                                ->sort()
                                ->values();

                            $vars = [];
                            foreach ($indexes as $i) {
                                $vars[$i] = '';
                            }
                            $set('template_variables', $vars);
                        }),

                    Forms\Components\Placeholder::make('template_language_display')
                        ->label(__('Template Language'))
                        ->content(fn (Get $get) => (string) ($get('template_details.language') ?? '—')),

                    Forms\Components\Hidden::make('default_locale'),
                    Forms\Components\Hidden::make('template_details'),
                ]),

            // SECTION 3: HEADER IMAGE (if template HEADER=IMAGE)
            Forms\Components\Section::make(__('Header Media'))
                ->visible(function (Get $get): bool {
                    $components = $get('template_details.components') ?? [];
                    $header = collect($components)->firstWhere('type', 'HEADER');

                    return $header && strtoupper((string) ($header['format'] ?? '')) === 'IMAGE';
                })
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('header_info')
                        ->content(__('This template requires an image header.'))
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('header_image_path')
                        ->label(__('Header Image'))
                        ->image()
                        ->directory('whatsapp/campaigns/headers')
                        ->visibility('public')
                        ->imageEditor()
                        ->required()
                        ->live(),
                ]),

            // SECTION 4: TEMPLATE VARIABLES
            Forms\Components\Section::make(__('Template Variables'))
                ->description(__('Provide values for placeholders required by the selected template.'))
                ->visible(function (Get $get): bool {
                    $components = $get('template_details.components') ?? [];
                    $body = collect($components)->firstWhere('type', 'BODY');
                    $text = (string) data_get($body, 'text', '');

                    return $text !== '' && str_contains($text, '{{');
                })
                ->schema(function (Get $get): array {
                    $components = $get('template_details.components') ?? [];
                    $body = collect($components)->firstWhere('type', 'BODY');
                    $text = (string) data_get($body, 'text', '');

                    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
                    $numbers = collect($m[1] ?? [])
                        ->map(fn ($i) => (int) $i)
                        ->unique()
                        ->sort()
                        ->values();

                    if ($numbers->isEmpty()) {
                        return [
                            Forms\Components\Placeholder::make('no_vars')
                                ->content(__('This template body does not contain any variables.')),
                        ];
                    }

                    $fields = [];
                    foreach ($numbers as $n) {
                        $fields[] = Forms\Components\TextInput::make("template_variables.{$n}")
                            ->label("Variable {{$n}}")
                            ->required()
                            ->helperText("Replaces {{{{$n}}}} in the template body.")
                            ->live(debounce: 500);
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
                        ->timezone(config('app.timezone')),

                    Forms\Components\TextInput::make('send_rate_per_min')
                        ->label(__('Max Sends / Minute'))
                        ->numeric()
                        ->default(600)
                        ->minValue(60),

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
            ->query(
                PromotionalCampaign::withCount('conversions')
            )
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
                        'warning' => 'sending',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label(__('Restaurant'))
                    ->formatStateUsing(fn ($record) => $record->restaurant?->getTranslation('name', 'en'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('restaurant', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_recipients')
                    ->label(__('Recipients'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversions_count')
                    ->label(__('Conversions'))
                    ->sortable()
                    ->numeric(),

                Tables\Columns\TextColumn::make('conversion_rate')
                    ->label(__('Conv. Rate'))
                    ->formatStateUsing(function (PromotionalCampaign $record) {
                        if ($record->total_recipients === 0) {
                            return '0%';
                        }
                        $rate = ($record->conversions_count / $record->total_recipients) * 100;

                        return number_format($rate, 2).'%';
                    }),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'draft' => __('Draft'),
                        'sending' => __('Sending'),
                        'completed' => __('Completed'),
                        'failed' => __('Failed'),
                    ]),
            ])
            ->actions([
                // SEND TEST
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

                        dispatch(new SendPromotionalCampaignMessage($record->id, $recipient->id));

                        Notification::make()
                            ->title(__('Test Sent'))
                            ->body(__('A test message has been queued for sending to ').$e164)
                            ->success()
                            ->send();
                    }),

                // VALIDATE & QUEUE
                Tables\Actions\Action::make('validateAndQueue')
                    ->label(__('Validate & Queue'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('Queue All Recipients'))
                    ->modalDescription(__('Are you sure you want to queue all pending or failed recipients for this campaign?'))
                    ->action(function (PromotionalCampaign $record) {
                        // 1. Check template
                        if (! $record->template_name || ! $record->template_details) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('A valid template must be selected.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        // 2. Check status
                        if (in_array($record->status, ['sending', 'completed'], true)) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('Campaign is already sending or completed.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        // 3. Check for required header image
                        $components = $record->template_details['components'] ?? [];
                        $header = collect($components)->firstWhere('type', 'HEADER');
                        if ($header
                            && strtoupper((string) ($header['format'] ?? '')) === 'IMAGE'
                            && ! $record->header_image_path) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('This template requires a header image, but none is uploaded.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        // 4. Check for required variables
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

                        // 5. Recipients check
                        $recipientsToQueueIds = $record->recipients()
                            ->whereIn('status', ['pending', 'failed'])
                            ->pluck('id');

                        if ($record->recipients()->count() === 0) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('This campaign has no recipients. Please import recipients first.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($recipientsToQueueIds->isEmpty()) {
                            Notification::make()
                                ->title(__('Validation Failed'))
                                ->body(__('This campaign has no recipients in a "pending" or "failed" state to send to.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        // 6. Transaction: update campaign + recipients
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

                        $queuedCount = $recipientsToQueueIds->count();

                        Notification::make()
                            ->title(__('Campaign Queued'))
                            ->body(__("All checks passed. Status set to ':status' and :count recipient(s) have been queued for sending.", [
                                'status' => $record->status,
                                'count' => $queuedCount,
                            ]))
                            ->success()
                            ->send();
                    }),

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

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotionalCampaigns::route('/'),
            'create' => Pages\CreatePromotionalCampaign::route('/create'),
            'view' => Pages\ViewPromotionalCampaign::route('/{record}'),
            'edit' => Pages\EditPromotionalCampaign::route('/{record}/edit'),
        ];
    }
}
