<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BulkInviteCampaignResource\Pages;
use App\Filament\Resources\BulkInviteCampaignResource\RelationManagers\RecipientsRelationManager; // Added this
use App\Jobs\SendCampaignInvite;
use App\Models\BulkInviteCampaign;
use App\Models\BulkInviteCampaignRecipient;
use App\Services\WhatsAppTemplateCatalog;
use App\Support\Phone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB; // <-- ADDED THIS FOR MANUAL TRANSACTIONS

class BulkInviteCampaignResource extends Resource
{
    protected static ?string $model = BulkInviteCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Bulk Invitations';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // SECTION 1: PREVIEW (LIVE & REACTIVE)
            Forms\Components\Section::make('Preview')
                ->schema([
                    Forms\Components\View::make('filament.campaigns.preview')
                        ->viewData(fn (Get $get): array => [
                            'get' => $get,
                            'template_name' => $get('template_name'),
                            'template_details' => $get('template_details'),
                            'template_variables' => $get('template_variables'),
                            'default_locale' => $get('default_locale'),
                            'header_image_path' => $get('header_image_path'),
                        ])
                        ->dehydrated(false)
                        ->columnSpan('full'),
                ]),

            // SECTION 2: BASICS
            Forms\Components\Section::make('Campaign Basics')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Campaign Name')
                        ->required()
                        ->maxLength(160)
                        ->live(onBlur: true),

                    Forms\Components\Select::make('template_name')
                        ->label('Meta Template')
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
                        ->label('Template Language')
                        ->content(fn (Get $get) => (string) ($get('template_details.language') ?? '—')),

