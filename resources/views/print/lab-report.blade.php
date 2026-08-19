{{--
    Laboratory result report.

    Serves three surfaces from one template:
      $mode = 'print'  → opened in a browser tab, auto-triggers the print dialog
      $mode = 'view'   → same page without the auto-print (embedded preview)
      $mode = 'render' → fed to headless Chromium for the PDF / PNG we send out

    Everything is inline and self-contained (no remote fonts, logo inlined as a
    data URI by the controller) because the render path loads this from file://
    with no network access.

    Expects: $order (items.labTest, patient, doctor, branch), $clinic array,
             $logoData (data-URI string|null), $ar (bool), $mode
--}}
<!DOCTYPE html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ar ? 'تقرير مختبر' : 'Lab Report' }} — {{ $order->patient?->name }} — {{ $order->order_code }}</title>
    <style>
        @page { margin: 0; size: A4; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }

        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", "Noto Sans Arabic", "DejaVu Sans", Arial, sans-serif;
            color: #1f2937; background: #fff; margin: 0; padding: 0;
            font-size: 11pt; line-height: 1.45;
        }
        .page { width: 210mm; min-height: 296mm; padding: 14mm 16mm; margin: 0 auto; background: #fff; position: relative; }

        /* Letterhead */
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0d9488; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { display: flex; align-items: center; gap: 16px; }
        .logo { height: 64px; width: auto; object-fit: contain; }
        .clinic-name { margin: 0; font-size: 18pt; font-weight: 800; color: #111827; letter-spacing: -0.3px; }
        .clinic-sub { margin: 3px 0 0; font-size: 8.5pt; color: #4b5563; line-height: 1.35; }
        .code-box { text-align: {{ $ar ? 'left' : 'right' }}; min-width: 170px; }
        .code-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.6px; color: #0d9488; font-weight: 700; }
        .code-value { font-size: 13pt; font-weight: 800; color: #0f766e; font-family: "DejaVu Sans Mono", monospace; }
        .code-meta { font-size: 8.5pt; color: #6b7280; margin-top: 2px; }

        .title { text-align: center; margin: 4px 0 16px; }
        .title h2 { margin: 0; font-size: 16pt; font-weight: 800; letter-spacing: 0.6px; color: #0f766e; text-transform: uppercase; }
        .title .sub { font-size: 11pt; color: #6b7280; margin-top: 1px; }
        .urgent-tag { display: inline-block; margin-top: 6px; padding: 2px 12px; border-radius: 999px; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 8.5pt; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }

        /* Patient strip */
        .meta-bar { display: flex; flex-wrap: wrap; gap: 10px 26px; background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 8px; padding: 11px 16px; margin-bottom: 16px; }
        .meta-group { display: flex; flex-direction: column; min-width: 110px; }
        .meta-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; color: #0d9488; font-weight: 700; margin-bottom: 1px; }
        .meta-value { font-size: 10pt; font-weight: 600; color: #111827; }

        .note-box { background: #f8fafc; border-inline-start: 4px solid #0d9488; padding: 8px 14px; border-radius: 4px; margin-bottom: 14px; }
        .note-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; color: #0d9488; font-weight: 700; }
        .note-text { font-size: 10pt; color: #1f2937; margin-top: 1px; white-space: pre-line; }

        .eyebrow { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.6px; color: #0d9488; font-weight: 700; margin-bottom: 6px; }

        /* Results table */
        table.results { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        table.results thead th {
            background: #f9fafb; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px;
            color: #6b7280; font-weight: 700; text-align: {{ $ar ? 'right' : 'left' }};
            padding: 8px 12px; border-bottom: 1px solid #e5e7eb;
        }
        table.results tbody td { padding: 9px 12px; border-bottom: 1px solid #f3f4f6; font-size: 10pt; vertical-align: top; }
        table.results tbody tr:last-child td { border-bottom: none; }
        .test-name { font-weight: 700; color: #111827; }
        .test-code { font-size: 8pt; color: #9ca3af; font-family: "DejaVu Sans Mono", monospace; }
        .result-val { font-weight: 800; font-size: 11pt; color: #111827; white-space: nowrap; }
        .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 9.5pt; color: #4b5563; }
        .item-note { font-size: 8.5pt; color: #6b7280; margin-top: 2px; }

        .flag { display: inline-block; padding: 1px 9px; border-radius: 999px; font-size: 8pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; }
        .flag-normal   { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .flag-low      { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .flag-high     { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .flag-critical { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .flag-none     { background: #f9fafb; color: #9ca3af; border: 1px solid #e5e7eb; }
        tr.is-abnormal td { background: #fffdf7; }
        tr.is-critical td { background: #fef7f7; }
        .pending { color: #9ca3af; font-style: italic; }

        .legend { margin-top: 8px; font-size: 8pt; color: #9ca3af; }

        .footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 34px; gap: 20px; }
        .sig { text-align: center; min-width: 200px; }
        .sig-line { border-top: 1.5px solid #374151; margin-bottom: 5px; }
        .sig-name { font-weight: 700; color: #1f2937; font-size: 10pt; }
        .sig-role { font-size: 8.5pt; color: #6b7280; }
        .disclaimer { font-size: 7.5pt; color: #9ca3af; text-align: center; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 8px; }
    </style>
</head>
<body>
@if($mode === 'print')
    <div class="no-print" style="max-width:760px;margin:12px auto;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:8px;font-size:12px;line-height:1.5;">
        💡 To hide the page number &amp; web address your browser adds: in the Print dialog set <strong>Margins → None</strong> or untick <strong>Headers and footers</strong>.<br>
        <span dir="rtl">لإخفاء رقم الصفحة والرابط الذي يضيفه المتصفح: في نافذة الطباعة اضبط الهوامش إلى «بلا» أو ألغِ «رؤوس وتذييلات الصفحة».</span>
    </div>
@endif

<div class="page">
    <div class="header">
        <div class="brand">
            @if($logoData)
                <img src="{{ $logoData }}" alt="" class="logo">
            @endif
            <div>
                <h1 class="clinic-name">{{ $clinic['name'] }}</h1>
                <p class="clinic-sub">
                    @if($clinic['branch'])<strong>{{ $clinic['branch'] }}</strong><br>@endif
                    @if($clinic['address']){{ $clinic['address'] }}<br>@endif
                    @if($clinic['phone']){{ $ar ? 'هاتف' : 'Tel' }}: {{ $clinic['phone'] }}@endif
                    @if($clinic['license']) <span style="color:#9ca3af;">· {{ $ar ? 'ترخيص' : 'Lic' }}: {{ $clinic['license'] }}</span>@endif
                </p>
            </div>
        </div>
        <div class="code-box">
            <div class="code-label">{{ $ar ? 'رقم الطلب' : 'Order No.' }}</div>
            <div class="code-value">{{ $order->order_code }}</div>
            <div class="code-meta">
                {{ $ar ? 'طُلب' : 'Ordered' }}: {{ optional($order->ordered_at)->format('d M Y, H:i') ?? '—' }}<br>
                {{ $ar ? 'صدر' : 'Reported' }}: {{ optional($order->completed_at)->format('d M Y, H:i') ?? '—' }}
            </div>
        </div>
    </div>

    <div class="title">
        <h2>{{ $ar ? 'تقرير نتائج المختبر' : 'Laboratory Report' }}</h2>
        <div class="sub">{{ $ar ? 'Laboratory Report' : 'تقرير نتائج المختبر' }}</div>
        @if($order->priority === \App\Models\Lab\LabOrder::PRIORITY_URGENT)
            <div class="urgent-tag">{{ $ar ? 'عاجل — URGENT' : 'URGENT' }}</div>
        @endif
    </div>

    <div class="meta-bar">
        <div class="meta-group">
            <span class="meta-label">{{ $ar ? 'اسم المريض' : 'Patient' }}</span>
            <span class="meta-value">{{ $order->patient?->name ?? '—' }}</span>
        </div>
        <div class="meta-group">
            <span class="meta-label">{{ $ar ? 'العمر / الجنس' : 'Age / Gender' }}</span>
            <span class="meta-value">{{ $patientAge !== null ? $patientAge.($ar ? ' سنة' : ' yrs') : '—' }} / {{ $patientGender }}</span>
        </div>
        <div class="meta-group">
            <span class="meta-label">{{ $ar ? 'رقم الملف' : 'File No.' }}</span>
            <span class="meta-value">#{{ $order->patient_id ?? '—' }}</span>
        </div>
        <div class="meta-group">
            <span class="meta-label">{{ $ar ? 'الطبيب المُحوِّل' : 'Referring Doctor' }}</span>
            <span class="meta-value">{{ $doctorName }}</span>
        </div>
        <div class="meta-group">
            <span class="meta-label">{{ $ar ? 'الزيارة' : 'Visit' }}</span>
            <span class="meta-value">#{{ $order->visit_id }}</span>
        </div>
    </div>

    @if($order->clinical_note)
        <div class="note-box">
            <div class="note-label">{{ $ar ? 'السياق السريري' : 'Clinical Note' }}</div>
            <div class="note-text">{{ $order->clinical_note }}</div>
        </div>
    @endif

    <div class="eyebrow">{{ $ar ? 'النتائج' : 'Results' }}</div>
    <table class="results">
        <thead>
            <tr>
                <th style="width:34%">{{ $ar ? 'التحليل' : 'Test' }}</th>
                <th style="width:18%">{{ $ar ? 'النتيجة' : 'Result' }}</th>
                <th style="width:11%">{{ $ar ? 'الوحدة' : 'Unit' }}</th>
                <th style="width:23%">{{ $ar ? 'المعدل المرجعي' : 'Reference Range' }}</th>
                <th style="width:14%">{{ $ar ? 'الحالة' : 'Flag' }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            @php
                $flag = $item->flag;
                $rowClass = $flag === 'critical' ? 'is-critical' : (in_array($flag, ['low', 'high'], true) ? 'is-abnormal' : '');
                $flagLabels = $ar
                    ? ['normal' => 'طبيعي', 'low' => 'منخفض', 'high' => 'مرتفع', 'critical' => 'خطير']
                    : ['normal' => 'Normal', 'low' => 'Low', 'high' => 'High', 'critical' => 'Critical'];
            @endphp
            <tr class="{{ $rowClass }}">
                <td>
                    <div class="test-name">{{ $item->labTest?->name ?? '—' }}</div>
                    @if($item->labTest?->code)<div class="test-code">{{ $item->labTest->code }}@if($item->labTest?->specimen_type) · {{ $item->labTest->specimen_type }}@endif</div>@endif
                </td>
                <td>
                    @if(trim((string) $item->result_value) !== '')
                        <span class="result-val">{{ $item->result_value }}</span>
                    @else
                        <span class="pending">{{ $ar ? 'قيد التنفيذ' : 'Pending' }}</span>
                    @endif
                    @if($item->notes)<div class="item-note">{{ $item->notes }}</div>@endif
                </td>
                <td class="mono">{{ $item->result_unit ?: '—' }}</td>
                <td class="mono">{{ $item->reference_range_snapshot ?: '—' }}</td>
                <td>
                    <span class="flag flag-{{ $flag ?: 'none' }}">{{ $flag ? ($flagLabels[$flag] ?? $flag) : '—' }}</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;padding:26px;">{{ $ar ? 'لا توجد تحاليل على هذا الطلب.' : 'No tests on this order.' }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @if($hasAbnormal)
        <div class="legend">
            {{ $ar
                ? 'النتائج المظللة خارج المعدل المرجعي. يُرجى مراجعة الطبيب المعالج لتفسير النتائج.'
                : 'Shaded rows fall outside the reference range. Please discuss results with your treating physician.' }}
        </div>
    @endif

    @if($order->lab_note)
        <div class="note-box" style="margin-top:16px;">
            <div class="note-label">{{ $ar ? 'ملاحظة المختبر' : 'Laboratory Comment' }}</div>
            <div class="note-text">{{ $order->lab_note }}</div>
        </div>
    @endif

    <div class="footer">
        <div style="font-size:8.5pt;color:#6b7280;max-width:52%;">
            {{ $ar
                ? 'هذا التقرير صادر إلكترونياً ومرتبط بملف المريض. النتائج لا تُشكّل تشخيصاً بذاتها.'
                : 'This report is issued electronically and stored on the patient record. Results are not a diagnosis on their own.' }}
        </div>
        <div class="sig">
            <div class="sig-line"></div>
            <div class="sig-name">{{ $releasedByName }}</div>
            <div class="sig-role">{{ $ar ? 'أخصائي المختبر' : 'Laboratory' }}</div>
        </div>
    </div>

    <div class="disclaimer">
        {{ $ar ? 'أُنشئ في' : 'Generated' }} {{ now()->format('d M Y, H:i') }} ·
        {{ $order->order_code }} · {{ $ar ? 'زيارة' : 'Visit' }} #{{ $order->visit_id }}
    </div>
</div>

@if($mode === 'print')
    <script>window.onload = function () { window.print(); };</script>
@endif
</body>
</html>
