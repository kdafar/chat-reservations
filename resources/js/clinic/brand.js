// -----------------------------------------------------------------------------
// EVA Medical — public-site brand config + bilingual strings.
//
// Single source of truth for the marketing site copy. Edit CLINIC below to
// update contact details everywhere. Strings are {en, ar}; tr() picks the
// active locale (driven by <html lang> via getLocale()).
// -----------------------------------------------------------------------------
import { getLocale } from './api'

// Server-managed contact details (v2 Settings → Public Website), injected into
// the page as window.__CLINIC__. Any blank field falls back to the default below.
const S_ = (typeof window !== 'undefined' && window.__CLINIC__) ? window.__CLINIC__ : {}
const pick = (v, fallback = '') => (typeof v === 'string' && v.trim() !== '') ? v.trim() : fallback

const websiteShown = pick(S_.website, 'evamedical.kw')
const normalizeUrl = (u) => /^https?:\/\//i.test(u) ? u : ('https://' + u.replace(/^\/+/, ''))

export const CLINIC = {
  name: { en: pick(S_.name_en, 'EVA Medical'), ar: pick(S_.name_ar, 'إيفا الطبية') },
  // The small label under the logo.
  kicker: { en: pick(S_.tagline_en, 'Aesthetic & Dermatology'), ar: pick(S_.tagline_ar, 'التجميل والجلدية') },
  // Optional logo image URL — when set, replaces the icon in the navbar/footer.
  logo: pick(S_.logo_url),

  // Managed from v2 Settings → Public Website. Leave the phone blank there to
  // hide all "Call" buttons and show "Book Now" instead.
  phone: pick(S_.phone),
  whatsapp: pick(S_.whatsapp),

  email: pick(S_.email, 'hello@evamedical.kw'),
  website: websiteShown,
  websiteUrl: normalizeUrl(websiteShown),

  address: {
    en: pick(S_.address_en, 'Al-Qiblah Medical Centre · Floor 8 · Kuwait City'),
    ar: pick(S_.address_ar, 'مجمع القبلة الطبي · الدور الثامن · مدينة الكويت'),
  },
  city: { en: 'Kuwait City, Kuwait', ar: 'مدينة الكويت، الكويت' },

  // Optional social links — blank ones are hidden in the footer.
  social: {
    instagram: pick(S_.instagram),
    tiktok: pick(S_.tiktok),
    snapchat: pick(S_.snapchat),
  },
}

/** Pick the right language for an {en, ar} pair (or a plain string). */
export function tr(en, ar) {
  const loc = getLocale()
  if (typeof en === 'object' && en !== null) {
    return loc === 'ar' ? (en.ar ?? en.en ?? '') : (en.en ?? en.ar ?? '')
  }
  return loc === 'ar' ? (ar ?? en ?? '') : (en ?? ar ?? '')
}

export function isRTL() {
  return getLocale() === 'ar'
}

