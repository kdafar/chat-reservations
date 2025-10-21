<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'city_id',
        'block_id',
        'label',
        'street',
        'building',
        'house',
        'apartment',
        'floor',
        'notes',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    // Keep single default per user + sync user's default_address_id
    protected static function booted()
    {
        static::saving(function (self $address) {
            // Ensure label fallback
            if (! $address->label) {
                $address->label = 'Home';
            }
        });

        static::saved(function (self $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)
                    ->where('id', '<>', $address->id)
                    ->update(['is_default' => false]);

                $address->user()
                    ->update(['default_address_id' => $address->id]);
            } else {
                // If user has no default, promote this one
                if (! $address->user?->default_address_id) {
                    $address->updateQuietly(['is_default' => true]);
                    $address->user()
                        ->update(['default_address_id' => $address->id]);
                }
            }
        });

        static::deleted(function (self $address) {
            if ($address->id === optional($address->user)->default_address_id) {
                $next = $address->user
                    ? $address->user->addresses()->whereNull('deleted_at')->first()
                    : null;

                $address->user?->update(['default_address_id' => $next?->id]);
                if ($next) {
                    $next->updateQuietly(['is_default' => true]);
                }
            }
        });
    }
}
