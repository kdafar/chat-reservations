<?php

namespace Database\Seeders;

use App\Models\ClinicalPhrase;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared clinic quick-phrase library with common, bilingual
 * outpatient snippets. Idempotent — re-running updates the body/sort of an
 * existing (field, locale, label, clinic) row rather than duplicating it.
 * Doctors add their own personal phrases from the console at runtime.
 */
class ClinicalPhraseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $field => $items) {
            $sort = 0;
            foreach ($items as $item) {
                [$labelEn, $bodyEn, $labelAr, $bodyAr] = $item;
                $sort += 10;
                $this->upsert($field, 'en', $labelEn, $bodyEn, $sort);
                $this->upsert($field, 'ar', $labelAr, $bodyAr, $sort);
            }
        }
    }

    private function upsert(string $field, string $locale, string $label, string $body, int $sort): void
    {
        ClinicalPhrase::updateOrCreate(
            [
                'field' => $field,
                'locale' => $locale,
                'label' => $label,
                'scope' => 'clinic',
                'doctor_id' => null,
            ],
            [
                'body' => $body,
                'branch_id' => null,
                'sort_order' => $sort,
                'is_active' => true,
            ],
        );
    }

    /**
     * field => [ [label_en, body_en, label_ar, body_ar], ... ]
     */
    private function data(): array
    {
        return [
            'chief_complaint' => [
                ['Fever', 'Fever for 2 days', 'حُمّى', 'حمّى منذ يومين'],
                ['Cough', 'Productive cough', 'سعال', 'سعال مصحوب ببلغم'],
                ['Sore throat', 'Sore throat and difficulty swallowing', 'التهاب حلق', 'ألم في الحلق وصعوبة في البلع'],
                ['Headache', 'Headache for 3 days', 'صداع', 'صداع منذ ٣ أيام'],
                ['Abdominal pain', 'Abdominal pain', 'ألم بطن', 'ألم في البطن'],
                ['Diarrhea', 'Diarrhea and vomiting', 'إسهال', 'إسهال وتقيؤ'],
                ['Runny nose', 'Runny nose and sneezing', 'رشح', 'سيلان أنف وعطس'],
                ['Follow-up', 'Routine follow-up visit', 'مراجعة', 'زيارة متابعة روتينية'],
            ],
            'examination' => [
                ['Vitals stable', 'Vitals stable, afebrile, well hydrated.', 'العلامات مستقرة', 'العلامات الحيوية مستقرة، لا حُمّى، ترطيب جيد.'],
                ['Chest clear', 'Chest clear, good air entry bilaterally, no added sounds.', 'الصدر سليم', 'الصدر سليم، دخول هواء جيد على الجانبين، لا أصوات إضافية.'],
                ['Throat congested', 'Throat congested, tonsils enlarged, no exudate.', 'احتقان الحلق', 'احتقان في الحلق، تضخم اللوزتين، دون إفرازات.'],
                ['Abdomen soft', 'Abdomen soft, non-tender, no organomegaly.', 'البطن لين', 'البطن لين، غير مؤلم، دون تضخم في الأعضاء.'],
                ['CVS normal', 'S1 S2 heard, no murmurs.', 'القلب طبيعي', 'صوتا القلب طبيعيان، لا نفخات.'],
                ['No neuro deficit', 'Alert and oriented, no focal neurological deficit.', 'لا عجز عصبي', 'واعٍ ومدرك، لا عجز عصبي بؤري.'],
            ],
            'diagnosis' => [
                ['URTI', 'Upper respiratory tract infection (URTI)', 'عدوى تنفسية علوية', 'عدوى الجهاز التنفسي العلوي'],
                ['Acute pharyngitis', 'Acute pharyngitis', 'التهاب بلعوم حاد', 'التهاب البلعوم الحاد'],
                ['Acute gastroenteritis', 'Acute gastroenteritis', 'نزلة معوية حادة', 'التهاب المعدة والأمعاء الحاد'],
                ['Migraine', 'Migraine', 'الشقيقة', 'الصداع النصفي (الشقيقة)'],
                ['Allergic rhinitis', 'Allergic rhinitis', 'حساسية الأنف', 'التهاب الأنف التحسسي'],
                ['UTI', 'Urinary tract infection', 'التهاب مسالك بولية', 'التهاب المسالك البولية'],
                ['Hypertension', 'Essential hypertension', 'ارتفاع ضغط الدم', 'ارتفاع ضغط الدم الأساسي'],
                ['Type 2 DM', 'Type 2 diabetes mellitus', 'سكري النوع الثاني', 'داء السكري من النوع الثاني'],
            ],
            'patient_instructions' => [
                ['Rest & fluids', 'Rest and drink plenty of fluids.', 'راحة وسوائل', 'الراحة وشرب كميات وفيرة من السوائل.'],
                ['After food', 'Take medication after food.', 'بعد الأكل', 'تناول الدواء بعد الطعام.'],
                ['Complete course', 'Complete the full course of antibiotics.', 'أكمل العلاج', 'أكمل الجرعة الكاملة من المضاد الحيوي.'],
                ['Return if worse', 'Return if symptoms worsen or fever persists more than 3 days.', 'راجع إذا ساءت', 'راجع الطبيب إذا ساءت الأعراض أو استمرت الحمّى أكثر من ٣ أيام.'],
                ['Avoid cold drinks', 'Avoid cold drinks and ice cream.', 'تجنّب البارد', 'تجنّب المشروبات الباردة والآيس كريم.'],
                ['Follow up 1 week', 'Follow up in one week, or sooner if needed.', 'متابعة بعد أسبوع', 'المتابعة بعد أسبوع، أو أبكر عند الحاجة.'],
            ],
            'lab_requests' => [
                ['CBC', 'CBC', 'تعداد دم كامل', 'تعداد الدم الكامل'],
                ['CRP', 'CRP', 'بروتين سي التفاعلي', 'بروتين سي التفاعلي'],
                ['Urine routine', 'Urine routine & microscopy', 'تحليل بول', 'تحليل بول روتيني ومجهري'],
                ['FBS', 'Fasting blood sugar', 'سكر صائم', 'سكر الدم الصائم'],
                ['Lipid profile', 'Lipid profile', 'دهون الدم', 'صورة الدهون'],
            ],
        ];
    }
}
