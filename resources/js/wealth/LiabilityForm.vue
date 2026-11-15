<template>
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4 sm:p-8"
        @click.self="$emit('close')"
    >
        <form class="w-full max-w-2xl bg-gray-900 border border-gray-800 rounded-xl" @submit.prevent="save">
            <div class="px-5 py-4 border-b border-gray-800">
                <h2 class="text-sm font-semibold text-white">{{ title }}</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Saving adds a new dated record. Earlier records are kept as they were.
                </p>
            </div>

            <div class="p-5 space-y-5">
                <label v-if="!liability" class="block text-xs text-gray-400 space-y-1.5">
                    <span>Description</span>
                    <input v-model="form.description" type="text" maxlength="100" placeholder="Car finance" :class="inputClass" />
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-xs text-gray-400 space-y-1.5">
                        <span>Figures correct as of</span>
                        <input v-model="form.effective_date" type="date" :max="today" :class="inputClass" />
                    </label>
                    <label class="block text-xs text-gray-400 space-y-1.5">
                        <span>{{ isMortgage ? 'Loan remaining' : 'Amount owed' }} (£)</span>
                        <input v-model="form.balance" type="number" step="0.01" min="0" :class="inputClass" />
                    </label>
                    <label class="block text-xs text-gray-400 space-y-1.5">
                        <span>{{ isMortgage ? 'Fixed interest rate' : 'Interest rate' }} (%)</span>
                        <input v-model="form.interest_rate" type="number" step="0.001" min="0" :class="inputClass" />
                    </label>
                    <div class="text-xs text-gray-400 space-y-1.5">
                        <span>Term remaining</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <input v-model="form.years" type="number" min="0" aria-label="Years remaining" :class="inputClass" />
                                <span class="absolute right-3 top-2 text-gray-600">years</span>
                            </div>
                            <div class="relative">
                                <input v-model="form.months" type="number" min="0" max="11" aria-label="Months remaining" :class="inputClass" />
                                <span class="absolute right-3 top-2 text-gray-600">months</span>
                            </div>
                        </div>
                    </div>
                    <template v-if="isMortgage">
                        <label class="block text-xs text-gray-400 space-y-1.5">
                            <span>Fixed rate ends</span>
                            <input v-model="form.fix_end_date" type="date" :class="inputClass" />
                        </label>
                        <label class="block text-xs text-gray-400 space-y-1.5">
                            <span>Assumed rate after the fix (%)</span>
                            <input v-model="form.follow_on_rate" type="number" step="0.001" min="0" placeholder="Same as fixed rate" :class="inputClass" />
                        </label>
                    </template>
                    <label class="block text-xs text-gray-400 space-y-1.5">
                        <span>Regular overpayment (£ per month)</span>
                        <input v-model="form.regular_overpayment" type="number" step="0.01" min="0" :class="inputClass" />
                    </label>
                    <label class="block text-xs text-gray-400 space-y-1.5">
                        <span>{{ isMortgage ? "Lender's" : 'Actual' }} monthly payment (£, optional)</span>
                        <input v-model="form.actual_payment" type="number" step="0.01" min="0" placeholder="Use calculated" :class="inputClass" />
                        <span v-if="carriedPayment" class="block text-gray-600">
                            Carried over from your last save. Clear it to recalculate from the balance and term.
                        </span>
                    </label>
                </div>

                <div class="rounded-lg border border-dashed border-gray-700 bg-gray-950 px-4 py-3">
                    <div v-if="preview" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly payment</div>
                            <div class="text-base font-semibold text-white tabular-nums">{{ money(preview.monthly_payment, 2) }}</div>
                            <div class="text-xs text-gray-600">
                                {{ usingActual ? 'entered · calculated ' + money(preview.calculated_payment, 2) : 'calculated' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total each month</div>
                            <div class="text-base font-semibold text-white tabular-nums">{{ money(preview.monthly_payment + overpayment, 2) }}</div>
                            <div class="text-xs text-gray-600">with overpayment</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Paid off</div>
                            <div class="text-base font-semibold tabular-nums" :class="preview.payoff_date ? 'text-emerald-400' : 'text-rose-400'">
                                {{ preview.payoff_date ? monthYear(preview.payoff_date) : 'Never' }}
                            </div>
                            <div class="text-xs text-gray-600">
                                {{ preview.months_saved ? duration(preview.months_saved) + ' early' : preview.payoff_date ? 'on schedule' : 'payment below interest' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Interest to pay</div>
                            <div class="text-base font-semibold text-white tabular-nums">{{ money(preview.interest_remaining) }}</div>
                            <div class="text-xs text-gray-600">{{ preview.interest_saved ? 'saves ' + money(preview.interest_saved) : '' }}</div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-600">
                        Enter the amount owed, rate and term to see the payment and payoff date.
                    </div>
                </div>

                <ul v-if="errors.length" class="text-sm text-rose-400 space-y-1">
                    <li v-for="error in errors" :key="error">{{ error }}</li>
                </ul>
            </div>

            <div class="px-5 py-4 border-t border-gray-800 flex justify-end gap-2">
                <button type="button" class="px-3 py-1.5 rounded-lg text-sm text-gray-300 bg-gray-800 border border-gray-700 hover:bg-gray-700 transition-colors" @click="$emit('close')">
                    Cancel
                </button>
                <button type="submit" :disabled="saving || !complete" class="px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 transition-colors">
                    {{ saving ? 'Saving…' : 'Save as new record' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { duration, money, monthYear } from './format'

const props = defineProps({
    // The liability being updated, or null when adding a new debt.
    liability: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const inputClass = 'w-full bg-gray-800 border border-gray-700/50 rounded-lg px-3 py-2 text-gray-200 text-sm tabular-nums focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-colors'

const now = new Date()
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

const start = props.liability?.form
const blank = v => (v === null || v === undefined ? '' : v)

const form = reactive({
    description: '',
    effective_date: start?.effective_date ?? today,
    balance: blank(start?.balance),
    interest_rate: blank(start?.interest_rate),
    years: start ? Math.floor(start.term_months / 12) : '',
    months: start ? start.term_months % 12 : '',
    fix_end_date: start?.fix_end_date ?? '',
    follow_on_rate: blank(start?.follow_on_rate),
    regular_overpayment: start?.regular_overpayment ?? 0,
    actual_payment: blank(start?.actual_payment),
})

const isMortgage = computed(() => props.liability?.type === 'mortgage')
const title = computed(() => {
    if (!props.liability) return 'Add a debt'
    return `${props.liability.record ? 'Update' : 'Enter'} ${props.liability.description.toLowerCase()} details`
})

// True while the payment field still holds the value the server carried over from the last record.
const carriedPayment = computed(() =>
    start?.actual_payment && !props.liability?.record?.actual_payment && Number(form.actual_payment) === Number(start.actual_payment)
)
const usingActual = computed(() => Number(form.actual_payment) > 0)
const overpayment = computed(() => Number(form.regular_overpayment) || 0)

const payload = computed(() => ({
    liability_id: props.liability?.id ?? null,
    description: form.description,
    effective_date: form.effective_date,
    balance: form.balance,
    interest_rate: form.interest_rate,
    term_months: (Number(form.years) || 0) * 12 + (Number(form.months) || 0),
    fix_end_date: isMortgage.value ? form.fix_end_date || null : null,
    follow_on_rate: isMortgage.value && form.follow_on_rate !== '' ? form.follow_on_rate : null,
    regular_overpayment: form.regular_overpayment === '' ? 0 : form.regular_overpayment,
    actual_payment: form.actual_payment === '' ? null : form.actual_payment,
}))

const complete = computed(() =>
    form.effective_date && form.balance !== '' && form.interest_rate !== '' && payload.value.term_months > 0
    && (props.liability || form.description.trim() !== '')
)

const preview = ref(null)
const errors = ref([])
const saving = ref(false)
let timer = null
let requestId = 0

function validationErrors(error) {
    if (error.response?.status === 422) {
        return Object.values(error.response.data.errors).flat()
    }
    return ['Something went wrong. Nothing was saved.']
}

async function loadPreview() {
    if (form.balance === '' || form.interest_rate === '' || payload.value.term_months < 1 || !form.effective_date) {
        preview.value = null
        return
    }
    const id = ++requestId
    try {
        const res = await axios.post('/wealth/liabilities/preview', payload.value)
        if (id === requestId) preview.value = res.data
    } catch {
        if (id === requestId) preview.value = null
    }
}

watch(payload, () => {
    clearTimeout(timer)
    timer = setTimeout(loadPreview, 250)
}, { deep: true, immediate: true })

async function save() {
    saving.value = true
    errors.value = []
    try {
        const url = props.liability ? `/wealth/liabilities/${props.liability.id}/records` : '/wealth/liabilities'
        await axios.post(url, payload.value)
        emit('saved')
    } catch (error) {
        errors.value = validationErrors(error)
    } finally {
        saving.value = false
    }
}
</script>
