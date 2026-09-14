import { ACCENT, TABLET, tr, login, setRole, openRow, tab, PROJECT } from '../lib.mjs';

const KEY = '07-tablet';

export default function build(lang) {
    const T = tr(lang);
    return {
        name: `${KEY}.${lang}`, accent: ACCENT, viewport: TABLET, rtl: lang === 'ar', projectDir: PROJECT,
        login: login(KEY, lang),
        storyboard: async (page, s) => {
            await s.card(T('On a tablet', 'على الجهاز اللوحي'),
                T('The same screen on an iPad in the consulting room.', 'نفس الشاشة على آيباد في غرفة الكشف.'), 4000);
            await s.cardOut();

            await setRole(page, s, 'doctor', 'Mona Haddad');
            await s.caption(T('1 · The queue first', '١ · الطابور أولاً'), T('Your patients fill the screen', 'مرضاك يملؤون الشاشة'),
                T('Tap a patient to open their visit.', 'اضغط على المريض لفتح زيارته.'), 4500);
            await openRow(page, s, 'Maryam', 1800);

            await s.caption(T('2 · The visit, full width', '٢ · الزيارة بعرض الشاشة'), T('The queue tucks away', 'يختفي الطابور جانباً'),
                T('On a tablet the visit gets the whole screen. The Queue button at the top brings the list back.',
                  'على الجهاز اللوحي تأخذ الزيارة الشاشة كاملة. زر «الطابور» في الأعلى يعيد القائمة.'), 6000);
            await tab(page, s, 'vitals', 1400);
            await s.caption(T('2 · The visit, full width', '٢ · الزيارة بعرض الشاشة'), T('Large boxes for your finger', 'خانات كبيرة تناسب الإصبع'),
                T('Vital boxes are big enough for a finger, and on a touch screen every button grows to finger size.', 'خانات العلامات الحيوية كبيرة تناسب الإصبع، وعلى شاشة اللمس تكبر كل الأزرار لتناسب الإصبع.'), 5000);

            await s.click(page.locator('.wsp-qbtn'), { after: 1600 });
            await s.caption(T('3 · Switch patients', '٣ · تبديل المريض'), T('The queue slides in', 'ينزلق الطابور إلى الشاشة'),
                T('Pick the next patient; the list slides away again by itself.', 'اختر المريض التالي، وتختفي القائمة تلقائياً.'), 5000);
            await s.click(page.locator('.wsp-queue .qrow').filter({ hasText: 'Abdullah' }).locator('.qname'), { after: 1800 });
            await s.highlight(page.locator('.ab'), 1800);
            await s.caption(T('3 · Switch patients', '٣ · تبديل المريض'), T('Alerts come with them', 'التنبيهات تنتقل معهم'),
                T('Abdullah is on warfarin — the strip says so before you prescribe anything.',
                  'عبدالله يتناول الوارفارين — الشريط يوضح ذلك قبل أن تصف أي دواء.'), 5500);

            await s.card(T('You have seen it all', 'شاهدت كل شيء'),
                T('Open the preview and try it yourself — nothing you do there is saved.',
                  'افتح المعاينة وجرّب بنفسك — لا يُحفظ أي شيء تفعله هناك.'), 4200);
        },
    };
}
