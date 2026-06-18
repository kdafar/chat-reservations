<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Translatable\HasTranslations;

class Partner extends Model
{
    use LogsClinicActivity;

    use HasTranslations;

    protected $fillable = ['name', 'slug', 'logo_path', 'is_active', 'website', 'email', 'license_number', 'footer_text', 'account_id'];

    public $translatable = ['name'];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
        'account_id' => 'integer',
    ];

    /** The clinic's default (services) revenue account — see ChartOfAccounts. */
    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'account_id');
    }

    /**
     * SAFE SCOPE: Filters Partners based on User Access.
     * Logic:
     * 1. If Admin -> Show All.
     * 2. If Linked via partner_user -> Show those.
     * 3. If Linked via branch_user -> Show the parent partners of those branches.
     */
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        // 1. Guard: No user or CLI context
        if (! $user) {
            return $query;
        }

        // 2. Guard: Admin Bypass (Matches your existing Logic in BelongsToBranchScope)
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return $query;
        }

        // 3. Get Direct Partner IDs (from partner_user pivot)
        $directIds = DB::table('partner_user')
            ->where('user_id', $user->id)
            ->pluck('partner_id');

        // 4. Get Indirect Partner IDs (from branch_user -> branches table)
        // This ensures if a user is assigned ONLY to a Branch, they can still "see" the parent Clinic.
        $indirectIds = DB::table('branch_user')
            ->join('branches', 'branch_user.branch_id', '=', 'branches.id')
            ->where('branch_user.user_id', $user->id)
            ->pluck('branches.partner_id');

        // 5. Merge and Unique
        $allIds = $directIds->merge($indirectIds)->unique()->filter();

        // 6. Apply Filter
        return $query->whereIn('id', $allIds);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'partner_user');
    }

    public function getNameLabelAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale());
    }

    public function branchIntegrations(): HasMany
    {
        return $this->hasMany(BranchIntegration::class);
    }

    public function gatewayAccounts()
    {
        return $this->hasMany(GatewayAccount::class, 'partner_id')
            ->where('owner_type', 'partner');
    }
}
