<template>
    <div
        class="fixed inset-0 z-50 flex items-end md:items-start justify-center overflow-y-auto bg-black/40 md:p-8"
        @click.self="$emit('close')"
    >
        <form class="w-full max-w-2xl bg-white rounded-t-3xl md:rounded-3xl max-h-full overflow-y-auto" @submit.prevent="save">
            <div class="px-5 pt-5 pb-4 border-b border-line">
                <h2 class="text-lg font-bold">{{ title }}</h2>
                <p class="text-[13px] text-muted mt-1">Saving adds a new dated record. Earlier records are kept as they were.</p>
            </div>

            <div class="p-5 flex flex-col gap-5">
                <label v-if="!liability" class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                    Description
                    <input v-model="form.description" type="text" maxlength="100" placeholder="Car finance" class="w-input" />
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        Figures correct as of
                        <input v-model="form.effective_date" type="date" :max="today" class="w-input" />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        {{ isMortgage ? 'Loan remaining' : 'Amount owed' }} (£)
                        <input v-model="form.balance" type="number" inputmode="decimal" step="0.01" min="0" class="w-input" />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        {{ isMortgage ? 'Fixed interest rate' : 'Interest rate' }} (%)
                        <input v-model="form.interest_rate" type="number" inputmode="decimal" step="0.001" min="0" class="w-input" />
                    </label>
                    <div class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        Term remaining
                        <div class="grid grid-cols-2 gap-2">
                            <label class="relative">
                                <input v-model="form.years" type="number" inputmode="numeric" min="0" aria-label="Years remaining" class="w-input pr-14" />
                                <span class="absolute right-3 top-3.5 font-normal text-muted">years</span>
                            </label>
                            <label class="relative">
                                <input v-model="form.months" type="number" inputmode="numeric" min="0" max="11" aria-label="Months remaining" class="w-input pr-16" />
                                <span class="absolute right-3 top-3.5 font-normal text-muted">months</span>
                            </label>
                        </div>
                    </div>
                    <template v-if="isMortgage">
                        <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                            Fixed rate ends
                            <input v-model="form.fix_end_date" type="date" class="w-input" />
                        </label>
                        <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                            Assumed rate after the fix (%)
                            <input v-model="form.follow_on_rate" type="number" inputmode="decimal" step="0.001" min="0" placeholder="Same as fixed rate" class="w-input" />
                        </label>
                    </template>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        Regular overpayment (£ per month)
                        <input v-model="form.regular_overpayment" type="number" inputmode="decimal" step="0.01" min="0" class="w-input" />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                        {{ isMortgage ? "Lender's" : 'Actual' }} monthly payment (£, optional)
                        <input v-model="form.actual_payment" type="number" inputmode="decimal" step="0.01" min="0" placeholder="Use calculated" class="w-input" />
                        <span v-if="carriedPayment" class="font-normal text-muted">
                            Carried over from your last save. Clear it to recalculate from the balance and term.
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-dashed border-line-strong bg-canvas px-4 py-3.5">
                    <div v-if="preview" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-muted">Monthly payment</div>
                            <div class="text-base font-bold">{{ money(preview.monthly_payment, 2) }}</div>
                            <div class="text-xs text-muted">{{ usingActual ? 'entered · calculated ' + money(preview.calculated_payment, 2) : 'calculated' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Total each month</div>
                            <div class="text-base font-bold">{{ money(preview.monthly_payment + overpayment, 2) }}</div>
                            <div class="text-xs text-muted">with overpayment</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Paid off</div>
                            <div class="text-base font-bold" :class="preview.payoff_date ? 'text-up' : 'text-down'">
                                {{ preview.payoff_date ? monthYear(preview.payoff_date) : 'Never' }}
                            </div>
                            <div class="text-xs text-muted">
                                {{ preview.months_saved ? duration(preview.months_saved) + ' early' : preview.payoff_date ? 'on schedule' : 'payment below interest' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Interest to pay</div>
                            <div class="text-base font-bold">{{ money(preview.interest_remaining) }}</div>
                            <div class="text-xs text-muted">{{ preview.interest_saved ? 'saves ' + money(preview.interest_saved) : '' }}</div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-muted">Enter the amount owed, rate and term to see the payment and payoff date.</div>
                </div>

                <ul v-if="errors.length" class="text-sm text-down flex flex-col gap-1">
                    <li v-for="error in errors" :key="error">{{ error }}</li>
                </ul>
            </div>

            <div class="px-5 pt-4 border-t border-line grid grid-cols-[104px_minmax(0,1fr)] gap-2.5" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                <button type="button" class="w-btn h-12 rounded-[14px] text-[15px]" @click="$emit('close')">Cancel</button>
                <button type="submit" class="w-btn-primary" :disabled="saving || !complete">{{ saving ? 'Saving…' : 'Save as new record' }}</button>
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
