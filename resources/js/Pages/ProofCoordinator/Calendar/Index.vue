<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import jaLocale from '@fullcalendar/core/locales/ja';
import { computed } from 'vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
});

const statusColor = {
    pending:     '#9ca3af',
    assigned:    '#3b82f6',
    in_progress: '#f97316',
    completed:   '#22c55e',
};

const calendarEvents = computed(() =>
    props.events.map(e => ({
        id:    String(e.id),
        title: `${e.title}${e.proofreader ? ' (' + e.proofreader + ')' : ''}`,
        start: e.start,
        color: statusColor[e.status] ?? '#9ca3af',
        extendedProps: e,
    }))
);

const calendarOptions = computed(() => ({
    plugins:      [dayGridPlugin],
    initialView:  'dayGridMonth',
    locale:       jaLocale,
    events:       calendarEvents.value,
    headerToolbar: {
        left:   'prev,next today',
        center: 'title',
        right:  '',
    },
    eventContent: (arg) => ({
        html: `<div class="overflow-hidden text-ellipsis whitespace-nowrap px-1 text-xs">${arg.event.title}</div>`,
    }),
}));
</script>

<template>
    <AppLayout title="校正カレンダー">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正カレンダー（管理者）</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="calendar" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 凡例 -->
            <div class="mb-4 flex flex-wrap gap-3 text-xs">
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-gray-400"></span>依頼中</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-blue-500"></span>割り当て済み</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-orange-500"></span>校正中</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-green-500"></span>完了</span>
            </div>
            <FullCalendar :options="calendarOptions" />
        </div>
    </AppLayout>
</template>
