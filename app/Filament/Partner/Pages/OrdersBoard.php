<?php

namespace App\Filament\Partner\Pages;

use App\Models\Branch;
use App\Models\CommerceOrder;
use Filament\Actions;
// 👈 fallback picker
// 👈 fallback notice
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;                                     // 👈 LocaleSwitcher
use Filament\Tables\Table;                            // 👈 read panel locale
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class OrdersBoard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $title = 'Orders';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.partner.pages.orders-board';

    /** Header actions — add a language switcher */
    protected function getHeaderActions(): array
    {
        return array_filter([
            // Optional: if you installed the Filament Translatable plugin you can keep this
            class_exists(\Filament\Actions\LocaleSwitcher::class)
                ? \Filament\Actions\LocaleSwitcher::make()
                : null,

            \Filament\Actions\Action::make('language')
                ->label(__('Language'))
                ->icon('heroicon-o-language')
                ->form([
                    \Filament\Forms\Components\Select::make('locale')
                        ->label(__('Choose language'))
                        ->options(['en' => 'English', 'ar' => 'العربية'])
                        ->required()
                        ->default(session('lang', app()->getLocale())),
                ])
                ->action(function (array $data) {
                    // 1) persist & apply NOW for this request
                    session(['lang' => $data['locale']]);
                    app()->setLocale($data['locale']);

                    // 2) IMPORTANT: redirect to the page’s GET route (avoid POST replay)
                    // EITHER:
                    return redirect()->to(static::getUrl());
                    // OR (Livewire v3 client-side nav):
                    // $this->redirect(static::getUrl(), navigate: true);
                })
                ->modalWidth('md')
                ->slideOver(),
        ]);
    }

    public function table(Table $table): Table
    {
        $partnerId = (int) session('active_partner_id');
        $locale = app()->getLocale();   // 👈 use panel locale

        return $table
            ->query(
                CommerceOrder::query()
                    ->with(['branch', 'user', 'latestPayment'])
                    ->where('partner_id', $partnerId)
            )
            ->defaultSort('id', 'desc')
            ->deferLoading()
            ->poll('15s')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->striped()
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading(__('No orders found for the selected filters.'))

            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->formatStateUsing(fn ($state, $record) => $record->branch?->name ?? '—') // Spatie uses current locale
                    ->searchable(query: function (Builder $q, string $term) use ($locale) {
                        return $q->whereHas('branch', fn (Builder $b) => $b->where("name->$locale", 'like', "%{$term}%")
                        );
                    })
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label(__('Customer'))
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('grand_total')
                    ->label(__('Total'))
                    ->money(fn ($record) => $record->currency ?? 'KWD', locale: 'en')
                    ->sortable(),

                TextColumn::make('latestPayment.status')
                    ->label(__('Payment'))
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'warning' => ['placed', 'pending'],
                        'info' => ['confirmed', 'out_for_delivery'],
                        'primary' => ['preparing'],
                        'success' => ['ready', 'delivered'],
                        'danger' => ['cancelled'],
                    ])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Placed'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->options(function () use ($partnerId, $locale) {
                        return Branch::query()
                            ->where('partner_id', $partnerId)
                            ->orderBy("name->$locale")
                            ->get(['id', 'name'])
                            ->mapWithKeys(fn (Branch $b) => [$b->id => $b->name]) // current-locale string
                            ->toArray();
                    }),

                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'placed' => 'Placed',
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'preparing' => 'Preparing',
                        'ready' => 'Ready',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('payment_status')
                    ->label(__('Payment'))
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->query(function (Builder $q, array $data) {
                        $value = $data['value'] ?? null;

                        return $value
                            ? $q->whereHas('latestPayment', fn (Builder $p) => $p->where('status', $value))
                            : $q;
                    }),

                SelectFilter::make('type')
                    ->label(__('Channel'))
                    ->options([
                        'web' => 'Web',
                        'whatsapp' => 'WhatsApp',
                        'app' => 'App',
                        'pos' => 'POS',
                    ]),

                Filter::make('date_range')
                    ->label(__('Date'))
                    ->form([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('to')->label(__('To')),
                    ])
                    ->query(function (Builder $q, array $data) {
                        return $q
                            ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->filtersFormColumns(6)
            ->filtersFormWidth('7xl')
            ->searchPlaceholder(__('Search code, name, phone…'))

            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label(__('Confirm'))
                    ->visible(fn (CommerceOrder $r) => in_array($r->status, ['placed', 'pending']))
                    ->action(fn (CommerceOrder $r) => $this->transition($r, 'confirmed'))
                    ->size(ActionSize::ExtraSmall),

                Tables\Actions\Action::make('prepare')
                    ->label(__('Prepare'))
                    ->visible(fn (CommerceOrder $r) => $r->status === 'confirmed')
                    ->action(fn (CommerceOrder $r) => $this->transition($r, 'preparing'))
                    ->size(ActionSize::ExtraSmall),

                Tables\Actions\Action::make('ready')
                    ->label(__('Ready'))
                    ->visible(fn (CommerceOrder $r) => $r->status === 'preparing')
                    ->action(fn (CommerceOrder $r) => $this->transition($r, 'ready'))
                    ->size(ActionSize::ExtraSmall),

                Tables\Actions\Action::make('out')
                    ->label(__('Out for Delivery'))
                    ->visible(fn (CommerceOrder $r) => $r->status === 'ready')
                    ->action(fn (CommerceOrder $r) => $this->transition($r, 'out_for_delivery'))
                    ->size(ActionSize::ExtraSmall),

                Tables\Actions\Action::make('delivered')
                    ->label(__('Complete'))
                    ->visible(fn (CommerceOrder $r) => in_array($r->status, ['ready', 'out_for_delivery']))
                    ->action(fn (CommerceOrder $r) => $this->transition($r, 'delivered'))
                    ->color('success')
                    ->size(ActionSize::ExtraSmall),

                Tables\Actions\Action::make('cancel')
                    ->label(__('Cancel'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (CommerceOrder $r) => in_array($r->status, ['placed', 'pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery']))
                    ->action(fn (CommerceOrder $r) => $this->transition($r, 'cancelled'))
                    ->size(ActionSize::ExtraSmall),
            ])
            ->headerActions(array_filter([
                class_exists(ExportAction::class) ? ExportAction::make()->label(__('Export')) : null,
                Tables\Actions\Action::make('refresh')
                    ->label(__('Refresh'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => null),
            ]))
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkConfirm')
                    ->label(__('Bulk: Confirm'))
                    ->action(fn ($records) => $records->each->update(['status' => 'confirmed'])),
            ]);
    }

    protected function transition(CommerceOrder $order, string $to): void
    {
        $order->update(['status' => $to]);
        $this->dispatch('notify', type: 'success', message: __('Order updated.'));
    }
}
