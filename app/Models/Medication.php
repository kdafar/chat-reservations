<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use App\Models\Concerns\LogsClinicActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Outpatient drug formulary entry used by the v2 prescription builder.
 * See the create_medications migration for the field meanings.
 */
class Medication extends Model
{
    use SoftDeletes;
    use BelongsToBranchScope;
    use LogsClinicActivity;

    protected $guarded = [];

    protected $activityLogName = 'medications';

    protected $casts = [
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Human label for a picker row, e.g. "Amoxicillin 500mg cap".
     */
    public function getDisplayLabelAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->name, $this->strength, $this->form])));
    }

    /**
     * The default one-line prescription this drug suggests, e.g.
     * "Amoxicillin 500mg cap — 1 PO q8h × 7 days (after food)".
     */
    public function defaultLine(): string
    {
        return static::composeLine([
            'name' => $this->name,
            'strength' => $this->strength,
            'form' => $this->form,
            'route' => $this->route,
            'dose' => $this->default_dose,
            'frequency' => $this->default_frequency,
            'duration' => $this->default_duration,
            'instructions' => $this->default_instructions,
        ]);
    }

    /**
     * Compose a prescription line from parts. Shared by the model default and
     * the API so the builder and the catalog format lines identically.
     */
    public static function composeLine(array $p): string
    {
        $head = trim(implode(' ', array_filter([
            $p['name'] ?? null,
            $p['strength'] ?? null,
            $p['form'] ?? null,
        ])));

        $sig = trim(implode(' ', array_filter([
            $p['dose'] ?? null,
            $p['route'] ?? null,
            $p['frequency'] ?? null,
        ])));

        $duration = trim((string) ($p['duration'] ?? ''));
        if ($duration !== '') {
            $sig = trim($sig.' × '.$duration);
        }

        $line = $sig !== '' ? trim($head.' — '.$sig) : $head;

        $instructions = trim((string) ($p['instructions'] ?? ''));
        if ($instructions !== '') {
            $line .= ' ('.$instructions.')';
        }

        return $line;
    }
}
