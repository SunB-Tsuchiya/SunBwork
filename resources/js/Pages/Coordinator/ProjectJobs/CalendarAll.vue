<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import { computed, onMounted, ref } from 'vue';

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

function textColorFor(hex) {
    try {
        if (hex && hex.startsWith('#') && hex.length === 7) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            const lum = 0.2126 * (r / 255) + 0.7152 * (g / 255) + 0.0722 * (b / 255);
            return lum < 0.55 ? '#ffffff' : '#111827';
        }
    } catch (e) { /* ignore */ }
    return '#ffffff';
}

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
            const project = projectMap.value[s.project_job_id];
            const bg = s.color || '#2563eb';
            const startDate = fmtDate(s.start_date);
            const endDate   = s.end_date ? addDay(fmtDate(s.end_date)) : addDay(startDate);
            return {
                title:           s.name || '（無題）',
                start:           startDate,
                end:             endDate,
                allDay:          true,
                backgroundColor: bg,
                borderColor:     bg,
                textColor:       textColorFor(bg),
                extendedProps: {
                    schedule_id:    s.id,
                    project_job_id: s.project_job_id,
                    project_title:  project?.title ?? '',
                    progress:       s.progress ?? 0,
                },
            };
        })
);

function renderEventContent(arg) {
    const proj    = arg.event.extendedProps.project_title;
    const title   = arg.event.title;
    const progress = arg.event.extendedProps.progress;
    const safeTitle = title.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const safeProj  = proj.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return {
        html: `<div class="fc-event-inner" title="${safeProj}: ${safeTitle}">
                   <span class="fc-event-title">${safeTitle}</span>
                   ${progress > 0 ? `<span class="fc-event-progress"> ${progress}%</span>` : ''}
               </div>`,
    };
}

const calendarOptions = computed(() => ({
    plugins:       [dayGridPlugin],
    initialView:   'dayGridMonth',
    locale:        'ja',
    headerToolbar: {
        left:   'prev,next today',
        center: 'title',
        right:  'dayGridMonth,dayGridWeek',
    },
    dayMaxEvents: 4,
    moreLinkText: '件以上',
    height:       'auto',   // cells expand; scroll is handled by the wrapper div
    eventContent: renderEventContent,
    buttonText: {
        today: '今日',
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

const calendarOptionsFinal = computed(() => ({
    ...calendarOptions.value,
    events: filteredEvents.value,
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
    const todayStr = new Date().toISOString().split('T')[0];
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

        <div class="rounded bg-white p-6 shadow">

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
                    <template v-for="project in visibleProjects" :key="project.id">
                        <div
                            class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                            :style="{
                                backgroundColor: project.color + '22',
                                border: '1.5px solid ' + project.color,
                                color: project.color,
                            }"
                        >
                            <span
                                class="inline-block h-2.5 w-2.5 flex-shrink-0 rounded-sm"
                                :style="{ backgroundColor: project.color }"
                            ></span>
                            <span>{{ project.title }}</span>
                            <span v-if="project.client_name" class="ml-0.5 font-normal text-gray-500">
                                （{{ project.client_name }}）
                            </span>
                            <span v-if="project.completed" class="ml-1 text-gray-400">完了</span>
                        </div>
                    </template>
                    <div v-if="visibleProjects.length === 0" class="text-sm text-gray-400">
                        表示する案件がありません
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
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 100%;
    padding: 0 3px;
    font-size: 0.72rem;
    line-height: 1.4;
}
:deep(.fc-event-progress) {
    opacity: 0.75;
    font-size: 0.65rem;
}
</style>
