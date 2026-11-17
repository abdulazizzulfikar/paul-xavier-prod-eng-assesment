// Formats integer nanodollars exactly, without float rounding: 211350000 -> "$0.21135".
export function formatUsd(nano: number): string {
    const digits = String(nano).padStart(10, '0');
    const dollars = digits.slice(0, -9);
    const fraction = digits.slice(-9).replace(/0+$/, '').padEnd(2, '0');

    return `$${dollars}.${fraction}`;
}
