import { ACCENT, VIEWPORT, tr, login, setRole, openRow, tab, foot, PROJECT } from '../lib.mjs';

const KEY = '04-doctor-visit';

export default function build(lang) {
    const T = tr(lang);
    return {
        name: `${KEY}.${lang}`, accent: ACCENT, viewport: VIEWPORT, rtl: lang === 'ar', projectDir: PROJECT,
        login: login(KEY, lang),
        storyboard: async (page, s) => {
            await s.card(T('Doctor: the visit', 'الطبيب: الزيارة'),
                T('Notes, prescription, lab tests, sick leave and follow-up — with last visit beside you and allergy checks on every drug.',
                  'الملاحظات والوصفة والتحاليل والإجازة والمتابعة — مع الزيارة السابقة أمامك وفحص الحساسية لكل دواء.'), 4500);
            await s.cardOut();

            await s.caption(T('Before you start', 'قبل البدء'), T('View as Doctor, and pick yourself', 'اختر عرض «الطبيب» ثم اسمك'),
                T('The queue shows only your patients, and the visit opens on Notes once treatment has started.',
                  'يعرض الطابور مرضاك فقط، وتُفتح الزيارة على الملاحظات بعد بدء العلاج.'), 5500);
            await setRole(page, s, 'doctor', 'Khaled Nasser');

            await openRow(page, s, 'Mohammed', 1600);
            await s.highlight(page.locator('.gl'), 1800);
            await s.caption(T('1 · Before you start treatment', '١ · قبل بدء العلاج'), T('Overview tells you the visit so far', 'النظرة العامة تلخّص الزيارة حتى الآن'),
                T('Allergy strip, pending lab tests, last visit\'s diagnosis. Then press Start treatment at the bottom.',
                  'شريط الحساسية، التحاليل المعلقة، وتشخيص الزيارة السابقة. ثم اضغط «بدء العلاج» في الأسفل.'), 6500);
            await s.click(foot(page), { after: 2200 });

            // ── Notes ────────────────────────────────────────────────────
            await s.highlight(page.locator('.vn-last'), 1800);
            await s.caption(T('2 · Notes', '٢ · الملاحظات'), T('Last visit sits above your notes', 'الزيارة السابقة فوق ملاحظاتك'),
                T('Diagnosis and drugs from last time, and under each box what was written then. "Use" copies it in.',
                  'التشخيص والأدوية من المرة السابقة، وتحت كل خانة ما كُتب حينها. زر «استخدم» ينسخه.'), 6500);
            await s.click(page.locator('.vn-card').nth(1).locator('.vn-link').first(), { after: 1300 });
            await s.click(page.locator('#vn-examination'), { after: 900 });
            await s.caption(T('2 · Notes', '٢ · الملاحظات'), T('Quick phrases under the box you are typing in', 'عبارات سريعة تحت الخانة التي تكتب فيها'),
                T('One tap adds a phrase. Type something you use often and "Save this text as a phrase" keeps it for next time.',
                  'ضغطة واحدة تضيف العبارة. وإذا كتبت شيئاً تستخدمه كثيراً، «حفظ النص كعبارة» يحفظه للمرة القادمة.'), 6500);
            await s.click(page.locator('.vn-chip').first(), { after: 1000 });
            await s.type(page.locator('#vn-diagnosis'), T('Acute lumbar strain', 'شد عضلي حاد أسفل الظهر'), { after: 700 });
            await s.click(page.locator('#vn-patient_instructions'), { after: 700 });
            await s.click(page.locator('.vn-chip').first(), { after: 1400 });

            // ── Prescription ────────────────────────────────────────────
            await tab(page, s, 'rx');
            await s.caption(T('3 · Prescription', '٣ · الوصفة'), T('Search a drug — dose, frequency and duration fill in', 'ابحث عن دواء — تُملأ الجرعة والتكرار والمدة'),
                T('Watch what happens with amoxicillin for a patient allergic to penicillin.',
                  'لاحظ ما يحدث عند وصف أموكسيسيلين لمريض لديه حساسية من البنسلين.'), 5500);
            await s.type(page.locator('.vn-search input'), 'amox', { delay: 110, after: 900 });
            await s.click(page.locator('.vn-drop .vn-opt').first(), { after: 1500 });
            await s.highlight(page.locator('.vn-warn').first(), 1800);
            await s.caption(T('3 · Prescription', '٣ · الوصفة'), T('Stopped before it is added', 'يتوقف قبل الإضافة'),
                T('Amoxicillin is a penicillin. You choose: Don\'t add — or Add anyway, which keeps a red warning on that line for everyone to see.',
                  'الأموكسيسيلين من فئة البنسلين. أنت تختار: «لا تضف» — أو «أضف رغم التنبيه»، فيبقى تنبيه أحمر على السطر يراه الجميع.'), 7500);
            await s.click(page.locator('.vn-warn-actions .btn-primary').first(), { after: 1300 });

            await s.caption(T('3 · Prescription', '٣ · الوصفة'), T('Repeat last prescription', 'تكرار الوصفة السابقة'),
                T('A regular patient? One click brings back last visit\'s drugs — and each still goes through the allergy check.',
                  'مريض متابع؟ ضغطة واحدة تعيد أدوية الزيارة السابقة — وكل دواء يمر بفحص الحساسية أيضاً.'), 6000);
            await s.click(page.locator('.vn-rxbar .btn-outline').first(), { after: 2000 });
            await s.caption(T('3 · Prescription', '٣ · الوصفة'), T('Saved sets', 'المجموعات المحفوظة'),
                T('Your usual treatment in one click: "Back pain" adds its drugs, its test and a follow-up in two weeks. Drugs already listed are skipped.',
                  'علاجك المعتاد بضغطة: «ألم أسفل الظهر» تضيف أدويتها وتحليلها ومتابعة بعد أسبوعين. الأدوية الموجودة لا تتكرر.'), 7000);
            await s.click(page.locator('.vn-rxbar .btn-outline').nth(1), { after: 1200 });
            await s.click(page.locator('.vn-set').filter({ hasText: /Back pain|ألم أسفل الظهر/ }).first(), { after: 2200 });
            await s.caption(T('3 · Prescription', '٣ · الوصفة'), T('Change any line in place', 'عدّل أي سطر مباشرة'),
                T('Dose, frequency and duration are editable on the line. "Save as set" in the same menu keeps this prescription for next time.',
                  'الجرعة والتكرار والمدة قابلة للتعديل على السطر. «حفظ كمجموعة» في نفس القائمة يحفظ هذه الوصفة للمرة القادمة.'), 6500);

            // ── Lab ──────────────────────────────────────────────────────
            await tab(page, s, 'lab');
            await s.caption(T('4 · Lab tests', '٤ · التحاليل'), T('One list: ordered, at the lab, result ready', 'قائمة واحدة: مطلوب، في المختبر، النتيجة جاهزة'),
                T('Search a test to order it. A test already ordered cannot be ordered twice. Urgent tells the lab to run it first.',
                  'ابحث عن التحليل لطلبه. لا يمكن طلب التحليل مرتين. «عاجل» يخبر المختبر بأولويته.'), 6500);
            await s.type(page.locator('.vn-search input'), 'cbc', { delay: 110, after: 900 });
            await s.click(page.locator('.vn-drop .vn-opt:not([disabled])').first(), { after: 1300 });
            await s.click(page.locator('.vn-labrow').filter({ hasText: /Complete blood count/ }).locator('.btn').first(), { after: 1500 });

            // ── Leave & follow-up ───────────────────────────────────────
            await tab(page, s, 'leave');
            await s.caption(T('5 · Sick leave & follow-up', '٥ · الإجازة والمتابعة'), T('Big buttons that say the date', 'أزرار كبيرة تُظهر التاريخ'),
                T('3 days of sick leave shows exactly which days. The follow-up from the set is already chosen — or pick any date on the calendar.',
                  '٣ أيام إجازة تُظهر الأيام بالضبط. موعد المتابعة من المجموعة محدد مسبقاً — أو اختر أي تاريخ من التقويم.'), 7000);
            await s.click(page.locator('.vn-leave .vn-card').first().locator('.vn-pill').nth(3), { after: 1500 });
            await s.click(page.locator('.vn-leave .vn-card').nth(1).locator('.vn-pill').last(), { after: 1200 });
            await s.click(page.locator('.cal-nav').last(), { after: 800 });
            await s.click(page.locator('.cal-day:not([disabled])').nth(12), { after: 1800 });

            await tab(page, s, 'overview', 1600);
            await s.caption(T('6 · Check your work', '٦ · راجع عملك'), T('Overview shows everything you just did', 'النظرة العامة تعرض كل ما قمت به'),
                T('Notes, drugs, tests, leave, follow-up — and the Today timeline records each step with its time.',
                  'الملاحظات والأدوية والتحاليل والإجازة والمتابعة — وسجل «مجريات اليوم» يسجل كل خطوة بوقتها.'), 7000);

            await s.card(T('The visit is documented', 'تم توثيق الزيارة'),
                T('Next: photos, consent and finishing the visit.', 'التالي: الصور والموافقة وإنهاء الزيارة.'), 3800);
        },
    };
}
