<template>
    <div class="flex flex-col gap-4 max-w-2xl mx-auto">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-[22px] font-bold tracking-tight">What if</h1>
            <button v-if="active" class="w-btn rounded-full" @click="reset">Reset</button>
        </div>
        <p class="text-sm text-muted -mt-2">Try a different house value or overpayment. Nothing entered here is saved.</p>

        <p v-if="!mortgage" class="w-card p-5 text-sm text-muted">
            Save your mortgage details on the Mortgage tab first, then come back to try scenarios.
        </p>

        <template v-else>
            <section class="w-card p-4 md:p-5 flex flex-col gap-3.5">
                <label v-if="property" class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                    {{ property.description }} value
                    <span class="w-field"><span>£</span><input v-model="input.house" type="number" inputmode="numeric" min="0" step="1000" :placeholder="plain(property.current_amount)" /></span>
                </label>
                <div class="grid grid-cols-2 gap-2.5">
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        Monthly overpayment
                        <span class="w-field"><span>£</span><input v-model="input.overpayment" type="number" inputmode="numeric" min="0" step="25" :placeholder="plain(mortgage.record.regular_overpayment)" /></span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        Lump sum now
                        <span class="w-field"><span>£</span><input v-model="input.lump" type="number" inputmode="numeric" min="0" step="500" placeholder="0" /></span>
                    </label>
                </div>
            </section>

            <section v-if="houseResult" class="w-card p-4 md:p-5 grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs text-muted">Equity</span>
                    <span class="text-lg font-bold">{{ money(houseResult.equity) }}</span>
                    <span class="text-xs" :class="tone(houseResult.equityChange)">{{ signedMoney(houseResult.equityChange) }}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs text-muted">Loan to value</span>
                    <span class="text-lg font-bold">{{ houseResult.ltv }}%</span>
                    <span class="text-xs text-muted">now {{ houseResult.ltvNow }}%</span>
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs text-muted">Net worth</span>
                    <span class="text-lg font-bold text-brand">{{ money(houseResult.netWorth) }}</span>
                    <span class="text-xs" :class="tone(houseResult.netWorthChange)">{{ signedMoney(houseResult.netWorthChange) }}</span>
                </div>
            </section>

            <section v-if="forecast" class="w-card p-4 md:p-5 flex flex-col gap-3.5">
                <div class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted">Paid off</span>
                        <span class="text-lg font-bold" :class="forecast.payoff_date ? '' : 'text-down'">{{ forecast.payoff_date ? monthYear(forecast.payoff_date) : 'Never' }}</span>
                        <span class="text-xs" :class="tone(forecast.months_sooner)">{{ soonerLabel }}</span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted">Interest to pay</span>
                        <span class="text-lg font-bold">{{ money(forecast.interest_remaining) }}</span>
                        <span class="text-xs" :class="tone(forecast.interest_saved)">{{ interestLabel }}</span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted">Time left</span>
                        <span class="text-lg font-bold">{{ duration(forecast.months_remaining) }}</span>
                        <span class="text-xs text-muted">now {{ duration(mortgage.forecast.months_remaining) }}</span>
                    </div>
                </div>
                <ApexChart :key="chartKey" type="line" height="220" :options="chartOptions" :series="chartSeries" />
            </section>

            <p v-if="!active" class="text-sm text-muted text-center py-6">
                Today: paid off {{ mortgage.forecast.payoff_date ? monthYear(mortgage.forecast.payoff_date) : 'never' }}, with {{ money(mortgage.forecast.interest_remaining) }} of interest still to pay.
            </p>
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import ApexChart from 'vue3-apexcharts'
import axios from 'axios'
import { state } from './store'
import { chartBase, duration, money, monthYear, signedMoney } from './format'

const mortgage = computed(() =>
    state.liabilities.find(l => l.type === 'mortgage' && l.forecast) ?? state.liabilities.find(l => l.forecast) ?? null
)
const property = computed(() => state.sources.find(s => s.id === mortgage.value?.property_source_id) ?? null)

const input = reactive({ house: '', overpayment: '', lump: '' })
const entered = value => value !== '' && value !== null && !Number.isNaN(Number(value))
const active = computed(() => entered(input.house) || entered(input.overpayment) || Number(input.lump) > 0)

const plain = value => new Intl.NumberFormat('en-GB').format(value)
const tone = value => (value > 0 ? 'text-up' : value < 0 ? 'text-down' : 'text-muted')

function reset() {
    Object.assign(input, { house: '', overpayment: '', lump: '' })
}

// --- House value: arithmetic on what is already loaded ---

const houseResult = computed(() => {
    if (!property.value || !entered(input.house)) return null

    const value = Number(input.house)
    const owedNow = mortgage.value.balance_today
    const owed = Math.max(0, owedNow - (Number(input.lump) || 0))
    const current = property.value.current_amount

    return {
        equity: value - owed,
        equityChange: value - owed - (current - owedNow),
        ltv: value > 0 ? (owed / value * 100).toFixed(1) : '—',
        ltvNow: current > 0 ? (owedNow / current * 100).toFixed(1) : '—',
        // A lump sum moves money from savings to the loan, so only the valuation changes net worth.
        netWorth: state.summary.net_worth + value - current,
        netWorthChange: value - current,
    }
})

// --- Overpayments: forecast from the server, never saved ---

const forecast = ref(null)
const chartVersion = ref(0)
let timer = null
let request = 0

async function load() {
    const id = ++request
    let result = null

    if (mortgage.value && (entered(input.overpayment) || Number(input.lump) > 0)) {
        try {
            const res = await axios.post(`/wealth/liabilities/${mortgage.value.id}/what-if`, {
                regular_overpayment: entered(input.overpayment) ? input.overpayment : null,
                lump_sum: Number(input.lump) > 0 ? input.lump : null,
            })
            result = res.data.what_if
        } catch {
            result = null
        }
    }

    if (id === request) {
        forecast.value = result
        chartVersion.value++
    }
}

watch([() => input.overpayment, () => input.lump, () => state.version], () => {
    clearTimeout(timer)
    timer = setTimeout(load, 250)
})

const soonerLabel = computed(() => {
    const months = forecast.value.months_sooner
    if (months === null) return 'payment below interest'
    if (months === 0) return 'no change'
    return `${duration(Math.abs(months))} ${months > 0 ? 'sooner' : 'later'}`
})

const interestLabel = computed(() => {
    const saved = forecast.value.interest_saved
    if (saved === null) return ''
    if (Math.round(saved) === 0) return 'no change'
    return `${saved > 0 ? 'saves' : 'costs'} ${money(Math.abs(saved))}`
})

const toPoints = schedule => schedule.map(([date, balance]) => ({ x: new Date(date).getTime(), y: balance }))
const chartKey = computed(() => `what-if-${state.version}-${chartVersion.value}`)

const chartSeries = computed(() => [
    { name: 'What if', data: toPoints(forecast.value.schedule) },
    { name: 'As it stands', data: toPoints(mortgage.value.schedule) },
])

const chartOptions = computed(() => {
    const base = chartBase('line')
    return {
        ...base,
        colors: ['#f26b21', '#2447d8'],
        stroke: { curve: 'straight', width: [2.5, 2], dashArray: [0, 5] },
        yaxis: { ...base.yaxis, min: 0 },
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', labels: { colors: '#5b6472' } },
        tooltip: { x: { format: 'MMM yyyy' }, y: { formatter: val => money(Math.round(val)) } },
    }
})
</script>
