{{-- 
    ENTERPRISE DASHBOARD VIEW
    - Design System: Professional, High-Density, Data-First.
    - Aesthetics: Minimalist, Border-based (Stripe-like), Precise Typography.
    - Tech: ECharts (CDN) + Tailwind + Blade.
--}}

{{-- CRITICAL: Load the Vite assets to ensure custom Tailwind classes apply --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])

<div class="space-y-6 p-4 md:p-8 max-w-[1920px] mx-auto font-sans antialiased text-gray-900 dark:text-gray-100">
    
    {{-- 1. LOAD ECHARTS LIBRARY --}}
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>

    {{-- 2. HEADER & CONTROLS --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Daily Operational Report</h1>
            <div class="flex items-center gap-3 mt-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium border border-gray-200 dark:border-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    {{ $reportData['role'] ?? 'Staff' }} Perspective
                </span>
                <span>&bull;</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ $reportData['date'] ?? now()->format('d M Y') }}</span>
            </div>
        </div>

        <div class="w-full lg:w-auto min-w-[300px]">
            <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm p-1">
                <form wire:submit="updateReport">
                    {{ $this->filtersForm }}
                </form>
            </div>
        </div>
    </div>

    {{-- 3. INVESTOR / OWNER FINANCIALS --}}
    @if(isset($reportData['financials']['net_profit']))
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        {{-- KPI STATS COLUMN --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Net Profit Tile --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 {{ ($reportData['financials']['net_profit'] ?? 0) > 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Net {{ $reportData['financials']['profit_status'] }}</dt>
                <dd class="mt-3 flex items-baseline gap-2">
                    <span class="text-4xl font-bold tracking-tighter text-gray-900 dark:text-white">
                        {{ number_format($reportData['financials']['net_profit'] ?? 0, 3) }}
                    </span>
                    <span class="text-sm font-medium text-gray-500">KD</span>
                </dd>
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] uppercase text-gray-400 font-bold">Revenue</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($reportData['financials']['total_revenue'] ?? 0, 0) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] uppercase text-gray-400 font-bold">Overhead</p>
                        <p class="text-sm font-semibold text-rose-500">-{{ number_format($reportData['financials']['staff_overhead'] ?? 0, 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- COGS Tile --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                <div class="flex justify-between items-start mb-2">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">COGS (Materials)</dt>
                    <span class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[10px] font-bold px-2 py-0.5 rounded">EXPENSE</span>
                </div>
                <dd class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ number_format($reportData['financials']['cost_of_goods'] ?? 0, 3) }}
                    </span>
                    <span class="text-xs font-medium text-gray-500">KD</span>
                </dd>
            </div>
        </div>

        {{-- WATERFALL CHART (Professional) --}}
        <div class="lg:col-span-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm"
             wire:key="waterfall-{{ $reportData['date'] }}"
             wire:ignore
             x-data="{
                init() {
                    if (typeof echarts === 'undefined') return;
                    let chart = echarts.init(this.$refs.waterfall);
                    
                    let isDark = document.documentElement.classList.contains('dark');
                    let textColor = isDark ? '#9ca3af' : '#4b5563';
                    let gridColor = isDark ? '#374151' : '#f3f4f6';
                    
                    let data = @js($reportData['financials']['chart_data'] ?? []);
                    if (data.length === 0) return;

                    let categories = data.map(i => i.name);
                    let values = data.map(i => i.value);

                    let option = {
                        title: { text: 'Profit & Loss Waterfall', left: '0%', textStyle: { fontSize: 14, fontWeight: 600, color: textColor } },
                        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, backgroundColor: isDark ? '#1f2937' : '#fff', borderColor: isDark ? '#374151' : '#e5e7eb', textStyle: { color: textColor } },
                        grid: { left: '2%', right: '2%', bottom: '5%', top: '15%', containLabel: true },
                        xAxis: { 
                            type: 'category', 
                            data: categories, 
                            axisLine: { show: false },
                            axisTick: { show: false },
                            axisLabel: { color: textColor, fontWeight: 500, fontSize: 11, interval: 0 } 
                        },
                        yAxis: { 
                            type: 'value', 
                            splitLine: { lineStyle: { type: 'dashed', color: gridColor } },
                            axisLabel: { color: textColor, fontSize: 11 } 
                        },
                        series: [{
                            name: 'Amount',
                            type: 'bar',
                            stack: 'Total',
                            label: { show: true, position: 'top', fontWeight: 600, fontSize: 11, color: textColor, formatter: '{c}' },
                            data: values,
                            barMaxWidth: 50,
                            itemStyle: {
                                borderRadius: [4, 4, 0, 0],
                                color: function(params) {
                                    let type = data[params.dataIndex].type;
                                    if(type === 'income') return '#10b981'; // Emerald-500
                                    if(type === 'expense') return '#ef4444'; // Red-500
                                    return '#3b82f6'; // Blue-500
                                }
                            }
                        }]
                    };
                    chart.setOption(option);
                    window.addEventListener('resize', () => chart.resize());
                }
             }"
        >
            <div x-ref="waterfall" class="h-[320px] w-full flex items-center justify-center text-gray-400 text-sm">
                @if(empty($reportData['financials']['chart_data']))
                    <span>No financial data available for this selection.</span>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- 4. DOCTOR PERSONAL DASHBOARD --}}
    @if(isset($reportData['financials']['commission_earned']))
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Commission Card --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-emerald-100 dark:border-emerald-900 p-6 shadow-sm relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-5">
                <svg class="w-20 h-20 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
            </div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Total Commission</h3>
            <p class="mt-2 text-4xl font-bold text-gray-900 dark:text-white">{{ number_format($reportData['financials']['commission_earned'], 3) }} <span class="text-lg text-gray-400 font-normal">KD</span></p>
            <p class="mt-2 text-xs text-gray-500">Calculated from finalized invoices.</p>
        </div>
        
        {{-- Production Card --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gross Production</h3>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($reportData['financials']['revenue_generated'], 3) }} KD</p>
            <div class="mt-4 w-full bg-gray-100 rounded-full h-1.5 dark:bg-gray-800">
                <div class="bg-indigo-500 h-1.5 rounded-full" style="width: 100%"></div>
            </div>
        </div>

        {{-- Volume Card --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Patient Visits</h3>
            <div class="flex items-center justify-between mt-2">
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $reportData['financials']['patients_seen'] }}</p>
                <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 px-2 py-1 rounded text-xs font-bold">TODAY</div>
            </div>
        </div>
    </div>
    @endif

    {{-- 5. OPERATIONAL METRICS (Donut Charts) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        {{-- Traffic Sources --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm"
             wire:key="sources-{{ $reportData['date'] }}"
             wire:ignore
             x-data="{
                init() {
                    if (typeof echarts === 'undefined') return;
                    let chart = echarts.init(this.$refs.sources);
                    let isDark = document.documentElement.classList.contains('dark');
                    let textColor = isDark ? '#9ca3af' : '#4b5563';
                    
                    let data = @js($reportData['bookings']['sources_chart'] ?? []);
                    
                    let option = {
                        title: { text: 'Booking Sources', left: 'left', textStyle: { fontSize: 14, fontWeight: 600, color: textColor } },
                        tooltip: { trigger: 'item', backgroundColor: isDark ? '#1f2937' : '#fff', textStyle: { color: textColor } },
                        legend: { bottom: '0%', icon: 'circle', itemGap: 20, textStyle: { color: textColor, fontSize: 11 } },
                        series: [{
                            name: 'Source',
                            type: 'pie',
                            radius: ['45%', '70%'],
                            center: ['50%', '45%'],
                            avoidLabelOverlap: false,
                            itemStyle: { borderRadius: 5, borderColor: isDark ? '#111827' : '#fff', borderWidth: 2 },
                            label: { show: false },
                            data: data,
                            color: ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e']
                        }]
                    };
                    chart.setOption(option);
                    window.addEventListener('resize', () => chart.resize());
                }
             }"
        >
            <div x-ref="sources" class="h-[300px] w-full flex items-center justify-center text-gray-400 text-sm">
                 @if(empty($reportData['bookings']['sources_chart']))
                    <div class="text-center">
                        <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>No booking activity recorded.</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Payment Methods --}}
        @if(isset($reportData['payments']))
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm"
             wire:key="payments-{{ $reportData['date'] }}"
             wire:ignore
             x-data="{
                init() {
                    if (typeof echarts === 'undefined') return;
                    let chart = echarts.init(this.$refs.payments);
                    let isDark = document.documentElement.classList.contains('dark');
                    let textColor = isDark ? '#9ca3af' : '#4b5563';
                    
                    let data = @js($reportData['payments']['methods_chart'] ?? []);
                    
                    let option = {
                        title: { 
                            text: 'Collection Methods', 
                            subtext: 'Total: {{ number_format($reportData['payments']['total_collected'] ?? 0, 3) }} KD',
                            left: 'left',
                            textStyle: { fontSize: 14, fontWeight: 600, color: textColor },
                            subtextStyle: { fontSize: 12, color: '#10b981', fontWeight: 600 }
                        },
                        tooltip: { trigger: 'item', backgroundColor: isDark ? '#1f2937' : '#fff', textStyle: { color: textColor } },
                        legend: { bottom: '0%', icon: 'circle', itemGap: 20, textStyle: { color: textColor, fontSize: 11 } },
                        series: [{
                            name: 'Method',
                            type: 'pie',
                            radius: ['45%', '70%'],
                            center: ['50%', '45%'],
                            itemStyle: { borderRadius: 5, borderColor: isDark ? '#111827' : '#fff', borderWidth: 2 },
                            label: { show: false },
                            data: data,
                            color: ['#10b981', '#3b82f6', '#f59e0b', '#6366f1'] 
                        }]
                    };
                    chart.setOption(option);
                    window.addEventListener('resize', () => chart.resize());
                }
             }"
        >
            <div x-ref="payments" class="h-[300px] w-full flex items-center justify-center text-gray-400 text-sm">
                @if(empty($reportData['payments']['methods_chart']))
                    <div class="text-center">
                        <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span>No payments recorded.</span>
                    </div>
                @endif
            </div>
        </div>
        @endif

    </div>

</div>