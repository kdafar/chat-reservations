<?php

namespace App\Models;

use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_country_code',
        'status',
        'marketing_opt_in',
        'default_address_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'marketing_opt_in' => 'boolean',
        ];
    }

    // Relations
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function defaultAddress()
    {
        return $this->belongsTo(Address::class, 'default_address_id');
    }

    // Helpers
    public function isActive(): bool
    {
        return in_array($this->status, ['active'], true);
    }

    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'partner_user');
    }

    // branches via a second pivot (we’ll add migration below)
    public function partnerBranches()
    {
        return $this->belongsToMany(Branch::class, 'partner_user_branch')
            ->withPivot('role') // owner|manager|staff|kitchen|finance
            ->withTimestamps();
    }

    public function hasPartnerRole(int $branchId, string $role): bool
    {
        return $this->partnerBranches()
            ->where('branches.id', $branchId)
            ->wherePivot('role', $role)
            ->exists();
    }

    // Gate Partner Panel access
    public function canAccessPanel(Panel $panel): bool
    {
        // Only apply this rule to the Filament "admin" panel.
        if ($panel->getId() !== 'admin') {
            return false;
        }

        // Optional: keep "status" safety if you use it (won't break if column missing)
        // If you are sure `status` exists and you want to block inactive users, keep it.
        if (property_exists($this, 'status') || array_key_exists('status', $this->attributes ?? [])) {
            if (($this->status ?? null) !== 'active') {
                return false;
            }
        }

        // If Spatie roles exist, allow access to anyone with at least one role.
        if (method_exists($this, 'roles')) {
            return $this->roles()->exists();
        }

        // If your project uses hasRole() helper:
        if (method_exists($this, 'hasRole')) {
            // Some implementations might not have roles relation visible, so be safe.
            // We cannot enumerate roles here; instead require that the roles relation exists.
            // If hasRole() exists but roles() doesn't, allow login (or tighten if you want).
            return true;
        }

        // No role system detected -> default deny (safer).
        return false;
    }

    public function branchLinks(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user');
    }

    public function doctorProfile(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function collectedVisitPayments(): HasMany
    {
        return $this->hasMany(VisitPayment::class, 'collected_by_user_id');
    }

    public function currentBranchId(): ?int
    {
        return $this->branchLinks()
            ->select('branches.id')
            ->value('branches.id');
    }
}
