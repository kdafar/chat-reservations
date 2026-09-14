import { ACCENT, VIEWPORT, tr, login, setRole, openRow, foot, PROJECT } from '../lib.mjs';

const KEY = '02-reception-front-desk';

export default function build(lang) {
    const T = tr(lang);
    return {
        name: `${KEY}.${lang}`, accent: ACCENT, viewport: VIEWPORT, rtl: lang === 'ar', projectDir: PROJECT,
        login: login(KEY, lang),
        storyboard: async (page, s) => {
            await s.card(T('Reception: the front desk', 'الاستقبال: مكتب الاستقبال'),
                T('A walk-in, a booking for later, and checking in patients who booked — all from the same screen.',
                  'مريض بدون موعد، حجز لوقت لاحق، وتسجيل وصول من حجز مسبقاً — كلها من نفس الشاشة.'), 4200);
            await s.cardOut();

            await s.caption(T('Before you start', 'قبل البدء'), T('Set your view to Reception', 'اختر عرض «الاستقبال»'),
                T('Reception sees every patient, the bill and files — and the buttons to add, check in and discharge.',
                  'الاستقبال يرى كل المرضى والفواتير والملفات — وأزرار الإضافة وتسجيل الوصول والخروج.'), 5000);
            await setRole(page, s, 'reception');

            // ── 1. Walk-in ───────────────────────────────────────────────
            await s.caption(T('1 · A walk-in', '١ · مريض بدون موعد'), T('One button: Add patient', 'زر واحد: إضافة مريض'),
                T('Whether the patient is here now or wants a time later is decided inside the form — not by guessing between two buttons.',
                  'سواء كان المريض حاضراً الآن أو يريد موعداً لاحقاً، يُحدَّد ذلك داخل النموذج — وليس بالاختيار بين زرّين متشابهين.'), 6000);
            await s.click(page.locator('.qh-row .btn-primary'), { after: 1500 });

            await s.caption(T('1 · A walk-in', '١ · مريض بدون موعد'), T('"Here now" is already selected', '«موجود الآن» محدد مسبقاً'),
                T('Step 1 is the patient. Type two letters of the name, a phone number or a file number.',
                  'الخطوة ١ هي المريض. اكتب حرفين من الاسم أو رقم الهاتف أو رقم الملف.'), 5500);
            await s.type(page.locator('.ip-search input').first(), 'Bader', { delay: 90, after: 1200 });
            await s.caption(T('1 · A walk-in', '١ · مريض بدون موعد'), T('Last visit shows beside each name', 'آخر زيارة تظهر بجانب كل اسم'),
                T('"18 days ago · Eczema flare" — so you know you have the right person before you check them in.',
                  '«قبل ١٨ يوماً · نوبة إكزيما» — لتتأكد أنه الشخص الصحيح قبل تسجيل وصوله.'), 5500);
            await s.click(page.locator('.ip-res').first(), { after: 1400 });

            await s.caption(T('1 · A walk-in', '١ · مريض بدون موعد'), T('Step 2 · Pick the doctor', 'الخطوة ٢ · اختر الطبيب'),
                T('Each doctor shows how many are waiting for them. Doctors who are off today or have not started their shift are greyed out with the reason.',
                  'يظهر عند كل طبيب عدد المنتظرين عنده. الطبيب الذي في إجازة أو لم تبدأ مناوبته يظهر باهتاً مع السبب.'), 7000);
            await s.click(page.locator('.ip-docrow').filter({ hasText: 'Mona Haddad' }).first(), { after: 1400 });
            await s.type(page.locator('.ip-col').first().locator('input.input').last(), T('Itchy rash on both arms', 'حكة وطفح على الذراعين'), { after: 900 });

            await s.caption(T('1 · A walk-in', '١ · مريض بدون موعد'), T('Check in', 'تسجيل الوصول'),
                T('The patient joins the queue straight away and their visit opens, ready for the doctor.',
                  'ينضم المريض إلى الطابور فوراً وتُفتح زيارته جاهزة للطبيب.'), 4500);
            await s.click(page.locator('.ip-foot .btn-primary'), { after: 2600 });

            // ── 2. Booking for later ────────────────────────────────────
            await s.caption(T('2 · A booking for later', '٢ · حجز لوقت لاحق'), T('Same button, then "Book a time"', 'نفس الزر، ثم «حجز موعد»'),
                T('For a patient on the phone who wants to come another day.',
                  'لمريض على الهاتف يريد الحضور في يوم آخر.'), 5000);
            await s.click(page.locator('.qh-row .btn-primary'), { after: 1300 });
            await s.click(page.locator('.ip-kind button').nth(1), { after: 1200 });
            await s.type(page.locator('.ip-search input').first(), 'Reem', { delay: 90, after: 1000 });
            await s.click(page.locator('.ip-res').first(), { after: 1000 });
            await s.click(page.locator('.ip-docrow').filter({ hasText: 'Mona Haddad' }).first(), { after: 1300 });

            await s.caption(T('2 · A booking for later', '٢ · حجز لوقت لاحق'), T('Pick the day, then a free time', 'اختر اليوم ثم وقتاً متاحاً'),
                T('Only free times are shown, grouped into morning, afternoon and evening. "Next available" finds the soonest slot for you.',
                  'تظهر الأوقات المتاحة فقط، مقسّمة إلى صباح وظهر ومساء. زر «أقرب موعد متاح» يجد أقرب وقت لك.'), 7000);
            await s.click(page.locator('.ip-day').nth(1), { after: 1300 });
            await s.click(page.locator('.ip-slot:not([disabled])').nth(2), { after: 1200 });
            await s.caption(T('2 · A booking for later', '٢ · حجز لوقت لاحق'), T('Where the booking came from', 'مصدر الحجز'),
                T('Call, WhatsApp or reception — it matters for the reports, and it is one tap. Then Book.',
                  'هاتف أو واتساب أو الاستقبال — مهم للتقارير وبضغطة واحدة. ثم احجز.'), 5500);
            await s.click(page.locator('.ip-srcrow .seg button').first(), { after: 900 });
            await s.click(page.locator('.ip-foot .btn-primary'), { after: 2400 });
            await s.caption(T('2 · A booking for later', '٢ · حجز لوقت لاحق'), T('A receipt to read back to the patient', 'ملخص تقرؤه على المريض'),
                T('Name, day, time and doctor — confirm it on the phone before you hang up.',
                  'الاسم واليوم والوقت والطبيب — أكّدها معه على الهاتف قبل إنهاء المكالمة.'), 5500);
            await s.click(page.locator('.ip-foot .btn-primary'), { after: 1200 });

            // ── 3. Check in a booking ───────────────────────────────────
            await s.caption(T('3 · Checking in a booking', '٣ · تسجيل وصول صاحب حجز'), T('Bookings for today wait at the top with their time', 'حجوزات اليوم تنتظر مع وقتها'),
                T('Yousef booked online and chose an offer. Open him, and the gold button at the bottom says Check in.',
                  'يوسف حجز عبر الموقع واختار عرضاً. افتحه، والزر الذهبي في الأسفل هو «تسجيل الوصول».'), 6500);
            await openRow(page, s, 'Yousef', 1500);
            await s.highlight(page.locator('.wsp-pbody'), 1500);
            await s.click(foot(page), { after: 2400 });
            await s.caption(T('3 · Checking in a booking', '٣ · تسجيل وصول صاحب حجز'), T('The offer he picked comes with him', 'العرض الذي اختاره ينتقل معه'),
                T('It waits on Overview and is added to the bill automatically when the doctor starts treatment.',
                  'يبقى في النظرة العامة ويُضاف إلى الفاتورة تلقائياً عند بدء الطبيب العلاج.'), 6000);

            await openRow(page, s, 'Latifa', 1500);
            await s.click(foot(page), { after: 2200 });
            await s.caption(T('3 · Checking in a booking', '٣ · تسجيل وصول صاحب حجز'), T('An offer from another branch needs you', 'عرض من فرع آخر يحتاج موافقتك'),
                T('The price may differ here, so it is never added silently. Check it with the patient, then Approve & add.',
                  'قد يختلف السعر هنا، لذلك لا يُضاف تلقائياً أبداً. تأكد منه مع المريض ثم اضغط «اعتماد وإضافة».'), 7000);
            await s.click(page.locator('.vb-offer .btn-primary').first(), { after: 2000 });

            await s.card(T('Front desk done', 'انتهى عمل الاستقبال'),
                T('Next: the nurse — calling patients in, allergies and vitals.', 'التالي: التمريض — نداء المرضى والحساسية والعلامات الحيوية.'), 3800);
        },
    };
}
