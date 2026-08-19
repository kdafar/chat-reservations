<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Eureka pitch demo — realistic Kuwait multi-specialty poly-clinic market.
 *
 * Builds a clean org tree for the /v2 admin:
 *   4 partners (clinic groups)  ->  3 branches each (12 total, real KW areas)
 *   -> 4 doctors per branch (48 total, general specialties + real KW names)
 *   -> one staff user per role per branch (manager / reception / nurse / accountant)
 *   -> one partner_owner per group (sees all 3 of its branches)
 *   -> one GROUP accountant that sees ALL 12 branches (not branch-restricted)
 *
 * It first REMOVES the old throwaway partners/branches/doctors/staff, then
 * rebuilds. The global catalog (items, packages, chart-of-accounts) is branch-
 * agnostic (branch_id = null) so it survives untouched.
 *
 * Operational volume (patients + ~40 days of visits/payments) is layered on
 * afterwards by ClinicFreshMonthSeeder, which this seeder calls last.
 *
 * Run:  php artisan db:seed --class=EurekaDemoSeeder
 *
 * All logins use the password:  password
 */
class EurekaDemoSeeder extends Seeder
{
    private const PASSWORD_HASH_PLAIN = 'password';

    private const EMAIL_DOMAIN = 'eureka.demo';

    /** Kuwaiti given names (male / female) + family names for realistic staff. */
    private const MALE = ['Ahmad', 'Yousef', 'Khalid', 'Abdullah', 'Fahad', 'Mohammed', 'Nasser', 'Bader', 'Faisal', 'Salem', 'Hamad', 'Mishari', 'Talal', 'Waleed', 'Saud'];

    private const FEMALE = ['Fatima', 'Noura', 'Sara', 'Maryam', 'Latifa', 'Hessa', 'Dalal', 'Reem', 'Aisha', 'Munira', 'Shaikha', 'Ghadeer', 'Amal', 'Wafa', 'Bibi'];

    private const FAMILY = ['Al-Sabah', 'Al-Rashidi', 'Al-Otaibi', 'Al-Mutairi', 'Al-Ajmi', 'Al-Azmi', 'Al-Enezi', 'Al-Dosari', 'Al-Hajri', 'Al-Shammari', 'Al-Qahtani', 'Al-Fadhli', 'Al-Kandari', 'Al-Sanea', 'Al-Failakawi', 'Al-Duaij', 'Al-Roudan'];

    /** Poly-clinic specialties with a realistic KWD consultation fee. */
    private const SPECIALTIES = [
        ['en' => 'General Practice', 'ar' => 'طب عام', 'fee' => 10.000],
        ['en' => 'Dermatology', 'ar' => 'الجلدية', 'fee' => 15.000],
        ['en' => 'Pediatrics', 'ar' => 'طب الأطفال', 'fee' => 12.000],
        ['en' => 'ENT', 'ar' => 'أنف وأذن وحنجرة', 'fee' => 15.000],
        ['en' => 'Internal Medicine', 'ar' => 'الباطنية', 'fee' => 18.000],
        ['en' => 'Obstetrics & Gynecology', 'ar' => 'النساء والولادة', 'fee' => 20.000],
        ['en' => 'Orthopedics', 'ar' => 'العظام', 'fee' => 20.000],
        ['en' => 'Ophthalmology', 'ar' => 'العيون', 'fee' => 18.000],
        ['en' => 'Dentistry', 'ar' => 'طب الأسنان', 'fee' => 12.000],
        ['en' => 'Cardiology', 'ar' => 'القلب', 'fee' => 25.000],
    ];

