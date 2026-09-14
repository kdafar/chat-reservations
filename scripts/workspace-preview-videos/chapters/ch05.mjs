import { ACCENT, VIEWPORT, ASSETS, tr, login, setRole, openRow, tab, foot, PROJECT } from '../lib.mjs';

const KEY = '05-doctor-files-finish';

export default function build(lang) {
    const T = tr(lang);
    return {
        name: `${KEY}.${lang}`, accent: ACCENT, viewport: VIEWPORT, rtl: lang === 'ar', projectDir: PROJECT,
        login: login(KEY, lang),
        storyboard: async (page, s) => {
            await s.card(T('Doctor: photos, consent and finishing', 'الطبيب: الصور والموافقة وإنهاء الزيارة'),
                T('Keep before-and-after photos with the visit, have the patient sign on screen, and close the visit without gaps.',
                  'احفظ صور قبل وبعد مع الزيارة، ودع المريض يوقّع على الشاشة، وأنهِ الزيارة دون نواقص.'), 4500);
            await s.cardOut();

            await setRole(page, s, 'doctor', 'Khaled Nasser');
            await openRow(page, s, 'Mohammed', 1400);
            await s.caption(T('Before you start', 'قبل البدء'), T('Treatment is under way', 'العلاج قيد التنفيذ'),
                T('As in the last chapter: Start treatment first.', 'كما في الفصل السابق: ابدأ العلاج أولاً.'), 3500);
            await s.click(foot(page), { after: 1800 });

            // ── Files ───────────────────────────────────────────────────
            await tab(page, s, 'files');
            await s.caption(T('1 · Files', '١ · الملفات'), T('Photos, reports and consent forms', 'الصور والتقارير ونماذج الموافقة'),
                T('Everything attached to this patient, newest first. Filter by kind at the top. Click a file to open it large.',
                  'كل ما أُرفق لهذا المريض، الأحدث أولاً. صفّ حسب النوع في الأعلى. اضغط على الملف لفتحه بحجم كبير.'), 6000);
            await s.click(page.locator('.vf-thumb').first(), { after: 2600 });
            await page.keyboard.press('Escape'); await page.waitForTimeout(900);

            await s.caption(T('1 · Files', '١ · الملفات'), T('Add files', 'إضافة ملفات'),
                T('Choose photos from the computer or phone — or drag them onto this tab.',
                  'اختر الصور من الكمبيوتر أو الهاتف — أو اسحبها إلى هذا التبويب.'), 5000);
            const [chooser] = await Promise.all([
                page.waitForEvent('filechooser'),
                s.click(page.locator('.vf-bar .btn-primary'), { after: 400 }),
            ]);
            await chooser.setFiles([`${ASSETS}/cheek-before.png`, `${ASSETS}/cheek-after.png`]);
            await page.waitForTimeout(1800);

            await s.caption(T('1 · Files', '١ · الملفات'), T('Compare before and after', 'قارن قبل وبعد'),
                T('Tick two photos, then Compare selected — side by side, full screen.',
                  'حدّد صورتين، ثم «قارن المحدد» — جنباً إلى جنب بملء الشاشة.'), 5000);
            await s.click(page.locator('.vf-cmp input').nth(0), { after: 600 });
            await s.click(page.locator('.vf-cmp input').nth(1), { after: 800 });
            await s.click(page.locator('.vf-bar .btn-outline').first(), { after: 3500 });
            await page.keyboard.press('Escape'); await page.waitForTimeout(900);

            await s.caption(T('2 · Consent', '٢ · الموافقة'), T('Sign consent on the screen', 'توقيع الموافقة على الشاشة'),
                T('The patient signs with a finger or the mouse. It is saved as a file on the visit, with the date and time.',
                  'يوقّع المريض بإصبعه أو بالماوس. تُحفظ كملف في الزيارة مع التاريخ والوقت.'), 5500);
            await s.click(page.locator('.vf-bar .btn-outline').filter({ hasText: /Sign consent|توقيع موافقة/ }).first(), { after: 1200 });
            const pad = await page.locator('.vf-pad').boundingBox();
            if (pad) {
                const pts = [[0.12, 0.62], [0.2, 0.35], [0.28, 0.66], [0.36, 0.3], [0.46, 0.6], [0.56, 0.42], [0.66, 0.58], [0.8, 0.4]];
                await page.mouse.move(pad.x + pad.width * pts[0][0], pad.y + pad.height * pts[0][1]);
                await page.mouse.down();
                for (const [x, y] of pts.slice(1)) await page.mouse.move(pad.x + pad.width * x, pad.y + pad.height * y, { steps: 12 });
                await page.mouse.up();
            }
            await page.waitForTimeout(800);
            await s.click(page.locator('.vf-sign-foot .btn-primary'), { after: 2000 });

            // ── Finish ──────────────────────────────────────────────────
            await s.caption(T('3 · Finishing the visit', '٣ · إنهاء الزيارة'), T('Complete visit asks what is missing', '«إنهاء الزيارة» يسأل عمّا ينقص'),
                T('Instead of "Are you sure?", you get a checklist of this visit.',
                  'بدلاً من «هل أنت متأكد؟» تظهر قائمة تحقق خاصة بهذه الزيارة.'), 5000);
            await s.click(foot(page), { after: 1800 });
            await s.caption(T('3 · Finishing the visit', '٣ · إنهاء الزيارة'), T('Amber means a gap; Open takes you there', 'البرتقالي يعني نقصاً، و«فتح» يأخذك إليه'),
                T('No vitals, no diagnosis, results still at the lab. Nothing is blocked — clinics have real reasons — but every gap is named.',
                  'لا علامات حيوية، لا تشخيص، نتائج ما زالت في المختبر. لا شيء ممنوع — للعيادة أسبابها — لكن كل نقص مذكور.'), 8000);
            await s.click(page.locator('.fc-item').filter({ hasText: /No diagnosis|لا يوجد تشخيص/ }).locator('.fc-go'), { after: 1500 });
            await s.type(page.locator('#vn-diagnosis'), T('Acute lumbar strain', 'شد عضلي حاد أسفل الظهر'), { after: 900 });
            await s.click(foot(page), { after: 1800 });
            await s.caption(T('3 · Finishing the visit', '٣ · إنهاء الزيارة'), T('Fewer gaps — complete when you are ready', 'نواقص أقل — أنهِ عندما تكون جاهزاً'),
                T('Green lines are done. With nothing left that matters, Complete visit sends the patient to reception.',
                  'الأسطر الخضراء مكتملة. وعندما لا يبقى شيء مهم، «إنهاء الزيارة» يرسل المريض إلى الاستقبال.'), 6500);
            await s.click(page.locator('.fc-foot .btn').last(), { after: 2200 });
            await s.caption(T('3 · Finishing the visit', '٣ · إنهاء الزيارة'), T('Ready for payment', 'جاهز للدفع'),
                T('The patient is now on reception\'s list with what they owe. Your part is done.',
                  'المريض الآن في قائمة الاستقبال مع المبلغ المستحق. انتهى دورك.'), 5500);

            await s.card(T('Visit complete', 'اكتملت الزيارة'),
                T('Next: reception — taking payment, discharge and closing the day.', 'التالي: الاستقبال — الدفع والخروج وإغلاق اليوم.'), 3800);
        },
    };
}