                    Forms\Components\Hidden::make('default_locale'),
                    Forms\Components\Hidden::make('template_details'),
                ]),

            // SECTION 3: HEADER (IMAGE upload only if template header is IMAGE)
            Forms\Components\Section::make('Header Media')
                ->visible(function (Get $get): bool {
                    $components = $get('template_details.components') ?? [];
                    $header = collect($components)->firstWhere('type', 'HEADER');

                    return $header && strtoupper((string) ($header['format'] ?? '')) === 'IMAGE';
                })
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('header_info')
                        ->content('This template requires an image header.')
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('header_image_path')
                        ->label('Header Image')
                        ->image()
                        ->directory('whatsapp/campaigns/headers')
                        ->visibility('public')
                        ->imageEditor()
                        ->required()
                        ->live(), // Keep live here
                ]),

            // SECTION 4: VARIABLES (auto from template)
            Forms\Components\Section::make('Template Variables')
                ->description('Provide values for placeholders required by the selected template.')
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
                                ->content('This template body does not contain any variables.'),
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

            // SECTION 5: SCHEDULE
            Forms\Components\Section::make('Schedule & Throttle')
                ->columns(3)
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Schedule At')
                        ->seconds(false)
                        ->timezone(config('app.timezone')),

                    Forms\Components\TextInput::make('send_rate_per_min')
                        ->label('Max Sends / Minute')
                        ->numeric()
                        ->default(600)
                        ->minValue(60),

                    Forms\Components\TextInput::make('status')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Status will update automatically.')
                        ->default('draft'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('template_name')->label('Template')->toggleable(),
                Tables\Columns\TextColumn::make('default_locale')->label('Locale')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'scheduled',
                        'warning' => 'running',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('total_recipients')->label('Total')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('sent_count')->label('Sent')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('failed_count')->label('Failed')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'running' => 'Running',
                        'paused' => 'Paused',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('sendTest')
                    ->label('Send Test')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\TextInput::make('test_msisdn')
                            ->label('Test phone (E.164 or local)')
                            ->helperText('Accepts GCC + Egypt. Local numbers are parsed with Kuwait rules first.')
                            ->required(),
                        Forms\Components\Select::make('preferred_region')
                            ->label('Preferred region for local numbers')
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
                    ->action(function (BulkInviteCampaign $record, array $data) {
                        // Normalize & validate for GCC + Egypt (mobile only)
                        $e164 = Phone::parseToE164AcrossRegions(
                            (string) $data['test_msisdn'],
                            ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'],    // allowed regions
                            (string) ($data['preferred_region'] ?? 'KW'),  // preferred region for local
                            true                                           // mobile only
                        );

                        if (! $e164) {
                            Notification::make()
                                ->title('Invalid phone')
                                ->body('Please enter a valid mobile number from Kuwait, Saudi, UAE, Qatar, Bahrain, Oman, or Egypt.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Upsert a recipient under this campaign (idempotent by msisdn)
                        $recipient = BulkInviteCampaignRecipient::firstOrNew(
                            ['bulk_invite_campaign_id' => $record->id, 'msisdn' => $e164],
                            ['status' => 'pending']
                        );

                        // Ensure consistent state and save
                        $recipient->status = 'pending';
                        $recipient->error_message = null;
                        $recipient->wa_message_id = null;
                        $recipient->save();

                        // Dispatch a test send job for this recipient
                        dispatch(new SendCampaignInvite($record->id, $recipient->id));

                        Notification::make()
                            ->title('Test Sent')
                            ->body('A test invite has been queued for sending to '.$e164)
                            ->success()
                            ->send();
                    }),

                // *** MODIFIED VALIDATE AND QUEUE ACTION ***
                Tables\Actions\Action::make('validateAndQueue')
                    ->label('Validate & Queue')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Queue All Recipients')
                    ->modalDescription('Are you sure you want to queue all pending or failed recipients for this campaign?')
                    ->action(function (BulkInviteCampaign $record) {
                        // 1. Check template
                        if (! $record->template_name || ! $record->template_details) {
                            Notification::make()->title('Validation Failed')->body('A valid template must be selected.')->danger()->send();

                            return;
                        }

                        // 2. Check status
                        if (in_array($record->status, ['running', 'completed'])) {
                            Notification::make()->title('Validation Failed')->body('Campaign is already running or completed.')->danger()->send();

                            return;
                        }

                        // 3. Check for required header image
                        $components = $record->template_details['components'] ?? [];
                        $header = collect($components)->firstWhere('type', 'HEADER');
                        if ($header && strtoupper((string) ($header['format'] ?? '')) === 'IMAGE' && ! $record->header_image_path) {
                            Notification::make()->title('Validation Failed')->body('This template requires a header image, but none is uploaded.')->danger()->send();

                            return;
                        }

                        // 4. Check for required variables
                        $body = collect($components)->firstWhere('type', 'BODY');
                        $text = (string) data_get($body, 'text', '');
                        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m);
                        $indexes = collect($m[1] ?? [])->map(fn ($i) => (string) ((int) $i))->unique();

                        if ($indexes->isNotEmpty()) {
                            if (empty($record->template_variables)) {
                                Notification::make()->title('Validation Failed')->body('This template requires variables, but none are set.')->danger()->send();

                                return;
                            }
                            // Check if all required variable keys exist and are not empty
                            foreach ($indexes as $i) {
                                if (empty(Arr::get($record->template_variables, $i))) {
                                    Notification::make()->title('Validation Failed')->body("Template variable {{$i}} is required but is empty.")->danger()->send();

                                    return;
                                }
                            }
                        }

                        // 5. Check for recipients
                        $recipientsToQueueIds = $record->recipients()
                            ->whereIn('status', ['pending', 'failed'])
                            ->pluck('id'); // <-- Get IDs to queue

                        if ($record->recipients()->count() === 0) {
                            Notification::make()->title('Validation Failed')->body('This campaign has no recipients. Please import recipients first.')->danger()->send();

                            return;
                        }

                        if ($recipientsToQueueIds->isEmpty()) {
                            Notification::make()->title('Validation Failed')->body('This campaign has no recipients in a "pending" or "failed" state to send to.')->danger()->send();

                            return;
                        }

                        // 6. *** NEW *** Perform all DB updates inside a transaction
                        try {
                            DB::transaction(function () use ($record, $recipientsToQueueIds) {
                                // All checks passed. Set status.
                                $record->status = $record->scheduled_at ? 'scheduled' : 'running';
                                $record->save();

                                // Reset all pending/failed recipients in one query
                                $record->recipients()
                                    ->whereIn('id', $recipientsToQueueIds)
                                    ->update([
                                        'status' => 'pending',
                                        'error_message' => null,
                                        'wa_message_id' => null,
                                    ]);
                            });
                        } catch (\Throwable $e) {
                            // If DB transaction fails
                            Notification::make()
                                ->title('Database Error')
                                ->body('Could not queue jobs. Error: '.$e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        // 7. *** NEW *** Dispatch jobs AFTER the transaction has committed
                        foreach ($recipientsToQueueIds as $recipientId) {
                            dispatch(new SendCampaignInvite($record->id, $recipientId));
                        }

                        $queuedCount = $recipientsToQueueIds->count();

                        Notification::make()
                            ->title('Campaign Queued')
                            ->body("All checks passed. Status set to '{$record->status}' and {$queuedCount} recipient(s) have been queued for sending.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class, // Enabled this
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkInviteCampaigns::route('/'),
            'create' => Pages\CreateBulkInviteCampaign::route('/create'),
            'edit' => Pages\EditBulkInviteCampaign::route('/{record}/edit'),
        ];
    }
}
