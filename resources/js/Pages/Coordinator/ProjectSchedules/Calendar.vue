<script setup>
import ProjectCalendar from '@/Components/ProjectCalendar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';
import axios from 'axios';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
    project: { type: Object, default: null },
    client: { type: Object, default: null },
    comments: { type: Array, default: () => [] },
    memos: { type: Array, default: () => [] },
});

// カレンダー連携設定を非同期で取得（calendar_linked=true の項目のみ）
const items = ref([]);
onMounted(async () => {
    if (!props.project) return;
    try {
        const res = await axios.get(route('coordinator.project_jobs.link_settings.index', { projectJob: props.project.id }));
        items.value = res.data.items ?? [];
    } catch (e) {
        // 取得失敗は無視
    }
});

// Convert schedules to FullCalendar events
const events = ref(
    (props.schedules || []).map((s) => {
        // start_date / end_date はバックエンドで date:Y-m-d キャストされるため YYYY-MM-DD で届く
        const startDateOnly = s.start_date ? String(s.start_date).split('T')[0] : null;
        const endDateOnly = s.end_date ? String(s.end_date).split('T')[0] : null;

        // FullCalendar の allDay イベントは end が exclusive なので +1日する
        let endForCalendar = endDateOnly;
        if (endDateOnly) {
            try {
                const parts = endDateOnly.split('-').map(Number);
                const d = new Date(parts[0], parts[1] - 1, parts[2]);
                d.setDate(d.getDate() + 1);
                endForCalendar = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            } catch (e) {
                endForCalendar = endDateOnly;
            }
        }

        const isCompleted = !!s.completed_at || (s.progress ?? 0) >= 100;
        const C = isCompleted
            ? { bg: '#dcfce7', border: '#15803d', text: '#14532d' }
            : { bg: '#dbeafe', border: '#1d4ed8', text: '#1e3a8a' };

        return {
            id: s.id,
            title: s.name ?? '',
            start: startDateOnly,
            end: endForCalendar,
            allDay: true,
            color: C.text,
            backgroundColor: C.bg,
            borderColor: C.border,
            textColor: C.text,
            description: s.description ?? '',
            extendedProps: {
                schedule_id: s.id,
                project_schedule_id: s.id,
                progress: s.progress ?? 0,
                description: s.description ?? '',
                completed_at: s.completed_at ?? null,
                original_color: s.color ?? null,
            },
        };
    }),
);

// Debug: log incoming props and computed events
// debug logging removed
// Provide the converted events to the Calendar component
const diaries = ref([]);

const weekPostsUrl = computed(() =>
    props.project ? route('coordinator.project_jobs.week_posts.index', { projectJob: props.project.id }) : null,
);
</script>

<template>
    <AppLayout title="案件スケジュール">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('coordinator.project_jobs.calendar')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← カレンダー一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">案件スケジュール</h2>
            </div>
        </template>
        <div class="rounded bg-white p-6 shadow">
                    <!-- Project header: show project name and client when available -->
                    <div class="mb-4 flex items-baseline justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ project ? project.name : '案件スケジュール' }}</h1>
                            <div class="text-sm text-gray-600">{{ client ? client.name : '' }}</div>
                        </div>
                    </div>

                    <ProjectCalendar
                        :diaries="diaries"
                        :events="events"
                        :comments="props.comments"
                        :memos="props.memos"
                        :project="props.project"
                        :schedules="props.schedules"
                        :items="items"
                        :weekPostsUrl="weekPostsUrl"
                        :uniformColors="true"
                        :showMemoButton="false"
                    />
        </div>
    </AppLayout>
</template>
