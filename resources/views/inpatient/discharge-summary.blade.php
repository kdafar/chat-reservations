<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Discharge summary — {{ $admission->admission_code }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Inter', system-ui, sans-serif; color: #111; margin: 0; padding: 24px; font-size: 12px; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    h2 { font-size: 14px; margin: 16px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #ccc; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
    .brand-name { font-size: 14px; font-weight: 600; }
    .meta { font-size: 11px; color: #666; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .field { margin-bottom: 6px; }
    .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.05em; }
    .value { font-size: 12px; font-weight: 500; }
    table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 4px; }
    th, td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; }
    th { background: #f5f5f5; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #555; }
    .total-row td { border-top: 2px solid #111; border-bottom: none; font-weight: 600; }
    .pre { white-space: pre-wrap; }
    .footer { margin-top: 24px; font-size: 10px; color: #666; border-top: 1px solid #eee; padding-top: 8px; }
    .sig { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 40px; }
    .sig-block { font-size: 11px; }
    .sig-line { border-top: 1px solid #111; padding-top: 4px; margin-top: 30px; }
    @media print {
        body { padding: 14mm; }
        .no-print { display: none; }
    }
</style>
</head>
<body onload="window.print()">

<div class="no-print" style="margin-bottom: 12px;">
    <button onclick="window.print()" style="padding: 6px 12px;">Print / Save as PDF</button>
</div>

<div class="header">
    <div>
        <div class="brand-name">{{ config('app.name', 'Clinic') }}</div>
        <div class="meta">{{ $admission->branch?->getTranslation('name', app()->getLocale(), true) }}</div>
    </div>
    <div style="text-align: right;">
        <h1>Discharge Summary</h1>
        <div class="meta">{{ $admission->admission_code }} · printed {{ now()->format('M j, Y H:i') }}</div>
    </div>
</div>

<h2>Patient</h2>
<div class="grid">
    <div>
        <div class="field"><div class="label">Name</div><div class="value">{{ $admission->patient?->name }}</div></div>
        <div class="field"><div class="label">Phone</div><div class="value">{{ $admission->patient?->phone }}</div></div>
        <div class="field"><div class="label">Gender</div><div class="value">{{ ucfirst($admission->patient?->gender ?? '—') }}</div></div>
    </div>
    <div>
        <div class="field"><div class="label">DOB</div><div class="value">{{ $admission->patient?->dob ?? '—' }}</div></div>
        <div class="field"><div class="label">Civil ID</div><div class="value">{{ $admission->patient?->civil_id ?? '—' }}</div></div>
        @if ($admission->patient?->medical_alerts)
            <div class="field"><div class="label">Medical alerts</div><div class="value">{{ $admission->patient->medical_alerts }}</div></div>
        @endif
    </div>
</div>

<h2>Admission</h2>
<div class="grid">
    <div>
        <div class="field"><div class="label">Admitting doctor</div><div class="value">{{ $admission->admittingDoctor?->name }} ({{ $admission->admittingDoctor?->specialty }})</div></div>
        <div class="field"><div class="label">Admitted</div><div class="value">{{ optional($admission->admitted_at)->format('M j, Y H:i') }}</div></div>
        <div class="field"><div class="label">Discharged</div><div class="value">{{ optional($admission->discharged_at)->format('M j, Y H:i') ?: '— still admitted —' }}</div></div>
    </div>
    <div>
        <div class="field"><div class="label">Status</div><div class="value">{{ str_replace('_', ' ', $admission->status) }}</div></div>
        <div class="field"><div class="label">Length of stay</div><div class="value">
            @if ($admission->discharged_at)
                {{ round($admission->admitted_at->diffInDays($admission->discharged_at, true), 1) }} days
            @else — @endif
        </div></div>
        <div class="field"><div class="label">Discharged by</div><div class="value">{{ $admission->dischargedBy?->name ?? '—' }}</div></div>
    </div>
</div>

<h2>Reason & diagnosis</h2>
<div class="field"><div class="label">Admission reason</div><div class="value pre">{{ $admission->admission_reason }}</div></div>
@if ($admission->diagnosis)
    <div class="field" style="margin-top: 8px;"><div class="label">Diagnosis</div><div class="value pre">{{ $admission->diagnosis }}</div></div>
@endif

<h2>Bed history</h2>
@if ($admission->bedStays->isEmpty())
    <div style="color: #999;">No bed assignments recorded.</div>
@else
    <table>
        <thead><tr><th>Ward</th><th>Bed</th><th>Assigned</th><th>Released</th><th>Rate / night</th><th>Reason</th></tr></thead>
        <tbody>
            @foreach ($admission->bedStays as $s)
                <tr>
                    <td>{{ $s->bed?->ward?->name }}</td>
                    <td>{{ $s->bed?->code }}</td>
                    <td>{{ optional($s->assigned_at)->format('M j H:i') }}</td>
                    <td>{{ optional($s->released_at)->format('M j H:i') ?: 'current' }}</td>
                    <td>{{ number_format($s->daily_rate, 3) }}</td>
                    <td>{{ $s->reason_for_change ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($admission->rounds->isNotEmpty())
    <h2>Daily rounds ({{ $admission->rounds->count() }})</h2>
    @foreach ($admission->rounds as $r)
        <div style="margin-bottom: 10px; padding: 8px; border: 1px solid #eee; border-radius: 4px;">
            <div style="font-weight: 600;">{{ $r->doctor?->name }} · {{ optional($r->round_date)->format('M j, Y') }}</div>
            @if ($r->vitals)
                <div style="font-size: 11px; margin-top: 4px;">
                    @foreach ($r->vitals as $k => $v)<span style="margin-right: 12px;"><strong>{{ $k }}:</strong> {{ $v }}</span>@endforeach
                </div>
            @endif
            @if ($r->progress_notes)<div class="pre" style="margin-top: 4px;">{{ $r->progress_notes }}</div>@endif
            @if ($r->med_changes)<div style="font-size: 11px; margin-top: 4px;"><strong>Meds:</strong> {{ $r->med_changes }}</div>@endif
            @if ($r->next_steps)<div style="font-size: 11px; margin-top: 4px;"><strong>Next:</strong> {{ $r->next_steps }}</div>@endif
        </div>
    @endforeach
@endif

@if ($admission->discharge_summary)
    <h2>Discharge summary</h2>
    <div class="pre">{{ $admission->discharge_summary }}</div>
@endif

<h2>Charges</h2>
@php
    $total = $admission->charges->sum('amount');
@endphp
@if ($admission->charges->isEmpty())
    <div style="color: #999;">No charges recorded.</div>
@else
    <table>
        <thead><tr><th>Date</th><th>Description</th><th>Source</th><th style="text-align: right;">Amount (KWD)</th></tr></thead>
        <tbody>
            @foreach ($admission->charges as $c)
                <tr>
                    <td>{{ optional($c->charge_date)->format('M j, Y') }}</td>
                    <td>{{ $c->description }}</td>
                    <td>{{ str_replace('_', ' ', $c->source) }}</td>
                    <td style="text-align: right;">{{ number_format($c->amount, 3) }}</td>
                </tr>
            @endforeach
            <tr class="total-row"><td colspan="3">Total</td><td style="text-align: right;">{{ number_format($total, 3) }}</td></tr>
        </tbody>
    </table>
    @if ($admission->finalVisit)
        <div style="margin-top: 8px; font-size: 11px; color: #666;">
            Final invoice Visit #{{ $admission->finalVisit->id }} — status: {{ str_replace('_', ' ', $admission->finalVisit->status) }}
        </div>
    @endif
@endif

<div class="sig">
    <div class="sig-block">
        <div class="sig-line">Patient / Guardian signature</div>
    </div>
    <div class="sig-block">
        <div class="sig-line">Discharging doctor signature</div>
        <div style="font-size: 10px; color: #666; margin-top: 2px;">{{ $admission->admittingDoctor?->name }}</div>
    </div>
</div>

<div class="footer">
    Generated by {{ config('app.name', 'Clinic') }} · {{ now()->format('M j, Y H:i') }} ·
    This document is for medical record purposes.
</div>

</body>
</html>
