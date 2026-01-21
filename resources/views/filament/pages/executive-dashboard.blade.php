<x-filament-panels::page>
@vite(['resources/css/filament-dashboard.css', 'resources/js/filament-dashboard.js'])
<style>
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse-subtle {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
    
    .card-hover {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    /* UPDATED: Added explicit text colors to glass effect to prevent inheritance issues */
    .glass-effect {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .dark .glass-effect {
        background: rgba(17, 24, 39, 0.8);
        border: 1px solid rgba(55, 65, 81, 0.3);
    }
    
    .gradient-border {
        position: relative;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2px;
        border-radius: 12px;
    }
    
    .gradient-border-content {
        background: white;
        border-radius: 10px;
    }
    
    .dark .gradient-border-content {
        background: #111827;
    }
    
    .metric-animation {
        animation: slideUp 0.6s ease-out;
    }
    
    .loading-pulse {
        animation: pulse-subtle 2s ease-in-out infinite;
    }
</style>

{{-- UPDATED: Added text-gray-900 dark:text-gray-100 to root for safety inheritance --}}
<div class="exec-dashboard min-h-screen bg-gray-50 dark:bg-gray-950 p-4 md:p-8 text-gray-900 dark:text-gray-100"
     x-data="{ loading: false }">


    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>

    {{-- HEADER WITH GLASSMORPHIC DESIGN --}}
    <div class="glass-effect rounded-2xl shadow-2xl p-6 md:p-8 mb-8 metric-animation">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-black bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-400 dark:to-purple-400 bg-clip-text text-transparent">
                        Executive Dashboard
                    </h1>
                    {{-- UPDATED: Explicit dark text color --}}
                    <p class="text-sm md:text-base text-gray-600 dark:text-gray-300 font-semibold mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        {{ $dashboardData['meta']['period_label'] ?? 'Performance Overview' }}
                    </p>
                </div>
            </div>

            <div class="w-full lg:w-auto lg:min-w-[650px]">
                <div class="glass-effect rounded-xl shadow-lg p-4">
                    <x-filament-panels::form wire:submit.prevent="updateDashboard">
                        {{ $this->filtersForm }}
                    </x-filament-panels::form>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI CARDS - MODERN DESIGN --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 md:gap-6 mb-8"
         wire:key="kpi-grid-{{ md5(json_encode($dashboardData['kpis'] ?? [])) }}">
        @foreach([
            ['title' => 'Total Revenue', 'key' => 'revenue', 'prefix' => 'KWD', 'decimals' => 0, 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'gradient' => 'from-green-500 to-emerald-600'],
            ['title' => 'Net Profit', 'key' => 'profit', 'prefix' => 'KWD', 'decimals' => 0, 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'gradient' => 'from-blue-500 to-cyan-600'],
            ['title' => 'Profit Margin', 'key' => 'margin', 'suffix' => '%', 'decimals' => 1, 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'gradient' => 'from-purple-500 to-pink-600'],
            ['title' => 'Avg Transaction', 'key' => 'avg_transaction', 'prefix' => 'KWD', 'decimals' => 0, 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'gradient' => 'from-orange-500 to-red-600'],
            ['title' => 'Total Visits', 'key' => 'visits', 'decimals' => 0, 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'gradient' => 'from-indigo-500 to-blue-600'],
            ['title' => 'Show Rate', 'key' => 'show_rate', 'suffix' => '%', 'decimals' => 1, 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'gradient' => 'from-teal-500 to-green-600'],
        ] as $index => $kpi)
            @php
                $data = $dashboardData['kpis'][$kpi['key']] ?? ['value' => 0, 'change' => 0, 'trend' => 'neutral'];
                $isPositive = ($data['trend'] ?? 'neutral') === 'up';
                $changeValue = (float)($data['change'] ?? 0);
            @endphp
            <div class="glass-effect rounded-2xl p-6 card-hover shadow-lg metric-animation border-l-4 border-{{ $isPositive ? 'green' : ($changeValue < 0 ? 'red' : 'gray') }}-500"
                 style="animation-delay: {{ $index * 0.1 }}s">
                
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $kpi['gradient'] }} flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/>
                        </svg>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ $kpi['title'] }}
                    </span>
                </div>

                {{-- UPDATED: Enforced dark:text-white --}}
                <div class="text-3xl font-black text-gray-900 dark:text-white mb-3">
                    {{ $kpi['prefix'] ?? '' }}
                    {{ number_format((float)($data['value'] ?? 0), $kpi['decimals']) }}
                    {{ $kpi['suffix'] ?? '' }}
                </div>

                @if(abs($changeValue) > 0)
                    <div class="flex items-center gap-2">
                        <div class="flex items-center px-3 py-1 rounded-full {{ $isPositive ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30' }}">
                            @if($isPositive)
                                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            @else
                                <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                                </svg>
                            @endif
                            <span class="ml-1 text-sm font-bold {{ $isPositive ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                {{ number_format(abs($changeValue), 1) }}%
                            </span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">vs previous</span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- REVENUE TREND & PAYMENT MIX & BOOKING SOURCES --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Revenue Trend Chart --}}
        <div class="lg:col-span-1 glass-effect rounded-2xl shadow-xl p-6 card-hover metric-animation"
             style="animation-delay: 0.7s"
             wire:key="revenue-trend-{{ md5(json_encode($dashboardData['revenue_trend'] ?? [])) }}"
             wire:ignore
             x-data="{
                chart: null,
                init() {
                    if (typeof echarts === 'undefined') return;
                    this.chart = echarts.init(this.$refs.revenueChart);
                    this.render();
                    
                    new MutationObserver(() => {
                        if (!this.chart) return;
                        this.chart.dispose();
                        this.chart = echarts.init(this.$refs.revenueChart);
                        this.render();
                    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    
                    window.addEventListener('resize', () => {
                      if (!this.chart) return;
                      if (typeof this.chart.isDisposed === 'function' && this.chart.isDisposed()) return;
                      try { this.chart.resize(); } catch (e) { /* swallow */ }
                    });
                },
                render() {
                    let isDark = document.documentElement.classList.contains('dark');
                    // UPDATED: High contrast colors for axes
                    let textColor = isDark ? '#e5e7eb' : '#374151'; // gray-200 vs gray-700
                    let gridColor = isDark ? '#374151' : '#e5e7eb';
                    
                    let data = @js($dashboardData['revenue_trend'] ?? []);
                    
                    this.chart.setOption({
                        backgroundColor: 'transparent',
                        title: {
                            text: 'Revenue Trend',
                            left: '0%',
                            textStyle: { fontSize: 18, fontWeight: 'bold', color: isDark ? '#ffffff' : '#111827' }
                        },
                        tooltip: {
                            trigger: 'axis',
                            backgroundColor: isDark ? '#1f2937' : '#ffffff',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            textStyle: { color: textColor },
                            axisPointer: { type: 'cross', lineStyle: { type: 'dashed' } }
                        },
                        grid: { left: '3%', right: '4%', bottom: '12%', top: '18%', containLabel: true },
                        xAxis: {
                            type: 'category',
                            data: data.map(d => d.date),
                            axisLine: { show: false },
                            axisTick: { show: false },
                            axisLabel: { color: textColor, fontSize: 11, fontWeight: '600' }
                        },
                        yAxis: {
                            type: 'value',
                            splitLine: { lineStyle: { type: 'dashed', color: gridColor, opacity: 0.5 } },
                            axisLabel: { color: textColor, fontSize: 11, fontWeight: '600' }
                        },
                        series: [
                            {
                                name: 'Revenue',
                                type: 'line',
                                data: data.map(d => d.revenue),
                                smooth: true,
                                lineStyle: { width: 4, color: '#3b82f6' },
                                areaStyle: { color: { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [
                                    { offset: 0, color: 'rgba(59, 130, 246, 0.3)' },
                                    { offset: 1, color: 'rgba(59, 130, 246, 0.05)' }
                                ]}},
                                itemStyle: { color: '#3b82f6', borderWidth: 3, borderColor: '#fff' }
                            }
                        ]
                    });
                }
             }">
            <div x-ref="revenueChart" class="h-[350px] w-full"></div>
        </div>

        {{-- Payment Mix --}}
        <div class="glass-effect rounded-2xl shadow-xl p-6 card-hover metric-animation"
             style="animation-delay: 0.8s"
             wire:key="payment-mix-{{ md5(json_encode($dashboardData['payment_mix'] ?? [])) }}"
             wire:ignore
             x-data="{
                chart: null,
                init() {
                    if (typeof echarts === 'undefined') return;
                    this.chart = echarts.init(this.$refs.paymentChart);
                    this.render();
                    
                    new MutationObserver(() => {
                        if (!this.chart) return;
                        this.chart.dispose();
                        this.chart = echarts.init(this.$refs.paymentChart);
                        this.render();
                    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    
                    window.addEventListener('resize', () => {
                      if (!this.chart) return;
                      if (typeof this.chart.isDisposed === 'function' && this.chart.isDisposed()) return;
                      try { this.chart.resize(); } catch (e) { /* swallow */ }
                    });
                },
                render() {
                    let isDark = document.documentElement.classList.contains('dark');
                    let textColor = isDark ? '#e5e7eb' : '#374151';
                    let data = @js($dashboardData['payment_mix'] ?? []);
                    
                    this.chart.setOption({
                        backgroundColor: 'transparent',
                        title: {
                            text: 'Payment Methods',
                            left: '0%',
                            textStyle: { fontSize: 18, fontWeight: 'bold', color: isDark ? '#ffffff' : '#111827' }
                        },
                        tooltip: {
                            trigger: 'item',
                            backgroundColor: isDark ? '#1f2937' : '#ffffff',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            textStyle: { color: textColor },
                            formatter: '{b}: {c} KWD<br/>({d}%)'
                        },
                        legend: {
                            bottom: '0%',
                            icon: 'circle',
                            textStyle: { color: textColor, fontSize: 11, fontWeight: 'bold' }
                        },
                        series: [{
                            name: 'Payment',
                            type: 'pie',
                            radius: ['50%', '75%'],
                            center: ['50%', '45%'],
                            data: data,
                            itemStyle: {
                                borderRadius: 10,
                                borderColor: isDark ? '#111827' : '#fff',
                                borderWidth: 3
                            },
                            label: { show: false },
                            color: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6']
                        }]
                    });
                }
             }">
            <div x-ref="paymentChart" class="h-[350px] w-full"></div>
        </div>

        {{-- Booking Sources (NEW) --}}
        <div class="glass-effect rounded-2xl shadow-xl p-6 card-hover metric-animation"
             style="animation-delay: 0.85s"
             wire:key="booking-sources-{{ md5(json_encode($dashboardData['booking_sources'] ?? [])) }}"
             wire:ignore
             x-data="{
                chart: null,
                init() {
                    if (typeof echarts === 'undefined') return;
                    this.chart = echarts.init(this.$refs.sourceChart);
                    this.render();
                    
                    new MutationObserver(() => {
                        if (!this.chart) return;
                        try {
                            if (this.chart && !(this.chart.isDisposed?.())) {
                                this.chart.dispose();
                            }
                        } catch (e) {}

                        this.chart = echarts.init(this.$refs.sourceChart);
                        this.render();
                    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    
                    window.addEventListener('resize', () => {
                      if (!this.chart) return;
                      if (typeof this.chart.isDisposed === 'function' && this.chart.isDisposed()) return;
                      try { this.chart.resize(); } catch (e) { /* swallow */ }
                    });
                },
                render() {
                    let isDark = document.documentElement.classList.contains('dark');
                    let textColor = isDark ? '#e5e7eb' : '#374151';
                    let data = @js($dashboardData['booking_sources'] ?? []);
                    
                    this.chart.setOption({
                        backgroundColor: 'transparent',
                        title: {
                            text: 'Booking Sources',
                            left: '0%',
                            textStyle: { fontSize: 18, fontWeight: 'bold', color: isDark ? '#ffffff' : '#111827' }
                        },
                        tooltip: {
                            trigger: 'item',
                            backgroundColor: isDark ? '#1f2937' : '#ffffff',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            textStyle: { color: textColor },
                            formatter: '{b}: {c}<br/>({d}%)'
                        },
                        legend: {
                            bottom: '0%',
                            icon: 'circle',
                            textStyle: { color: textColor, fontSize: 11, fontWeight: 'bold' }
                        },
                        series: [{
                            name: 'Source',
                            type: 'pie',
                            radius: ['40%', '70%'],
                            center: ['50%', '45%'],
                            roseType: 'radius',
                            data: data,
                            itemStyle: {
                                borderRadius: 5,
                                borderColor: isDark ? '#111827' : '#fff',
                                borderWidth: 2
                            },
                            label: { show: false },
                            color: ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6']
                        }]
                    });
                }
             }">
            <div x-ref="sourceChart" class="h-[350px] w-full"></div>
        </div>
    </div>

    {{-- BRANCH PERFORMANCE TABLE --}}
    @if(!empty($dashboardData['branch_performance']))
        <div class="glass-effect rounded-2xl shadow-xl p-6 mb-8 card-hover metric-animation" 
             style="animation-delay: 0.9s"
             wire:key="branch-perf-{{ md5(json_encode($dashboardData['branch_performance'] ?? [])) }}">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                {{-- UPDATED: Explicit text-white for dark mode --}}
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">Branch Performance</h2>
            </div>
            
            <div class="overflow-x-auto rounded-xl">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-200 dark:border-gray-700">
                            {{-- UPDATED: High contrast headers --}}
                            <th class="text-left py-4 px-4 text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Branch</th>
                            <th class="text-right py-4 px-4 text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Revenue</th>
                            <th class="text-right py-4 px-4 text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Profit</th>
                            <th class="text-right py-4 px-4 text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Margin</th>
                            <th class="text-right py-4 px-4 text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Visits</th>
                            <th class="text-right py-4 px-4 text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Avg Tx</th>
                            <th class="text-right py-4 px-4 text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Show Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dashboardData['branch_performance'] as $branch)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50 dark:hover:from-blue-950/30 dark:hover:to-purple-950/30 transition-all duration-300">
                                <td class="py-4 px-4 text-sm font-bold text-gray-900 dark:text-white">{{ $branch['branch'] }}</td>
                                <td class="py-4 px-4 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ number_format((float)$branch['revenue'], 0) }}</td>
                                <td class="py-4 px-4 text-sm text-right font-semibold text-green-600 dark:text-green-400">{{ number_format((float)$branch['profit'], 0) }}</td>
                                <td class="py-4 px-4 text-sm text-right">
                                    <span class="inline-flex px-3 py-1.5 rounded-full text-xs font-black shadow-sm
                                          {{ ($branch['margin'] ?? 0) >= 40 ? 'bg-gradient-to-r from-green-500 to-emerald-600 text-white'
                                            : (($branch['margin'] ?? 0) >= 30 ? 'bg-gradient-to-r from-yellow-500 to-orange-600 text-white'
                                            : 'bg-gradient-to-r from-red-500 to-pink-600 text-white') }}">
                                        {{ number_format((float)($branch['margin'] ?? 0), 1) }}%
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ (int)($branch['visits'] ?? 0) }}</td>
                                <td class="py-4 px-4 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ number_format((float)($branch['avg_tx'] ?? 0), 0) }}</td>
                                <td class="py-4 px-4 text-sm text-right font-semibold text-blue-600 dark:text-blue-400">{{ number_format((float)($branch['show_rate'] ?? 0), 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- DOCTOR PERFORMANCE & ITEM PROFITABILITY --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Doctor Performance --}}
<div class="glass-effect text-gray-900 dark:text-gray-100 rounded-2xl shadow-xl p-6 card-hover metric-animation"
     style="animation-delay: 1s"
     wire:key="doctor-perf-{{ md5(json_encode($dashboardData['doctor_performance'] ?? [])) }}">

            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">Doctor Performance</h2>
            </div>
            
            <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                @forelse(($dashboardData['doctor_performance'] ?? []) as $doctor)
                    {{-- UPDATED: Background dark/light logic --}}
                    <div class="bg-white dark:!bg-gray-800/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center justify-between mb-3">
                            {{-- UPDATED: Flexbox truncation pattern (flex-1 min-w-0) instead of fixed max-w --}}
                            <div class="flex-1 min-w-0 pr-2">
                                <div class="text-base font-bold text-gray-900 dark:text-white truncate">{{ $doctor['name'] }}</div>
                            </div>
                            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-full text-sm font-bold flex-shrink-0">
                                {{ $doctor['visits'] }} visits
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-950/20 dark:to-emerald-950/20 rounded-lg p-3">
                                {{-- UPDATED: text-gray-300/400 for strict visibility --}}
                                <div class="text-xs text-gray-600 dark:text-gray-300 font-semibold mb-1">Revenue</div>
                                <div class="text-lg font-black text-green-700 dark:text-green-400">{{ number_format((float)$doctor['revenue'], 0) }}</div>
                            </div>
                            <div class="bg-gradient-to-br from-blue-50 to-cyan-50 dark:from-blue-950/20 dark:to-cyan-950/20 rounded-lg p-3">
                                <div class="text-xs text-gray-600 dark:text-gray-300 font-semibold mb-1">Net Profit</div>
                                <div class="text-lg font-black text-blue-700 dark:text-blue-400">{{ number_format((float)$doctor['net_profit'], 0) }}</div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-gray-600 dark:text-gray-400">Utilization</span>
                                <span class="text-sm font-black {{ ($doctor['utilization'] ?? 0) >= 80 ? 'text-green-600 dark:text-green-400' : (($doctor['utilization'] ?? 0) >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                                    {{ number_format((float)($doctor['utilization'] ?? 0), 0) }}%
                                </span>
                            </div>
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden shadow-inner">
                                <div class="h-full rounded-full transition-all duration-500 {{ ($doctor['utilization'] ?? 0) >= 80 ? 'bg-gradient-to-r from-green-500 to-emerald-600' : (($doctor['utilization'] ?? 0) >= 50 ? 'bg-gradient-to-r from-yellow-500 to-orange-600' : 'bg-gradient-to-r from-red-500 to-pink-600') }}"
                                     style="width: {{ (float)($doctor['utilization'] ?? 0) }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">No doctor data available</div>
                @endforelse
            </div>
        </div>

        {{-- Item Profitability --}}
<div class="glass-effect text-gray-900 dark:text-gray-100 rounded-2xl shadow-xl p-6 card-hover metric-animation"
     style="animation-delay: 1.1s"
     wire:key="item-profit-{{ md5(json_encode($dashboardData['item_profitability'] ?? [])) }}">

            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">Top Services</h2>
            </div>
            
            <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                @forelse(($dashboardData['item_profitability'] ?? []) as $item)
                    <div class="bg-white dark:!bg-gray-800/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-300">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0 pr-2">
                                <div class="text-base font-bold text-gray-900 dark:text-white mb-1 truncate">{{ $item['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase">{{ $item['type'] }}</div>
                            </div>
                            <span class="px-3 py-1.5 rounded-full text-xs font-black bg-gradient-to-r from-purple-500 to-pink-600 text-white shadow-md flex-shrink-0">
                                {{ number_format((float)$item['margin'], 1) }}% margin
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-2 mb-3 text-xs">
                            <div class="text-center p-2 bg-blue-50 dark:bg-blue-950/20 rounded-lg">
                                <div class="text-gray-600 dark:text-gray-300 font-semibold mb-1">Revenue</div>
                                <div class="font-black text-blue-700 dark:text-blue-400">{{ number_format((float)$item['revenue'], 0) }}</div>
                            </div>
                            <div class="text-center p-2 bg-red-50 dark:bg-red-950/20 rounded-lg">
                                <div class="text-gray-600 dark:text-gray-300 font-semibold mb-1">Cost</div>
                                <div class="font-black text-red-700 dark:text-red-400">{{ number_format((float)$item['cost'], 0) }}</div>
                            </div>
                            <div class="text-center p-2 bg-green-50 dark:bg-green-950/20 rounded-lg">
                                <div class="text-gray-600 dark:text-gray-300 font-semibold mb-1">Profit</div>
                                <div class="font-black text-green-700 dark:text-green-400">{{ number_format((float)$item['profit'], 0) }}</div>
                            </div>
                        </div>
                        
                        <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden shadow-inner">
                            <div class="h-full bg-gradient-to-r from-green-500 to-emerald-600 rounded-full transition-all duration-500"
                                 style="width: {{ min(100, (float)($item['margin'] ?? 0)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">No service data available</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- CANCELLATIONS & FOLLOW-UP --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Cancellation Analysis --}}
        {{-- UPDATED: Added max-height, scrollbar, and missing card hover effects --}}
<div class="glass-effect text-gray-900 dark:text-gray-100 rounded-2xl shadow-xl p-6 card-hover metric-animation"
     style="animation-delay: 1.2s"
     wire:key="cancel-{{ md5(json_encode($dashboardData['cancellation_analysis'] ?? [])) }}">

            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-orange-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">Cancellation Insights</h2>
            </div>
            
            <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                @forelse(($dashboardData['cancellation_analysis'] ?? []) as $item)
                    <div class="bg-white dark:!bg-gray-800/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center justify-between mb-2">
                            {{-- UPDATED: Swapped span for div with min-w-0 for flexbox truncation --}}
                            <div class="flex-1 min-w-0 pr-2">
                                <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $item['reason'] }}</div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="text-lg font-black text-gray-900 dark:text-white">{{ $item['count'] }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-2 font-semibold">({{ number_format((float)$item['percentage'], 1) }}%)</span>
                            </div>
                        </div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden shadow-inner">
                            <div class="h-full bg-gradient-to-r from-red-500 to-orange-600 rounded-full transition-all duration-500"
                                 style="width: {{ (float)($item['percentage'] ?? 0) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">No cancellation data available</div>
                @endforelse
            </div>
        </div>

        {{-- Follow-up Funnel --}}
        {{-- UPDATED: Added matching scroll logic to prevent grid stretching --}}
        <div class="glass-effect rounded-2xl shadow-xl p-6 card-hover metric-animation" 
             style="animation-delay: 1.3s"
             wire:key="followup-{{ md5(json_encode($dashboardData['followup_funnel'] ?? [])) }}">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-500 to-cyan-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">Follow-up Conversion</h2>
            </div>
            
            <div class="space-y-5 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                @forelse(($dashboardData['followup_funnel'] ?? []) as $index => $stage)
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-teal-500 to-cyan-600 flex items-center justify-center text-white text-sm font-black">
                                    {{ $index + 1 }}
                                </div>
                                <span class="text-base font-bold text-gray-900 dark:text-white">{{ $stage['stage'] }}</span>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-black text-gray-900 dark:text-white">{{ $stage['count'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-semibold">{{ number_format((float)$stage['percentage'], 0) }}%</div>
                            </div>
                        </div>
                        <div class="relative h-10 bg-gray-200 dark:bg-gray-700 rounded-xl overflow-hidden shadow-lg">
                            <div class="absolute inset-0 bg-gradient-to-r from-teal-500 to-cyan-600 transition-all duration-700 flex items-center justify-end pr-4"
                                 style="width: {{ (float)($stage['percentage'] ?? 0) }}%">
                                <span class="text-white text-sm font-black">{{ number_format((float)$stage['percentage'], 0) }}%</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">No follow-up data available</div>
                @endforelse
            </div>

            @if(!empty($dashboardData['followup_funnel']))
                @php $conversion = $dashboardData['followup_funnel'][2]['percentage'] ?? 0; @endphp
                <div class="mt-6 gradient-border">
                    <div class="gradient-border-content p-4">
                        <div class="text-center">
                            <div class="text-4xl font-black bg-gradient-to-r from-teal-600 to-cyan-600 bg-clip-text text-transparent mb-2">
                                {{ number_format((float)$conversion, 0) }}%
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Overall Conversion Rate</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Footer Info --}}
    <div class="glass-effect rounded-2xl p-6 shadow-lg metric-animation" style="animation-delay: 1.4s">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Data Integrity Notice</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    All calculations use snapshot values from <span class="font-bold text-gray-900 dark:text-white">completed visits only</span>. 
                    Dashboard generated at <span class="font-bold text-blue-600 dark:text-blue-400">{{ $dashboardData['meta']['generated_at'] ?? now()->format('H:i') }}</span>. 
                    Data refreshes automatically when filters change.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 10px;
    }
    
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
    }
    
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }
</style>
</x-filament-panels::page>