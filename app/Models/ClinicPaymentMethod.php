<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-configurable payment method offered in the visit payment modal.
 *
 * Scope is layered (most specific wins, deduped by `key` in the resolver):
 *   branch_id set            -> branch-specific override
 *   partner_id set, branch null -> clinic-wide default
 *   both null                -> global system default (all clinics)
 *
 * NOTE: deliberately NO BelongsToPartnerScope global scope here. Resolution is
 * explicit in ClinicPaymentMethodResolver, which must be able to read global
 * (null-partner) and clinic rows together to pick the winner. The Filament
 * resource is admin-gated, so cross-clinic visibility in the admin is intended.
 */
class ClinicPaymentMethod extends Model
{
    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'integer',
        'branch_id' => 'integer',
        'requires_reference' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Safety net: if a row is saved with a branch but no clinic, derive the
     * clinic from the branch so a branch override is never orphaned as global.
     */
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (empty($m->partner_id) && ! empty($m->branch_id)) {
                $m->partner_id = Branch::query()->whereKey($m->branch_id)->value('partner_id');
            }

            // One row per (partner, branch, key). A duplicate in the same scope
            // would make the resolver's required-reference rule ambiguous, so we
            // block it here (covers Filament + any code path; null-safe, which a
            // MySQL unique index is not for the global/clinic NULL scopes).
            $dupe = static::query()
                ->where('key', $m->key)
                ->when($m->getKey(), fn ($q) => $q->whereKeyNot($m->getKey()))
                ->where(fn ($q) => $m->partner_id === null
                    ? $q->whereNull('partner_id')
                    : $q->where('partner_id', $m->partner_id))
                ->where(fn ($q) => $m->branch_id === null
                    ? $q->whereNull('branch_id')
                    : $q->where('branch_id', $m->branch_id))
                ->exists();

            if ($dupe) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'key' => "A '{$m->key}' method already exists for this clinic/branch scope.",
                ]);
            }
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
