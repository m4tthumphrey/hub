<template>
    <div class="min-h-screen bg-gray-950 text-gray-100">

        <header class="border-b border-gray-800 px-6 py-4">
            <h1 class="text-lg font-semibold tracking-tight text-white">Wealth Dashboard</h1>
        </header>

        <div v-if="loading" class="flex items-center justify-center min-h-[calc(100vh-57px)]">
            <span class="text-gray-600 text-sm">Loading…</span>
        </div>

        <main v-else class="p-6 space-y-6">

            <!-- Summary cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:flex lg:flex-wrap">
                <div
                    v-for="total in totals"
                    :key="total.name"
                    class="bg-gray-900 border border-gray-800 rounded-xl px-5 py-4 lg:flex-1"
                    :class="total.name === 'Total' ? 'border-indigo-900/60 bg-indigo-950/40' : ''"
                >
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">{{ total.name }}</div>
                    <div
                        class="font-bold tabular-nums"
                        :class="total.name === 'Total' ? 'text-2xl text-indigo-300' : 'text-xl text-white'"
                    >
                        £{{ formatAmount(total.total) }}
                    </div>
                </div>
            </div>

            <!-- Property, liabilities and net worth -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="bg-gray-900 border border-gray-800 rounded-xl px-5 py-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Property</div>
                    <div class="text-xl font-bold tabular-nums text-white">£{{ formatAmount(summary.property) }}</div>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-xl px-5 py-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Liabilities</div>
                    <div class="text-xl font-bold tabular-nums" :class="summary.liabilities > 0 ? 'text-rose-400' : 'text-white'">
                        {{ summary.liabilities > 0 ? '−' : '' }}£{{ formatAmount(summary.liabilities) }}
                    </div>
                </div>
                <div class="border border-indigo-900/60 bg-indigo-950/40 rounded-xl px-5 py-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Net Worth</div>
                    <div class="text-2xl font-bold tabular-nums text-indigo-300">£{{ formatAmount(summary.net_worth) }}</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Total wealth over time -->
                <div class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-xl p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ selectedCategory ? selectedCategory : chartViews[chartView] }}
                        </h2>
                        <div v-if="!selectedCategory" class="inline-flex rounded-lg border border-gray-700 overflow-hidden text-xs">
                            <button
                                v-for="(label, view) in chartViewLabels"
                                :key="view"
                                @click="chartView = view"
                                class="px-3 py-1 transition-colors"
                                :class="chartView === view ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-gray-200'"
                            >
                                {{ label }}
                            </button>
                        </div>
                        <button
                            v-if="selectedCategory"
                            @click="selectedCategory = null"
                            class="text-xs text-gray-600 hover:text-gray-400 transition-colors"
                        >
                            ✕ clear
                        </button>
                    </div>
                    <ApexChart
                        v-if="areaChartSeries[0].data.length > 1"
                        :key="`area-${chartVersion}-${selectedCategory ?? chartView}`"
                        type="area"
                        height="220"
                        :options="areaChartOptions"
                        :series="areaChartSeries"
                    />
                    <div v-else class="flex items-center justify-center h-[220px] text-gray-700 text-sm">
                        Not enough history yet
                    </div>
                </div>

                <!-- Category breakdown -->
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                    <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Current Breakdown</h2>
                    <ApexChart
                        v-if="donutSeries.length"
                        :key="`donut-${chartVersion}`"
                        type="donut"
                        height="220"
                        :options="donutChartOptions"
                        :series="donutSeries"
                    />
                </div>

            </div>

            <!-- Sources table -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-800">
                    <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider">Sources</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs font-medium text-gray-600 uppercase tracking-wider">
                                <th class="text-left px-5 py-3">Who</th>
                                <th class="text-left px-5 py-3">Description</th>
                                <th class="text-right px-5 py-3">Regular</th>
                                <th class="text-right px-5 py-3">Current</th>
                                <th class="px-5 py-3 w-36">Trend</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60">
                            <tr
                                v-for="source in liquidSources"
                                :key="source.id"
                                class="hover:bg-gray-800/40 transition-colors"
                            >
                                <td class="px-5 py-3 text-gray-200 font-medium">{{ source.who }}</td>
                                <td class="px-5 py-3 text-gray-400">{{ source.description }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <span class="text-gray-600 text-xs">£</span>
                                        <input
                                            type="number"
                                            :value="source.regular_amount"
                                            @change="updateField(source.id, 'regular', $event.target.value)"
                                            class="w-24 bg-gray-800 border border-gray-700/50 rounded-lg px-2 py-1.5 text-right text-gray-200 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-colors"
                                        />
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <span class="text-gray-600 text-xs">£</span>
                                        <input
                                            type="number"
                                            :value="source.current_amount"
                                            @change="updateField(source.id, 'current', $event.target.value)"
                                            class="w-24 bg-gray-800 border border-gray-700/50 rounded-lg px-2 py-1.5 text-right text-gray-200 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-colors"
                                        />
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <ApexChart
                                            v-if="sparklineData[source.id]?.length >= 1"
                                            :key="`spark-${source.id}-${chartVersion}`"
                                            type="line"
                                            height="36"
                                            width="100"
                                            :options="sparklineOptions(source.id, source.current_amount)"
                                            :series="[{ data: sparklineData[source.id] }]"
                                        />
                                        <span
                                            class="text-xs tabular-nums"
                                            :class="sourceTrend(source.id, source.current_amount) === 'up' ? 'text-emerald-400' : sourceTrend(source.id, source.current_amount) === 'down' ? 'text-rose-400' : 'text-gray-500'"
                                        >
                                            {{ sourceTrend(source.id, source.current_amount) === 'up' ? '▲' : sourceTrend(source.id, source.current_amount) === 'down' ? '▼' : '' }}
                                            £{{ formatAmount(source.current_amount) }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <LiabilitiesPanel
                :properties="propertySources"
                :net-worth="summary.net_worth"
                @update-source="(id, value) => updateField(id, 'current', value)"
                @changed="refresh"
            />

        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import ApexChart from 'vue3-apexcharts'
import axios from 'axios'
import LiabilitiesPanel from './LiabilitiesPanel.vue'

const sources = ref([])
const totals = ref([])
const summary = ref({ property: 0, liabilities: 0, net_worth: 0 })
const history = ref({ total_over_time: [], by_source: [], liabilities_over_time: [], net_worth_over_time: [] })

// The house lives in a non-liquid category: it is edited in the property panel, not the sources table.
const liquidSources = computed(() => sources.value.filter(s => s.is_liquid))
const propertySources = computed(() => sources.value.filter(s => !s.is_liquid))

const chartView = ref('liquid')
const chartViewLabels = { liquid: 'Liquid', net: 'Net worth', liabilities: 'Liabilities' }
const chartViews = { liquid: 'Total Wealth Over Time', net: 'Net Worth Over Time', liabilities: 'Liabilities Over Time' }
const loading = ref(true)
const chartVersion = ref(0)
const selectedCategory = ref(null)

function formatAmount(val) {
    return new Intl.NumberFormat('en-GB').format(val)
}

// --- History helpers ---

const sparklineData = computed(() => {
    const map = {}
    for (const entry of history.value.by_source) {
        if (!map[entry.id]) map[entry.id] = []
        map[entry.id].push(entry.value)
    }
    return map
})

function sourceTrend(id, currentAmount) {
    const vals = sparklineData.value[id]
    if (!vals || !vals.length) return null
    const last = vals[vals.length - 1]
    const compare = currentAmount !== last ? currentAmount : (vals.length > 1 ? vals[vals.length - 2] : null)
    if (compare === null) return null
    return currentAmount > compare ? 'up' : currentAmount < compare ? 'down' : null
}

// --- Chart configs ---

const CHART_COLORS = ['#6366f1', '#22d3ee', '#a78bfa', '#34d399', '#f59e0b', '#f87171']

const areaChartSeries = computed(() => {
    if (!selectedCategory.value) {
        const series = {
            liquid: history.value.total_over_time,
            net: history.value.net_worth_over_time,
            liabilities: history.value.liabilities_over_time,
        }[chartView.value]

        return [{
            name: chartViews[chartView.value].replace(' Over Time', ''),
            data: series.map(d => ({
                x: new Date(d.date).getTime(),
                y: d.total,
            })),
        }]
    }

    const sourceIds = new Set(
        sources.value
            .filter(s => s.category_name === selectedCategory.value)
            .map(s => s.id)
    )

    const byDate = {}
    for (const entry of history.value.by_source) {
        if (sourceIds.has(entry.id)) {
            byDate[entry.date] = (byDate[entry.date] || 0) + entry.value
        }
    }

    const data = Object.entries(byDate)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([date, total]) => ({ x: new Date(date).getTime(), y: total }))

    return [{ name: selectedCategory.value, data }]
})

const areaChartOptions = {
    chart: {
        type: 'area',
        background: 'transparent',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
        animations: { easing: 'easeinout', speed: 600 },
    },
    theme: { mode: 'dark' },
    colors: ['#6366f1'],
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0, stops: [0, 100] },
    },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: {
        type: 'datetime',
        labels: { style: { colors: '#4b5563' }, datetimeUTC: false },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { colors: '#4b5563' },
            formatter: val => '£' + formatAmount(val),
        },
    },
    grid: { borderColor: '#1f2937', strokeDashArray: 4, padding: { left: 8, right: 8 } },
    tooltip: {
        theme: 'dark',
        x: { format: 'dd MMM yyyy' },
        y: { formatter: val => '£' + formatAmount(val) },
    },
    dataLabels: { enabled: false },
}

