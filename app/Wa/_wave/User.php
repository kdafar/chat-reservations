<?php

namespace Wave;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Minimal shim of Wave\User for the isolated WhatsApp module.
 *
 * The real Wave\User pulls in Devdojo Auth, JWT, Impersonate, subscriptions, etc.
 * The WhatsApp module only needs: id, is_admin, role helpers (hasRole/assignRole/
 * syncRoles via Spatie), avatar, the whatsapp_* credential columns, and Filament
 * panel access. This base provides exactly that, on the module `wa` connection.
 */
abstract class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasRoles;
    use Notifiable;

    protected $connection = 'wa';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The guard used by Spatie permissions for module users.
     */
    protected $guard_name = 'wa';

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ?: null;
    }

    /**
     * is_admin convenience used throughout the WhatsApp code.
     */
    public function getIsAdminAttribute(): bool
    {
        if (array_key_exists('is_admin', $this->attributes)) {
            return (bool) $this->attributes['is_admin'];
        }

        return $this->hasRole('admin');
    }
}
