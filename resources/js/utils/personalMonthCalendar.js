import { CLERK_EVENT_COLORS } from '@/Components/Clerk/clerkEventColors';
export const personalItemTypes = { event: 'イベント', deadline: '締め切り', todo: 'TODO' };
export const personalItemColors = CLERK_EVENT_COLORS;
export function isCalendarJob(event) {
    return event._source === 'personal-work' || Boolean(event.project_job_assignment_id || event._work_entry?.id);
}
export function personalMonthEvents(items, start, end) {
    const result = [];
    for (const item of items) {
        if (!item.start_date) continue;
        const first = item.start_date > start ? item.start_date : start;
        const last = (item.end_date || item.start_date) < end ? (item.end_date || item.start_date) : end;
        for (let day = first; day <= last;) {
            const color = personalItemColors[item.color_key] || personalItemColors.indigo;
            result.push({ id: `personal-item-${item.id}-${day}`, title: `${item.completed ? '✓ ' : ''}${item.overdue ? '期限超過 ' : ''}${item.item_type === 'event' ? '' : item.item_type === 'todo' ? '□ ' : '【締切】'}${item.title}`,
                starts_at: `${day}T${day === item.start_date && item.start_time ? item.start_time : '00:00'}:00`,
                ends_at: `${day}T23:59:59`, is_own: true, _custom_color: color.hex, _custom_text_color: color.text,
                completed: item.completed, _personal_item: item });
            const next = new Date(day + 'T12:00:00'); next.setDate(next.getDate() + 1); day = next.toLocaleDateString('sv-SE');
        }
    }
    return result;
}
