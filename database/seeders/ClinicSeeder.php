<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\RestaurantTable;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Base Clinic Structure Exists
        $partner = Partner::firstOrCreate(
            ['slug' => 'healthfirst-clinic'],
            [
                'name' => ['en' => 'HealthFirst Clinic', 'ar' => 'عيادة هيلث فيرست'],
                'is_active' => true,
            ]
        );

        $branch = Branch::firstOrCreate(
            ['partner_id' => $partner->id, 'slug' => 'main-branch'],
            [
                'name' => ['en' => 'Main Branch - Downtown', 'ar' => 'الفرع الرئيسي - وسط البلد'],
                'address' => '123 Medical St',
                'phone' => '1800123',
                'is_available' => true,
            ]
        );

        // Ensure Rooms (RestaurantTables) exist for assignment
        $room1 = RestaurantTable::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Room 101 (Cardio)'],
            ['capacity' => 1, 'status' => 'available']
        );

        $room2 = RestaurantTable::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Room 102 (Pediatrics)'],
            ['capacity' => 1, 'status' => 'available']
        );

        // 2. Seed Doctors
        $doctorsData = [
            [
                'name' => 'Dr. Sarah Smith',
                'specialty' => 'Cardiology',
                'email' => 'sarah@clinic.com',
                'phone' => '+96550000001',
                'license_number' => 'LIC-KW-998877',
                'bio' => 'Senior Cardiologist with 15 years experience in hypertension management.',
                'working_hours' => [
                    'mon' => '09:00-17:00',
                    'wed' => '09:00-17:00',
                    'fri' => '09:00-13:00',
                ],
                'restaurant_table_id' => $room1->id, // Assign default room
            ],
            [
                'name' => 'Dr. Ahmed Ali',
                'specialty' => 'Pediatrics',
                'email' => 'ahmed@clinic.com',
                'phone' => '+96550000002',
                'license_number' => 'LIC-KW-112233',
                'bio' => 'Specialist in child healthcare and vaccination.',
                'working_hours' => [
                    'sun' => '10:00-18:00',
                    'tue' => '10:00-18:00',
                    'thu' => '10:00-18:00',
                ],
                'restaurant_table_id' => $room2->id,
            ],
        ];

        foreach ($doctorsData as $data) {
            Doctor::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'partner_id' => $partner->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'consultation_fee' => 25.00,
                ])
            );
        }

        // 3. Seed Patients
        $patientsData = [
            [
                'name' => 'John Doe',
                'phone' => '+96560001111',
                'dob' => '1985-06-15',
                'gender' => 'male',
                'civil_id' => '285061500123',
                'medical_alerts' => 'Allergic to Penicillin',
            ],
            [
                'name' => 'Fatima Al-Sabah',
                'phone' => '+96560002222',
                'dob' => '1992-03-20',
                'gender' => 'female',
                'civil_id' => '292032000456',
                'medical_alerts' => null,
            ],
        ];

        foreach ($patientsData as $pData) {
            Patient::firstOrCreate(
                ['phone' => $pData['phone']],
                array_merge($pData, ['partner_id' => $partner->id])
            );
        }

        // 4. Seed Visits (Encounters)
        $doctor = Doctor::where('email', 'sarah@clinic.com')->first();
        $patient1 = Patient::where('phone', '+96560001111')->first();
        $patient2 = Patient::where('phone', '+96560002222')->first();

        if ($doctor && $patient1) {
            // Visit 1: Historical / Completed checkup
            Visit::create([
                'patient_id' => $patient1->id,
                'doctor_id' => $doctor->id,
                'branch_id' => $branch->id,
                'restaurant_table_id' => $room1->id,
                'status' => 'completed',
                'created_at' => Carbon::now()->subDays(5),
                'completed_at' => Carbon::now()->subDays(5)->addHour(),
                'chief_complaint' => 'Chest pain and fatigue',
                'history' => 'Patient reports mild chest pain after exercise for 2 days.',
                'examination' => 'BP elevated. Heart sounds normal. No edema.',
                'vitals' => ['bp' => '130/85', 'pulse' => 88, 'temp' => 37.1, 'weight' => 82],
                'diagnosis' => 'Mild Hypertension',
                'prescriptions' => [
                    ['medicine' => 'Lisinopril', 'dosage' => '10mg', 'instruction' => 'Once daily'],
                ],
                'lab_requests' => ['ECG', 'Lipid Profile'],
            ]);
        }

        if ($doctor && $patient2) {
            // Visit 2: Live / Checked In right now
            Visit::create([
                'patient_id' => $patient2->id,
                'doctor_id' => $doctor->id,
                'branch_id' => $branch->id,
                'restaurant_table_id' => $room1->id,
                'status' => 'checked_in',
                'created_at' => Carbon::now(),
                'checked_in_at' => Carbon::now(),
                'chief_complaint' => 'Routine follow-up',
                'vitals' => ['bp' => '120/80', 'pulse' => 76, 'temp' => 37.0, 'weight' => 65],
            ]);
        }
    }
}
