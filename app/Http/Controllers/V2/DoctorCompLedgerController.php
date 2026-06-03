<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Doctor Compensation Ledger — v2 replacement for Filament DoctorCompensationLedgerResource.
 * Read-only per-visit earnings snapshots. Branch + doctor scoped (a doctor user sees
 * only their own rows via the model global scope).
 */
class DoctorCompLedgerController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_doctor_compensation_ledgers')) {
            abort(403, 'Not authorized to view doctor compensation.');
        }
    }

    /** Styled .xlsx export of the doctor compensation ledger (mirrors filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $doctorId = $request->input('doctor_id', '') !== '' ? (int) $request->input('doctor_id') : null;
        $from = $request->input('from');
        $until = $request->input('until');

        $query = DoctorCompensationLedger::query()->with(['doctor:id,name', 'visit:id,booking_code']);
        if ($doctorId) { $query->where('doctor_id', $doctorId); }
        if ($from) { $query->whereDate('created_at', '>=', $from); }
        if ($until) { $query->whereDate('created_at', '<=', $until); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Date', 'Doctor', 'Visit', 'Type', 'Fees', 'Profit', 'Doctor cut'],
                fn ($l) => [$l->id, optional($l->created_at)->format('Y-m-d'), $l->doctor?->name ?? ('#'.$l->doctor_id), $l->visit?->booking_code ?? ('#'.$l->visit_id), $l->type_snapshot, number_format((float) $l->fees_snapshot, 3, '.', ''), number_format((float) $l->profit_snapshot, 3, '.', ''), number_format((float) $l->doctor_cut_amount, 3, '.', '')],
                'Doctor Compensation',
                app()->getLocale() === 'ar',
            ),
            'doctor-compensation-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'doctor_id' => $request->input('doctor_id', '') !== '' ? (int) $request->input('doctor_id') : null,
            'from' => $request->input('from'),
            'until' => $request->input('until'),
        ];

        $query = DoctorCompensationLedger::query()->with(['doctor:id,name', 'visit:id,booking_code']);

        if ($filters['doctor_id']) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if ($filters['from']) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['until']) {
            $query->whereDate('created_at', '<=', $filters['until']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (DoctorCompensationLedger $l) {
            $l->setAttribute('doctor_name', $l->doctor?->name ?? ('#'.$l->doctor_id));
            $l->setAttribute('visit_label', $l->visit?->booking_code ?? ('#'.$l->visit_id));
            return $l;
        });

        // Sum of the doctor cut over the current (filtered, unpaginated) set.
        $totalQuery = DoctorCompensationLedger::query();
        if ($filters['doctor_id']) $totalQuery->where('doctor_id', $filters['doctor_id']);
        if ($filters['from']) $totalQuery->whereDate('created_at', '>=', $filters['from']);
        if ($filters['until']) $totalQuery->whereDate('created_at', '<=', $filters['until']);

        return Inertia::render('DoctorCompLedger/Index', [
            'filters' => $filters,
            'page' => $page,
            'doctors' => Doctor::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name ?? ('#'.$d->id)])->all(),
            'counts' => [
                'total' => (clone $totalQuery)->count(),
                'doctor_cut_sum' => round((float) (clone $totalQuery)->sum('doctor_cut_amount'), 3),
            ],
        ]);
    }
}
