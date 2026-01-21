<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorCompensationProfile;
use Illuminate\Database\Seeder;

class DoctorCompensationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $doctor = Doctor::query()->orderBy('id')->first();
        if (! $doctor) {
            return;
        }

        DoctorCompensationProfile::query()->updateOrCreate(
            ['doctor_id' => $doctor->id],
            [
                'type' => 'percentage',
                'basis' => 'fees_only',
                'percentage_rate' => 30.000,
                'is_active' => true,
            ]
        );
    }
}
