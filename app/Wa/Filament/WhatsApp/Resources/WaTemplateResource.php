<?php

namespace App\Wa\Filament\WhatsApp\Resources;

// ADD THIS IMPORT
use App\Wa\Filament\WhatsApp\Pages\SendTemplateMessage;
use App\Wa\Filament\WhatsApp\Resources\WaTemplateResource\Pages;
use App\Wa\Models\WhatsApp\WaNumber;
use App\Wa\Models\WhatsApp\WaTemplate;
use App\Wa\Services\WhatsApp\Tenant\TenantWhatsAppService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // 👈 NEW

class WaTemplateResource extends Resource
{
    protected static ?string $model = WaTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 12;

    /**
     * Limit visible templates:
     * - Admin: all
     * - Others: only templates whose account.owner_user_id = auth()->id()
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0'); // no user => no access
        }

        $isAdmin = method_exists($user, 'hasRole')
            ? $user->hasRole('admin')
            : ($user->is_admin ?? false);

        if (! $isAdmin) {
            $query->whereHas('account', function (Builder $q) use ($user) {
                $q->where('owner_user_id', $user->id);
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Template')
                ->schema([
                    Forms\Components\Select::make('wa_account_id')
                        ->relationship(
                            name: 'account',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (Builder $query) {
                                $user = auth()->user();

                                if (! $user) {
                                    $query->whereRaw('1 = 0');

                                    return;
                                }

                                $isAdmin = method_exists($user, 'hasRole')
                                    ? $user->hasRole('admin')
                                    : ($user->is_admin ?? false);

                                if (! $isAdmin) {
                                    $query->where('owner_user_id', $user->id);
                                }
                            }
                        )
                        ->label('Account')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('language')
                        ->label('Language')
                        ->placeholder('en or en_US')
                        ->required(),

                    Forms\Components\TextInput::make('category')
                        ->label('Category')
                        ->placeholder('UTILITY / MARKETING / AUTHENTICATION'),

                    Forms\Components\TextInput::make('status')
                        ->label('Status')
                        ->placeholder('APPROVED / PENDING / REJECTED'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Body')
                ->schema([
                    Forms\Components\Textarea::make('components')
                        ->label('Components (JSON, read-only)')
                        ->json()
                        ->disabled()
                        ->dehydrated(false)
                        ->rows(10)
                        ->helperText('Synced from Meta. Use the Sync action to refresh.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Template')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('language')
                    ->label('Lang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'APPROVED',
                        'warning' => 'PENDING',
                        'danger' => 'REJECTED',
                    ]),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('Y-m-d H:i')
                    ->label('Updated')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'APPROVED' => 'Approved',
                        'PENDING' => 'Pending',
                        'REJECTED' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('sendTest')
                    ->label('Send Test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->url(function (WaTemplate $record): string {
                        $templateKey = $record->name.'|'.$record->language;
                        $baseUrl = SendTemplateMessage::getNavigationUrl();

                        return $baseUrl.'?template='.urlencode($templateKey);
                    })
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('syncFromMeta')
                    ->label('Sync from Meta')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('wa_number_id')
                            ->label('From Number')
                            ->helperText('We will use this number\'s credentials/WABA to fetch templates.')
                            ->options(function () {
                                $user = auth()->user();

                                if (! $user) {
                                    return [];
                                }

                                $isAdmin = method_exists($user, 'hasRole')
                                    ? $user->hasRole('admin')
                                    : ($user->is_admin ?? false);

                                $query = WaNumber::query()
                                    ->where('status', 'connected');

                                if (! $isAdmin) {
                                    // Only numbers whose account belongs to this user
                                    $query->whereHas('account', function (Builder $q) use ($user) {
                                        $q->where('owner_user_id', $user->id);
                                    });
                                }

                                return $query->pluck('display_phone_number', 'id');
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status filter')
                            ->options([
                                'APPROVED' => 'Approved only',
                                'PENDING' => 'Pending only',
                                'REJECTED' => 'Rejected only',
                                'ALL' => 'All statuses',
                            ])
                            ->default('APPROVED')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $number = WaNumber::find($data['wa_number_id'] ?? null);
                        if (! $number) {
                            Notification::make()->title('Number not found')->danger()->send();

                            return;
                        }

                        $statusFilter = $data['status'] ?? 'APPROVED';
                        $status = $statusFilter === 'ALL' ? null : $statusFilter;

                        try {
                            /** @var TenantWhatsAppService $svc */
                            $svc = app(TenantWhatsAppService::class)->forNumber($number);
                            $templates = $svc->listTemplates($status);

                            $count = 0;
                            foreach ($templates as $tpl) {
                                $name = $tpl['name'] ?? null;
                                $language = $tpl['language'] ?? null;
                                if (! $name || ! $language) {
                                    continue;
                                }

                                WaTemplate::updateOrCreate(
                                    [
                                        'wa_account_id' => $number->wa_account_id,
                                        'name' => $name,
                                        'language' => $language,
                                    ],
                                    [
                                        'category' => $tpl['category'] ?? null,
                                        'status' => $tpl['status'] ?? null,
                                        'components' => $tpl['components'] ?? null,
                                        'meta_raw' => $tpl,
                                    ]
                                );
                                $count++;
                            }

                            Notification::make()
                                ->title('Templates synced')
                                ->body("Synced {$count} templates from Meta.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\CreateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaTemplates::route('/'),
            'create' => Pages\CreateWaTemplate::route('/create'),
            'edit' => Pages\EditWaTemplate::route('/{record}/edit'),
        ];
    }
}
