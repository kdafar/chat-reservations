<?php

namespace App\Models\Workspace;

use Illuminate\Database\Eloquent\Model;

/** A doctor's saved treatment set (drugs, lab tests, bill items, follow-up). */
class OrderSet extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'shared' => 'boolean',
        'drugs' => 'array',
        'lab_test_ids' => 'array',
        'items' => 'array',
        'follow_up_days' => 'integer',
        'uses' => 'integer',
    ];
}
