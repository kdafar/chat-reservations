<?php

namespace App\Filament\Resources\Inpatient\AdmissionResource\Pages;

use App\Filament\Resources\Inpatient\AdmissionResource;
use App\Models\Branch;
use App\Models\Inpatient\Admission;
use App\Models\Inpatient\Bed;
use App\Services\Inpatient\AdmissionService;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmission extends CreateRecord
{
    protected static string $resource = AdmissionResource::class;

    /**
     * Route the create through AdmissionService so the admission code is
     * generated, the active-admission guard fires, and the initial bed
     * (if picked) is assigned atomically with the admission row.
     */
    protected function handleRecordCreation(array $data): Admission
    {
        $branch = Branch::query()->find($data['branch_id']);
        $partnerId = $branch?->partner_id;

        $initialBedId = $this->data['_initial_bed_id'] ?? null;
        $bed = $initialBedId ? Bed::query()->find($initialBedId) : null;

        return app(AdmissionService::class)->admit([
            'patient_id' => $data['patient_id'],
            'admitting_doctor_id' => $data['admitting_doctor_id'],
            'branch_id' => $data['branch_id'],
            'partner_id' => $partnerId,
            'admitting_visit_id' => $data['admitting_visit_id'] ?? null,
            'admission_reason' => $data['admission_reason'],
            'diagnosis' => $data['diagnosis'] ?? null,
            'admitted_at' => $data['admitted_at'] ?? now(),
            'expected_discharge_at' => $data['expected_discharge_at'] ?? null,
        ], $bed, auth()->user());
    }
}
