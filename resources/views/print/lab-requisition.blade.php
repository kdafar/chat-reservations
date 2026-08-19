{{--
    Sample requisition slip — what the lab assistant prints and keeps with the
    specimen. Deliberately compact (half A4) and result-free: it carries the
    order code, who the sample belongs to, what has to be run, and the specimen
    type per test, with tick boxes for the bench.

    Expects: $order (items.labTest, patient, doctor), $clinic, $ar, $mode,
             $patientAge, $patientGender, $doctorName
--}}
<!DOCTYPE html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $ar ? 'طلب عينة' : 'Sample Requisition' }} — {{ $order->order_code }}</title>
    <style>
        @page { margin: 0; size: A4; }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .no-print { display: none !important; } }
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", "Noto Sans Arabic", "DejaVu Sans", Arial, sans-serif; color: #111827; margin: 0; font-size: 11pt; }
        .slip { width: 210mm; padding: 12mm 14mm; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; border-bottom: 2px solid #111827; padding-bottom: 8px; }
        .clinic { font-size: 13pt; font-weight: 800; }
        .kind { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-top: 2px; }
        .code { font-family: "DejaVu Sans Mono", monospace; font-size: 15pt; font-weight: 800; letter-spacing: 0.5px; white-space: nowrap; }
        .code-cap { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; text-align: {{ $ar ? 'left' : 'right' }}; }
        .urgent { display: inline-block; margin-top: 4px; padding: 2px 10px; background: #111827; color: #fff; font-size: 8.5pt; font-weight: 800; letter-spacing: 1px; border-radius: 3px; }

        .grid { display: flex; flex-wrap: wrap; gap: 6px 28px; margin: 12px 0 14px; }
        .cell { min-width: 130px; }
        .cap { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; font-weight: 700; }
        .val { font-size: 11pt; font-weight: 700; }

        table { width: 100%; border-collapse: collapse; border: 1px solid #111827; }
        th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; text-align: {{ $ar ? 'right' : 'left' }}; padding: 6px 10px; border-bottom: 1px solid #111827; }
        td { padding: 9px 10px; border-bottom: 1px solid #e5e7eb; font-size: 10.5pt; }
        tr:last-child td { border-bottom: none; }
        .box { display: inline-block; width: 13px; height: 13px; border: 1.5px solid #111827; }
        .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; color: #4b5563; }
        .note { margin-top: 12px; padding: 8px 12px; border: 1px dashed #9ca3af; font-size: 9.5pt; white-space: pre-line; }
        .sigrow { display: flex; gap: 30px; margin-top: 26px; }
        .sigbox { flex: 1; }
        .sigline { border-top: 1px solid #111827; margin-top: 26px; padding-top: 3px; font-size: 8.5pt; color: #6b7280; }
        .cut { margin-top: 16px; border-top: 1px dashed #9ca3af; text-align: center; font-size: 7.5pt; color: #9ca3af; padding-top: 3px; }
    </style>
</head>
<body>
<div class="slip">
    <div class="top">
        <div>
            <div class="clinic">{{ $clinic['name'] }}@if($clinic['branch']) — {{ $clinic['branch'] }}@endif</div>
            <div class="kind">{{ $ar ? 'طلب عينة مختبر / Sample Requisition' : 'Laboratory Sample Requisition' }}</div>
        </div>
        <div>
            <div class="code-cap">{{ $ar ? 'رقم الطلب' : 'Order No.' }}</div>
            <div class="code">{{ $order->order_code }}</div>
            @if($order->priority === \App\Models\Lab\LabOrder::PRIORITY_URGENT)
                <div class="urgent">{{ $ar ? 'عاجل URGENT' : 'URGENT' }}</div>
            @endif
        </div>
    </div>

    <div class="grid">
        <div class="cell"><div class="cap">{{ $ar ? 'المريض' : 'Patient' }}</div><div class="val">{{ $order->patient?->name ?? '—' }}</div></div>
        <div class="cell"><div class="cap">{{ $ar ? 'العمر / الجنس' : 'Age / Gender' }}</div><div class="val">{{ $patientAge !== null ? $patientAge : '—' }} / {{ $patientGender }}</div></div>
        <div class="cell"><div class="cap">{{ $ar ? 'رقم الملف' : 'File No.' }}</div><div class="val">#{{ $order->patient_id ?? '—' }}</div></div>
        <div class="cell"><div class="cap">{{ $ar ? 'الطبيب' : 'Doctor' }}</div><div class="val">{{ $doctorName }}</div></div>
        <div class="cell"><div class="cap">{{ $ar ? 'وقت الطلب' : 'Ordered' }}</div><div class="val">{{ optional($order->ordered_at)->format('d M Y, H:i') ?? '—' }}</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:34px">✓</th>
                <th>{{ $ar ? 'التحليل المطلوب' : 'Requested Test' }}</th>
                <th style="width:22%">{{ $ar ? 'نوع العينة' : 'Specimen' }}</th>
                <th style="width:24%">{{ $ar ? 'المعدل المرجعي' : 'Reference' }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            <tr>
                <td><span class="box"></span></td>
                <td>
                    <strong>{{ $item->labTest?->name ?? '—' }}</strong>
                    @if($item->labTest?->code)<span class="mono"> · {{ $item->labTest->code }}</span>@endif
                </td>
                <td class="mono">{{ $item->labTest?->specimen_type ?: '—' }}</td>
                <td class="mono">{{ $item->reference_range_snapshot ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:20px;">{{ $ar ? 'لا توجد تحاليل.' : 'No tests.' }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @if($order->clinical_note)
        <div class="note"><strong>{{ $ar ? 'ملاحظة الطبيب:' : 'Clinical note:' }}</strong> {{ $order->clinical_note }}</div>
    @endif

    <div class="sigrow">
        <div class="sigbox"><div class="sigline">{{ $ar ? 'سحب العينة — الاسم والوقت' : 'Sample collected by — name & time' }}</div></div>
        <div class="sigbox"><div class="sigline">{{ $ar ? 'استلام المختبر' : 'Received in lab by' }}</div></div>
    </div>

    <div class="cut">{{ $ar ? 'يُرفق مع العينة' : 'Keep with the specimen' }} · {{ $order->order_code }}</div>
</div>

@if($mode === 'print')
    <script>window.onload = function () { window.print(); };</script>
@endif
</body>
</html>
