<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = BookingResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_bookings.what.heading'), 'body' => __('help.pages.list_bookings.what.body')],
            ['heading' => __('help.pages.list_bookings.how.heading'), 'items' => (array) trans('help.pages.list_bookings.how.items')],
            ['heading' => __('help.pages.list_bookings.faq.heading'), 'items' => (array) trans('help.pages.list_bookings.faq.items')],
        ];
    }

    protected function getTableQuery(): Builder
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $q = BookingResource::getEloquentQuery();
        $filters = $this->tableFilters ?? [];

        $now = \Carbon\Carbon::now($tz);

        // no_show (toggle filter) - if active, force confirmed and apply no-show logic
        $noShowActive = data_get($filters, 'no_show.isActive');
        $noShowActive = ($noShowActive === true || $noShowActive === 'true' || $noShowActive === 1 || $noShowActive === '1');

        if ($noShowActive) {
            $q->where('status', BookingResource::STATUS_CONFIRMED)
                ->whereNull('checked_in_at')
                ->whereNotNull('res_end')
                ->where('res_end', '<', $now);
        } else {
            // status (multiple)
            $statusValues = data_get($filters, 'status.values', []);
            if (is_array($statusValues) && count($statusValues)) {
                $q->whereIn('status', $statusValues);
            } else {
                // keep your current default behavior
                $q->whereIn('status', [BookingResource::STATUS_CONFIRMED]);
            }
        }

        // when (single)
        $preset = data_get($filters, 'when.value');
        if ($preset) {
            match ($preset) {
                'today' => $q->whereDate('res_date', $now->toDateString()),
                'tomorrow' => $q->whereDate('res_date', $now->copy()->addDay()->toDateString()),
                'week' => $q->whereBetween('res_date', [
                    $now->copy()->startOfWeek()->toDateString(),
                    $now->copy()->endOfWeek()->toDateString(),
                ]),
                'past' => $q->whereDate('res_date', '<', $now->toDateString()),
                default => null,
            };
        }

        // time_of_day (single)
        $slot = data_get($filters, 'time_of_day.value');
        if ($slot) {
            match ($slot) {
                'morning' => $q->whereRaw('res_time >= ? AND res_time < ?', ['08:00:00', '12:00:00']),
                'afternoon' => $q->whereRaw('res_time >= ? AND res_time < ?', ['12:00:00', '17:00:00']),
                'evening' => $q->whereRaw('res_time >= ? AND res_time <= ?', ['17:00:00', '23:00:00']),
                default => null,
            };
        }

        return $q;
    }
}
