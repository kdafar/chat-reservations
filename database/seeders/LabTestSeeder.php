<?php

namespace Database\Seeders;

use App\Models\Lab\LabTest;
use Illuminate\Database\Seeder;

/**
 * Idempotent seed of common outpatient lab tests so the lab module isn't
 * an empty catalog on first boot. Reference ranges are typical adult
 * values — clinic-specific tweaks are an admin task.
 */
class LabTestSeeder extends Seeder
{
    public function run(): void
    {
        $tests = [
            ['code' => 'CBC',   'name' => 'Complete Blood Count',        'specimen' => 'Blood (EDTA)', 'unit' => null,       'range' => 'Panel — see breakdown', 'price' => 5.000],
            ['code' => 'GLU',   'name' => 'Fasting Glucose',             'specimen' => 'Blood',        'unit' => 'mg/dL',    'range' => '70-100 mg/dL',          'price' => 2.000],
            ['code' => 'HBA1C', 'name' => 'HbA1c (Glycated Hemoglobin)', 'specimen' => 'Blood',        'unit' => '%',        'range' => '<5.7%',                  'price' => 6.000],
            ['code' => 'LIPID', 'name' => 'Lipid Panel',                 'specimen' => 'Blood',        'unit' => 'mg/dL',    'range' => 'Panel — see breakdown', 'price' => 7.000],
            ['code' => 'TSH',   'name' => 'Thyroid Stimulating Hormone', 'specimen' => 'Blood',        'unit' => 'µIU/mL',   'range' => '0.4-4.0 µIU/mL',         'price' => 4.500],
            ['code' => 'CRE',   'name' => 'Creatinine',                  'specimen' => 'Blood',        'unit' => 'mg/dL',    'range' => '0.6-1.3 mg/dL',          'price' => 2.500],
            ['code' => 'UREA',  'name' => 'Urea',                        'specimen' => 'Blood',        'unit' => 'mg/dL',    'range' => '15-40 mg/dL',            'price' => 2.500],
            ['code' => 'ALT',   'name' => 'ALT (SGPT)',                  'specimen' => 'Blood',        'unit' => 'U/L',      'range' => '7-56 U/L',               'price' => 3.000],
            ['code' => 'AST',   'name' => 'AST (SGOT)',                  'specimen' => 'Blood',        'unit' => 'U/L',      'range' => '10-40 U/L',              'price' => 3.000],
            ['code' => 'VITD',  'name' => 'Vitamin D (25-OH)',           'specimen' => 'Blood',        'unit' => 'ng/mL',    'range' => '30-100 ng/mL',           'price' => 8.000],
            ['code' => 'URI',   'name' => 'Urinalysis',                  'specimen' => 'Urine',        'unit' => null,       'range' => 'Panel — see breakdown', 'price' => 3.000],
            ['code' => 'PT',    'name' => 'Pregnancy Test (β-hCG)',      'specimen' => 'Urine',        'unit' => null,       'range' => 'Negative',               'price' => 4.000],
        ];

        foreach ($tests as $t) {
            LabTest::firstOrCreate(
                ['branch_id' => null, 'code' => $t['code']],
                [
                    'name' => $t['name'],
                    'specimen_type' => $t['specimen'],
                    'unit' => $t['unit'],
                    'reference_range' => $t['range'],
                    'default_price' => $t['price'],
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('  lab tests: '.LabTest::query()->count().' total');
    }
}
