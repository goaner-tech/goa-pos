// Amounts arrive as integer minor units (piasters for SSP, cents for USD).
let currency = 'SSP';

export function setCurrency(code) {
    currency = code;
}

export function money(minor, code = currency) {
    const value = (minor ?? 0) / 100;

    return `${code} ${value.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

// Staff type whole amounts ("15000" or "12.50"); the API wants minor units.
export function toMinor(text) {
    const value = Number(String(text).replace(/,/g, ''));

    return Number.isFinite(value) ? Math.round(value * 100) : NaN;
}

export function fromMinor(minor) {
    return String((minor ?? 0) / 100);
}

export const tenderNames = {
    cash_ssp: 'Cash (SSP)',
    cash_usd: 'Cash (USD)',
    mobile_money: 'Mobile money',
    card: 'Card',
};
