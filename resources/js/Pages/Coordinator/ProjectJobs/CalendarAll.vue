<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    projects:  { type: Array, default: () => [] },
    schedules: { type: Array, default: () => [] },
});

// Build a lookup: project_job_id → project
const projectMap = computed(() => {
    const m = {};
    (props.projects || []).forEach(p => { m[p.id] = p; });
    return m;
});

// Unified colors: blue = normal, green = completed
const COLOR_NORMAL    = { bg: '#dbeafe', border: '#1d4ed8', text: '#1e3a8a' }; // blue-100/700/900
const COLOR_COMPLETED = { bg: '#dcfce7', border: '#15803d', text: '#14532d' }; // green-100/700/900

function fmtDate(v) {
    if (!v) return null;
    try { return String(v).split('T')[0]; } catch (e) { return String(v); }
}

function addDay(dateStr) {
    if (!dateStr) return null;
    try {
        const d = new Date(dateStr);
        d.setDate(d.getDate() + 1);
        return d.toISOString().split('T')[0];
    } catch (e) { return dateStr; }
}

const events = computed(() =>
    (props.schedules || [])
        .filter(s => s.start_date)
        .map(s => {
            const project   = projectMap.value[s.project_job_id];
            const isCompleted = !!s.completed_at || (s.progress ?? 0) >= 100;
            const c         = isCompleted ? COLOR_COMPLETED : COLOR_NORMAL;
            const startDate = fmtDate(s.start_date);
            const endDate   = s.end_date ? addDay(fmtDate(s.end_date)) : addDay(startDate);
            return {
                title:           s.name || '（無題）',
                start:           startDate,
                end:             endDate,
                allDay:          true,
                backgroundColor: c.bg,
                borderColor:     c.border,
                textColor:       c.text,
                extendedProps: {
                    schedule_id:    s.id,
                    project_job_id: s.project_job_id,
                    project_title:  project?.title ?? '',
                    progress:       s.progress ?? 0,
                    completed:      isCompleted,
                },
            };
        })
);

function renderEventContent(arg) {
    const proj  = arg.event.extendedProps.project_title;
    const title = arg.event.title;
    const safe  = (s) => s.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return {
        html: `<div class="fc-event-inner" title="${safe(proj)}: ${safe(title)}">
                   <div class="fc-event-project">${safe(proj)}</div>
                   <div class="fc-event-schedule">${safe(title)}</div>
               </div>`,
    };
}

const isMobileScreen = typeof window !== 'undefined' && window.innerWidth < 640;

const calendarOptions = computed(() => ({
    plugins:       [dayGridPlugin],
    initialView:   'dayGridMonth',
    locale:        'ja',
    headerToolbar: isMobileScreen ? {
        left:   'prev,next',
        center: 'title',
        right:  'dayGridMonth,dayGridWeek',
    } : {
        left:   'prev,next today',
        center: 'title',
        right:  'dayGridMonth,dayGridWeek',
    },
    dayMaxEvents: 4,
    moreLinkText: '件以上',
    height:       'auto',   // cells expand; scroll is handled by the wrapper div
    eventContent: renderEventContent,
    buttonText: {
        today: 'today',
        month: '月',
        week:  '週',
    },
}));

// Toggle for hiding completed projects
const hideCompleted = ref(false);

const visibleProjects = computed(() =>
    hideCompleted.value
        ? (props.projects || []).filter(p => !p.completed)
        : (props.projects || [])
);

const visibleIds = computed(() => new Set(visibleProjects.value.map(p => p.id)));

const filteredEvents = computed(() =>
    events.value.filter(e => visibleIds.value.has(e.extendedProps.project_job_id))
);

// Track current view range for legend filtering
const now = new Date();
const viewStart = ref(new Date(now.getFullYear(), now.getMonth(), 1).toLocaleDateString('sv-SE'));
const viewEnd   = ref(new Date(now.getFullYear(), now.getMonth() + 1, 0).toLocaleDateString('sv-SE'));

function onDatesSet(info) {
    viewStart.value = info.startStr.split('T')[0];
    viewEnd.value   = info.endStr.split('T')[0];
}

// Projects that have at least one schedule overlapping the current calendar view
const projectsInView = computed(() => {
    const start = viewStart.value;
    const end   = viewEnd.value;
    const inView = new Set();
    (props.schedules || []).forEach(s => {
        const sStart = s.start_date;
        const sEnd   = s.end_date || s.start_date;
        if (sStart && sStart <= end && sEnd >= start) {
            inView.add(s.project_job_id);
        }
    });
    return visibleProjects.value.filter(p => inView.has(p.id));
});