    /**
     * 4 clinic groups, each with 3 branches in real Kuwait areas and a
     * per-branch specialty offset so the 48 doctors spread across specialties.
     */
    private const GROUPS = [
        [
            'name' => ['en' => 'Al Salam International Medical Center', 'ar' => 'مركز السلام الطبي الدولي'],
            'slug' => 'al-salam-medical', 'short' => 'salam',
            'owner' => ['en' => 'Dr. Yousef Al-Rashidi', 'ar' => 'د. يوسف الرشيدي'],
            'branches' => [
                ['en' => 'Al Salam — Salmiya', 'ar' => 'السلام — السالمية', 'area' => 'Salmiya', 'phone' => '25710001'],
                ['en' => 'Al Salam — Hawally', 'ar' => 'السلام — حولي', 'area' => 'Hawally', 'phone' => '22610002'],
                ['en' => 'Al Salam — Jabriya', 'ar' => 'السلام — الجابرية', 'area' => 'Jabriya', 'phone' => '25340003'],
            ],
        ],
        [
            'name' => ['en' => 'Kuwait Family Care Polyclinic', 'ar' => 'عيادات العناية العائلية الكويتية'],
            'slug' => 'kuwait-family-care', 'short' => 'family',
            'owner' => ['en' => 'Fatima Al-Sabah', 'ar' => 'فاطمة الصباح'],
            'branches' => [
                ['en' => 'Family Care — Farwaniya', 'ar' => 'العناية — الفروانية', 'area' => 'Farwaniya', 'phone' => '24720011'],
                ['en' => 'Family Care — Khaitan', 'ar' => 'العناية — خيطان', 'area' => 'Khaitan', 'phone' => '24730012'],
                ['en' => 'Family Care — Jleeb Al-Shuyoukh', 'ar' => 'العناية — جليب الشيوخ', 'area' => 'Jleeb Al-Shuyoukh', 'phone' => '24340013'],
            ],
        ],
        [
            'name' => ['en' => 'Al Noor Specialized Clinics', 'ar' => 'عيادات النور التخصصية'],
            'slug' => 'al-noor-clinics', 'short' => 'noor',
            'owner' => ['en' => 'Khalid Al-Otaibi', 'ar' => 'خالد العتيبي'],
            'branches' => [
                ['en' => 'Al Noor — Fahaheel', 'ar' => 'النور — الفحيحيل', 'area' => 'Fahaheel', 'phone' => '23910021'],
                ['en' => 'Al Noor — Mangaf', 'ar' => 'النور — المنقف', 'area' => 'Mangaf', 'phone' => '23720022'],
                ['en' => 'Al Noor — Fintas', 'ar' => 'النور — الفنطاس', 'area' => 'Fintas', 'phone' => '23900023'],
            ],
        ],
        [
            'name' => ['en' => 'Bayan Medical Group', 'ar' => 'مجموعة بيان الطبية'],
            'slug' => 'bayan-medical', 'short' => 'bayan',
            'owner' => ['en' => 'Noura Al-Mutairi', 'ar' => 'نورة المطيري'],
            'branches' => [
                ['en' => 'Bayan — Kuwait City', 'ar' => 'بيان — مدينة الكويت', 'area' => 'Kuwait City', 'phone' => '22400031'],
                ['en' => 'Bayan — Sharq', 'ar' => 'بيان — شرق', 'area' => 'Sharq', 'phone' => '22410032'],
                ['en' => 'Bayan — Bayan', 'ar' => 'بيان — بيان', 'area' => 'Bayan', 'phone' => '25390033'],
            ],
        ],
    ];

    /** Rooms per branch (consultation rooms). */
    private const ROOMS_PER_BRANCH = 5;

    /** Monotonic counter guaranteeing globally-unique generated emails. */
    private int $seq = 0;

