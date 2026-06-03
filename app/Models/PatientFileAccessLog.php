<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientFileAccessLog extends Model
{
    public const ACTION_VIEW = 'view';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_DELETE = 'delete';
    public const ACTION_UPLOAD = 'upload';

    protected $fillable = [
        'patient_file_id', 'accessed_by_user_id', 'action',
        'accessed_at', 'ip_address', 'user_agent', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'accessed_at' => 'datetime',
    ];

    public function patientFile(): BelongsTo
    {
        return $this->belongsTo(PatientFile::class);
    }

    public function accessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accessed_by_user_id');
    }
}
