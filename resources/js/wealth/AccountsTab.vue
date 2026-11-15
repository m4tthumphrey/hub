<template>
    <div class="flex flex-col gap-4 max-w-2xl mx-auto">

        <div class="flex items-baseline justify-between gap-3">
            <h1 class="text-[22px] font-bold tracking-tight">Accounts</h1>
            <span class="text-[13px] text-muted">{{ money(total) }} · {{ money(monthly) }} a month</span>
        </div>

        <div class="flex gap-2 overflow-x-auto -mx-4 px-4 md:mx-0 md:px-0">
            <button
                v-for="option in filters"
                :key="option.id"
                class="w-chip"
                :class="{ 'w-chip-on': filter === option.id }"
                :aria-pressed="filter === option.id"
                @click="filter = option.id"
            >{{ option.label }}</button>
        </div>

        <p v-if="!groups.length" class="text-sm text-muted py-8 text-center">No accounts match this filter.</p>

        <section v-for="group in groups" :key="group.name" class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between">
                <h2 class="w-label">{{ group.name }}</h2>
                <span class="text-sm font-semibold">{{ money(group.total) }}</span>
            </div>

            <div class="w-card flex flex-col">
                <template v-for="(source, index) in group.sources" :key="source.id">

                    <!-- Editing in place -->
                    <form
                        v-if="openId === source.id"
                        class="-m-px px-4 pt-3.5 pb-4 border-2 border-brand rounded-[18px] bg-brand-wash flex flex-col gap-3.5"
                        @submit.prevent="save(source)"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex flex-col gap-0.5 min-w-0">
                                <span class="text-[15px] font-semibold truncate">{{ source.description }}</span>
                                <span class="text-xs" :class="isStale(source) ? 'text-owe-ink' : 'text-muted'">{{ subtitle(source, false) }}</span>
                            </span>
                            <svg v-if="sparklines[source.id]" viewBox="0 0 80 24" width="80" height="24" class="shrink-0" aria-hidden="true">
                                <polyline :points="sparklines[source.id]" fill="none" stroke="#2447d8" stroke-width="2" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                                Current
                                <span class="w-field"><span>£</span><input v-model="draft.current" type="number" inputmode="numeric" min="1" step="1" required /></span>
                            </label>
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-700">
                                Monthly
                                <span class="w-field"><span>£</span><input v-model="draft.monthly" type="number" inputmode="numeric" min="0" step="1" /></span>
                            </label>
                        </div>
                        <span class="text-[13px] text-muted">{{ changeNote(source) }}</span>
                        <p v-if="error" class="text-[13px] text-down">{{ error }}</p>
                        <div class="grid grid-cols-[104px_minmax(0,1fr)] gap-2.5">
                            <button type="button" class="w-btn h-12 rounded-[14px] text-[15px]" @click="openId = null">Cancel</button>
                            <button type="submit" class="w-btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
                        </div>
                    </form>

                    <button
                        v-else
                        class="min-h-16 px-4 py-2.5 flex items-center gap-3 text-left"
                        :class="index < group.sources.length - 1 ? 'border-b border-line' : ''"
                        @click="open(source)"
                    >
                        <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                            <span class="text-[15px] font-medium truncate">{{ source.description }}</span>
                            <span class="text-xs" :class="isStale(source) ? 'text-owe-ink' : 'text-muted'">{{ subtitle(source, true) }}</span>
                        </span>
                        <span class="text-base font-semibold">{{ money(source.current_amount) }}</span>
                    </button>
                </template>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { errorMessage, refresh, state, updateSource } from './store'
import { dayLabel, daysSince, money, signedMoney } from './format'

const STALE_AFTER_DAYS = 31

const accounts = computed(() => state.sources.filter(s => s.is_liquid))
const isStale = source => daysSince(source.last_updated) > STALE_AFTER_DAYS

// --- Filters ---

const filter = ref('all')

const filters = computed(() => {
    const people = [...new Set(accounts.value.map(s => s.who))]
    const stale = accounts.value.filter(isStale).length
    return [
        { id: 'all', label: 'All' },
        ...people.map(who => ({ id: 'who:' + who, label: who })),
        ...(stale ? [{ id: 'stale', label: `Stale · ${stale}` }] : []),
    ]
})

const visible = computed(() => accounts.value.filter(source => {
    if (filter.value === 'stale') return isStale(source)
    if (filter.value.startsWith('who:')) return source.who === filter.value.slice(4)
    return true
}))

const groups = computed(() => {
    const byCategory = new Map()
    for (const source of visible.value) {
        if (!byCategory.has(source.category_name)) byCategory.set(source.category_name, [])
        byCategory.get(source.category_name).push(source)
    }
    return [...byCategory].map(([name, sources]) => ({
        name,
        sources: [...sources].sort((a, b) => b.current_amount - a.current_amount),
        total: sources.reduce((sum, s) => sum + s.current_amount, 0),
    }))
})

const total = computed(() => visible.value.reduce((sum, s) => sum + s.current_amount, 0))
const monthly = computed(() => visible.value.reduce((sum, s) => sum + s.regular_amount, 0))

// --- Row text ---

const originLabel = { screenshot: 'screenshot', manual: 'typed' }

function subtitle(source, withMonthly) {
    const bits = [source.who]
    if (source.last_updated) {
        bits.push(`${originLabel[source.last_origin] ?? 'updated'} ${dayLabel(source.last_updated)}`)
    } else {
        bits.push('never updated')
    }
    if (withMonthly && source.regular_amount > 0) bits.push(`${money(source.regular_amount)} a month`)
    return bits.join(' · ')
}

const sparklines = computed(() => {
    const values = {}
    for (const entry of state.history.by_source) (values[entry.id] ??= []).push(entry.value)

    const lines = {}
    for (const [id, list] of Object.entries(values)) {
        if (list.length < 2) continue
        const min = Math.min(...list)
        const range = Math.max(...list) - min || 1
        lines[id] = list.map((v, i) => `${(i / (list.length - 1) * 78 + 1).toFixed(1)},${(21 - (v - min) / range * 18).toFixed(1)}`).join(' ')
    }
    return lines
})

// --- Editing ---

const openId = ref(null)
const draft = reactive({ current: '', monthly: '' })
const saving = ref(false)
const error = ref('')

function open(source) {
    openId.value = source.id
    error.value = ''
    Object.assign(draft, { current: source.current_amount, monthly: source.regular_amount })
}

function changeNote(source) {
    const difference = (Number(draft.current) || 0) - source.current_amount
    if (!difference) return `Currently ${money(source.current_amount)}.`
    return `Was ${money(source.current_amount)}. This change is ${signedMoney(difference)}.`
}

async function save(source) {
    const current = Math.round(Number(draft.current))
    const monthlyAmount = Math.round(Number(draft.monthly) || 0)

    saving.value = true
    error.value = ''
    try {
        if (current !== source.current_amount) await updateSource(source.id, 'current', current)
        if (monthlyAmount !== source.regular_amount) await updateSource(source.id, 'regular', monthlyAmount)
        await refresh()
        openId.value = null
    } catch (e) {
        error.value = errorMessage(e, 'Could not save. Nothing was changed.')
    } finally {
        saving.value = false
    }
}
</script>
