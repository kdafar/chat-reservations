<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\ClinicItem;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\DoctorCompensationProfile;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitItem;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Explicit mappings for the models you are actively securing now.
     * This avoids “it exists but guessed wrong namespace” problems.
     */
    protected $policies = [
        Doctor::class => \App\Policies\Clinic\DoctorPolicy::class,
        Patient::class => \App\Policies\Clinic\PatientPolicy::class,
        Visit::class => \App\Policies\Clinic\VisitPolicy::class,
        VisitItem::class => \App\Policies\Clinic\VisitItemPolicy::class,
        ClinicItem::class => \App\Policies\Clinic\ClinicItemPolicy::class,
        DoctorCompensationProfile::class => \App\Policies\Clinic\DoctorCompensationProfilePolicy::class,
        DoctorCompensationLedger::class => \App\Policies\Clinic\DoctorCompensationLedgerPolicy::class,
        Booking::class => \App\Policies\Clinic\BookingPolicy::class,
        Branch::class => \App\Policies\Clinic\BranchPolicy::class,
        BranchAvailabilityRule::class => \App\Policies\Clinic\BranchAvailabilityRulePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * Fallback auto-guess for everything else:
         * App\Models\Order => App\Policies\OrderPolicy
         *
         * Note: we only guess if a policy class exists.
         * If it doesn’t exist, returning null is safer than returning a class name that doesn’t exist.
         */
        Gate::guessPolicyNamesUsing(function (string $modelClass) {
            $base = class_basename($modelClass);

            // If you ever have nested models like App\Models\Clinic\Doctor,
            // this creates "Clinic\DoctorPolicy" as an additional candidate.
            $relative = ltrim(str_replace('App\\Models\\', '', $modelClass), '\\');
            $relativePolicy = str_replace('\\', '\\', $relative).'Policy';

            $candidates = [
                // your current structure
                "App\\Policies\\Clinic\\{$base}Policy",
                // common default structure
                "App\\Policies\\{$base}Policy",

                // optional: supports nested model folders -> matching policy folders
                "App\\Policies\\{$relativePolicy}",
                "App\\Policies\\Clinic\\{$relativePolicy}",
            ];

            foreach ($candidates as $policyClass) {
                if (class_exists($policyClass)) {
                    return $policyClass;
                }
            }

            return null;
        });
    }
}
