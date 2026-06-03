<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Bulk-import staff users. Matches by email (the login key), so re-importing a
 * corrected file updates the same person. Roles are referenced by name and
 * synced after save. Passwords: provide one to set it, or leave blank and a
 * random password is generated for new users (they reset it via "forgot
 * password"); blank on an existing user leaves their current password alone.
 */
class UserImport extends AbstractImport
{
    public function slug(): string { return 'users'; }
    public function title(): string { return 'Users'; }
    public function model(): string { return User::class; }

    /** Creating users (with roles) is admin-only. */
    public function authorize(?object $user): bool
    {
        return (bool) ($user && $user->hasRole(['admin', 'super_admin']));
    }

    public function columns(): array
    {
        return [
            ImportColumn::make('name', 'Name')->required()->rules(['string', 'max:255'])->example('Sara Admin'),
            ImportColumn::make('email', 'Email')->required()->rules(['email', 'max:191'])->note('Unique login key')->example('sara@clinic.com'),
            ImportColumn::make('phone', 'Phone')->rules(['string', 'max:32']),
            ImportColumn::make('status', 'Status')->required()->allowed(['active', 'inactive', 'suspended']),
            ImportColumn::make('roles', 'Roles')->note('Comma-separated role names that already exist')->example('receptionist, accountant'),
            ImportColumn::make('password', 'Password')->rules(['string', 'min:8'])->note('Optional — blank generates a random password for new users'),
        ];
    }

    public function instructions(): array
    {
        return [
            'Roles must already exist (see the Roles screen). Separate multiple roles with commas.',
            'Leave Password blank to auto-generate one for new users; existing users keep their password unless you set a new one.',
        ];
    }

    public function exampleRows(): array
    {
        return [['name' => 'Sara Admin', 'email' => 'sara@clinic.com', 'status' => 'active', 'roles' => 'receptionist']];
    }

    public function mapRow(array $row): array
    {
        // Validate roles up-front so a bad role fails the row before any write.
        foreach ($this->parseRoles($row) as $name) {
            if (! \Illuminate\Support\Facades\DB::table('roles')->where('name', $name)->exists()) {
                $this->fail("Unknown role \"{$name}\".");
            }
        }

        $attrs = [
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?: null,
            'status' => $row['status'],
        ];
        // Plain password — the model's 'hashed' cast hashes it. Only set when given.
        if (! empty($row['password'])) {
            $attrs['password'] = $row['password'];
        }

        return $attrs;
    }

    protected function fillForCreate(array $attrs, array $row): array
    {
        if (empty($attrs['password'])) {
            $attrs['password'] = Str::random(14);
        }

        return $attrs;
    }

    protected function afterSave(object $model, array $row, bool $created): void
    {
        $roles = $this->parseRoles($row);
        if ($roles || $created) {
            $model->syncRoles($roles);
        }
    }

    protected function parseRoles(array $row): array
    {
        return collect(explode(',', (string) ($row['roles'] ?? '')))
            ->map(fn ($r) => trim($r))->filter()->unique()->values()->all();
    }

    public function matchAttributes(array $attrs): array
    {
        return ['email' => $attrs['email']];
    }
}