const calendarOptionsFinal = computed(() => ({
    ...calendarOptions.value,
    events: filteredEvents.value,
    datesSet: onDatesSet,
}));

// ─── Scroll to current week ────────────────────────────────────────────────
const calendarWrapperRef = ref(null);

onMounted(() => {
    // FullCalendar renders asynchronously; wait a tick before querying the DOM
    setTimeout(scrollToCurrentWeek, 150);
});

function scrollToCurrentWeek() {
    const wrapper = calendarWrapperRef.value;
    if (!wrapper) return;

    // Find today's cell by data-date attribute
    const todayStr = new Date().toLocaleDateString('sv-SE');
    const todayEl  = wrapper.querySelector(`[data-date="${todayStr}"]`);
    if (!todayEl) return;

    // Determine which row (week) today is in
    const allRows  = Array.from(wrapper.querySelectorAll('.fc-daygrid-body tbody tr'));
    const todayRow = todayEl.closest('tr');
    const rowIndex = allRows.indexOf(todayRow); // 0-based
    const weekNum  = rowIndex + 1;              // 1-based

    if (weekNum >= 4) {
        // Scroll the wrapper to the bottom so week 4/5 is fully visible
        wrapper.scrollTop = wrapper.scrollHeight;
    }
}
</script>

<template>
    <AppLayout title="案件カレンダー">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-green-700">案件カレンダー</h2>
        </template>
        <template #tabs>
            <CoordinatorNavigationTabs active="calendar" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- Legend -->
            <div class="mb-5">
                <div class="mb-2 flex items-center gap-3">
                    <h3 class="text-sm font-semibold text-gray-600">凡例（案件）</h3>
                    <label class="flex cursor-pointer items-center gap-1 text-xs text-gray-500">
                        <input type="checkbox" v-model="hideCompleted" class="rounded" />
                        完了済み案件を非表示
                    </label>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template v-for="project in projectsInView" :key="project.id">
                        <Link
                            :href="route('coordinator.project_schedules.calendar', { project_job_id: project.id })"
                            class="flex flex-col rounded-lg px-3 py-2.5 text-xs font-medium transition-opacity hover:opacity-75"
                            :style="project.completed
                                ? 'background-color:#dcfce7;border:1.5px solid #15803d;color:#14532d'
                                : 'background-color:#dbeafe;border:1.5px solid #1d4ed8;color:#1e3a8a'"
                        >
                            <span class="font-semibold leading-snug">
                                {{ project.title }}
                                <span v-if="project.client_name" class="font-normal opacity-70">（{{ project.client_name }}）</span>
                                <span v-if="project.completed" class="ml-1 opacity-60">完了</span>
                            </span>
                            <span class="mt-1 text-[0.6rem] opacity-55">＠ 詳細を見る</span>
                        </Link>
                    </template>
                    <div v-if="projectsInView.length === 0" class="text-sm text-gray-400">
                        この月に予定のある案件がありません
                    </div>
                </div>
            </div>

            <!-- Calendar — fixed-height scrollable wrapper -->
            <div ref="calendarWrapperRef" class="calendar-scroll-wrapper">
                <FullCalendar :options="calendarOptionsFinal" />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media (max-width: 639px) {
    :deep(.fc-toolbar-title) {
        font-size: 1rem;
        font-weight: 600;
    }
    :deep(.fc-button) {
        padding: 0.2rem 0.45rem !important;
        font-size: 0.75rem !important;
    }
    :deep(.fc-toolbar.fc-header-toolbar) {
        gap: 0.25rem;
    }
}

/* ── Scrollable container ────────────────────────────────────── */
/* Height ≈ 3 rows × row-height + ~80px header.
   Adjust row-height in .fc-daygrid-day-frame below.           */
.calendar-scroll-wrapper {
    height: 560px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

/* ── Double the cell (row) height ───────────────────────────── */
:deep(.fc-daygrid-day-frame) {
    min-height: 140px; /* approx 2× the default ~70px */
}

/* ── Event pill styling ──────────────────────────────────────── */
:deep(.fc-event-inner) {
    overflow: hidden;
    max-width: 100%;
    padding: 2px 4px;
    line-height: 1.35;
}
:deep(.fc-event-project) {
    font-size: 0.62rem;
    opacity: 0.75;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
:deep(.fc-event-schedule) {
    font-size: 0.72rem;
    font-weight: 600;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
</style>
