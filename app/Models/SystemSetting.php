<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use LogsClinicActivity;

    protected $table = 'system_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];
}
