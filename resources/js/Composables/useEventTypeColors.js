// スケジュール機能: イベント種別スラッグ → 表示色マップ
// WeekView / DayView / MonthView など複数コンポーネントで共有する

export const EVENT_TYPE_COLORS = {
    conference:       { bg: '#3b82f6', text: '#fff', border: '#2563eb' },
    meeting_internal: { bg: '#10b981', text: '#fff', border: '#059669' },
    meeting_client:   { bg: '#0ea5e9', text: '#fff', border: '#0284c7' },
    client_visit:     { bg: '#8b5cf6', text: '#fff', border: '#7c3aed' },
    customer_visit:   { bg: '#f97316', text: '#fff', border: '#ea580c' },
    outing:           { bg: '#f59e0b', text: '#fff', border: '#d97706' },
    other:            { bg: '#6b7280', text: '#fff', border: '#4b5563' },
};

export const DEFAULT_OWN_COLOR     = { bg: '#3b82f6', text: '#fff', border: '#2563eb' };
export const DEFAULT_OVERLAY_COLOR = { bg: '#e5e7eb', text: '#374151', border: '#d1d5db' };

/**
 * イベントの表示色を返す
 * @param {object} ev - events 配列の要素 (is_own, event_item_type.slug を持つ)
 * @returns {{ bg: string, text: string, border: string }}
 */
export function evColor(ev) {
    if (!ev.is_own) return DEFAULT_OVERLAY_COLOR;
    const slug = ev.event_item_type?.slug;
    return EVENT_TYPE_COLORS[slug] ?? DEFAULT_OWN_COLOR;
}
