#!/usr/bin/env python3
"""
publish.py [key.lang ...]   (no args = every recording found)

Run from anywhere; paths are relative to the repository.

For each recording: trim the (un-narrated) login off the front, install it where
the preview videos page streams from, grab a poster frame from the title card,
and write manifest.json with titles, descriptions, steps and durations in both
languages.
"""
import json, os, subprocess, sys, glob

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
REC = os.path.join(ROOT, 'recordings', 'preview')
DEST = os.path.join(ROOT, 'storage', 'app', 'workspace-preview-videos')
SP = os.path.join(HERE, '.cache')
os.makedirs(f'{DEST}/posters', exist_ok=True)

META = {
    '01-tour': dict(role='everyone',
        title_en='A tour of the new visit screen', title_ar='جولة في شاشة الزيارة الجديدة',
        desc_en='The queue, opening a patient, the allergy strip, the tabs, the next-step button, and choosing your view.',
        desc_ar='الطابور، فتح المريض، شريط الحساسية، التبويبات، زر الخطوة التالية، واختيار طريقة العرض.',
        steps_en=['Read a queue row', 'Filter and search', 'Open a patient', 'The allergy strip', 'Tabs and number keys', 'The next-step button', 'Role, text size and the More menu'],
        steps_ar=['قراءة سطر الطابور', 'التصفية والبحث', 'فتح مريض', 'شريط الحساسية', 'التبويبات وأرقام لوحة المفاتيح', 'زر الخطوة التالية', 'الدور وحجم الخط وقائمة المزيد']),
    '02-reception-front-desk': dict(role='reception',
        title_en='Reception: walk-ins, bookings and check-in', title_ar='الاستقبال: الحضور والحجوزات وتسجيل الوصول',
        desc_en='Add a walk-in with a new file, book a patient for another day, and check in patients who booked — collecting the fee and keeping their offer.',
        desc_ar='إضافة مريض حاضر بملف جديد، حجز مريض ليوم آخر، وتسجيل وصول من حجز مسبقاً — مع تحصيل الرسوم والإبقاء على العرض.',
        steps_en=['Today’s bookings in the queue', 'Add patient → New patient', 'Doctor, today, a free time', 'Check in: fee, then room', 'Book another day', 'Check in a booking with an offer'],
        steps_ar=['حجوزات اليوم في الطابور', 'إضافة مريض ← مريض جديد', 'الطبيب، اليوم، وقت متاح', 'تسجيل الوصول: الرسوم ثم الغرفة', 'حجز ليوم آخر', 'تسجيل وصول حجز مع عرض']),
    '03-nurse-vitals-allergies': dict(role='nurse',
        title_en='Nurse: calling in, allergies and vitals', title_ar='التمريض: النداء والحساسية والعلامات الحيوية',
        desc_en='Call the next patient to the room, read and record allergies and alerts, and take vitals as numbers with ranges.',
        desc_ar='نداء المريض التالي، قراءة وتسجيل الحساسية والتنبيهات، وتسجيل العلامات الحيوية كأرقام مع المعدلات.',
        steps_en=['Call next and the waiting-room screen', 'Call again', 'Read the allergy strip', 'Enter vitals', 'Use last height, BMI', 'Record an allergy'],
        steps_ar=['نداء التالي وشاشة الانتظار', 'النداء مرة أخرى', 'قراءة شريط الحساسية', 'إدخال العلامات الحيوية', 'الطول السابق ومؤشر كتلة الجسم', 'تسجيل حساسية']),
    '04-doctor-visit': dict(role='doctor',
        title_en='Doctor: notes, prescription, lab and leave', title_ar='الطبيب: الملاحظات والوصفة والتحاليل والإجازة',
        desc_en='Start treatment, write notes with last visit beside you, prescribe with allergy checks, repeat and saved sets, order tests, set sick leave and follow-up.',
        desc_ar='بدء العلاج، كتابة الملاحظات مع الزيارة السابقة، الوصف مع فحص الحساسية، التكرار والمجموعات، طلب التحاليل، وتحديد الإجازة والمتابعة.',
        steps_en=['Start treatment', 'Last-visit hints and quick phrases', 'Allergy warning on a drug', 'Repeat last prescription', 'Apply a saved set', 'Order a test, mark urgent', 'Sick leave and follow-up'],
        steps_ar=['بدء العلاج', 'تلميحات الزيارة السابقة والعبارات السريعة', 'تنبيه الحساسية عند دواء', 'تكرار الوصفة السابقة', 'تطبيق مجموعة محفوظة', 'طلب تحليل وجعله عاجلاً', 'الإجازة المرضية والمتابعة']),
    '05-doctor-files-finish': dict(role='doctor',
        title_en='Doctor: photos, consent and finishing the visit', title_ar='الطبيب: الصور والموافقة وإنهاء الزيارة',
        desc_en='Add and compare photos, have the patient sign consent on screen, and finish the visit with the checklist.',
        desc_ar='إضافة الصور ومقارنتها، توقيع المريض على الموافقة، وإنهاء الزيارة بقائمة التحقق.',
        steps_en=['Open a file', 'Add photos', 'Compare before and after', 'Sign consent', 'The finish-visit checklist', 'Fix a gap, then complete'],
        steps_ar=['فتح ملف', 'إضافة صور', 'مقارنة قبل وبعد', 'توقيع الموافقة', 'قائمة التحقق عند الإنهاء', 'إصلاح النقص ثم الإنهاء']),
    '06-reception-payment-close': dict(role='reception',
        title_en='Reception: payment, discharge and closing the day', title_ar='الاستقبال: الدفع والخروج وإغلاق اليوم',
        desc_en='Take a payment, print the receipt and invoice, discharge with the checklist, apply insurance, read the visit timeline, and close the cash drawer.',
        desc_ar='استلام دفعة، طباعة الإيصال والفاتورة، الخروج بقائمة التحقق، تطبيق التأمين، قراءة سجل الزيارة، وإغلاق الصندوق.',
        steps_en=['Take a payment', 'Print the receipt and invoice', 'Discharge a paid visit', 'Unpaid and insurance warnings', 'Apply the insurer’s share', 'The visit timeline', 'Daily cash close'],
        steps_ar=['استلام دفعة', 'طباعة الإيصال والفاتورة', 'خروج زيارة مدفوعة', 'تنبيهات عدم الدفع والتأمين', 'تطبيق حصة التأمين', 'سجل مجريات الزيارة', 'إغلاق الصندوق اليومي']),
    '07-tablet': dict(role='doctor',
        title_en='On a tablet', title_ar='على الجهاز اللوحي',
        desc_en='The visit takes the whole screen, the queue slides in when you need it, and alerts travel with each patient.',
        desc_ar='تأخذ الزيارة الشاشة كاملة، ويظهر الطابور عند الحاجة، وتنتقل التنبيهات مع كل مريض.',
        steps_en=['Open a patient', 'Full-width visit', 'Switch patients from the drawer'],
        steps_ar=['فتح مريض', 'الزيارة بعرض الشاشة', 'تبديل المريض من القائمة الجانبية']),
}

