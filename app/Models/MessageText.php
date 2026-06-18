<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Model;

class MessageText extends Model
{
    use LogsClinicActivity;

    protected $fillable = ['key', 'locale', 'value', 'updated_by'];

    protected static function booted()
    {
        static::saved(fn () => \Cache::forget('message_texts_map'));
        static::deleted(fn () => \Cache::forget('message_texts_map'));
    }
}
