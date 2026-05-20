<x-filament-panels::page>
    {{-- DEPENDENCIES --}}
    @vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">

    <style>
        /* Modern Dashboard Styles */
        .exec-dashboard { font-family: 'Inter', sans-serif; }
        
        /* Animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .metric-animation { animation: slideUp 0.6s ease-out backwards; }
        
        /* Glassmorphism */
        .glass-effect {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(229, 231, 235, 0.5);
        }
        .dark .glass-effect {
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid rgba(55, 65, 81, 0.5);
        }

        /* Card Hover */
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }

        /* Gradients */
        .gradient-text {
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            background-image: linear-gradient(to right, #3b82f6, #8b5cf6);
        }
        .dark .gradient-text {
            background-image: linear-gradient(to right, #60a5fa, #a78bfa);
        }

        /* Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(156, 163, 175, 0.5); border-radius: 20px; }

        /* Print Optimization */
        @media print {
            .no-print { display: none !important; }
            .fi-sidebar, .fi-header, .fi-topbar { display: none !important; }
            main { margin: 0 !important; padding: 0 !important; }
            body { background: white !important; color: black !important; }
            .glass-effect { box-shadow: none !important; border: 1px solid #ddd !important; background: white !important; }
            .card-hover { transform: none !important; box-shadow: none !important; }
            .text-white { color: black !important; }
        }
    </style>

    <div class="exec-dashboard space-y-6 text-gray-900 dark:text-gray-100">

        {{-- HEADER --}}
        <div class="glass-effect rounded-3xl p-6 shadow-sm metric-animation flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-600 to-purple-700 flex items-center justify-center shadow-lg text-white">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <h1 class="text-3xl font-black gradient-text tracking-tight">Daily Reconciliation</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        {{ $reportDate->format('l, d M Y') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 no-print w-full md:w-auto">
                <div class="glass-effect rounded-xl px-4 py-2 flex-1 md:flex-none">
                    {{ $this->form }}
                </div>
            </div>
        </div>

        {{-- KPI GRID --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Total Revenue --}}
            <div class="glass-effect rounded-2xl p-6 card-hover metric-animation border-l-4 border-green-500" style="animation-delay: 0.1s">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Revenue</p>
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white mt-1">{{ number_format($totalCollected, 3) }} <span class="text-sm text-gray-500 dark:text-gray-400">KD</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600 dark:text-green-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </div>

            {{-- Transactions --}}
            <div class="glass-effect rounded-2xl p-6 card-hover metric-animation border-l-4 border-blue-500" style="animation-delay: 0.2s">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transactions</p>
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white mt-1">{{ $payments->count() }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </div>
                </div>
            </div>

            {{-- Average Ticket --}}
            <div class="glass-effect rounded-2xl p-6 card-hover metric-animation border-l-4 border-purple-500" style="animation-delay: 0.3s">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg. Transaction</p>
                        @php $avg = $payments->count() > 0 ? $totalCollected / $payments->count() : 0; @endphp
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white mt-1">{{ number_format($avg, 3) }} <span class="text-sm text-gray-500 dark:text-gray-400">KD</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                    </div>
                </div>
            </div>

            {{-- Cash Collected --}}
            <div class="glass-effect rounded-2xl p-6 card-hover metric-animation border-l-4 border-orange-500" style="animation-delay: 0.4s">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cash Collected</p>
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white mt-1">{{ number_format($byMethod['cash'] ?? 0, 3) }} <span class="text-sm text-gray-500 dark:text-gray-400">KD</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-600 dark:text-orange-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHARTS & BREAKDOWNS --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {{-- Payment Methods Chart --}}
            <div class="glass-effect rounded-2xl p-6 shadow-sm metric-animation lg:col-span-2" style="animation-delay: 0.5s">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6">Payment Distribution</h3>
                <div x-data="{
                    init() {
                        const chart = echarts.init(this.$refs.chart);
                        const isDark = document.documentElement.classList.contains('dark');
                        const textColor = isDark ? '#e5e7eb' : '#374151';
                        
                        const data = @js($byMethod->map(fn($v, $k) => ['name' => ucfirst($k), 'value' => $v])->values());
                        
                        chart.setOption({
                            tooltip: { 
                                trigger: 'item', 
                                formatter: '{b}: {c} KD ({d}%)',
                                backgroundColor: isDark ? '#1f2937' : '#ffffff',
                                borderColor: isDark ? '#374151' : '#e5e7eb',
                                textStyle: { color: textColor }
                            },
                            legend: { 
                                bottom: '0%', 
                                left: 'center',
                                textStyle: { color: textColor } 
                            },
                            color: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899'],
                            series: [{
                                name: 'Method',
                                type: 'pie',
                                radius: ['40%', '70%'],
                                avoidLabelOverlap: false,
                                itemStyle: { borderRadius: 10, borderColor: isDark ? '#1f2937' : '#fff', borderWidth: 2 },
                                label: { show: false },
                                data: data
                            }]
                        });
                        window.addEventListener('resize', () => chart.resize());
                    }
                }">
                    <div x-ref="chart" class="h-[300px] w-full"></div>
                </div>
            </div>

            {{-- Collector Breakdown --}}
            <div class="glass-effect rounded-2xl p-6 shadow-sm metric-animation" style="animation-delay: 0.6s">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6">Staff Performance</h3>
                <div class="space-y-4 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
                    @foreach($byCollector as $name => $amount)
                        @php $percent = $totalCollected > 0 ? ($amount / $totalCollected) * 100 : 0; @endphp
                        <div>
                            <div class="flex justify-between items-end mb-1">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 truncate w-24" title="{{ $name }}">{{ $name }}</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($amount, 3) }} KD</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2.5 rounded-full" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- TRANSACTION TABLE --}}
        <div class="glass-effect dark:!bg-gray-800/50 rounded-2xl shadow-sm overflow-hidden metric-animation" style="animation-delay: 0.7s">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Transaction History</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-4 font-bold">Time</th>
                            <th class="px-6 py-4 font-bold">Ref #</th>
                            <th class="px-6 py-4 font-bold">Patient</th>
                            <th class="px-6 py-4 font-bold">Doctor</th>
                            <th class="px-6 py-4 font-bold">Method</th>
                            <th class="px-6 py-4 font-bold">Collector</th>
                            <th class="px-6 py-4 font-bold text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:!bg-gray-800/50">
                        @forelse($payments as $p)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap font-medium">
                                    {{ $p->paid_at ? $p->paid_at->format('h:i A') : '-' }}
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-gray-400 dark:text-gray-500">
                                    {{ $p->reference_no ?? $p->id }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                    {{ $p->visit->patient->name ?? 'Guest' }}
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                    {{ $p->visit->doctor->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $p->method === 'cash',
                                        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' => $p->method === 'knet',
                                        'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' => in_array($p->method, ['link','myfatoorah','tap']),
                                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => !in_array($p->method, ['cash','knet','link','myfatoorah','tap']),
                                    ])>
                                        {{ $p->method }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                    {{ $p->collectedBy->name ?? 'System' }}
                                </td>
                                <td class="px-6 py-4 text-right font-black text-gray-900 dark:text-white text-base">
                                    {{ number_format($p->amount, 3) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                    No payments recorded for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SIGNATURE SECTION (Print Only) --}}
        <div class="hidden print:block mt-12 pt-8 border-t-2 border-dashed border-gray-300">
            <div class="flex justify-between items-center text-sm">
                <div class="text-left w-1/3">
                    <p class="font-bold text-gray-900 mb-12">Receptionist Signature</p>
                    <div class="border-b border-gray-900 w-full"></div>
                </div>
                <div class="text-center w-1/3">
                    <p class="text-xs text-gray-400">
                        Generated via {{ config('app.name') }}<br>
                        {{ now()->format('Y-m-d H:i:s') }}
                    </p>
                </div>
                <div class="text-right w-1/3">
                    <p class="font-bold text-gray-900 mb-12">Manager Verification</p>
                    <div class="border-b border-gray-900 w-full"></div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>