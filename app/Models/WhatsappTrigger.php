<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Manages automated WhatsApp responses based on keywords or events.
 *
 * @property string $type (keyword, welcome, finale, fallback)
 * @property ?string $keyword The word that triggers the response
 * @property ?string $response_message_en
 * @property ?string $response_message_ar
 * @property string $response_type (text, image_url, document_url)
 * @property bool $is_active
 */
class WhatsappTrigger extends Model
{
    use LogsClinicActivity;

    use HasFactory;

    protected static function booted()
    {
        $forget = fn () => Cache::forget('whatsapp_triggers_active');
        static::created($forget);
        static::updated($forget);
        static::deleted($forget);
    }

    /**
     * The attributes that aren't mass assignable.
     * We use this for convenience as we trust our admin panel.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'response_meta' => 'array',
    ];

    /**
     * Get the appropriate localized response message.
     *
     * @param  string  $locale  ('en', 'ar', etc.)
     */
    public function getResponseMessage(string $locale): string
    {
        $message = $this->{"response_message_{$locale}"};

        // Fallback to English if the specific locale is empty
        if (empty($message)) {
            $message = $this->response_message_en;
        }

        return (string) $message;
    }
}
