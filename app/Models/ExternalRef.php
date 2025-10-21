<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExternalRef extends Model
{
    protected $fillable = ['source', 'entity', 'external_id', 'local_type', 'local_id'];

    public function local(): MorphTo
    {
        return $this->morphTo()->withTrashed();

    }
}
