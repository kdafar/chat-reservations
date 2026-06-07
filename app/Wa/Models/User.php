<?php

namespace App\Wa\Models;

use App\Wa\Hub\Models\FleetMessageLog;
use App\Wa\Hub\Models\PointPurchase;
use App\Wa\Notifications\VerifyEmailNotification;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Wave\Traits\HasProfileKeyValues;
use Wave\User as WaveUser;

class User extends WaveUser
{
    use HasProfileKeyValues, Notifiable;

    public $guard_name = 'web';

    // optional: keep the default path in one place (used by accessor only)
    public const DEFAULT_AVATAR = 'apple-touch-icon.png';

    protected $appends = ['avatar_url'];

    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar',
        'password',
        'role_id',
        'verification_code',
        'verified',
        'trial_ends_at',
        'company_name',
        'phone_number',
        'logo_path',
        'subdomain',
        'whatsapp_access_token',
        'whatsapp_business_account_id',
        'preferred_locale',
        'last_login_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'verified' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'whatsapp_access_token',
    ];

    protected static function boot()
    {
        parent::boot();

        // Before creating
        static::creating(function (User $user) {
            // 1) Ensure username exists
            if (empty($user->username)) {
                $username = Str::slug($user->name, '');
                $i = 1;

                while (self::where('username', $username)->exists()) {
                    $username = Str::slug($user->name, '').$i;
                    $i++;
                }

                $user->username = $username;
            }

            //  DO NOT TOUCH $user->avatar HERE
            // Let it be null; display will use avatar_url accessor.
        });

        // After created: assign default role
        static::created(function (User $user) {
            $user->syncRoles([]);
            // $user->assignRole(config('wave.default_user_role', 'registered'));
        });

        // Before updating
        static::updating(function (User $user) {
            // 1) If email is changed, reset verification
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            //  DO NOT READ $user->avatar HERE EITHER
            // If you really want to normalize, do it only on display.
        });
    }

    public function getAvatarUrlAttribute(): string
    {
        // Use the DB value if present; otherwise fallback to your default
        $path = $this->attributes['avatar'] ?? self::DEFAULT_AVATAR;

        // Full URL already
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Stored in storage/app/public/avatars/... → /storage/avatars/...
        if (Str::startsWith($path, 'avatars/')) {
            return asset('storage/'.$path);
        }

        // Already a storage path
        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        // Plain filename (apple-touch-icon.png, demo/default.png, etc.) in /public
        return asset($path);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        // Use your computed accessor instead of calling Wave\User::avatar()
        return $this->avatar_url;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->roles()->exists(),
            'whatsapp' => $this->hasAnyRole(['admin', 'whatsapp_admin', 'whatsapp_agent']),
            'fleet' => $this->hasAnyRole(['admin', 'company', 'fleet_customer']),
            default => false,
        };
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function pointPurchases(): HasMany
    {
        return $this->hasMany(PointPurchase::class, 'user_id');
    }

    public function fleetMessageLogs(): HasMany
    {
        return $this->hasMany(FleetMessageLog::class, 'user_id');
    }

    public function getLocale(): string
    {
        return $this->preferred_locale ?: config('app.locale', 'en');
    }
}
