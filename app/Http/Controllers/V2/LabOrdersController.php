<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Lab\LabOrder;
use App\Models\Lab\LabOrderItem;
use App\Models\Lab\LabTest;
use App\Models\PatientFile;
use App\Models\PatientFileAccessLog;
use App\Models\Visit;
use App\Services\Clinic\LabOrderService;
use App\Services\HtmlToPdfService;
use App\Support\ResolvesAccessibleClinics;
use App\Support\VisitAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The lab assistant's screen — and the doctor's way back into it.
 *
 * Two surfaces:
 *   index()  the worklist: every open order in the branch, oldest first, urgent
 *            on top, plus a "released today" tab. This is what a lab assistant
 *            leaves open all day.
 *   show()   one order: patient header, the tests to run, result entry, report
 *            attachments, and the release/print/send actions.
 *
 * Visibility mirrors the rest of the clinic: admins and lab/reception staff see
 * the whole branch queue; a doctor only sees orders they raised (their own
 * patients), which is how the "results ready" loop closes without exposing
 * other doctors' patients.
 */
class LabOrdersController extends Controller
{
    use ResolvesAccessibleClinics;
    use VisitAuthorization;

    /** Anyone who can see lab orders at all can reach the worklist. */
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_lab_orders')) {
            abort(403, 'Not authorized to view lab orders.');
        }
    }

    /**
     * Can the current user do bench work — collect samples, type results,
     * release reports? Lab staff, nurses and admins. A doctor may order and
     * review but never enters results (that's the whole point of the split).
     */
    protected function isLabUser(): bool
    {
        $u = auth()->user();
        if (! $u) {
            return false;
        }
        if ($this->isAdminUser()) {
            return true;
        }

        return (bool) $u->can('update_lab_orders')
            && (bool) $u->hasAnyRole(['clinic_lab', 'clinic_nurse', 'clinic_reception']);
    }

    protected function authorizeLabWork(Request $request): void
    {
        if (! $this->isLabUser()) {
            abort(403, 'Only lab staff can update results on a lab order.');
        }
    }

    // ---------------------------------------------------------------- worklist

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'tab' => in_array($request->input('tab'), ['open', 'ordered', 'in_progress', 'completed', 'all'], true)
                ? $request->input('tab')
                : 'open',
            'priority' => $request->input('priority', '') === 'urgent' ? 'urgent' : '',
            'doctor_id' => $request->input('doctor_id', '') !== '' ? (int) $request->input('doctor_id') : null,
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
        ];

        $query = $this->scopedQuery()
            ->with(['patient:id,name,phone,dob,gender', 'doctor:id,name', 'branch:id,name', 'items.labTest:id,code,name,specimen_type']);

        match ($filters['tab']) {
            'ordered' => $query->where('status', LabOrder::STATUS_ORDERED),
            'in_progress' => $query->whereIn('status', [LabOrder::STATUS_SAMPLE_COLLECTED, LabOrder::STATUS_IN_PROGRESS]),
            'completed' => $query->where('status', LabOrder::STATUS_COMPLETED),
            'all' => $query,
            default => $query->open(),
        };

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($w) use ($q) {
                $w->where('order_code', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
                    ->orWhereHas('items.labTest', fn ($t) => $t->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
            });
        }
        if ($filters['priority'] === 'urgent') {
            $query->where('priority', LabOrder::PRIORITY_URGENT);
        }
        if ($filters['doctor_id']) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if ($filters['from'] !== '') {
            $query->whereDate('ordered_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('ordered_at', '<=', $filters['to']);
        }

        // Urgent first, then oldest — a lab queue is a FIFO with a fast lane.
        // The completed tab flips to newest-first (you want today's releases).
        $page = $filters['tab'] === 'completed'
            ? $query->orderByDesc('completed_at')->paginate(25)->withQueryString()
            : $query->orderByRaw("FIELD(priority, 'urgent') DESC")
                ->orderBy('ordered_at')
                ->paginate(25)->withQueryString();

        $page->through(fn (LabOrder $o) => $this->transform($o));

        return Inertia::render('LabOrders/Index', [
            'filters' => $filters,
            'page' => $page,
            'counts' => $this->counts(),
            'doctor_options' => Doctor::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all(),
            'can' => $this->abilities(),
        ]);
    }

    /** Tab badge numbers, all in one pass over the scoped queue. */
    protected function counts(): array
    {
        $rows = $this->scopedQuery()
            ->selectRaw('status, priority, COUNT(*) as c')
            ->groupBy('status', 'priority')
            ->get();

        $byStatus = fn (array $statuses) => (int) $rows
            ->whereIn('status', $statuses)->sum('c');

        return [
            'open' => $byStatus(LabOrder::OPEN_STATUSES),
            'ordered' => $byStatus([LabOrder::STATUS_ORDERED]),
            'in_progress' => $byStatus([LabOrder::STATUS_SAMPLE_COLLECTED, LabOrder::STATUS_IN_PROGRESS]),
            'urgent_open' => (int) $rows
                ->whereIn('status', LabOrder::OPEN_STATUSES)
                ->where('priority', LabOrder::PRIORITY_URGENT)
                ->sum('c'),
            // "Completed" as a tab means everything ever released, but the badge
            // people care about is today's — releases they may still need to send.
            'completed_today' => (int) $this->scopedQuery()
                ->where('status', LabOrder::STATUS_COMPLETED)
                ->whereDate('completed_at', today())
                ->count(),
            'awaiting_review' => (int) $this->scopedQuery()
                ->where('status', LabOrder::STATUS_COMPLETED)
                ->whereNull('reviewed_at')
                ->count(),
        ];
    }

    // ------------------------------------------------------------------ detail

    public function show(Request $request, LabOrder $labOrder): Response
    {
        $this->authorizeAccess($request);
        $this->authorizeSee($labOrder);

        $labOrder->load([
            'patient', 'doctor:id,name', 'branch:id,name', 'visit:id,booking_code,status,diagnosis,chief_complaint',
            'items.labTest', 'items.completedBy:id,name',
            'orderedBy:id,name', 'sampleCollectedBy:id,name', 'completedBy:id,name', 'reviewedBy:id,name',
            'attachments.uploadedBy:id,name',
        ]);

        return Inertia::render('LabOrders/Show', [
            'order' => $this->transform($labOrder, true),
            'can' => $this->abilities($labOrder),
            // Catalog for the "add another test" picker on an open order.
            'catalog' => $this->catalogFor($labOrder->branch_id),
            'wa_enabled' => (bool) config('clinic.lab.send_report_whatsapp'),
            'pdf_available' => app(HtmlToPdfService::class)->available(),
        ]);
    }

    /** Search the branch's catalog — used by the order picker on both surfaces. */
    public function catalog(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $branchId = $request->query('branch_id') !== null ? (int) $request->query('branch_id') : null;

        return response()->json(['tests' => $this->catalogFor($branchId, (string) $request->query('q', ''))]);
    }

    protected function catalogFor(?int $branchId, string $q = ''): array
    {
        return LabTest::query()
            ->where('is_active', true)
            ->when($branchId, fn ($w) => $w->where(
                fn ($w2) => $w2->where('branch_id', $branchId)->orWhereNull('branch_id')
            ))
            ->when(trim($q) !== '', fn ($w) => $w->where(
                fn ($w2) => $w2->where('name', 'like', '%'.trim($q).'%')->orWhere('code', 'like', '%'.trim($q).'%')
            ))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'code', 'name', 'specimen_type', 'unit', 'reference_range', 'default_price'])
            ->map(fn (LabTest $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'specimen' => $t->specimen_type,
                'unit' => $t->unit,
                'reference_range' => $t->reference_range,
                'price' => (float) $t->default_price,
            ])->all();
    }

    // ----------------------------------------------------------- doctor: order

    /**
     * Doctor (or admin/reception on their behalf) raises an order on a visit.
     * Called from the visit console / visit sheet lab panel.
     */
    public function store(Request $request, Visit $visit): JsonResponse
    {
        if (! $this->canOperateVisit($visit) && ! $this->isReceptionUser()) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to order tests on this visit.'], 403);
        }
        if (! $request->user()?->can('create_lab_orders')) {
            return response()->json(['ok' => false, 'error' => 'You do not have permission to order lab tests.'], 403);
        }

        $data = $request->validate([
            'test_ids' => 'required|array|min:1',
            'test_ids.*' => 'integer|exists:lab_tests,id',
            'priority' => 'nullable|in:routine,urgent',
            'clinical_note' => 'nullable|string|max:2000',
        ]);

        try {
            $order = app(LabOrderService::class)->order(
                $visit,
                $data['test_ids'],
                $data['priority'] ?? LabOrder::PRIORITY_ROUTINE,
                $data['clinical_note'] ?? null,
                (int) (auth()->id() ?? 0),
                (bool) config('clinic.lab.bill_ordered_tests', true),
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'order' => $this->transform($order->load(['items.labTest', 'patient:id,name', 'doctor:id,name']), true),
        ]);
    }

    /** Every lab order on a visit — powers the doctor-side lab panel. */
    public function forVisit(Request $request, Visit $visit): JsonResponse
    {
        if (! $request->user()?->can('view_any_lab_orders')) {
            return response()->json(['ok' => false, 'error' => 'Not authorized.'], 403);
        }

        $orders = LabOrder::query()
            ->where('visit_id', $visit->id)
            ->with(['items.labTest:id,code,name,specimen_type', 'doctor:id,name', 'attachments'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (LabOrder $o) => $this->transform($o, true));

        return response()->json([
            'ok' => true,
            'orders' => $orders,
            'catalog' => $this->catalogFor($visit->branch_id),
            'can_order' => (bool) $request->user()?->can('create_lab_orders')
                && ($this->canOperateVisit($visit) || $this->isReceptionUser()),
        ]);
    }

    /**
     * Patient-level lab history — every released report for this patient, so a
     * doctor can compare today's result against the last one without hunting
     * through old visits.
     */
    public function forPatient(Request $request, \App\Models\Patient $patient): JsonResponse
    {
        if (! $request->user()?->can('view_any_lab_orders')) {
            return response()->json(['ok' => false, 'error' => 'Not authorized.'], 403);
        }

        $orders = $this->scopedQuery()
            ->where('patient_id', $patient->id)
            ->with(['items.labTest:id,code,name', 'doctor:id,name'])
            ->orderByDesc('ordered_at')
            ->limit(30)
            ->get()
            ->map(fn (LabOrder $o) => $this->transform($o, true));

        return response()->json(['ok' => true, 'orders' => $orders]);
    }

    // -------------------------------------------------------------- lab: bench

    public function addTests(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeSee($labOrder);
        if (! $this->isLabUser() && ! $this->canReview($labOrder)) {
            return response()->json(['ok' => false, 'error' => 'Not authorized to change this order.'], 403);
        }

        $data = $request->validate([
            'test_ids' => 'required|array|min:1',
            'test_ids.*' => 'integer|exists:lab_tests,id',
        ]);

        return $this->run(fn () => app(LabOrderService::class)->addTests(
            $labOrder, $data['test_ids'], (int) (auth()->id() ?? 0),
            (bool) config('clinic.lab.bill_ordered_tests', true),
        ));
    }

    public function collectSample(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeLabWork($request);
        $this->authorizeSee($labOrder);

        return $this->run(fn () => app(LabOrderService::class)->collectSample($labOrder, (int) (auth()->id() ?? 0)));
    }

    public function start(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeLabWork($request);
        $this->authorizeSee($labOrder);

        return $this->run(fn () => app(LabOrderService::class)->start($labOrder, (int) (auth()->id() ?? 0)));
    }

    public function saveResults(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeLabWork($request);
        $this->authorizeSee($labOrder);

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.result_value' => 'nullable|string|max:191',
            'items.*.result_unit' => 'nullable|string|max:32',
            'items.*.flag' => 'nullable|in:normal,low,high,critical',
            'items.*.notes' => 'nullable|string|max:2000',
            'lab_note' => 'nullable|string|max:2000',
        ]);

        $rows = [];
        foreach ($data['items'] as $row) {
            $rows[(int) $row['id']] = $row;
        }

        return $this->run(function () use ($labOrder, $rows, $data) {
            $svc = app(LabOrderService::class);
            $order = $svc->saveResults($labOrder, $rows, (int) (auth()->id() ?? 0));
            if (array_key_exists('lab_note', $data)) {
                $order->forceFill(['lab_note' => trim((string) $data['lab_note']) ?: null])->save();
            }

            return $order;
        });
    }

    public function complete(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeLabWork($request);
        $this->authorizeSee($labOrder);

        $data = $request->validate(['lab_note' => 'nullable|string|max:2000']);

        return $this->run(fn () => app(LabOrderService::class)->complete(
            $labOrder, $data['lab_note'] ?? null, (int) (auth()->id() ?? 0)
        ));
    }

    public function cancel(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeSee($labOrder);
        if (! $this->isLabUser() && ! $this->canReview($labOrder)) {
            return response()->json(['ok' => false, 'error' => 'Not authorized to cancel this order.'], 403);
        }

        $data = $request->validate(['reason' => 'nullable|string|max:255']);

        return $this->run(fn () => app(LabOrderService::class)->cancel(
            $labOrder, $data['reason'] ?? null, (int) (auth()->id() ?? 0)
        ));
    }

    public function removeItem(Request $request, LabOrderItem $labOrderItem): JsonResponse
    {
        $order = $labOrderItem->labOrder;
        abort_unless($order !== null, 404);
        $this->authorizeSee($order);
        if (! $this->isLabUser() && ! $this->canReview($order)) {
            return response()->json(['ok' => false, 'error' => 'Not authorized.'], 403);
        }

        return $this->run(function () use ($labOrderItem, $order) {
            app(LabOrderService::class)->removeItem($labOrderItem);

            return $order->fresh(['items.labTest']);
        });
    }

    // --------------------------------------------------------- doctor: sign-off

    public function review(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeSee($labOrder);
        if (! $this->canReview($labOrder)) {
            return response()->json(['ok' => false, 'error' => 'Only the ordering doctor (or an admin) can sign off on a report.'], 403);
        }

        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        return $this->run(fn () => app(LabOrderService::class)->review(
            $labOrder, $data['note'] ?? null, (int) (auth()->id() ?? 0)
        ));
    }

    // ------------------------------------------------------------- attachments

    /**
     * Attach the analyser printout / scanned report (PDF or image). Stored as a
     * PatientFile so it inherits PHI access logging and shows up on the
     * patient's file timeline, linked back to this order.
     */
    public function uploadAttachment(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeSee($labOrder);
        if (! $this->isLabUser()) {
            return response()->json(['ok' => false, 'error' => 'Only lab staff can attach reports.'], 403);
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $upload = $request->file('file');
        $patientId = (int) ($labOrder->patient_id ?? 0);
        if ($patientId <= 0) {
            return response()->json(['ok' => false, 'error' => 'This order has no patient on file.'], 422);
        }

        $ext = $upload->getClientOriginalExtension() ?: 'bin';
        $storedPath = $upload->storeAs(
            'patient-files/'.$patientId,
            Str::uuid()->toString().'.'.$ext,
            'local'
        );

        $file = PatientFile::create([
            'patient_id' => $patientId,
            'visit_id' => $labOrder->visit_id,
            'lab_order_id' => $labOrder->id,
            'branch_id' => $labOrder->branch_id,
            'file_path' => $storedPath,
            'original_filename' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
            'category' => PatientFile::CATEGORY_LAB_REPORT,
            'uploaded_by_user_id' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'attachment' => $this->transformAttachment($file->load('uploadedBy:id,name')),
            'order' => $this->transform($labOrder->fresh(['items.labTest', 'attachments.uploadedBy:id,name']), true),
        ]);
    }

    public function deleteAttachment(Request $request, LabOrder $labOrder, PatientFile $patientFile): JsonResponse
    {
        $this->authorizeSee($labOrder);
        if (! $this->isLabUser()) {
            return response()->json(['ok' => false, 'error' => 'Only lab staff can remove report files.'], 403);
        }
        if ((int) $patientFile->lab_order_id !== (int) $labOrder->id) {
            return response()->json(['ok' => false, 'error' => 'That file does not belong to this order.'], 422);
        }

        $patientFile->delete(); // soft delete — the PHI audit trail survives

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------ report

    /** Printable HTML report (browser print / Save as PDF). */
    public function report(Request $request, LabOrder $labOrder)
    {
        $this->authorizeAccess($request);
        $this->authorizeSee($labOrder);

        $mode = $request->query('view') ? 'view' : 'print';

        return response()
            ->view('print.lab-report', $this->reportData($labOrder, $mode))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /** The sample slip the lab keeps with the specimen. */
    public function requisition(Request $request, LabOrder $labOrder)
    {
        $this->authorizeAccess($request);
        $this->authorizeSee($labOrder);

        return response()
            ->view('print.lab-requisition', $this->reportData($labOrder, $request->query('view') ? 'view' : 'print'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * The report as a real file: ?format=pdf (default) or ?format=image.
     * Streams inline by default so it previews in a tab; ?download=1 saves it.
     */
    public function reportFile(Request $request, LabOrder $labOrder)
    {
        $this->authorizeAccess($request);
        $this->authorizeSee($labOrder);

        $format = $request->query('format') === 'image' ? 'image' : 'pdf';

        try {
            [$bytes, $mime, $filename] = $this->renderReportFile($labOrder, $format);
        } catch (\Throwable $e) {
            // Log it: with APP_DEBUG off the 503 page swallows the reason, and
            // "the PDF button is broken" is otherwise undiagnosable in prod.
            \Illuminate\Support\Facades\Log::warning('[lab] report render failed', [
                'lab_order_id' => $labOrder->id,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
            abort(503, $e->getMessage());
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Push the report to the patient's WhatsApp as a PDF or an image.
     *
     * Media is uploaded to Meta and sent by media id rather than by URL — a lab
     * result is PHI and must never sit behind a guessable public link. Off by
     * default (config clinic.lab.send_report_whatsapp) because Meta bills per
     * message and a clinic should opt into sending results over WhatsApp.
     */
    public function sendReportWhatsApp(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeSee($labOrder);

        if (! config('clinic.lab.send_report_whatsapp')) {
            return response()->json([
                'ok' => false,
                'error' => 'Sending lab reports over WhatsApp is turned off for this clinic. Enable clinic.lab.send_report_whatsapp first.',
            ], 422);
        }
        if ($labOrder->status !== LabOrder::STATUS_COMPLETED) {
            return response()->json(['ok' => false, 'error' => 'Release the report before sending it.'], 422);
        }

        $data = $request->validate([
            'format' => 'nullable|in:pdf,image',
            // Optionally send an already-attached file (the analyser printout)
            // instead of the generated report.
            'patient_file_id' => 'nullable|integer',
        ]);

        $phone = trim((string) ($labOrder->patient?->phone ?? ''));
        if ($phone === '') {
            return response()->json(['ok' => false, 'error' => 'No phone number on file for this patient.'], 422);
        }

        $ar = app()->getLocale() === 'ar';
        $patientName = $labOrder->patient?->name ?: '';
        $clinicName = $labOrder->branch?->getTranslation('name', app()->getLocale(), true)
            ?: config('app.name', 'Clinic');
        $caption = $ar
            ? trim("مرحباً {$patientName}، هذا تقرير نتائج المختبر ({$labOrder->order_code}) من {$clinicName}. يُرجى مراجعة الطبيب لتفسير النتائج.")
            : trim("Hello {$patientName}, here is your laboratory report ({$labOrder->order_code}) from {$clinicName}. Please discuss the results with your doctor.");

        $tmp = null;
        try {
            if (! empty($data['patient_file_id'])) {
                /** @var PatientFile|null $file */
                $file = PatientFile::query()
                    ->where('lab_order_id', $labOrder->id)
                    ->whereKey((int) $data['patient_file_id'])
                    ->first();
                if (! $file || ! $file->existsOnDisk()) {
                    return response()->json(['ok' => false, 'error' => 'That report file is missing.'], 422);
                }
                $bytes = (string) $file->disk()->get($file->file_path);
                $mime = (string) ($file->mime_type ?: 'application/octet-stream');
                $filename = $file->original_filename ?: ($labOrder->order_code.'.pdf');
                $isImage = str_starts_with($mime, 'image/');
            } else {
                $format = $data['format'] ?? (config('clinic.lab.report_default_format') === 'image' ? 'image' : 'pdf');
                [$bytes, $mime, $filename] = $this->renderReportFile($labOrder, $format);
                $isImage = $format === 'image';
            }

            // Meta's upload endpoint takes a path, so the bytes have to land on
            // disk briefly. 0600 in the private temp dir, removed in `finally`.
            $tmp = rtrim(sys_get_temp_dir(), '/').'/'.$filename;
            file_put_contents($tmp, $bytes);
            @chmod($tmp, 0600);

            $api = app(\App\Services\WhatsAppApiService::class);
            $mediaId = $api->uploadMedia($tmp, $mime);

            $isImage
                ? $api->sendImageById($phone, $mediaId, $caption)
                : $api->sendDocumentById($phone, $mediaId, $caption, $filename);

            app(LabOrderService::class)->markDelivered($labOrder, 'whatsapp', (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } finally {
            if ($tmp) {
                @unlink($tmp);
            }
        }

        return response()->json([
            'ok' => true,
            'order' => $this->transform($labOrder->fresh(['items.labTest', 'attachments.uploadedBy:id,name']), true),
        ]);
    }

    /** Record a print/download hand-off so "delivered" reflects reality. */
    public function markDelivered(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeSee($labOrder);

        $data = $request->validate(['channel' => 'required|in:print,download,handed_over']);

        return $this->run(fn () => app(LabOrderService::class)->markDelivered(
            $labOrder, $data['channel'], (int) (auth()->id() ?? 0)
        ));
    }

    /** Styled .xlsx export of the worklist (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);

        $query = $this->scopedQuery()->with(['patient:id,name,phone', 'doctor:id,name']);

        $tab = $request->input('tab', 'open');
        if ($tab === 'open') {
            $query->open();
        } elseif ($tab === 'completed') {
            $query->where('status', LabOrder::STATUS_COMPLETED);
        }
        if (trim((string) $request->input('q', '')) !== '') {
            $q = trim((string) $request->input('q'));
            $query->where(fn ($w) => $w->where('order_code', 'like', "%{$q}%")
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")));
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('ordered_at'),
                ['Order', 'Patient', 'Phone', 'Doctor', 'Priority', 'Status', 'Tests', 'Ordered', 'Released'],
                fn (LabOrder $o) => [
                    $o->order_code,
                    $o->patient?->name,
                    $o->patient?->phone,
                    $o->doctor?->name,
                    $o->priority,
                    $o->status,
                    $o->items()->count(),
                    optional($o->ordered_at)->format('Y-m-d H:i'),
                    optional($o->completed_at)->format('Y-m-d H:i'),
                ],
                'Lab Orders',
                app()->getLocale() === 'ar',
            ),
            'lab-orders-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    // ------------------------------------------------------------------ shared

    /**
     * Run a service call and return the refreshed order, turning domain errors
     * into 422s instead of 500s — every bench action goes through here.
     */
    protected function run(callable $fn): JsonResponse
    {
        try {
            /** @var LabOrder $order */
            $order = $fn();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'order' => $this->transform(
                $order->fresh([
                    'items.labTest', 'items.completedBy:id,name', 'patient', 'doctor:id,name',
                    'orderedBy:id,name', 'sampleCollectedBy:id,name', 'completedBy:id,name', 'reviewedBy:id,name',
                    'attachments.uploadedBy:id,name',
                ]),
                true
            ),
        ]);
    }

    /**
     * Base query with the caller's visibility applied. Branch scoping already
     * happens in the model's global scope; this narrows doctors to their own
     * orders on top of it.
     */
    protected function scopedQuery()
    {
        $q = LabOrder::query();

        if ($this->isAdminUser() || $this->isLabUser() || $this->isReceptionUser()) {
            return $q;
        }

        $doctorId = $this->doctorIdForCurrentUser();

        return $doctorId ? $q->where('doctor_id', $doctorId) : $q->whereRaw('1=0');
    }

    protected function authorizeSee(LabOrder $order): void
    {
        if ($this->isAdminUser() || $this->isLabUser() || $this->isReceptionUser()) {
            return;
        }

        $doctorId = $this->doctorIdForCurrentUser();
        abort_unless($doctorId !== null && (int) $order->doctor_id === (int) $doctorId, 403,
            'This lab order belongs to another doctor.');
    }

    /** The ordering doctor (or an admin) signs off on results. */
    protected function canReview(LabOrder $order): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }
        $doctorId = $this->doctorIdForCurrentUser();

        return $doctorId !== null && (int) $order->doctor_id === (int) $doctorId;
    }

    protected function abilities(?LabOrder $order = null): array
    {
        $u = auth()->user();

        return [
            'lab_work' => $this->isLabUser(),
            'order' => (bool) $u?->can('create_lab_orders'),
            'cancel' => $this->isLabUser() || ($order ? $this->canReview($order) : $this->isAdminUser()),
            'review' => $order ? $this->canReview($order) : false,
            'export' => (bool) $u?->can('view_any_lab_orders'),
        ];
    }

    protected function transform(LabOrder $o, bool $full = false): array
    {
        $items = $o->relationLoaded('items') ? $o->items : $o->items()->with('labTest')->get();

        $done = $items->where('status', LabOrderItem::STATUS_COMPLETED)->count();
        $active = $items->reject(fn (LabOrderItem $i) => $i->status === LabOrderItem::STATUS_CANCELLED)->count();

        $row = [
            'id' => $o->id,
            'order_code' => $o->order_code,
            'status' => $o->status,
            'priority' => $o->priority,
            'is_urgent' => $o->isUrgent(),
            'is_open' => $o->isOpen(),
            'awaiting_review' => $o->awaitingDoctorReview(),
            'worst_flag' => $o->worstFlag(),
            'visit_id' => $o->visit_id,
            'patient' => $o->patient ? [
                'id' => $o->patient->id,
                'name' => $o->patient->name,
                'phone' => $o->patient->phone,
                'age' => $this->ageOf($o->patient->dob ?? null),
                'gender' => $o->patient->gender,
            ] : null,
            'doctor' => $o->doctor ? ['id' => $o->doctor->id, 'name' => $o->doctor->name] : null,
            'branch' => $o->branch ? [
                'id' => $o->branch->id,
                'name' => $o->branch->getTranslation('name', app()->getLocale(), true),
            ] : null,
            'tests_total' => $active,
            'tests_done' => $done,
            'test_names' => $items->take(4)->map(fn (LabOrderItem $i) => $i->labTest?->name)->filter()->values()->all(),
            'ordered_at' => optional($o->ordered_at)->toIso8601String(),
            'sample_collected_at' => optional($o->sample_collected_at)->toIso8601String(),
            'started_at' => optional($o->started_at)->toIso8601String(),
            'completed_at' => optional($o->completed_at)->toIso8601String(),
            'reviewed_at' => optional($o->reviewed_at)->toIso8601String(),
            'delivered_at' => optional($o->delivered_at)->toIso8601String(),
            'delivered_channel' => $o->delivered_channel,
            'attachments_count' => $o->relationLoaded('attachments')
                ? $o->attachments->count()
                : $o->attachments()->count(),
            // Minutes the order has been waiting — drives the "aging" colour on
            // the worklist so a forgotten sample gets loud instead of scrolling away.
            'waiting_minutes' => $o->ordered_at && $o->isOpen()
                ? (int) $o->ordered_at->diffInMinutes(now())
                : null,
        ];

        if (! $full) {
            return $row;
        }

        return $row + [
            'clinical_note' => $o->clinical_note,
            'lab_note' => $o->lab_note,
            'review_note' => $o->review_note,
            'cancel_reason' => $o->cancel_reason,
            'notes' => $o->notes,
            'ordered_by' => $o->orderedBy?->name,
            'sample_collected_by' => $o->sampleCollectedBy?->name,
            'completed_by' => $o->completedBy?->name,
            'reviewed_by' => $o->reviewedBy?->name,
            'visit' => $o->relationLoaded('visit') && $o->visit ? [
                'id' => $o->visit->id,
                'booking_code' => $o->visit->booking_code,
                'status' => $o->visit->status,
                'diagnosis' => $o->visit->diagnosis,
                'chief_complaint' => $o->visit->chief_complaint,
            ] : null,
            'items' => $items->map(fn (LabOrderItem $i) => [
                'id' => $i->id,
                'lab_test_id' => $i->lab_test_id,
                'name' => $i->labTest?->name ?? '—',
                'code' => $i->labTest?->code,
                'specimen' => $i->labTest?->specimen_type,
                'status' => $i->status,
                'result_value' => $i->result_value,
                'result_unit' => $i->result_unit,
                'reference_range' => $i->reference_range_snapshot,
                'ref_low' => $i->ref_low !== null ? (float) $i->ref_low : null,
                'ref_high' => $i->ref_high !== null ? (float) $i->ref_high : null,
                'flag' => $i->flag,
                'notes' => $i->notes,
                'price' => (float) $i->price_snapshot,
                'completed_at' => optional($i->completed_at)->toIso8601String(),
                'completed_by' => $i->relationLoaded('completedBy') ? $i->completedBy?->name : null,
            ])->values()->all(),
            'attachments' => ($o->relationLoaded('attachments') ? $o->attachments : $o->attachments()->get())
                ->map(fn (PatientFile $f) => $this->transformAttachment($f))->values()->all(),
            'urls' => [
                'print_report' => route('v2.lab-orders.report', $o),
                'print_requisition' => route('v2.lab-orders.requisition', $o),
                'pdf' => route('v2.lab-orders.report-file', ['labOrder' => $o->id, 'format' => 'pdf']),
                'image' => route('v2.lab-orders.report-file', ['labOrder' => $o->id, 'format' => 'image']),
                'show' => route('v2.lab-orders.show', $o),
            ],
        ];
    }

    protected function transformAttachment(PatientFile $f): array
    {
        return [
            'id' => $f->id,
            'filename' => $f->original_filename,
            'mime_type' => $f->mime_type,
            'display_size' => $f->display_size,
            'notes' => $f->notes,
            'uploaded_by' => $f->relationLoaded('uploadedBy') ? $f->uploadedBy?->name : null,
            'created_at' => optional($f->created_at)->toIso8601String(),
            'view_url' => route('v2.api.patient-files.download', ['patientFile' => $f->id, 'inline' => 1]),
            'download_url' => route('v2.api.patient-files.download', ['patientFile' => $f->id]),
            'is_image' => str_starts_with((string) $f->mime_type, 'image/'),
            'is_pdf' => $f->mime_type === 'application/pdf',
        ];
    }

    protected function ageOf($dob): ?int
    {
        if (! $dob) {
            return null;
        }
        try {
            return Carbon::parse($dob)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Render the report to bytes. Returns [bytes, mime, filename].
     * Also logs the render against the patient's file access trail when it
     * leaves the building (WhatsApp / download) — a lab result is PHI.
     */
    protected function renderReportFile(LabOrder $labOrder, string $format): array
    {
        $renderer = app(HtmlToPdfService::class);
        if (! $renderer->available()) {
            throw new \RuntimeException(
                'PDF rendering is not configured on this server — use the printable report instead.'
            );
        }

        $html = view('print.lab-report', $this->reportData($labOrder, 'render'))->render();
        $safeCode = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $labOrder->order_code) ?: 'lab-report';

        return $format === 'image'
            ? [$renderer->toPng($html), 'image/png', $safeCode.'.png']
            : [$renderer->toPdf($html), 'application/pdf', $safeCode.'.pdf'];
    }

    /**
     * View data shared by the printable report, the requisition slip and the
     * headless render. The logo is inlined as a data URI because the render
     * path loads the page from file:// with no network access.
     */
    protected function reportData(LabOrder $labOrder, string $mode): array
    {
        $labOrder->loadMissing(['patient', 'doctor.partner', 'branch', 'items.labTest', 'completedBy:id,name']);

        $ar = app()->getLocale() === 'ar';
        $partner = $labOrder->doctor?->partner;

        $branchName = $labOrder->branch
            ? $labOrder->branch->getTranslation('name', app()->getLocale(), true)
            : null;

        $partnerName = null;
        if ($partner) {
            $raw = $partner->name;
            $partnerName = is_array($raw)
                ? ($raw[app()->getLocale()] ?? $raw['en'] ?? $raw['ar'] ?? reset($raw))
                : $raw;
        }

        $doctorName = $labOrder->doctor?->name ?: '—';
        if ($doctorName !== '—' && ! Str::startsWith($doctorName, ['Dr.', 'Dr ', 'د.'])) {
            $doctorName = ($ar ? 'د. ' : 'Dr. ').$doctorName;
        }

        $items = $labOrder->items
            ->reject(fn (LabOrderItem $i) => $i->status === LabOrderItem::STATUS_CANCELLED)
            ->values();

        // A generated report leaving the building is an access event on the
        // patient's record — log it next to the file downloads.
        if ($mode === 'render') {
            $this->logReportAccess($labOrder);
        }

        return [
            'order' => $labOrder,
            'items' => $items,
            'ar' => $ar,
            'mode' => $mode,
            'clinic' => [
                'name' => $partnerName ?: config('app.name', 'Clinic'),
                'branch' => $branchName,
                'address' => $labOrder->branch?->address,
                'phone' => $labOrder->branch?->phone,
                'license' => $partner?->license_number,
            ],
            'logoData' => $this->inlineLogo($partner?->logo_path),
            'patientAge' => $this->ageOf($labOrder->patient?->dob ?? null),
            'patientGender' => $labOrder->patient?->gender
                ? ucfirst((string) $labOrder->patient->gender)
                : '—',
            'doctorName' => $doctorName,
            'releasedByName' => $labOrder->completedBy?->name
                ?: ($ar ? 'المختبر' : 'Laboratory'),
            'hasAbnormal' => $items->contains(fn (LabOrderItem $i) => in_array(
                $i->flag, [LabOrderItem::FLAG_LOW, LabOrderItem::FLAG_HIGH, LabOrderItem::FLAG_CRITICAL], true
            )),
        ];
    }

    /** Best-effort PHI trail for a generated (non-file) report. */
    protected function logReportAccess(LabOrder $labOrder): void
    {
        try {
            $anchor = PatientFile::query()
                ->where('lab_order_id', $labOrder->id)
                ->orderBy('id')
                ->first();
            if (! $anchor) {
                return;
            }
            PatientFileAccessLog::create([
                'patient_file_id' => $anchor->id,
                'accessed_by_user_id' => auth()->id(),
                'action' => PatientFileAccessLog::ACTION_DOWNLOAD,
                'accessed_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable) {
            // Never block a report on its audit row.
        }
    }

    /** Read a stored logo into a data: URI, or null when there isn't one. */
    protected function inlineLogo(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if (! $disk->exists($path)) {
                return null;
            }
            $bytes = $disk->get($path);
            $mime = $disk->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode((string) $bytes);
        } catch (\Throwable) {
            return null;
        }
    }
}
