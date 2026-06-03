<?php

namespace App\Filament\Imports;

use App\Models\Patient;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Bulk-import patients from a CSV/XLSX. Matches existing records by
 * civil_id when present (the regulatory unique identifier in KW), falls
 * back to phone for civilian rows without a Civil ID on file.
 *
 * Idempotent — running the same file twice updates instead of duplicating.
 */
class PatientImporter extends Importer
{
    protected static ?string $model = Patient::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('phone')
                ->rules(['nullable', 'string', 'max:32'])
                ->example('+96599999999'),

            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:191']),

            ImportColumn::make('dob')
                ->label('Date of birth')
                ->rules(['nullable', 'date'])
                ->example('1990-05-12'),

            ImportColumn::make('gender')
                ->rules(['nullable', 'in:male,female,other'])
                ->example('male'),

            ImportColumn::make('civil_id')
                ->label('Civil ID')
                ->rules(['nullable', 'string', 'max:32']),

            ImportColumn::make('blood_group')
                ->rules(['nullable', 'string', 'max:8'])
                ->example('O+'),

            ImportColumn::make('allergies')
                ->rules(['nullable', 'string', 'max:1000']),

            ImportColumn::make('medical_alerts')
                ->rules(['nullable', 'string', 'max:1000']),

            ImportColumn::make('notes')
                ->rules(['nullable', 'string', 'max:2000']),
        ];
    }

    public function resolveRecord(): ?Patient
    {
        // Match-by-civil-id first (unique business key), then phone.
        $civilId = trim((string) ($this->data['civil_id'] ?? ''));
        $phone = $this->normalizePhone((string) ($this->data['phone'] ?? ''));

        if ($civilId !== '') {
            $existing = Patient::query()->where('civil_id', $civilId)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($phone !== '') {
            // Match against the stored phone after stripping the same set of
            // non-digit characters from it — so "+965 9988 7777" in the DB
            // matches "96599887777" in the CSV. Country-code variants
            // (with vs without leading "+", with vs without local code)
            // are also matched by suffix-of-suffix logic at the end.
            $existing = Patient::query()
                ->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                    [$phone]
                )
                ->first();

            // Country-code-tolerant fallback: match if either phone ends with
            // the other (so 99887777 also matches 96599887777 and vice versa,
            // when at least 7 trailing digits agree).
            if (! $existing && strlen($phone) >= 7) {
                $tail = substr($phone, -8);
                $existing = Patient::query()
                    ->whereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?",
                        ['%'.$tail]
                    )
                    ->first();
            }

            if ($existing) {
                return $existing;
            }
        }

        // New row — inherit partner_id from the importing user.
        $record = new Patient;
        $partnerId = (int) (
            $this->import->user?->partners()->value('id')
                ?: \App\Models\Partner::query()->orderBy('id')->value('id')
        );
        if ($partnerId) {
            $record->partner_id = $partnerId;
        }

        return $record;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Patient import complete: '.number_format($import->successful_rows).' row'.($import->successful_rows === 1 ? '' : 's').' imported.';
        if ($failed = $import->getFailedRowsCount()) {
            $body .= " {$failed} failed — see failed-rows file.";
        }

        return $body;
    }

    protected function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        $digits = preg_replace('/\D+/', '', $raw);
        return $digits !== '' ? $digits : $raw;
    }
}
