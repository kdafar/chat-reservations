<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\VisitAuthorization;
use App\Models\Visit;
use App\Models\Workspace\QueueCall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Response;

/**
 * Visit workspace (v3) on REAL data — the redesigned queue + visit pane.
 *
 * Owns no queue logic of its own. It asks the live Waiting Patients
 * controller for exactly the payload that screen gets (same role scoping,
 * same statuses, same branch scope, same 403 rules) and renders the new page
 * with it. Every write the page makes goes to the /api/visits/*,
 * /api/checkin/* and /api/bookings/* endpoints v2 already uses, so there is
 * one set of business rules, not two.
 *
 * v2 is untouched and stays available at /admin/v2/waiting-patients.
 */
class WorkspaceController extends Controller
{
    use VisitAuthorization;

    public function index(Request $request): Response
    {
        // Access = exactly the Waiting Patients rule (admin, reception, doctor,
        // nurse): its index() below aborts 403 for anyone else and scopes the
        // rows by role. One gate, shared, so the two screens never disagree.
        /** @var Response $response */
        $response = app(WaitingPatientsController::class)->index($request);

        // Inertia keeps the component name protected. Swapping it on the v2
        // response is a small reflection seam; the alternative is copying
        // ~600 lines of scoping rules that would drift from the live queue.
        $component = (new \ReflectionObject($response))->getProperty('component');
        $component->setAccessible(true);
        $component->setValue($response, 'WorkspacePreview/Index');

        $waiting = app(WaitingPatientsController::class);

        return $response->with([
            'live' => true,
            'is_demo' => false,
            'done_today' => $this->doneToday($waiting),
            'calls_today' => $this->callsToday(),
            // Each v3 feature switches itself on once its table exists, so
            // deploying the code before running the migration changes nothing.
            'features' => [
                'vitals' => true,
                'alerts' => true,
                'files' => true,
                'order_sets' => Schema::hasTable('order_sets'),
                'calls' => Schema::hasTable('queue_calls'),
                'cash_close' => Schema::hasTable('cash_closes'),
            ],
        ]);
    }

    /**
     * Visits finished today, for the "Done" filter. Same row shape as the
     * live queue (WaitingPatientsController::transform), same branch scope
     * and the same doctor/reception visibility.
     */
    protected function doneToday(WaitingPatientsController $waiting): array
    {
        $scope = new \ReflectionMethod($waiting, 'applyVisibilityScope');
        $scope->setAccessible(true);
        $transform = new \ReflectionMethod($waiting, 'transform');
        $transform->setAccessible(true);

        $query = Visit::query()
            ->with(['patient', 'doctor', 'branch', 'room'])
            ->where('status', Visit::STATUS_COMPLETED)
            ->where('completed_at', '>=', today())
            ->orderByDesc('completed_at')
            ->limit(200);
        $query = $scope->invoke($waiting, $query);

        return $query->get()->map(function (Visit $v) use ($transform, $waiting) {
            $row = $transform->invoke($waiting, $v);
            $row['completed_at'] = optional($v->completed_at)->toIso8601String();

            return $row;
        })->values()->all();
    }

    /** visit_id => how many times the patient was called today. */
    protected function callsToday(): array
    {
        if (! Schema::hasTable('queue_calls')) {
            return [];
        }

        return QueueCall::query()
            ->where('called_at', '>=', today())
            ->selectRaw('visit_id, count(*) as n')
            ->groupBy('visit_id')
            ->pluck('n', 'visit_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
