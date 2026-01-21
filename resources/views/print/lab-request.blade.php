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

        body {
            font-family: 'Inter', sans-serif;
            color: #334155;
            line-height: 1.5;
            background: #fff;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            margin: 0 auto;
            position: relative;
            box-sizing: border-box;
        }

        /* Header Style (Distinct from Rx) */
        .header {
            border-bottom: 4px solid #1e3a8a; /* Dark Blue for Labs */
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section img {
            height: 80px;
            width: auto;
        }

        .title-section {
            text-align: right;
        }

        .doc-title {
            font-size: 20pt;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .doc-subtitle {
            font-size: 10pt;
            color: #64748b;
            margin-top: 5px;
            font-weight: 600;
        }

        /* Patient Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1px solid #cbd5e1;
            margin-bottom: 30px;
            border-radius: 4px;
            overflow: hidden;
        }

        .grid-item {
            display: flex;
            border-bottom: 1px solid #cbd5e1;
        }
        
        .grid-item:nth-last-child(-n+2) {
            border-bottom: none;
        }

        .grid-item:nth-child(odd) {
            border-right: 1px solid #cbd5e1;
        }

        .label {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 10px 15px;
            width: 120px;
            border-right: 1px solid #e2e8f0;
        }

        .value {
            padding: 10px 15px;
            font-size: 11pt;
            font-weight: 600;
            color: #0f172a;
            flex: 1;
        }

        /* Clinical Notes */
        .clinical-context {
            background-color: #eff6ff; /* Light blue */
            border: 1px solid #bfdbfe;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 30px;
            color: #1e40af;
        }

        /* Request Table */
        .request-container {
            border: 2px solid #1e3a8a;
            border-radius: 6px;
            overflow: hidden;
            min-height: 400px;
        }

        .request-header {
            background-color: #1e3a8a;
            color: white;
            padding: 12px 15px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11pt;
            letter-spacing: 0.5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            color: #64748b;
            background-color: #f8fafc;
            padding: 12px 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 4px;
            background-color: #e0e7ff;
            color: #3730a3;
        }

        /* Footer */
        .footer {
            position: absolute;
            bottom: 15mm;
            left: 20mm;
            right: 20mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 20px;
            border-top: 1px solid #cbd5e1;
        }

        .physician-box {
            font-size: 11pt;
        }

        .doc-name {
            font-weight: 700;
            color: #0f172a;
            margin-top: 10px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="logo-section">
                @if($visit->doctor->partner && $visit->doctor->partner->logo_path)
                    <img src="{{ asset('storage/' . $visit->doctor->partner->logo_path) }}" alt="Logo">
                @else
                    <h2 style="color:#1e3a8a; margin:0;">{{ $visit->doctor->partner->name['en'] ?? 'CLINIC' }}</h2>
                @endif
            </div>
            <div class="title-section">
                <h1 class="doc-title">Investigation Request</h1>
                <div class="doc-subtitle">
                    {{ $visit->doctor->partner->name['en'] ?? 'Medical Clinic' }}
                    @if($visit->branch) | {{ $visit->branch->name['en'] ?? $visit->branch->name }} @endif
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="grid-item">
                <div class="label">Patient</div>
                <div class="value">{{ $visit->patient->name }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Req Date</div>
                <div class="value">{{ $visit->created_at->format('d/m/Y') }}</div>
            </div>
            
            <div class="grid-item">
                <div class="label">ID / MRN</div>
                <div class="value">#{{ $visit->patient->id }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Age/Sex</div>
                <div class="value">
                    {{ $visit->patient->dob ? $visit->patient->dob->age : '-' }} / 
                    {{ $visit->patient->gender ? ucfirst($visit->patient->gender) : '-' }}
                </div>
            </div>

            <div class="grid-item">
                <div class="label">Mobile</div>
                <div class="value">{{ $visit->patient->phone ?? '-' }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Visit ID</div>
                <div class="value">#{{ $visit->id }}</div>
            </div>
        </div>

        @if($visit->chief_complaint || $visit->diagnosis)
        <div class="clinical-context">
            <strong>Clinical Context / Diagnosis:</strong><br>
            {{ $visit->diagnosis ?? $visit->chief_complaint }}
        </div>
        @endif

        <div class="request-container">
            <div class="request-header">Requested Tests</div>
            <table>
                <thead>
                    <tr>
                        <th width="15%">Type</th>
                        <th width="45%">Investigation</th>
                        <th width="40%">Clinical Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @if($visit->lab_requests)
                        @foreach($visit->lab_requests as $req)
                            <tr>
                                <td><span class="type-badge">{{ $req['type'] }}</span></td>
                                <td><strong>{{ $req['name'] }}</strong></td>
                                <td>{{ $req['note'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 50px; color: #94a3b8; font-style: italic;">
                                No investigations requested.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="footer">
            <div class="physician-box">
                <strong>Requesting Physician:</strong><br><br>
                <div style="border-bottom: 2px solid #1e3a8a; width: 220px; margin-bottom: 5px;"></div>
                <span class="doc-name">{{ str_starts_with($visit->doctor->name, 'Dr.') ? $visit->doctor->name : 'Dr. ' . $visit->doctor->name }}</span>
                <small style="color:#64748b;">{{ $visit->doctor->specialty ?? '' }}</small>
            </div>
            
            <div style="text-align: right; font-size: 8pt; color: #94a3b8;">
                {{ $visit->branch->address ?? '' }}<br>
                {{ $visit->branch->license_number ? 'Lic: ' . $visit->branch->license_number : '' }}<br>
                Printed: {{ now()->format('d M Y H:i') }}
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>