<?php

namespace App\Imports\V2;

use App\Models\Partner;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Base class for every v2 spreadsheet importer.
 *
 * A concrete importer only declares: a slug, a title, the target model, the
 * columns, how a raw row maps to model attributes (mapRow), and the business
 * key used to detect an existing record (matchAttributes). The engine handles
 * parsing, per-row validation, create-or-update (upsert), error collection and
 * the downloadable .xlsx template — so adding a table is a small config, not a
 * bespoke importer.
 *
 * Safety: only master/reference data is importable. Transactional/derived
 * tables (visits, bookings, claims, journal entries, …) are intentionally
 * excluded — importing them raw would bypass costing, the accounting engine
 * and state machines.
 */
abstract class AbstractImport
{
    /** Partner the imported rows belong to (resolved from the acting user). */
    protected ?int $partnerId = null;

    /** Simple per-request lookup caches for FK resolution. */
    protected array $cache = [];

    abstract public function slug(): string;

    abstract public function title(): string;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    abstract public function model(): string;

    /** @return ImportColumn[] */
    abstract public function columns(): array;

    /**
     * Map a raw assoc row (header => cell) to model attributes.
     * Throw \RuntimeException(message) to fail just this row with a clear reason
     * (e.g. an FK lookup that found nothing).
     */
    abstract public function mapRow(array $row): array;

    /**
     * The where() conditions that identify an existing record for upsert.
     * Return [] to always create (no dedupe).
     */
    abstract public function matchAttributes(array $attrs): array;

    /**
     * Find the existing record to upsert against. Defaults to a simple where()
     * on matchAttributes(); override for fuzzy matching (e.g. normalized phone).
     */
    protected function findExisting(array $attrs): ?object
    {
        $match = $this->matchAttributes($attrs);

        return $match ? $this->model()::query()->where($match)->first() : null;
    }

    public static function make(): static
    {
        return new static;
    }

    /**
     * The permission required to import this table (a write permission). Null =
     * no specific permission (still behind the v2 auth/role middleware).
     * Override authorize() for non-permission checks (e.g. role-based).
     */
    public function permission(): ?string
    {
        return null;
    }

    /** Whether $user may import this table. Imports WRITE data, so gate on it. */
    public function authorize(?object $user): bool
    {
        $permission = $this->permission();

        return $permission ? (bool) ($user && $user->can($permission)) : true;
    }

    /** Extra free-text lines for the instructions sheet. */
    public function instructions(): array
    {
        return [];
    }

    /**
     * Hook: tweak attributes just before a NEW record is created (e.g. generate
     * a password). Not called on update. Default: unchanged.
     */
    protected function fillForCreate(array $attrs, array $row): array
    {
        return $attrs;
    }

    /**
     * Hook: run after a row is created/updated (e.g. sync roles / relations).
     * Default: no-op.
     */
    protected function afterSave(object $model, array $row, bool $created): void
    {
        //
    }

    /** Optional example data rows (assoc by column key) for the template. */
    public function exampleRows(): array
    {
        return [];
    }

    public function boot(?object $user): void
    {
        $this->partnerId = $this->resolvePartnerId($user);
    }

    /**
     * Import already-parsed rows. $mode is 'upsert' (update matches) or 'skip'
     * (leave matches untouched, create only new). Returns a result summary.
     */
    public function import(array $rows, string $mode, ?object $user): array
    {
        return $this->process($rows, $mode, $user, commit: true);
    }

    /**
     * Dry-run: classify every row (new / update / skip / error) WITHOUT writing
     * anything, so the UI can show "this will be added / this will update"
     * before the user commits. Same validation + match logic as import().
     */
    public function preview(array $rows, string $mode, ?object $user): array
    {
        return $this->process($rows, $mode, $user, commit: false);
    }

