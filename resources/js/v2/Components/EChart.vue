<script setup>
/**
 * EChart — thin, theme-aware Apache ECharts wrapper for the v2 admin.
 *
 * - Tree-shaken core import (only the chart/component types the reports use).
 * - Reads the v2 design tokens (--primary, --success, …) off the live DOM so it
 *   automatically matches light/dark mode; re-themes when the `.dark` class flips.
 * - Adds a consistent toolbox (data view, zoom, line/bar switch, restore, save
 *   image) so every report chart is interactive — pass :toolbox="false" to hide.
 * - Auto-resizes via ResizeObserver. Pass a plain ECharts `option`; this wrapper
 *   only injects palette + axis/tooltip/toolbox defaults underneath it.
 */
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import * as echarts from 'echarts/core'
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import {
    GridComponent, TooltipComponent, LegendComponent,
    ToolboxComponent, DataZoomComponent, TitleComponent, MarkLineComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([
    BarChart, LineChart, PieChart,
    GridComponent, TooltipComponent, LegendComponent,
    ToolboxComponent, DataZoomComponent, TitleComponent, MarkLineComponent,
    CanvasRenderer,
])

const props = defineProps({
    option: { type: Object, required: true },
    height: { type: String, default: '280px' },
    toolbox: { type: Boolean, default: true },
    // toolbox feature labels (bilingual — passed from the page's `t`)
    labels: {
        type: Object,
        default: () => ({
            dataView: 'Data', zoom: 'Zoom', back: 'Reset', line: 'Line',
            bar: 'Bar', restore: 'Restore', save: 'Save', close: 'Close', refresh: 'Refresh',
        }),
    },
})

const el = ref(null)
let chart = null
let ro = null
let mo = null

const isObj = (v) => v && typeof v === 'object' && !Array.isArray(v)
function deepMerge(base, over) {
    const out = { ...base }
    for (const k of Object.keys(over || {})) {
        out[k] = isObj(base?.[k]) && isObj(over[k]) ? deepMerge(base[k], over[k]) : over[k]
    }
    return out
}

function cssVar(name, fallback) {
    try {
        const v = getComputedStyle(el.value).getPropertyValue(name).trim()
        return v || fallback
    } catch { return fallback }
}

function buildOption() {
    const c = {
        primary: cssVar('--primary', '#b8985a'),
        success: cssVar('--success', '#16a34a'),
        info: cssVar('--info', '#2563eb'),
        violet: cssVar('--violet', '#7c3aed'),
        warning: cssVar('--warning', '#d97706'),
        destructive: cssVar('--destructive', '#dc2626'),
        fg: cssVar('--fg', '#1f2937'),
        fgFaint: cssVar('--fg-faint', '#9ca3af'),
        line: cssVar('--line', '#e5e7eb'),
        bgElev: cssVar('--bg-elev', '#ffffff'),
    }
    const L = props.labels
    const base = {
        color: [c.primary, c.success, c.info, c.violet, c.warning, c.destructive],
        textStyle: { fontFamily: 'inherit', color: c.fgFaint },
        grid: { left: 6, right: 8, top: props.toolbox ? 30 : 14, bottom: 2, containLabel: true },
        tooltip: {
            backgroundColor: c.bgElev, borderColor: c.line, borderWidth: 1,
            textStyle: { color: c.fg, fontSize: 12 }, confine: true,
            axisPointer: { lineStyle: { color: c.line }, crossStyle: { color: c.line } },
        },
    }
    if (props.toolbox) {
        base.toolbox = {
            right: 6, top: 0, itemSize: 14, itemGap: 9,
            iconStyle: { borderColor: c.fgFaint },
            emphasis: { iconStyle: { borderColor: c.primary } },
            feature: {
                dataZoom: { yAxisIndex: 'none', title: { zoom: L.zoom, back: L.back } },
                magicType: { type: ['line', 'bar'], title: { line: L.line, bar: L.bar } },
                dataView: { readOnly: true, title: L.dataView, lang: [L.dataView, L.close, L.refresh], backgroundColor: c.bgElev, textColor: c.fg },
                restore: { title: L.restore },
                saveAsImage: { title: L.save, pixelRatio: 2, backgroundColor: c.bgElev },
            },
        }
    }
    if (props.option.legend) {
        base.legend = { textStyle: { color: c.fgFaint }, icon: 'circle', itemWidth: 8, itemHeight: 8, itemGap: 14 }
    }
    if (props.option.xAxis) {
        base.xAxis = {
            axisLine: { lineStyle: { color: c.line } }, axisTick: { show: false },
            axisLabel: { color: c.fgFaint, fontSize: 11 }, splitLine: { show: false },
        }
    }
    if (props.option.yAxis) {
        base.yAxis = {
            axisLine: { show: false }, axisTick: { show: false },
            axisLabel: { color: c.fgFaint, fontSize: 11 },
            splitLine: { lineStyle: { color: c.line, type: 'dashed' } },
        }
    }
    return deepMerge(base, props.option)
}

function render() {
    if (!chart) return
    chart.setOption(buildOption(), { notMerge: true })
}

onMounted(() => {
    chart = echarts.init(el.value, null, { renderer: 'canvas' })
    render()
    ro = new ResizeObserver(() => chart && chart.resize())
    ro.observe(el.value)
    // Re-theme when the app toggles the `.dark` class on <html>.
    mo = new MutationObserver(() => render())
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
})

watch(() => props.option, render, { deep: true })

onBeforeUnmount(() => {
    if (ro) ro.disconnect()
    if (mo) mo.disconnect()
    if (chart) { chart.dispose(); chart = null }
})
</script>

<template>
    <div ref="el" :style="{ width: '100%', height }"></div>
</template>
