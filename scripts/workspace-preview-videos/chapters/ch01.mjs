import { ACCENT, VIEWPORT, tr, login, tab, tabLoc, row, openRow, foot, PROJECT } from '../lib.mjs';

const KEY = '01-tour';

export default function build(lang) {
    const T = tr(lang);
    return {
        name: `${KEY}.${lang}`,
        accent: ACCENT,
        viewport: VIEWPORT,
        rtl: lang === 'ar',
        projectDir: PROJECT,
        login: login(KEY, lang),

        storyboard: async (page, s) => {
            await s.card(
                T('A tour of the new visit screen', 'جولة في شاشة الزيارة الجديدة'),
                T('Everything for today\'s patients on one screen — the queue on one side, the open visit on the other.',
                  'كل ما يخص مرضى اليوم في شاشة واحدة — الطابور في جهة، والزيارة المفتوحة في الجهة الأخرى.'),
                4200);
            await s.cardOut();

            // ── The queue ───────────────────────────────────────────────
            await s.highlight(page.locator('.wsp-queue'), 1800);
            await s.caption(T('1 · The queue', '١ · الطابور'),
                T('Today\'s patients, most urgent first', 'مرضى اليوم، الأكثر حاجة أولاً'),
                T('The list sorts itself: whoever is stuck — urgent lab, allergy, long wait — rises to the top. You never re-sort it by hand.',
                  'القائمة ترتّب نفسها: من يحتاج انتباهاً — تحليل عاجل، حساسية، انتظار طويل — يصعد إلى الأعلى. لا حاجة لترتيبها يدوياً.'), 6500);

            const mo = row(page, 'Mohammed');
            await s.highlight(mo, 2000);
            await s.caption(T('1 · The queue', '١ · الطابور'),
                T('What one row tells you', 'ماذا يخبرك كل سطر'),
                T('Status and doctor under the name. Red chips need a person: 3 urgent tests, a penicillin allergy. The clock on the right is the wait; its bar turns red past the target.',
                  'الحالة والطبيب تحت الاسم. الشارات الحمراء تحتاج تدخلاً: ٣ تحاليل عاجلة وحساسية بنسلين. الساعة يميناً هي مدة الانتظار، وشريطها يحمرّ بعد تجاوز الوقت المستهدف.'), 8000);

            await s.highlight(page.locator('.wsp-chips'), 1600);
            await s.caption(T('1 · The queue', '١ · الطابور'),
                T('Filters and search', 'التصفية والبحث'),
                T('Tap a status to see only those patients. "Done" shows who finished today. Search takes a name, a phone number or a booking code — press / to jump there.',
                  'اضغط على حالة لعرض مرضاها فقط. «المنجزة» تعرض من انتهى اليوم. البحث يقبل الاسم أو رقم الهاتف أو رمز الحجز — اضغط / للانتقال إليه.'), 7500);
            await s.click(page.locator('.wsp-chips .tab-pill').nth(2), { after: 1600 });
            await s.click(page.locator('.wsp-chips .tab-pill').first(), { after: 1000 });

            // ── Opening a patient ──────────────────────────────────────
            await s.caption(T('2 · Open a patient', '٢ · فتح مريض'),
                T('Click a row — the visit opens beside the queue', 'اضغط على السطر — تُفتح الزيارة بجانب الطابور'),
                T('No pop-ups, no new page. The queue stays in view, so you can move to the next patient at any time.',
                  'بدون نوافذ منبثقة ولا صفحة جديدة. يبقى الطابور ظاهراً لتنتقل إلى المريض التالي في أي وقت.'), 5500);
            await openRow(page, s, 'Mohammed', 1800);

            await s.highlight(page.locator('.ab'), 2000);
            await s.caption(T('2 · Open a patient', '٢ · فتح مريض'),
                T('The allergy strip is on every tab', 'شريط الحساسية يظهر في كل التبويبات'),
                T('Red means a recorded allergy — here, penicillin, with anaphylaxis. Amber is an alert such as pregnancy. It stays under the name whatever you open.',
                  'الأحمر يعني حساسية مسجلة — هنا البنسلين مع صدمة تحسسية. البرتقالي تنبيه مثل الحمل. يبقى تحت الاسم مهما فتحت.'), 7500);

            await s.highlight(page.locator('.wsp-tabs'), 2000);
            await s.caption(T('3 · The tabs', '٣ · التبويبات'),
                T('One job per tab', 'لكل مهمة تبويب واحد'),
                T('Overview, Vitals, Notes, Prescription, Lab tests, Leave & follow-up, Files, Items, Payments, History. The small number on a tab tells you there is something inside.',
                  'نظرة عامة، العلامات الحيوية، الملاحظات، الوصفة، التحاليل، الإجازة والمتابعة، الملفات، الخدمات، الدفع، السجل. الرقم الصغير على التبويب يعني أن فيه شيئاً.'), 8000);

            await s.highlight(page.locator('.gl'), 1800);
            await s.caption(T('3 · The tabs', '٣ · التبويبات'),
                T('Overview is the whole visit at a glance', 'النظرة العامة تعرض الزيارة كاملة'),
                T('A card for every tab. Click a card to open its tab — here, Lab tests.',
                  'بطاقة لكل تبويب. اضغط على البطاقة لفتح تبويبها — هنا التحاليل.'), 5500);
            await s.click(page.locator('.gl-card').filter({ hasText: /Lab tests|التحاليل/ }).first(), { after: 2200 });

            await s.caption(T('3 · The tabs', '٣ · التبويبات'),
                T('Or use the number keys', 'أو استخدم أرقام لوحة المفاتيح'),
                T('1 is Overview, 2 Vitals, 3 Notes … 0 History. Shortcuts pause while you are typing in a field.',
                  '١ النظرة العامة، ٢ العلامات الحيوية، ٣ الملاحظات … ٠ السجل. تتوقف الاختصارات أثناء الكتابة في حقل.'), 6000);
            await page.locator('.wsp-pname').click();
            await page.keyboard.press('3'); await page.waitForTimeout(1300);
            await page.keyboard.press('2'); await page.waitForTimeout(1300);
            await page.keyboard.press('1'); await page.waitForTimeout(1300);
            await page.keyboard.press('?'); await page.waitForTimeout(800);
            await s.caption(T('3 · The tabs', '٣ · التبويبات'),
                T('Press ? for every shortcut', 'اضغط ? لعرض كل الاختصارات'),
                T('The full list, whenever you forget one. Escape closes it.',
                  'القائمة الكاملة كلما نسيت اختصاراً. زر Esc يغلقها.'), 5500);
            await page.keyboard.press('Escape'); await page.waitForTimeout(900);

            // ── The footer ─────────────────────────────────────────────
            await s.highlight(page.locator('.wsp-pfoot'), 1800);
            await s.caption(T('4 · The next step', '٤ · الخطوة التالية'),
                T('The gold button is always the next step', 'الزر الذهبي هو دائماً الخطوة التالية'),
                T('It changes with the status: Check in → Start treatment → Complete visit → Discharge. "Call in" puts the patient on the waiting-room screen.',
                  'يتغير حسب الحالة: تسجيل الوصول ← بدء العلاج ← إنهاء الزيارة ← الخروج. زر «نداء» يُظهر المريض على شاشة الانتظار.'), 7500);

            // ── Role and display ───────────────────────────────────────
            await s.hideCaption();
            await s.click(page.locator('.qrole'), { after: 1200 });
            await s.caption(T('5 · Your view', '٥ · طريقة العرض'),
                T('Choose who you are', 'اختر دورك'),
                T('Reception, Doctor, Nurse or Admin. Each sees less noise: a doctor sees only their own patients, a nurse opens on Vitals.',
                  'الاستقبال أو الطبيب أو التمريض أو المدير. كل دور يرى ما يخصه فقط: الطبيب يرى مرضاه، والتمريض يبدأ من العلامات الحيوية.'), 7000);
            await s.caption(T('5 · Your view', '٥ · طريقة العرض'),
                T('Larger text, if you prefer', 'خط أكبر إن رغبت'),
                T('Everything grows together — buttons, text and spacing — and it is remembered on this computer.',
                  'يكبر كل شيء معاً — الأزرار والنص والمسافات — ويُحفظ على هذا الجهاز.'), 4500);
            await s.click(page.locator('.qseg button').last(), { after: 2400 });
            await s.click(page.locator('.qseg button').first(), { after: 1200 });
            await page.locator('.qrole').click(); await page.waitForTimeout(600);

            await s.click(page.locator('.qh-row button[aria-label="More"], .qh-row button[aria-label="المزيد"]').first(), { after: 1000 });
            await s.caption(T('5 · Your view', '٥ · طريقة العرض'),
                T('More: waiting-room screen, cash close, these videos', 'المزيد: شاشة الانتظار، إغلاق الصندوق، هذه الفيديوهات'),
                T('Tools you use now and then live in this menu, so the top of the queue stays uncluttered.',
                  'الأدوات التي تُستخدم أحياناً موجودة في هذه القائمة، لتبقى أعلى القائمة مرتبة.'), 6000);
            await page.keyboard.press('Escape'); await page.waitForTimeout(600);

            await s.card(
                T('That is the whole screen', 'هذه هي الشاشة كاملة'),
                T('Next: reception — adding patients, bookings and check-in.', 'التالي: الاستقبال — إضافة المرضى والحجوزات وتسجيل الوصول.'),
                3800);
        },
    };
}
