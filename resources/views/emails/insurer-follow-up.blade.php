@php
    // Email HTML rules: tables for layout, inline styles, no shorthand that
    // Outlook drops, hex colours only (oklch from the app theme is unsupported).
    $gold   = '#b0904f';
    $ink    = '#1f1d1a';
    $muted  = '#6b6659';
    $line   = '#e6e1d6';
    $paper  = '#ffffff';
    $canvas = '#f4f2ec';
    $danger = '#b3261e';

    $money = fn ($v) => number_format((float) $v, 3);
    $initials = collect(preg_split('/\s+/', trim($clinic['name'])))
        ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $clinic['name'] }} — Statement of outstanding claims</title>
</head>
<body style="margin:0; padding:0; background:{{ $canvas }}; -webkit-font-smoothing:antialiased;">

{{-- Inbox preview line --}}
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">
    {{ $totals['count'] }} claim(s) totalling KWD {{ $money($totals['outstanding']) }} remain unpaid — Ref {{ $reference }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $canvas }}; padding:24px 12px;">
<tr><td align="center">

<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px; max-width:100%; background:{{ $paper }}; border:1px solid {{ $line }}; border-radius:10px; overflow:hidden; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

    {{-- Letterhead --}}
    <tr>
        <td style="padding:22px 28px; border-bottom:3px solid {{ $gold }};">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="vertical-align:middle;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="width:42px; height:42px; background:{{ $gold }}; border-radius:8px; text-align:center; vertical-align:middle; color:#ffffff; font-size:15px; font-weight:700; letter-spacing:0.5px;">{{ $initials }}</td>
                                <td style="padding-left:12px; vertical-align:middle;">
                                    <div style="font-size:16px; font-weight:700; color:{{ $ink }}; line-height:1.25;">{{ $clinic['name'] }}</div>
                                    @if($clinic['license'])
                                        <div style="font-size:11px; color:{{ $muted }}; margin-top:2px;">MOH Licence {{ $clinic['license'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td align="right" style="vertical-align:middle; font-size:11px; color:{{ $muted }}; line-height:1.6;">
                        @if($clinic['phone'])<div>{{ $clinic['phone'] }}</div>@endif
                        @if($clinic['email'])<div>{{ $clinic['email'] }}</div>@endif
                        @if($clinic['website'])<div>{{ $clinic['website'] }}</div>@endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Document header: what this is, our reference, the date --}}
    <tr>
        <td style="padding:22px 28px 0;">
            <div style="font-size:11px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:{{ $gold }};">Accounts receivable</div>
            <div style="font-size:20px; font-weight:700; color:{{ $ink }}; margin-top:4px;">Statement of outstanding claims</div>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px; font-size:12px; color:{{ $ink }};">
                <tr>
                    <td style="padding:4px 0; width:110px; color:{{ $muted }};">Reference</td>
                    <td style="padding:4px 0; font-weight:600;">{{ $reference }}</td>
                    <td style="padding:4px 0; width:80px; color:{{ $muted }};">Date</td>
                    <td style="padding:4px 0; font-weight:600;">{{ now()->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0; color:{{ $muted }};">To</td>
                    <td style="padding:4px 0; font-weight:600;">{{ $insurer['name'] }}@if($insurer['code']) <span style="color:{{ $muted }}; font-weight:400;">({{ $insurer['code'] }})</span>@endif</td>
                    <td style="padding:4px 0; color:{{ $muted }};">Terms</td>
                    <td style="padding:4px 0; font-weight:600;">Net {{ $totals['terms_days'] }} days</td>
                </tr>
                <tr>
                    <td style="padding:4px 0; color:{{ $muted }};">Attention</td>
                    <td style="padding:4px 0;" colspan="3">Claims &amp; Reimbursement Department</td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td style="padding:20px 28px 0; font-size:13.5px; line-height:1.65; color:{{ $ink }};">
            <p style="margin:0 0 12px;">Dear Sir / Madam,</p>
            @if($note)
                <p style="margin:0 0 12px;">{{ $note }}</p>
            @else
                <p style="margin:0 0 12px;">
                    We refer to the claims submitted to {{ $insurer['name'] }} on behalf of our insured patients.
                    As of the date of this statement, the items listed below remain unsettled.
                </p>
            @endif
        </td>
    </tr>

    {{-- Summary strip --}}
    <tr>
        <td style="padding:8px 28px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid {{ $line }}; border-radius:8px; background:#faf8f3;">
                <tr>
                    <td style="padding:12px 14px; border-right:1px solid {{ $line }};">
                        <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:0.6px; color:{{ $muted }};">Total outstanding</div>
                        <div style="font-size:19px; font-weight:700; color:{{ $ink }}; margin-top:3px;">KWD {{ $money($totals['outstanding']) }}</div>
                    </td>
                    <td style="padding:12px 14px; border-right:1px solid {{ $line }};">
                        <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:0.6px; color:{{ $muted }};">Claims</div>
                        <div style="font-size:19px; font-weight:700; color:{{ $ink }}; margin-top:3px;">{{ $totals['count'] }}</div>
                    </td>
                    <td style="padding:12px 14px;">
                        <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:0.6px; color:{{ $muted }};">Oldest item</div>
                        <div style="font-size:19px; font-weight:700; color:{{ $totals['oldest_days'] > $totals['terms_days'] ? $danger : $ink }}; margin-top:3px;">{{ $totals['oldest_days'] }} days</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Claim table --}}
    <tr>
        <td style="padding:18px 28px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; font-size:12px;">
                <thead>
                    <tr>
                        <th align="left"  style="padding:8px 8px 8px 0; border-bottom:2px solid {{ $ink }}; font-size:10.5px; text-transform:uppercase; letter-spacing:0.5px; color:{{ $muted }};">Claim no.</th>
                        <th align="left"  style="padding:8px; border-bottom:2px solid {{ $ink }}; font-size:10.5px; text-transform:uppercase; letter-spacing:0.5px; color:{{ $muted }};">Patient</th>
                        <th align="left"  style="padding:8px; border-bottom:2px solid {{ $ink }}; font-size:10.5px; text-transform:uppercase; letter-spacing:0.5px; color:{{ $muted }};">Submitted</th>
                        <th align="right" style="padding:8px; border-bottom:2px solid {{ $ink }}; font-size:10.5px; text-transform:uppercase; letter-spacing:0.5px; color:{{ $muted }};">Days</th>
                        <th align="right" style="padding:8px 0 8px 8px; border-bottom:2px solid {{ $ink }}; font-size:10.5px; text-transform:uppercase; letter-spacing:0.5px; color:{{ $muted }};">Amount (KWD)</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($claims as $i => $c)
                    <tr style="background:{{ $i % 2 ? '#fbfaf7' : $paper }};">
                        <td style="padding:9px 8px 9px 0; border-bottom:1px solid {{ $line }}; font-family:Consolas,Menlo,monospace; font-size:11.5px; color:{{ $ink }}; white-space:nowrap;">{{ $c['claim_number'] }}</td>
                        <td style="padding:9px 8px; border-bottom:1px solid {{ $line }}; color:{{ $ink }};">{{ $c['patient'] ?: '—' }}</td>
                        <td style="padding:9px 8px; border-bottom:1px solid {{ $line }}; color:{{ $muted }}; white-space:nowrap;">{{ $c['submitted'] ? \Illuminate\Support\Carbon::parse($c['submitted'])->format('d M Y') : 'Awaiting submission' }}</td>
                        <td align="right" style="padding:9px 8px; border-bottom:1px solid {{ $line }}; color:{{ $c['age_days'] > $totals['terms_days'] ? $danger : $muted }}; font-weight:{{ $c['age_days'] > $totals['terms_days'] ? 700 : 400 }};">{{ $c['age_days'] }}</td>
                        <td align="right" style="padding:9px 0 9px 8px; border-bottom:1px solid {{ $line }}; font-weight:600; color:{{ $ink }}; white-space:nowrap;">{{ $money($c['outstanding']) }}</td>
                    </tr>
                @endforeach
                    <tr>
                        <td colspan="4" align="right" style="padding:12px 8px 12px 0; font-weight:700; color:{{ $ink }};">Total due ({{ $totals['count'] }} {{ \Illuminate\Support\Str::plural('claim', $totals['count']) }})</td>
                        <td align="right" style="padding:12px 0 12px 8px; font-weight:700; font-size:14px; color:{{ $ink }}; border-top:2px solid {{ $gold }};">KWD {{ $money($totals['outstanding']) }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>

    {{-- Aging --}}
    @if(array_sum($totals['aging']) > 0)
    <tr>
        <td style="padding:18px 28px 0;">
            <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:0.6px; color:{{ $muted }}; margin-bottom:6px;">Aging analysis</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; font-size:11.5px;">
                <tr>
                    @foreach(['0–30 days' => 'b0', '31–60 days' => 'b31', '61–90 days' => 'b61', 'Over 90 days' => 'b90'] as $label => $key)
                        <td style="padding:8px 10px; border:1px solid {{ $line }}; background:{{ $key === 'b90' && $totals['aging'][$key] > 0 ? '#fdf3f2' : $paper }};">
                            <div style="color:{{ $muted }};">{{ $label }}</div>
                            <div style="font-weight:700; font-size:13px; color:{{ $key === 'b90' && $totals['aging'][$key] > 0 ? $danger : $ink }}; margin-top:2px;">{{ $money($totals['aging'][$key]) }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </td>
    </tr>
    @endif

    {{-- Ask --}}
    <tr>
        <td style="padding:20px 28px 0; font-size:13.5px; line-height:1.65; color:{{ $ink }};">
            <p style="margin:0 0 12px;">
                We kindly request confirmation of the expected settlement date for the above, or notification of any
                claim requiring further documentation from our side. Supporting invoices, medical reports and approval
                references can be resent on request.
            </p>
            <p style="margin:0 0 12px;">
                Should any item have already been settled, please share the remittance advice or transfer reference so
                we may reconcile our records accordingly.
            </p>
            <p style="margin:0 0 4px;">Thank you for your continued cooperation.</p>
        </td>
    </tr>

    {{-- Signature --}}
    <tr>
        <td style="padding:16px 28px 24px; font-size:13px; color:{{ $ink }};">
            <div style="border-top:1px solid {{ $line }}; padding-top:14px;">
                <div style="font-weight:700;">{{ $sender['name'] ?: 'Insurance Collections' }}</div>
                <div style="color:{{ $muted }}; font-size:12px; margin-top:2px;">{{ $sender['role'] }} · {{ $clinic['name'] }}</div>
                @if($clinic['phone'] || $clinic['email'])
                    <div style="color:{{ $muted }}; font-size:12px; margin-top:2px;">
                        @if($clinic['phone']){{ $clinic['phone'] }}@endif
                        @if($clinic['phone'] && $clinic['email']) · @endif
                        @if($clinic['email']){{ $clinic['email'] }}@endif
                    </div>
                @endif
            </div>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:14px 28px 18px; background:#faf8f3; border-top:1px solid {{ $line }}; font-size:10.5px; line-height:1.6; color:{{ $muted }};">
            <div style="font-weight:600; color:{{ $ink }};">{{ $clinic['name'] }}</div>
            @if($clinic['address'])<div>{{ $clinic['address'] }}</div>@endif
            <div style="margin-top:6px;">
                This statement is generated from our claims ledger and reflects balances outstanding as at
                {{ now()->format('d M Y') }}. It is intended solely for the addressed insurer and may contain
                confidential information.
            </div>
        </td>
    </tr>

</table>

</td></tr>
</table>
</body>
</html>
