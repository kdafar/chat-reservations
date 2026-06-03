<?php

namespace App\Imports\V2;

use App\Imports\V2\Tables\ClinicItemImport;
use App\Imports\V2\Tables\ClinicPackageImport;
use App\Imports\V2\Tables\ClinicStockOpeningImport;
use App\Imports\V2\Tables\DoctorCompProfileImport;
use App\Imports\V2\Tables\DoctorImport;
use App\Imports\V2\Tables\ExpenseImport;
use App\Imports\V2\Tables\InsurancePlanImport;
use App\Imports\V2\Tables\InsurerImport;
use App\Imports\V2\Tables\LabTestImport;
use App\Imports\V2\Tables\PatientImport;
use App\Imports\V2\Tables\PatientPolicyImport;
use App\Imports\V2\Tables\UserImport;
use App\Imports\V2\Tables\VendorImport;

/**
 * Maps the {type} route segment to its importer. The single source of truth for
 * "which tables support import" — add a class here and it's wired everywhere.
 */
class ImportRegistry
{
    /** @var array<string, class-string<AbstractImport>> */
    protected const MAP = [
        'patients' => PatientImport::class,
        'doctors' => DoctorImport::class,
        'clinic-items' => ClinicItemImport::class,
        'vendors' => VendorImport::class,
        'insurers' => InsurerImport::class,
        'insurance-plans' => InsurancePlanImport::class,
        'lab-tests' => LabTestImport::class,
        'clinic-packages' => ClinicPackageImport::class,
        'patient-policies' => PatientPolicyImport::class,
        'clinic-stock' => ClinicStockOpeningImport::class,
        'expenses' => ExpenseImport::class,
        'users' => UserImport::class,
        'doctor-comp-profiles' => DoctorCompProfileImport::class,
    ];

    public static function resolve(string $type): ?AbstractImport
    {
        $class = self::MAP[$type] ?? null;

        return $class ? $class::make() : null;
    }

    public static function exists(string $type): bool
    {
        return isset(self::MAP[$type]);
    }

    /**
     * Per-table import authorization for a user, as [slug => bool]. Shared to the
     * frontend so the Import button hides where the user lacks the write
     * permission. The server still enforces this — it's a presentation hint.
     */
    public static function authorizationsFor(?object $user): array
    {
        $out = [];
        foreach (self::MAP as $slug => $class) {
            $out[$slug] = $class::make()->authorize($user);
        }

        return $out;
    }
}
