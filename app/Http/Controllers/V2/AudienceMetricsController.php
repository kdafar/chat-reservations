<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\AudienceMetric;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audience Metrics — read-only v2 replacement for the Filament
 * AudienceMetricResource. Per-phone engagement rollup (bookings, confirmations,
 * last interaction) used to build WhatsApp campaign audiences. Admin-only.
 */
class AudienceMetricsController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Admin access required.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $minBookings = $request->input('min_bookings') ? (int) $request->input('min_bookings') : null;
        $from = $request->input('from');
        $to = $request->input('to');
        $query = AudienceMetric::query();
        if ($q !== '') { $query->where('msisdn', 'like', "%{$q}%"); }
        if ($minBookings) { $query->where('bookings_count', '>=', $minBookings); }
        if ($from) { $query->whereDate('last_booking_at', '>=', $from); }
        if ($to) { $query->whereDate('last_booking_at', '<=', $to); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('bookings_count'),
                ['Phone', 'Bookings', 'Confirmed', 'Last booking', 'Last interaction', 'Last branch', 'Last party size'],
                fn ($r) => [$r->msisdn, $r->bookings_count, $r->confirmed_count, (string) $r->last_booking_at, (string) $r->last_interaction_at, $r->last_branch, $r->last_party_size],
                'Audience Metrics',
                app()->getLocale() === 'ar',
            ),
            'audience-metrics-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $locale = app()->getLocale();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'min_bookings' => $request->input('min_bookings') ? (int) $request->input('min_bookings') : null,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        $query = AudienceMetric::query();
        if ($filters['q'] !== '') {
            $query->where('msisdn', 'like', "%{$filters['q']}%");
        }
        if ($filters['min_bookings']) {
            $query->where('bookings_count', '>=', $filters['min_bookings']);
        }
        if ($filters['from']) {
            $query->whereDate('last_booking_at', '>=', $filters['from']);
        }
        if ($filters['to']) {
            $query->whereDate('last_booking_at', '<=', $filters['to']);
        }

        $page = $query->orderByDesc('last_interaction_at')->paginate(30)->withQueryString();

        $branchNames = Branch::query()->get(['id', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->localized_name]);

        $page->getCollection()->transform(fn (AudienceMetric $m) => [
            'id' => $m->id, 'msisdn' => $m->msisdn,
            'bookings_count' => $m->bookings_count, 'confirmed_count' => $m->confirmed_count,
            'last_booking_at' => optional($m->last_booking_at)->format('Y-m-d'),
            'last_branch' => $m->last_branch_id ? ($branchNames[$m->last_branch_id] ?? ('#'.$m->last_branch_id)) : null,
            'last_party_size' => $m->last_party_size,
            'last_interaction_at' => optional($m->last_interaction_at)->format('Y-m-d h:i A'),
        ]);

        return Inertia::render('Whatsapp/AudienceMetrics', [
            'filters' => $filters,
            'page' => $page,
            'counts' => [
                'total' => AudienceMetric::query()->count(),
                'with_booking' => AudienceMetric::query()->where('bookings_count', '>', 0)->count(),
            ],
        ]);
    }
}
