<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-skins the seeded clinic catalog as a BEAUTY / AESTHETIC clinic.
 *
 * Done in place (by id / code) so existing visits, packages, lab orders and
 * accounting rows keep their foreign keys — only the labels, formulary and
 * phrase library change. Idempotent: safe to re-run.
 *
 *   php artisan db:seed --class=BeautyClinicDataSeeder
 */
class BeautyClinicDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->relabelClinicItems();
        $this->reskinServiceCategories();
        $this->reskinDoctors();
        $this->seedAestheticFormulary();
        $this->reskinLabPanel();
        $this->seedBeautyPhrases();

        $this->command?->info('Beauty clinic catalog applied.');
    }

    /**
     * Relabel the generic-medical clinic_items (consultations, nursing, vitals,
     * nebulizer, wound care, lab-as-service) to aesthetic treatments. Beauty
     * items already in the catalog (HydraFacial, Botox, Filler, PRP …) are left
     * untouched. Names are stored as {"en","ar"} translatable JSON.
     */
    private function relabelClinicItems(): void
    {
        // id => [en, ar, price, cost]
        $map = [
            1  => ['Aesthetic Consultation',                'استشارة تجميلية',                 15, 0],
            2  => ['Skin & Laser Specialist Consultation',  'استشارة أخصائي بشرة وليزر',        25, 0],
            3  => ['Treatment Follow-up',                   'متابعة العلاج',                    5,  0],
            4  => ['Aesthetic Nurse Service Fee',           'رسوم خدمة ممرضة التجميل',          5,  1],
            5  => ['Skin Analysis (Digital Scan)',          'تحليل البشرة (مسح رقمي)',          10, 2],
            6  => ['Allergy Patch Test',                    'اختبار حساسية الجلد',              8,  2],
            7  => ['Oxygen Infusion Facial',                'فيشل الأكسجين',                    35, 12],
            8  => ['Dermaplaning Session',                  'جلسة ديرمابلاننغ',                 30, 8],
            9  => ['Vitamin B12 Injection (IM)',            'حقنة فيتامين ب12 (عضلي)',          12, 3],
            10 => ['IV Glow Drip Therapy',                  'جلسة تنقية ووهج وريدي',            45, 15],
            11 => ['Dermatology Consultation',              'استشارة جلدية',                    20, 0],
            38 => ['Mesotherapy Session',                   'جلسة ميزوثيرابي',                  60, 20],
            39 => ['Skin Booster (Profhilo) Session',       'جلسة سكين بوستر (بروفايلو)',       180, 70],
            40 => ['Fat-Dissolving Injection (Per Area)',   'حقن إذابة الدهون (لكل منطقة)',     90, 30],
        ];

        foreach ($map as $id => [$en, $ar, $price, $cost]) {
            DB::table('clinic_items')->where('id', $id)->update([
                'name' => json_encode(['en' => $en, 'ar' => $ar], JSON_UNESCAPED_UNICODE),
                'default_price' => $price,
                'default_cost' => $cost,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Re-skin the medical specialty taxonomy (services table) as beauty
     * categories. Updated in place by slug — name + icon only, the slug and id
     * are kept so existing partner_service / gateway / promotion links survive.
     */
    private function reskinServiceCategories(): void
    {
        // current slug => [en, ar, icon]
        $map = [
            'general-practice'   => ['Cosmetic Consultation', 'استشارة تجميلية',     '💬'],
            'pediatrics'         => ['Facials & Skincare',    'العناية بالبشرة',     '🧖'],
            'dermatology'        => ['Dermatology',           'الجلدية',             '✨'],
            'dentistry'          => ['Injectables & Fillers', 'الحقن والفيلر',       '💉'],
            'cardiology'         => ['Laser & Hair Removal',  'الليزر وإزالة الشعر',  '🔆'],
            'orthopedics'        => ['Body Contouring',       'نحت الجسم',           '💃'],
            'gynecology'         => ['Hair Restoration',      'زراعة وعلاج الشعر',    '💇'],
            'ophthalmology'      => ['Lashes & Brows',        'الرموش والحواجب',      '👁️'],
            'ent'                => ['Skin Treatments',       'علاجات البشرة',        '🌿'],
            'internal-medicine'  => ['Wellness & IV Drips',   'العافية والمغذيات',    '💧'],
            'neurology'          => ['Anti-Aging',            'مكافحة الشيخوخة',      '⏳'],
            'psychiatry'         => ['Bridal & Events',       'العرائس والمناسبات',   '👰'],
            'nutrition'          => ['Skin Nutrition',        'تغذية البشرة',        '🍓'],
            'physical-therapy'   => ['Slimming & Massage',    'التنحيف والمساج',     '💆'],
            'radiology'          => ['Skin Imaging (VISIA)',  'تصوير البشرة',        '📷'],
            'laboratory'         => ['Lab & Diagnostics',     'المختبر والتحاليل',   '🧪'],
        ];

        foreach ($map as $slug => [$en, $ar, $icon]) {
            DB::table('services')->where('slug', $slug)->update([
                'name' => json_encode(['en' => $en, 'ar' => $ar], JSON_UNESCAPED_UNICODE),
                'icon' => $icon,
                'updated_at' => now(),
            ]);
        }

        // Retire the leftover restaurant-template "Food / مطاعم" category so it
        // never surfaces on the aesthetic-clinic public site.
        DB::table('services')->where('slug', 'food')->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('branch_service')
            ->whereIn('service_id', DB::table('services')->where('slug', 'food')->pluck('id'))
            ->delete();
    }

    /**
     * Move doctor specialties off cardiology/peds/GP onto aesthetic disciplines.
     */
    private function reskinDoctors(): void
    {
        $bySpecialty = [
            'Cardiology'        => ['Aesthetic Medicine',     'Aesthetic physician — injectables, threads and skin tightening.'],
            'Pediatrics'        => ['Cosmetic Dermatology',   'Cosmetic dermatologist — acne, pigmentation and laser.'],
            'General Practice'  => ['Aesthetic Medicine',     'Aesthetic practitioner — facials, peels and skin boosters.'],
        ];

        foreach ($bySpecialty as $old => [$new, $bio]) {
            DB::table('doctors')->where('specialty', $old)->update([
                'specialty' => $new,
                'bio' => $bio,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Replace the general outpatient formulary with an aesthetic / dermatology
     * one (topicals, pre-procedure and aftercare). medications has no foreign
     * keys (prescriptions store free text), so a clean replace is safe.
     */
    private function seedAestheticFormulary(): void
    {
        DB::table('medications')->delete();

        $sort = 0;
        $now = now();
        $rows = [];
        foreach ($this->formulary() as $m) {
            $sort += 10;
            $rows[] = [
                'name' => $m[0],
                'strength' => $m[1],
                'form' => $m[2],
                'route' => $m[3],
                'default_dose' => $m[4],
                'default_frequency' => $m[5],
                'default_duration' => $m[6],
                'default_instructions' => $m[7],
                'branch_id' => null,
                'usage_count' => 0,
                'sort_order' => $sort,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('medications')->insert($rows);
    }

    /** [name, strength, form, route, dose, frequency, duration, instructions] */
    private function formulary(): array
    {
        return [
            ['Numbing Cream (Lidocaine/Prilocaine)', '5%',     'cream',   'topical', 'thin layer', 'once',        'pre-procedure', 'apply 30–45 min before treatment, cover with film'],
            ['Tretinoin',                            '0.025%', 'cream',   'topical', 'pea-size',   'at night',    '12 weeks',      'nightly, avoid sun, use SPF in the morning'],
            ['Adapalene',                            '0.1%',   'gel',     'topical', 'thin layer', 'at night',    '12 weeks',      'for acne; may cause initial dryness'],
            ['Clindamycin',                          '1%',     'gel',     'topical', 'thin layer', 'twice daily', '8 weeks',       'for inflammatory acne'],
            ['Azelaic Acid',                         '15%',    'gel',     'topical', 'thin layer', 'twice daily', '12 weeks',      'for redness and pigmentation'],
            ['Hydroquinone',                         '4%',     'cream',   'topical', 'thin layer', 'at night',    '8 weeks',       'pigmentation; strictly with daily SPF, courses only'],
            ['Tranexamic Acid',                      '250mg',  'tab',     'PO',      '1',          'twice daily', '8 weeks',       'for melasma, after food'],
            ['Vitamin C Serum',                      '15%',    'serum',   'topical', '4–5 drops',  'every morning','ongoing',      'antioxidant; apply before sunscreen'],
            ['Niacinamide Serum',                    '10%',    'serum',   'topical', '4–5 drops',  'twice daily', 'ongoing',       'for pores and tone'],
            ['Hyaluronic Acid Serum',               null,     'serum',   'topical', '3–4 drops',  'twice daily', 'ongoing',       'apply on damp skin to hydrate'],
            ['Sunscreen',                            'SPF 50+','cream',   'topical', '2 fingers',  'every morning','ongoing',      'reapply every 2–3 hours outdoors'],
            ['Arnica',                               null,     'tab',     'PO',      '1',          'twice daily', '5 days',        'to reduce bruising after injectables'],
            ['Acyclovir',                            '400mg',  'tab',     'PO',      '1',          'twice daily', '5 days',        'cold-sore prophylaxis before lip filler/laser'],
            ['Cetirizine',                           '10mg',   'tab',     'PO',      '1',          'once daily',  '5 days',        'for swelling or allergic reaction'],
            ['Paracetamol',                          '500mg',  'tab',     'PO',      '1–2',        'q6–8h PRN',   '3 days',        'for post-treatment discomfort'],
            ['Co-Amoxiclav',                         '625mg',  'tab',     'PO',      '1',          'twice daily', '5 days',        'only if post-procedure infection suspected'],
            ['Minoxidil',                            '5%',     'solution','topical', '1ml',        'twice daily', 'ongoing',       'for hair thinning; apply to dry scalp'],
            ['Bimatoprost',                          '0.03%',  'solution','topical', '1 drop',     'at night',    '16 weeks',      'lash growth; apply to upper lash line'],
        ];
    }

    /**
     * Re-purpose the 12 seeded lab tests as an aesthetic pre-treatment / wellness
     * screen. Updated in place by current code (lab_order_items reference id, so
     * existing orders stay valid).
     */
    private function reskinLabPanel(): void
    {
        // current code => [new code, name, specimen, unit, range, price]
        $map = [
            'CBC'   => ['CBC',    'Complete Blood Count',          'Blood (EDTA)', null,     'Panel — see breakdown', 5.000],
            'GLU'   => ['GLU',    'Fasting Glucose',               'Blood',        'mg/dL',  '70-100 mg/dL',          2.000],
            'HBA1C' => ['FERR',   'Ferritin (iron / hair workup)', 'Blood',        'ng/mL',  '30-250 ng/mL',          6.000],
            'LIPID' => ['B12',    'Vitamin B12',                   'Blood',        'pg/mL',  '200-900 pg/mL',         6.000],
            'TSH'   => ['TSH',    'Thyroid Stimulating Hormone',   'Blood',        'µIU/mL', '0.4-4.0 µIU/mL',        4.500],
            'CRE'   => ['ZN',     'Zinc (skin & hair)',            'Blood',        'µg/dL',  '70-120 µg/dL',          5.000],
            'UREA'  => ['TESTO',  'Testosterone (Total)',          'Blood',        'ng/dL',  'sex-specific',          7.000],
            'ALT'   => ['HBSAG',  'Hepatitis B Surface Antigen',   'Blood',        null,     'Non-reactive',          5.000],
            'AST'   => ['HCV',    'Hepatitis C Antibody',          'Blood',        null,     'Non-reactive',          5.000],
            'VITD'  => ['VITD',   'Vitamin D (25-OH)',             'Blood',        'ng/mL',  '30-100 ng/mL',          8.000],
            'URI'   => ['COAG',   'Coagulation Profile (PT/INR)',  'Blood',        null,     'PT 11-13.5s',           6.000],
            'PT'    => ['BHCG',   'Pregnancy Test (β-hCG)',        'Blood',        null,     'Negative',              4.000],
        ];

        foreach ($map as $oldCode => [$code, $name, $specimen, $unit, $range, $price]) {
            DB::table('lab_tests')->where('code', $oldCode)->update([
                'code' => $code,
                'name' => $name,
                'specimen_type' => $specimen,
                'unit' => $unit,
                'reference_range' => $range,
                'default_price' => $price,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Replace the general clinic-scope quick-phrase library with aesthetic
     * consultation snippets. Only clinic-scope, non-doctor phrases are cleared;
     * doctors' personal phrases are kept. Field keys are unchanged so they wire
     * to the same v2 note fields.
     */
    private function seedBeautyPhrases(): void
    {
        DB::table('clinical_phrases')
            ->where('scope', 'clinic')
            ->whereNull('doctor_id')
            ->delete();

        $now = now();
        $rows = [];
        foreach ($this->phrases() as $field => $items) {
            $sort = 0;
            foreach ($items as [$labelEn, $bodyEn, $labelAr, $bodyAr]) {
                $sort += 10;
                foreach ([['en', $labelEn, $bodyEn], ['ar', $labelAr, $bodyAr]] as [$loc, $label, $body]) {
                    $rows[] = [
                        'field' => $field,
                        'locale' => $loc,
                        'label' => $label,
                        'body' => $body,
                        'scope' => 'clinic',
                        'doctor_id' => null,
                        'branch_id' => null,
                        'usage_count' => 0,
                        'sort_order' => $sort,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }
        DB::table('clinical_phrases')->insert($rows);
    }

    /** field => [ [label_en, body_en, label_ar, body_ar], ... ] */
    private function phrases(): array
    {
        return [
            // What the client wants to address.
            'chief_complaint' => [
                ['Fine lines',      'Fine lines and early signs of ageing.',              'خطوط دقيقة',   'خطوط دقيقة وعلامات تقدّم السن المبكرة.'],
                ['Pigmentation',    'Pigmentation and uneven skin tone.',                 'تصبغات',       'تصبغات وعدم تجانس لون البشرة.'],
                ['Acne',            'Active acne and breakouts.',                         'حب الشباب',    'حب شباب نشط وبثور متكررة.'],
                ['Acne scars',      'Post-acne scarring and texture.',                    'آثار حب الشباب','ندبات وآثار حب الشباب وملمس غير منتظم.'],
                ['Dull skin',       'Dull, tired-looking skin.',                          'بشرة باهتة',   'بشرة باهتة ومتعبة المظهر.'],
                ['Unwanted hair',   'Unwanted facial / body hair.',                       'شعر زائد',     'شعر زائد في الوجه أو الجسم.'],
                ['Hair thinning',   'Hair thinning and shedding.',                        'تساقط الشعر',  'ترقّق وتساقط الشعر.'],
                ['Double chin',     'Submental fullness (double chin).',                  'الذقن المزدوج','امتلاء أسفل الذقن (الذقن المزدوج).'],
                ['Lip enhancement', 'Interested in lip enhancement.',                     'تكبير الشفاه', 'الرغبة في تحسين وتكبير الشفاه.'],
            ],
            // Skin / area assessment.
            'examination' => [
                ['Skin type III',   'Fitzpatrick III, normal-combination skin.',          'نوع البشرة III','فيتزباتريك III، بشرة عادية إلى مختلطة.'],
                ['Photoaging',      'Mild photoaging, periorbital fine lines.',           'تقدّم ضوئي',   'تقدّم ضوئي خفيف، خطوط دقيقة حول العينين.'],
                ['Melasma',         'Symmetric malar pigmentation, consistent with melasma.','كلف',       'تصبّغ متماثل على الوجنتين يتوافق مع الكلف.'],
                ['Active acne',     'Inflammatory papules over cheeks and jawline.',      'حب شباب نشط',  'حطاطات التهابية على الخدين وخط الفك.'],
                ['Good for filler', 'Adequate skin laxity and volume for filler.',        'مناسب للفيلر', 'مرونة وحجم مناسبان للحقن بالفيلر.'],
                ['Patch test clear','Patch test clear, no reaction at 24h.',              'اختبار سليم',  'اختبار الحساسية سليم، لا تفاعل بعد ٢٤ ساعة.'],
            ],
            // Aesthetic assessment / plan.
            'diagnosis' => [
                ['Photoaging',       'Photoaging — recommend antioxidants & SPF.',        'تقدّم ضوئي',     'تقدّم ضوئي — يُنصح بمضادات الأكسدة والواقي الشمسي.'],
                ['Melasma',          'Melasma — topical lightening + strict sun care.',   'كلف',           'كلف — تفتيح موضعي مع حماية صارمة من الشمس.'],
                ['Acne vulgaris',    'Acne vulgaris — topical regimen ± peels.',          'حب الشباب',      'حب الشباب — نظام موضعي مع جلسات تقشير عند الحاجة.'],
                ['Volume loss',      'Mid-face volume loss — suitable for filler.',       'فقدان الحجم',    'فقدان حجم منتصف الوجه — مناسب للفيلر.'],
                ['Dynamic lines',    'Dynamic forehead/glabella lines — botulinum toxin.','خطوط حركية',     'خطوط جبهة/ما بين الحاجبين حركية — مناسبة للبوتوكس.'],
                ['Androgenetic',     'Androgenetic hair thinning — PRP / minoxidil.',     'صلع وراثي',      'ترقّق شعر وراثي — بلازما أو مينوكسيديل.'],
            ],
            // Aftercare given to the client.
            'patient_instructions' => [
                ['Sun protection',   'Use SPF 50 daily and avoid direct sun for 1 week.', 'حماية شمسية',    'استخدمي واقٍ شمسي ٥٠ يومياً وتجنّبي الشمس المباشرة لمدة أسبوع.'],
                ['No makeup 24h',    'Avoid makeup for 24 hours after the treatment.',    'بدون مكياج ٢٤س', 'تجنّبي المكياج لمدة ٢٤ ساعة بعد الجلسة.'],
                ['No heat 48h',      'No sauna, gym or hot showers for 48 hours.',        'تجنّبي الحرارة', 'تجنّبي الساونا والنادي والاستحمام الساخن لمدة ٤٨ ساعة.'],
                ['Filler aftercare', 'Avoid pressure on the area; mild swelling is normal.','عناية بالفيلر',  'تجنّبي الضغط على المنطقة؛ التورّم البسيط طبيعي.'],
                ['Botox aftercare',  'Stay upright 4 hours; no rubbing the treated area.', 'عناية بالبوتوكس','ابقي منتصبة ٤ ساعات؛ لا تفركي المنطقة المعالَجة.'],
                ['Hydrate skin',     'Keep skin hydrated and use a gentle cleanser.',     'ترطيب البشرة',   'حافظي على ترطيب البشرة واستخدمي غسولاً لطيفاً.'],
                ['Follow-up 2 weeks','Review in 2 weeks to assess results.',              'متابعة أسبوعين', 'المراجعة بعد أسبوعين لتقييم النتائج.'],
            ],
            // Pre-treatment checks.
            'lab_requests' => [
                ['CBC',           'CBC (before PRP / threads)',          'تعداد دم كامل',  'تعداد دم كامل (قبل البلازما/الخيوط)'],
                ['Ferritin',      'Ferritin (hair loss workup)',         'فيريتين',        'فيريتين (تقييم تساقط الشعر)'],
                ['Vitamin D',     'Vitamin D (25-OH)',                   'فيتامين د',      'فيتامين د (25-OH)'],
                ['Hormonal',      'Hormonal screen (acne / hair)',       'فحص هرموني',     'فحص هرموني (حب الشباب/الشعر)'],
                ['Viral screen',  'Hepatitis B/C before injectables',    'فحص فيروسي',     'التهاب الكبد ب/ج قبل الحقن'],
            ],
        ];
    }
}
