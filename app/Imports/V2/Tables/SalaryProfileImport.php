<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\StaffCompensationProfile;

/**
 * Bulk-load staff salary structures during onboarding — basic salary, recurring
 * allowances/deductions, annual leave days and hire/termination dates. One
 * profile per staff member, so re-importing updates the same person's profile.
 *
 * Allowances and deductions are written as "Label:amount" pairs separated by
 * semicolons, e.g. "Housing:150; Transport:50".
 */
class SalaryProfileImport extends AbstractImport
{
    public function slug(): string { return 'salary-profiles'; }
    public function title(): string { return 'Salary Profiles'; }
    public function model(): string { return StaffCompensationProfile::class; }

    /** Same write gate the controller's store() uses (there is no separate create permission). */
    public function permission(): string { return 'update_staff_compensation_profiles'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('staff', 'Staff')->required()->note('Staff email (preferred) or exact name')->example('sara@clinic.com'),
            ImportColumn::make('basic_salary', 'Basic salary')->required()->rules(['numeric', 'min:0'])->example('450'),
            ImportColumn::make('annual_leave_days', 'Annual leave days')->required()->rules(['integer', 'min:0', 'max:90'])->example('30'),
            ImportColumn::make('allowances', 'Allowances')->note('"Label:amount" pairs separated by ;')->example('Housing:150; Transport:50'),
            ImportColumn::make('deductions', 'Deductions')->note('"Label:amount" pairs separated by ;')->example('Insurance:10'),
            ImportColumn::make('hire_date', 'Hire date')->note('YYYY-MM-DD')->example('2023-01-15'),
            ImportColumn::make('termination_date', 'Termination date')->note('YYYY-MM-DD — leave blank for active staff'),
            ImportColumn::make('branch', 'Branch')->note('Branch name, slug or id — blank applies to all branches'),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
            ImportColumn::make('notes', 'Notes')->rules(['string', 'max:1000']),
        ];
    }

    public function instructions(): array
    {
        return [
            'Each staff member has a single salary profile — importing again updates it.',
            'Allowances and deductions: write "Label:amount" pairs separated by semicolons, e.g. "Housing:150; Transport:50".',
            'Staff must already exist as users — match by email (preferred) or exact name.',
        ];
    }

    public function exampleRows(): array
    {
        return [['staff' => 'sara@clinic.com', 'basic_salary' => '450', 'annual_leave_days' => '30', 'allowances' => 'Housing:150; Transport:50', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'user_id' => $this->resolveUserId($row['staff'] ?? null),
            'branch_id' => $this->resolveBranchId($row['branch'] ?? null),
            'basic_salary' => round((float) $row['basic_salary'], 3),
            'annual_leave_days' => (int) $row['annual_leave_days'],
            'allowances' => $this->parseLines($row['allowances'] ?? null),
            'deductions' => $this->parseLines($row['deductions'] ?? null),
            'hire_date' => $this->date($row['hire_date'] ?? null),
            'termination_date' => $this->date($row['termination_date'] ?? null),
            'is_active' => $this->bool($row['is_active'] ?? null, true),
            'notes' => $row['notes'] ?: null,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['user_id' => $attrs['user_id']];
    }

    /** Parse "Label:amount; Label2:amount2" into the model's JSON line shape. */
    protected function parseLines(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/[;\n]+/', $value) as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            [$label, $amount] = array_pad(array_map('trim', explode(':', $piece, 2)), 2, null);
            if ($label === '' || $label === null || ! is_numeric($amount)) {
                $this->fail("Bad allowance/deduction \"{$piece}\" — use the Label:amount format, e.g. Housing:150.");
            }
            $out[] = ['label' => $label, 'amount' => round((float) $amount, 3)];
        }

        return $out;
    }
}
