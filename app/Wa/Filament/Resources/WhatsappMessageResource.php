<?php

namespace App\Wa\Filament\Resources;

use App\Wa\Filament\Resources\WhatsappMessageResource\Pages;
use App\Wa\Hub\Models\WhatsappMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid as InfoGrid;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsappMessageResource extends Resource
{
    protected static ?string $model = WhatsappMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'WhatsApp Vendor Management';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        // Read-only log
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('whatsappSession.customer_phone_number')
                    ->label('Phone')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->placeholder('N/A')
                    ->formatStateUsing(fn ($state) => self::stringifyMaybeTranslatable($state))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('direction')
                    ->colors([
                        'success' => 'incoming',
                        'info' => 'outgoing',
                    ])
                    ->icons([
                        'heroicon-o-arrow-down-circle' => 'incoming',
                        'heroicon-o-arrow-up-circle' => 'outgoing',
                    ])
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'gray' => 'read',
                        'primary' => 'text',
                        'warning' => 'interactive',
                        'success' => 'image',
                        'info' => 'template',
                        'danger' => 'error',
                    ])
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'queued',
                        'primary' => 'sent',
                        'success' => 'delivered',
                        'warning' => 'read',
                        'danger' => 'failed',
                    ])
                    ->sortable()
                    ->toggleable(),

                // Hidden by default; keep if you occasionally need it
                Tables\Columns\TextColumn::make('meta_message_id')
                    ->label('WA Msg ID')
                    ->limit(16)
                    ->tooltip(fn ($state) => $state)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Short preview; full, redacted JSON is in the View modal
                Tables\Columns\TextColumn::make('content')
                    ->label('Content')
                    ->limit(80)
                    ->wrap()
                    ->tooltip(function (WhatsappMessage $record): string {
                        $state = $record->content;

                        if (is_array($state) || is_object($state)) {
                            $clean = self::redactWamid($state);

                            return json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }

                        $str = (string) $state;
                        $decoded = json_decode($str, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $clean = self::redactWamid($decoded);

                            return json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }

                        // Plain text fallback: mask raw wamid tokens
                        return preg_replace('/wamid\.[A-Za-z0-9._=\-]+/u', '[wamid hidden]', $str) ?? $str;
                    }),
            ])
            ->filters([
                SelectFilter::make('restaurant')
                    ->relationship('restaurant', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('direction')
                    ->options([
                        'incoming' => 'Incoming',
                        'outgoing' => 'Outgoing',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalWidth('7xl')
                    ->infolist(fn (WhatsappMessage $record, Infolist $infolist) => $infolist->schema([
                        InfoSection::make('Message')
                            ->schema([
                                InfoGrid::make(4)->schema([
                                    TextEntry::make('created_at')
                                        ->label('Time')
                                        ->dateTime('Y-m-d H:i:s'),

                                    TextEntry::make('direction')
                                        ->badge()
                                        ->color(fn (string $state) => $state === 'incoming' ? 'success' : 'info'),

                                    TextEntry::make('type')
                                        ->badge()
                                        ->color('primary'),

                                    TextEntry::make('status')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'sent' => 'primary',
                                            'delivered' => 'success',
                                            'read' => 'warning',
                                            'failed' => 'danger',
                                            default => 'gray',
                                        }),

                                    TextEntry::make('whatsappSession.customer_phone_number')
                                        ->label('Phone')
                                        ->copyable(),

                                    TextEntry::make('restaurant.name')
                                        ->label('Restaurant')
                                        ->placeholder('N/A')
                                        ->formatStateUsing(fn ($state) => self::stringifyMaybeTranslatable($state)),

                                    // Hide the WA message id row completely
                                    TextEntry::make('meta_message_id')
                                        ->label('WA Message ID')
                                        ->hidden(fn () => true),
                                ]),
                            ]),

                        InfoSection::make('Payload')
                            ->collapsible()
                            ->compact()
                            ->schema([
                                // Use ->state(...) so TextEntry state is ALWAYS a string (no arrays)
                                TextEntry::make('content')
                                    ->label('Content / JSON')
                                    ->state(function (WhatsappMessage $record): string {
                                        $state = $record->content;

                                        // Arrays/objects: redact, pretty print as HTML <pre>
                                        if (is_array($state) || is_object($state)) {
                                            $clean = self::redactWamid($state);
                                            $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                                            return "<pre class='font-mono text-xs leading-5 whitespace-pre-wrap break-words break-all'>"
                                                .e($json).'</pre>';
                                        }

                                        // JSON string: decode, redact, pretty print
                                        $str = (string) $state;
                                        $decoded = json_decode($str, true);
                                        if (json_last_error() === JSON_ERROR_NONE) {
                                            $clean = self::redactWamid($decoded);
                                            $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                                            return "<pre class='font-mono text-xs leading-5 whitespace-pre-wrap break-words break-all'>"
                                                .e($json).'</pre>';
                                        }

                                        // Plain text fallback: mask raw wamid tokens
                                        $masked = preg_replace('/wamid\.[A-Za-z0-9._=\-]+/u', '[wamid hidden]', $str);

                                        return "<div class='font-mono text-xs whitespace-pre-wrap break-words break-all'>"
                                            .e($masked ?? $str).'</div>';
                                    })
                                    ->html()
                                    ->copyable()
                                    ->columnSpanFull(),
                            ]),
                    ])),
            ])
            ->bulkActions([
                // none
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsappMessages::route('/'),
        ];
    }

    // Disable the "Create" button
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Turn a possibly-translatable (array) value into a string.
     */
    protected static function stringifyMaybeTranslatable(mixed $state): string
    {
        if (is_array($state)) {
            $locale = app()->getLocale();
            if (isset($state[$locale]) && is_string($state[$locale])) {
                return $state[$locale];
            }
            $first = reset($state);

            return is_string($first) ? $first : 'N/A';
        }

        return (string) ($state ?? 'N/A');
    }

    /**
     * Recursively remove values that are WhatsApp message IDs (strings that start with "wamid.")
     * while keeping meaningful business IDs (e.g., interactive.button_reply.id = "start_new_order_flow").
     */
    protected static function redactWamid(mixed $data): mixed
    {
        if (is_string($data)) {
            return preg_replace('/wamid\.[A-Za-z0-9._=\-]+/u', '[wamid hidden]', $data);
        }

        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $vClean = self::redactWamid($v);

                // Drop only if the original value was exactly a wamid token string
                if (is_string($v) && str_starts_with($v, 'wamid.')) {
                    continue;
                }

                $out[$k] = $vClean;
            }

            return $out;
        }

        if (is_object($data)) {
            $arr = json_decode(json_encode($data), true);

            return self::redactWamid($arr);
        }

        return $data;
    }
}
