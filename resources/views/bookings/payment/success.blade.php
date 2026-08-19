<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $ref }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Print Optimization */
        @media print {
            @page { margin: 0; size: auto; }
            body { background: white !important; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .print-container { 
                box-shadow: none !important; 
                border: none !important; 
                max-width: 100% !important; 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 20px !important;
            }
            .print-text-black { color: black !important; }
            .print-bg-none { background: none !important; }
        }
    </style>

    {{-- Favicon --}}
    @include('partials.favicon')
</head>
<!-- Screen: Centered gray background. Print: White, full width. -->
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4 print:bg-white print:block print:p-0">

    <!-- Receipt Container -->
    <div class="print-container w-full max-w-sm bg-white rounded-3xl shadow-[0_20px_50px_rgba(8,_112,_184,_0.07)] border border-gray-100 overflow-hidden relative">
        
        <!-- Status Bar (Success) -->
        <div class="bg-emerald-50 p-6 text-center border-b border-dashed border-emerald-100 print-bg-none print:border-gray-200">
            <div class="w-14 h-14 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm print:hidden">
                <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Payment Successful</h1>
            <p class="text-sm text-emerald-600 font-medium mt-1 print:text-gray-500">{{ now()->format('d M Y, h:i A') }}</p>
        </div>

        <!-- Ticket Body -->
        <div class="p-6 bg-white relative">
            
            <!-- Amount Display -->
            <div class="text-center mb-8">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Paid</span>
                <div class="mt-1 flex items-baseline justify-center text-gray-900">
                    <span class="text-4xl font-bold tracking-tighter">{{ number_format($amount, 3) }}</span>
                    <span class="text-lg font-medium text-gray-500 ml-1">KWD</span>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="space-y-4 text-sm">
                <!-- Reference -->
                <div class="flex justify-between items-center py-3 border-b border-gray-50 print:border-gray-100">
                    <span class="text-gray-500">Transaction ID</span>
                    <span class="font-mono font-medium text-gray-900 select-all">{{ $ref }}</span>
                </div>

                <!-- Booking Code -->
                <div class="flex justify-between items-center py-3 border-b border-gray-50 print:border-gray-100">
                    <span class="text-gray-500">Booking Reference</span>
                    <span class="font-mono font-medium text-gray-900">{{ $booking->booking_code ?? '---' }}</span>
                </div>

                <!-- Patient Name (Defensive check) -->
                <div class="flex justify-between items-center py-3 border-b border-gray-50 print:border-gray-100">
                    <span class="text-gray-500">Patient</span>
                    <span class="font-medium text-gray-900 text-right truncate max-w-[180px]">
                        {{ $booking->patient->name ?? $booking->contact->name ?? 'Guest Patient' }}
                    </span>
                </div>

                <!-- Payment Method -->
                <div class="flex justify-between items-center py-3">
                    <span class="text-gray-500">Method</span>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">KNET / Card</span>
                    </div>
                </div>
            </div>

            <!-- Cutout/Dashed Line decoration for screen only -->
            <div class="absolute left-0 right-0 -bottom-3 flex justify-between items-center px-4 no-print pointer-events-none">
                <div class="w-4 h-4 bg-gray-100 rounded-full -ml-6"></div>
                <div class="w-full border-b-2 border-dashed border-gray-100 mx-2"></div>
                <div class="w-4 h-4 bg-gray-100 rounded-full -mr-6"></div>
            </div>
        </div>

        <!-- Footer / Actions -->
        <div class="bg-gray-50 p-6 mt-2 print:hidden">
            <button onclick="window.print()" class="w-full mb-3 bg-gray-900 hover:bg-black text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-gray-200 transition-all flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print Receipt
            </button>
            <p class="text-center text-xs text-gray-400 font-medium">
                Automated System Message<br>You can close this page safely.
            </p>
        </div>

        <!-- Print Footer (Only shows on print) -->
        <div class="hidden print:block text-center mt-8 pt-8 border-t border-gray-200">
            <p class="text-xs text-gray-500">Thank you for your visit.</p>
        </div>
    </div>

</body>
</html>