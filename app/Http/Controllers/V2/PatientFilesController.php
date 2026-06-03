<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientFile;
use App\Models\PatientFileAccessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PatientFilesController extends Controller
{
    public const CATEGORIES = [
        'lab_report', 'prescription', 'imaging', 'insurance_card',
        'consent_form', 'referral', 'discharge_summary', 'other',
    ];

    /**
     * Admin-wide browse view of every patient file. Per-patient management
     * (upload/edit/delete) still happens inside the Patient profile via the
     * existing JSON endpoints (list/store/update/destroy/accessLogs above).
     */
    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        abort_unless($request->user()?->can('patient_files_view'), 403);
        $q = trim((string) $request->input('q', ''));
        $category = $request->input('category', '');
        $patientId = $request->input('patient_id', '') !== '' ? (int) $request->input('patient_id') : null;
        $from = $request->input('from', '');
        $to = $request->input('to', '');
        $query = PatientFile::query()->with(['patient:id,name,phone', 'uploadedBy:id,name']);
        if ($q !== '') { $query->where(fn ($w) => $w->where('original_filename', 'like', "%{$q}%")->orWhere('notes', 'like', "%{$q}%")->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))); }
        if ($category !== '') { $query->where('category', $category); }
        if ($patientId) { $query->where('patient_id', $patientId); }
        if ($from) { $query->whereDate('created_at', '>=', $from); }
        if ($to) { $query->whereDate('created_at', '<=', $to); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Patient', 'Phone', 'Category', 'Filename', 'Size (bytes)', 'Uploaded by', 'Uploaded at'],
                fn ($f) => [$f->id, $f->patient?->name, $f->patient?->phone, $f->category, $f->original_filename, $f->size_bytes, $f->uploadedBy?->name, optional($f->created_at)->format('Y-m-d H:i')],
                'Patient Files',
                app()->getLocale() === 'ar',
            ),
            'patient-files-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        abort_unless(Auth::user()?->can('patient_files_view'), 403);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'category' => $request->input('category', ''),
            'patient_id' => $request->input('patient_id', '') !== '' ? (int) $request->input('patient_id') : null,
            'from' => $request->input('from', ''),
            'to' => $request->input('to', ''),
        ];

        $query = PatientFile::query()
            ->with(['patient:id,name,phone', 'visit:id,booking_code', 'uploadedBy:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('original_filename', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
            });
        }
        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }
        if ($filters['patient_id'] !== null) {
            $query->where('patient_id', $filters['patient_id']);
        }
        if ($filters['from']) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to']) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $page = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $counts = [
            'total' => PatientFile::query()->count(),
            'this_month' => PatientFile::query()->whereDate('created_at', '>=', now()->startOfMonth())->count(),
            'this_week' => PatientFile::query()->whereDate('created_at', '>=', now()->startOfWeek())->count(),
        ];

        return Inertia::render('PatientFiles/Index', [
            'filters' => $filters,
            'page' => $page,
            'categories' => self::CATEGORIES,
            'counts' => $counts,
            'can_delete' => (bool) Auth::user()?->can('patient_files_delete'),
        ]);
    }

    public function list(Request $request, Patient $patient): JsonResponse
    {
        abort_unless(Auth::user()?->can('patient_files_view'), 403);

        $category = $request->query('category');
        $hasVisit = $request->query('has_visit'); // 'yes' | 'no' | null

        $q = PatientFile::query()
            ->where('patient_id', $patient->id)
            ->with(['uploadedBy:id,name', 'visit:id'])
            ->orderByDesc('created_at');

        if ($category && in_array($category, self::CATEGORIES, true)) {
            $q->where('category', $category);
        }
        if ($hasVisit === 'yes') {
            $q->whereNotNull('visit_id');
        } elseif ($hasVisit === 'no') {
            $q->whereNull('visit_id');
        }

        return response()->json([
            'ok' => true,
            'files' => $q->get()->map(fn (PatientFile $f) => $this->transform($f))->values(),
        ]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        abort_unless(Auth::user()?->can('patient_files_upload'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $upload = $request->file('file');
        $ext = $upload->getClientOriginalExtension() ?: 'bin';
        $directory = 'patient-files/'.$patient->id;
        $fileName = Str::uuid()->toString().'.'.$ext;

        // Save to the same `local` disk used by the Filament uploader + downloader.
        $storedPath = $upload->storeAs($directory, $fileName, 'local');

        $file = PatientFile::create([
            'patient_id' => $patient->id,
            'visit_id' => $data['visit_id'] ?? null,
            'branch_id' => $patient->branch_id ?? null,
            'file_path' => $storedPath,
            'original_filename' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
            'category' => $data['category'],
            'uploaded_by_user_id' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'file' => $this->transform($file->load(['uploadedBy:id,name', 'visit:id'])),
        ]);
    }

    public function update(Request $request, PatientFile $patientFile): JsonResponse
    {
        abort_unless(Auth::user()?->can('patient_files_upload'), 403);

        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $patientFile->update($data);

        return response()->json([
            'ok' => true,
            'file' => $this->transform($patientFile->fresh()->load(['uploadedBy:id,name', 'visit:id'])),
        ]);
    }

    public function destroy(PatientFile $patientFile): JsonResponse
    {
        $user = Auth::user();
        $canDelete = $user && (
            $user->hasRole(['admin', 'super_admin', 'clinic_admin'])
            || $user->can('patient_files_delete')
        );
        abort_unless($canDelete, 403);

        $patientFile->delete(); // soft delete; observer logs the deletion

        return response()->json(['ok' => true]);
    }

    public function accessLogs(PatientFile $patientFile): JsonResponse
    {
        abort_unless(Auth::user()?->can('patient_files_view'), 403);

        $logs = PatientFileAccessLog::query()
            ->where('patient_file_id', $patientFile->id)
            ->with('accessedBy:id,name')
            ->orderByDesc('accessed_at')
            ->limit(50)
            ->get();

        return response()->json([
            'ok' => true,
            'logs' => $logs->map(fn (PatientFileAccessLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'accessed_at' => optional($l->accessed_at)->toIso8601String(),
                'actor' => $l->accessedBy?->name,
                'ip' => $l->ip_address,
            ])->values(),
        ]);
    }

    /** Stream/serve a patient file (v2 replacement for the Filament download). Logs access. */
    public function download(Request $request, PatientFile $patientFile)
    {
        abort_unless(Auth::user()?->can('patient_files_view'), 403);

        if (! $patientFile->existsOnDisk()) {
            abort(404, 'File missing from disk.');
        }

        $inline = (bool) $request->query('inline');
        PatientFileAccessLog::create([
            'patient_file_id' => $patientFile->id,
            'accessed_by_user_id' => Auth::id(),
            'action' => $inline ? PatientFileAccessLog::ACTION_VIEW : PatientFileAccessLog::ACTION_DOWNLOAD,
            'accessed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $disposition = $inline ? 'inline' : 'attachment';

        return $patientFile->disk()->download(
            $patientFile->file_path,
            $patientFile->original_filename,
            ['Content-Disposition' => $disposition.'; filename="'.addslashes($patientFile->original_filename).'"']
        );
    }

    protected function transform(PatientFile $f): array
    {
        return [
            'id' => $f->id,
            'patient_id' => $f->patient_id,
            'visit_id' => $f->visit_id,
            'category' => $f->category,
            'original_filename' => $f->original_filename,
            'mime_type' => $f->mime_type,
            'size_bytes' => (int) $f->size_bytes,
            'display_size' => $f->display_size,
            'notes' => $f->notes,
            'uploaded_by' => $f->uploadedBy?->name,
            'created_at' => optional($f->created_at)->toIso8601String(),
            'download_url' => route('v2.api.patient-files.download', ['patientFile' => $f->id]),
            'view_url' => route('v2.api.patient-files.download', ['patientFile' => $f->id, 'inline' => 1]),
            'is_image' => str_starts_with((string) $f->mime_type, 'image/'),
            'is_pdf' => $f->mime_type === 'application/pdf',
        ];
    }
}
