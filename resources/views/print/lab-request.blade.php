<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Request - {{ $visit->patient->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

        @media print {
            @page { margin: 0; size: A4; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; margin: 0; padding: 0; }
        }

        body { font-family: 'Inter', sans-serif; color: #1f2937; background: #fff; margin: 0; padding: 0; font-size: 12pt; line-height: 1.5; }
        .page { width: 210mm; min-height: 290mm; padding: 15mm 20mm; margin: 0 auto; background: white; position: relative; box-sizing: border-box; }

        /* Letterhead — same as prescription / medical-leave */
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

        .meta-bar { display: flex; background-color: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 8px; padding: 12px 20px; margin-bottom: 28px; justify-content: space-between; }
        .meta-group { display: flex; flex-direction: column; }
        .meta-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; color: #0d9488; font-weight: 700; margin-bottom: 2px; }
        .meta-value { font-size: 10.5pt; font-weight: 600; color: #111827; }

        .diagnosis-section { background: #f8fafc; border-left: 4px solid #0d9488; padding: 10px 16px; border-radius: 4px; margin-bottom: 24px; }
        .diagnosis-label { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; color: #0d9488; font-weight: 700; }
        .diagnosis-text { font-size: 11pt; color: #1f2937; margin-top: 2px; }

        .section-eyebrow { font-size: 9pt; text-transform: uppercase; letter-spacing: 0.6px; color: #0d9488; font-weight: 700; margin-bottom: 8px; }

        /* Clean numbered test list (v2 free-text format) */
        .lab-list { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .lab-item { display: flex; gap: 14px; align-items: baseline; padding: 14px 16px; border-bottom: 1px solid #f3f4f6; }
        .lab-item:last-child { border-bottom: none; }
        .lab-num { flex-shrink: 0; width: 24px; height: 24px; border-radius: 50%; background: #f0fdfa; color: #0d9488; font-size: 10pt; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
        .lab-body { flex: 1; }
        .lab-name { font-weight: 700; font-size: 12pt; color: #111827; }
        .lab-note { color: #6b7280; font-size: 10pt; margin-top: 2px; }
        .lab-type { display: inline-block; margin-inline-start: 8px; padding: 1px 8px; font-size: 8pt; font-weight: 700; text-transform: uppercase; border-radius: 999px; background: #f0fdfa; color: #0d9488; border: 1px solid #ccfbf1; }
        .lab-empty { padding: 40px; text-align: center; color: #9ca3af; font-style: italic; }

        .footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 50px; }
        .signature-block { text-align: center; min-width: 220px; }
        .sign-line { border-top: 1.5px solid #374151; margin-bottom: 6px; }
        .disclaimer { font-size: 8pt; color: #9ca3af; text-align: center; position: absolute; bottom: 5mm; left: 0; right: 0; }
    </style>
</head>
<body>
    <div class="page">
        <!-- HEADER -->
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
            <h2>Investigation Request</h2>
            <div class="sub">طلب فحوصات مخبرية</div>
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
            <div class="meta-group">
                <span class="meta-label">Visit ID</span>
                <span class="meta-value">#{{ $visit->id }}</span>
            </div>
            <div class="meta-group">
                <span class="meta-label">Date</span>
                <span class="meta-value">{{ $visit->created_at->format('d M Y') }}</span>
            </div>
        </div>

        @if($visit->diagnosis || $visit->chief_complaint)
        <div class="diagnosis-section">
            <div class="diagnosis-label">Clinical Context / Diagnosis</div>
            <div class="diagnosis-text">{{ $visit->diagnosis ?? $visit->chief_complaint }}</div>
        </div>
        @endif

        <!-- REQUESTED TESTS -->
        <div class="section-eyebrow">Requested Investigations</div>
        @php
            // v2 stores `lab_requests` as free text (one test per line); the legacy
            // admin stored a structured array. Support both.
            $labRaw = $visit->lab_requests;
            $labStructured = is_array($labRaw) && isset($labRaw[0]) && is_array($labRaw[0]);
            $labLines = [];
            if (! $labStructured) {
                $labText = is_array($labRaw) ? implode("\n", array_map(fn ($r) => is_string($r) ? $r : '', $labRaw)) : (string) $labRaw;
                $labLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $labText)), fn ($l) => $l !== ''));
            }
        @endphp

        <div class="lab-list">
            @if($labStructured)
                @foreach($labRaw as $i => $req)
                    <div class="lab-item">
                        <span class="lab-num">{{ $i + 1 }}</span>
                        <div class="lab-body">
                            <span class="lab-name">{{ $req['name'] ?? '-' }}</span>
                            @if(!empty($req['type']))<span class="lab-type">{{ $req['type'] }}</span>@endif
                            @if(!empty($req['note']))<div class="lab-note">{{ $req['note'] }}</div>@endif
                        </div>
                    </div>
                @endforeach
            @elseif(count($labLines))
                @foreach($labLines as $i => $line)
                    <div class="lab-item">
                        <span class="lab-num">{{ $i + 1 }}</span>
                        <div class="lab-body">
                            <span class="lab-name">{{ $line }}</span>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="lab-empty">No investigations requested.</div>
            @endif
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <div style="font-size: 9pt; color: #6b7280; max-width: 55%;">
                {{ $visit->doctor->partner->footer_text ?? 'Please present this request at the laboratory. Results will be linked to the patient record.' }}
            </div>
            <div class="signature-block">
                <div class="sign-line"></div>
                <div style="font-weight: bold; color: #1f2937;">{{ str_starts_with($visit->doctor->name, 'Dr.') ? $visit->doctor->name : 'Dr. ' . $visit->doctor->name }}</div>
                <div style="font-size: 9pt; color: #6b7280;">Requesting Physician</div>
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
