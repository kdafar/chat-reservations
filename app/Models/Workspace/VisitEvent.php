<?php

namespace App\Models\Workspace;

use Illuminate\Database\Eloquent\Model;

/** One line of a visit's timeline (called, vitals taken, tests ordered…). */
class VisitEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['at' => 'datetime'];
}
