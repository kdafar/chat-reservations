<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Branch;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Appointments Report — the demand side of the clinic.
 *
 * Clinic Reports counts visits, so a booking that was cancelled or never turned
 * up simply doesn't exist in it. That hides the two numbers a front desk is
 * judged on: how much booked capacity evaporates, and which channel brings
 * patients who actually attend.
 */
class BookingReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_booking_reports')) {
            abort(403, 'Not authorized to view appointment reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'from' => $request->input('from') ?: Carbon::now($tz)->subDays(89)->toDateString(),
            'to' => $request->input('to') ?: Carbon::now($tz)->toDateString(),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'doctor_id' => $request->input('doctor_id', '') !== '' ? (int) $request->input('doctor_id') : null,
        ];
        // A doctor carried over from a different branch would silently produce an
        // all-zero report, so drop the selection when it doesn't belong to the
        // chosen branch.
        if ($filters['branch_id'] && $filters['doctor_id']
            && ! Doctor::query()->whereKey($filters['doctor_id'])->where('branch_id', $filters['branch_id'])->exists()) {
            $filters['doctor_id'] = null;
        }

        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();
        $branchIds = $this->accessibleBranchIds();

        $memo = null;
        $get = function (string $key) use (&$memo, $filters, $from, $to, $branchIds) {
            $memo ??= $this->build($filters, $from, $to, $branchIds);

            return $memo[$key];
        };

        return Inertia::render('Reports/BookingReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'bookings'),
            'status_mix' => Inertia::defer(fn () => $get('status_mix'), 'bookings'),
            'by_source' => Inertia::defer(fn () => $get('by_source'), 'bookings'),
            'no_show_by_doctor' => Inertia::defer(fn () => $get('no_show_by_doctor'), 'bookings'),
            'by_weekday' => Inertia::defer(fn () => $get('by_weekday'), 'bookings'),
            'by_hour' => Inertia::defer(fn () => $get('by_hour'), 'bookings'),
            'lead_time' => Inertia::defer(fn () => $get('lead_time'), 'bookings'),
            'cancellation_reasons' => Inertia::defer(fn () => $get('cancellation_reasons'), 'bookings'),
            'trend' => Inertia::defer(fn () => $get('trend'), 'bookings'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
            // Narrow to the chosen branch, and never past what this user may see —
            // withoutGlobalScopes() here would hand a branch-scoped user the names
            // of every doctor in the group.
            'doctors' => Doctor::query()
                ->atBranch($filters['branch_id'])
                ->when(! $filters['branch_id'] && $branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name ?? ('#'.$d->id)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $base = function () use ($filters, $from, $to, $branchIds) {
            $q = DB::table('bookings as b')
                ->whereNull('b.deleted_at')
                ->whereBetween('b.res_start', [$from, $to]);

            if ($filters['branch_id']) {
                $q->where('b.branch_id', $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn('b.branch_id', $branchIds ?: [0]);
            }
            if ($filters['doctor_id']) {
                $q->where('b.doctor_id', $filters['doctor_id']);
            }

            return $q;
        };

        $total = (int) $base()->count();
        $attended = (int) $base()->whereIn('b.status', ['completed'])->count();
        $noShow = (int) $base()->where('b.status', 'no_show')->count();
        $cancelled = (int) $base()->where('b.status', 'cancelled')->count();
        $upcoming = (int) $base()->whereIn('b.status', ['confirmed', 'pending'])->count();

        // Everything that reached its slot — the denominator for show rate.
        $due = $attended + $noShow;

        // Average wait between arriving and being seen.
        $wait = DB::table('visits as v')
            ->join('bookings as b2', 'b2.id', '=', 'v.booking_id')
            ->whereBetween('b2.res_start', [$from, $to])
            ->whereNotNull('v.checked_in_at')->whereNotNull('v.accepted_at')
            ->when($filters['branch_id'], fn ($q) => $q->where('v.branch_id', $filters['branch_id']))
            ->when($branchIds !== null && ! $filters['branch_id'], fn ($q) => $q->whereIn('v.branch_id', $branchIds ?: [0]))
            ->when($filters['doctor_id'], fn ($q) => $q->where('v.doctor_id', $filters['doctor_id']))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, v.checked_in_at, v.accepted_at)) as m')->value('m');

        $leadRows = $base()->selectRaw('AVG(DATEDIFF(b.res_start, b.created_at)) as avg_days')->first();

        $kpis = [
            'total' => $total,
            'attended' => $attended,
            'no_show' => $noShow,
            'cancelled' => $cancelled,
            'upcoming' => $upcoming,
            'show_rate' => $due > 0 ? round(($attended / $due) * 100, 1) : null,
            'no_show_rate' => $due > 0 ? round(($noShow / $due) * 100, 1) : null,
            'cancel_rate' => $total > 0 ? round(($cancelled / $total) * 100, 1) : null,
            'avg_wait_minutes' => $wait !== null ? round((float) $wait, 1) : null,
            'avg_lead_days' => $leadRows && $leadRows->avg_days !== null ? round((float) $leadRows->avg_days, 1) : null,
        ];

        $statusMix = $base()->groupBy('b.status')->selectRaw('b.status as status, COUNT(*) as c')
            ->orderByDesc('c')->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->c])->all();

        // ---- Channel quality --------------------------------------------------
        // Volume alone flatters whichever channel is loudest; the number that
        // matters is how many of those bookings turn into an attended visit.
        $bySource = $base()->groupBy('b.source')
            ->selectRaw("COALESCE(NULLIF(b.source,''),'unknown') as source, COUNT(*) as total,
                SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as attended,
                SUM(CASE WHEN b.status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->orderByDesc('total')->get()
            ->map(function ($r) {
                $due = (int) $r->attended + (int) $r->no_show;

                return [
                    'source' => (string) $r->source,
                    'total' => (int) $r->total,
                    'attended' => (int) $r->attended,
                    'no_show' => (int) $r->no_show,
                    'cancelled' => (int) $r->cancelled,
                    'show_rate' => $due > 0 ? round(((int) $r->attended / $due) * 100, 1) : null,
                ];
            })->all();

        // ---- Who gets stood up ---------------------------------------------------
        $noShowByDoctor = $base()
            ->leftJoin('doctors as d', 'd.id', '=', 'b.doctor_id')
            ->groupBy('d.id', 'd.name')
            ->havingRaw('COUNT(*) >= 5')
            ->selectRaw("COALESCE(d.name,'(unassigned)') as doctor, COUNT(*) as total,
                SUM(CASE WHEN b.status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as attended")
            ->get()
            ->map(function ($r) {
                $due = (int) $r->attended + (int) $r->no_show;

                return [
                    'doctor' => (string) $r->doctor,
                    'total' => (int) $r->total,
                    'no_show' => (int) $r->no_show,
                    'rate' => $due > 0 ? round(((int) $r->no_show / $due) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('rate')->take(12)->values()->all();

        // ---- When demand actually lands ---------------------------------------------
        $wdRaw = $base()->selectRaw('DAYOFWEEK(b.res_start) as wd, COUNT(*) as c,
            SUM(CASE WHEN b.status = "no_show" THEN 1 ELSE 0 END) as ns')
            ->groupBy('wd')->get()->keyBy('wd');
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $byWeekday = [];
        for ($i = 1; $i <= 7; $i++) {
            $row = $wdRaw->get($i);
            $byWeekday[] = [
                'label' => $labels[$i - 1],
                'count' => (int) ($row->c ?? 0),
                'no_show' => (int) ($row->ns ?? 0),
            ];
        }

        $hrRaw = $base()->selectRaw('HOUR(b.res_start) as h, COUNT(*) as c')->groupBy('h')->pluck('c', 'h')->all();
        $byHour = [];
        for ($h = 8; $h <= 21; $h++) {
            $byHour[] = ['hour' => $h, 'count' => (int) ($hrRaw[$h] ?? 0)];
        }

        // ---- How far ahead people book ---------------------------------------------------
        $leadBuckets = ['Same day' => 0, '1–3 days' => 0, '4–7 days' => 0, '8–14 days' => 0, '15+ days' => 0];
        $leadRaw = $base()->selectRaw('DATEDIFF(b.res_start, b.created_at) as d, COUNT(*) as c')->groupBy('d')->get();
        foreach ($leadRaw as $r) {
            $d = (int) $r->d;
            $key = match (true) {
                $d <= 0 => 'Same day',
                $d <= 3 => '1–3 days',
                $d <= 7 => '4–7 days',
                $d <= 14 => '8–14 days',
                default => '15+ days',
            };
            $leadBuckets[$key] += (int) $r->c;
        }
        $leadTime = array_map(fn ($k) => ['label' => $k, 'count' => $leadBuckets[$k]], array_keys($leadBuckets));

        // ---- Why appointments fall away ------------------------------------------------------
        $cancellationReasons = $base()->where('b.status', 'cancelled')
            ->selectRaw("COALESCE(NULLIF(b.cancellation_reason_code,''),
                COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(b.meta,'$.cancel_reason')),'null'), '(not recorded)')) as reason,
                COUNT(*) as c")
            ->groupBy('reason')->orderByDesc('c')->limit(12)->get()
            ->map(fn ($r) => ['reason' => (string) $r->reason, 'count' => (int) $r->c])->all();

        // ---- Volume over time ---------------------------------------------------------------------
        $trend = $base()
            ->groupBy(DB::raw('DATE(b.res_start)'))
            ->selectRaw('DATE(b.res_start) as d, COUNT(*) as total,
                SUM(CASE WHEN b.status = "completed" THEN 1 ELSE 0 END) as attended,
                SUM(CASE WHEN b.status = "no_show" THEN 1 ELSE 0 END) as no_show')
            ->orderBy('d')->get()
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->d)->format('d M'),
                'total' => (int) $r->total,
                'attended' => (int) $r->attended,
                'no_show' => (int) $r->no_show,
            ])->all();

        return [
            'kpis' => $kpis,
            'status_mix' => $statusMix,
            'by_source' => $bySource,
            'no_show_by_doctor' => $noShowByDoctor,
            'by_weekday' => $byWeekday,
            'by_hour' => $byHour,
            'lead_time' => $leadTime,
            'cancellation_reasons' => $cancellationReasons,
            'trend' => $trend,
        ];
    }
}