const donutSeries = computed(() =>
    totals.value.filter(t => t.name !== 'Total').map(t => Number(t.total))
)

function handleDonutClick(event, chartContext, config) {
    const labels = totals.value.filter(t => t.name !== 'Total').map(t => t.name)
    const label = labels[config.dataPointIndex]
    if (!label) return
    selectedCategory.value = selectedCategory.value === label ? null : label
}

const donutChartOptions = computed(() => ({
    chart: {
        type: 'donut',
        background: 'transparent',
        fontFamily: 'inherit',
        animations: { easing: 'easeinout', speed: 600 },
        events: { dataPointSelection: handleDonutClick },
    },
    theme: { mode: 'dark' },
    colors: CHART_COLORS,
    labels: totals.value.filter(t => t.name !== 'Total').map(t => t.name),
    legend: {
        position: 'bottom',
        labels: { colors: '#6b7280' },
        fontSize: '11px',
    },
    tooltip: {
        theme: 'dark',
        y: { formatter: val => '£' + formatAmount(val) },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        color: '#6b7280',
                        fontSize: '12px',
                        formatter: w => '£' + formatAmount(
                            w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                        ),
                    },
                    value: {
                        color: '#f3f4f6',
                        fontSize: '18px',
                        fontWeight: 700,
                        formatter: val => '£' + formatAmount(val),
                    },
                },
            },
        },
    },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
}))