    public function run(): void
    {
        $this->command->info('=== EurekaDemoSeeder: building Kuwait poly-clinic market ===');

        $this->wipeOldOrg();

        $mainAccountantBranchIds = [];
        $credentials = [];

        DB::transaction(function () use (&$mainAccountantBranchIds, &$credentials) {
            $specIndex = 0; // rolls across all branches so specialties vary

            foreach (self::GROUPS as $g) {
                $partner = Partner::create([
                    'name' => $g['name'],
                    'slug' => $g['slug'],
                    'email' => 'info@'.$g['short'].'.'.self::EMAIL_DOMAIN,
                    'website' => 'https://'.$g['short'].'.example.kw',
                    'license_number' => 'MOH-'.strtoupper($g['short']).'-'.random_int(1000, 9999),
                    'is_active' => true,
                ]);

                // ---- Partner owner (sees all 3 branches via partner_user) ----
                $ownerEmail = 'owner.'.$g['short'].'@'.self::EMAIL_DOMAIN;
                $owner = $this->makeUser($g['owner'], $ownerEmail, 'partner_owner');
                DB::table('partner_user')->insertOrIgnore([
                    'partner_id' => $partner->id,
                    'user_id' => $owner->id,
                    'role' => 'owner',
                ]);
                $credentials[] = ['Group Owner — '.$g['name']['en'], $ownerEmail];

                foreach ($g['branches'] as $b) {
                    $branchSlug = $g['short'].'-'.Str::slug($b['area']);
                    $branch = Branch::create([
                        'partner_id' => $partner->id,
                        'name' => ['en' => $b['en'], 'ar' => $b['ar']],
                        'slug' => $branchSlug,
                        'phone' => '+965'.$b['phone'],
                        'email' => Str::slug($b['area']).'@'.$g['short'].'.'.self::EMAIL_DOMAIN,
                        'license_number' => 'BR-'.strtoupper($g['short']).'-'.strtoupper(substr(Str::slug($b['area']), 0, 3)),
                        'address' => $b['area'].', Kuwait',
                        'is_hub' => false,
                        'is_available' => true,
                        'max_booking_days' => 30,
                    ]);

                    $mainAccountantBranchIds[] = $branch->id;

                    // ---- Rooms ----
                    for ($r = 1; $r <= self::ROOMS_PER_BRANCH; $r++) {
                        RestaurantTable::create([
                            'branch_id' => $branch->id,
                            'name' => 'Room '.$r,
                            'capacity' => 1,
                            'status' => 'available',
                        ]);
                    }

                    // ---- 4 doctors ----
                    for ($i = 0; $i < 4; $i++) {
                        $spec = self::SPECIALTIES[$specIndex % count(self::SPECIALTIES)];
                        $specIndex++;

                        $female = random_int(0, 1) === 1;
                        $first = $female ? self::FEMALE[array_rand(self::FEMALE)] : self::MALE[array_rand(self::MALE)];
                        $family = self::FAMILY[array_rand(self::FAMILY)];
                        $docName = 'Dr. '.$first.' '.$family;

                        $this->seq++;
                        $docSlug = Str::slug($first.'-'.$family).'-'.$this->seq;
                        $docEmail = $docSlug.'@'.self::EMAIL_DOMAIN;
                        $docUser = $this->makeUser(
                            ['en' => $docName, 'ar' => 'د. '.$first.' '.$family],
                            $docEmail,
                            'clinic_doctor'
                        );

                        Doctor::create([
                            'partner_id' => $partner->id,
                            'branch_id' => $branch->id,
                            'user_id' => $docUser->id,
                            'name' => $docName,
                            'phone' => '+9656'.random_int(1000000, 9999999),
                            'email' => $docEmail,
                            'specialty' => $spec['en'],
                            'license_number' => 'DR-'.random_int(10000, 99999),
                            'consultation_fee' => $spec['fee'],
                            'working_hours' => $this->workingHours(),
                            'is_active' => true,
                        ]);

                        DB::table('branch_user')->insertOrIgnore([
                            'branch_id' => $branch->id,
                            'user_id' => $docUser->id,
                            'role' => 'clinic_doctor',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // ---- One staff user per role ----
                    $roles = [
                        'clinic_admin' => 'Branch Manager',
                        'clinic_reception' => 'Reception',
                        'clinic_nurse' => 'Nurse',
                        'accountant' => 'Branch Accountant',
                    ];
                    $roleSlug = ['clinic_admin' => 'manager', 'clinic_reception' => 'reception', 'clinic_nurse' => 'nurse', 'accountant' => 'accountant'];

                    foreach ($roles as $roleName => $label) {
                        $female = random_int(0, 1) === 1;
                        $first = $female ? self::FEMALE[array_rand(self::FEMALE)] : self::MALE[array_rand(self::MALE)];
                        $family = self::FAMILY[array_rand(self::FAMILY)];
                        $email = $roleSlug[$roleName].'.'.$branchSlug.'@'.self::EMAIL_DOMAIN;

                        $u = $this->makeUser(
                            ['en' => $first.' '.$family, 'ar' => $first.' '.$family],
                            $email,
                            $roleName
                        );
                        DB::table('branch_user')->insertOrIgnore([
                            'branch_id' => $branch->id,
                            'user_id' => $u->id,
                            'role' => $roleName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $this->command->info("  branch '{$b['en']}' -> 4 doctors + 4 staff");
                }
            }

            // ---- Group accountant: sees ALL 12 branches (not restricted) ----
            $groupAcctEmail = 'finance@'.self::EMAIL_DOMAIN;
            $groupAcct = $this->makeUser(
                ['en' => 'Group Finance — Head Office', 'ar' => 'المالية — المركز الرئيسي'],
                $groupAcctEmail,
                'accountant'
            );
            foreach ($mainAccountantBranchIds as $bid) {
                DB::table('branch_user')->insertOrIgnore([
                    'branch_id' => $bid,
                    'user_id' => $groupAcct->id,
                    'role' => 'accountant',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $credentials[] = ['GROUP Accountant (all 12 branches)', $groupAcctEmail];

            // Keep the real account owner (if present) able to see everything:
            // link them as owner of all 4 groups.
            $realOwner = User::where('email', 'bangimustaqeem6@gmail.com')->first();
            if ($realOwner) {
                foreach (Partner::pluck('id') as $pid) {
                    DB::table('partner_user')->updateOrInsert(
                        ['partner_id' => $pid, 'user_id' => $realOwner->id],
                        ['role' => 'owner']
                    );
                }
            }
        });

        $this->printCredentials($credentials);

        // ---- Each group needs a hub, or inter-branch transfers have no source ----
        $this->call(StockHubSeeder::class);

        // ---- Operational volume: patients + ~40 days of activity across all 12 branches ----
        $this->command->info('=== Layering activity via ClinicFreshMonthSeeder ===');
        $this->call(ClinicFreshMonthSeeder::class);

        $this->command->info('=== EurekaDemoSeeder done ===');
    }

    /**
     * Remove the previous throwaway org + its staff users. Keeps: super admin,
     * customers, and the real account owner (bangimustaqeem6). The global
     * catalog (branch_id = null) is untouched.
     */
    private function wipeOldOrg(): void
    {
        $this->command->info('Wiping old partners/branches/doctors/staff...');

        $keepEmails = ['admin@platform.com', 'bangimustaqeem6@gmail.com'];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Staff users tied to the old demo (by role), except the ones we keep.
        $staffRoleIds = DB::table('roles')
            ->whereIn('name', ['clinic_admin', 'clinic_doctor', 'clinic_reception', 'clinic_nurse', 'accountant'])
            ->pluck('id');
        $staffUserIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('role_id', $staffRoleIds)
            ->pluck('model_id')
            ->all();
        $staffUserIds = DB::table('users')
            ->whereIn('id', $staffUserIds)
            ->whereNotIn('email', $keepEmails)
            ->pluck('id')
            ->all();

        if (! empty($staffUserIds)) {
            DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $staffUserIds)->delete();
            DB::table('users')->whereIn('id', $staffUserIds)->delete();
        }

        // Org tables + scoping pivots.
        DB::table('doctors')->delete();
        DB::table('restaurant_tables')->delete();
        DB::table('branch_user')->delete();
        DB::table('partner_user')->delete();
        if (DB::getSchemaBuilder()->hasTable('partner_user_branch')) {
            DB::table('partner_user_branch')->delete();
        }
        DB::table('branches')->delete();
        DB::table('partners')->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('  removed '.count($staffUserIds).' old staff users + old org.');
    }

    private function makeUser(array $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name['en'],
                'password' => bcrypt(self::PASSWORD_HASH_PLAIN),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $user->forceFill(['status' => 'active', 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->syncRoles([$role]);

        return $user;
    }

    /** Sun–Thu 09:00–17:00 (Kuwait weekend = Fri). Day codes 0..6, Fri = 5 omitted. */
    private function workingHours(): array
    {
        $hours = [];
        foreach (['0', '1', '2', '3', '4', '6'] as $day) {
            $hours[] = ['day' => $day, 'start' => '09:00', 'end' => '17:00'];
        }

        return $hours;
    }

    private function printCredentials(array $rows): void
    {
        $this->command->info('');
        $this->command->info('================ DEMO LOGINS (password: password) ================');
        $this->command->info('Super admin (sees all):     admin@platform.com');
        foreach ($rows as [$label, $email]) {
            $this->command->info(str_pad($label, 42).$email);
        }
        $this->command->info('Per-branch staff pattern:   {manager|reception|nurse|accountant}.<group>-<area>@'.self::EMAIL_DOMAIN);
        $this->command->info('Doctors:                    <name>-<...>@'.self::EMAIL_DOMAIN.'  (see Doctors page)');
        $this->command->info('==================================================================');
    }
}
