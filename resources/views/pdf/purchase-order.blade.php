@php
    $isRtl = ($locale ?? app()->getLocale()) === 'ar';

    // KWD formatter (3 dp) and foreign formatter (3 dp, currency-aware)
    $kwd = fn ($n) => number_format((float) ($n ?? 0), 3);
    $cur = $po['currency'] ?? 'KWD';
    $isForeign = !empty($po['is_foreign']);
    $fx = fn ($n) => number_format((float) ($n ?? 0), 3);

    $lines = $po['lines'] ?? [];

    // Landed cost lines — only show nonzero
    $landed = [
        'Freight'   => $po['freight_amount']   ?? 0,
        'Customs'   => $po['customs_amount']    ?? 0,
        'Clearance' => $po['clearance_amount']  ?? 0,
        'Insurance' => $po['insurance_amount']  ?? 0,
        'Other'     => $po['other_charges_amount'] ?? 0,
    ];

    $hasShipment = !empty($po['carrier']) || !empty($po['tracking_no']) || !empty($po['container_no'])
        || !empty($po['ship_date']) || !empty($po['eta']);

    $statusRaw = $po['status'] ?? '';
@endphp
<!DOCTYPE html>
<html lang="{{ $isRtl ? 'ar' : 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order {{ $po['code'] ?? '' }}</title>
    <style>
        :root { --accent: #0d9488; --ink: #111827; --muted: #6b7280; --line: #e5e7eb; --soft: #f9fafb; }
        * { box-sizing: border-box; }

        @page { size: A4; margin: 18mm; }
        @media print {
            html, body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink); background: #fff; margin: 0; padding: 0;
            font-size: 12px; line-height: 1.5;
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }

        .print-bar {
            position: fixed; top: 12px; {{ $isRtl ? 'left' : 'right' }}: 12px; z-index: 50;
        }
        .print-bar button {
            font: inherit; cursor: pointer; background: var(--accent); color: #fff;
            border: none; border-radius: 8px; padding: 9px 16px; font-weight: 600; font-size: 13px;
            box-shadow: 0 4px 12px rgba(13,148,136,.3);
        }

        /* Letterhead */
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .head .org h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -.3px; color: var(--ink); }
        .head .org .branch { margin: 4px 0 0; font-size: 12px; color: var(--muted); font-weight: 600; }
        .head .doc { text-align: {{ $isRtl ? 'left' : 'right' }}; }
        .head .doc .title { font-size: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--accent); }
        .head .doc .code { margin-top: 4px; font-size: 14px; font-weight: 700; }
        .head .doc .date { margin-top: 2px; font-size: 11px; color: var(--muted); }
        .head .doc .status {
            display: inline-block; margin-top: 8px; padding: 2px 10px; border-radius: 999px;
            font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
            background: #f0fdfa; color: var(--accent); border: 1px solid #ccfbf1;
        }

        hr.rule { border: none; border-top: 2px solid var(--accent); margin: 14px 0 18px; }

        /* Two-column parties block */
        .parties { display: flex; gap: 24px; margin-bottom: 18px; }
        .parties .col { flex: 1; }
        .card-label { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: var(--accent); font-weight: 800; margin-bottom: 6px; }
        .party-name { font-size: 14px; font-weight: 700; }
        .kv { font-size: 11.5px; color: var(--ink); margin-top: 3px; }
        .kv .k { color: var(--muted); }

        .ship-row { margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--line); }

        /* Line items */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items th {
            background: var(--soft); border: 1px solid var(--line); padding: 8px 10px;
            font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; color: var(--muted); font-weight: 800;
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }
        table.items th.num, table.items td.num { text-align: {{ $isRtl ? 'left' : 'right' }}; white-space: nowrap; }
        table.items th.idx, table.items td.idx { text-align: center; width: 32px; }
        table.items td { border: 1px solid var(--line); padding: 8px 10px; vertical-align: top; }
        table.items tbody tr:nth-child(even) { background: #fcfcfd; }
        table.items .item-name { font-weight: 600; }
        table.items .empty { text-align: center; color: var(--muted); font-style: italic; padding: 16px 0; }

        /* Totals */
        .totals-wrap { display: flex; justify-content: {{ $isRtl ? 'flex-start' : 'flex-end' }}; }
        .totals { width: 320px; max-width: 100%; }
        .totals .row { display: flex; justify-content: space-between; font-size: 12px; padding: 5px 0; border-bottom: 1px solid #f3f4f6; }
        .totals .row .lbl { color: var(--muted); }
        .totals .row.section { border-bottom: none; padding-top: 8px; }
        .totals .grand {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-top: 8px; padding-top: 10px; border-top: 2px solid var(--ink);
        }
        .totals .grand .lbl { font-size: 13px; font-weight: 800; }
        .totals .grand .val { font-size: 18px; font-weight: 800; }
        .totals .grand .cur { font-size: 11px; color: var(--muted); font-weight: 600; }

        /* Notes */
        .notes { margin-top: 22px; border: 1px solid var(--line); border-radius: 8px; padding: 12px 14px; background: var(--soft); }
        .notes .card-label { margin-bottom: 4px; }
        .notes p { margin: 0; white-space: pre-line; font-size: 11.5px; }

        /* Signatures + footer */
        .signatures { display: flex; gap: 48px; margin-top: 48px; }
        .signatures .sig { flex: 1; }
        .signatures .sig .line { border-top: 1px solid var(--ink); padding-top: 6px; font-size: 11px; color: var(--muted); }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid var(--line); font-size: 10px; color: var(--muted); }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    {{-- Letterhead --}}
    <div class="head">
        <div class="org">
            <h1>{{ $clinic['name'] ?? '' }}</h1>
            @if(!empty($clinic['branch']))
                <p class="branch">{{ $clinic['branch'] }}</p>
            @endif
        </div>
        <div class="doc">
            <div class="title">Purchase Order</div>
            <div class="code">{{ $po['code'] ?? '' }}</div>
            @if(!empty($po['order_date']))
                <div class="date">{{ $po['order_date'] }}</div>
            @endif
            @if($statusRaw !== '')
                <div class="status">{{ strtoupper(str_replace('_', ' ', $statusRaw)) }}</div>
            @endif
        </div>
    </div>

    <hr class="rule">

    {{-- Vendor / Ship-to --}}
    <div class="parties">
        <div class="col">
            <div class="card-label">Vendor</div>
            <div class="party-name">{{ $po['vendor'] ?? '' }}</div>
            @if(!empty($po['vendor_code']))
                <div class="kv"><span class="k">Code:</span> {{ $po['vendor_code'] }}</div>
            @endif
            @if(!empty($po['vendor_reference']))
                <div class="kv"><span class="k">Vendor ref:</span> {{ $po['vendor_reference'] }}</div>
            @endif
        </div>
        <div class="col">
            <div class="card-label">Ship to</div>
            <div class="party-name">{{ $po['branch'] ?? '' }}</div>
            @if(!empty($po['order_date']))
                <div class="kv"><span class="k">Order date:</span> {{ $po['order_date'] }}</div>
            @endif
            @if(!empty($po['expected_date']))
                <div class="kv"><span class="k">Expected:</span> {{ $po['expected_date'] }}</div>
            @endif
            @if(!empty($po['incoterm']))
                <div class="kv"><span class="k">Incoterm:</span> {{ $po['incoterm'] }}</div>
            @endif
            <div class="kv">
                <span class="k">Currency:</span>
                @if($isForeign)
                    {{ $cur }} — 1 {{ $cur }} = {{ $kwd($po['exchange_rate'] ?? 0) }} KWD
                @else
                    KWD
                @endif
            </div>

            @if($hasShipment)
                <div class="ship-row">
                    <div class="card-label">Shipment</div>
                    @if(!empty($po['carrier']))
                        <div class="kv"><span class="k">Carrier:</span> {{ $po['carrier'] }}</div>
                    @endif
                    @if(!empty($po['tracking_no']))
                        <div class="kv"><span class="k">Tracking #:</span> {{ $po['tracking_no'] }}</div>
                    @endif
                    @if(!empty($po['container_no']))
                        <div class="kv"><span class="k">Container #:</span> {{ $po['container_no'] }}</div>
                    @endif
                    @if(!empty($po['ship_date']))
                        <div class="kv"><span class="k">Ship date:</span> {{ $po['ship_date'] }}</div>
                    @endif
                    @if(!empty($po['eta']))
                        <div class="kv"><span class="k">ETA:</span> {{ $po['eta'] }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th class="idx">#</th>
                <th>Item</th>
                <th>Country of origin</th>
                <th class="num">Qty</th>
                <th class="num">Unit cost ({{ $cur }})</th>
                <th class="num">Discount</th>
                <th class="num">Line total ({{ $cur }})</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $i => $line)
                <tr>
                    <td class="idx">{{ $i + 1 }}</td>
                    <td class="item-name">{{ $line['name'] ?? '' }}</td>
                    <td>{{ $line['country_of_origin'] ?? '' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) ($line['qty_ordered'] ?? 0), 2), '0'), '.') }}</td>
                    <td class="num">{{ $fx($line['unit_cost'] ?? 0) }}</td>
                    <td class="num">@if((float) ($line['discount_value'] ?? 0) > 0){{ ($line['discount_type'] ?? 'percent') === 'amount' ? $fx($line['discount_value']) : ($line['discount_value'].'%') }}@else—@endif</td>
                    <td class="num">{{ $fx($line['line_total'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No line items on this purchase order.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-wrap">
        <div class="totals">
            <div class="row">
                <span class="lbl">Goods subtotal ({{ $cur }})</span>
                <span>{{ $fx($po['goods_total'] ?? 0) }}</span>
            </div>
            @if($isForeign)
                <div class="row">
                    <span class="lbl">Goods (KWD)</span>
                    <span>{{ $kwd($po['goods_total_kwd'] ?? 0) }}</span>
                </div>
            @endif

            @foreach($landed as $label => $amount)
                @if((float) $amount != 0.0)
                    <div class="row">
                        <span class="lbl">{{ $label }} (KWD)</span>
                        <span>{{ $kwd($amount) }}</span>
                    </div>
                @endif
            @endforeach

            @if(!empty($po['landed_total']) && (float) $po['landed_total'] != 0.0)
                <div class="row">
                    <span class="lbl">Landed total (KWD)</span>
                    <span>{{ $kwd($po['landed_total']) }}</span>
                </div>
            @endif

            <div class="grand">
                <span class="lbl">Grand Total</span>
                <span><span class="val">{{ $kwd($po['total'] ?? 0) }}</span> <span class="cur">KWD</span></span>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    @if(!empty($po['notes']))
        <div class="notes">
            <div class="card-label">Notes</div>
            <p>{{ $po['notes'] }}</p>
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig">
            <div class="line">Authorised by</div>
        </div>
        <div class="sig">
            <div class="line">Vendor acknowledgement</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        {{ $clinic['name'] ?? '' }} · Generated {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
