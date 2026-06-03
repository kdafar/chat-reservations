<?php

namespace Database\Seeders;

use App\Models\Medication;
use Illuminate\Database\Seeder;

/**
 * Seeds a small formulary of common outpatient medications with sensible
 * default dosing for the v2 prescription builder. Idempotent on (name,
 * strength). Clinics extend/edit this from the Drug Formulary admin screen.
 */
class MedicationSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach ($this->data() as $m) {
            $sort += 10;
            Medication::updateOrCreate(
                ['name' => $m['name'], 'strength' => $m['strength'] ?? null],
                [
                    'form' => $m['form'] ?? null,
                    'route' => $m['route'] ?? null,
                    'default_dose' => $m['dose'] ?? null,
                    'default_frequency' => $m['frequency'] ?? null,
                    'default_duration' => $m['duration'] ?? null,
                    'default_instructions' => $m['instructions'] ?? null,
                    'branch_id' => null,
                    'sort_order' => $sort,
                    'is_active' => true,
                ],
            );
        }
    }

    private function data(): array
    {
        return [
            ['name' => 'Paracetamol', 'strength' => '500mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'q6-8h PRN', 'duration' => '5 days', 'instructions' => 'for fever or pain'],
            ['name' => 'Ibuprofen', 'strength' => '400mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'q8h', 'duration' => '5 days', 'instructions' => 'after food'],
            ['name' => 'Amoxicillin', 'strength' => '500mg', 'form' => 'cap', 'route' => 'PO', 'dose' => '1', 'frequency' => 'q8h', 'duration' => '7 days', 'instructions' => 'after food'],
            ['name' => 'Amoxicillin/Clavulanate', 'strength' => '625mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'q12h', 'duration' => '7 days', 'instructions' => 'after food'],
            ['name' => 'Azithromycin', 'strength' => '500mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'OD', 'duration' => '3 days', 'instructions' => 'before food'],
            ['name' => 'Cephalexin', 'strength' => '500mg', 'form' => 'cap', 'route' => 'PO', 'dose' => '1', 'frequency' => 'q8h', 'duration' => '7 days', 'instructions' => 'after food'],
            ['name' => 'Cetirizine', 'strength' => '10mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'OD', 'duration' => '7 days', 'instructions' => 'at night'],
            ['name' => 'Loratadine', 'strength' => '10mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'OD', 'duration' => '7 days', 'instructions' => null],
            ['name' => 'Omeprazole', 'strength' => '20mg', 'form' => 'cap', 'route' => 'PO', 'dose' => '1', 'frequency' => 'OD', 'duration' => '14 days', 'instructions' => 'before breakfast'],
            ['name' => 'Salbutamol', 'strength' => '100mcg', 'form' => 'inhaler', 'route' => 'INH', 'dose' => '2 puffs', 'frequency' => 'q6h PRN', 'duration' => null, 'instructions' => 'for wheeze'],
            ['name' => 'Dextromethorphan', 'strength' => '15mg/5ml', 'form' => 'syrup', 'route' => 'PO', 'dose' => '10ml', 'frequency' => 'q8h', 'duration' => '5 days', 'instructions' => 'for dry cough'],
            ['name' => 'Guaifenesin', 'strength' => '100mg/5ml', 'form' => 'syrup', 'route' => 'PO', 'dose' => '10ml', 'frequency' => 'q8h', 'duration' => '5 days', 'instructions' => 'for productive cough'],
            ['name' => 'ORS', 'strength' => null, 'form' => 'sachet', 'route' => 'PO', 'dose' => '1 sachet', 'frequency' => 'after each loose stool', 'duration' => null, 'instructions' => 'dissolve in 200ml water'],
            ['name' => 'Metformin', 'strength' => '500mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'BID', 'duration' => 'ongoing', 'instructions' => 'with meals'],
            ['name' => 'Metronidazole', 'strength' => '400mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'q8h', 'duration' => '7 days', 'instructions' => 'after food, no alcohol'],
            ['name' => 'Diclofenac', 'strength' => '50mg', 'form' => 'tab', 'route' => 'PO', 'dose' => '1', 'frequency' => 'q8h PRN', 'duration' => '5 days', 'instructions' => 'after food'],
        ];
    }
}
