<?php

namespace App\Wa\Filament\Resources\BulkSenderCampaignResource\RelationManagers;

use App\Wa\Hub\Models\ContactGroup;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Jobs\ImportBulkInviteRecipients;
use App\Wa\Jobs\SendPromotionalCampaignMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('msisdn')
                ->label('Phone (E.164)')
                ->helperText('Example: +96551112233')
                ->required()
                ->maxLength(32),

            Forms\Components\TextInput::make('name')
                ->maxLength(120),

            Forms\Components\Select::make('locale')
                ->options(['en' => 'English', 'ar' => 'العربية'])
                ->native(false),

            Forms\Components\Select::make('source')
                ->options(['system' => 'System', 'excel' => 'Excel', 'group' => 'Contact Group'])
                ->default('system')
                ->disabled(fn ($context) => $context !== 'create'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('msisdn')
                    ->label('Phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('locale')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ?: '—'),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'system')),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->colors([
                        'gray' => ['pending'],
                        'info' => ['sent'],
                        'success' => ['delivered', 'read'],
                        'warning' => ['limited', 'experiment_blocked'],
                        'danger' => ['failed', 'undeliverable'],
                    ])
                    ->formatStateUsing(function (?string $state) {
                        return match ($state) {
                            'pending' => 'Pending',
                            'sent' => 'Sent',
                            'delivered' => 'Delivered',
                            'read' => 'Read',
                            'failed' => 'Failed',
                            'limited' => 'Limited',
                            'undeliverable' => 'Undeliverable',
                            'experiment_blocked' => 'Experiment Blocked',
                            default => $state ?? '—',
                        };
                    })
                    //  NEW: Show the error/delay message directly under "Pending" status
                    ->description(fn (PromotionalCampaignRecipient $record) => $record->status === 'pending' && $record->error_message
                            ? $record->error_message
                            : null
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->since()
                    ->tooltip(fn ($record) => $record->sent_at?->toDateTimeString())
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->since()
                    ->tooltip(fn ($record) => $record->delivered_at?->toDateTimeString())
                    ->sortable(),

                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read')
                    ->since()
                    ->tooltip(fn ($record) => $record->read_at?->toDateTimeString())
                    ->sortable(),

                Tables\Columns\TextColumn::make('wa_message_id')
                    ->label('WA Msg ID')
                    ->limit(24)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('wa_conversation_id')
                    ->label('Conversation')
                    ->limit(16)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('wa_pricing_model')
                    ->label('Pricing')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('wa_error_code')
                    ->label('Err Code')
                    ->badge()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('wa_error_title')
                    ->label('Err Title')
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error Message')
                    ->limit(60)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('issues')
                    ->label('Failed / Limited / Undeliverable')
                    ->query(fn (Builder $query) => $query->whereIn('status', [
                        'failed',
                        'limited',
                        'undeliverable',
                        'experiment_blocked',
                    ])),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                        'limited' => 'Limited',
                        'undeliverable' => 'Undeliverable',
                        'experiment_blocked' => 'Experiment Blocked',
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->headerActions([
                Tables\Actions\CreateAction::make(),

                //  NEW: EXPORT FAILED/PENDING LIST (Moved outside)
                Tables\Actions\Action::make('exportFailedPending')
                    ->label('Export Failed/Pending (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->action(function ($livewire) {
                        $campaign = $livewire->getOwnerRecord();
                        // Select only problem statuses + pending
                        $records = $campaign->recipients()
                            ->whereIn('status', ['pending', 'failed', 'limited', 'undeliverable', 'experiment_blocked'])
                            ->get();

                        return response()->streamDownload(function () use ($records) {
                            $handle = fopen('php://output', 'w');
                            // Add BOM for Excel utf-8 compatibility
                            fwrite($handle, "\xEF\xBB\xBF");

                            fputcsv($handle, ['Phone', 'Name', 'Status', 'Error Message', 'Updated At']);

                            foreach ($records as $record) {
                                fputcsv($handle, [
                                    $record->msisdn,
                                    $record->name,
                                    $record->status,
                                    $record->error_message,
                                    $record->updated_at,
                                ]);
                            }
                            fclose($handle);
                        }, 'campaign-issues-'.$campaign->id.'.csv');
                    }),

                // ---------------------------------------------------------
                // IMPORT FROM CONTACT GROUPS (Moved outside)
                // ---------------------------------------------------------
                Tables\Actions\Action::make('importFromGroup')
                    ->label('Import from Group')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('contact_group_ids')
                            ->label('Select Groups')
                            ->multiple()
                            ->options(ContactGroup::pluck('name', 'id'))
                            ->required()
                            ->preload()
                            ->searchable()
                            ->helperText('Contacts from these groups will be added to the campaign.'),

                        Forms\Components\Toggle::make('deduplicate')
                            ->label('Skip duplicates')
                            ->default(true)
                            ->helperText('Do not add if phone number already exists in this campaign.'),
                    ])
                    ->action(function (array $data, $livewire) {
                        $campaign = $livewire->getOwnerRecord();
                        $groupIds = $data['contact_group_ids'];
                        $dedupe = $data['deduplicate'] ?? true;

                        // 1. Get all contacts from selected groups
                        // Using cursor to handle large groups efficiently
                        $query = ContactGroup::whereIn('id', $groupIds)
                            ->with('contacts')
                            ->get()
                            ->pluck('contacts')
                            ->flatten()
                            ->unique('msisdn'); // unique by phone across selected groups

                        $count = 0;
                        $skipped = 0;

                        foreach ($query as $contact) {
                            if ($dedupe) {
                                $exists = PromotionalCampaignRecipient::where('promotional_campaign_id', $campaign->id)
                                    ->where('msisdn', $contact->msisdn)
                                    ->exists();

                                if ($exists) {
                                    $skipped++;

                                    continue;
                                }
                            }

                            // Create recipient entry
                            PromotionalCampaignRecipient::create([
                                'promotional_campaign_id' => $campaign->id,
                                'msisdn' => $contact->msisdn,
                                'name' => $contact->name,
                                'locale' => $contact->locale,
                                'source' => 'group',
                                'status' => 'pending',
                            ]);

                            $count++;
                        }

                        Notification::make()
                            ->title('Import Complete')
                            ->body("Added {$count} recipients from groups. Skipped {$skipped} duplicates.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    // ---------------------------------------------------------
                    // END NEW SECTION
                    // ---------------------------------------------------------

                    // Download sample files (routes must exist)
                    Tables\Actions\Action::make('downloadSampleCsv')
                        ->label('Download Prefilled (CSV)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->url(fn () => route('bulk-invite.sample.csv', $this->getOwnerRecord()))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('downloadSampleXlsx')
                        ->label('Download Prefilled (XLSX)')
                        ->icon('heroicon-o-document-text')
                        ->url(fn () => route('bulk-invite.sample.xlsx', $this->getOwnerRecord()))
                        ->openUrlInNewTab(),

                    // Import with validation options (GCC + Egypt)
                    Tables\Actions\Action::make('import')
                        ->label('Import from Excel/CSV')
                        ->icon('heroicon-o-arrow-up-on-square-stack')
                        ->form([
                            Forms\Components\FileUpload::make('file')
                                ->disk('public')
                                ->directory('whatsapp/imports')
                                ->acceptedFileTypes([
                                    'text/csv',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                ])
                                ->maxSize(8 * 1024)
                                ->required()
                                ->helperText('Headers supported: phone | msisdn | mobile | whatsapp (optional: name, locale).'),

                            Forms\Components\Toggle::make('has_header')
                                ->label('First row is header')
                                ->default(true),

                            Forms\Components\Select::make('preferred_region')
                                ->label('Preferred region for local numbers (no +)')
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

                            Forms\Components\Select::make('allowed_regions')
                                ->label('Allowed regions')
                                ->options([
                                    'KW' => 'Kuwait',
                                    'SA' => 'Saudi Arabia',
                                    'AE' => 'United Arab Emirates',
                                    'QA' => 'Qatar',
                                    'BH' => 'Bahrain',
                                    'OM' => 'Oman',
                                    'EG' => 'Egypt',
                                ])
                                ->default(['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'])
                                ->multiple()
                                ->native(false)
                                ->helperText('Numbers must belong to one of these regions.'),

                            Forms\Components\Toggle::make('mobile_only')
                                ->label('Accept mobile numbers only')
                                ->default(true),

                            Forms\Components\Toggle::make('dedupe')
                                ->label('De-duplicate by phone within this campaign')
                                ->default(true),
                        ])
                        ->action(function (array $data) {
                            $campaign = $this->getOwnerRecord();

                            $allowedRegions = (array) ($data['allowed_regions'] ?? ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG']);

                            dispatch(new ImportBulkInviteRecipients(
                                campaignId: $campaign->id,
                                storedFilePath: $data['file'],
                                hasHeader: (bool) ($data['has_header'] ?? true),
                                defaultRegion: (string) ($data['preferred_region'] ?? 'KW'),
                                dedupe: (bool) ($data['dedupe'] ?? true),
                                allowedRegions: $allowedRegions,
                                mobileOnly: (bool) ($data['mobile_only'] ?? true),
                            ));

                            Notification::make()
                                ->title('Import started')
                                ->body('Parsing file and adding recipients. Refresh to see updates.')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('More Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('info')
                    ->button(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    // BULK SEND ACTION
                    Tables\Actions\BulkAction::make('sendSelected')
                        ->label('Send to Selected')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send Messages')
                        ->modalDescription('Are you sure you want to queue messages for the selected recipients? Messages will only be queued for recipients with "pending", "failed", "limited", or "undeliverable" status.')
                        ->action(function (Collection $records) {
                            $count = 0;

                            foreach ($records as $record) {
                                // Only process if eligible for sending/retrying
                                if (in_array($record->status, ['pending', 'failed', 'limited', 'undeliverable', 'experiment_blocked'])) {

                                    // Reset status to pending to clear error states
                                    $record->update([
                                        'status' => 'pending',
                                        'error_message' => null,
                                    ]);

                                    dispatch(new SendPromotionalCampaignMessage($record->promotional_campaign_id, $record->id));
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk Send Queued')
                                ->body("Queued {$count} messages for sending.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