// Centralised UI copy. Use S.<key> with tr(): tr(S.nav.services).
export const S = {
  nav: {
    clinics: { en: 'Clinics', ar: 'الفروع' },
    services: { en: 'Services', ar: 'الخدمات' },
    contact: { en: 'Contact', ar: 'تواصل معنا' },
    bookNow: { en: 'Book Now', ar: 'احجز الآن' },
    call: { en: 'Call Us', ar: 'اتصل بنا' },
  },
  hero: {
    badge: { en: 'Now Accepting New Clients', ar: 'نستقبل عملاء جدد الآن' },
    titleA: { en: 'Beauty that', ar: 'جمالٌ' },
    titleB: { en: 'feels like you.', ar: 'يشبهكِ تمامًا.' },
    subtitle: {
      en: 'Advanced aesthetic & dermatology care in the heart of Kuwait City. Book with our expert doctors — injectables, lasers, skin & hair, all in one place.',
      ar: 'رعاية تجميلية وجلدية متقدمة في قلب مدينة الكويت. احجزي مع نخبة أطبائنا — الحقن والليزر والبشرة والشعر، كل ذلك في مكان واحد.',
    },
    stat1: { en: 'Expert Doctors', ar: 'أطباء متخصصون' },
    stat2: { en: 'Treatments', ar: 'خدمة علاجية' },
  },
  stats: {
    s1: { en: 'Signature Treatments', ar: 'خدمة علاجية متميزة' },
    s2: { en: 'Expert Practitioners', ar: 'أطباء وأخصائيون' },
    s3: { en: 'Bilingual Care', ar: 'رعاية بلغتين' },
    s4: { en: 'Premium Products', ar: 'منتجات فاخرة' },
    s1sub: { en: 'across skin, laser & injectables', ar: 'للبشرة والليزر والحقن' },
    s2sub: { en: 'dermatology & aesthetics', ar: 'جلدية وتجميل' },
    s3sub: { en: 'Arabic & English', ar: 'العربية والإنجليزية' },
    s4sub: { en: 'globally trusted brands', ar: 'علامات عالمية موثوقة' },
  },
  servicesSection: {
    kicker: { en: 'Our Treatments', ar: 'خدماتنا' },
    title: { en: 'Aesthetic Excellence', ar: 'تميّزٌ تجميلي' },
    subtitle: {
      en: 'A full menu of dermatology, injectables, laser and skin treatments — tailored to you.',
      ar: 'قائمة متكاملة من خدمات الجلدية والحقن والليزر والعناية بالبشرة — مصمّمة خصيصًا لكِ.',
    },
    cardCta: { en: 'Find Doctors', ar: 'اعثري على طبيب' },
    cardDesc: { en: 'Expert care with personalized treatment plans.', ar: 'رعاية متخصصة مع خطط علاجية مخصّصة.' },
    viewAll: { en: 'View All Treatments', ar: 'عرض جميع الخدمات' },
  },
  info: {
    badge: { en: 'GCC & International Clients', ar: 'عملاء الخليج والخارج' },
    titleA: { en: 'Visiting from', ar: 'قادمة من' },
    titleB: { en: 'abroad?', ar: 'خارج الكويت؟' },
    body: {
      en: 'We make your beauty journey effortless. From scheduling and airport pickup to premium accommodation, our concierge team handles every detail of your visit.',
      ar: 'نجعل رحلة جمالكِ سهلة وسلسة. من تنظيم المواعيد والاستقبال في المطار إلى الإقامة الفاخرة، يتولّى فريق الكونسيرج كل تفاصيل زيارتكِ.',
    },
    cta1: { en: 'Plan Your Visit', ar: 'خطّطي لزيارتك' },
    cta2: { en: 'Contact Concierge', ar: 'تواصلي مع الكونسيرج' },
    conciergeTitle: { en: 'Concierge Service', ar: 'خدمة الكونسيرج' },
    conciergeSub: { en: 'Dedicated support', ar: 'دعم مخصّص' },
    f1: { en: 'Appointment Scheduling', ar: 'تنظيم المواعيد' },
    f2: { en: 'Airport Pickup', ar: 'الاستقبال في المطار' },
    f3: { en: 'Luxury Accommodation', ar: 'إقامة فاخرة' },
    f4: { en: 'Bilingual Coordinators', ar: 'منسّقون بلغتين' },
  },
  servicesPage: {
    title: { en: 'Our Treatments', ar: 'خدماتنا العلاجية' },
    subtitle: {
      en: 'Explore our aesthetic and dermatology treatment categories, then find the right doctor for you.',
      ar: 'تصفّحي فئات خدماتنا التجميلية والجلدية، ثم اختاري الطبيب المناسب لكِ.',
    },
    cardCta: { en: 'Find Doctors', ar: 'اعثري على طبيب' },
  },
  clinics: {
    title: { en: 'Our Clinics', ar: 'فروعنا' },
    subtitle: { en: 'Find an EVA Medical branch near you.', ar: 'اعثري على أقرب فرع لإيفا الطبية.' },
    partner: { en: 'Clinic Group', ar: 'المجموعة' },
    service: { en: 'Treatment', ar: 'الخدمة' },
    searchLabel: { en: 'Search', ar: 'بحث' },
    searchPlaceholder: { en: 'Search by name or area…', ar: 'ابحثي بالاسم أو المنطقة…' },
    allPartners: { en: 'All Groups', ar: 'كل المجموعات' },
    allServices: { en: 'All Treatments', ar: 'كل الخدمات' },
    noResults: { en: 'No clinics match your filters.', ar: 'لا توجد فروع مطابقة لبحثك.' },
  },
  footer: {
    tagline: {
      en: 'Advanced aesthetic and dermatology care in Kuwait City. Where medical expertise meets timeless beauty.',
      ar: 'رعاية تجميلية وجلدية متقدمة في مدينة الكويت. حيث تلتقي الخبرة الطبية بالجمال الخالد.',
    },
    exploreTitle: { en: 'Explore', ar: 'استكشفي' },
    contactTitle: { en: 'Contact Us', ar: 'تواصلي معنا' },
    rights: { en: 'All rights reserved.', ar: 'جميع الحقوق محفوظة.' },
    privacy: { en: 'Privacy Policy', ar: 'سياسة الخصوصية' },
    terms: { en: 'Terms of Service', ar: 'الشروط والأحكام' },
  },
  select: {
    search: { en: 'Search…', ar: 'بحث…' },
    none: { en: 'No matches', ar: 'لا نتائج' },
  },
  booking: {
    tabNew: { en: 'New Booking', ar: 'حجز جديد' },
    tabManage: { en: 'Manage Booking', ar: 'إدارة الحجز' },
    stepOf: { en: 'Step', ar: 'الخطوة' },
    of: { en: 'of', ar: 'من' },
    t1: { en: 'Find a Clinic', ar: 'اختاري الفرع' },
    t2: { en: 'Select Specialist', ar: 'اختاري الطبيب' },
    t3: { en: 'Choose Time', ar: 'اختاري الوقت' },
    t4: { en: 'Confirm Details', ar: 'تأكيد التفاصيل' },
    s1: { en: 'Where would you like to visit?', ar: 'أي فرع تودّين زيارته؟' },
    s2: { en: 'Who would you like to see?', ar: 'مع أي طبيب تودّين الحجز؟' },
    s3: { en: 'Select a date and available slot.', ar: 'اختاري التاريخ والوقت المتاح.' },
    s4: { en: 'Please review your booking info.', ar: 'يرجى مراجعة بيانات الحجز.' },
    manageTitle: { en: 'Manage Booking', ar: 'إدارة الحجز' },
    manageSub: { en: 'Cancel or view details of your visit.', ar: 'إلغاء أو عرض تفاصيل موعدك.' },
    next: { en: 'Next', ar: 'التالي' },
    goBack: { en: 'Go Back', ar: 'رجوع' },
    confirm: { en: 'Confirm Booking', ar: 'تأكيد الحجز' },
    loadingClinics: { en: 'Loading clinics…', ar: 'جارٍ تحميل الفروع…' },
    noClinics: { en: 'No clinics available at the moment.', ar: 'لا توجد فروع متاحة حاليًا.' },
    findingSpecialists: { en: 'Finding specialists…', ar: 'جارٍ تحميل الأطباء…' },
    noDoctors: { en: 'No doctors found for this location.', ar: 'لا يوجد أطباء في هذا الفرع.' },
    checkingAvail: { en: 'Checking availability…', ar: 'جارٍ التحقق من المواعيد…' },
    noSlots: { en: 'No slots available', ar: 'لا توجد مواعيد متاحة' },
    noSlotsHint: { en: 'Please try another date', ar: 'يرجى تجربة تاريخ آخر' },
    pickDate: { en: 'Select a date above to view times', ar: 'اختاري تاريخًا لعرض الأوقات' },
    summary: { en: 'Booking Summary', ar: 'ملخص الحجز' },
    selectedClinic: { en: 'Selected Clinic', ar: 'الفرع المختار' },
    specialist: { en: 'Specialist', ar: 'الطبيب' },
    apptTime: { en: 'Appointment Time', ar: 'موعد الزيارة' },
    patientName: { en: 'Patient Name', ar: 'اسم المريض' },
    patientNamePh: { en: 'e.g. Ali Ahmed', ar: 'مثال: علي أحمد' },
    mobile: { en: 'Mobile Number', ar: 'رقم الجوال' },
    refCode: { en: 'Reference Code', ar: 'رمز الحجز' },
    refPh: { en: 'e.g. A1B2C', ar: 'مثال: A1B2C' },
    manageIntro: { en: 'To cancel or reschedule, enter the mobile number used during booking and your reference code.', ar: 'للإلغاء أو إعادة الجدولة، أدخلي رقم الجوال المستخدم عند الحجز ورمز الحجز.' },
    cancelBooking: { en: 'Cancel Booking', ar: 'إلغاء الحجز' },
    confirmedTitle: { en: 'Booking Confirmed!', ar: 'تم تأكيد الحجز!' },
    confirmedSub: { en: 'Your appointment has been successfully scheduled.', ar: 'تم حجز موعدك بنجاح.' },
    bookingRef: { en: 'Booking Reference', ar: 'رمز الحجز' },
    bookAnother: { en: 'Book Another Appointment', ar: 'حجز موعد آخر' },
    cancelledTitle: { en: 'Booking Cancelled', ar: 'تم إلغاء الحجز' },
    cancelledSub: { en: 'Your appointment has been removed from our system.', ar: 'تمت إزالة موعدك من النظام.' },
    // OTP
    verifyWa: { en: 'Verify on WhatsApp', ar: 'التحقق عبر واتساب' },
    sentCodeA: { en: 'We sent a 6-digit code to', ar: 'أرسلنا رمزًا من 6 أرقام إلى' },
    enterCode: { en: 'Enter 6-digit code', ar: 'أدخلي الرمز المكوّن من 6 أرقام' },
    verifyConfirm: { en: 'Verify & Confirm Booking', ar: 'تحقّق وأكّد الحجز' },
    resendIn: { en: 'Resend code in', ar: 'إعادة الإرسال خلال' },
    resend: { en: 'Resend code', ar: 'إعادة إرسال الرمز' },
    sending: { en: 'Sending…', ar: 'جارٍ الإرسال…' },
    close: { en: 'Close', ar: 'إغلاق' },
    attention: { en: 'Attention', ar: 'تنبيه' },
    dismiss: { en: 'Dismiss', ar: 'حسنًا' },
    // validation / errors
    errMobile: { en: 'Please enter a valid mobile number.', ar: 'يرجى إدخال رقم جوال صحيح.' },
    errMobileRef: { en: 'Please enter your Mobile Number and Booking Reference.', ar: 'يرجى إدخال رقم الجوال ورمز الحجز.' },
    errOtpIncomplete: { en: 'Enter the full 6-digit code we sent on WhatsApp.', ar: 'أدخلي الرمز الكامل المكوّن من 6 أرقام المُرسل عبر واتساب.' },
    errOtpInvalid: { en: 'That code is invalid or expired. Try again or resend.', ar: 'الرمز غير صحيح أو منتهٍ. حاولي مجددًا أو أعيدي الإرسال.' },
    errVerify: { en: 'Verification failed. Please try again.', ar: 'فشل التحقق. يرجى المحاولة مرة أخرى.' },
    errUnable: { en: 'Unable to complete booking. Please try again.', ar: 'تعذّر إكمال الحجز. يرجى المحاولة مرة أخرى.' },
    errUnexpected: { en: 'An unexpected error occurred. Please try again.', ar: 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.' },
    errResend: { en: 'Could not resend the code. Please try again in a moment.', ar: 'تعذّر إعادة إرسال الرمز. حاولي بعد قليل.' },
    errResendWait: { en: 'Please wait before requesting another code.', ar: 'يرجى الانتظار قبل طلب رمز جديد.' },
    errCancelVerify: { en: 'Could not verify booking details.', ar: 'تعذّر التحقق من بيانات الحجز.' },
    errCantFind: { en: "We couldn't find a booking matching those details. Please check your reference and try again.", ar: 'لم نعثر على حجز مطابق لهذه البيانات. يرجى التحقق من الرمز والمحاولة مجددًا.' },
  },
}
