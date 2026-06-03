<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription - {{ $visit->patient->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        @media print {
            @page { margin: 0; size: A4; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background: #fff;
            margin: 0;
            padding: 0;
            font-size: 12pt;
            line-height: 1.5;
        }

        .page {
            width: 210mm;
            min-height: 290mm;
            padding: 15mm 20mm;
            margin: 0 auto;
            background: white;
            position: relative;
            box-sizing: border-box;
        }

        /* --- Header Section --- */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #0d9488;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            height: 90px;
            width: auto;
            object-fit: contain;
        }

        .clinic-info h1 {
            margin: 0;
            font-size: 22pt;
            font-weight: 800;
            text-transform: uppercase;
            color: #111827;
            letter-spacing: -0.5px;
        }

        .clinic-info p {
            margin: 4px 0 0;
            font-size: 9pt;
            color: #4b5563;
            line-height: 1.4;
        }

        .doc-info {
            text-align: right;
            min-width: 200px;
        }

        .doc-name {
            font-size: 14pt;
            font-weight: 700;
            color: #0d9488;
            margin: 0;
        }

        .doc-meta {
            font-size: 9pt;
            color: #6b7280;
            margin: 2px 0 0;
        }

        /* --- Patient Metadata --- */
        .meta-bar {
            display: flex;
            background-color: #f0fdfa; /* Teal-50 */
            border: 1px solid #ccfbf1;
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 30px;
            justify-content: space-between;
        }

        .meta-group {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0d9488;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .meta-value {
            font-size: 10.5pt;
            font-weight: 600;
            color: #111827;
        }

        /* --- Diagnosis --- */
        .diagnosis-section {
            margin-bottom: 30px;
            padding-left: 15px;
            border-left: 4px solid #f59e0b; /* Amber border */
        }
        
        .diagnosis-label {
            font-size: 9pt;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
        }

        .diagnosis-text {
            font-size: 11pt;
            color: #1f2937;
        }

        /* --- Rx Section --- */
        .rx-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .rx-table th {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #e5e7eb;
            color: #6b7280;
            font-size: 9pt;
            text-transform: uppercase;
            font-weight: 700;
        }

        .rx-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .medicine-name {
            font-weight: 700;
            font-size: 11pt;
            color: #111827;
            display: block;
        }

        .medicine-instr {
            color: #0d9488;
            font-style: italic;
            font-size: 10pt;
            margin-top: 4px;
            display: block;
        }

        /* Free-text prescription list (v2 format) */
        .rx-symbol {
            font-family: 'Georgia', 'Times New Roman', serif;
            font-size: 30pt;
            font-weight: 700;
            color: #0d9488;
            line-height: 1;
            margin-bottom: 6px;
        }
        .rx-list { margin-bottom: 20px; }
        .rx-item {
            display: flex;
            gap: 14px;
            align-items: baseline;
            padding: 13px 4px;
            border-bottom: 1px solid #f3f4f6;
        }
        .rx-num {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #f0fdfa;
            color: #0d9488;
            font-size: 10pt;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .rx-body { flex: 1; }
        .rx-drug { font-weight: 700; font-size: 12pt; color: #111827; display: block; }
        .rx-sig { color: #374151; font-size: 11pt; margin-top: 3px; display: block; }

        .instructions-box {
            background: #fff;
            border: 1px dashed #cbd5e1;
            padding: 15px;
            border-radius: 6px;
            margin-top: 30px;
        }

        /* --- Footer --- */
        .footer {
            position: absolute;
            bottom: 15mm;
            left: 20mm;
            right: 20mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f3f4f6;
        }

        .signature-block {
            text-align: center;
            width: 250px;
        }

        .sign-line {
            border-bottom: 1px solid #1f2937;
            margin-bottom: 8px;
            height: 40px; /* Space for image/wet signature */
        }

        .disclaimer {
            font-size: 8pt;
            color: #9ca3af;
            text-align: center;
            position: absolute;
            bottom: 5mm;
            left: 0;
            right: 0;
        }
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

        <!-- CLINICAL CONTEXT -->
        @if($visit->diagnosis)
        <div class="diagnosis-section">
            <div class="diagnosis-label">Diagnosis</div>
            <div class="diagnosis-text">{{ $visit->diagnosis }}</div>
        </div>
        @endif

        @if(!empty($visit->patient->allergies))
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; font-size: 10pt;">
            ⚠️ Allergies: {{ $visit->patient->allergies }}
        </div>
        @endif

        <!-- PRESCRIPTION -->
        @php
            // v2 stores `prescriptions` as free text (one drug per line); the legacy
            // admin stored a structured array of rows. Detect which we have.
            $rxRaw = $visit->prescriptions;
            $rxStructured = is_array($rxRaw) && isset($rxRaw[0]) && is_array($rxRaw[0]);
            $rxLines = [];
            if (! $rxStructured) {
                $rxText = is_array($rxRaw) ? implode("\n", array_map(fn ($r) => is_string($r) ? $r : '', $rxRaw)) : (string) $rxRaw;
                $rxLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rxText)), fn ($l) => $l !== ''));
            }
        @endphp

        @if($rxStructured)
            <table class="rx-table">
                <thead>
                    <tr>
                        <th width="40%">Medication</th>
                        <th width="15%">Dosage</th>
                        <th width="15%">Frequency</th>
                        <th width="15%">Duration</th>
                        <th width="15%">Route/Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rxRaw as $rx)
                        <tr>
                            <td><span class="medicine-name">{{ $rx['medicine'] ?? ($rx['name'] ?? '-') }}</span></td>
                            <td>{{ $rx['dosage'] ?? '-' }}</td>
                            <td>{{ $rx['frequency'] ?? '-' }}</td>
                            <td>{{ $rx['duration'] ?? '-' }}</td>
                            <td><span class="medicine-instr">{{ $rx['instruction'] ?? '' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif(count($rxLines))
            <div class="rx-symbol">℞</div>
            <div class="rx-list">
                @foreach($rxLines as $i => $line)
                    @php
                        // Split "Name 500mg form — sig × dur (note)" into a bold drug
                        // head and a lighter signa, when the em-dash separator exists.
                        $parts = preg_split('/\s+—\s+/u', $line, 2);
                        $rxHead = $parts[0];
                        $rxSig = $parts[1] ?? null;
                    @endphp
                    <div class="rx-item">
                        <span class="rx-num">{{ $i + 1 }}</span>
                        <div class="rx-body">
                            <span class="rx-drug">{{ $rxHead }}</span>
                            @if($rxSig)<span class="rx-sig">{{ $rxSig }}</span>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align: center; padding: 40px; color: #9ca3af; font-style: italic; border-bottom: 1px solid #f3f4f6;">
                No medications prescribed during this visit.
            </div>
        @endif

        <!-- PATIENT ADVICE -->
        @if($visit->patient_instructions)
        <div class="instructions-box">
            <div style="font-weight: bold; font-size: 9pt; color: #475569; margin-bottom: 5px; text-transform: uppercase;">Medical Advice / Instructions</div>
            <div style="white-space: pre-wrap;">{{ $visit->patient_instructions }}</div>
        </div>
        @endif

        @if($visit->sick_leave_days)
        <div style="margin-top: 15px; font-weight: bold; color: #1f2937;">
            * Sick leave granted for {{ $visit->sick_leave_days }} day(s) starting {{ $visit->created_at->format('d M Y') }}.
        </div>
        @endif

        <!-- FOOTER -->
        <div class="footer">
            <div style="font-size: 9pt; color: #6b7280; max-width: 60%;">
                {{ $visit->doctor->partner->footer_text ?? 'This prescription is valid for 3 days from the date of issue unless otherwise specified.' }}
            </div>
            
            <div class="signature-block">
                <div class="sign-line"></div> <!-- Placeholder for wet signature -->
                <div style="font-weight: bold; color: #1f2937;">{{ str_starts_with($visit->doctor->name, 'Dr.') ? $visit->doctor->name : 'Dr. ' . $visit->doctor->name }}</div>
                <div style="font-size: 9pt; color: #6b7280;">Physician Signature & Stamp</div>
            </div>
        </div>

        <div class="disclaimer">
            Generated by System on {{ now()->format('d M Y H:i') }} | Ref: {{ $visit->booking ? $visit->booking->booking_code : 'V-'.$visit->id }}
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>