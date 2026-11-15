<template>
    <div class="flex flex-col gap-4">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-[22px] font-bold tracking-tight">{{ selected?.description ?? 'Mortgage' }}</h1>
            <button v-if="selected" class="w-btn rounded-full" @click="openForm(selected)">
                {{ selected.record ? 'Update details' : 'Enter details' }}
            </button>
        </div>

        <div class="flex gap-2 overflow-x-auto -mx-4 px-4 md:mx-0 md:px-0">
            <button
                v-for="liability in state.liabilities"
                :key="liability.id"
                class="w-chip"
                :class="{ 'w-chip-on': liability.id === selected?.id }"
                :aria-pressed="liability.id === selected?.id"
                @click="selectedId = liability.id"
            >{{ liability.description }}</button>
            <button class="w-chip border-dashed text-muted" @click="openForm(null)">+ Add debt</button>
        </div>

        <p v-if="selected && !selected.forecast" class="w-card p-5 text-sm text-muted">
            No details saved yet. Enter the loan remaining, rate and term to see the monthly payment, the payoff date and a monthly balance log.
        </p>

        <div v-if="selected?.forecast" class="flex flex-col gap-4 md:grid md:grid-cols-2 md:gap-5 md:items-start">

            <div class="flex flex-col gap-4 md:gap-5 min-w-0">

                <!-- Summary -->
                <section class="w-card px-4 py-[18px] md:p-5 flex flex-col gap-4">
                    <div class="flex items-end justify-between gap-3">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ selected.type === 'mortgage' ? 'Loan remaining' : 'Owed' }}</span>
                            <span class="text-4xl font-bold tracking-tight leading-[1.05]">{{ money(selected.balance_today) }}</span>
                        </div>
                        <span class="text-[13px] text-muted pb-1">projected today</span>
                    </div>

                    <div v-if="property" class="flex flex-col gap-1.5">
                        <div class="h-3.5 flex gap-0.5 rounded-full overflow-hidden" role="img" :aria-label="`${loanToValue}% of the ${property.description.toLowerCase()} value is owed`">
                            <div class="flex-1 bg-[#14297f]"></div>
                            <div class="bg-owe" :style="{ width: Math.min(100, loanToValue) + '%' }"></div>
                        </div>
                        <div class="flex justify-between text-[13px]">
                            <span class="text-gray-700">Equity {{ money(property.current_amount - selected.balance_today) }}</span>
                            <span class="text-owe-ink">{{ loanToValue }}% of {{ money(property.current_amount) }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 border-t border-line pt-3.5">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted">Monthly</span>
                            <span class="text-base font-bold">{{ money(selected.forecast.monthly_payment, 2) }}</span>
                            <span class="text-xs text-muted">{{ selected.record.regular_overpayment > 0 ? '+ ' + money(selected.record.regular_overpayment) + ' over' : 'no overpayment' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted">Rate</span>
                            <span class="text-base font-bold">{{ percent(selected.record.interest_rate) }}</span>
                            <span v-if="selected.record.fix_end_date" class="text-xs text-owe-ink">{{ fixEnded ? 'fix ended' : 'to' }} {{ monthYear(selected.record.fix_end_date) }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted">Paid off</span>
                            <span class="text-base font-bold" :class="selected.forecast.payoff_date ? 'text-up' : 'text-down'">
                                {{ selected.forecast.payoff_date ? monthYear(selected.forecast.payoff_date) : 'Never' }}
                            </span>
                            <span class="text-xs text-muted">{{ selected.forecast.months_saved ? duration(selected.forecast.months_saved) + ' early' : selected.forecast.payoff_date ? 'on schedule' : 'below interest' }}</span>
                        </div>
                    </div>
                </section>

                <!-- House value -->
                <form v-if="property" class="w-card p-4 md:p-5 flex flex-col gap-2" @submit.prevent="saveHouse">
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        {{ property.description }} value
                        <span class="flex gap-2.5">
                            <span class="w-field flex-1"><span>£</span><input v-model="house" type="number" inputmode="numeric" min="1" step="1" /></span>
                            <button v-if="houseChanged" type="submit" class="w-btn-primary h-14" :disabled="busy">Save</button>
                        </span>
                    </label>
                    <span class="text-[13px] text-muted">
                        {{ property.last_updated ? 'Last changed ' + dayLabel(property.last_updated) + '.' : '' }} Each change is kept in the history.
                    </span>
                </form>

                <!-- Forecast -->
                <section class="w-card p-4 md:p-5 flex flex-col gap-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="w-label">Forecast</h2>
                        <span v-if="selected.forecast.interest_saved > 0" class="text-xs text-muted">saves {{ money(selected.forecast.interest_saved) }} interest</span>
                    </div>
                    <ApexChart
                        :key="`forecast-${selected.id}-${state.version}`"
                        type="line"
                        height="220"
                        :options="forecastOptions"
                        :series="forecastSeries"
                    />
                    <div class="grid grid-cols-3 gap-2 text-[13px]">
                        <span class="flex flex-col"><span class="text-xs text-muted">Interest to pay</span><span class="font-semibold">{{ money(selected.forecast.interest_remaining) }}</span></span>
                        <span class="flex flex-col"><span class="text-xs text-muted">Time left</span><span class="font-semibold">{{ duration(selected.forecast.months_remaining) }}</span></span>
                        <span class="flex flex-col"><span class="text-xs text-muted">Contractual end</span><span class="font-semibold">{{ monthYear(selected.forecast.contractual_end_date) }}</span></span>
                    </div>
                </section>
            </div>

            <div class="flex flex-col gap-4 md:gap-5 min-w-0">

                <!-- Balance log -->
                <section class="flex flex-col gap-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="w-label">Balance log</h2>
                        <span class="text-xs text-muted">Logged on each payment date</span>
                    </div>
                    <div class="w-card flex flex-col">
                        <template v-for="(value, index) in shownValues" :key="value.id">

                            <form
                                v-if="editing.id === value.id"
                                class="-m-px px-4 pt-3.5 pb-4 border-2 border-brand rounded-[20px] bg-brand-wash flex flex-col gap-3"
                                @submit.prevent="saveValue(value)"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <span class="flex flex-col gap-0.5">
                                        <span class="text-[15px] font-semibold">{{ shortDate(value.date) }}</span>
                                        <span class="text-xs text-muted">Projected {{ money(value.projected_balance ?? value.balance, 2) }}</span>
                                    </span>
                                    <span class="text-xs font-semibold text-gray-700 bg-line rounded-full px-2.5 py-1">{{ value.is_manual ? 'entered' : 'projected' }}</span>
                                </div>
                                <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                                    Balance from your lender
                                    <span class="w-field h-[60px] border-2 border-brand"><span>£</span><input v-model="editing.balance" type="number" inputmode="decimal" min="0" step="0.01" required /></span>
                                </label>
                                <span class="text-[13px] font-medium" :class="editingDrift === 0 ? 'text-muted' : editingDrift < 0 ? 'text-up' : 'text-owe-ink'">{{ editingNote }}</span>
                                <p v-if="editing.error" class="text-[13px] text-down">{{ editing.error }}</p>
                                <div class="grid grid-cols-[104px_minmax(0,1fr)] gap-2.5">
                                    <button type="button" class="w-btn h-12 rounded-[14px] text-[15px]" @click="editing.id = null">Cancel</button>
                                    <button type="submit" class="w-btn-primary" :disabled="busy">Save correction</button>
                                </div>
                            </form>

                            <div v-else class="min-h-16 px-4 py-2.5 flex items-center gap-3" :class="index < shownValues.length - 1 ? 'border-b border-line' : ''">
                                <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                                    <span class="text-[15px] font-medium">{{ shortDate(value.date) }}</span>
                                    <span class="text-xs text-muted">{{ valueNote(value) }}</span>
                                </span>
                                <span class="text-base font-semibold">{{ money(value.balance) }}</span>
                                <button v-if="value.editable" class="w-btn px-3 text-[13px]" @click="startEditing(value)">Correct</button>
                            </div>
                        </template>
                        <button v-if="selected.values.length > VALUES_SHOWN" class="h-12 border-t border-line text-sm font-semibold text-brand" @click="showAllValues = !showAllValues">
                            {{ showAllValues ? 'Show fewer' : `Show all ${selected.values.length}` }}
                        </button>
                    </div>
                </section>

                <!-- One-off overpayments -->
                <section class="flex flex-col gap-2">
                    <h2 class="w-label">One-off overpayments</h2>
                    <div class="w-card flex flex-col">
                        <div v-for="overpayment in selected.overpayments" :key="overpayment.id" class="min-h-14 px-4 py-2 flex items-center gap-3 border-b border-line">
                            <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                                <span class="text-[15px] font-medium">{{ money(overpayment.amount, overpayment.amount % 1 ? 2 : 0) }} · {{ shortDate(overpayment.paid_on) }}</span>
                                <span class="text-xs text-muted truncate">{{ [statusLabel[overpayment.status], overpayment.note].filter(Boolean).join(' · ') }}</span>
                            </span>
                            <button class="w-11 h-11 flex items-center justify-center text-muted hover:text-down" :aria-label="`Remove the overpayment of ${money(overpayment.amount)}`" @click="removeOverpayment(overpayment)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                            </button>
                        </div>
                        <form class="p-4 flex flex-col gap-2.5" @submit.prevent="addOverpayment">
                            <div class="grid grid-cols-2 gap-2.5">
                                <input v-model="oneOff.paid_on" type="date" required aria-label="Overpayment date" class="w-input" />
                                <input v-model="oneOff.amount" type="number" inputmode="decimal" step="0.01" min="0.01" required placeholder="£ amount" aria-label="Overpayment amount" class="w-input" />
                            </div>
                            <div class="flex gap-2.5">
                                <input v-model="oneOff.note" type="text" maxlength="100" placeholder="Note (optional)" aria-label="Overpayment note" class="w-input" />
                                <button type="submit" class="w-btn h-12" :disabled="busy">Add</button>
                            </div>
                            <span class="text-xs text-muted">A future date counts as planned and feeds the forecast.</span>
                            <p v-if="oneOffError" class="text-[13px] text-down">{{ oneOffError }}</p>
                        </form>
                    </div>
                </section>

                <!-- Saved details -->
                <section class="flex flex-col gap-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="w-label">Saved details</h2>
                        <span class="text-xs text-muted">Never edited</span>
                    </div>
                    <ul class="w-card divide-y divide-line">
                        <li v-for="record in shownRecords" :key="record.id" class="px-4 py-3 flex flex-col gap-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="text-[15px] font-medium">{{ shortDate(record.created_at) }}</span>
                                <span class="text-[13px] font-semibold" :class="record.payoff_date ? 'text-up' : 'text-down'">paid off {{ record.payoff_date ? monthYear(record.payoff_date) : 'never' }}</span>
                            </span>
                            <span class="text-[13px] text-gray-700">{{ record.change_note }}</span>
                            <span class="text-xs text-muted">
                                {{ money(record.balance) }} as of {{ shortDate(record.effective_date) }} · {{ percent(record.interest_rate) }} · {{ duration(record.term_months) }} left · {{ money(record.monthly_payment, 2) }} + {{ money(record.regular_overpayment) }}
                            </span>
                        </li>
                        <li v-if="selected.records.length > RECORDS_SHOWN">
                            <button class="w-full h-12 text-sm font-semibold text-brand" @click="showAllRecords = !showAllRecords">
                                {{ showAllRecords ? 'Show fewer' : `Show all ${selected.records.length}` }}
                            </button>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <LiabilityForm v-if="formOpen" :liability="formLiability" @close="formOpen = false" @saved="onSaved" />
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import ApexChart from 'vue3-apexcharts'
import axios from 'axios'
import LiabilityForm from './LiabilityForm.vue'
import { errorMessage, refresh, state, updateSource } from './store'
import { chartBase, dayLabel, duration, money, monthYear, percent, shortDate } from './format'

const VALUES_SHOWN = 6
const RECORDS_SHOWN = 3

const selectedId = ref(null)
const busy = ref(false)

const selected = computed(() =>
    state.liabilities.find(l => l.id === selectedId.value) ?? state.liabilities[0] ?? null
)

const property = computed(() => state.sources.find(s => s.id === selected.value?.property_source_id) ?? null)

const loanToValue = computed(() =>
    property.value?.current_amount ? (selected.value.balance_today / property.value.current_amount * 100).toFixed(1) : 0
)

const fixEnded = computed(() => new Date(selected.value.record.fix_end_date) < new Date())

// --- House value ---

const house = ref('')
watch(() => property.value?.current_amount, value => { house.value = value ?? '' }, { immediate: true })
const houseChanged = computed(() => house.value !== '' && Math.round(Number(house.value)) !== property.value?.current_amount)

async function saveHouse() {
    if (!houseChanged.value) return
    busy.value = true
    try {
        await updateSource(property.value.id, 'current', Math.round(Number(house.value)))
        await refresh()
    } finally {
        busy.value = false
    }
}

// --- Forecast chart ---

const toPoints = schedule => schedule.map(([date, balance]) => ({ x: new Date(date).getTime(), y: balance }))

const forecastSeries = computed(() => [
    { name: 'With overpayments', data: toPoints(selected.value.schedule) },
    { name: 'Contractual payments only', data: toPoints(selected.value.schedule_contractual) },
])

const forecastOptions = computed(() => {
    const base = chartBase('line')
    const fixEnd = selected.value.record?.fix_end_date
    return {
        ...base,
        colors: ['#2447d8', '#8a93a3'],
        stroke: { curve: 'straight', width: [2.5, 1.5], dashArray: [0, 5] },
        yaxis: { ...base.yaxis, min: 0 },
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', labels: { colors: '#5b6472' } },
        tooltip: { x: { format: 'MMM yyyy' }, y: { formatter: val => money(Math.round(val)) } },
        annotations: fixEnd ? {
            xaxis: [{
                x: new Date(fixEnd).getTime(),
                borderColor: '#f26b21',
                strokeDashArray: 3,
                label: { text: 'Fix ends', orientation: 'horizontal', borderColor: 'transparent', style: { color: '#b54708', background: 'transparent', fontSize: '11px' } },
            }],
        } : {},
    }
})

// --- Balance log ---

const showAllValues = ref(false)
const shownValues = computed(() => (showAllValues.value ? selected.value.values : selected.value.values.slice(0, VALUES_SHOWN)))

const editing = reactive({ id: null, balance: '', error: '' })

function startEditing(value) {
    Object.assign(editing, { id: value.id, balance: value.balance, error: '' })
}

function driftText(difference) {
    return `${money(Math.abs(difference), 2)} ${difference < 0 ? 'below' : 'above'} projection`
}

function valueNote(value) {
    const bits = [value.is_manual ? 'Entered' : 'Projected']
    if (value.is_manual && value.projected_balance !== null && Math.abs(value.balance - value.projected_balance) >= 0.01) {
        bits.push(driftText(value.balance - value.projected_balance))
    }
    if (!value.editable) bits.push('locked')
    return bits.join(' · ')
}

// How far the figure being typed sits from what was projected for that date.
const editingValue = computed(() => selected.value?.values.find(v => v.id === editing.id) ?? null)
const editingDrift = computed(() => {
    if (!editingValue.value || editing.balance === '') return 0
    const difference = Number(editing.balance) - (editingValue.value.projected_balance ?? editingValue.value.balance)
    return Math.abs(difference) < 0.01 ? 0 : difference
})
const editingNote = computed(() =>
    editingDrift.value === 0
        ? 'Matches the projection.'
        : `${driftText(editingDrift.value)}. Later months will follow this figure.`
)

async function saveValue(value) {
    if (Number(editing.balance) === value.balance) {
        editing.id = null
        return
    }
    busy.value = true
    editing.error = ''
    try {
        await axios.put(`/wealth/liabilities/${selected.value.id}/values/${value.id}`, { balance: editing.balance })
        editing.id = null
        await refresh()
    } catch (error) {
        editing.error = errorMessage(error, 'Could not save the correction.')
    } finally {
        busy.value = false
    }
}

// --- One-off overpayments ---

const statusLabel = { planned: 'Planned', paid: 'Paid', included: 'Already in the saved balance' }
const oneOff = reactive({ paid_on: '', amount: '', note: '' })
const oneOffError = ref('')

async function addOverpayment() {
    busy.value = true
    oneOffError.value = ''
    try {
        await axios.post(`/wealth/liabilities/${selected.value.id}/overpayments`, oneOff)
        Object.assign(oneOff, { paid_on: '', amount: '', note: '' })
        await refresh()
    } catch (error) {
        oneOffError.value = errorMessage(error, 'Could not add the overpayment.')
    } finally {
        busy.value = false
    }
}

async function removeOverpayment(overpayment) {
    await axios.delete(`/wealth/liabilities/${selected.value.id}/overpayments/${overpayment.id}`)
    await refresh()
}

// --- Saved details ---

const showAllRecords = ref(false)
const shownRecords = computed(() => (showAllRecords.value ? selected.value.records : selected.value.records.slice(0, RECORDS_SHOWN)))

const formOpen = ref(false)
const formLiability = ref(null)

function openForm(liability) {
    formLiability.value = liability
    formOpen.value = true
}

async function onSaved() {
    const adding = !formLiability.value
    formOpen.value = false
    await refresh()
    if (adding) selectedId.value = state.liabilities[state.liabilities.length - 1].id
}
</script>
