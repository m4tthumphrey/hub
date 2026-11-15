const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export function money(value, decimals = 0) {
    if (value === null || value === undefined) return '—'
    return '£' + new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value)
}

// Dates arrive as 'YYYY-MM-DD' (or ISO timestamps); read the calendar parts directly to avoid timezone shifts.
function parts(date) {
    const [y, m, d] = String(date).slice(0, 10).split('-').map(Number)
    return { y, m, d }
}

export function monthYear(date) {
    if (!date) return '—'
    const { y, m } = parts(date)
    return `${MONTHS[m - 1]} ${y}`
}

export function shortDate(date) {
    if (!date) return '—'
    const { y, m, d } = parts(date)
    return `${d} ${MONTHS[m - 1]} ${y}`
}

export function duration(months) {
    if (months === null || months === undefined) return '—'
    return `${Math.floor(months / 12)}y ${months % 12}m`
}

export function percent(value) {
    if (value === null || value === undefined) return '—'
    return `${Number(value).toFixed(2)}%`
}
