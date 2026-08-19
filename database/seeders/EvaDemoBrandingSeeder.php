<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-skins the demo org chart from the generic medical template it was seeded
 * from into an aesthetic-clinic group.
 *
 * BeautyClinicDataSeeder rebrands the *catalog* (items, services, formulary).
 * This handles the things a visitor actually reads first on the public site and
 * that were still saying "Polyclinic" and "Pediatrics": clinic group names,
 * branch names, doctor specialties, and the map coordinates the branch page
 * needs. Everything is keyed by slug/id and updated in place, so bookings,
 * visits and invoices that already reference these rows stay valid.
 *
 * Demo data only — a live install would carry the clinic's real details.
 */
class EvaDemoBrandingSeeder extends Seeder
{
    /** Clinic groups: template medical names → aesthetic brands. */
    private const PARTNERS = [
        'al-salam-medical' => ['EVA Aesthetics', 'إيفا للتجميل', 'eva-aesthetics'],
        'kuwait-family-care' => ['Lumière Skin & Laser', 'لوميير للبشرة والليزر', 'lumiere-skin-laser'],
        'al-noor-clinics' => ['Noor Beauty Clinics', 'عيادات نور للتجميل', 'noor-beauty'],
        'bayan-medical' => ['Bayan Aesthetic Group', 'مجموعة بيان للتجميل', 'bayan-aesthetic'],
    ];

    /**
     * Branches: current slug => [brand, area en, area ar, new slug, lat, lng].
     *
     * The areas were already real Kuwait districts and are kept — only the
     * clinic half of the name changes. Coordinates are approximate district
     * centres, good enough to place a demo pin.
     */
    private const BRANCHES = [
        'salam-salmiya'            => ['EVA Aesthetics', 'Salmiya', 'السالمية', 'eva-salmiya', 29.3339, 48.0783],
        'salam-hawally'            => ['EVA Aesthetics', 'Hawally', 'حولي', 'eva-hawally', 29.3326, 48.0289],
        'salam-jabriya'            => ['EVA Aesthetics', 'Jabriya', 'الجابرية', 'eva-jabriya', 29.3167, 48.0219],
        'family-farwaniya'         => ['Lumière', 'Farwaniya', 'الفروانية', 'lumiere-farwaniya', 29.2775, 47.9589],
        'family-khaitan'           => ['Lumière', 'Khaitan', 'خيطان', 'lumiere-khaitan', 29.2861, 47.9553],
        'family-jleeb-al-shuyoukh' => ['Lumière', 'Jleeb Al-Shuyoukh', 'جليب الشيوخ', 'lumiere-jleeb', 29.2661, 47.9236],
        'noor-fahaheel'            => ['Noor Beauty', 'Fahaheel', 'الفحيحيل', 'noor-fahaheel', 29.0826, 48.1289],
        'noor-mangaf'              => ['Noor Beauty', 'Mangaf', 'المنقف', 'noor-mangaf', 29.0972, 48.1306],
        'noor-fintas'              => ['Noor Beauty', 'Fintas', 'الفنطاس', 'noor-fintas', 29.1747, 48.1211],
        'bayan-kuwait-city'        => ['Bayan Aesthetic', 'Kuwait City', 'مدينة الكويت', 'bayan-kuwait-city', 29.3759, 47.9774],
        'bayan-sharq'              => ['Bayan Aesthetic', 'Sharq', 'شرق', 'bayan-sharq', 29.3797, 47.9925],
        'bayan-bayan'              => ['Bayan Aesthetic', 'Bayan', 'بيان', 'bayan-bayan', 29.3036, 48.0431],
    ];

    /** Arabic form of each branch brand, so RTL names don't stay half-English. */
    private const BRAND_AR = [
        'EVA Aesthetics' => 'إيفا للتجميل',
        'Lumière' => 'لوميير',
        'Noor Beauty' => 'نور للتجميل',
        'Bayan Aesthetic' => 'بيان للتجميل',
    ];

    /**
     * Doctor specialties. The column is a plain string (not translatable), so
     * the public site maps these to Arabic via SPECIALTY_AR in
     * resources/js/clinic/brand.js — keep the two lists in step.
     */
    private const SPECIALTIES = [
        'General Practice' => 'Aesthetic Medicine',
        'Dermatology' => 'Cosmetic Dermatology',
        'Pediatrics' => 'Laser & Hair Removal',
        'ENT' => 'Injectables & Fillers',
        'Internal Medicine' => 'Skin & Wellness',
        'Obstetrics & Gynecology' => 'Body Contouring',
        'Orthopedics' => 'Hair Restoration',
        'Ophthalmology' => 'Lashes & Brows',
        'Dentistry' => 'Facial Aesthetics',
        'Cardiology' => 'Anti-Aging Medicine',
    ];

    public function run(): void
    {
        $this->rebrandPartners();
        $this->rebrandBranches();
        $this->rebrandDoctors();

        $this->command?->info('Demo branding updated: clinic groups, branches, coordinates and doctor specialties.');
    }

    private function rebrandPartners(): void
    {
        foreach (self::PARTNERS as $slug => [$en, $ar, $newSlug]) {
            DB::table('partners')->where('slug', $slug)->update([
                'name' => json_encode(['en' => $en, 'ar' => $ar], JSON_UNESCAPED_UNICODE),
                'slug' => $newSlug,
                'updated_at' => now(),
            ]);
        }
    }

    private function rebrandBranches(): void
    {
        foreach (self::BRANCHES as $slug => [$brand, $areaEn, $areaAr, $newSlug, $lat, $lng]) {
            DB::table('branches')->where('slug', $slug)->update([
                'name' => json_encode([
                    'en' => "{$brand} — {$areaEn}",
                    'ar' => (self::BRAND_AR[$brand] ?? $brand)." — {$areaAr}",
                ], JSON_UNESCAPED_UNICODE),
                'slug' => $newSlug,
                'latitude' => $lat,
                'longitude' => $lng,
                'updated_at' => now(),
            ]);
        }
    }

    private function rebrandDoctors(): void
    {
        foreach (self::SPECIALTIES as $from => $to) {
            DB::table('doctors')->where('specialty', $from)->update([
                'specialty' => $to,
                'updated_at' => now(),
            ]);
        }
    }
}
