<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WAMessageLog extends Model
{
    protected $table = 'wa_message_logs';

    protected $fillable = ['wa_message_id', 'phone', 'payload', 'status'];

    protected $casts = ['payload' => 'array'];
}