def dur(path):
    out = subprocess.run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', path], capture_output=True, text=True).stdout.strip()
    return float(out or 0)

names = sys.argv[1:] or [os.path.basename(p)[:-4] for p in sorted(glob.glob(f'{REC}/*.mp4'))]
manifest_path = f'{DEST}/manifest.json'
manifest = json.load(open(manifest_path)) if os.path.exists(manifest_path) else {}

for name in names:
    key, lang = name.rsplit('.', 1)
    src = f'{REC}/{name}.mp4'
    if not os.path.exists(src):
        print('missing', src); continue
    ms_file = f'{SP}/login-{key}.{lang}.ms'
    cut = int(open(ms_file).read()) / 1000 if os.path.exists(ms_file) else 0
    dst = f'{DEST}/{key}.{lang}.mp4'
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-ss', f'{cut:.2f}', '-i', src, '-c:v', 'libx264', '-crf', '21', '-preset', 'veryfast',
                    '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an', dst], check=True)
    # Poster: the title card, fully faded in.
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-ss', '2.2', '-i', dst, '-frames:v', '1', '-vf', 'scale=960:-2', '-q:v', '3',
                    f'{DEST}/posters/{key}.{lang}.jpg'], check=True)
    seconds = round(dur(dst))
    entry = manifest.get(key, {})
    entry.update(META.get(key, {}))
    entry[f'seconds_{lang}'] = seconds
    manifest[key] = entry
    print(f'{name}: {dur(src):.1f}s -> {seconds}s (trimmed {cut:.1f}s)')

json.dump(manifest, open(manifest_path, 'w'), ensure_ascii=False, indent=2)
print('manifest:', len(manifest), 'chapters')