    /**
     * Shared engine for both preview and import. When $commit is false nothing
     * is persisted — rows are only classified.
     */
    protected function process(array $rows, string $mode, ?object $user, bool $commit): array
    {
        $this->boot($user);

        $model = $this->model();
        $created = $updated = $skipped = 0;
        $errors = [];
        $preview = [];
        $rules = $this->validationRules();
        $attributeNames = $this->validationAttributeNames();

        foreach ($rows as $index => $row) {
            $line = $index + 2; // row 1 is the header
            $row = $this->normalizeRow($row);

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $label = $this->rowLabel($row);

            $validator = Validator::make($row, $rules, [], $attributeNames);
            if ($validator->fails()) {
                $message = implode(' ', $validator->errors()->all());
                $errors[] = ['row' => $line, 'message' => $message];
                $preview[] = ['row' => $line, 'action' => 'error', 'label' => $label, 'message' => $message];

                continue;
            }

            try {
                $attrs = $this->mapRow($row);
                $existing = $this->findExisting($attrs);

                if ($existing) {
                    if ($mode === 'skip') {
                        $skipped++;
                        $preview[] = ['row' => $line, 'action' => 'skip', 'label' => $label];

                        continue;
                    }
                    if ($commit) {
                        $existing->fill($attrs)->save();
                        $this->afterSave($existing, $row, false);
                    }
                    $updated++;
                    $preview[] = ['row' => $line, 'action' => 'update', 'label' => $label];
                } else {
                    if ($commit) {
                        $record = $model::query()->create($this->fillForCreate($attrs, $row));
                        $this->afterSave($record, $row, true);
                    }
                    $created++;
                    $preview[] = ['row' => $line, 'action' => 'new', 'label' => $label];
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $line, 'message' => $e->getMessage()];
                $preview[] = ['row' => $line, 'action' => 'error', 'label' => $label, 'message' => $e->getMessage()];
            }
        }

        $result = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => count($errors),
            'errors' => array_slice($errors, 0, 100),
            'rows' => array_slice($preview, 0, 300),
        ];

        // Audit trail — only for real imports that changed something.
        if ($commit && ($created || $updated)) {
            $this->logActivity($user, $result, $mode);
        }

