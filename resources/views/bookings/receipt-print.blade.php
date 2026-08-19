@php
    use Illuminate\Support\Carbon;

    $clinicName = is_array($partner->name ?? null) ? ($partner->name['en'] ?? reset($partner->name)) : ($partner?->name ?? 'Medical Clinic');
    $branchName = is_array($booking->branch->name ?? null) ? ($booking->branch->name['en'] ?? null) : ($booking->branch->name ?? null);
    $logo = $partner && $partner->logo_path ? asset('storage/'.$partner->logo_path) : null;
    $isRtl = app()->getLocale() === 'ar';

    $fmt = fn ($n) => number_format((float) $n, 3);
    $lastPaid = $payments->last();
    $issuedAt = $lastPaid && $lastPaid->paid_at ? Carbon::parse($lastPaid->paid_at) : now();
    $receiptNo = $lastPaid->reference_no ?? ('V'.$visit->id);

    $status = $balance <= 0.005 ? 'PAID' : ($paid > 0 ? 'PARTIAL' : 'DUE');
    $statusClass = $status === 'PAID' ? 'st-paid' : ($status === 'PARTIAL' ? 'st-partial' : 'st-due');

    $patientName = $patient->name ?? $booking->contact->name ?? 'Guest';
    $patientPhone = $booking->msisdn ?? ($patient->phone ?? null);
    $age = ($patient && ($patient->dob ?? null)) ? Carbon::parse($patient->dob)->age.' yrs' : null;
    $gender = ($patient && ($patient->gender ?? null)) ? ucfirst($patient->gender) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $receiptNo }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        :root { --accent: #0d9488; --ink: #111827; --muted: #6b7280; --line: #e5e7eb; }
        * { box-sizing: border-box; }

        @media print {
            @page { margin: 0; size: A5 portrait; }
            html, body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .sheet { box-shadow: none !important; margin: 0 !important; border: none !important; width: 100% !important; }
        }

        body {
            font-family: 'Inter', sans-serif; color: var(--ink); background: #f3f4f6;
            margin: 0; padding: 20px 12px; font-size: 12px; line-height: 1.5;
            display: flex; flex-direction: column; align-items: center;
        }
        .sheet { width: 148mm; max-width: 100%; background: #fff; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.08); }

        /* Letterhead — matches the prescription / medical-leave prints */
        .head { display: flex; align-items: center; gap: 16px; padding: 20px 24px; border-bottom: 3px solid var(--accent); }
        .head img { height: 56px; width: auto; object-fit: contain; }
        .head .title { flex: 1; min-width: 0; }
        .head h1 { margin: 0; font-size: 17px; font-weight: 800; text-transform: uppercase; letter-spacing: -.2px; color: var(--ink); }
        .head .sub { margin: 3px 0 0; font-size: 10px; color: var(--muted); line-height: 1.4; }
        .head .badge { text-align: {{ $isRtl ? 'left' : 'right' }}; font-size: 11px; color: var(--accent); font-weight: 800; text-transform: uppercase; letter-spacing: .6px; }

        .body { padding: 20px 24px; }

        .meta { display: flex; justify-content: space-between; gap: 12px; padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px dashed var(--line); }
        .meta .k { display: block; text-transform: uppercase; letter-spacing: .5px; font-size: 8px; color: var(--accent); font-weight: 700; margin-bottom: 2px; }
        .meta .v { color: var(--ink); font-weight: 600; font-size: 12px; }

        .bill-to { margin-bottom: 16px; }
        .bill-to .name { font-size: 14px; font-weight: 700; }
        .bill-to .meta-line { font-size: 11px; color: var(--muted); }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th { text-align: {{ $isRtl ? 'right' : 'left' }}; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); border-bottom: 1px solid var(--line); padding: 7px 0; }
        table.items th.qty, table.items td.qty { text-align: center; width: 40px; }
        table.items th.amt, table.items td.amt { text-align: {{ $isRtl ? 'left' : 'right' }}; white-space: nowrap; }
        table.items td { padding: 9px 0; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        table.items .desc { font-weight: 600; }
        table.items .hint { font-size: 10px; color: var(--muted); }
        table.items .empty { text-align: center; color: var(--muted); font-style: italic; padding: 18px 0; }

        /* Per-line saving: the gross price struck through, the charged price next to it. */
        table.items .was { color: var(--muted); text-decoration: line-through; font-size: 10px; margin-inline-end: 5px; }
        table.items .now { font-weight: 700; }
        table.items .saved-tag { margin-top: 3px; font-size: 9px; font-weight: 700; color: #047857; }

        /* Headline saving — the number the patient should walk away remembering. */
        .savings-banner {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            margin-top: 12px; padding: 10px 14px;
            background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px;
        }
        .savings-banner .sb-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #047857; }
        .savings-banner .sb-amount { font-size: 17px; font-weight: 800; color: #047857; white-space: nowrap; }
        .savings-banner .sb-cur { font-size: 10px; font-weight: 700; }
        .savings-banner .sb-pct { margin-inline-start: 6px; padding: 1px 7px; border-radius: 999px; background: #047857; color: #fff; font-size: 10px; font-weight: 800; }

        .sumrow { display: flex; justify-content: space-between; font-size: 12px; padding: 3px 0; }
        .sumrow .lbl { color: var(--muted); }
        .sums { border-top: 1px solid var(--line); padding-top: 10px; }
        .grand { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; padding-top: 10px; border-top: 2px solid var(--ink); }
        .grand .lbl { font-size: 13px; font-weight: 700; }
        .grand .val { font-size: 22px; font-weight: 800; color: var(--ink); }
        .grand .cur { font-size: 11px; color: var(--muted); font-weight: 600; }

        .pay-block { margin-top: 16px; }
        .pay-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .pay-head .section-label { font-size: 8px; text-transform: uppercase; letter-spacing: .6px; color: var(--accent); font-weight: 700; }
        .pay-line { display: flex; justify-content: space-between; font-size: 11px; padding: 4px 0; border-bottom: 1px solid #f6f7f8; }
        .pay-line .left { color: var(--ink); }
        .pay-line .when { color: var(--muted); }

        .settle { margin-top: 12px; }
        .settle .row { display: flex; justify-content: space-between; font-size: 12px; padding: 3px 0; }
        .settle .row.paid .v { font-weight: 700; color: var(--accent); }
        .settle .row.bal .v { font-weight: 800; }
        .settle .row.bal.due .v { color: #b91c1c; }

        .chip { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
        .st-paid { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .st-partial { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .st-due { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .method-chip { display: inline-block; background: #f0fdfa; color: var(--accent); border: 1px solid #ccfbf1; padding: 1px 7px; border-radius: 999px; font-size: 9px; font-weight: 700; text-transform: uppercase; }

        .ins-note { margin-top: 16px; border: 1px solid #ccfbf1; background: #f0fdfa; border-radius: 8px; padding: 12px 14px; }
        .ins-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .ins-claim { font-size: 10px; color: var(--muted); font-weight: 600; }
        .ins-row { display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 600; color: var(--ink); }
        .ins-status { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: var(--accent); }
        .ins-amts { display: flex; gap: 24px; margin-top: 8px; }
        .ins-amts > div { display: flex; flex-direction: column; }
        .ins-amts .k { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: var(--accent); font-weight: 700; }
        .ins-amts .v { font-size: 13px; font-weight: 700; color: var(--ink); }
        .ins-foot { margin-top: 8px; font-size: 9px; color: var(--muted); font-style: italic; }

        .foot { padding: 14px 24px 20px; background: #fafafa; border-top: 1px solid var(--line); text-align: center; font-size: 9px; color: var(--muted); line-height: 1.6; }

        .actions { margin-top: 16px; display: flex; gap: 10px; }
        .actions button { font: inherit; cursor: pointer; border-radius: 8px; padding: 10px 18px; font-weight: 600; font-size: 13px; border: 1px solid transparent; }
        .btn-print { background: var(--accent); color: #fff; }
        .btn-close { background: #fff; color: var(--ink); border-color: var(--line); }
    </style>
</head>
<body>
    <div class="no-print" style="max-width:560px;width:100%;margin:0 auto 12px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:8px;font-size:12px;line-height:1.5;">
        💡 To hide the page number &amp; web address that your browser adds: in the Print dialog set <strong>Margins → None</strong> or untick <strong>Headers and footers</strong>.<br>
        <span dir="rtl">لإخفاء رقم الصفحة والرابط الذي يضيفه المتصفح: في نافذة الطباعة اضبط الهوامش إلى «بلا» أو ألغِ «رؤوس وتذييلات الصفحة».</span>
    </div>
    <div class="sheet">
        <div class="head">
            @if($logo)<img src="{{ $logo }}" alt="Logo">@endif
            <div class="title">
                <h1>{{ $clinicName }}</h1>
                <p class="sub">
                    @if($branchName)<strong>{{ $branchName }}</strong>@endif
                    @if($booking->branch->phone ?? null) · {{ $booking->branch->phone }}@endif
                    @if($partner->license_number ?? null)<br>Lic: {{ $partner->license_number }}@endif
                </p>
            </div>
            <div class="badge">Payment<br>Receipt</div>
        </div>

        <div class="body">
            <div class="meta">
                <div>
                    <span class="k">Receipt No.</span>
                    <span class="v">#{{ $receiptNo }}</span>
                </div>
                <div style="text-align:center">
                    <span class="k">Visit</span>
                    <span class="v">#{{ $visit->id }}{{ $booking->booking_code ? ' · '.$booking->booking_code : '' }}</span>
                </div>
                <div style="text-align:{{ $isRtl ? 'left' : 'right' }}">
                    <span class="k">Date</span>
                    <span class="v">{{ $issuedAt->format('d M Y · h:i A') }}</span>
                </div>
            </div>

            <div class="bill-to">
                <span class="k" style="display:block; text-transform:uppercase; letter-spacing:.5px; font-size:8px; color:var(--accent); font-weight:700; margin-bottom:2px;">Bill To</span>
                <div class="name">{{ $patientName }}</div>
                <div class="meta-line">
                    {{ $patientPhone ?? '' }}
                    @if($age || $gender) {{ $patientPhone ? '·' : '' }} {{ trim(($age ?? '').($age && $gender ? ' / ' : '').($gender ?? '')) }}@endif
                </div>
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="qty">Qty</th>
                        <th class="amt">Amount (KD)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                        <tr>
                            <td class="desc">
                                {{ $line['label'] }}
                                @if(!empty($line['hint']))<div class="hint">{{ $line['hint'] }}</div>@endif
                                @if($line['discount'] > 0)
                                    <div class="saved-tag">{{ $line['saved_label'] }} — you save {{ $fmt($line['discount']) }} KD</div>
                                @endif
                            </td>
                            <td class="qty">{{ rtrim(rtrim(number_format($line['qty'], 2), '0'), '.') }}</td>
                            <td class="amt">
                                @if($line['discount'] > 0)
                                    <span class="was">{{ $fmt($line['amount']) }}</span>
                                    <span class="now">{{ $fmt($line['net']) }}</span>
                                @else
                                    {{ $fmt($line['amount']) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No charges recorded for this visit.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="sums">
                <div class="sumrow">
                    <span class="lbl">Subtotal</span>
                    <span>{{ $fmt($subtotal) }}</span>
                </div>
                @if($lineDiscounts > 0)
                    <div class="sumrow">
                        <span class="lbl">Package offers / promotions</span>
                        <span>− {{ $fmt($lineDiscounts) }}</span>
                    </div>
                @endif
                @if($visitDiscount > 0)
                    <div class="sumrow">
                        <span class="lbl">Discount{{ $couponCode ? ' (coupon '.$couponCode.')' : '' }}</span>
                        <span>− {{ $fmt($visitDiscount) }}</span>
                    </div>
                @endif
                <div class="grand">
                    <span class="lbl">Grand Total</span>
                    <span><span class="val">{{ $fmt($grandTotal) }}</span> <span class="cur">KD</span></span>
                </div>
            </div>

            @if($totalSavings > 0.005)
                <div class="savings-banner">
                    <span class="sb-label">You saved on this visit</span>
                    <span class="sb-amount">
                        {{ $fmt($totalSavings) }} <span class="sb-cur">KD</span>
                        @if($savingsPercent > 0)<span class="sb-pct">−{{ $savingsPercent }}%</span>@endif
                    </span>
                </div>
            @endif

            <div class="pay-block">
                <div class="pay-head">
                    <span class="section-label">Payments</span>
                    <span class="chip {{ $statusClass }}">{{ $status }}</span>
                </div>
                @forelse($payments as $p)
                    <div class="pay-line">
                        <span class="left">
                            <span class="method-chip">{{ strtoupper($p->method ?? 'cash') }}</span>
                            <span class="when">{{ $p->paid_at ? Carbon::parse($p->paid_at)->format('d M Y') : '' }}</span>
                            @if($p->reference_no) · {{ $p->reference_no }}@endif
                        </span>
                        <span style="font-weight:600">{{ $fmt($p->amount) }}</span>
                    </div>
                @empty
                    <div class="pay-line"><span class="when">No payments recorded yet.</span><span></span></div>
                @endforelse
            </div>

            <div class="settle">
                <div class="row paid">
                    <span class="lbl">Total Paid</span>
                    <span class="v">{{ $fmt($paid) }} KD</span>
                </div>
                <div class="row bal {{ $balance > 0.005 ? 'due' : '' }}">
                    <span class="lbl">Balance Due</span>
                    <span class="v">{{ $fmt($balance) }} KD</span>
                </div>
            </div>

            @if($insurance)
                <div class="ins-note">
                    <div class="ins-head">
                        <span class="section-label">Insurance</span>
                        @if($insurance['claim_number'])<span class="ins-claim">Claim {{ $insurance['claim_number'] }}</span>@endif
                    </div>
                    <div class="ins-row">
                        <span>{{ $insurance['insurer'] ?? 'Insurer' }}{{ $insurance['plan'] ? ' · '.$insurance['plan'] : '' }}</span>
                        @if($insurance['status'])<span class="ins-status">{{ strtoupper(str_replace('_', ' ', $insurance['status'])) }}</span>@endif
                    </div>
                    <div class="ins-amts">
                        <div><span class="k">Insurer payable</span><span class="v">{{ $fmt($insurance['insurer_payable']) }} KD</span></div>
                        <div><span class="k">Patient copay</span><span class="v">{{ $fmt($insurance['patient_copay']) }} KD</span></div>
                    </div>
                    <div class="ins-foot">For information only — the insurer is billed separately and does not affect the balance above.</div>
                </div>
            @endif
        </div>

        <div class="foot">
            Thank you for choosing {{ $clinicName }}.<br>
            This is a computer-generated receipt — no signature required.
        </div>
    </div>

    <div class="actions no-print">
        <button class="btn-print" onclick="window.print()">Print Receipt</button>
        <button class="btn-close" onclick="window.close()">Close</button>
    </div>

    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 250));
    </script>
</body>
</html>
