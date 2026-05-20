<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $payment->reference_no ?? $payment->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Print Optimization */
        @media print {
            @page { margin: 0; size: auto; }
            body { background: white !important; -webkit-print-color-adjust: exact; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-container { 
                box-shadow: none !important; 
                border: none !important; 
                max-width: 100% !important; 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 10px !important;
            }
            .print-bg-none { background: none !important; }
            .print-text-black { color: black !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-start justify-center p-8 print:p-0 print:bg-white print:items-start">

    <!-- Receipt Container -->
    <div class="print-container w-full max-w-[400px] bg-white shadow-xl rounded-2xl border border-gray-100 overflow-hidden relative">
        
        <!-- Header -->
        <div class="bg-slate-50 p-6 text-center border-b border-dashed border-slate-200 print-bg-none print:border-gray-300">
            @if(isset($partner) && $partner->logo_url)
                <img src="{{ $partner->logo_url }}" alt="Logo" class="h-12 mx-auto mb-3 object-contain filter grayscale opacity-90">
            @endif
            
            <h1 class="text-lg font-bold text-gray-900 uppercase tracking-tight">
                {{ $partner->name_label ?? 'Medical Clinic' }}
            </h1>
            <p class="text-xs text-gray-500 font-medium mt-1">
                {{ $booking->branch->localized_name ?? '' }}
            </p>
        </div>

        <!-- Receipt Body -->
        <div class="p-6 bg-white">
            
            <!-- Metadata -->
            <div class="flex justify-between items-center text-xs text-gray-500 mb-6 pb-4 border-b border-gray-100">
                <span>{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y h:i A') : date('d/m/Y') }}</span>
                <span>Receipt #{{ $payment->id }}</span>
            </div>

            <!-- Patient Info -->
            <div class="mb-6 space-y-1">
                <p class="text-xs text-gray-400 uppercase font-semibold">Bill To</p>
                <p class="text-sm font-bold text-gray-900 truncate">
                    {{ $patient->name ?? $booking->contact->name ?? 'Guest' }}
                </p>
                <p class="text-xs text-gray-500 font-mono">{{ $booking->msisdn }}</p>
            </div>

            <!-- Line Items -->
            <div class="space-y-3 mb-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Consultation Fee</p>
                        <p class="text-xs text-gray-500">{{ $doctor->name ?? 'General' }}</p>
                    </div>
                    <p class="text-sm font-bold text-gray-900">
                        {{ number_format($payment->amount, 3) }}
                    </p>
                </div>
            </div>

            <!-- Totals -->
            <div class="border-t-2 border-dashed border-gray-100 pt-4 mb-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-gray-900">Total Paid</span>
                    <span class="text-xl font-bold text-gray-900">
                        {{ number_format($payment->amount, 3) }} <span class="text-xs text-gray-500 font-medium">KD</span>
                    </span>
                </div>
            </div>
            
            <div class="flex justify-between items-center mt-2">
                <span class="text-xs text-gray-500">Method</span>
                <span class="text-xs font-bold text-gray-700 uppercase bg-gray-100 px-2 py-1 rounded">
                    {{ $payment->method ?? 'CASH' }}
                </span>
            </div>
            
            @if($payment->reference_no)
            <div class="flex justify-between items-center mt-1">
                <span class="text-xs text-gray-500">Ref No.</span>
                <span class="text-xs font-mono text-gray-500">{{ $payment->reference_no }}</span>
            </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="bg-slate-50 p-4 text-center border-t border-slate-100 print-bg-none">
            <p class="text-[10px] text-gray-400 leading-relaxed">
                Thank you for your visit.<br>
                Booking Ref: {{ $booking->booking_code }}
            </p>
        </div>

        <!-- Cutout Decoration (Screen Only) -->
        <div class="absolute top-[120px] left-0 -ml-3 w-6 h-6 bg-gray-100 rounded-full no-print"></div>
        <div class="absolute top-[120px] right-0 -mr-3 w-6 h-6 bg-gray-100 rounded-full no-print"></div>

        <!-- Actions (Hidden on Print) -->
        <div class="p-4 no-print space-y-3 bg-white border-t border-gray-100">
            <button onclick="window.print()" class="w-full bg-gray-900 hover:bg-black text-white font-semibold py-3 px-4 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print Receipt
            </button>
            <button onclick="window.close()" class="w-full bg-white border border-gray-200 text-gray-600 font-medium py-3 px-4 rounded-xl hover:bg-gray-50 transition-colors text-sm">
                Close Window
            </button>
        </div>
    </div>

</body>
</html>