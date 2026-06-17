/**
 * Format a KWD amount for DISPLAY: thousand separators + 3 decimals (fils).
 *   formatMoney(12362.65) → "12,362.650"
 *
 * Display-only. NEVER feed the result back into an <input> value, v-model, or a
 * number parser (parseFloat/Number) — the thousand separators break parsing.
 * For those cases keep using Number(x).toFixed(3) (no separators).
 */
export function formatMoney(n) {
    return Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}
