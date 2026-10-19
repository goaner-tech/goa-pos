// Amounts arrive as integer minor units (piasters for SSP).
let currency = 'SSP';

export function setCurrency(code) {
    currency = code;
}

export function money(minor) {
    const value = (minor ?? 0) / 100;

    return `${currency} ${value.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}
