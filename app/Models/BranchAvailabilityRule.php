<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class BranchAvailabilityRule extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    public const DOW = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

    protected $fillable = [
        'branch_id', 'day_of_week',
        'is_open', 'open_at', 'close_at',
        'slot_length_minutes', 'slot_step_minutes', 'max_party_size',
        'capacity_map', 'lead_time_minutes', 'ui_party_images', 'ui_time_image',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'capacity_map' => 'array',
        'ui_party_images' => 'array',
        'ui_time_image' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function capacityForSize(int $size, int $default = 1): int
    {
        $map = $this->capacity_map ?? [];

        return isset($map[(string) $size]) ? (int) $map[(string) $size] : $default;
    }

    public function durationMinutes(): int
    {
        return (int) $this->slot_length_minutes;
    }

    public function stepMinutes(): int
    {
        return (int) $this->slot_step_minutes;
    }

    public function maxPartySize(): int
    {
        return (int) $this->max_party_size;
    }

    /**
     * Given a date (start of day in app TZ), return the open/close window as Carbon instances.
     * Handles overnight close (e.g., open 16:00, close 01:00 -> next day).
     */
    public function windowForDate(Carbon $date): array
    {
        $tz = $date->getTimezone();
        $open = Carbon::parse($date->toDateString().' '.$this->open_at, $tz);
        $close = Carbon::parse($date->toDateString().' '.$this->close_at, $tz);
        if ($close->lessThanOrEqualTo($open)) {
            $close->addDay();
        }

        return [$open, $close];
    }
}
