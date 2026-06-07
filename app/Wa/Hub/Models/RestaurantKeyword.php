<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantKeyword extends Model
{
    protected $connection = 'wa';
    protected $table = 'restaurant_keywords';

    protected $fillable = [
        'vendor_id',
        'locale',
        'keyword',
        'raw',
    ];

    public function restaurant(): BelongsTo
    {
        // Now this works perfectly
        return $this->belongsTo(Vendors::class, 'vendor_id');
    }

    public function vendor(): BelongsTo
    {
        // Now this works perfectly
        return $this->belongsTo(Vendors::class, 'vendor_id');
    }

    /** Normalize once, at save time */
    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        // simple Arabic normalization
        $s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
        $s = str_replace(['ى'], 'ي', $s);
        $s = str_replace(['ة'], 'ه', $s);
        $s = preg_replace('/\p{Mn}+/u', '', $s); // strip diacritics
        $s = preg_replace('/\s+/u', ' ', $s);

        return $s;
    }

    public function setKeywordAttribute($value): void
    {
        $this->attributes['keyword'] = static::normalize((string) $value);
    }
}
