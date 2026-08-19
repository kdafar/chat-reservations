<?php

namespace App\Support;

/**
 * Plain-language wording for every HTTP error the app can show.
 *
 * One source of truth for two surfaces: the standalone Blade pages in
 * resources/views/errors, and the in-app Inertia error panel that renders
 * inside the v2 shell. Both must say exactly the same thing.
 *
 * House rules for the copy:
 *   - Say what happened and what the person can do next. Nothing else.
 *   - No paths, no status names, no trace IDs, no "clear your cache".
 *   - For anything the server owns, say plainly that it isn't their fault.
 */
class ErrorCopy
{
    /** [headline, message] per code, per language. */
    private const COPY = [
        '400' => [
            'en' => ['That request didn’t look right', 'Something in the request was incomplete. Please go back and try again.'],
            'ar' => ['الطلب غير صحيح', 'هناك نقص في بيانات الطلب. يرجى الرجوع والمحاولة مرة أخرى.'],
        ],
        '401' => [
            'en' => ['Please sign in', 'You need to be signed in to open this page.'],
            'ar' => ['يرجى تسجيل الدخول', 'تحتاج إلى تسجيل الدخول لفتح هذه الصفحة.'],
        ],
        '402' => [
            'en' => ['Payment needed first', 'This needs to be paid for before you can continue.'],
            'ar' => ['الدفع مطلوب أولاً', 'يجب إتمام الدفع قبل المتابعة.'],
        ],
        '403' => [
            'en' => ['This page isn’t part of your access', 'Your account doesn’t include this section. If you need it, ask your clinic manager to add it.'],
            'ar' => ['هذه الصفحة خارج صلاحياتك', 'حسابك لا يشمل هذا القسم. إذا كنت بحاجة إليه، اطلب من مدير العيادة إضافته.'],
        ],
        '404' => [
            'en' => ['We couldn’t find that page', 'It may have been moved or removed. Try again from the home page.'],
            'ar' => ['لم نتمكن من العثور على الصفحة', 'ربما تم نقلها أو حذفها. حاول مرة أخرى من الصفحة الرئيسية.'],
        ],
        '405' => [
            'en' => ['That didn’t work', 'This action isn’t available here. Please go back and try again.'],
            'ar' => ['لم تنجح هذه العملية', 'هذا الإجراء غير متاح هنا. يرجى الرجوع والمحاولة مرة أخرى.'],
        ],
        '408' => [
            'en' => ['That took too long', 'The connection timed out. Please check your internet and try again.'],
            'ar' => ['استغرقت العملية وقتاً طويلاً', 'انتهت مهلة الاتصال. يرجى التحقق من الإنترنت والمحاولة مرة أخرى.'],
        ],
        '409' => [
            'en' => ['Someone else changed this first', 'This record was updated while you had it open. Please reload the page and redo your change.'],
            'ar' => ['قام شخص آخر بالتعديل قبلك', 'تم تحديث هذا السجل أثناء فتحك له. يرجى إعادة تحميل الصفحة وتكرار التعديل.'],
        ],
        '410' => [
            'en' => ['This is no longer available', 'The page or link you used has been removed.'],
            'ar' => ['لم يعد هذا متاحاً', 'تمت إزالة الصفحة أو الرابط الذي استخدمته.'],
        ],
        '413' => [
            'en' => ['That file is too large', 'Please upload a smaller file and try again.'],
            'ar' => ['حجم الملف كبير جداً', 'يرجى رفع ملف أصغر والمحاولة مرة أخرى.'],
        ],
        '419' => [
            'en' => ['You were signed out', 'You were away for a while, so we signed you out to keep patient data safe. Sign in again to carry on.'],
            'ar' => ['تم تسجيل خروجك', 'لعدم استخدامك النظام لفترة، قمنا بتسجيل خروجك للحفاظ على بيانات المرضى. سجّل الدخول للمتابعة.'],
        ],
        '422' => [
            'en' => ['Something in the form needs fixing', 'Please go back, check the details you entered, and save again.'],
            'ar' => ['هناك خطأ في البيانات المدخلة', 'يرجى الرجوع ومراجعة البيانات التي أدخلتها ثم الحفظ مرة أخرى.'],
        ],
        '423' => [
            'en' => ['This is locked right now', 'Someone else is working on it. Please try again shortly.'],
            'ar' => ['هذا العنصر مقفل حالياً', 'شخص آخر يعمل عليه. يرجى المحاولة بعد قليل.'],
        ],
        '429' => [
            'en' => ['Too many attempts', 'Please wait a minute, then try again.'],
            'ar' => ['محاولات كثيرة جداً', 'يرجى الانتظار دقيقة ثم المحاولة مرة أخرى.'],
        ],
        '451' => [
            'en' => ['This isn’t available here', 'This content can’t be shown in your region.'],
            'ar' => ['هذا غير متاح هنا', 'لا يمكن عرض هذا المحتوى في منطقتك.'],
        ],
        '500' => [
            'en' => ['Something went wrong on our side', 'This isn’t your fault. Please try again — if it keeps happening, let your clinic manager know.'],
            'ar' => ['حدث خطأ من جانبنا', 'هذا ليس خطأك. يرجى المحاولة مرة أخرى — وإذا تكرر الأمر، أبلغ مدير العيادة.'],
        ],
        '501' => [
            'en' => ['This isn’t available yet', 'That feature hasn’t been switched on for your clinic.'],
            'ar' => ['هذه الميزة غير متاحة بعد', 'لم يتم تفعيل هذه الميزة لعيادتك.'],
        ],
        '502' => [
            'en' => ['The system is having trouble responding', 'Please try again in a moment.'],
            'ar' => ['النظام يواجه صعوبة في الاستجابة', 'يرجى المحاولة بعد قليل.'],
        ],
        '503' => [
            'en' => ['We’re doing some maintenance', 'The system will be back shortly. Please try again in a few minutes.'],
            'ar' => ['نقوم ببعض أعمال الصيانة', 'سيعود النظام قريباً. يرجى المحاولة بعد بضع دقائق.'],
        ],
        '504' => [
            'en' => ['That took too long', 'The request timed out before it finished. Please try again.'],
            'ar' => ['استغرقت العملية وقتاً طويلاً', 'انتهت مهلة الطلب قبل اكتماله. يرجى المحاولة مرة أخرى.'],
        ],
    ];

