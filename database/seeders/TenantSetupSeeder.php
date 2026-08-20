<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\RestaurantTable;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Creates the organisation for THIS install, entirely from config/tenant.php.
 *
 * This is the config-driven replacement for the old per-clinic setup seeders
 * (AlqiblaSetupSeeder and friends). The structure of a clinic — partner, first
 * branch, service catalogue, one user per role, public-site brand copy — is
 * identical everywhere; only the values differ, and those now live in .env.
 * A new clinic is a new .env and a new database, not a new git branch.
 *
 * Creates the organisation ONLY. No patients, visits, bookings, packages,
 * stock or accounting entries — the clinic enters its own. Demo data lives in
 * the *DemoSeeder classes and is deliberately not called from here.
 *
 * Run the shared product seeders first (roles, permissions, geo, chart of
 * accounts); BootstrapSeeder sequences all of it correctly.
 *
 * Idempotent — safe to re-run.
 */
class TenantSetupSeeder extends Seeder
{
    public function run(): void
    {
        $t = config('tenant');

        if (blank($t['staff']['password'] ?? null)) {
            $this->command?->error('TENANT_STAFF_PASSWORD is not set. Aborting rather than creating staff with a guessable password.');

            return;
        }

        if (blank($t['staff']['email_domain'] ?? null)) {
            $this->command?->error('TENANT_STAFF_EMAIL_DOMAIN is not set — staff emails would be malformed. Aborting.');

            return;
        }

        $partner = $this->partner($t);
        $branch = $this->branch($t, $partner);

        $this->services($t, $partner, $branch);
        $this->staff($t, $partner, $branch);
        $this->room($t, $branch);
        $this->publicSiteBranding($t);

        $this->command?->warn('Staff share one password from TENANT_STAFF_PASSWORD — have each person change it on first login.');
    }

    private function partner(array $t): Partner
    {
        $partner = Partner::withoutGlobalScopes()->firstOrCreate(
            ['slug' => $t['slug']],
            ['name' => $t['name'], 'is_active' => true],
        );

        $this->command?->info("partner #{$partner->id} {$t['name']['en']}");

        return $partner;
    }

    private function branch(array $t, Partner $partner): Branch
    {
        $b = $t['branch'];

        // City is matched loosely against whatever the geo seeder loaded; a
        // miss is not fatal, the clinic can set it in admin.
        $city = blank($b['city']) ? null
            : DB::table('cities')->where('name', 'like', '%'.$b['city'].'%')->first();

        $branch = Branch::withoutGlobalScopes()->firstOrCreate(
            ['slug' => $b['slug']],
            [
                'partner_id' => $partner->id,
                'name' => $b['name'],
                'address' => $b['address'],
                'city_id' => $city->id ?? null,
                // The first branch is the stock hub; inter-branch transfers
                // resolve against it, so something must hold the flag.
                'is_hub' => true,
                'is_available' => true,
                'max_booking_days' => $b['max_booking_days'],
            ],
        );

        $this->command?->info("branch #{$branch->id} {$b['name']['en']}"
            .($city ? " (city #{$city->id})" : ' (city not matched — set it in admin)'));

        return $branch;
    }

    private function services(array $t, Partner $partner, Branch $branch): void
    {
        foreach ($t['services'] as [$slug, $en, $ar]) {
            $id = DB::table('services')->where('slug', $slug)->value('id')
                ?? DB::table('services')->insertGetId([
                    'slug' => $slug,
                    'name' => json_encode(['en' => $en, 'ar' => $ar], JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('partner_service')->updateOrInsert(
                ['partner_id' => $partner->id, 'service_id' => $id], []
            );
            DB::table('branch_service')->updateOrInsert(
                ['branch_id' => $branch->id, 'service_id' => $id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command?->info(count($t['services']).' services linked to the branch');
    }

    private function staff(array $t, Partner $partner, Branch $branch): void
    {
        $domain = $t['staff']['email_domain'];
        $password = Hash::make($t['staff']['password']);

        foreach ($t['staff']['accounts'] as $local => [$name, $roleName]) {
            $email = $local.'@'.$domain;

            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->command?->warn("role '{$roleName}' missing — run the role seeders first; skipping {$email}");

                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'status' => 'active'],
            );
            $user->syncRoles([$role]);

            DB::table('partner_user')->updateOrInsert(
                ['partner_id' => $partner->id, 'user_id' => $user->id],
                ['role' => $roleName]
            );
            DB::table('partner_user_branch')->updateOrInsert(
                ['user_id' => $user->id, 'branch_id' => $branch->id],
                ['role' => $roleName, 'created_at' => now(), 'updated_at' => now()]
            );

            $this->command?->info("  {$email} -> {$roleName}");

            // A clinic_doctor user without a Doctor record cannot be booked.
            if ($roleName === 'clinic_doctor') {
                $doctor = Doctor::withoutGlobalScopes()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'partner_id' => $partner->id,
                        'branch_id' => $branch->id,
                        'name' => $name,
                        'default_slot_minutes' => 30,
                        'is_active' => true,
                    ],
                );
                $this->command?->info($doctor->wasRecentlyCreated
                    ? '    + Doctor record created'
                    : '    · Doctor record already present');
            }
        }
    }

    /**
     * Every branch needs a consultation room: BookingsController only offers
     * doctors that have one, so a roomless install has no bookable doctors at
     * all. Create it here and put the starter doctor in it.
     */
    private function room(array $t, Branch $branch): void
    {
        $room = RestaurantTable::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branch->id, 'name' => $t['branch']['default_room_name']],
            ['capacity' => 1, 'status' => 'available'],
        );

        Doctor::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->whereNull('restaurant_table_id')
            ->update(['restaurant_table_id' => $room->id]);

        $this->command?->info("room #{$room->id} {$room->name} (doctors without a room assigned to it)");
    }

    /**
     * Public-site brand copy, read by the React shell as window.__CLINIC__.
     *
     * Not cosmetic: resources/js/clinic/brand.js falls back to its own defaults
     * whenever a key is blank, so an empty settings table serves someone else's
     * branding. Blank phone/whatsapp is intentional though — brand.js hides the
     * "Call" buttons and shows "Book Now" until real numbers are entered.
     */
    private function publicSiteBranding(array $t): void
    {
        $settings = [
            'name_en' => $t['name']['en'],
            'name_ar' => $t['name']['ar'],
            'tagline_en' => $t['tagline']['en'],
            'tagline_ar' => $t['tagline']['ar'],
            'address_en' => $t['address']['en'],
            'address_ar' => $t['address']['ar'],
            'email' => $t['contact']['email'],
            'website' => $t['contact']['website'],
            'phone' => $t['contact']['phone'],
            'whatsapp' => $t['contact']['whatsapp'],
            'instagram' => $t['social']['instagram'],
            'tiktok' => $t['social']['tiktok'],
            'snapchat' => $t['social']['snapchat'],
        ];

        $written = 0;

        foreach ($settings as $key => $value) {
            // Don't overwrite something the clinic has already edited in admin
            // with a blank from an unfilled .env key.
            if (blank($value) && filled(SystemSetting::where('key', 'clinic.public.'.$key)->value('value'))) {
                continue;
            }

            SystemSetting::updateOrCreate(
                ['key' => 'clinic.public.'.$key],
                ['value' => (string) $value],
            );
            $written++;
        }

        $this->command?->info("{$written} public-site branding settings written");
    }
}
