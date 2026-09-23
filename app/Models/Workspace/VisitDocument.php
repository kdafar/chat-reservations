<?php

namespace App\Models\Workspace;

use Illuminate\Database\Eloquent\Model;

/** A numbered invoice (per visit) or receipt (per payment) that was printed. */
class VisitDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
        'first_printed_at' => 'datetime',
        'last_printed_at' => 'datetime',
    ];
}
