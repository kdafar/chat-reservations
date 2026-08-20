<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a brand-new clinic needs to start working — and nothing else.
 *
 *     php artisan db:seed --class=BootstrapSeeder
 *
 * This is the entry point for a fresh install. DatabaseSeeder is the LEGACY
 * path: it additionally pulls in demo patients, bookings, discounts and pitch
 * data, which is fine for a sales demo and wrong for a real clinic.
 *
 * The split that makes one repo serve many clinics:
 *
 *   SHARED (identical everywhere, seeded below)
 *     permissions, roles, geography, chart of accounts, posting roles,
 *     lab test catalogue, drug formulary, clinical phrase library
 *
 *   PER-TENANT (config/tenant.php, from .env)
 *     partner, first branch + room, service catalogue, staff users,
 *     public-site brand copy
 *
 *   PER-DEPLOY (not seeded — `php artisan brand:generate`)
 *     favicons, app icons, logo files
 *
 * Order matters: permissions before roles, roles before staff, accounts before
 * the posting map, and TenantSetupSeeder last because it needs roles to exist.
 *
 * Every seeder called here is idempotent, so re-running is safe.
 */
class BootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Bootstrapping a clinic install (no demo data)...');

        // ---- Access control -------------------------------------------------
        // The permission catalogue must exist before any role can be granted.
        $this->call([
            ClinicFilamentPermissionSeeder::class,
            ClinicReportPermissionsSeeder::class,
            ClinicRoleStructureSeeder::class,
            ClinicLabRoleSeeder::class,
        ]);

        // ---- Reference data -------------------------------------------------
        // Geography first: the branch resolves its city against it.
        $this->call([
            KuwaitGeoSeeder::class,
            LabTestSeeder::class,
            MedicationSeeder::class,
            ClinicalPhraseSeeder::class,
        ]);

        // ---- Accounting -----------------------------------------------------
        // Without the posting map the auto-posting observers resolve nothing
        // and silently skip every journal entry.
        $this->call([
            AccountingChartOfAccountsSeeder::class,
            PostingAccountMapSeeder::class,
        ]);

        // ---- This install ---------------------------------------------------
        // Reads config/tenant.php; needs the roles above to already exist.
        $this->call([
            TenantSetupSeeder::class,
        ]);

        // Links the partner/branch to their GL accounts. After the tenant
        // seeder, so there is something to link.
        $this->call([
            PostingEntityLinksSeeder::class,
            ClinicRoomsBootstrapSeeder::class,
        ]);

        $this->command?->info('Bootstrap complete. Sign in and start entering real data.');
        $this->command?->warn('Branding assets are NOT seeded — run: php artisan brand:generate');
    }
}
