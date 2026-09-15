// 日付だけの計算はUTCで統一し、ブラウザのタイムゾーンに左右されないようにする。
export function addDays(key, days) {
    const date = new Date(`${key}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}
export function sunday(key) {
    return addDays(key, -new Date(`${key}T00:00:00Z`).getUTCDay());
}
export function monthStart(year, month) {
    return sunday(`${year}-${String(month).padStart(2, '0')}-01`);
}
export function stripRange(year) {
    return { start: addDays(monthStart(year, 1), -35), end: addDays(monthStart(year + 1, 1), 35) };
}
export function jstToday(now = new Date()) {
    return now.toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' });
}
export function weekday(key) {
    return new Date(`${key}T00:00:00Z`).getUTCDay();
}
export function dayClass(key, holidays) {
    if (holidays[key] || weekday(key) === 0) return 'clerk-holiday';
    return weekday(key) === 6 ? 'clerk-saturday' : '';
}
