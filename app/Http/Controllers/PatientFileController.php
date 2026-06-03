<?php

namespace App\Http\Controllers;

use App\Models\PatientFile;
use App\Models\PatientFileAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientFileController extends Controller
{
    public function download(Request $request, PatientFile $patientFile)
    {
        // Permission gate.
        abort_unless(Auth::user()?->can('patient_files_view'), 403);

        if (! $patientFile->existsOnDisk()) {
            abort(404, 'File missing from disk.');
        }

        // Log access BEFORE streaming the file.
        PatientFileAccessLog::create([
            'patient_file_id' => $patientFile->id,
            'accessed_by_user_id' => Auth::id(),
            'action' => $request->query('inline') ? PatientFileAccessLog::ACTION_VIEW : PatientFileAccessLog::ACTION_DOWNLOAD,
            'accessed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $disposition = $request->query('inline') ? 'inline' : 'attachment';

        return $patientFile->disk()->download(
            $patientFile->file_path,
            $patientFile->original_filename,
            ['Content-Disposition' => $disposition.'; filename="'.addslashes($patientFile->original_filename).'"']
        );
    }
}