    /** Wording for codes with no entry of their own, split by who's at fault. */
    private const FALLBACK = [
        'client' => [
            'en' => ['That didn’t work', 'We couldn’t complete that. Please go back and try again.'],
            'ar' => ['لم تنجح هذه العملية', 'لم نتمكن من إتمام ذلك. يرجى الرجوع والمحاولة مرة أخرى.'],
        ],
        'server' => [
            'en' => ['Something went wrong on our side', 'This isn’t your fault. Please try again — if it keeps happening, let your clinic manager know.'],
            'ar' => ['حدث خطأ من جانبنا', 'هذا ليس خطأك. يرجى المحاولة مرة أخرى — وإذا تكرر الأمر، أبلغ مدير العيادة.'],
        ],
    ];

    /** UI chrome — button labels and the like. */
    private const LABELS = [
        'en' => [
            'home' => 'Go to home', 'dashboard' => 'Go to dashboard', 'back' => 'Go back',
            'signin' => 'Sign in', 'retry' => 'Try again', 'ref' => 'Reference',
        ],
        'ar' => [
            'home' => 'الصفحة الرئيسية', 'dashboard' => 'لوحة التحكم', 'back' => 'رجوع',
            'signin' => 'تسجيل الدخول', 'retry' => 'إعادة المحاولة', 'ref' => 'رقم مرجعي',
        ],
    ];

    public static function lang(?string $locale = null): string
    {
        return str_starts_with($locale ?? app()->getLocale(), 'ar') ? 'ar' : 'en';
    }

    /** @return array{headline: string, message: string} */
    public static function for(int|string $code, ?string $locale = null): array
    {
        $lang = self::lang($locale);
        $key = (string) $code;

        $pair = self::COPY[$key][$lang]
            ?? self::FALLBACK[(int) $code >= 500 ? 'server' : 'client'][$lang];

        return ['headline' => $pair[0], 'message' => $pair[1]];
    }

    /** @return array<string, string> */
    public static function labels(?string $locale = null): array
    {
        return self::LABELS[self::lang($locale)];
    }

    /**
     * Which button should lead. A dead session wants "sign in"; anything the
     * server owns (plus timeouts, locks and rate-limits) is worth retrying;
     * everything else just needs a way out.
     */
    public static function primaryAction(int|string $code): string
    {
        if (in_array((string) $code, ['401', '419'], true)) {
            return 'signin';
        }

        if ((int) $code >= 500 || in_array((string) $code, ['408', '423', '429'], true)) {
            return 'retry';
        }

        return 'home';
    }

    /** Codes that have wording written for them by hand. */
    public static function knownCodes(): array
    {
        return array_map('intval', array_keys(self::COPY));
    }
}
