export type LinkageUnit = 'number' | 'currency' | 'percentage';

export function formatLinkageValue(value: number, unit: LinkageUnit): string {
    if (unit === 'currency') return 'RM ' + Math.round(value).toLocaleString('en-US');
    if (unit === 'percentage') return value.toFixed(1) + '%';
    return Math.round(value).toLocaleString('en-US');
}
