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
use App\Models\Concerns\LogsClinicActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable, LogsClinicActivity;

    /** Never write credentials/secrets into the audit trail. */
    protected array $activityLogExcept = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_country_code',
        'status',
        'marketing_opt_in',
        'default_address_id',
        'preferred_locale',
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

    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
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
        if (property_exists($this, 'status') || array_key_exists('status', $this->attributes ?? [])) {
            if (($this->status ?? null) !== 'active') {
                return false;
            }
        }

        if ($panel->getId() === 'partner') {
            return \DB::table('partner_user')->where('user_id', $this->id)->exists();
        }

        if ($panel->getId() === 'admin' || $panel->getId() === 'whatsapp') {
            if (method_exists($this, 'roles')) {
                return $this->roles()->exists();
            }

            if (method_exists($this, 'hasRole')) {
                return true;
            }

            return false;
        }

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
