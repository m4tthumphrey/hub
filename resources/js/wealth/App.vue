<template>
    <div class="min-h-screen bg-canvas text-ink tabular-nums pb-28 md:pb-10">

        <!-- Desktop: tabs along the top -->
        <header class="hidden md:block bg-white border-b border-line">
            <div class="max-w-[1160px] mx-auto px-6 py-2.5 flex items-center gap-8">
                <span class="text-xl font-bold tracking-tight">Wealth</span>
                <nav aria-label="Sections" class="flex-1 flex gap-1">
                    <a
                        v-for="tab in tabs"
                        :key="tab.id"
                        :href="'#' + tab.id"
                        class="h-11 px-4 rounded-xl flex items-center text-sm"
                        :class="tab.id === current ? 'bg-brand-soft text-brand font-bold' : 'text-muted font-semibold hover:text-ink'"
                        :aria-current="tab.id === current ? 'page' : null"
                    >{{ tab.label }}</a>
                </nav>
                <span class="text-[13px] text-muted">{{ today }}</span>
            </div>
        </header>

        <main class="max-w-[1160px] mx-auto px-4 md:px-6 pt-6">
            <p v-if="state.loading" class="text-sm text-muted py-20 text-center">Loading…</p>
            <p v-else-if="failed" class="text-sm text-down py-20 text-center">Could not load your data. Pull to refresh or try again.</p>
            <component :is="tabs.find(t => t.id === current).component" v-else />
        </main>

        <!-- Phone: tabs along the bottom -->
        <nav
            aria-label="Sections"
            class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-line px-2 pt-2 grid grid-cols-4 gap-1"
            style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))"
        >
            <a
                v-for="tab in tabs"
                :key="tab.id"
                :href="'#' + tab.id"
                class="h-[52px] rounded-xl flex flex-col items-center justify-center gap-1 text-xs"
                :class="tab.id === current ? 'bg-brand-soft text-brand font-bold' : 'text-muted font-semibold'"
                :aria-current="tab.id === current ? 'page' : null"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path :d="tab.icon" /></svg>
                {{ tab.label }}
            </a>
        </nav>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { refresh, state } from './store'
import OverviewTab from './OverviewTab.vue'
import AccountsTab from './AccountsTab.vue'
import MortgageTab from './MortgageTab.vue'
import WhatIfTab from './WhatIfTab.vue'

const tabs = [
    { id: 'overview', label: 'Overview', component: OverviewTab, icon: 'M4 20V10M10 20V4M16 20v-7M22 20H2' },
    { id: 'accounts', label: 'Accounts', component: AccountsTab, icon: 'M4 6h16M4 12h16M4 18h16' },
    { id: 'mortgage', label: 'Mortgage', component: MortgageTab, icon: 'M3 11l9-7 9 7M5 10v10h14V10' },
    { id: 'what-if', label: 'What if', component: WhatIfTab, icon: 'M4 18c4 0 5-12 9-12s3 6 7 6' },
]

const fromHash = () => {
    const id = window.location.hash.slice(1)
    return tabs.some(t => t.id === id) ? id : 'overview'
}

const current = ref(fromHash())
const failed = ref(false)
const today = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long' })

function onHashChange() {
    current.value = fromHash()
    window.scrollTo(0, 0)
}

onMounted(async () => {
    window.addEventListener('hashchange', onHashChange)
    try {
        await refresh()
    } catch {
        failed.value = true
        state.loading = false
    }
})

onUnmounted(() => window.removeEventListener('hashchange', onHashChange))
</script>
