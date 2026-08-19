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
    // Validated categorical palette (dataviz skill): harmonious + colour-blind
    // safe, selected per theme (not an auto-flip). Adjacent slots clear the CVD
    // and normal-vision floors in both light and dark. Kept identical to the
    // reference instance so it stays validated.
    const isDark = document.documentElement.classList.contains('dark')
    const palette = isDark
        ? ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767']
        : ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948']
    const base = {
        color: palette,
        textStyle: { fontFamily: 'inherit', color: c.fgFaint },
        grid: { left: 6, right: 8, top: props.toolbox ? 30 : 14, bottom: 2, containLabel: true },
        tooltip: {
            backgroundColor: c.bgElev, borderColor: c.line, borderWidth: 1,
            textStyle: { color: c.fg, fontSize: 12 }, confine: true,
            // Kill the tooltip's follow-animation + axis-pointer animation — the
            // main source of "flicker" as the cursor moves across the chart.
            transitionDuration: 0,
            axisPointer: {
                animation: false,
                lineStyle: { color: c.line }, crossStyle: { color: c.line },
            },
        },
    }
    if (props.toolbox) {
        // Zoom + line/bar switch only make sense on cartesian charts (x/y axes).
        // On a pie/donut they do nothing (the mark stays a circle), so omit them
        // there and keep just data-view / restore / save.
        const isCartesian = !!(props.option.xAxis || props.option.yAxis)
        const feature = {
            dataView: { readOnly: true, title: L.dataView, lang: [L.dataView, L.close, L.refresh], backgroundColor: c.bgElev, textColor: c.fg },
            restore: { title: L.restore },
            saveAsImage: { title: L.save, pixelRatio: 2, backgroundColor: c.bgElev },
        }
        if (isCartesian) {
            feature.dataZoom = { yAxisIndex: 'none', title: { zoom: L.zoom, back: L.back } }
            feature.magicType = { type: ['line', 'bar'], title: { line: L.line, bar: L.bar } }
        }
        base.toolbox = {
            right: 6, top: 0, itemSize: 14, itemGap: 9,
            iconStyle: { borderColor: c.fgFaint },
            emphasis: { iconStyle: { borderColor: c.primary } },
            feature,
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
    chart.setOption(buildOption(), { notMerge: true, lazyUpdate: true })
}

let lastW = 0
let lastH = 0
let rafId = null

onMounted(() => {
    chart = echarts.init(el.value, null, { renderer: 'canvas' })
    render()
    lastW = el.value.clientWidth
    lastH = el.value.clientHeight

    // Resize only on a real size change, coalesced to one frame. A bare
    // resize()-on-every-callback re-renders the canvas mid-hover → flicker.
    ro = new ResizeObserver(() => {
        if (!chart || !el.value) return
        const w = el.value.clientWidth
        const h = el.value.clientHeight
        if (w === 0 || h === 0 || (w === lastW && h === lastH)) return
        lastW = w
        lastH = h
        if (rafId) cancelAnimationFrame(rafId)
        rafId = requestAnimationFrame(() => chart && chart.resize())
    })
    ro.observe(el.value)

    // Re-theme ONLY when dark mode actually flips — not on every unrelated
    // class mutation on <html> (which would re-init the canvas on hover).
    let wasDark = document.documentElement.classList.contains('dark')
    mo = new MutationObserver(() => {
        const isDark = document.documentElement.classList.contains('dark')
        if (isDark !== wasDark) {
            wasDark = isDark
            render()
        }
    })
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
})

watch(() => props.option, render, { deep: true })

onBeforeUnmount(() => {
    if (rafId) cancelAnimationFrame(rafId)
    if (ro) ro.disconnect()
    if (mo) mo.disconnect()
    if (chart) { chart.dispose(); chart = null }
})
</script>

<template>
    <div ref="el" :style="{ width: '100%', height }"></div>
</template>
