import { addDays } from './clerkCalendarDates.js';

export function duplicateEventForm(source) {
    return {
        id: null,
        title: source.title,
        description: source.description,
        start_date: source.start_date,
        end_date: source.end_date,
        start_time: source.start_time || '00:00',
        end_time: source.end_time || '00:00',
        all_day: source.all_day,
        color_key: source.color_key,
        completed: false,
        schedule_rule_id: null,
    };
}

export function shiftedDuplicateEnd(oldStart, newStart, end) {
    if (!oldStart || !newStart || !end) return end;
    const delta = (Date.parse(`${newStart}T00:00:00Z`) - Date.parse(`${oldStart}T00:00:00Z`)) / 86400000;
    return Number.isFinite(delta) ? addDays(end, delta) : end;
}
