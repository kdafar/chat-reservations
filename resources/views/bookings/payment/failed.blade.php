<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed | {{ config('app.name') }}</title>
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
            .print-bg-none { background: none !important; }
        }
    </style>

    {{-- Favicon --}}
    @include('partials.favicon')
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4 print:bg-white print:block print:p-0">

    <!-- Ticket Container -->
    <div class="print-container w-full max-w-sm bg-white rounded-3xl shadow-[0_20px_50px_rgba(220,_38,_38,_0.07)] border border-gray-100 overflow-hidden relative">
        
        <!-- Status Bar (Error - Red Theme) -->
        <div class="bg-red-50 p-6 text-center border-b border-dashed border-red-100 print-bg-none print:border-gray-200">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm print:hidden">
                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Payment Unsuccessful</h1>
            <p class="text-sm text-red-600 font-medium mt-1 print:text-gray-500">{{ now()->format('d M Y, h:i A') }}</p>
        </div>

        <!-- Body -->
        <div class="p-6 bg-white relative">
            
            <!-- Message Box -->
            <div class="bg-red-50 border border-red-100 rounded-xl p-4 mb-6 text-center shadow-sm">
                <p class="text-red-800 text-sm font-semibold leading-relaxed">
                    {{ $message ?? 'The transaction was declined or cancelled.' }}
                </p>
            </div>

            <p class="text-gray-500 text-sm text-center leading-relaxed mb-4">
                We could not process your payment. No funds have been deducted. Please try again or contact the reception for assistance.
            </p>

            <!-- Cutout/Dashed Line decoration for screen only -->
            <div class="absolute left-0 right-0 -bottom-3 flex justify-between items-center px-4 no-print pointer-events-none">
                <div class="w-4 h-4 bg-gray-100 rounded-full -ml-6"></div>
                <div class="w-full border-b-2 border-dashed border-gray-100 mx-2"></div>
                <div class="w-4 h-4 bg-gray-100 rounded-full -mr-6"></div>
            </div>
        </div>

        <!-- Footer / Actions -->
        <div class="bg-gray-50 p-6 mt-2 print:hidden space-y-3">
            <a href="tel:+96512345678" class="block w-full bg-gray-900 hover:bg-black text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-gray-200 transition-all text-center flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                Contact Reception
            </a>
            <button onclick="window.close()" class="block w-full bg-white border border-gray-200 text-gray-700 font-semibold py-3.5 px-4 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
                Close Window
            </button>
        </div>
    </div>

</body>
</html>