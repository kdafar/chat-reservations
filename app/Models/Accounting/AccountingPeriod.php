<?php

namespace App\Models\Accounting;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use LogsClinicActivity;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'code', 'start_date', 'end_date', 'status',
        'closed_at', 'closed_by_user_id', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Get or create the month-period that contains the given date.
     * All journal entries auto-attach to one of these.
     */
    public static function forDate(Carbon|string $date): self
    {
        $d = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $start = $d->copy()->startOfMonth();
        $end = $d->copy()->endOfMonth();
        $code = $start->format('Y-m');

        return self::firstOrCreate(
            ['code' => $code],
            [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => self::STATUS_OPEN,
            ]
        );
    }

    public function close(?int $userId = null): \App\Models\Accounting\JournalEntry
    {
        return app(\App\Services\Accounting\PeriodCloseService::class)->close($this, $userId);
    }

    public function reopen(?int $userId = null): void
    {
        app(\App\Services\Accounting\PeriodCloseService::class)->reopen($this, $userId);
    }

    public function closingEntry(): ?\App\Models\Accounting\JournalEntry
    {
        return \App\Models\Accounting\JournalEntry::query()
            ->where('source_type', self::class)
            ->where('source_id', $this->id)
            ->where('status', \App\Models\Accounting\JournalEntry::STATUS_POSTED)
            ->latest('id')
            ->first();
    }
}
