<?php

namespace App\Models\Workspace;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One "please go to the room" call, as shown on the waiting-room screen. */
class QueueCall extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['called_at' => 'datetime'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
