<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        if ($panel->getId() !== 'partner') {
            return true; // other panels use your own logic
        }

        // Only allow if user is linked to at least one partner
        return $this->partners()->exists();
    }

    public function branchLinks(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user');
    }
}
