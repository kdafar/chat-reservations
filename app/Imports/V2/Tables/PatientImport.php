<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Patient;

class PatientImport extends AbstractImport
{
    public function slug(): string { return 'patients'; }
    public function title(): string { return 'Patients'; }
    public function model(): string { return Patient::class; }

    public function permission(): string { return 'create_patients'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('name', 'Full name')->required()->rules(['string', 'max:191'])->example('Ahmed Ali'),
            ImportColumn::make('phone', 'Phone')->required()->rules(['string', 'max:32'])->example('+96599887766'),
            ImportColumn::make('civil_id', 'Civil ID')->rules(['string', 'max:32'])->note('Used as the unique key when present')->example('290010112345'),
            ImportColumn::make('email', 'Email')->rules(['email', 'max:191']),
            ImportColumn::make('dob', 'Date of birth')->note('YYYY-MM-DD')->example('1990-05-12'),
            ImportColumn::make('gender', 'Gender')->allowed(['male', 'female']),
            ImportColumn::make('blood_group', 'Blood group')->allowed(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            ImportColumn::make('allergies', 'Allergies')->rules(['string', 'max:2000']),
            ImportColumn::make('medical_alerts', 'Medical alerts')->rules(['string', 'max:2000']),
            ImportColumn::make('notes', 'Notes')->rules(['string', 'max:2000']),
        ];
    }

    public function exampleRows(): array
    {
        return [['name' => 'Ahmed Ali', 'phone' => '+96599887766', 'civil_id' => '290010112345', 'gender' => 'male', 'dob' => '1990-05-12', 'blood_group' => 'O+']];
    }

    public function mapRow(array $row): array
    {
        return [
            'name' => $row['name'],
            'phone' => $row['phone'],
            'civil_id' => $row['civil_id'] ?: null,
            'email' => $row['email'] ?: null,
            'dob' => $this->date($row['dob'] ?? null),
            'gender' => $row['gender'] ?: null,
            'blood_group' => $row['blood_group'] ?: null,
            'allergies' => $row['allergies'] ?: null,
            'medical_alerts' => $row['medical_alerts'] ?: null,
            'notes' => $row['notes'] ?: null,
            'partner_id' => $this->partnerId,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        if (! empty($attrs['civil_id'])) {
            return ['civil_id' => $attrs['civil_id']];
        }

        return ['phone' => $attrs['phone']];
    }

    /**
     * Match on civil_id first (exact, unique), then on a digits-only comparison
     * of phone so "+965 9988 7777", "96599887777" and "99887777" all match the
     * same patient instead of creating a duplicate.
     */
    protected function findExisting(array $attrs): ?object
    {
        if (! empty($attrs['civil_id'])) {
            $byCivil = Patient::query()->where('civil_id', $attrs['civil_id'])->first();
            if ($byCivil) {
                return $byCivil;
            }
        }

        $digits = preg_replace('/\D+/', '', (string) ($attrs['phone'] ?? ''));
        if ($digits === '') {
            return null;
        }

        $stripExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";

        $exact = Patient::query()->whereRaw("{$stripExpr} = ?", [$digits])->first();
        if ($exact) {
            return $exact;
        }

        // Country-code tolerant: match on the last 8 digits (so 99887777
        // matches 96599887777 and vice-versa).
        if (strlen($digits) >= 8) {
            return Patient::query()->whereRaw("{$stripExpr} LIKE ?", ['%'.substr($digits, -8)])->first();
        }

        return null;
    }
}