        return $result;
    }

    /** Record the import in the activity log (spatie/laravel-activitylog). */
    protected function logActivity(?object $user, array $result, string $mode): void
    {
        try {
            activity('import')
                ->when($user, fn ($a) => $a->causedBy($user))
                ->withProperties([
                    'table' => $this->slug(),
                    'mode' => $mode,
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                ])
                ->log("Imported {$this->title()}: {$result['created']} new, {$result['updated']} updated, {$result['failed']} failed");
        } catch (\Throwable) {
            // Never let an audit-log failure break the import itself.
        }
    }

    /** A short human label for a row, for the preview list (first filled cell). */
    protected function rowLabel(array $row): string
    {
        foreach ($this->columns() as $col) {
            $v = $row[$col->key] ?? null;
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        }

        return '—';
    }

    // ---- Validation ---------------------------------------------------------

    protected function validationRules(): array
    {
        $rules = [];
        foreach ($this->columns() as $col) {
            $rules[$col->key] = $col->effectiveRules();
        }

        return $rules;
    }

    protected function validationAttributeNames(): array
    {
        $names = [];
        foreach ($this->columns() as $col) {
            $names[$col->key] = $col->label;
        }

        return $names;
    }

    protected function normalizeRow(array $row): array
    {
        // Case-insensitive header match: tolerate "Name" vs "name" etc., so a
        // re-typed or differently-cased header doesn't silently drop a column.
        $byLowerHeader = [];
        foreach ($row as $key => $value) {
            $byLowerHeader[mb_strtolower(trim((string) $key))] = $value;
        }

        $out = [];
        foreach ($this->columns() as $col) {
            $v = $byLowerHeader[mb_strtolower($col->key)] ?? null;
            // Spreadsheet cells come back typed (int/float/bool) — coerce to a
            // trimmed string so "string" rules pass and phone/ID numbers survive.
            // Numeric strings still satisfy numeric/integer rules.
            if ($v !== null && ! is_string($v)) {
                if (is_bool($v)) {
                    $v = $v ? '1' : '0';
                } elseif (is_float($v)) {
                    $v = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
                } else {
                    $v = (string) $v;
                }
            }
            if (is_string($v)) {
                $v = trim($v);
            }
            $out[$col->key] = ($v === '' ? null : $v);
        }

        return $out;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }

        return true;
    }

    // ---- Helpers for configs ------------------------------------------------

    protected function bool($v, bool $default = false): bool
    {
        if ($v === null || $v === '') {
            return $default;
        }
        if (is_bool($v)) {
            return $v;
        }

        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'y', 'نعم', 'active'], true);
    }

    protected function fail(string $message): never
    {
        throw new \RuntimeException($message);
    }

    /** Resolve a branch id from a name (localized), slug, or numeric id. */
    protected function resolveBranchId(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $branches = $this->cache['branches'] ??= \App\Models\Branch::query()
            ->get(['id', 'name', 'slug'])
            ->flatMap(function ($b) {
                $names = is_array($b->name) ? array_values($b->name) : [$b->name];
                $keys = array_merge($names, [$b->slug, (string) $b->id]);

                return collect($keys)->filter()->mapWithKeys(fn ($k) => [mb_strtolower(trim((string) $k)) => $b->id]);
            })->all();

        $id = $branches[mb_strtolower($value)] ?? null;
        if (! $id) {
            $this->fail("Unknown branch \"{$value}\".");
        }

        return (int) $id;
    }

    /** Resolve an insurer id from its code (case-insensitive). */
    protected function resolveInsurerId(?string $code, bool $required = true): ?int
    {
        $code = trim((string) $code);
        if ($code === '') {
            return $required ? $this->fail('Insurer code is required.') : null;
        }
        $map = $this->cache['insurers'] ??= \App\Models\Insurance\Insurer::query()
            ->pluck('id', 'code')->mapWithKeys(fn ($id, $c) => [mb_strtolower((string) $c) => $id])->all();
        $id = $map[mb_strtolower($code)] ?? null;
        if (! $id) {
            $this->fail("Unknown insurer code \"{$code}\".");
        }

        return (int) $id;
    }

    /** Resolve an insurance plan id from its code. */
    protected function resolvePlanId(?string $code, bool $required = false): ?int
    {
        $code = trim((string) $code);
        if ($code === '') {
            return $required ? $this->fail('Plan code is required.') : null;
        }
        $map = $this->cache['plans'] ??= \App\Models\Insurance\InsurancePlan::query()
            ->pluck('id', 'code')->mapWithKeys(fn ($id, $c) => [mb_strtolower((string) $c) => $id])->all();
        $id = $map[mb_strtolower($code)] ?? null;
        if (! $id) {
            $this->fail("Unknown plan code \"{$code}\".");
        }

        return (int) $id;
    }

    /** Resolve a patient id from a civil ID. */
    protected function resolvePatientId(?string $civilId): int
    {
        $civilId = trim((string) $civilId);
        if ($civilId === '') {
            $this->fail('Patient civil ID is required.');
        }
        $id = \App\Models\Patient::query()->where('civil_id', $civilId)->value('id');
        if (! $id) {
            $this->fail("No patient found with civil ID \"{$civilId}\".");
        }

        return (int) $id;
    }

    /** Resolve a clinic item id from its English name (optionally within a branch). */
    protected function resolveClinicItemId(?string $name, ?int $branchId = null): int
    {
        $name = trim((string) $name);
        if ($name === '') {
            $this->fail('Item name is required.');
        }
        $q = \App\Models\ClinicItem::query()->where('name->en', $name);
        if ($branchId) {
            $q->where(fn ($x) => $x->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        $id = $q->value('id');
        if (! $id) {
            $this->fail("No clinic item named \"{$name}\".");
        }

        return (int) $id;
    }

    /** Resolve a vendor id from its name or code. */
    protected function resolveVendorId(?string $value, bool $required = false): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $required ? $this->fail('Vendor is required.') : null;
        }
        $id = \App\Models\Accounting\Vendor::query()
            ->where('name', $value)->orWhere('code', $value)->value('id');
        if (! $id) {
            $this->fail("Unknown vendor \"{$value}\".");
        }

        return (int) $id;
    }

    /** Resolve a doctor id from a license number (preferred) or exact name. */
    protected function resolveDoctorId(?string $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            $this->fail('Doctor is required.');
        }
        $id = \App\Models\Doctor::query()
            ->withoutGlobalScopes()
            ->where('license_number', $value)
            ->orWhere('name', $value)
            ->value('id');
        if (! $id) {
            $this->fail("No doctor found matching \"{$value}\" (license # or name).");
        }

        return (int) $id;
    }

    /** Resolve a chart-of-accounts id from its code. */
    protected function resolveAccountId(?string $code, bool $required = true): ?int
    {
        $code = trim((string) $code);
        if ($code === '') {
            return $required ? $this->fail('Account code is required.') : null;
        }
        $map = $this->cache['accounts'] ??= \App\Models\Accounting\Account::query()
            ->pluck('id', 'code')->mapWithKeys(fn ($id, $c) => [mb_strtolower((string) $c) => $id])->all();
        $id = $map[mb_strtolower($code)] ?? null;
        if (! $id) {
            $this->fail("Unknown account code \"{$code}\".");
        }

        return (int) $id;
    }

    /**
     * Parse a spreadsheet date cell to Y-m-d. Accepts Excel serials and common
     * string formats. Slash/dot dates are read DAY-FIRST (d/m/Y) — Kuwait
     * convention — to avoid the ambiguous US month-first interpretation.
     */
    protected function date($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                $this->fail("Invalid date \"{$value}\".");
            }
        }

        $s = trim((string) $value);

        // Try explicit formats first (ISO, then day-first variants) so a date
        // like 01/05/2026 is read as 1 May, not 5 January. Carbon throws on a
        // format mismatch, so each attempt is guarded.
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            try {
                $parsed = \Illuminate\Support\Carbon::createFromFormat('!'.$format, $s);
                if ($parsed && $parsed->format($format) === $s) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                // not this format — try the next
            }
        }

        try {
            return \Illuminate\Support\Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            $this->fail("Invalid date \"{$value}\".");
        }
    }

    /** Resolve the acting user's partner; fall back to the first partner. */
    protected function resolvePartnerId(?object $user): ?int
    {
        $id = null;
        if ($user && method_exists($user, 'partners')) {
            $id = (int) ($user->partners()->value('id') ?: 0) ?: null;
        }

        return $id ?: ((int) (Partner::query()->orderBy('id')->value('id') ?: 0) ?: null);
    }

    // ---- Template -----------------------------------------------------------

    /** Build a styled, two-sheet .xlsx template (Data + Instructions). */
    public function buildTemplate(): Spreadsheet
    {
        $cols = $this->columns();
        $spreadsheet = new Spreadsheet;

        // Sheet 1 — the data sheet the user fills in.
        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Data');

        // Header row only — no example data rows in the Data sheet, so nothing
        // gets imported by accident. Examples live on the Instructions sheet.
        $colLetter = 'A';
        foreach ($cols as $col) {
            $data->setCellValue($colLetter.'1', $col->key);
            $colLetter++;
        }

        $lastCol = $data->getHighestColumn();
        $data->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $data->getRowDimension(1)->setRowHeight(24);
        $data->freezePane('A2');
        foreach (range('A', $lastCol) as $c) {
            $data->getColumnDimension($c)->setWidth(22);
        }

        // Sheet 2 — instructions.
        $info = $spreadsheet->createSheet();
        $info->setTitle('Instructions');
        $info->setCellValue('A1', $this->title().' — how to fill this template');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $info->setCellValue('A3', 'Column');
        $info->setCellValue('B3', 'Required');
        $info->setCellValue('C3', 'Notes / accepted values');
        $info->getStyle('A3:C3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
        ]);

        $r = 4;
        foreach ($cols as $col) {
            $notes = [];
            if ($col->note) {
                $notes[] = $col->note;
            }
            if ($col->allowed) {
                $notes[] = 'One of: '.implode(', ', $col->allowed);
            }
            if ($col->example !== null && $col->example !== '') {
                $notes[] = 'e.g. '.$col->example;
            }
            $info->setCellValue('A'.$r, $col->key);
            $info->setCellValue('B'.$r, $col->required ? 'Yes' : 'No');
            $info->setCellValue('C'.$r, implode('. ', $notes));
            $r++;
        }

        $r++;
        $info->setCellValue('A'.$r, 'Notes');
        $info->getStyle('A'.$r)->getFont()->setBold(true);
        $r++;
        $general = array_merge([
            'Re-importing the same file updates matching rows instead of creating duplicates.',
            'Leave optional columns blank if not applicable. Do not rename or reorder the header row.',
        ], $this->instructions());
        foreach ($general as $line) {
            $info->setCellValue('A'.$r, '• '.$line);
            $r++;
        }

        $info->getColumnDimension('A')->setWidth(26);
        $info->getColumnDimension('B')->setWidth(12);
        $info->getColumnDimension('C')->setWidth(80);
        $info->getStyle('A4:C'.$r)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
