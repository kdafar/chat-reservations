<?php

namespace App\Observers;

use App\Models\PatientFile;
use App\Models\PatientFileAccessLog;
use Illuminate\Support\Facades\Auth;

class PatientFileObserver
{
    public function created(PatientFile $file): void
    {
        PatientFileAccessLog::create([
            'patient_file_id' => $file->id,
            'accessed_by_user_id' => $file->uploaded_by_user_id ?? Auth::id(),
            'action' => PatientFileAccessLog::ACTION_UPLOAD,
            'accessed_at' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) (request()?->userAgent() ?? ''), 0, 500),
        ]);
    }

    public function deleting(PatientFile $file): void
    {
        // Log the soft delete; the file blob is preserved until force-delete or a cleanup job.
        PatientFileAccessLog::create([
            'patient_file_id' => $file->id,
            'accessed_by_user_id' => Auth::id(),
            'action' => PatientFileAccessLog::ACTION_DELETE,
            'accessed_at' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) (request()?->userAgent() ?? ''), 0, 500),
        ]);
    }

    public function forceDeleted(PatientFile $file): void
    {
        // Permanently remove the blob.
        if ($file->disk()->exists($file->file_path)) {
            $file->disk()->delete($file->file_path);
        }
    }
}
