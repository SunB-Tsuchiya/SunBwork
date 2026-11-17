/** Preserve the displayed minute precision; add hours without changing recorded values. */
export function recordedTime(value) {
    const numeric = Number(value || 0);
    const minutes = Number.isFinite(numeric) ? Number(Math.max(0, numeric).toFixed(1)) : 0;
    const tenths = Math.round(minutes * 10);
    const hours = Math.floor(tenths / 600);
    const remainder = (tenths % 600) / 10;
    return `${minutes}分（${hours}時間${remainder}分）`;
}
