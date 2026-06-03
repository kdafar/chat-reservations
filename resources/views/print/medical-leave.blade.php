<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Leave - {{ $visit->patient->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

        @media print {
            @page { margin: 0; size: A4; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }

        body { font-family: 'Inter', sans-serif; color: #1f2937; background: #fff; margin: 0; padding: 0; font-size: 12pt; line-height: 1.5; }
        .page { width: 210mm; min-height: 290mm; padding: 15mm 20mm; margin: 0 auto; background: white; position: relative; box-sizing: border-box; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px; }
        .brand-area { display: flex; align-items: center; gap: 20px; }
        .logo { height: 90px; width: auto; object-fit: contain; }
        .clinic-info h1 { margin: 0; font-size: 22pt; font-weight: 800; text-transform: uppercase; color: #111827; letter-spacing: -0.5px; }
        .clinic-info p { margin: 4px 0 0; font-size: 9pt; color: #4b5563; line-height: 1.4; }
        .doc-info { text-align: right; min-width: 200px; }
        .doc-name { font-size: 14pt; font-weight: 700; color: #0d9488; margin: 0; }
        .doc-meta { font-size: 9pt; color: #6b7280; margin: 2px 0 0; }

        .title-block { text-align: center; margin: 10px 0 28px; }
        .title-block h2 { margin: 0; font-size: 18pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #0f766e; }
        .title-block .sub { font-size: 12pt; color: #6b7280; margin-top: 2px; }

        .meta-bar { display: flex; background-color: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 8px; padding: 12px 20px; margin-bottom: 30px; justify-content: space-between; }
        .meta-group { display: flex; flex-direction: column; }
        .meta-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; color: #0d9488; font-weight: 700; margin-bottom: 2px; }
        .meta-value { font-size: 10.5pt; font-weight: 600; color: #111827; }

        .statement { font-size: 12pt; line-height: 1.9; margin-bottom: 28px; }
        .statement strong { color: #0f766e; }

        .leave-box { display: flex; gap: 0; border: 1px solid #99f6e4; border-radius: 10px; overflow: hidden; margin-bottom: 28px; }
        .leave-cell { flex: 1; padding: 16px 20px; text-align: center; border-inline-end: 1px solid #ccfbf1; }
        .leave-cell:last-child { border-inline-end: none; }
        .leave-cell .k { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; color: #0d9488; font-weight: 700; }
        .leave-cell .v { font-size: 16pt; font-weight: 800; color: #111827; margin-top: 4px; }
        .leave-cell.accent { background: #0d9488; }
        .leave-cell.accent .k { color: #ccfbf1; }
        .leave-cell.accent .v { color: #fff; }

        .diagnosis-section { background: #f8fafc; border-left: 4px solid #0d9488; padding: 10px 16px; border-radius: 4px; margin-bottom: 24px; }
        .diagnosis-label { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; color: #0d9488; font-weight: 700; }
        .diagnosis-text { font-size: 11pt; color: #1f2937; margin-top: 2px; }

        .footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 60px; }
        .signature-block { text-align: center; min-width: 220px; }
        .sign-line { border-top: 1.5px solid #374151; margin-bottom: 6px; }
        .disclaimer { font-size: 8pt; color: #9ca3af; text-align: center; position: absolute; bottom: 5mm; left: 0; right: 0; }
    </style>
</head>
<body>
@php
    $days = (int) ($visit->sick_leave_days ?? 0);
    $start = $visit->created_at ? $visit->created_at->copy() : now();
    $end = $start->copy()->addDays(max($days, 1) - 1);
    $resume = $end->copy()->addDay();
@endphp
    <div class="no-print" style="max-width:760px;margin:12px auto;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:8px;font-size:12px;line-height:1.5;font-family:'Inter',sans-serif;">
        💡 To hide the page number &amp; web address that your browser adds: in the Print dialog set <strong>Margins → None</strong> or untick <strong>Headers and footers</strong>.<br>
        <span dir="rtl">لإخفاء رقم الصفحة والرابط الذي يضيفه المتصفح: في نافذة الطباعة اضبط الهوامش إلى «بلا» أو ألغِ «رؤوس وتذييلات الصفحة».</span>
    </div>
    <div class="page">
        <!-- HEADER (same letterhead as the prescription) -->
        <div class="header">
            <div class="brand-area">
                @if($visit->doctor->partner && $visit->doctor->partner->logo_path)
                    <img src="{{ asset('storage/' . $visit->doctor->partner->logo_path) }}" alt="Logo" class="logo">
                @endif
                <div class="clinic-info">
                    <h1>{{ $visit->doctor->partner->name['en'] ?? $visit->doctor->partner->name ?? 'Medical Clinic' }}</h1>
                    <p>
                        @if($visit->branch)
                            <strong>{{ $visit->branch->name['en'] ?? $visit->branch->name }}</strong><br>
                            {{ $visit->branch->address ?? '' }}<br>
                            {{ $visit->branch->phone ? 'Tel: ' . $visit->branch->phone : '' }}
                            {{ $visit->branch->email ? '| ' . $visit->branch->email : '' }}
                        @endif
                        <br>
                        @if($visit->doctor->partner->license_number)
                            <span style="color: #9ca3af;">Clinic Lic: {{ $visit->doctor->partner->license_number }}</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="doc-info">
                <h2 class="doc-name">{{ str_starts_with($visit->doctor->name, 'Dr.') ? $visit->doctor->name : 'Dr. ' . $visit->doctor->name }}</h2>
                <p class="doc-meta">{{ $visit->doctor->specialty ?? 'General Practitioner' }}</p>
                @if($visit->doctor->license_number)
                    <p class="doc-meta">Lic No: {{ $visit->doctor->license_number }}</p>
                @endif
            </div>
        </div>

        <!-- TITLE -->
        <div class="title-block">
            <h2>Medical Leave Certificate</h2>
            <div class="sub">شهادة إجازة مرضية</div>
        </div>

        <!-- PATIENT META -->
        <div class="meta-bar">
            <div class="meta-group">
                <span class="meta-label">Patient Name</span>
                <span class="meta-value">{{ $visit->patient->name }}</span>
            </div>
            <div class="meta-group">
                <span class="meta-label">Age / Gender</span>
                <span class="meta-value">
                    {{ $visit->patient->dob ? $visit->patient->dob->age . ' yrs' : '-' }} /
                    {{ $visit->patient->gender ? ucfirst($visit->patient->gender) : '-' }}
                </span>
            </div>
            @if($visit->patient->civil_id ?? false)
            <div class="meta-group">
                <span class="meta-label">Civil ID</span>
                <span class="meta-value">{{ $visit->patient->civil_id }}</span>
            </div>
            @endif
            <div class="meta-group">
                <span class="meta-label">Visit ID</span>
                <span class="meta-value">#{{ $visit->id }}</span>
            </div>
            <div class="meta-group">
                <span class="meta-label">Issued</span>
                <span class="meta-value">{{ $start->format('d M Y') }}</span>
            </div>
        </div>

        <!-- STATEMENT -->
        <div class="statement">
            This is to certify that <strong>{{ $visit->patient->name }}</strong> was examined at this clinic on
            <strong>{{ $start->format('d M Y') }}</strong> and is medically advised rest / leave from duty for
            <strong>{{ $days }} day{{ $days == 1 ? '' : 's' }}</strong>.
        </div>

        <!-- LEAVE PERIOD -->
        <div class="leave-box">
            <div class="leave-cell">
                <div class="k">From</div>
                <div class="v">{{ $start->format('d M Y') }}</div>
            </div>
            <div class="leave-cell">
                <div class="k">To (inclusive)</div>
                <div class="v">{{ $end->format('d M Y') }}</div>
            </div>
            <div class="leave-cell accent">
                <div class="k">Total Days</div>
                <div class="v">{{ $days }}</div>
            </div>
            <div class="leave-cell">
                <div class="k">Fit to resume</div>
                <div class="v">{{ $resume->format('d M Y') }}</div>
            </div>
        </div>

        @if($visit->diagnosis)
        <div class="diagnosis-section">
            <div class="diagnosis-label">Diagnosis</div>
            <div class="diagnosis-text">{{ $visit->diagnosis }}</div>
        </div>
        @endif

        <!-- FOOTER -->
        <div class="footer">
            <div style="font-size: 9pt; color: #6b7280; max-width: 55%;">
                {{ $visit->doctor->partner->footer_text ?? 'This certificate is issued for medical purposes only and reflects the clinical assessment on the date of examination.' }}
            </div>
            <div class="signature-block">
                <div class="sign-line"></div>
                <div style="font-weight: bold; color: #1f2937;">{{ str_starts_with($visit->doctor->name, 'Dr.') ? $visit->doctor->name : 'Dr. ' . $visit->doctor->name }}</div>
                <div style="font-size: 9pt; color: #6b7280;">Physician Signature &amp; Stamp</div>
            </div>
        </div>

        <div class="disclaimer">
            Generated {{ now()->format('d M Y, H:i') }} · Visit #{{ $visit->id }}{{ $visit->booking_code ? ' · ' . $visit->booking_code : '' }}
        </div>
    </div>

    <script>
        window.onload = function () { window.print(); };
    </script>
</body>
</html>
