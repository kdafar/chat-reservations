<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WAMessage extends Model
{
    protected $table = 'wa_messages';

    protected $fillable = ['key', 'language', 'text', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
