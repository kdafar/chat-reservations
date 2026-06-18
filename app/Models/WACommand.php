<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Model;

class WACommand extends Model
{
    use LogsClinicActivity;

    protected $table = 'wa_commands';

    protected $fillable = ['keyword', 'language', 'action', 'params', 'priority', 'enabled'];

    protected $casts = ['params' => 'array', 'enabled' => 'boolean'];
}
