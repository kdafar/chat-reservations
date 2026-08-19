<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A published before/after case for the public results gallery.
 *
 * Only rows with both `consent_on_file` and `is_published` reach the website —
 * publishing a patient's photographs without recorded consent is not a
 * judgement call the front-end should be able to make by accident.
 */
class GalleryCase extends Model
{
    use HasTranslations;
    use LogsClinicActivity;

    protected $fillable = [
        'service_id', 'branch_id', 'doctor_id',
        'title', 'summary', 'protocol',
        'before_image_url', 'after_image_url',
        'consent_on_file', 'is_published', 'sort_order',
    ];

    public $translatable = ['title', 'summary', 'protocol'];

    protected $casts = [
        'consent_on_file' => 'boolean',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** Safe to show on the public site. */
    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_published', true)->where('consent_on_file', true);
    }

    public function getLocalizedTitleAttribute(): string
    {
        return $this->getTranslation('title', app()->getLocale());
    }

    public function getLocalizedSummaryAttribute(): ?string
    {
        return $this->summary ? $this->getTranslation('summary', app()->getLocale()) : null;
    }

    public function getLocalizedProtocolAttribute(): ?string
    {
        return $this->protocol ? $this->getTranslation('protocol', app()->getLocale()) : null;
    }
}
