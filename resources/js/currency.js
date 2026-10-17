export function formatMoney(value, currency = null) {
    const code = currency ?? (typeof document === 'undefined' ? null : document.querySelector('meta[name="app-currency"]')?.content) ?? 'EUR';
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: ['EUR', 'GBP', 'USD'].includes(code) ? code : 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
}
