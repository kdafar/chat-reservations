<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    public const PURPOSE_BOOKING = 'booking';

    public const PURPOSE_LOGIN = 'login';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'meta' => 'array',
    ];
}
