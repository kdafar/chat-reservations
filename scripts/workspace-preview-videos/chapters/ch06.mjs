import { ACCENT, VIEWPORT, tr, login, setRole, openRow, tab, foot, PROJECT } from '../lib.mjs';

const KEY = '06-reception-payment-close';

export default function build(lang) {
    const T = tr(lang);
    return {
        name: `${KEY}.${lang}`, accent: ACCENT, viewport: VIEWPORT, rtl: lang === 'ar', projectDir: PROJECT,
        login: login(KEY, lang),
        storyboard: async (page, s) => {
            await s.card(T('Reception: payment, discharge, end of day', 'الاستقبال: الدفع والخروج ونهاية اليوم'),
                T('Collect what is owed, let the patient go, and close the cash drawer at night.',
                  'حصّل المستحق، ودع المريض يغادر، وأغلق الصندوق في نهاية اليوم.'), 4500);
            await s.cardOut();

            await setRole(page, s, 'reception');

            // ── Payment ─────────────────────────────────────────────────
            await s.caption(T('1 · Taking payment', '١ · استلام الدفعة'), T('Ready for payment opens on Payments', '«جاهز للدفع» يُفتح على تبويب الدفع'),
                T('The amount owed is on the queue row too, so you can see who owes what without opening anyone.',
                  'المبلغ المستحق يظهر على سطر الطابور أيضاً، فترى من عليه ماذا دون فتح أحد.'), 5500);
            await openRow(page, s, 'Hessa', 1600);
            await s.highlight(page.locator('.vb-totals'), 1800);
            await s.caption(T('1 · Taking payment', '١ · استلام الدفعة'), T('The amount is already filled in', 'المبلغ مكتوب مسبقاً'),
                T('It follows the balance. Pick how the patient paid. KNET and card need the receipt number, so the day reconciles.',
                  'يتبع المبلغ المتبقي. اختر طريقة الدفع. كي نت والبطاقة تحتاجان رقم الإيصال لتتطابق حسابات اليوم.'), 6500);
            await s.click(page.locator('.vb-choice').first().locator('button').filter({ hasText: /Cash|نقد/ }).first(), { after: 900 });
            await s.click(page.locator('.vb-payfoot .btn-primary'), { after: 2200 });
            await s.highlight(page.locator('.vb-justpaid'), 1600);
            await s.caption(T('1 · Taking payment', '١ · استلام الدفعة'), T('Print the receipt', 'اطبع الإيصال'),
                T('Every payment gets its own receipt — one click, straight to the printer. The printer icon on a payment line prints it again later.',
                  'لكل دفعة إيصال خاص — ضغطة واحدة وتذهب للطابعة. أيقونة الطابعة بجانب الدفعة تعيد طباعته لاحقاً.'), 6500);
            await s.click(page.locator('.vb-printbar .btn').filter({ hasText: /Print invoice|طباعة الفاتورة/ }).first(), { after: 1200 });
            await s.caption(T('1 · Taking payment', '١ · استلام الدفعة'), T('One invoice per visit, by section', 'فاتورة واحدة للزيارة بأقسامها'),
                T('Print the full invoice — consultation, services & packages, items — or just one section for an insurer or employer. Same invoice number either way.',
                  'اطبع الفاتورة كاملة — الكشف والخدمات والباقات والأصناف — أو قسماً واحداً فقط لجهة تأمين أو عمل. بنفس رقم الفاتورة.'), 7000);
            // Close the menu with its own button: Escape would also close the patient.
            await page.locator('.vb-printbar .btn').filter({ hasText: /Print invoice|طباعة الفاتورة/ }).first().click(); await page.waitForTimeout(600);
            await s.caption(T('1 · Taking payment', '١ · استلام الدفعة'), T('Fully paid', 'مدفوعة بالكامل'),
                T('The payment is listed with its time. A mistake? Void it — it stays listed, crossed out, so nothing disappears.',
                  'تظهر الدفعة مع وقتها. خطأ؟ ألغِها — تبقى ظاهرة ومشطوبة، فلا يختفي شيء.'), 6000);

            // ── Discharge ───────────────────────────────────────────────
            await s.caption(T('2 · Discharge', '٢ · الخروج'), T('Discharge checks the bill for you', '«إنهاء وخروج» يراجع الفاتورة عنك'),
                T('Paid, nothing pending — so it is a one-click confirm.', 'مدفوعة ولا شيء معلق — فهو تأكيد بضغطة واحدة.'), 4500);
            await s.click(foot(page), { after: 1800 });
            await s.click(page.locator('.fc-foot .btn').last(), { after: 2000 });

            await openRow(page, s, 'Noura', 1600);
            await s.click(foot(page), { after: 1800 });
            await s.caption(T('2 · Discharge', '٢ · الخروج'), T('Money still owed is red', 'المبلغ غير المدفوع يظهر بالأحمر'),
                T('15.500 unpaid, and her Gulf Care insurance is not applied to the bill yet. Back — do not let her leave like this.',
                  '١٥٫٥٠٠ غير مدفوع، وتأمين Gulf Care لم يُطبّق على الفاتورة بعد. رجوع — لا تدعها تغادر هكذا.'), 7500);
            await s.click(page.locator('.fc-item').filter({ hasText: /Insurance|التأمين/ }).locator('.fc-go'), { after: 1600 });
            await s.caption(T('2 · Discharge', '٢ · الخروج'), T('Apply the insurer\'s share on Items', 'طبّق حصة التأمين من تبويب الخدمات'),
                T('The bill shows what the insurer covers. Apply it, and the patient pays only their share.',
                  'تعرض الفاتورة ما يغطيه التأمين. طبّقه، ويدفع المريض حصته فقط.'), 5500);
            await s.click(page.locator('.vb-ins .btn').first(), { after: 1500 });
            await s.click(page.locator('.vb-strip-go'), { after: 1500 });
            await s.caption(T('2 · Discharge', '٢ · الخروج'), T('Paid more than her share? Refund due', 'دفعت أكثر من حصتها؟ مبلغ مسترد'),
                T('She paid the consultation on arrival, so now the patient is owed money — shown in violet, never hidden as zero.',
                  'دفعت الكشف عند وصولها، والآن يستحق لها مبلغ — يظهر بالبنفسجي ولا يُخفى كصفر.'), 6500);

            // ── Timeline ────────────────────────────────────────────────
            await tab(page, s, 'history');
            await s.caption(T('3 · What happened today', '٣ · ماذا حدث اليوم'), T('The visit timeline', 'سجل مجريات الزيارة'),
                T('Checked in, seen, results, paid — each with its time, and the waiting gaps in between. It answers "why did she wait an hour?"',
                  'تسجيل الوصول، الكشف، النتائج، الدفع — كلٌّ بوقته مع فترات الانتظار بينها. يجيب عن سؤال «لماذا انتظرت ساعة؟».'), 7000);

            // ── Cash close ──────────────────────────────────────────────
            await s.caption(T('4 · End of the day', '٤ · نهاية اليوم'), T('More → Cash close', 'المزيد ← إغلاق الصندوق'),
                T('Compare what the system recorded with what is in the drawer and on the KNET terminal.',
                  'قارن ما سجّله النظام بما في الصندوق وجهاز كي نت.'), 5000);
            await s.click(page.locator('.qh-row button[aria-label="More"], .qh-row button[aria-label="المزيد"]').first(), { after: 800 });
            await s.click(page.locator('.qmenu-row').filter({ hasText: /Cash close|إغلاق الصندوق/ }).first(), { after: 1800 });
            await s.highlight(page.locator('.cc-card').first(), 2000);
            await s.caption(T('4 · End of the day', '٤ · نهاية اليوم'), T('What should be in the drawer', 'ما يجب أن يكون في الصندوق'),
                T('Every payment today by method, voids left out. Opening float plus cash taken is what the drawer should hold.',
                  'كل دفعات اليوم حسب الطريقة، دون الملغاة. رصيد البداية مع النقد المستلم هو ما يجب أن يكون في الصندوق.'), 7000);
            await s.caption(T('4 · End of the day', '٤ · نهاية اليوم'), T('Count the notes, not a total', 'عُدّ الأوراق النقدية وليس المجموع'),
                T('Type how many of each note. Counting notes avoids "about 100". The difference updates as you count.',
                  'اكتب عدد كل فئة. عدّ الأوراق يمنع التقدير. ويتحدث الفرق أثناء العدّ.'), 6000);
            const denom = (i) => page.locator('.cc-denom').nth(i).locator('input');
            await s.type(denom(0), '5', { after: 900 });
            await s.type(denom(2), '1', { after: 1200 });
            await s.highlight(page.locator('.cc-diff').first(), 1600);
            await s.caption(T('4 · End of the day', '٤ · نهاية اليوم'), T('Balanced — or over / short in colour', 'مطابق — أو زيادة / عجز بالألوان'),
                T('Then type the total on the KNET settlement slip. Anyone who left owing money is listed on the right.',
                  'ثم اكتب إجمالي إيصال تسوية كي نت. ومن غادر وعليه مبلغ يظهر في القائمة على الجانب.'), 6500);
            const knetSys = (await page.locator('.cc-card').nth(2).locator('.cc-line').first().textContent()).replace(/[^\d.]/g, '');
            await s.type(page.locator('.cc-field').first().locator('input'), knetSys || '42', { after: 1600 });
            await s.click(page.locator('.cc-foot .btn-primary'), { after: 2600 });
            await s.caption(T('4 · End of the day', '٤ · نهاية اليوم'), T('Day closed', 'أُغلق اليوم'),
                T('The summary locks: counted, differences, and the time it was closed.',
                  'يُقفل الملخص: المعدود والفروقات ووقت الإغلاق.'), 5000);

            await s.card(T('That is the whole day', 'هذا هو اليوم كاملاً'),
                T('Last: using the screen on a tablet.', 'الأخير: استخدام الشاشة على جهاز لوحي.'), 3800);
        },
    };
}
