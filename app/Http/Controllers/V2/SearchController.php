<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Global ⌘K search across Patients, Bookings, Doctors.
     *
     * Returns up to 6 results per type. The Booking + Doctor queries
     * are gated by the BelongsToBranchScope global scope automatically;
     * patients are joined to bookings so they only appear if the user
     * could actually see one of the patient's bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Global search surfaces patient PHI (name/phone/civil ID), so it is
        // gated on the patient-directory permission. Users without it get an
        // empty result set rather than a 403 (keeps the ⌘K palette quiet).
        if (! $user || ! $user->can('view_any_patients')) {
            return response()->json(['groups' => []]);
        }

        $q = trim((string) $request->query('q', ''));

        // Less than 2 chars: return empty rather than spamming the DB on
        // every keystroke.
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $like = '%'.$q.'%';

        // --- Patients --------------------------------------------------------
        $accessibleBookingPatientIds = Booking::query()
            ->whereNotNull('patient_id')
            ->select('patient_id')
            ->distinct()
            ->pluck('patient_id');

        $patients = Patient::query()
            ->where(function ($w) use ($like, $q) {
                $w->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('civil_id', 'like', $like)
                    ->orWhere('id', $q);
            })
            ->when(
                ! ($this->hasAdminAccess($user)),
                fn ($w) => $w->whereIn('id', $accessibleBookingPatientIds)
            )
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$q.'%'])
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'phone', 'civil_id']);

        // --- Bookings --------------------------------------------------------
        $bookings = Booking::query()
            ->where(function ($w) use ($like) {
                $w->where('booking_code', 'like', $like)
                    ->orWhere('msisdn', 'like', $like);
            })
            ->latest('res_date')
            ->limit(6)
            ->get(['id', 'booking_code', 'res_date', 'res_time', 'msisdn', 'status', 'doctor_id']);

        $doctorIds = $bookings->pluck('doctor_id')->filter()->unique()->all();
        $doctorNames = Doctor::query()->whereIn('id', $doctorIds)->pluck('name', 'id');

        // --- Doctors ---------------------------------------------------------
        $doctors = Doctor::query()
            ->where('name', 'like', $like)
            ->limit(6)
            ->get(['id', 'name', 'specialty']);

        $groups = [];

        if ($patients->isNotEmpty()) {
            $groups[] = [
                'title' => 'patients',
                'items' => $patients->map(fn ($p) => [
                    'type' => 'patient',
                    'id' => $p->id,
                    'icon' => 'user-round',
                    'title' => $p->name,
                    'subtitle' => trim(($p->phone ?? '').($p->civil_id ? ' · '.$p->civil_id : '')),
                    'url' => '/admin/v2/patients/'.$p->id,
                ])->all(),
            ];
        }

        if ($bookings->isNotEmpty()) {
            $groups[] = [
                'title' => 'bookings',
                'items' => $bookings->map(fn ($b) => [
                    'type' => 'booking',
                    'id' => $b->id,
                    'icon' => 'calendar-days',
                    'title' => $b->booking_code,
                    'subtitle' => trim(
                        ($b->res_date ? \Carbon\Carbon::parse($b->res_date)->format('M j').' · ' : '').
                        ($b->res_time ? substr($b->res_time, 0, 5).' · ' : '').
                        ($doctorNames[$b->doctor_id] ?? '').
                        ($b->status ? ' · '.$b->status : '')
                    ),
                    'url' => '/admin/v2/bookings?open='.$b->id,
                ])->all(),
            ];
        }

        if ($doctors->isNotEmpty()) {
            $groups[] = [
                'title' => 'doctors',
                'items' => $doctors->map(fn ($d) => [
                    'type' => 'doctor',
                    'id' => $d->id,
                    'icon' => 'stethoscope',
                    'title' => $d->name,
                    'subtitle' => $d->specialty ?: '—',
                    'url' => '/admin/v2/doctors?edit='.$d->id,
                ])->all(),
            ];
        }

        return response()->json(['groups' => $groups]);
    }

    protected function hasAdminAccess($user): bool
    {
        return method_exists($user, 'hasRole')
            && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }
}
