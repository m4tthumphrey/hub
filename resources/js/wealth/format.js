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

// 'today', 'yesterday' or '3 Nov' for a date or timestamp.
export function dayLabel(date) {
    if (!date) return ''
    const { y, m, d } = parts(date)
    const now = new Date()
    const days = Math.round((Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) - Date.UTC(y, m - 1, d)) / 86400000)
    if (days === 0) return 'today'
    if (days === 1) return 'yesterday'
    return `${d} ${MONTHS[m - 1]}${y === now.getFullYear() ? '' : ' ' + y}`
}

export function daysSince(date) {
    if (!date) return Infinity
    const { y, m, d } = parts(date)
    const now = new Date()
    return Math.round((Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) - Date.UTC(y, m - 1, d)) / 86400000)
}

export function signedMoney(value, decimals = 0) {
    return (value > 0 ? '+' : value < 0 ? '−' : '') + money(Math.abs(value), decimals)
}

// Light-theme defaults shared by the Apex charts.
export function chartBase(type) {
    return {
        chart: {
            type,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
            animations: { enabled: false },
        },
        xaxis: {
            type: 'datetime',
            labels: { style: { colors: '#5b6472' }, datetimeUTC: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: { style: { colors: '#5b6472' }, formatter: val => money(Math.round(val)) },
        },
        grid: { borderColor: '#e2e5ec', strokeDashArray: 4, padding: { left: 4, right: 8 } },
        dataLabels: { enabled: false },
    }
}
