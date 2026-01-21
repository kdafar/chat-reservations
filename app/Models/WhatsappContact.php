<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappContact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'opt_in' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public static function digitsOnly(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    public static function findByMsisdn(string $msisdn): ?self
    {
        return static::where('msisdn', static::digitsOnly($msisdn))->first();
    }

    public static function upsertContact(array $attrs): self
    {
        $msisdn = static::digitsOnly($attrs['msisdn'] ?? '');
        if ($msisdn === '') {
            throw new \InvalidArgumentException('msisdn is required');
        }

        /** @var self $c */
        $c = static::firstOrNew(['msisdn' => $msisdn]);
        if (isset($attrs['name']) && $attrs['name'] !== null) {
            $c->name = $attrs['name'];
        }
        if (isset($attrs['email']) && $attrs['email'] !== null) {
            $c->email = $attrs['email'];
        }
        if (isset($attrs['locale']) && $attrs['locale'] !== null) {
            $c->locale = $attrs['locale'];
        }
        if (isset($attrs['opt_in'])) {
            $c->opt_in = (bool) $attrs['opt_in'];
        }
        $c->last_seen_at = now();
        $c->save();

        return $c;
    }
}
