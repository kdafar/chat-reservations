import { ACCENT, VIEWPORT, tr, login, setRole, openRow, row, PROJECT } from '../lib.mjs';

const KEY = '03-nurse-vitals-allergies';

export default function build(lang) {
    const T = tr(lang);
    return {
        name: `${KEY}.${lang}`, accent: ACCENT, viewport: VIEWPORT, rtl: lang === 'ar', projectDir: PROJECT,
        login: login(KEY, lang),
        storyboard: async (page, s) => {
            await s.card(T('Nurse: before the doctor', 'التمريض: قبل الطبيب'),
                T('Call the patient in, check allergies, take vitals — so the doctor starts with everything in front of them.',
                  'نادِ المريض، تحقّق من الحساسية، سجّل العلامات الحيوية — ليبدأ الطبيب وكل شيء أمامه.'), 4200);
            await s.cardOut();

            await s.caption(T('Before you start', 'قبل البدء'), T('Set your view to Nurse', 'اختر عرض «التمريض»'),
                T('The queue now shows only patients who are waiting, and a patient opens on the Vitals tab.',
                  'يعرض الطابور الآن المنتظرين فقط، ويُفتح المريض على تبويب العلامات الحيوية.'), 5500);
            await setRole(page, s, 'nurse');

            // ── Call ─────────────────────────────────────────────────────
            await s.caption(T('1 · Call the next patient', '١ · نداء المريض التالي'), T('Call next', 'نداء التالي'),
                T('Picks the first patient in the queue — the most urgent — and opens them. Someone called in the last two minutes is skipped; they are probably on their way.',
                  'يختار أول مريض في الطابور — الأكثر حاجة — ويفتحه. من نودي خلال آخر دقيقتين يُتخطى، فغالباً هو في الطريق.'), 7500);
            await s.click(page.locator('.qh-row .btn-outline').first(), { after: 2400 });
            await s.highlight(row(page, 'Mohammed'), 1800);
            await s.caption(T('1 · Call the next patient', '١ · نداء المريض التالي'), T('What the waiting room sees', 'ما يظهر في غرفة الانتظار'),
                T('Only a ticket number and first name — never the full name or file number. A chime plays with each call.',
                  'رقم التذكرة والاسم الأول فقط — لا الاسم الكامل ولا رقم الملف. ويصدر صوت تنبيه مع كل نداء.'), 5000);
            await s.click(page.locator('.qh-row button[aria-label="More"], .qh-row button[aria-label="المزيد"]').first(), { after: 800 });
            await s.click(page.locator('.qmenu-row').first(), { after: 5500 });
            await page.keyboard.press('Escape'); await page.waitForTimeout(1000);
            await s.caption(T('1 · Call the next patient', '١ · نداء المريض التالي'), T('Not here? Call again', 'لم يحضر؟ نادِ مرة أخرى'),
                T('"Call in" at the bottom calls the open patient again — or press C. The queue shows "Called ×2" so everyone can see it.',
                  'زر «نداء» في الأسفل ينادي المريض المفتوح مجدداً — أو اضغط C. ويظهر في الطابور «نودي ×٢» ليراه الجميع.'), 6500);
            await s.click(page.locator('.wsp-pfoot .btn-outline').first(), { after: 2000 });

            // ── Allergies ────────────────────────────────────────────────
            await s.highlight(page.locator('.ab'), 2200);
            await s.caption(T('2 · Allergies first', '٢ · الحساسية أولاً'), T('Read the strip before anything else', 'اقرأ الشريط قبل أي شيء'),
                T('Mohammed has a severe penicillin allergy — solid red. His type 2 diabetes is shown in grey beside it.',
                  'محمد لديه حساسية شديدة من البنسلين — بالأحمر الكامل. وبجانبها السكري من النوع الثاني بالرمادي.'), 7000);

            // ── Vitals ───────────────────────────────────────────────────
            await s.caption(T('3 · Vitals', '٣ · العلامات الحيوية'), T('Numbers, not notes', 'أرقام وليست نصوصاً'),
                T('Each vital has its own box with the normal range underneath and last visit\'s value beside it.',
                  'لكل علامة خانة خاصة، تحتها المعدل الطبيعي وبجانبها قيمة الزيارة السابقة.'), 5500);
            const n = page.locator('.vt-num');
            await s.type(n.nth(0), '150', { delay: 120, after: 300 });
            await s.type(n.nth(1), '92', { delay: 120, after: 900 });
            await s.caption(T('3 · Vitals', '٣ · العلامات الحيوية'), T('Out of range turns amber, dangerous turns red', 'خارج المعدل يصبح برتقالياً، والخطير أحمر'),
                T('150/92 is above normal, so the card says High the moment it is typed.',
                  '١٥٠/٩٢ أعلى من الطبيعي، فتظهر كلمة «مرتفع» فور كتابتها.'), 5500);
            await s.type(n.nth(2), '88', { delay: 110, after: 300 });
            await s.type(n.nth(3), '37.2', { delay: 110, after: 300 });
            await s.type(n.nth(4), '97', { delay: 110, after: 300 });
            await s.type(n.nth(6), '182', { delay: 110, after: 300 });
            await s.type(n.nth(7), '93', { delay: 110, after: 700 });
            await s.caption(T('3 · Vitals', '٣ · العلامات الحيوية'), T('Height? Use last visit\'s', 'الطول؟ استخدم طول الزيارة السابقة'),
                T('An adult\'s height does not change, so it is one tap. BMI works itself out — nobody types it.',
                  'طول البالغ لا يتغير، فيكفي ضغطة واحدة. ومؤشر كتلة الجسم يُحسب تلقائياً — لا أحد يكتبه.'), 5500);
            await s.click(page.locator('.vt-link').first(), { after: 1800 });
            await s.highlight(page.locator('.wsp-save'), 1600);
            await s.caption(T('3 · Vitals', '٣ · العلامات الحيوية'), T('Saved — no Save button', 'محفوظ — بدون زر حفظ'),
                T('Every change saves as you type; the time of the last save shows beside the name.',
                  'كل تعديل يُحفظ أثناء الكتابة، ووقت آخر حفظ يظهر بجانب الاسم.'), 5000);

            // ── Alerts and recording an allergy ─────────────────────────
            await openRow(page, s, 'Maryam', 1600);
            await s.caption(T('4 · Alerts', '٤ · التنبيهات'), T('Pregnancy and mild allergies', 'الحمل والحساسية الخفيفة'),
                T('Maryam is 22 weeks pregnant and has a latex allergy. The doctor will be warned about risky drugs automatically.',
                  'مريم حامل في الأسبوع ٢٢ ولديها حساسية لاتكس. سيُنبَّه الطبيب تلقائياً عند الأدوية الخطرة.'), 6500);

            await s.click(page.locator('.wsp-chips .tab-pill').first(), { after: 1000 });
            await openRow(page, s, 'Dana', 1600);
            await s.highlight(page.locator('.ab'), 1800);
            await s.caption(T('4 · Alerts', '٤ · التنبيهات'), T('"Allergies not recorded" means nobody asked', '«الحساسية غير مسجلة» تعني أن أحداً لم يسأل'),
                T('It is not the same as "no allergies". Ask the patient, then press None — or record what they tell you.',
                  'وهي ليست مثل «لا توجد حساسية». اسأل المريض، ثم اضغط «لا يوجد» — أو سجّل ما يخبرك به.'), 7000);
            await s.click(page.locator('.ab-unknown .ab-link').nth(1), { after: 800 });
            await s.type(page.locator('.ab-form .input').nth(0), 'Aspirin', { after: 300 });
            await s.type(page.locator('.ab-form .input').nth(1), T('Hives', 'شرى'), { after: 500 });
            await s.click(page.locator('.ab-sev button').nth(1), { after: 500 });
            await s.click(page.locator('.ab-form .btn-primary'), { after: 2200 });
            await s.caption(T('4 · Alerts', '٤ · التنبيهات'), T('Now it protects every visit', 'الآن تحمي كل زيارة'),
                T('If a doctor ever prescribes aspirin or a drug from the same family, they will be stopped and asked first.',
                  'إذا وصف أي طبيب الأسبرين أو دواءً من نفس الفئة، سيتوقف ويُسأل أولاً.'), 6000);

            await s.card(T('Ready for the doctor', 'جاهز للطبيب'),
                T('Next: the doctor — notes, prescription, lab tests and sick leave.', 'التالي: الطبيب — الملاحظات والوصفة والتحاليل والإجازة المرضية.'), 3800);
        },
    };
}
