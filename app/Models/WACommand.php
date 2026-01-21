<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WACommand extends Model
{
    protected $table = 'wa_commands';

    protected $fillable = ['keyword', 'language', 'action', 'params', 'priority', 'enabled'];

    protected $casts = ['params' => 'array', 'enabled' => 'boolean'];
}
