import axios from 'axios';
import { route } from 'ziggy-js';

// Diary timelines show ordinary events and the owner's trial work entries together.
// The latter have a separate API and are display-only here; editing stays in the calendar.
export async function fetchDiaryTimelineEvents(date, userId, includeQuickWork = false) {
    const [ordinary, quick] = await Promise.allSettled([
        axios.get(route('events.index'), { params: { date, user_id: userId } }),
        includeQuickWork
            ? axios.get(route('schedule.department_work.mine'), { params: { start: date, end: date } })
            : Promise.resolve({ data: { events: [] } }),
    ]);

    const ordinaryEvents = ordinary.status === 'fulfilled' && Array.isArray(ordinary.value.data)
        ? ordinary.value.data.map((event) => ({
            id: event.id ?? event.event_id ?? event._id ?? null,
            title: event.title || event.name || '(無題)',
            start: event.start,
            end: event.end || event.start,
            allDay: !!event.allDay || !!event.all_day,
            color: event.color || event.backgroundColor || '#2563eb',
            description: event.description || event.extendedProps?.description || '',
        }))
        : [];
    const quickEvents = quick.status === 'fulfilled' && Array.isArray(quick.value.data?.events)
        ? quick.value.data.events.filter((event) => event.starts_at && event.ends_at).map((event) => ({
            id: event.id,
            title: event.title || '作業予定',
            start: event.starts_at,
            end: event.ends_at,
            allDay: false,
            color: '#fbcfe8',
            textColor: '#831843',
            borderColor: '#f472b6',
            tooltip: `${event.title || '作業予定'}（変更はカレンダーから）`,
            readOnly: true,
        }))
        : [];

    return {
        events: [...ordinaryEvents, ...quickEvents],
        failed: ordinary.status === 'rejected' || (includeQuickWork && quick.status === 'rejected'),
    };
}

export function diaryTimelineHours(events, defaultStart, defaultEnd) {
    let start = defaultStart;
    let end = defaultEnd;
    for (const event of events) {
        if (!event.readOnly || !event.start || !event.end) continue;
        const from = new Date(event.start);
        const to = new Date(event.end);
        if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) continue;
        start = Math.min(start, from.getHours());
        end = Math.max(end, to.getHours() + (to.getMinutes() > 0 ? 1 : 0));
    }
    return { start: Math.max(0, start), end: Math.min(24, end) };
}
