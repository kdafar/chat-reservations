<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Booking Pass – {{ $booking->booking_code }}</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#fafafa;margin:0;padding:24px;color:#111}
    .card{max-width:460px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.06);padding:20px}
    .h{font-size:20px;font-weight:700;margin:0 0 6px}
    .sub{color:#666;margin:0 0 16px}
    .row{display:flex;gap:12px;margin:10px 0}
    .chip{background:#f3f4f6;border-radius:10px;padding:8px 10px;font-size:14px}
    .qr{display:block;margin:18px auto 6px;width:260px;height:260px}
    .muted{color:#666;font-size:13px;text-align:center}
  </style>
</head>
<body>
  <div class="card">
    <h1 class="h">Booking Pass</h1>
    <p class="sub">Show this at the host stand to check in.</p>

    <div class="row">
      <div class="chip">Code: <strong>{{ $booking->booking_code }}</strong></div>
      <div class="chip">Party: <strong>{{ $booking->party_size }}</strong></div>
    </div>
    <div class="row">
      <div class="chip">Branch: <strong>{{ $booking->branch?->branch_name ?? $booking->branch?->name }}</strong></div>
    </div>
    <div class="row">
      <div class="chip">Date: <strong>{{ \Carbon\Carbon::parse($booking->res_date)->format('D, d M Y') }}</strong></div>
      <div class="chip">Time: <strong>{{ \Carbon\Carbon::parse($booking->res_time)->format('H:i') }}</strong></div>
    </div>

    <img class="qr" src="{{ $qrPngUrl }}" alt="Booking QR">
    <p class="muted">If the QR doesn’t scan, tell staff your code: <strong>{{ $booking->booking_code }}</strong></p>
  </div>
</body>
</html>
