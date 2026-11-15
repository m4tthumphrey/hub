import { reactive } from 'vue'
import axios from 'axios'

// Everything the dashboard shows, loaded once and shared by the tabs.
export const state = reactive({
    loading: true,
    version: 0,
    sources: [],
    totals: [],
    summary: { property: 0, liabilities: 0, net_worth: 0 },
    changes: [],
    history: { total_over_time: [], by_source: [], liabilities_over_time: [], net_worth_over_time: [] },
    liabilities: [],
})

export async function refresh() {
    // Liabilities first: loading them logs any balances that have fallen due, which the other two then include.
    const liabilities = await axios.get('/wealth/liabilities')
    const [data, history] = await Promise.all([axios.get('/wealth/data'), axios.get('/wealth/history')])

    state.liabilities = liabilities.data.liabilities
    state.sources = data.data.sources
    state.totals = data.data.totals
    state.summary = data.data.summary
    state.changes = data.data.changes
    state.history = history.data
    state.version++
    state.loading = false
}

export async function updateSource(id, name, value) {
    await axios.post('/wealth/update', { id, name, value })
}

export function errorMessage(error, fallback) {
    if (error.response?.status === 422) {
        const errors = error.response.data.errors
        return errors ? Object.values(errors).flat().join(' ') : (error.response.data.message ?? fallback)
    }
    return fallback
}
