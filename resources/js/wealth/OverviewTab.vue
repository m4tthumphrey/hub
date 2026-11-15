<template>
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:gap-5">

        <div class="flex flex-col gap-4 md:gap-5 md:flex-1 md:basis-[360px] min-w-0">
            <h1 class="md:hidden flex items-baseline justify-between">
                <span class="text-[22px] font-bold tracking-tight">Wealth</span>
                <span class="text-[13px] font-normal text-muted">{{ today }}</span>
            </h1>

            <!-- Own, owe, net -->
            <section class="w-card px-4 py-[18px] md:p-5 flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <div class="flex items-baseline justify-between">
                        <span class="text-[13px] font-semibold text-muted">You own</span>
                        <span class="text-lg font-bold">{{ money(own) }}</span>
                    </div>
                    <div class="h-[18px] flex gap-0.5 rounded-full overflow-hidden" role="img" :aria-label="ownLabel">
                        <div
                            v-for="segment in segments"
                            :key="segment.name"
                            :style="{ width: segment.share + '%', background: segment.color }"
                        ></div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px]">
                        <span v-for="segment in segments" :key="segment.name" class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-[3px] shrink-0" :style="{ background: segment.color }"></span>
                            <span class="flex-1 truncate text-gray-700">{{ segment.name }}</span>
                            <span class="font-semibold">{{ money(segment.total) }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex items-baseline justify-between">
                        <span class="text-[13px] font-semibold text-muted">You owe</span>
                        <span class="text-lg font-bold" :class="state.summary.liabilities > 0 ? 'text-owe-ink' : ''">{{ money(state.summary.liabilities) }}</span>
                    </div>
                    <div class="h-[18px] rounded-full bg-[#eceef3] flex" role="img" :aria-label="`${owePercent.toFixed(0)}% of what you own`">
                        <div class="rounded-full bg-owe" :style="{ width: owePercent + '%' }"></div>
                    </div>
                    <span class="text-[13px] text-muted">{{ oweNote }}</span>
                </div>

                <div class="border-t border-line pt-3.5 flex flex-wrap items-end justify-between gap-x-3 gap-y-1">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Net worth</span>
                        <span class="text-[38px] md:text-[40px] font-bold tracking-tight leading-[1.05]">{{ money(state.summary.net_worth) }}</span>
                    </div>
                    <span v-if="monthChange !== null" class="text-[13px] font-semibold pb-1.5" :class="monthChange >= 0 ? 'text-up' : 'text-down'">
                        {{ monthChange >= 0 ? '▲' : '▼' }} {{ money(Math.abs(monthChange)) }} this month
                    </span>
                </div>
            </section>

            <!-- Latest changes -->
            <section v-if="state.changes.length" class="flex flex-col gap-2">
                <h2 class="w-label">Latest changes</h2>
                <ul class="w-card divide-y divide-line">
                    <li v-for="(change, index) in state.changes" :key="index" class="min-h-[60px] px-4 py-2.5 flex items-center gap-3">
                        <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                            <span class="text-[15px] font-medium truncate">{{ change.description }}</span>
                            <span class="text-xs text-muted">{{ originLabel[change.origin] ?? 'Updated' }} · {{ dayLabel(change.date) }}</span>
                        </span>
                        <span class="flex flex-col items-end gap-0.5">
                            <span class="text-[15px] font-semibold">{{ money(change.value) }}</span>
                            <span v-if="change.previous !== null" class="text-xs" :class="changeClass(change)">
                                {{ signedMoney(change.value - change.previous) }}
                            </span>
                        </span>
                    </li>
                </ul>
            </section>
        </div>

        <div class="flex flex-col gap-4 md:gap-5 md:flex-[2] md:basis-[560px] min-w-0">

            <!-- History chart -->
            <section class="w-card p-4 md:p-5 flex flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="hidden md:block text-base font-bold">{{ views[view] }} over time</h2>
                    <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-canvas w-full md:w-80">
                        <button
                            v-for="(label, key) in views"
                            :key="key"
                            class="h-11 md:h-10 rounded-[9px] text-[13px] font-semibold"
                            :class="view === key ? 'bg-ink text-white' : 'text-muted'"
                            :aria-pressed="view === key"
                            @click="view = key"
                        >{{ label }}</button>
                    </div>
                </div>
                <ApexChart
                    v-if="series[0].data.length > 1"
                    :key="`${view}-${state.version}`"
                    type="area"
                    :height="chartHeight"
                    :options="chartOptions"
                    :series="series"
                />
                <p v-else class="h-40 flex items-center justify-center text-sm text-muted text-center">
                    Not enough history yet. This fills in as values are logged.
                </p>
            </section>

            <!-- Mortgage summary -->
            <a v-if="mortgage?.forecast" href="#mortgage" class="w-card px-4 py-3.5 md:px-5 md:py-[18px] flex flex-wrap items-center gap-x-8 gap-y-3 text-ink">
                <span class="flex-1 basis-40 flex flex-col gap-0.5">
                    <span class="text-[13px] font-semibold text-muted">{{ mortgage.description }}</span>
                    <span class="text-2xl font-bold text-owe-ink">{{ money(mortgage.balance_today) }}</span>
                </span>
                <span class="flex flex-col gap-0.5"><span class="text-xs text-muted">Monthly</span><span class="text-base font-bold">{{ money(mortgage.forecast.monthly_payment + mortgage.record.regular_overpayment) }}</span></span>
                <span v-if="mortgage.record.fix_end_date" class="flex flex-col gap-0.5"><span class="text-xs text-muted">Fix ends</span><span class="text-base font-bold">{{ monthYear(mortgage.record.fix_end_date) }}</span></span>
                <span class="flex flex-col gap-0.5"><span class="text-xs text-muted">Paid off</span><span class="text-base font-bold text-up">{{ mortgage.forecast.payoff_date ? monthYear(mortgage.forecast.payoff_date) : 'Never' }}</span></span>
            </a>
            <a v-else-if="mortgage" href="#mortgage" class="w-card px-4 py-4 text-sm text-ink">
                <span class="font-semibold">Add your mortgage details</span>
                <span class="block text-muted mt-0.5">Enter the loan, rate and term to see it here and in your net worth.</span>
            </a>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import ApexChart from 'vue3-apexcharts'
import { state } from './store'
import { chartBase, dayLabel, money, monthYear, signedMoney } from './format'

const today = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long' })

// --- Own / owe bars ---

// Lightest for the smallest liquid category up to the brand blue; property is always the darkest.
const SHADES = ['#c9d5fa', '#a9bbf7', '#6a88ee', '#2447d8']
const PROPERTY_SHADE = '#14297f'

const liquidTotal = computed(() => Number(state.totals.find(t => t.name === 'Total')?.total ?? 0))
const own = computed(() => liquidTotal.value + state.summary.property)

const segments = computed(() => {
    const categories = state.totals
        .filter(t => t.name !== 'Total')
        .map(t => ({ name: t.name, total: Number(t.total) }))
        .sort((a, b) => a.total - b.total)

    const list = categories.map((category, index) => ({
        ...category,
        color: SHADES[Math.max(0, SHADES.length - categories.length + index)],
    }))

    if (state.summary.property > 0) {
        list.push({ name: 'Property', total: state.summary.property, color: PROPERTY_SHADE })
    }

    return list.map(segment => ({ ...segment, share: own.value > 0 ? segment.total / own.value * 100 : 0 }))
})

const ownLabel = computed(() => segments.value.map(s => `${s.name} ${s.share.toFixed(0)}%`).join(', '))
const owePercent = computed(() => (own.value > 0 ? Math.min(100, state.summary.liabilities / own.value * 100) : 0))

const mortgage = computed(() => state.liabilities.find(l => l.type === 'mortgage') ?? null)

const oweNote = computed(() => {
    const debts = state.liabilities.filter(l => l.forecast && l.balance_today > 0)
    if (!debts.length) return 'Nothing owed'
    const payoff = mortgage.value?.forecast?.payoff_date
    const names = debts.map(d => d.description).join(', ')
    return payoff ? `${names} · paid off ${monthYear(payoff)}` : names
})

// Change since the last net worth figure from before this month.
const monthChange = computed(() => {
    const now = new Date()
    const monthStart = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
    const earlier = state.history.net_worth_over_time.filter(point => point.date < monthStart)
    return earlier.length ? state.summary.net_worth - earlier[earlier.length - 1].total : null
})

// --- Latest changes ---

const originLabel = { screenshot: 'Screenshot', manual: 'Typed', auto: 'Logged automatically', entered: 'Entered' }

// A falling debt is good news; a falling account is not.
function changeClass(change) {
    const difference = change.value - change.previous
    if (difference === 0) return 'text-muted'
    return (difference > 0) !== change.liability ? 'text-up' : 'text-down'
}

// --- Chart ---

const views = { net: 'Net worth', liquid: 'Liquid', liabilities: 'Liabilities' }
const view = ref('net')
const chartHeight = window.matchMedia('(min-width: 768px)').matches ? 320 : 180

const series = computed(() => {
    const points = {
        net: state.history.net_worth_over_time,
        liquid: state.history.total_over_time,
        liabilities: state.history.liabilities_over_time,
    }[view.value]

    return [{ name: views[view.value], data: points.map(p => ({ x: new Date(p.date).getTime(), y: Number(p.total) })) }]
})

const chartOptions = computed(() => ({
    ...chartBase('area'),
    colors: [view.value === 'liabilities' ? '#f26b21' : '#2447d8'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.18, opacityTo: 0, stops: [0, 100] } },
    stroke: { curve: 'smooth', width: 2.5 },
    tooltip: { x: { format: 'dd MMM yyyy' }, y: { formatter: val => money(Math.round(val)) } },
}))
</script>
