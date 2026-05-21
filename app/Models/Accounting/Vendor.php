<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payee / supplier the clinic incurs expenses with (landlord, utility
 * provider, marketing agency, supply distributor, etc.). Holds default
 * account hints so creating an Expense is a one-click experience.
 */
class Vendor extends Model
{
    use SoftDeletes;

    protected $table = 'vendors';

    protected $fillable = [
        'name',
        'code',
        'contact_name',
        'phone',
        'email',
        'address',
        'tax_number',
        'default_account_id',
        'default_payable_account_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function defaultPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_payable_account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'vendor_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->code
            ? "{$this->code} — {$this->name}"
            : (string) $this->name;
    }
}
