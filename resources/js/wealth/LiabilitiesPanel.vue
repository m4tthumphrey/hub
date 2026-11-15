<template>
    <section class="space-y-6">

        <!-- Property and the selected liability -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="space-y-6">
                <div
                    v-for="property in properties"
                    :key="property.id"
                    class="bg-gray-900 border border-gray-800 rounded-xl p-5"
                >
                    <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">{{ property.description }} value</h2>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-600 text-sm">£</span>
                        <input
                            type="number"
                            step="1000"
                            min="1"
                            :value="property.current_amount"
                            :aria-label="property.description + ' value'"
                            @change="$emit('update-source', property.id, $event.target.value)"
                            class="w-full bg-gray-800 border border-gray-700/50 rounded-lg px-3 py-2 text-gray-200 text-lg font-semibold tabular-nums focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-colors"
                        />
                    </div>
                    <template v-if="owedAgainst(property.id) !== null">
                        <div class="mt-4 h-2.5 rounded-full bg-indigo-500 overflow-hidden" :title="`Loan to value ${loanToValue(property)}%`">
                            <div class="h-full bg-rose-400/90" :style="{ width: Math.min(100, loanToValue(property)) + '%' }"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Equity</div>
                                <div class="text-lg font-semibold text-emerald-400 tabular-nums">{{ money(property.current_amount - owedAgainst(property.id)) }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Loan to value</div>
                                <div class="text-lg font-semibold text-white tabular-nums">{{ loanToValue(property) }}%</div>
                            </div>
                        </div>
                    </template>
                    <p v-else class="text-xs text-gray-600 mt-3">Equity appears once the mortgage details are saved.</p>
                </div>
            </div>

            <div v-if="selected" class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-xl p-5">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ selected.description }}</h2>
                        <p v-if="selected.record" class="text-xs text-gray-600 mt-1">
                            Saved {{ shortDate(selected.record.created_at) }}, correct as of {{ shortDate(selected.record.effective_date) }}.
                            Balance projected monthly since then.
                        </p>
                    </div>
                    <button :class="primaryButton" @click="openForm(selected)">
                        {{ selected.record ? 'Update details' : 'Enter details' }}
                    </button>
                </div>

                <div v-if="selected.forecast" class="grid grid-cols-2 sm:grid-cols-4 gap-5">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ selected.type === 'mortgage' ? 'Loan remaining' : 'Owed' }}</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ money(selected.balance_today) }}</div>
                        <div class="text-xs text-gray-600">projected today</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly payment</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ money(selected.forecast.monthly_payment, 2) }}</div>
                        <div class="text-xs text-gray-600">
                            {{ selected.record.regular_overpayment > 0 ? '+ ' + money(selected.record.regular_overpayment) + ' overpayment' : 'no regular overpayment' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ selected.record.fix_end_date ? 'Fixed rate' : 'Rate' }}</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ percent(selected.record.interest_rate) }}</div>
                        <div v-if="selected.record.fix_end_date" class="text-xs" :class="fixEnded(selected) ? 'text-rose-400' : 'text-amber-400'">
                            fix {{ fixEnded(selected) ? 'ended' : 'ends' }} {{ monthYear(selected.record.fix_end_date) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Paid off</div>
                        <div class="text-lg font-semibold tabular-nums" :class="selected.forecast.payoff_date ? 'text-emerald-400' : 'text-rose-400'">
                            {{ selected.forecast.payoff_date ? monthYear(selected.forecast.payoff_date) : 'Never' }}
                        </div>
                        <div class="text-xs text-gray-600">
                            {{ selected.forecast.months_saved ? duration(selected.forecast.months_saved) + ' early' : selected.forecast.payoff_date ? 'on schedule' : 'payment below interest' }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-600">
                    No details saved yet. Enter the loan remaining, rate and term to see the monthly payment and payoff date.
                </p>

                <template v-if="selected.forecast">
                    <div class="flex flex-wrap items-center justify-between gap-3 mt-6 mb-1">
                        <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wider">Balance forecast</h3>
                    </div>
                    <ApexChart
                        :key="`forecast-${selected.id}-${version}-${whatIfVersion}`"
                        type="line"
                        height="230"
                        :options="forecastOptions"
                        :series="forecastSeries"
                    />
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-5 mt-2">
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Interest saved</div>
                            <div class="text-base font-semibold text-emerald-400 tabular-nums">{{ money(selected.forecast.interest_saved) }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Interest to pay</div>
                            <div class="text-base font-semibold text-white tabular-nums">{{ money(selected.forecast.interest_remaining) }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ selected.forecast.balance_at_fix_end !== null ? 'Owed when fix ends' : 'Contractual end' }}</div>
                            <div class="text-base font-semibold text-white tabular-nums">
                                {{ selected.forecast.balance_at_fix_end !== null ? money(selected.forecast.balance_at_fix_end) : monthYear(selected.forecast.contractual_end_date) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Time left</div>
                            <div class="text-base font-semibold text-white tabular-nums">{{ duration(selected.forecast.months_remaining) }}</div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- What if: hypothetical values that change the projections but are never saved -->
        <div v-if="selected?.forecast" class="bg-gray-900 border border-dashed border-amber-800/60 rounded-xl p-5">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-xs font-medium text-amber-400 uppercase tracking-wider">What if</h2>
                    <p class="text-xs text-gray-600 mt-1">Try a different house value or overpayment. Nothing entered here is saved.</p>
                </div>
                <button v-if="whatIfActive" :class="secondaryButton" @click="resetWhatIf">Reset</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <label v-if="whatIfProperty" class="block text-xs text-gray-400 space-y-1.5">
                    <span>{{ whatIfProperty.description }} value (£)</span>
                    <input v-model="whatIf.house" type="number" step="1000" min="0" :placeholder="formatPlain(whatIfProperty.current_amount)" :class="whatIfInput" />
                </label>
                <label class="block text-xs text-gray-400 space-y-1.5">
                    <span>Monthly overpayment from now (£)</span>
                    <input v-model="whatIf.overpayment" type="number" step="25" min="0" :placeholder="formatPlain(selected.record.regular_overpayment)" :class="whatIfInput" />
                </label>
                <label class="block text-xs text-gray-400 space-y-1.5">
                    <span>One-off lump sum paid now (£)</span>
                    <input v-model="whatIf.lump" type="number" step="500" min="0" placeholder="0" :class="whatIfInput" />
                </label>
            </div>

            <div v-if="whatIfActive" class="grid grid-cols-2 sm:grid-cols-4 gap-5 mt-5">
                <template v-if="houseWhatIf">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Equity</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ money(houseWhatIf.equity) }}</div>
                        <div class="text-xs tabular-nums" :class="deltaClass(houseWhatIf.equityChange)">{{ signed(houseWhatIf.equityChange) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Loan to value</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ houseWhatIf.ltv }}%</div>
                        <div class="text-xs text-gray-600 tabular-nums">now {{ houseWhatIf.ltvNow }}%</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Net worth</div>
                        <div class="text-lg font-semibold text-indigo-300 tabular-nums">{{ money(houseWhatIf.netWorth) }}</div>
                        <div class="text-xs tabular-nums" :class="deltaClass(houseWhatIf.netWorthChange)">{{ signed(houseWhatIf.netWorthChange) }}</div>
                    </div>
                </template>
                <template v-if="whatIfForecast">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Paid off</div>
                        <div class="text-lg font-semibold tabular-nums" :class="whatIfForecast.payoff_date ? 'text-white' : 'text-rose-400'">
                            {{ whatIfForecast.payoff_date ? monthYear(whatIfForecast.payoff_date) : 'Never' }}
                        </div>
                        <div class="text-xs" :class="deltaClass(whatIfForecast.months_sooner)">{{ soonerLabel(whatIfForecast.months_sooner) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Interest to pay</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ money(whatIfForecast.interest_remaining) }}</div>
                        <div class="text-xs tabular-nums" :class="deltaClass(whatIfForecast.interest_saved)">{{ interestLabel(whatIfForecast.interest_saved) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Time left</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ duration(whatIfForecast.months_remaining) }}</div>
                        <div class="text-xs text-gray-600 tabular-nums">now {{ duration(selected.forecast.months_remaining) }}</div>
                    </div>
                    <div v-if="whatIfForecast.balance_at_fix_end !== null">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Owed when fix ends</div>
                        <div class="text-lg font-semibold text-white tabular-nums">{{ money(whatIfForecast.balance_at_fix_end) }}</div>
                        <div class="text-xs text-gray-600 tabular-nums">now {{ money(selected.forecast.balance_at_fix_end) }}</div>
                    </div>
                </template>
            </div>
            <p v-if="whatIfForecast" class="text-xs text-gray-600 mt-4">The amber line on the balance forecast above shows this scenario.</p>
        </div>

        <!-- All liabilities -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between gap-3">
                <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider">Liabilities</h2>
                <button :class="secondaryButton" @click="openForm(null)">+ Add debt</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-800 text-xs font-medium text-gray-600 uppercase tracking-wider">
                            <th class="text-left px-5 py-3">Description</th>
                            <th class="text-right px-5 py-3">Owed today</th>
                            <th class="text-right px-5 py-3">Rate</th>
                            <th class="text-right px-5 py-3">Monthly</th>
                            <th class="text-left px-5 py-3">Ends</th>
                            <th class="text-left px-5 py-3">Last saved</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60">
                        <tr
                            v-for="liability in liabilities"
                            :key="liability.id"
                            class="cursor-pointer transition-colors whitespace-nowrap"
                            :class="liability.id === selected?.id ? 'bg-indigo-950/40' : 'hover:bg-gray-800/40'"
                            @click="selectedId = liability.id"
                        >
                            <td class="px-5 py-3 text-gray-200 font-medium">{{ liability.description }}</td>
                            <template v-if="liability.forecast">
                                <td class="px-5 py-3 text-right text-gray-200 tabular-nums">{{ money(liability.balance_today) }}</td>
                                <td class="px-5 py-3 text-right text-gray-400 tabular-nums">{{ percent(liability.record.interest_rate) }}</td>
                                <td class="px-5 py-3 text-right text-gray-200 tabular-nums">{{ money(monthlyTotal(liability), 2) }}</td>
                                <td class="px-5 py-3 text-gray-400">{{ liability.forecast.payoff_date ? monthYear(liability.forecast.payoff_date) : 'Never' }}</td>
                                <td class="px-5 py-3 text-gray-400">{{ shortDate(liability.record.created_at) }}</td>
                            </template>
                            <td v-else colspan="5" class="px-5 py-3 text-gray-600">No details saved yet</td>
                        </tr>
                        <tr v-if="liabilities.length > 1" class="whitespace-nowrap">
                            <td class="px-5 py-3 text-gray-200 font-semibold">Total</td>
                            <td class="px-5 py-3 text-right text-gray-200 font-semibold tabular-nums">{{ money(totalOwed) }}</td>
                            <td></td>
                            <td class="px-5 py-3 text-right text-gray-200 font-semibold tabular-nums">{{ money(totalMonthly, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- One-off overpayments and the balance log for the selected liability -->
        <div v-if="selected" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-800">
                    <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ selected.description }} · one-off overpayments</h2>
                </div>
                <ul class="divide-y divide-gray-800/60">
                    <li v-for="overpayment in selected.overpayments" :key="overpayment.id" class="px-5 py-3 flex items-center gap-3 text-sm">
                        <div class="flex-1 min-w-0">
                            <div class="text-gray-200 tabular-nums">
                                {{ money(overpayment.amount, overpayment.amount % 1 ? 2 : 0) }}
                                <span class="text-gray-500">· {{ shortDate(overpayment.paid_on) }}</span>
                            </div>
                            <div v-if="overpayment.note" class="text-xs text-gray-500 truncate">{{ overpayment.note }}</div>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full border whitespace-nowrap" :class="statusClass[overpayment.status]">
                            {{ statusLabel[overpayment.status] }}
                        </span>
                        <button
                            class="text-gray-600 hover:text-rose-400 transition-colors"
                            :aria-label="`Remove overpayment of ${money(overpayment.amount)}`"
                            @click="removeOverpayment(overpayment)"
                        >✕</button>
                    </li>
                    <li v-if="!selected.overpayments.length" class="px-5 py-3 text-sm text-gray-600">
                        None yet. A future date counts as planned and feeds the forecast.
                    </li>
                </ul>
                <form class="p-5 border-t border-gray-800 space-y-2" @submit.prevent="addOverpayment">
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="oneOff.paid_on" type="date" required aria-label="Overpayment date" :class="smallInput" />
                        <input v-model="oneOff.amount" type="number" step="0.01" min="0.01" required placeholder="£ amount" aria-label="Overpayment amount" :class="smallInput" />
                    </div>
                    <div class="flex gap-2">
                        <input v-model="oneOff.note" type="text" maxlength="100" placeholder="Note (optional)" aria-label="Overpayment note" :class="smallInput" />
                        <button type="submit" :class="secondaryButton" :disabled="busy">Add</button>
                    </div>
                    <p v-if="oneOffError" class="text-xs text-rose-400">{{ oneOffError }}</p>
                </form>
            </div>

            <div class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-800 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ selected.description }} · balance log</h2>
                    <span class="text-xs text-gray-600">Logged automatically on each payment date. Correct an entry to match your lender.</span>
                </div>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table v-if="selected.values.length" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs font-medium text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                <th class="text-left px-5 py-3">Date</th>
                                <th class="text-right px-5 py-3">Balance</th>
                                <th class="text-left px-5 py-3">Source</th>
                                <th class="text-right px-5 py-3">Against projection</th>
                                <th class="px-5 py-3 w-40"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60">
                            <tr v-for="value in selected.values" :key="value.id" class="whitespace-nowrap">
                                <td class="px-5 py-3 text-gray-200">{{ shortDate(value.date) }}</td>
                                <td class="px-5 py-3 text-right text-gray-200 tabular-nums">
                                    <input
                                        v-if="editing.id === value.id"
                                        v-model="editing.balance"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :aria-label="`Balance on ${shortDate(value.date)}`"
                                        class="w-32 bg-gray-800 border border-gray-700/50 rounded-lg px-2 py-1 text-right text-gray-200 text-sm tabular-nums focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                        @keyup.enter="saveValue(value)"
                                        @keyup.esc="editing.id = null"
                                    />
                                    <template v-else>{{ money(value.balance, 2) }}</template>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full border" :class="value.is_manual ? 'text-indigo-300 border-indigo-900' : 'text-gray-400 border-gray-700'">
                                        {{ value.is_manual ? 'entered' : 'projected' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs tabular-nums" :class="deltaClass(-drift(value))">
                                    {{ driftLabel(value) }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <template v-if="editing.id === value.id">
                                        <button :class="primaryButton" :disabled="busy" @click="saveValue(value)">Save</button>
                                        <button class="ml-2 text-xs text-gray-500 hover:text-gray-300" @click="editing.id = null">Cancel</button>
                                    </template>
                                    <button v-else-if="value.editable" :class="secondaryButton" @click="startEditing(value)">Correct</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-5 py-4 text-sm text-gray-600">The balance will be logged here on each payment date once details are saved.</p>
                </div>
                <p v-if="editing.error" class="px-5 py-3 text-xs text-rose-400 border-t border-gray-800">{{ editing.error }}</p>
            </div>
        </div>

        <!-- Saved records for the selected liability -->
        <div v-if="selected">
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-800 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ selected.description }} · saved records</h2>
                    <span class="text-xs text-gray-600">Newest first. Records are never edited.</span>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="selected.records.length" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs font-medium text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                <th class="text-left px-5 py-3">Saved</th>
                                <th class="text-left px-5 py-3">As of</th>
                                <th class="text-right px-5 py-3">Balance</th>
                                <th class="text-right px-5 py-3">Rate</th>
                                <th class="text-left px-5 py-3">Term left</th>
                                <th class="text-right px-5 py-3">Overpay</th>
                                <th class="text-right px-5 py-3">Payment</th>
                                <th class="text-left px-5 py-3">Paid off</th>
                                <th class="text-left px-5 py-3">What changed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60">
                            <tr v-for="record in selected.records" :key="record.id" class="align-top">
                                <td class="px-5 py-3 text-gray-200 whitespace-nowrap">{{ shortDate(record.created_at) }}</td>
                                <td class="px-5 py-3 text-gray-400 whitespace-nowrap">{{ shortDate(record.effective_date) }}</td>
                                <td class="px-5 py-3 text-right text-gray-200 tabular-nums">{{ money(record.balance) }}</td>
                                <td class="px-5 py-3 text-right text-gray-400 tabular-nums">{{ percent(record.interest_rate) }}</td>
                                <td class="px-5 py-3 text-gray-400 whitespace-nowrap">{{ duration(record.term_months) }}</td>
                                <td class="px-5 py-3 text-right text-gray-400 tabular-nums">{{ money(record.regular_overpayment) }}</td>
                                <td class="px-5 py-3 text-right text-gray-200 tabular-nums">{{ money(record.monthly_payment, 2) }}</td>
                                <td class="px-5 py-3 whitespace-nowrap" :class="record.payoff_date ? 'text-emerald-400' : 'text-rose-400'">
                                    {{ record.payoff_date ? monthYear(record.payoff_date) : 'Never' }}
                                </td>
                                <td class="px-5 py-3 text-gray-400 min-w-64">{{ record.change_note }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-5 py-4 text-sm text-gray-600">Each save will appear here with the values entered and the forecast it produced.</p>
                </div>
            </div>
        </div>

        <LiabilityForm
            v-if="formOpen"
            :liability="formLiability"
            @close="formOpen = false"
            @saved="onSaved"
        />
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import ApexChart from 'vue3-apexcharts'
import axios from 'axios'
import LiabilityForm from './LiabilityForm.vue'
import { duration, money, monthYear, percent, shortDate } from './format'

const props = defineProps({
    // Non-liquid sources (the house).
    properties: { type: Array, required: true },
    // Current net worth, for the what-if panel.
    netWorth: { type: Number, default: 0 },
})
const emit = defineEmits(['update-source', 'changed'])

const primaryButton = 'px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-500 transition-colors'
const secondaryButton = 'px-3 py-1.5 rounded-lg text-xs font-medium text-gray-200 bg-gray-800 border border-gray-700 hover:bg-gray-700 disabled:opacity-40 transition-colors'
const smallInput = 'w-full min-w-0 bg-gray-800 border border-gray-700/50 rounded-lg px-2 py-1.5 text-gray-200 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-colors'

const statusLabel = { planned: 'planned', paid: 'paid', included: 'in saved balance' }
const statusClass = {
    planned: 'text-amber-400 border-amber-900',
    paid: 'text-emerald-400 border-emerald-900',
    included: 'text-gray-400 border-gray-700',
}

const liabilities = ref([])
const selectedId = ref(null)
const version = ref(0)
const busy = ref(false)

const selected = computed(() =>
    liabilities.value.find(l => l.id === selectedId.value) ?? liabilities.value[0] ?? null
)

async function load() {
    const res = await axios.get('/wealth/liabilities')
    liabilities.value = res.data.liabilities
    version.value++
}

onMounted(load)

// --- Totals ---

const monthlyTotal = liability => liability.forecast.monthly_payment + liability.record.regular_overpayment
const active = computed(() => liabilities.value.filter(l => l.forecast))
const totalOwed = computed(() => active.value.reduce((sum, l) => sum + l.balance_today, 0))
const totalMonthly = computed(() => active.value.filter(l => l.balance_today > 0).reduce((sum, l) => sum + monthlyTotal(l), 0))

function owedAgainst(propertyId) {
    const secured = active.value.filter(l => l.property_source_id === propertyId)
    return secured.length ? secured.reduce((sum, l) => sum + l.balance_today, 0) : null
}

function loanToValue(property) {
    if (!property.current_amount) return 0
    return (owedAgainst(property.id) / property.current_amount * 100).toFixed(1)
}

function fixEnded(liability) {
    return new Date(liability.record.fix_end_date) < new Date()
}

// --- Forecast chart ---

const toPoints = schedule => schedule.map(([date, balance]) => ({ x: new Date(date).getTime(), y: balance }))

const forecastSeries = computed(() => [
    { name: 'With overpayments', data: toPoints(selected.value.schedule) },
    { name: 'Contractual payments only', data: toPoints(selected.value.schedule_contractual) },
    ...(whatIfForecast.value ? [{ name: 'What if', data: toPoints(whatIfForecast.value.schedule) }] : []),
])

const forecastOptions = computed(() => {
    const fixEnd = selected.value.record?.fix_end_date
    return {
        chart: {
            type: 'line',
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
            animations: { enabled: false },
        },
        theme: { mode: 'dark' },
        colors: ['#6366f1', '#6b7280', '#f59e0b'],
        stroke: { curve: 'straight', width: [2, 1.5, 2], dashArray: [0, 5, 0] },
        xaxis: {
            type: 'datetime',
            labels: { style: { colors: '#4b5563' }, datetimeUTC: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: 0,
            labels: { style: { colors: '#4b5563' }, formatter: val => money(Math.round(val)) },
        },
        grid: { borderColor: '#1f2937', strokeDashArray: 4, padding: { left: 8, right: 8 } },
        legend: { position: 'top', horizontalAlign: 'right', labels: { colors: '#9ca3af' }, fontSize: '11px' },
        tooltip: {
            theme: 'dark',
            x: { format: 'MMM yyyy' },
            y: { formatter: val => money(Math.round(val)) },
        },
        annotations: fixEnd ? {
            xaxis: [{
                x: new Date(fixEnd).getTime(),
                borderColor: '#f59e0b',
                strokeDashArray: 3,
                label: {
                    text: 'Fix ends',
                    orientation: 'horizontal',
                    borderColor: 'transparent',
                    style: { color: '#fbbf24', background: 'transparent', fontSize: '11px' },
                },
            }],
        } : {},
        dataLabels: { enabled: false },
    }
})

// --- What if (never saved) ---

const whatIfInput = 'w-full bg-gray-800 border border-amber-900/50 rounded-lg px-3 py-2 text-gray-200 text-sm tabular-nums placeholder:text-gray-600 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors'

const whatIf = reactive({ house: '', overpayment: '', lump: '' })
const whatIfForecast = ref(null)
const whatIfVersion = ref(0)
let whatIfTimer = null
let whatIfRequest = 0

const entered = value => value !== '' && value !== null && !Number.isNaN(Number(value))
const whatIfActive = computed(() => entered(whatIf.house) || entered(whatIf.overpayment) || Number(whatIf.lump) > 0)
const whatIfProperty = computed(() =>
    props.properties.find(p => p.id === selected.value?.property_source_id) ?? null
)

// House value is pure arithmetic on what is already loaded.
const houseWhatIf = computed(() => {
    const property = whatIfProperty.value
    if (!property || !entered(whatIf.house)) return null

    const value = Number(whatIf.house)
    const owedNow = owedAgainst(property.id) ?? 0
    const owed = Math.max(0, owedNow - (Number(whatIf.lump) || 0))
    const equityNow = property.current_amount - owedNow

    return {
        equity: value - owed,
        equityChange: value - owed - equityNow,
        ltv: value > 0 ? (owed / value * 100).toFixed(1) : '—',
        ltvNow: loanToValue(property),
        // A lump sum moves money from savings to the loan, so only the valuation changes net worth.
        netWorth: props.netWorth + value - property.current_amount,
        netWorthChange: value - property.current_amount,
    }
})

async function loadWhatIf() {
    const id = ++whatIfRequest
    let result = null

    if (selected.value?.forecast && (entered(whatIf.overpayment) || Number(whatIf.lump) > 0)) {
        try {
            const res = await axios.post(`/wealth/liabilities/${selected.value.id}/what-if`, {
                regular_overpayment: entered(whatIf.overpayment) ? whatIf.overpayment : null,
                lump_sum: Number(whatIf.lump) > 0 ? whatIf.lump : null,
            })
            result = res.data.what_if
        } catch {
            result = null
        }
    }

    if (id === whatIfRequest) {
        whatIfForecast.value = result
        whatIfVersion.value++
    }
}

watch([() => whatIf.overpayment, () => whatIf.lump, () => selected.value?.id, version], () => {
    clearTimeout(whatIfTimer)
    whatIfTimer = setTimeout(loadWhatIf, 250)
})

function resetWhatIf() {
    Object.assign(whatIf, { house: '', overpayment: '', lump: '' })
}

const formatPlain = value => new Intl.NumberFormat('en-GB').format(value)
const signed = value => (value > 0 ? '+' : value < 0 ? '−' : '') + money(Math.abs(value)) + ' vs now'
const deltaClass = value => (value > 0 ? 'text-emerald-400' : value < 0 ? 'text-rose-400' : 'text-gray-600')

function soonerLabel(months) {
    if (months === null) return 'payment below interest'
    if (months === 0) return 'no change'
    return `${duration(Math.abs(months))} ${months > 0 ? 'sooner' : 'later'}`
}

function interestLabel(saved) {
    if (saved === null) return ''
    if (Math.round(saved) === 0) return 'no change'
    return `${saved > 0 ? 'saves' : 'costs'} ${money(Math.abs(saved))}`
}

// --- Saving details ---

const formOpen = ref(false)
const formLiability = ref(null)

function openForm(liability) {
    formLiability.value = liability
    formOpen.value = true
}

async function onSaved() {
    const adding = !formLiability.value
    formOpen.value = false
    await load()
    if (adding) selectedId.value = liabilities.value[liabilities.value.length - 1].id
    emit('changed')
}

// --- Balance log ---

const editing = reactive({ id: null, balance: '', error: '' })

function startEditing(value) {
    Object.assign(editing, { id: value.id, balance: value.balance, error: '' })
}

// How far an entered balance sits from what the projection said for that date.
const drift = value => (value.is_manual && value.projected_balance !== null ? value.balance - value.projected_balance : 0)

function driftLabel(value) {
    const difference = drift(value)
    if (!value.is_manual || value.projected_balance === null) return ''
    if (Math.abs(difference) < 0.01) return 'matched'
    return `${money(Math.abs(difference), 2)} ${difference < 0 ? 'below' : 'above'}`
}

async function saveValue(value) {
    if (editing.balance === '' || Number(editing.balance) === value.balance) {
        editing.id = null
        return
    }
    busy.value = true
    editing.error = ''
    try {
        await axios.put(`/wealth/liabilities/${selected.value.id}/values/${value.id}`, { balance: editing.balance })
        editing.id = null
        await load()
        emit('changed')
    } catch (error) {
        editing.error = error.response?.status === 422
            ? (error.response.data.message ?? 'That balance is not valid.')
            : 'Could not save the correction.'
    } finally {
        busy.value = false
    }
}

// --- One-off overpayments ---

const oneOff = reactive({ paid_on: '', amount: '', note: '' })
const oneOffError = ref('')

async function addOverpayment() {
    busy.value = true
    oneOffError.value = ''
    try {
        await axios.post(`/wealth/liabilities/${selected.value.id}/overpayments`, oneOff)
        Object.assign(oneOff, { paid_on: '', amount: '', note: '' })
        await load()
        emit('changed')
    } catch (error) {
        oneOffError.value = error.response?.status === 422
            ? Object.values(error.response.data.errors).flat().join(' ')
            : 'Could not add the overpayment.'
    } finally {
        busy.value = false
    }
}

async function removeOverpayment(overpayment) {
    await axios.delete(`/wealth/liabilities/${selected.value.id}/overpayments/${overpayment.id}`)
    await load()
    emit('changed')
}
</script>
