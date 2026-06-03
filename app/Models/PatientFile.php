<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PatientFile extends Model
{
    use BelongsToBranchScope;
    use SoftDeletes;

    public const CATEGORY_LAB_REPORT = 'lab_report';
    public const CATEGORY_PRESCRIPTION = 'prescription';
    public const CATEGORY_IMAGING = 'imaging';
    public const CATEGORY_INSURANCE_CARD = 'insurance_card';
    public const CATEGORY_CONSENT_FORM = 'consent_form';
    public const CATEGORY_REFERRAL = 'referral';
    public const CATEGORY_DISCHARGE_SUMMARY = 'discharge_summary';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_LAB_REPORT,
        self::CATEGORY_PRESCRIPTION,
        self::CATEGORY_IMAGING,
        self::CATEGORY_INSURANCE_CARD,
        self::CATEGORY_CONSENT_FORM,
        self::CATEGORY_REFERRAL,
        self::CATEGORY_DISCHARGE_SUMMARY,
        self::CATEGORY_OTHER,
    ];

    protected $fillable = [
        'patient_id', 'visit_id', 'branch_id',
        'file_path', 'original_filename', 'mime_type', 'size_bytes',
        'category', 'uploaded_by_user_id', 'notes', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'size_bytes' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(PatientFileAccessLog::class)->orderBy('accessed_at', 'desc');
    }

    /** Returns the storage disk handle (configured via config/filesystems.php 'local' disk by default). */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('local');
    }

    /** True if the file still exists on disk. */
    public function existsOnDisk(): bool
    {
        return $this->disk()->exists($this->file_path);
    }

    /** Human-readable size: 1.2 MB / 480 KB. */
    public function getDisplaySizeAttribute(): string
    {
        $b = (int) $this->size_bytes;
        if ($b < 1024) {
            return $b.' B';
        }
        if ($b < 1024 * 1024) {
            return number_format($b / 1024, 1).' KB';
        }

        return number_format($b / (1024 * 1024), 2).' MB';
    }
}
