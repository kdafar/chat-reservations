<?php

namespace Database\Seeders;

use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\ClinicPackageItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ready-to-sell package offers for the aesthetic catalog.
 *
 * These are the bundles an aesthetic clinic actually sells: multi-session
 * courses (laser hair removal, acne, pigmentation) and combination packages
 * (bridal, under-eye, face lift). Each one is built from real catalog services,
 * so adding it to a visit explodes into the right consumables and deducts stock.
 *
 * Pricing is honest by construction: the MAIN price is computed as the exact
 * sum of the component sessions at their own list prices — what the patient
 * would pay booking them one by one — and `offer` is the bundle price. The
 * "you save" figure shown on the website is therefore a real saving, not a
 * marked-up-then-discounted one.
 *
 * Idempotent: re-running updates the packages in place, matched on English name.
 *
 *   php artisan db:seed --class=ClinicPackageOffersSeeder
 */
class ClinicPackageOffersSeeder extends Seeder
{
    /**
     * Each package: component service names => number of sessions included,
     * plus the bundle price. Services are matched by their English catalog
     * name so the seeder survives id changes.
     */
    protected function definitions(): array
    {
        return [
            [
                'name' => ['en' => 'Laser Hair Removal — Full Body Course (6 sessions)', 'ar' => 'باقة الليزر — الجسم كامل (٦ جلسات)'],
                'description' => [
                    'en' => 'A complete six-session laser hair removal course for the full body — the number of sessions most clients need for lasting results, at well below the single-session price.',
                    'ar' => 'دورة كاملة من ست جلسات لإزالة الشعر بالليزر للجسم بالكامل — عدد الجلسات الذي تحتاجه معظم العميلات لنتيجة دائمة، بسعر أقل بكثير من حجز الجلسات منفردة.',
                ],
                'offer' => 149.000,
                'items' => ['LHR — Full body (ladies, single)' => 6],
            ],
            [
                'name' => ['en' => 'Laser Hair Removal — Underarms & Bikini Course (6 sessions)', 'ar' => 'باقة الليزر — الإبطين والبكيني (٦ جلسات)'],
                'description' => [
                    'en' => 'Six sessions each for underarms and bikini — our most requested combination, bundled into one course price.',
                    'ar' => 'ست جلسات لكل من الإبطين ومنطقة البكيني — أكثر التركيبات طلبًا لدينا، بسعر دورة واحدة.',
                ],
                'offer' => 109.000,
                'items' => [
                    'LHR — Underarms (single session)' => 6,
                    'LHR — Bikini (standard, single)' => 6,
                ],
            ],
            [
                'name' => ['en' => 'Bridal Glow Package', 'ar' => 'باقة العروس'],
                'description' => [
                    'en' => 'Our pre-wedding programme: hydro-lifting injections, golden plasma for face and neck, a vitamin Dermapen session and a brightening peel — timed together for the big day.',
                    'ar' => 'برنامجنا لما قبل الزفاف: حقن الترطيب والشد، والبلازما الذهبية للوجه والرقبة، وجلسة ديرمابن بالفيتامينات، وتقشير للإشراق — منسّقة معًا استعدادًا ليومك الكبير.',
                ],
                'offer' => 139.000,
                'items' => [
                    'Hydro-lifting injections (Profhilo/Jalupro)' => 1,
                    'Golden plasma — face + neck (premium PRP)' => 1,
                    'Vitamin Dermapen (single)' => 1,
                    'Glycolic / Salicylic peel — single' => 1,
                ],
            ],
            [
                'name' => ['en' => 'Clear Skin — Acne Programme (4 sessions)', 'ar' => 'برنامج البشرة الصافية — حب الشباب (٤ جلسات)'],
                'description' => [
                    'en' => 'Four combined medical and peel sessions for active acne, spaced over the course of treatment rather than paid for one visit at a time.',
                    'ar' => 'أربع جلسات تجمع بين البروتوكول الدوائي والتقشير لعلاج حب الشباب النشط، موزّعة على مدة العلاج بدل دفع كل زيارة على حدة.',
                ],
                'offer' => 79.000,
                'items' => ['Active acne — combined medical + peel protocol' => 4],
            ],
            [
                'name' => ['en' => 'Acne Scar Revision Course', 'ar' => 'دورة علاج آثار حب الشباب'],
                'description' => [
                    'en' => 'Three Genius RF laser sessions plus one subcision for deep scars — the standard protocol for pitted acne scarring, bundled.',
                    'ar' => 'ثلاث جلسات ليزر Genius RF مع جلسة سبسيجن للندبات العميقة — البروتوكول المعتاد لآثار حب الشباب الغائرة، ضمن باقة واحدة.',
                ],
                'offer' => 75.000,
                'items' => [
                    'Acne scar / scar laser — Genius RF session' => 3,
                    'Subcision (deep scars) session' => 1,
                ],
            ],
            [
                'name' => ['en' => 'Hair Restoration Programme', 'ar' => 'برنامج إعادة إنبات الشعر'],
                'description' => [
                    'en' => 'A full hair loss diagnosis and treatment plan followed by four scalp PRP sessions — assessment and course together.',
                    'ar' => 'تشخيص كامل لتساقط الشعر مع خطة علاجية، تليها أربع جلسات بلازما لفروة الرأس — التقييم والدورة معًا.',
                ],
                'offer' => 95.000,
                'items' => [
                    'Hair loss diagnosis & plan' => 1,
                    'PRP hair scalp' => 4,
                ],
            ],
            [
                'name' => ['en' => 'Glow & Rejuvenation Course (3 sessions)', 'ar' => 'دورة النضارة والتجديد (٣ جلسات)'],
                'description' => [
                    'en' => 'Three skin rejuvenation mesotherapy sessions finished with a vitamin Dermapen — a gentle course for dull, tired skin.',
                    'ar' => 'ثلاث جلسات ميزوثيرابي لتجديد البشرة تُختتم بجلسة ديرمابن بالفيتامينات — دورة لطيفة للبشرة الباهتة والمُجهدة.',
                ],
                'offer' => 72.000,
                'items' => [
                    'Skin rejuvenation mesotherapy' => 3,
                    'Vitamin Dermapen (single)' => 1,
                ],
            ],
            [
                'name' => ['en' => 'Non-Surgical Face Lift Programme', 'ar' => 'برنامج شد الوجه بدون جراحة'],
                'description' => [
                    'en' => 'A full-face HIFU/RF lift combined with a PDO mono thread lift — tightening and support in one programme, no surgery and no downtime.',
                    'ar' => 'شد كامل للوجه بتقنية الهايفو/الترددات الراديوية مع شد بخيوط PDO المونو — الشد والدعم في برنامج واحد، بلا جراحة وبلا فترة نقاهة.',
                ],
                'offer' => 129.000,
                'items' => [
                    'HIFU / RF face lift (full face)' => 1,
                    'PDO thread lift (mono x 20)' => 1,
                ],
            ],
            [
                'name' => ['en' => 'Under-Eye Refresh', 'ar' => 'باقة إشراقة تحت العين'],
                'description' => [
                    'en' => 'Dark-circle treatment, under-eye mesotherapy and a tear-trough filler — the three steps that address hollowing and pigmentation together.',
                    'ar' => 'علاج الهالات السوداء، وميزوثيرابي تحت العين، وفيلر الأخدود الدمعي — الخطوات الثلاث التي تعالج الغؤور والتصبّغ معًا.',
                ],
                'offer' => 89.000,
                'items' => [
                    'Dark circles treatment (mesotherapy + topical)' => 1,
                    'Mesotherapy under-eye' => 1,
                    'Filler — tear trough (1ml)' => 1,
                ],
            ],
            [
                'name' => ['en' => 'Melasma & Pigmentation Course (4 sessions)', 'ar' => 'دورة علاج الكلف والتصبغات (٤ جلسات)'],
                'description' => [
                    'en' => 'Four Hollywood Spectra laser sessions for melasma and sun pigmentation — pigmentation needs a course, so it is priced as one.',
                    'ar' => 'أربع جلسات ليزر هوليوود سبكترا للكلف والتصبّغات الشمسية — التصبّغات تحتاج دورة كاملة، لذا سعّرناها كدورة.',
                ],
                'offer' => 59.000,
                'items' => ['Melasma / pigmentation laser — Hollywood Spectra session' => 4],
            ],
            [
                'name' => ['en' => 'Body Contouring Course (6 sessions)', 'ar' => 'دورة نحت الجسم (٦ جلسات)'],
                'description' => [
                    'en' => 'Six non-surgical contouring sessions combining radiofrequency and cavitation — the full course most areas require.',
                    'ar' => 'ست جلسات نحت غير جراحي تجمع بين الترددات الراديوية والكافيتيشن — الدورة الكاملة التي تحتاجها معظم المناطق.',
                ],
                'offer' => 139.000,
                'items' => ['Non-surgical contouring (RF + cavitation, single)' => 6],
            ],
            [
                'name' => ['en' => 'Lip Enhancement Duo', 'ar' => 'باقة نضارة وامتلاء الشفاه'],
                'description' => [
                    'en' => '1ml of lip filler paired with a brightening peel session — volume and even tone in one visit.',
                    'ar' => 'ملّيلتر واحد من فيلر الشفاه مع جلسة تقشير للإشراق — الامتلاء وتوحيد اللون في زيارة واحدة.',
                ],
                'offer' => 72.000,
                'items' => [
                    'Filler — lips (1ml)' => 1,
                    'Lip + intimate brightening (peel course session)' => 1,
                ],
            ],
        ];
    }

