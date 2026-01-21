<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VisitPrintController extends Controller
{
    public function labs(Request $request, Visit $visit): Response
    {
        // Optional: authorizations (recommended)
        // abort_unless($request->user()->can('view', $visit), 403);

        // Lock on first print (don’t keep toggling)
        if (! $visit->is_labs_printed) {
            $visit->forceFill(['is_labs_printed' => true])->save();
        }

        // Make sure relations are loaded for the view
        $visit->loadMissing(['patient', 'doctor.partner', 'branch']);

        return response()
            ->view('print.lab-request', compact('visit'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function prescription(Request $request, Visit $visit): Response
    {
        if (! $visit->is_prescriptions_printed) {
            $visit->forceFill(['is_prescriptions_printed' => true])->save();
        }

        $visit->loadMissing(['patient', 'doctor.partner', 'branch']);

        return response()
            ->view('print.prescription', compact('visit'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
