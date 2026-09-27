export type LinkageUnit = 'number' | 'currency' | 'percentage' | 'ratio' | 'days' | 'hours' | 'score' | 'binary' | 'custom';

/**
 * Decimals are kept only when the value actually has a fractional part
 * (87.58 stays 87.58; 45 stays 45, not 45.00) for the plain-number case.
 * Currency/percentage keep a fixed 2dp, matching the one other currency
 * formatter in the repo (SubscriptionPlans/Index.tsx's formatPrice).
 * ratio/days/hours/score/binary/custom have no established display
 * convention yet, so they fall through to the plain-number formatting.
 */
export function formatLinkageValue(value: number, unit: LinkageUnit): string {
    if (unit === 'currency') return 'RM ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (unit === 'percentage') return value.toFixed(2) + '%';

    const hasFraction = value % 1 !== 0;
    return value.toLocaleString('en-US', { minimumFractionDigits: hasFraction ? 2 : 0, maximumFractionDigits: 2 });
}
