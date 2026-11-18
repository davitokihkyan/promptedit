import type { Usd } from '@/types';

/** Display only; exact amounts stay as strings and are never used for math here. */
export function formatUsd(amount: Usd, fractionDigits = 4): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(Number(amount));
}

/** Exact comparison: "9.532656250" becomes 9532656250n nanodollars. */
export function toNanos(amount: Usd): bigint {
    return BigInt(amount.replace('.', ''));
}