function sparklineOptions(sourceId, currentAmount) {
    const trend = sourceTrend(sourceId, currentAmount)
    const color = trend === 'up' ? '#34d399' : trend === 'down' ? '#f87171' : '#6366f1'
    return {
        chart: {
            type: 'line',
            sparkline: { enabled: true },
            background: 'transparent',
            fontFamily: 'inherit',
            animations: { enabled: false },
        },
        theme: { mode: 'dark' },
        colors: [color],
        stroke: { curve: 'smooth', width: 1.5 },
        tooltip: {
            fixed: { enabled: false },
            theme: 'dark',
            y: { formatter: val => '£' + formatAmount(val) },
            marker: { show: false },
            x: { show: false },
        },
    }
}

// --- Actions ---

async function refresh() {
    const [dataRes, histRes] = await Promise.all([
        axios.get('/wealth/data'),
        axios.get('/wealth/history'),
    ])
    sources.value = dataRes.data.sources
    totals.value = dataRes.data.totals
    summary.value = dataRes.data.summary
    history.value = histRes.data
    chartVersion.value++
}

async function updateField(id, name, value) {
    await axios.post('/wealth/update', { id, name, value })
    const source = sources.value.find(s => s.id === id)
    if (name === 'current') {
        await refresh()
    } else {
        source.regular_amount = parseInt(value)
    }
}

onMounted(async () => {
    await refresh()
    loading.value = false
})
</script>
