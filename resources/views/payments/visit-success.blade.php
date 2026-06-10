<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Payment received') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:#f6f7f9; margin:0; display:flex; min-height:100vh; align-items:center; justify-content:center; }
        .card { background:#fff; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,.08); padding:36px 32px; max-width:380px; text-align:center; }
        .tick { width:64px; height:64px; border-radius:50%; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:34px; margin:0 auto 18px; }
        h1 { font-size:20px; margin:0 0 8px; color:#111; }
        p { color:#555; font-size:14px; margin:4px 0; }
        .amt { font-size:24px; font-weight:700; color:#111; margin:14px 0; }
        .ref { font-size:12px; color:#999; }
    </style>
</head>
<body>
    <div class="card">
        <div class="tick">&#10003;</div>
        <h1>{{ app()->getLocale() === 'ar' ? 'تم استلام الدفعة' : 'Payment received' }}</h1>
        <div class="amt">{{ number_format((float) $amount, 3) }} {{ app()->getLocale() === 'ar' ? 'د.ك' : 'KWD' }}</div>
        <p>{{ app()->getLocale() === 'ar' ? 'شكراً لك. يمكنك إغلاق هذه الصفحة.' : 'Thank you. You can close this page.' }}</p>
        <p class="ref">{{ app()->getLocale() === 'ar' ? 'المرجع' : 'Ref' }}: {{ $ref }}</p>
    </div>
</body>
</html>
