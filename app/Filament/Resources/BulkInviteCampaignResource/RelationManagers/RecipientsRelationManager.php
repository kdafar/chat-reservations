<?php

namespace App\Filament\Resources\BulkInviteCampaignResource\RelationManagers;

use App\Jobs\ImportBulkInviteRecipients;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                ->options(['system' => 'System', 'excel' => 'Excel'])
                ->default('system')
                ->disabled(fn ($context) => $context !== 'create'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('msisdn')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('locale')->badge(),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray' => 'pending',
                    'success' => 'sent',
                    'danger' => 'failed',
                ]),
                Tables\Columns\TextColumn::make('wa_message_id')
                    ->label('WA Msg ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('error_message')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),

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

                        // Ensure arrays are arrays
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
