<?php

namespace App\Filament\Imports;

use App\Models\Branch;
use App\Models\Doctor;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Bulk-import doctors. Matches by `license_number` when present
 * (the regulatory unique key), falls back to (name, branch_code) so a
 * re-run with the same roster file updates instead of duplicating.
 *
 * Branch lookup is by `branches.slug` so the CSV stays human-editable
 * — no opaque IDs to keep in sync.
 */
class DoctorImporter extends Importer
{
    protected static ?string $model = Doctor::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('specialty')
                ->rules(['nullable', 'string', 'max:100'])
                ->example('Cardiology'),

            ImportColumn::make('phone')
                ->rules(['nullable', 'string', 'max:32']),

            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:191']),

            ImportColumn::make('license_number')
                ->label('License #')
                ->rules(['nullable', 'string', 'max:64']),

            ImportColumn::make('consultation_fee')
                ->label('Consultation fee (KWD)')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('branch_slug')
                ->label('Branch slug')
                ->rules(['nullable', 'string', 'max:100'])
                ->example('main-clinic'),

            ImportColumn::make('bio')
                ->rules(['nullable', 'string', 'max:2000']),

            ImportColumn::make('is_active')
                ->boolean()
                ->rules(['nullable', 'boolean'])
                ->example('1'),
        ];
    }

    public function resolveRecord(): ?Doctor
    {
        $license = trim((string) ($this->data['license_number'] ?? ''));

        if ($license !== '') {
            $existing = Doctor::query()->where('license_number', $license)->first();
            if ($existing) {
                return $existing;
            }
        }

        $name = trim((string) ($this->data['name'] ?? ''));
        $branchId = $this->resolveBranchId();

        if ($name !== '' && $branchId) {
            $existing = Doctor::query()
                ->where('name', $name)
                ->where('branch_id', $branchId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // New row — set the resolved branch_id so the next save() picks it up.
        $record = new Doctor;
        if ($branchId) {
            $record->branch_id = $branchId;
            // Inherit partner_id from the branch so global scopes don't hide the row.
            $branch = Branch::query()->find($branchId);
            if ($branch) {
                $record->partner_id = $branch->partner_id;
            }
        }
        $record->is_active = $record->is_active ?? true;

        return $record;
    }

    /**
     * Strip branch_slug from the fillable payload (it's a virtual column
     * we use only to look up the branch_id in resolveRecord). Runs before
     * Filament fills the record so the unknown column never hits the DB.
     */
    public function beforeFill(): void
    {
        unset($this->data['branch_slug']);
    }

    protected function resolveBranchId(): ?int
    {
        $slug = trim((string) ($this->data['branch_slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        return (int) (Branch::query()->where('slug', $slug)->value('id') ?: 0) ?: null;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Doctor import complete: '.number_format($import->successful_rows).' row'.($import->successful_rows === 1 ? '' : 's').' imported.';
        if ($failed = $import->getFailedRowsCount()) {
            $body .= " {$failed} failed — see failed-rows file.";
        }

        return $body;
    }
}
