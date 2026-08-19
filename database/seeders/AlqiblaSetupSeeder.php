<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Real setup for the Alqibla Clinic Center install (alqibla.majestic-kw.com).
 *
 * Creates the organisation only -- partner, one branch, the specialty catalogue
 * and one staff user per role. It deliberately creates NO patients, visits,
 * bookings, packages, stock or accounting entries: the clinic enters its own.
 *
 * Idempotent -- safe to re-run. Staff passwords come from ALQIBLA_STAFF_PASSWORD
 * so nothing is hard-coded in the repo.
 *
 * Requires first: ClinicRoleStructureSeeder, ClinicFilamentPermissionSeeder,
 * ClinicReportPermissionsSeeder, ClinicLabRoleSeeder, KuwaitGeoSeeder.
 */
class AlqiblaSetupSeeder extends Seeder
{
    /** Cosmetic / laser / dermatology lines, one per treatment floor area. */
    private const SERVICES = [
        ['cosmetic-consultation', 'Cosmetic Consultation',    'استشارة تجميلية'],
        ['dermatology',           'Dermatology',              'الجلدية'],
        ['laser-hair-removal',    'Laser & Hair Removal',     'الليزر وإزالة الشعر'],
        ['injectables-fillers',   'Injectables & Fillers',    'الحقن والفيلر'],
        ['skin-treatments',       'Facial & Skin Treatments', 'علاجات البشرة والوجه'],
        ['body-contouring',       'Body Contouring',          'نحت الجسم'],
        ['hair-transplant',       'Hair Transplant & Therapy', 'زراعة وعلاج الشعر'],
        ['anti-aging',            'Anti-Ageing',              'مكافحة الشيخوخة'],
        ['general-medicine',      'General Medicine',         'الطب العام'],
        ['laboratory',            'Laboratory',               'المختبر والتحاليل'],
    ];

    /** email-local => [display name, role, arabic name] */
    private const STAFF = [
        'admin'      => ['Clinic Administrator', 'clinic_admin',     'مدير المركز'],
        'reception'  => ['Reception',            'clinic_reception', 'الاستقبال'],
        'doctor'     => ['Dr. Alqibla',          'clinic_doctor',    'طبيب'],
        'nurse'      => ['Nurse',                'clinic_nurse',     'ممرضة'],
        'accountant' => ['Accountant',           'accountant',       'المحاسب'],
        'lab'        => ['Lab Technician',       'clinic_lab',       'فني المختبر'],
    ];

    private const EMAIL_DOMAIN = 'alqiblaclinic.com';

    public function run(): void
    {
        $password = env('ALQIBLA_STAFF_PASSWORD');

        if (blank($password)) {
            $this->command?->error('ALQIBLA_STAFF_PASSWORD is not set. Aborting rather than creating staff with a guessable password.');

            return;
        }

        $partner = Partner::firstOrCreate(
            ['slug' => 'alqibla-clinic-center'],
            [
                'name' => ['en' => 'Alqibla Clinic Center', 'ar' => 'مركز عيادات القبلة'],
                'is_active' => true,
            ]
        );
        $this->command?->info("partner #{$partner->id} Alqibla Clinic Center");

        // Sabah Al-Salem comes from KuwaitGeoSeeder (Mubarak Al-Kabeer governorate).
        $city = DB::table('cities')->where('name', 'like', '%Sabah Al-Salem%')->first()
             ?? DB::table('cities')->where('name', 'like', '%Sabah%')->first();

        $branch = Branch::firstOrCreate(
            ['slug' => 'alqibla-sabah-al-salem'],
            [
                'partner_id' => $partner->id,
                'name' => [
                    'en' => 'Alqibla Clinic Center — Sabah Al Salem',
                    'ar' => 'مركز عيادات القبلة — صباح السالم',
                ],
                'address' => 'Sabah Al Salem, Kuwait',
                'city_id' => $city->id ?? null,
                // The first branch is the stock hub; inter-branch transfers
                // resolve against it, so something must hold the flag.
                'is_hub' => true,
                'is_available' => true,
                'max_booking_days' => 30,
            ]
        );
        $this->command?->info("branch #{$branch->id} Sabah Al Salem".($city ? " (city #{$city->id})" : ' (city not matched -- set it in admin)'));

        // Specialty catalogue, linked to both the partner and the branch.
        foreach (self::SERVICES as [$slug, $en, $ar]) {
            $service = DB::table('services')->where('slug', $slug)->first();

            if (! $service) {
                $id = DB::table('services')->insertGetId([
                    'slug' => $slug,
                    'name' => json_encode(['en' => $en, 'ar' => $ar], JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $id = $service->id;
            }

            DB::table('partner_service')->updateOrInsert(
                ['partner_id' => $partner->id, 'service_id' => $id],
                []
            );
            DB::table('branch_service')->updateOrInsert(
                ['branch_id' => $branch->id, 'service_id' => $id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
        $this->command?->info(count(self::SERVICES).' services linked to the branch');

        // One staff user per role, scoped to this partner and branch.
        foreach (self::STAFF as $local => [$name, $roleName, $ar]) {
            $email = $local.'@'.self::EMAIL_DOMAIN;

            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->command?->warn("role '{$roleName}' missing -- run the role seeders first; skipping {$email}");

                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make($password), 'status' => 'active']
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

            // The doctor role needs a Doctor record or it cannot be booked.
            if ($roleName === 'clinic_doctor') {
                Doctor::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'partner_id' => $partner->id,
                        'branch_id' => $branch->id,
                        'name' => $name,
                        'specialty' => 'Cosmetic Dermatology',
                        'default_slot_minutes' => 30,
                        'is_active' => true,
                    ]
                );
                $this->command?->info('    + Doctor record created');
            }
        }

        $this->seedPublicSiteBranding();

        $this->command?->warn('Staff share one password from ALQIBLA_STAFF_PASSWORD -- have each person change it on first login.');
    }

    /**
     * Public-site brand copy (v2 Settings -> Public Website), injected into the
     * React shell as window.__CLINIC__.
     *
     * These are not cosmetic extras: resources/js/clinic/brand.js falls back to
     * EVA Medical's name, email and website whenever a key is blank, so an empty
     * settings table serves an EVA-branded site. Every key that has an EVA
     * fallback must be filled.
     *
     * Blank phone/whatsapp is intentional -- brand.js hides the "Call" buttons
     * and shows "Book Now" instead until real numbers are entered in admin.
     */
    private function seedPublicSiteBranding(): void
    {
        $settings = [
            'name_en' => 'Alqibla Clinic Center',
            'name_ar' => 'مركز عيادات القبلة',
            'tagline_en' => 'Aesthetic, Laser & Dermatology',
            'tagline_ar' => 'التجميل والليزر والجلدية',
            'address_en' => 'Sabah Al Salem, Kuwait',
            'address_ar' => 'صباح السالم، الكويت',
            // Must be non-blank or brand.js substitutes the EVA defaults.
            'email' => 'info@'.self::EMAIL_DOMAIN,
            'website' => self::EMAIL_DOMAIN,
        ];

        foreach ($settings as $key => $value) {
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => 'clinic.public.'.$key],
                ['value' => $value]
            );
        }

        $this->command?->info(count($settings).' public-site branding settings written');
    }
}
