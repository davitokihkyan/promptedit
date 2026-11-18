import type { Usd } from '@/types';

/** Rounded for display, e.g. "$0.3819". Never used for math. */
export function formatUsd(amount: Usd, fractionDigits = 4): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(Number(amount));
}

/** The exact amount with all nine decimals, e.g. "$0.381887500". */
export function formatExactUsd(amount: Usd): string {
    return amount.startsWith('-') ? `-$${amount.slice(1)}` : `$${amount}`;
}

/** For exact comparisons. Expects the server's nine-decimal format: "9.532656250" becomes 9532656250n. */
export function toNanos(amount: Usd): bigint {
    return BigInt(amount.replace('.', ''));
}
