<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Design preview: the queue with the selected patient's visit in a pane
 * beside it, instead of behind an overlay.
 *
 * ────────────────────────────────────────────────────────────────────────
 *  THIS PAGE IS SEALED OFF FROM THE SYSTEM. On purpose.
 * ────────────────────────────────────────────────────────────────────────
 *
 *  - It executes NO database query. Not a read, not a write. Every row below
 *    is a hardcoded fixture, so the page renders identically on any install
 *    and cannot show a real patient to anyone.
 *  - The Vue page performs NO network request. Check-in, start/complete/
 *    discharge, payments, doctor reassignment, no-show and cancel all mutate
 *    local component state and vanish on reload. Nothing reaches an endpoint,
 *    so nothing can reach the database.
 *  - It does not modify or reuse any existing v2 screen. WaitingPatients,
 *    Visit\Console and VisitSheet are untouched and unaware of it.
 *
 *  The payload keys match WaitingPatientsController's exactly, so when the
 *  design is approved the real screen is this Vue page pointed at that
 *  controller — no reshaping needed.
 *
 *  Deleting this file, resources/js/v2/Pages/WorkspacePreview/ and the single
 *  route that names it removes the feature completely.
 */
class VisitWorkspacePreviewController extends Controller
{
    public function index(Request $request): Response
    {
        // Same gate as the queue, so the preview is not a way around
        // permissions — even though there is nothing real behind it.
        abort_unless(
            $request->user() && $request->user()->can('view_any_visits'),
            403,
        );

        return Inertia::render('WorkspacePreview/Index', [
            'visits' => $this->sampleRows(),
            'counts' => [
                'pending_checkin' => 2,
                'awaiting_doctor' => 3,
                'in_progress' => 1,
                'awaiting_stock' => 1,
                'awaiting_payment' => 2,
                'awaiting_payment_visible' => 2,
            ],
            'is_admin' => true,
            'is_reception' => false,
            'is_doctor' => false,
            'doctor_id' => null,
            'doctor_schedule' => [],
            // Twelve doctors, on purpose. With three, any layout looks fine; a
            // real branch runs a dozen or more across overlapping shifts, and
            // the booking form has to hold up there. Each carries the room it
            // works from (a booking reserves a doctor AND a room), a shift,
            // and any weekday it does not work. Two rooms are shared by a
            // morning and an evening doctor, as rooms are in practice.
            'doctor_options' => $this->doctors(),
            // Appointment grid. 15 minutes rather than 30 so a full day is ~50
            // slots per doctor — the case the time picker has to survive.
            'slot_minutes' => 15,
            // Everything the Visit and Bill tabs pick from. One list per job —
            // the old screens showed treatment names as chief-complaint
            // phrases and offered two separate ways to request a lab test.
            'phrases' => $this->phrases(),
            'formulary' => $this->formulary(),
            'lab_catalogue' => $this->labCatalogue(),
            'catalogue' => $this->catalogue(),
            'catalogue_categories' => $this->catalogueCategories(),
            'payment_methods' => [
                ['id' => 'knet', 'label' => 'KNET', 'label_ar' => 'كي نت', 'needs_ref' => true],
                ['id' => 'cash', 'label' => 'Cash', 'label_ar' => 'نقداً', 'needs_ref' => false],
                ['id' => 'card', 'label' => 'Card', 'label_ar' => 'بطاقة', 'needs_ref' => true],
                ['id' => 'link', 'label' => 'Payment link', 'label_ar' => 'رابط دفع', 'needs_ref' => false, 'online' => true],
            ],
            'coupons' => [
                ['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10],
                ['code' => 'SAVE5', 'type' => 'amount', 'value' => 5],
            ],
            // Registered patients who are NOT in today's queue — the people a
            // walk-in or a phone booking is most often for. Invented names.
            'directory' => $this->directory(),
            // Per patient: allergies and alerts, the last vitals and the last
            // prescription (for "repeat" and the faint last-visit hints), and
            // files already on record. Keyed by patient id.
            'clinical' => $this->clinical(),
            // Saved sets a doctor applies in one click: drugs + tests + items
            // + a follow-up. Owned by a doctor, or shared with the clinic.
            'order_sets' => $this->orderSets(),
            // Cash drawer float the day started with, for the cash close.
            'cash_float' => 20.0,
            // No attendance widget on a demo page — it would offer to clock a
            // real person in.
            'attendance' => null,
            'is_demo' => true,
        ]);
    }

    /**
     * Invented doctors. Shifts overlap and two rooms are shared across a
     * morning and an evening shift, so availability genuinely differs by time
     * of day — which is what "next free" and "who is free at 11:00" need.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doctors(): array
    {
        $d = fn (int $id, string $name, string $spec, string $room, int $roomId, string $from, string $to, float $fee, array $off = []) => [
            'id' => $id, 'name' => $name, 'branch_id' => 1, 'specialty' => $spec,
            'room' => ['id' => $roomId, 'name' => $room], 'fee' => $fee,
            'shift' => ['start' => $from, 'end' => $to], 'off_days' => $off,
        ];

        return [
            $d(901, 'Dr. Sara Al-Ansari',   'General practice', 'Room 2',  2,  '08:00', '16:00', 15.0),
            $d(902, 'Dr. Khaled Nasser',    'Orthopaedics',     'Room 1',  1,  '09:00', '17:00', 25.0),
            $d(903, 'Dr. Mona Haddad',      'Dermatology',      'Room 3',  3,  '10:00', '18:00', 20.0),
            $d(904, 'Dr. Yousef Al-Rifai',  'Paediatrics',      'Room 4',  4,  '08:00', '14:00', 15.0),
            $d(905, 'Dr. Layla Hamdan',     'Paediatrics',      'Room 4',  4,  '14:00', '22:00', 15.0),
            $d(906, 'Dr. Omar Saleh',       'Dental',           'Room 5',  5,  '09:00', '17:00', 20.0, ['saturday']),
            $d(907, 'Dr. Huda Al-Sayegh',   'Dental',           'Room 6',  6,  '13:00', '21:00', 20.0),
            $d(908, 'Dr. Faisal Al-Omar',   'General practice', 'Room 2',  2,  '16:00', '22:00', 15.0),
            $d(909, 'Dr. Rana Khoury',      'Dermatology',      'Room 7',  7,  '09:00', '15:00', 20.0, ['monday']),
            $d(910, 'Dr. Tariq Mansour',    'ENT',              'Room 8',  8,  '08:00', '14:00', 20.0),
            $d(911, 'Dr. Noor Al-Bahar',    'General practice', 'Room 9',  9,  '12:00', '20:00', 15.0, ['thursday']),
            $d(912, 'Dr. Adel Nasrallah',   'Orthopaedics',     'Room 10', 10, '14:00', '22:00', 25.0),
        ];
    }

    /**
     * Quick phrases per clinical field — phrases that belong to that field.
     * (Production currently has the same 30 treatment names seeded under both
     * chief complaint and patient instructions; that is a data problem this
     * preview deliberately does not copy.)
     *
     * @return array<string, array<int, array{en: string, ar: string}>>
     */
    protected function phrases(): array
    {
        $p = fn (array $pairs) => array_map(fn ($x) => ['en' => $x[0], 'ar' => $x[1]], $pairs);

        return [
            'chief_complaint' => $p([
                ['Headache', 'صداع'], ['Fever', 'حمى'], ['Sore throat', 'ألم في الحلق'], ['Cough', 'سعال'],
                ['Abdominal pain', 'ألم في البطن'], ['Back pain', 'ألم في الظهر'], ['Skin rash', 'طفح جلدي'], ['Follow-up visit', 'زيارة متابعة'],
            ]),
            'examination' => $p([
                ['BP within normal range', 'الضغط ضمن الطبيعي'], ['Chest clear', 'الصدر سليم'], ['Throat inflamed', 'التهاب في الحلق'],
                ['Abdomen soft, non-tender', 'البطن لين وغير مؤلم'], ['No lymphadenopathy', 'لا تضخم في الغدد'], ['Afebrile', 'بدون حرارة'],
            ]),
            'diagnosis' => $p([
                ['Upper respiratory tract infection', 'التهاب الجهاز التنفسي العلوي'], ['Acute pharyngitis', 'التهاب البلعوم الحاد'],
                ['Tension-type headache', 'صداع توتري'], ['Contact dermatitis', 'التهاب جلد تماسي'], ['Lumbar strain', 'شد في أسفل الظهر'],
                ['Hypertension — controlled', 'ارتفاع ضغط منضبط'],
            ]),
            'patient_instructions' => $p([
                ['Drink plenty of fluids', 'اشرب كمية كافية من السوائل'], ['Rest for 2–3 days', 'راحة لمدة ٢–٣ أيام'],
                ['Take medication after food', 'تناول الدواء بعد الأكل'], ['Return if symptoms worsen', 'راجع إذا ساءت الأعراض'],
                ['Avoid direct sun exposure', 'تجنب التعرض المباشر للشمس'], ['Apply a cold compress', 'ضع كمادات باردة'],
            ]),
        ];
    }

    /** @return array<int, array<string, string>> */
    protected function formulary(): array
    {
        // `class` is what an allergy is recorded against (a penicillin allergy
        // covers amoxicillin); `bleeds` marks drugs that add bleeding risk on
        // top of an anticoagulant.
        $d = fn ($name, $strength, $form, $dose, $freq, $dur, $class = null, $bleeds = false) => compact('name', 'strength', 'form', 'dose', 'freq', 'dur', 'class', 'bleeds');

        return [
            $d('Amoxicillin', '500mg', 'capsule', '1 capsule', 'every 8 hours', '7 days', 'penicillin'),
            $d('Augmentin', '625mg', 'tablet', '1 tablet', 'every 12 hours', '7 days', 'penicillin'),
            $d('Azithromycin', '500mg', 'tablet', '1 tablet', 'once daily', '3 days', 'macrolide'),
            $d('Paracetamol', '500mg', 'tablet', '1–2 tablets', 'every 6 hours as needed', '3 days'),
            $d('Ibuprofen', '400mg', 'tablet', '1 tablet', 'every 8 hours after food', '5 days', 'nsaid', true),
            $d('Diclofenac', '50mg', 'tablet', '1 tablet', 'twice daily after food', '5 days', 'nsaid', true),
            $d('Aspirin', '81mg', 'tablet', '1 tablet', 'once daily', '30 days', 'nsaid', true),
            $d('Cetirizine', '10mg', 'tablet', '1 tablet', 'once daily', '7 days'),
            $d('Omeprazole', '20mg', 'capsule', '1 capsule', 'before breakfast', '14 days'),
            $d('Hydrocortisone', '1%', 'cream', 'thin layer', 'twice daily', '7 days'),
            $d('Tretinoin', '0.025%', 'cream', 'pea-sized amount', 'at night', '8 weeks', 'retinoid'),
            $d('Doxycycline', '100mg', 'capsule', '1 capsule', 'once daily', '6 weeks', 'tetracycline'),
            $d('Salbutamol', '100mcg', 'inhaler', '2 puffs', 'every 6 hours as needed', '—'),
            $d('Metformin', '500mg', 'tablet', '1 tablet', 'twice daily', '30 days'),
            $d('Vitamin D3', '50,000 IU', 'capsule', '1 capsule', 'once weekly', '8 weeks'),
            $d('Sumatriptan', '50mg', 'tablet', '1 tablet', 'at migraine onset, max 2/day', '—'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function labCatalogue(): array
    {
        $t = fn ($code, $name, $unit, $range, $price) => compact('code', 'name', 'unit', 'range', 'price');

        return [
            $t('CBC', 'Complete blood count', null, null, 12.0),
            $t('HBA1C', 'HbA1c', '%', '4.0 – 5.6', 10.0),
            $t('LIPID', 'Lipid profile', null, null, 14.0),
            $t('TSH', 'TSH', 'mIU/L', '0.4 – 4.0', 9.0),
            $t('VITD', 'Vitamin D', 'ng/mL', '30 – 100', 15.0),
            $t('CRP', 'CRP', 'mg/L', '0 – 5', 8.0),
            $t('ESR', 'ESR', 'mm/hr', '0 – 20', 6.0),
            $t('UA', 'Urine analysis', null, null, 6.0),
            $t('LFT', 'Liver function tests', null, null, 14.0),
            $t('KFT', 'Kidney function tests', null, null, 12.0),
            $t('FERR', 'Ferritin', 'ng/mL', '15 – 150', 11.0),
            $t('CXR', 'Chest X-ray', null, null, 20.0),
        ];
    }

    /** Services and products that can go on a bill. @return array<int, array<string, mixed>> */
    protected function catalogue(): array
    {
        $c = fn ($id, $label, $kind, $price, $cat) => ['id' => $id, 'label' => $label, 'kind' => $kind, 'price' => $price, 'category_id' => $cat];

        return [
            $c(501, 'Consultation', 'service', 15.0, 1), $c(502, 'Follow-up', 'service', 10.0, 1),
            $c(503, 'Wound dressing', 'service', 4.0, 2), $c(504, 'Suture removal', 'service', 8.0, 2),
            $c(505, 'Nebulizer session', 'service', 6.0, 2), $c(506, 'Vitamin B12 injection', 'product', 8.5, 4),
            $c(507, 'Deep Hydrafacial', 'service', 45.0, 3), $c(508, 'Chemical Peel', 'service', 35.0, 3),
            $c(509, 'Scaling & polishing', 'service', 25.0, null), $c(510, 'Composite filling', 'service', 30.0, null),
            $c(511, 'Sterile dressing pack', 'product', 2.0, 4), $c(512, 'Sunscreen SPF 50', 'product', 9.0, 4),
        ];
    }

    /** Fixture categories for the preview's browse-by-category cards. */
    protected function catalogueCategories(): array
    {
        $c = fn ($id, $en, $ar) => ['id' => $id, 'name_en' => $en, 'name_ar' => $ar];

        return [
            $c(1, 'Consultations', 'الكشوفات'), $c(2, 'Procedures', 'الإجراءات'),
            $c(3, 'Skin care', 'العناية بالبشرة'), $c(4, 'Products', 'المنتجات'),
        ];
    }

    /**
     * Allergies, alerts, last vitals, last prescription and files per patient.
     *
     * Chosen so every case the banner and the checks must handle is on screen:
     * a severe drug allergy that the formulary can trip (Mohammed, penicillin),
     * an anticoagulant that makes NSAIDs a warning (Abdullah), pregnancy
     * (Maryam), "no known allergies" confirmed (Hessa), and allergies simply
     * never asked (Dana) — which is not the same as having none.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function clinical(): array
    {
        $days = fn (int $n) => now()->subDays($n)->toDateString();
        $v = fn (int $ago, $sys, $dia, $pulse, $temp, $spo2, $weight, $height) => [
            'date' => $days($ago), 'bp_sys' => $sys, 'bp_dia' => $dia, 'pulse' => $pulse,
            'temp' => $temp, 'spo2' => $spo2, 'weight' => $weight, 'height' => $height,
        ];
        $rx = fn ($name, $strength, $dose, $freq, $dur) => compact('name', 'strength', 'dose', 'freq', 'dur');
        // Files are drawn, not photographed: an SVG placeholder per kind, so
        // the demo never ships anything that looks like a real patient photo.
        $f = fn (string $id, string $kind, string $name, int $ago, string $by) => [
            'id' => $id, 'kind' => $kind, 'name' => $name, 'date' => $days($ago), 'by' => $by,
        ];

        return [
            8801 => ['allergies_recorded' => true, 'allergies' => [], 'conditions' => [], 'alerts' => [],
                'last_vitals' => $v(54, 118, 76, 72, 36.8, 99, 78.0, 176),
                'last_rx' => [$rx('Tretinoin', '0.025%', 'pea-sized amount', 'at night', '8 weeks')],
                'files' => [$f('f1', 'photo', 'Before — left cheek', 210, 'Dr. Khaled Nasser'), $f('f2', 'photo', 'After 6 weeks — left cheek', 54, 'Dr. Mona Haddad')]],
            8802 => ['allergies_recorded' => true, 'allergies' => [], 'conditions' => [], 'alerts' => [], 'last_vitals' => null, 'last_rx' => [], 'files' => []],
            8803 => ['allergies_recorded' => true,
                'allergies' => [['name' => 'Sulfa drugs', 'class' => 'sulfonamide', 'reaction' => 'Hives', 'severity' => 'moderate']],
                'conditions' => ['Migraine'], 'alerts' => [],
                'last_vitals' => $v(96, 124, 80, 78, 36.9, 98, 64.5, 162),
                'last_rx' => [$rx('Sumatriptan', '50mg', '1 tablet', 'at migraine onset, max 2/day', '—'), $rx('Paracetamol', '500mg', '1–2 tablets', 'every 6 hours as needed', '3 days')],
                'files' => [$f('f3', 'report', 'MRI brain report', 300, 'External — Dar Al Shifa')]],
            8804 => ['allergies_recorded' => true, 'allergies' => [], 'conditions' => [], 'alerts' => [],
                'last_vitals' => $v(365, 110, 70, 68, 36.6, 99, 58.0, 165), 'last_rx' => [], 'files' => []],
            8805 => ['allergies_recorded' => false, 'allergies' => [], 'conditions' => ['Asthma'], 'alerts' => [],
                'last_vitals' => null,
                'last_rx' => [$rx('Salbutamol', '100mcg', '2 puffs', 'every 6 hours as needed', '—')], 'files' => []],
            8806 => ['allergies_recorded' => true,
                'allergies' => [['name' => 'Penicillin', 'class' => 'penicillin', 'reaction' => 'Anaphylaxis', 'severity' => 'severe']],
                'conditions' => ['Type 2 diabetes'], 'alerts' => [],
                'last_vitals' => $v(40, 138, 88, 82, 37.0, 97, 92.0, 174),
                'last_rx' => [$rx('Metformin', '500mg', '1 tablet', 'twice daily', '30 days'), $rx('Diclofenac', '50mg', '1 tablet', 'twice daily after food', '5 days')],
                'files' => [$f('f4', 'report', 'Lumbar X-ray', 40, 'Dr. Khaled Nasser')]],
            8807 => ['allergies_recorded' => true, 'allergies' => [],
                'conditions' => ['Atrial fibrillation', 'Hypertension'],
                'alerts' => [['kind' => 'anticoagulant', 'text' => 'On warfarin — check bleeding risk']],
                'last_vitals' => $v(28, 146, 92, 88, 36.7, 96, 84.0, 170),
                'last_rx' => [], 'files' => [$f('f5', 'consent', 'Consent — minor procedure', 28, 'Reception')]],
            8808 => ['allergies_recorded' => true, 'allergies' => [['name' => 'Latex', 'class' => null, 'reaction' => 'Contact rash', 'severity' => 'mild']],
                'conditions' => [], 'alerts' => [['kind' => 'pregnancy', 'text' => 'Pregnant — 22 weeks']],
                'last_vitals' => $v(21, 112, 72, 90, 36.8, 99, 69.0, 160), 'last_rx' => [], 'files' => []],
            8809 => ['allergies_recorded' => true, 'allergies' => [], 'conditions' => [], 'alerts' => [],
                'last_vitals' => null, 'last_rx' => [], 'files' => []],
            8810 => ['allergies_recorded' => true, 'allergies' => [], 'conditions' => [], 'alerts' => [], 'last_vitals' => null, 'last_rx' => [], 'files' => []],
            8811 => ['allergies_recorded' => true, 'allergies' => [], 'conditions' => [], 'alerts' => [], 'last_vitals' => null, 'last_rx' => [], 'files' => []],
        ];
    }

    /**
     * Saved sets. Two belong to a doctor, one is shared with the clinic.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function orderSets(): array
    {
        return [
            [
                'id' => 's1', 'name' => 'Acne — start', 'name_ar' => 'حب الشباب — بداية', 'owner' => 903, 'shared' => false,
                'drugs' => ['Tretinoin', 'Doxycycline'], 'labs' => [], 'items' => [512], 'follow_up_days' => 56,
            ],
            [
                'id' => 's2', 'name' => 'Back pain', 'name_ar' => 'ألم أسفل الظهر', 'owner' => 902, 'shared' => false,
                'drugs' => ['Ibuprofen', 'Paracetamol'], 'labs' => ['CRP'], 'items' => [], 'follow_up_days' => 14,
            ],
            [
                'id' => 's3', 'name' => 'Fever workup', 'name_ar' => 'فحص الحمى', 'owner' => null, 'shared' => true,
                'drugs' => ['Paracetamol'], 'labs' => ['CBC', 'CRP', 'UA'], 'items' => [], 'follow_up_days' => 7,
            ],
            [
                'id' => 's4', 'name' => 'Diabetes review', 'name_ar' => 'مراجعة السكري', 'owner' => 901, 'shared' => true,
                'drugs' => ['Metformin'], 'labs' => ['HBA1C', 'LIPID', 'KFT'], 'items' => [502], 'follow_up_days' => 90,
            ],
        ];
    }

    /**
     * Existing patients not in today's queue, each with their last visit so the
     * picker can show "last seen 3 months ago · Migraine" — enough to be sure
     * you have the right Fatima before you check her in.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function directory(): array
    {
        $p = fn (int $id, string $name, string $phone, int $age, string $g, ?int $daysAgo, ?string $dx, ?string $doc) => [
            'id' => $id, 'name' => $name, 'msisdn' => $phone, 'age' => $age, 'gender' => $g,
            'last_visit' => $daysAgo === null ? null : [
                'date' => now()->subDays($daysAgo)->toDateString(), 'diagnosis' => $dx, 'doctor' => $doc,
            ],
        ];

        return [
            $p(8820, 'Fatima Al-Kandari', '96550310011', 29, 'female', 92, 'Migraine', 'Dr. Sara Al-Ansari'),
            $p(8821, 'Fatima Al-Shammari', '96550310022', 57, 'female', 400, 'Knee osteoarthritis', 'Dr. Khaled Nasser'),
            $p(8822, 'Bader Al-Mutawa', '96550310033', 41, 'male', 18, 'Eczema flare', 'Dr. Mona Haddad'),
            $p(8823, 'Reem Al-Qattan', '96550310044', 24, 'female', 210, 'Acne vulgaris', 'Dr. Mona Haddad'),
            $p(8824, 'Hamad Al-Azmi', '96550310055', 66, 'male', 35, 'Hypertension review', 'Dr. Sara Al-Ansari'),
            $p(8825, 'Nasser Al-Harbi', '96550310066', 33, 'male', null, null, null),
        ];
    }

    /**
     * A few past visits per patient. The doctor's first question about anyone
     * is usually "what happened last time", so the pane needs somewhere to
     * answer it — this is what the History tab reads.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function history(array $entries): array
    {
        return array_map(fn (array $e) => [
            'id' => $e[0],
            'date' => now()->subDays($e[1])->toDateString(),
            'doctor' => $e[2],
            'diagnosis' => $e[3],
            'total' => $e[4],
            'status' => 'completed',
            // What the History tab reveals when a row is opened.
            'complaint' => $e[5] ?? null,
            'note' => $e[6] ?? null,
            'items' => array_map(
                fn (array $i) => ['label' => $i[0], 'amount' => $i[1]],
                $e[7] ?? [],
            ),
        ], $entries);
    }

    /**
     * Fixture covering every status, both wait-time bands, and each optional
     * badge the queue can render: insurance, outstanding balance, discount,
     * lab results ready / at lab, and a requested offer including the
     * branch-mismatch warning.
     *
     * Names are invented. Any resemblance to a real patient is coincidence.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sampleRows(): array
    {
        $t = fn (int $minsAgo) => now()->subMinutes($minsAgo)->toIso8601String();

        return [
            [
                'id' => 'b9001', 'booking_id' => 9001, 'is_booking' => true,
                'status' => 'pending_checkin',
                'queued_at' => null, 'checked_in_at' => null, 'service_started_at' => null,
                'booking_code' => 'DEMO-4471', 'source' => 'whatsapp', 'notes' => null,
                'res_time' => '11:30:00', 'res_date' => now()->toDateString(),
                'fee' => ['amount' => 15.0, 'paid' => false, 'paid_amount' => 0.0, 'paid_total' => 0.0, 'balance' => 15.0],
                'discount_total' => 0.0, 'policy' => null, 'lab' => null,
                'requested_package' => ['id' => 1, 'name' => 'Skin Glow Package', 'price' => 45.0, 'has_discount' => true, 'branch_mismatch' => false],
                'patient' => ['id' => 8801, 'name' => 'Yousef Al-Enezi', 'msisdn' => '96550001122', 'age' => 31, 'gender' => 'male'],
                'history' => $this->history([[6101, 210, 'Dr. Khaled Nasser', 'Acne vulgaris', 35.0, 'Facial breakouts, six weeks', 'Started topical treatment; review in 8 weeks.', [['Consultation', 15.0], ['Topical retinoid', 20.0]]], [6102, 54, 'Dr. Mona Haddad', 'Follow-up — improving', 10.0, 'Review after topical course', 'Clear improvement. Continue for one more month.', [['Follow-up', 10.0]]]]),
                'doctor' => ['id' => 902, 'name' => 'Dr. Khaled Nasser'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => null,
            ],
            [
                'id' => 'b9002', 'booking_id' => 9002, 'is_booking' => true,
                'status' => 'pending_checkin',
                'queued_at' => null, 'checked_in_at' => null, 'service_started_at' => null,
                'booking_code' => 'DEMO-4472', 'source' => 'web', 'notes' => null,
                'res_time' => '11:45:00', 'res_date' => now()->toDateString(),
                'fee' => ['amount' => 15.0, 'paid' => false, 'paid_amount' => 0.0, 'paid_total' => 0.0, 'balance' => 15.0],
                'discount_total' => 0.0, 'policy' => null, 'lab' => null,
                // Branch-mismatch warning: the offer belongs to another branch.
                'requested_package' => ['id' => 2, 'name' => 'Dental Cleaning Offer', 'price' => 25.0, 'has_discount' => true, 'branch_mismatch' => true],
                'patient' => ['id' => 8802, 'name' => 'Latifa Al-Sane', 'msisdn' => '96550003344', 'age' => 27, 'gender' => 'female'],
                'history' => $this->history([[6103, 120, 'Dr. Sara Al-Ansari', 'Routine dental check', 15.0, 'Six-month check', 'No caries. Scaling advised next visit.', [['Consultation', 15.0]]]]),
                'doctor' => ['id' => 901, 'name' => 'Dr. Sara Al-Ansari'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => null,
            ],
            [
                'id' => 7001, 'is_booking' => false, 'status' => 'awaiting_payment',
                'queued_at' => $t(64), 'checked_in_at' => $t(64), 'service_started_at' => $t(40),
                'booking_code' => 'DEMO-4460', 'source' => 'reception',
                'notes' => 'Patient asked for a printed report.',
                'fee' => ['amount' => 15.0, 'paid' => true, 'paid_amount' => 15.0, 'paid_total' => 15.0, 'balance' => 45.0],
                'discount_total' => 5.0,
                'policy' => [
                    'insurer' => 'Gulf Care', 'plan' => 'Silver', 'number' => 'GC-88213',
                    'status' => 'active', 'coverage_percent' => 80, 'copay_percent' => 20,
                    'valid_until' => now()->addMonths(7)->toDateString(),
                    'approval_required_over' => 50.0, 'notes' => 'Consultations covered. Cosmetic work excluded.',
                ],
                'lab' => null, 'requested_package' => null,
                'patient' => ['id' => 8803, 'name' => 'Noura Al-Mutairi', 'msisdn' => '96550112233', 'age' => 34, 'gender' => 'female'],
                'history' => $this->history([[6104, 96, 'Dr. Sara Al-Ansari', 'Migraine, prescribed rest', 28.0, 'Severe headache with photophobia', 'Advised hydration and rest. Return if it worsens.', [['Consultation', 15.0], ['Analgesic injection', 13.0]]], [6105, 300, 'Dr. Khaled Nasser', 'Seasonal allergy', 22.5, 'Sneezing and itchy eyes', 'Antihistamine course prescribed.', [['Consultation', 15.0], ['Antihistamine', 7.5]]], [6106, 430, 'Dr. Sara Al-Ansari', 'Annual check — normal', 15.0, 'Annual check', 'All findings within normal range.', [['Consultation', 15.0]]]]),
                'doctor' => ['id' => 901, 'name' => 'Dr. Sara Al-Ansari'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 1, 'name' => 'Room 2'],
                'chief_complaint' => 'Recurring headache, three weeks',
                'examination' => 'BP 128/84 · Temp 36.8°C',
                'diagnosis' => 'Tension-type headache',
                'items' => [
                    ['id' => 1, 'label' => 'Consultation', 'qty' => 1, 'amount' => 15.0],
                    ['id' => 2, 'label' => 'Vitamin B12 injection', 'qty' => 1, 'amount' => 8.5, 'kind' => 'product'],
                    ['id' => 3, 'label' => 'CBC panel', 'qty' => 1, 'amount' => 12.0, 'kind' => 'service'],
                ],
                'payments' => [
                    ['id' => 1, 'label' => 'KNET', 'kind' => 'consultation', 'amount' => 15.0, 'at' => $t(60)],
                ],
            ],
            [
                'id' => 7002, 'is_booking' => false, 'status' => 'awaiting_payment',
                'queued_at' => $t(51), 'checked_in_at' => $t(51), 'service_started_at' => $t(30),
                'booking_code' => 'DEMO-4461', 'source' => 'call', 'notes' => null,
                'fee' => ['amount' => 15.0, 'paid' => false, 'paid_amount' => 0.0, 'paid_total' => 0.0, 'balance' => 15.0],
                'discount_total' => 0.0, 'policy' => null,
                'lab' => [
                    'ready' => 2, 'pending' => 0, 'urgent' => false, 'worst_flag' => 'high',
                    'tests' => [
                        ['name' => 'Haemoglobin', 'result' => '10.2', 'unit' => 'g/dL', 'range' => '12.0 – 15.5', 'flag' => 'low',    'reported_at' => now()->subHours(3)->toIso8601String()],
                        ['name' => 'ESR',         'result' => '38',   'unit' => 'mm/hr','range' => '0 – 20',      'flag' => 'high',   'reported_at' => now()->subHours(3)->toIso8601String()],
                        ['name' => 'Platelets',   'result' => '265',  'unit' => 'x10⁹/L','range' => '150 – 400',  'flag' => 'normal', 'reported_at' => now()->subHours(3)->toIso8601String()],
                    ],
                ],
                'requested_package' => null,
                'patient' => ['id' => 8804, 'name' => 'Hessa Rashed', 'msisdn' => '96550556677', 'age' => 39, 'gender' => 'female'],
                'history' => $this->history([[6107, 365, 'Dr. Sara Al-Ansari', 'Annual check — normal', 27.0, 'Annual check', 'Bloods normal. Repeat next year.', [['Consultation', 15.0], ['CBC panel', 12.0]]]]),
                'doctor' => ['id' => 901, 'name' => 'Dr. Sara Al-Ansari'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 1, 'name' => 'Room 2'],
                'chief_complaint' => 'Annual check',
                'examination' => 'Unremarkable',
                'diagnosis' => 'Fit and well',
                'items' => [['id' => 4, 'label' => 'Consultation', 'qty' => 1, 'amount' => 15.0]],
                'payments' => [],
            ],
            [
                'id' => 7003, 'is_booking' => false, 'status' => 'in_progress',
                'queued_at' => $t(22), 'checked_in_at' => $t(22), 'service_started_at' => $t(8),
                'booking_code' => 'DEMO-4462', 'source' => 'walk_in', 'notes' => null,
                'fee' => ['amount' => 15.0, 'paid' => false, 'paid_amount' => 0.0, 'paid_total' => 0.0, 'balance' => 15.0],
                'discount_total' => 0.0,
                'policy' => [
                    'insurer' => 'Falcon Health', 'plan' => 'Gold', 'number' => 'FH-10042',
                    'status' => 'active', 'coverage_percent' => 90, 'copay_percent' => 10,
                    'valid_until' => now()->addMonths(2)->toDateString(),
                    'approval_required_over' => 100.0, 'notes' => 'Direct billing. Lab included.',
                ],
                'lab' => null, 'requested_package' => null,
                'patient' => ['id' => 8805, 'name' => 'Dana Al-Otaibi', 'msisdn' => '96550778899', 'age' => 28, 'gender' => 'female'],
                'history' => $this->history([[6108, 30, 'Dr. Sara Al-Ansari', 'Contact dermatitis', 40.0, 'Rash on forearms', 'Likely detergent. Barrier cream given.', [['Consultation', 15.0], ['Barrier cream', 25.0]]], [6109, 75, 'Dr. Mona Haddad', 'Skin assessment', 15.0, 'General skin review', 'No concerning lesions.', [['Consultation', 15.0]]]]),
                'doctor' => ['id' => 901, 'name' => 'Dr. Sara Al-Ansari'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 1, 'name' => 'Room 2'],
                'chief_complaint' => 'Follow-up, skin rash',
                'examination' => '', 'diagnosis' => '',
                'items' => [['id' => 5, 'label' => 'Follow-up', 'qty' => 1, 'amount' => 10.0]],
                'payments' => [],
            ],
            [
                'id' => 7004, 'is_booking' => false, 'status' => 'awaiting_doctor',
                'queued_at' => $t(41), 'checked_in_at' => $t(41), 'service_started_at' => null,
                'booking_code' => 'DEMO-4463', 'source' => 'follow_up', 'notes' => null,
                'fee' => ['amount' => 15.0, 'paid' => false, 'paid_amount' => 0.0, 'paid_total' => 0.0, 'balance' => 15.0],
                'discount_total' => 0.0, 'policy' => null,
                'lab' => [
                    'ready' => 0, 'pending' => 3, 'urgent' => true, 'worst_flag' => null,
                    'tests' => [
                        ['name' => 'CRP',              'result' => null, 'unit' => 'mg/L', 'range' => '0 – 5',    'flag' => null, 'reported_at' => null],
                        ['name' => 'Vitamin D',        'result' => null, 'unit' => 'ng/mL','range' => '30 – 100', 'flag' => null, 'reported_at' => null],
                        ['name' => 'Lumbar X-ray read','result' => null, 'unit' => null,   'range' => null,       'flag' => null, 'reported_at' => null],
                    ],
                ],
                'requested_package' => null,
                'patient' => ['id' => 8806, 'name' => 'Mohammed Al-Ajmi', 'msisdn' => '96550443322', 'age' => 45, 'gender' => 'male'],
                'history' => $this->history([[6110, 180, 'Dr. Khaled Nasser', 'Lumbar strain — physio referral', 60.0, 'Lower back pain after lifting', 'Referred to physiotherapy, six sessions.', [['Consultation', 15.0], ['X-ray lumbar spine', 45.0]]]]),
                'doctor' => ['id' => 902, 'name' => 'Dr. Khaled Nasser'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 2, 'name' => 'Room 1'],
                'chief_complaint' => 'Lower back pain',
                'examination' => '', 'diagnosis' => '',
                'items' => [], 'payments' => [],
            ],
            [
                'id' => 7005, 'is_booking' => false, 'status' => 'awaiting_doctor',
                'queued_at' => $t(19), 'checked_in_at' => $t(19), 'service_started_at' => null,
                'booking_code' => 'DEMO-4464', 'source' => 'whatsapp', 'notes' => null,
                'fee' => ['amount' => 15.0, 'paid' => true, 'paid_amount' => 15.0, 'paid_total' => 15.0, 'balance' => 0.0],
                'discount_total' => 0.0, 'policy' => null, 'lab' => null, 'requested_package' => null,
                'patient' => ['id' => 8807, 'name' => 'Abdullah Al-Sabah', 'msisdn' => '96550998877', 'age' => 60, 'gender' => 'male'],
                'history' => $this->history([[6111, 90, 'Dr. Mona Haddad', 'Hypertension review', 15.0, 'Routine BP review', 'BP 138/88. Medication unchanged.', [['Consultation', 15.0]]], [6112, 270, 'Dr. Mona Haddad', 'Hypertension review', 15.0, 'Routine BP review', 'BP 142/90. Dose increased.', [['Consultation', 15.0]]]]),
                'doctor' => ['id' => 903, 'name' => 'Dr. Mona Haddad'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 3, 'name' => 'Room 3'],
                'chief_complaint' => 'Blood pressure review',
                'examination' => '', 'diagnosis' => '',
                'items' => [], 'payments' => [['id' => 6, 'label' => 'Cash', 'kind' => 'consultation', 'amount' => 15.0, 'at' => $t(18)]],
            ],
            [
                'id' => 7006, 'is_booking' => false, 'status' => 'awaiting_doctor',
                'queued_at' => $t(9), 'checked_in_at' => $t(9), 'service_started_at' => null,
                'booking_code' => 'DEMO-4465', 'source' => 'reception', 'notes' => null,
                'fee' => ['amount' => 20.0, 'paid' => false, 'paid_amount' => 0.0, 'paid_total' => 0.0, 'balance' => 20.0],
                'discount_total' => 0.0, 'policy' => null, 'lab' => null, 'requested_package' => null,
                'patient' => ['id' => 8808, 'name' => 'Maryam Al-Fadhli', 'msisdn' => '96550665544', 'age' => 22, 'gender' => 'female'],
                'history' => [],
                'doctor' => ['id' => 903, 'name' => 'Dr. Mona Haddad'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 3, 'name' => 'Room 3'],
                'chief_complaint' => 'Sore throat, two days',
                'examination' => '', 'diagnosis' => '',
                'items' => [], 'payments' => [],
            ],
            [
                'id' => 7007, 'is_booking' => false, 'status' => 'awaiting_stock',
                'queued_at' => $t(17), 'checked_in_at' => $t(17), 'service_started_at' => $t(12),
                'booking_code' => 'DEMO-4466', 'source' => 'reception', 'notes' => 'Waiting on dressing pack from the hub.',
                'fee' => ['amount' => 15.0, 'paid' => false, 'paid_amount' => 0.0, 'paid_total' => 0.0, 'balance' => 19.0],
                'discount_total' => 0.0, 'policy' => null, 'lab' => null, 'requested_package' => null,
                'patient' => ['id' => 8809, 'name' => 'Fatima Al-Hajri', 'msisdn' => '96550224466', 'age' => 52, 'gender' => 'female'],
                'history' => $this->history([[6113, 21, 'Dr. Khaled Nasser', 'Minor laceration — sutured', 45.0, 'Cut to left hand', 'Four sutures. Removal in ten days.', [['Consultation', 15.0], ['Suture kit', 30.0]]]]),
                'doctor' => ['id' => 902, 'name' => 'Dr. Khaled Nasser'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 2, 'name' => 'Room 1'],
                'chief_complaint' => 'Dressing change',
                'examination' => 'Wound clean, no discharge',
                'diagnosis' => 'Healing as expected',
                'items' => [['id' => 7, 'label' => 'Sterile dressing pack', 'qty' => 2, 'amount' => 2.0]],
                'payments' => [],
            ],
            [
                'id' => 7008, 'is_booking' => false, 'status' => 'completed',
                'queued_at' => $t(186), 'checked_in_at' => $t(186), 'service_started_at' => $t(170),
                'completed_at' => $t(148),
                'booking_code' => 'DEMO-4455', 'source' => 'whatsapp', 'notes' => null,
                'fee' => ['amount' => 15.0, 'paid' => true, 'paid_amount' => 15.0, 'paid_total' => 27.0, 'balance' => 0.0],
                'discount_total' => 0.0, 'policy' => null, 'lab' => null, 'requested_package' => null,
                'patient' => ['id' => 8810, 'name' => 'Salem Al-Rashidi', 'msisdn' => '96550111000', 'age' => 44, 'gender' => 'male'],
                'doctor' => ['id' => 901, 'name' => 'Dr. Sara Al-Ansari'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 1, 'name' => 'Room 2'],
                'chief_complaint' => 'Sore throat and fever',
                'examination' => 'Temp 38.1°C · throat inflamed',
                'diagnosis' => 'Acute pharyngitis',
                'items' => [
                    ['id' => 20, 'label' => 'Consultation', 'qty' => 1, 'amount' => 15.0],
                    ['id' => 21, 'label' => 'Throat swab', 'qty' => 1, 'amount' => 12.0],
                ],
                'payments' => [['id' => 30, 'label' => 'KNET', 'kind' => 'visit', 'amount' => 27.0, 'at' => $t(146)]],
                'history' => $this->history([[6114, 400, 'Dr. Sara Al-Ansari', 'Tonsillitis', 30.0, 'Recurrent sore throat', 'Course of antibiotics completed.', [['Consultation', 15.0], ['Antibiotic', 15.0]]]]),
            ],
            [
                'id' => 7009, 'is_booking' => false, 'status' => 'completed',
                'queued_at' => $t(141), 'checked_in_at' => $t(141), 'service_started_at' => $t(128),
                'completed_at' => $t(103),
                'booking_code' => 'DEMO-4456', 'source' => 'reception', 'notes' => null,
                'fee' => ['amount' => 15.0, 'paid' => true, 'paid_amount' => 15.0, 'paid_total' => 55.0, 'balance' => 0.0],
                'discount_total' => 5.0,
                'policy' => [
                    'insurer' => 'Gulf Care', 'plan' => 'Silver', 'number' => 'GC-77410',
                    'status' => 'active', 'coverage_percent' => 80, 'copay_percent' => 20,
                    'valid_until' => now()->addMonths(4)->toDateString(),
                    'approval_required_over' => 50.0, 'notes' => 'Consultations covered. Cosmetic work excluded.',
                ],
                'lab' => null, 'requested_package' => null,
                'patient' => ['id' => 8811, 'name' => 'Amal Al-Dosari', 'msisdn' => '96550222333', 'age' => 36, 'gender' => 'female'],
                'doctor' => ['id' => 903, 'name' => 'Dr. Mona Haddad'],
                'branch' => ['id' => 1, 'name' => 'Demo Branch'],
                'room' => ['id' => 3, 'name' => 'Room 3'],
                'chief_complaint' => 'Mole check',
                'examination' => 'Dermoscopy — benign appearance',
                'diagnosis' => 'Benign naevus',
                'items' => [
                    ['id' => 22, 'label' => 'Consultation', 'qty' => 1, 'amount' => 15.0],
                    ['id' => 23, 'label' => 'Dermoscopy', 'qty' => 1, 'amount' => 45.0],
                ],
                'payments' => [['id' => 31, 'label' => 'Cash', 'kind' => 'visit', 'amount' => 55.0, 'at' => $t(101)]],
                'history' => [],
            ],
        ];
    }
}