    public function run(): void
    {
        // Catalog lookup by English name. Global scopes are dropped so the
        // seeder behaves the same from CLI and from an authenticated context.
        $catalog = ClinicItem::query()->withoutGlobalScopes()
            ->where('is_active', true)
            ->get(['id', 'name', 'default_price'])
            ->keyBy(fn (ClinicItem $i) => $i->name['en'] ?? '');

        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($this->definitions() as $i => $def) {
            $lines = [];
            $mainPrice = 0.0;
            $missing = [];

            foreach ($def['items'] as $serviceName => $sessions) {
                $item = $catalog->get($serviceName);
                if (! $item) {
                    $missing[] = $serviceName;

                    continue;
                }
                $lines[] = ['id' => $item->id, 'qty' => (float) $sessions];
                $mainPrice += (float) $item->default_price * $sessions;
            }

            // A package missing a component would price wrong and bill wrong —
            // skip it loudly rather than publish a half-built bundle.
            if ($missing) {
                $skipped[] = $def['name']['en'].' (missing: '.implode(', ', $missing).')';

                continue;
            }

            $mainPrice = round($mainPrice, 3);

            if ($def['offer'] >= $mainPrice) {
                $skipped[] = $def['name']['en'].' (offer '.$def['offer'].' is not below main price '.$mainPrice.')';

                continue;
            }

            DB::transaction(function () use ($def, $lines, $mainPrice, $i, &$created, &$updated) {
                $existing = ClinicPackage::query()->withoutGlobalScopes()
                    ->where('name->en', $def['name']['en'])
                    ->first();

                $attributes = [
                    'partner_id' => null,   // global catalog, like the services it bundles
                    'branch_id' => null,    // available at every branch
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'default_price' => $mainPrice,
                    'discount_price' => $def['offer'],
                    'is_active' => true,
                    'is_public' => true,    // published to the website Offers page
                    'sort_order' => $i + 1,
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $package = $existing;
                    $updated++;
                } else {
                    $package = ClinicPackage::create($attributes);
                    $created++;
                }

                // Resync the bundle contents.
                ClinicPackageItem::query()->where('clinic_package_id', $package->id)->delete();
                foreach ($lines as $line) {
                    ClinicPackageItem::create([
                        'clinic_package_id' => $package->id,
                        'clinic_item_id' => $line['id'],
                        'qty_base' => $line['qty'],
                        'is_consumable' => true,
                    ]);
                }
            });
        }

        $this->command?->info("Package offers: {$created} created, {$updated} updated.");

        foreach ($skipped as $reason) {
            $this->command?->warn("Skipped — {$reason}");
        }
    }
}
