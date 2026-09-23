import { ACCENT, VIEWPORT, LIVE, tr, login, openRow, foot, PROJECT } from '../lib.mjs';

const KEY = '02-reception-front-desk';

/*
 * Recorded on the LIVE workspace (/admin/v2/workspace) of the demo copy, as the
 * reception user — the front desk there uses the system's New booking sheet
 * and Check-in window, not the preview's intake pane.
 * REC_RECEPTION_EMAIL: a reception login on the demo (falls back to REC_EMAIL).
 * Needs the demo DB with two of today's bookings (Maryam 10:30, Noura Al-Sabah
 * 11:00 with the Glow Package offer) — see ch02-demo-bookings.sql. Restore
 * the demo DB and re-run that file before every take.
 */
export default function build(lang) {
    const T = tr(lang);
    const dialog = (page) => page.locator('[role=dialog]').last();
    return {
        name: `${KEY}.${lang}`, accent: ACCENT, viewport: VIEWPORT, rtl: lang === 'ar', projectDir: PROJECT,
        login: login(KEY, lang, { url: LIVE, email: process.env.REC_RECEPTION_EMAIL }),
        storyboard: async (page, s) => {
            const sheet = page.locator('.nb-panel');
            const pick = async (i, query, re) => {
                // i = 'doctor' → the last field (the patient picker is absent for a new patient)
                await s.click(i === 'doctor' ? sheet.locator('.ss-trigger').last() : sheet.locator('.ss-trigger').nth(i), { after: 600 });
                if (query) await s.type(page.locator('.ss-panel .ss-search input'), query, { delay: 90, after: 900 });
                await s.click(page.locator('.ss-panel .opt').filter({ hasText: re }).first(), { after: 900 });
            };
            const pickDay = async (offset) => {
                await s.click(sheet.locator('.dtp-trigger'), { after: 700 });
                const days = page.locator('.dtp-panel button.dtp-day');
                const today = await days.evaluateAll((els) => els.findIndex((e) => e.classList.contains('is-today')));
                await s.click(days.nth(today + offset), { after: 1600 });
            };
            const source = (i) => sheet.locator('.seg').last().locator('button').nth(i);   // web, whatsapp, call, walk-in, reception

            await s.card(T('Reception: the front desk', 'الاستقبال: مكتب الاستقبال'),
                T('A walk-in, a booking for another day, and checking in patients who booked.',
                  'مريض حاضر الآن، حجز ليوم آخر، وتسجيل وصول من حجز مسبقاً.'), 4200);
            await s.cardOut();

            await s.caption(T('The queue', 'الطابور'), T('Today\'s bookings wait at the top of the list', 'حجوزات اليوم في القائمة مع وقتها'),
                T('"Pending check-in" means booked but not arrived yet. A gift icon means the patient chose an offer when booking.',
                  '«بانتظار التسجيل» تعني محجوز ولم يصل بعد. أيقونة الهدية تعني أن المريض اختار عرضاً عند الحجز.'), 6000);
            await s.highlight(page.locator('.qrow').filter({ hasText: 'Noura Al-Sabah' }).first(), 1600);

            // ── 1. Walk-in, new patient ────────────────────────────────
            await s.caption(T('1 · A walk-in', '١ · مريض حاضر الآن'), T('Add patient', 'إضافة مريض'),
                T('Every visit starts as a booking — for a walk-in, the booking is simply for today, now.',
                  'كل زيارة تبدأ بحجز — ولمن حضر الآن يكون الحجز لليوم، الآن.'), 5500);
            await s.click(page.locator('.qh-row .btn-primary'), { after: 1500 });

            await s.caption(T('1 · A walk-in', '١ · مريض حاضر الآن'), T('Not in the system? New patient', 'غير مسجّل؟ مريض جديد'),
                T('Search first — most patients already have a file. If not, "New patient" opens the file right here: name and phone are enough.',
                  'ابحث أولاً — معظم المرضى لديهم ملف. إن لم يوجد، «مريض جديد» يفتح الملف هنا: الاسم والهاتف يكفيان.'), 7000);
            await s.click(sheet.locator('.nb-section .btn-ghost').first(), { after: 900 });
            await s.type(sheet.locator('input.input').nth(0), T('Bader Al-Kandari', 'بدر الكندري'), { delay: 70, after: 500 });
            await s.type(sheet.locator('input.input').nth(1), '96551234567', { delay: 60, after: 900 });

            await s.caption(T('1 · A walk-in', '١ · مريض حاضر الآن'), T('Doctor, today, the next free time', 'الطبيب، اليوم، أقرب وقت متاح'),
                T('The room follows the doctor. Only free times are shown. Mark the source as Walk-in — it matters for the reports.',
                  'الغرفة تتبع الطبيب. تظهر الأوقات المتاحة فقط. اختر المصدر «حضور» — مهم للتقارير.'), 7000);
            await pick('doctor', null, /Sara/);
            await pickDay(0);
            await s.click(sheet.locator('.nb-slot').first(), { after: 800 });
            await s.click(source(3), { after: 900 });
            await s.click(page.locator('.nb-foot .btn-primary'), { after: 2800 });

            await s.caption(T('1 · A walk-in', '١ · مريض حاضر الآن'), T('Now check them in', 'الآن سجّل وصوله'),
                T('The new booking is in the queue. Open it — the button at the bottom says Check in.',
                  'الحجز الجديد في الطابور. افتحه — الزر في الأسفل هو «تسجيل الوصول».'), 5500);
            await openRow(page, s, T('Bader', 'بدر'), 1500);
            await s.click(foot(page), { after: 2000 });

            await s.caption(T('1 · A walk-in', '١ · مريض حاضر الآن'), T('Collect the consultation fee', 'حصّل رسوم الكشف'),
                T('Three steps across the top: booking, fee, room. Pick how they paid, then Collect payment.',
                  'ثلاث خطوات في الأعلى: الحجز، الرسوم، الغرفة. اختر طريقة الدفع ثم «تحصيل الدفعة».'), 6500);
            await s.click(dialog(page).locator('.btn-primary').filter({ hasText: /Collect payment|تحصيل الدفعة/ }).first(), { after: 2400 });
            await s.caption(T('1 · A walk-in', '١ · مريض حاضر الآن'), T('The room is the doctor\'s own', 'الغرفة هي غرفة الطبيب'),
                T('Check in — the patient joins the doctor\'s waiting list straight away.',
                  'سجّل الوصول — ينضم المريض لقائمة انتظار الطبيب فوراً.'), 5000);
            await s.click(dialog(page).locator('.btn-primary').filter({ hasText: /Check in|تسجيل الوصول/ }).first(), { after: 2600 });
            await s.highlight(page.locator('.qrow').filter({ hasText: T('Bader', 'بدر') }).first(), 1600);

            // ── 2. Booking for another day ──────────────────────────────
            await s.caption(T('2 · A booking for another day', '٢ · حجز ليوم آخر'), T('Same button, another day', 'نفس الزر، ويوم آخر'),
                T('For a patient on the phone. Type two letters of the name or the phone number to find their file.',
                  'لمريض على الهاتف. اكتب حرفين من الاسم أو رقم الهاتف لإيجاد ملفه.'), 6000);
            await s.click(page.locator('.qh-row .btn-primary'), { after: 1400 });
            await pick(0, 'Hessa', /Hessa/);
            await pick('doctor', null, /Sara/);
            await pickDay(1);
            await s.caption(T('2 · A booking for another day', '٢ · حجز ليوم آخر'), T('Pick a free time, source: Call', 'اختر وقتاً متاحاً، المصدر: هاتف'),
                T('Booked times are simply not offered, so there is no double booking. Read the day and time back before you hang up.',
                  'الأوقات المحجوزة لا تظهر أصلاً، فلا يوجد حجز مكرر. أعد قراءة اليوم والوقت قبل إنهاء المكالمة.'), 7000);
            await s.click(sheet.locator('.nb-slot').nth(2), { after: 800 });
            await s.click(source(2), { after: 900 });
            await s.click(page.locator('.nb-foot .btn-primary'), { after: 2600 });

            // ── 3. Check in a booking with an offer ────────────────────
            await s.caption(T('3 · Checking in a booking', '٣ · تسجيل وصول صاحب حجز'), T('She booked online, with an offer', 'حجزت عبر الموقع مع عرض'),
                T('Noura arrives for her 11:00. Open her and press Check in.',
                  'وصلت نورة لموعد ١١:٠٠. افتحها واضغط «تسجيل الوصول».'), 5500);
            await openRow(page, s, 'Noura Al-Sabah', 1500);
            await s.click(foot(page), { after: 2000 });
            await s.click(dialog(page).locator('.btn-primary').filter({ hasText: /Collect payment|تحصيل الدفعة/ }).first(), { after: 2400 });
            await s.highlight(dialog(page).locator('.ci-request'), 1800);
            await s.caption(T('3 · Checking in a booking', '٣ · تسجيل وصول صاحب حجز'), T('The offer she chose is ticked', 'العرض الذي اختارته محدد'),
                T('Leave it ticked and it goes on her bill for the doctor to see. From another branch, the price may differ — so it is left unticked for you to check.',
                  'اتركه محدداً فيُضاف إلى فاتورتها ويراه الطبيب. إن كان من فرع آخر قد يختلف السعر — فيبقى غير محدد لتتأكد منه.'), 8000);
            await s.click(dialog(page).locator('.btn-primary').filter({ hasText: /Check in|تسجيل الوصول/ }).first(), { after: 2600 });

            await s.card(T('Front desk done', 'انتهى عمل الاستقبال'),
                T('Next: the nurse — calling patients in, allergies and vitals.', 'التالي: التمريض — نداء المرضى والحساسية والعلامات الحيوية.'), 3800);
        },
    };
}
