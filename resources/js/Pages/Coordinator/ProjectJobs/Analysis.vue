<template>
    <AppLayout title="ジョブ分析">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ分析 — {{ job.title || '案件' }}</h2>
                <div>
                    <button
                        type="button"
                        @click="goShow"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        <span>詳細に戻る</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold">この案件に紐づく割り当てと実際の作業</h3>

                    <div v-if="!hasEvents" class="text-sm text-gray-600">割り当てに紐づく実作業の予定・記録はありません。</div>

                    <div v-else class="space-y-6">
                        <!-- Assignment groups: detailed per-assignment tables (reuses stage-detail layout) -->
                        <div>
                            <h4 class="mb-2 font-semibold">割り当て別（詳細）</h4>
                            <div class="space-y-6">
                                <div v-for="group in assignmentGroups" :key="`assign-group-${group.name}`" class="overflow-auto">
                                    <div class="mb-2 flex items-center justify-between">
                                        <div class="font-semibold">割り当て: {{ group.name || '（割り当て未設定）' }}</div>
                                        <div class="text-sm text-gray-600">合計: {{ formatDurationFromMinutes(group.totalMinutes) }}</div>
                                    </div>

                                    <table class="min-w-full text-sm text-gray-700">
                                        <thead>
                                            <tr class="border-b bg-white">
                                                <th class="px-3 py-2 text-left">ユーザーID</th>
                                                <th class="px-3 py-2 text-left">ユーザー名</th>
                                                <th class="px-3 py-2 text-left">割当名</th>
                                                <th class="px-3 py-2 text-left">ステータス</th>
                                                <th class="px-3 py-2 text-left">開始</th>
                                                <th class="px-3 py-2 text-left">終了</th>
                                                <th class="px-3 py-2 text-left">作業合計</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="row in group.items"
                                                :key="`assign-row-${group.name}-${row.assignment_id}-${row.start}`"
                                                class="border-b"
                                            >
                                                <td class="px-3 py-2">{{ row.user_id ?? '-' }}</td>
                                                <td class="px-3 py-2">{{ row.user_name || '-' }}</td>
                                                <td class="px-3 py-2">{{ row.assignment_name || '-' }}</td>
                                                <td class="px-3 py-2">{{ row.status_name || '-' }}</td>
                                                <td class="px-3 py-2">{{ formatDateTime(row.start) }}</td>
                                                <td class="px-3 py-2">{{ formatDateTime(row.end) }}</td>
                                                <td class="px-3 py-2">{{ formatDuration(row.start, row.end) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Stage summary -->
                        <div>
                            <h4 class="mb-2 font-semibold">ステージ別合計</h4>
                            <div class="overflow-auto">
                                <table class="mb-4 min-w-full text-sm text-gray-700">
                                    <thead>
                                        <tr class="border-b bg-white">
                                            <th class="px-3 py-2 text-left">ステージ</th>
                                            <th class="px-3 py-2 text-left">合計時間</th>
                                            <th class="px-3 py-2 text-left">イベント数</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="s in stageSummaries" :key="`stage-${s.id || 'none'}`" class="border-b">
                                            <td class="px-3 py-2">{{ s.name || '（ステージ未設定）' }}</td>
                                            <td class="px-3 py-2">{{ formatDurationFromMinutes(s.totalMinutes) }}</td>
                                            <td class="px-3 py-2">{{ s.count }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Worker summary -->
                        <div>
                            <h4 class="mb-2 font-semibold">作業者別合計</h4>
                            <div class="overflow-auto">
                                <table class="mb-4 min-w-full text-sm text-gray-700">
                                    <thead>
                                        <tr class="border-b bg-white">
                                            <th class="px-3 py-2 text-left">ユーザーID</th>
                                            <th class="px-3 py-2 text-left">ユーザー名</th>
                                            <th class="px-3 py-2 text-left">合計時間</th>
                                            <th class="px-3 py-2 text-left">イベント数</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="w in workerSummaries" :key="`worker-${w.id || 'unknown'}`" class="border-b">
                                            <td class="px-3 py-2">{{ w.id ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ w.name || '（ユーザー不明）' }}</td>
                                            <td class="px-3 py-2">{{ formatDurationFromMinutes(w.totalMinutes) }}</td>
                                            <td class="px-3 py-2">{{ w.count }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Per-stage details -->
                        <div v-for="stage in stageGroups" :key="`stage-detail-${stage.id || 'none'}`" class="overflow-auto">
                            <div class="mb-2 flex items-center justify-between">
                                <div class="font-semibold">ステージ: {{ stage.name || '（未設定）' }}</div>
                                <div class="text-sm text-gray-600">合計: {{ formatDurationFromMinutes(stage.totalMinutes) }}</div>
                            </div>

                            <table class="min-w-full text-sm text-gray-700">
                                <thead>
                                    <tr class="border-b bg-white">
                                        <th class="px-3 py-2 text-left">ユーザーID</th>
                                        <th class="px-3 py-2 text-left">ユーザー名</th>
                                        <th class="px-3 py-2 text-left">割当名</th>
                                        <th class="px-3 py-2 text-left">ステータス</th>
                                        <th class="px-3 py-2 text-left">開始</th>
                                        <th class="px-3 py-2 text-left">終了</th>
                                        <th class="px-3 py-2 text-left">作業合計</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in stage.items" :key="`ae-${row.assignment_id}-${row.start}`" class="border-b">
                                        <td class="px-3 py-2">{{ row.user_id ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ row.user_name || '-' }}</td>
                                        <td class="px-3 py-2">{{ row.assignment_name || '-' }}</td>
                                        <td class="px-3 py-2">{{ row.status_name || '-' }}</td>
                                        <td class="px-3 py-2">{{ formatDateTime(row.start) }}</td>
                                        <td class="px-3 py-2">{{ formatDateTime(row.end) }}</td>
                                        <td class="px-3 py-2">{{ formatDuration(row.start, row.end) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const job = page.props.job || {};
const assignmentEvents = page.props.assignmentEvents || [];

const assignmentEventsArr = computed(() => (Array.isArray(assignmentEvents) ? assignmentEvents : []));

const groupedAssignments = computed(() => {
    const map = {};
    assignmentEventsArr.value.forEach((row) => {
        const name = row.assignment_name || `assignment-${row.assignment_id || 'unknown'}`;
        if (!map[name]) map[name] = { name, items: [], totalMinutes: 0 };
        map[name].items.push(row);
        try {
            const s = row.start ? new Date(row.start) : null;
            const e = row.end ? new Date(row.end) : null;
            if (s && e && !isNaN(s.getTime()) && !isNaN(e.getTime())) {
                let diff = Math.round((e - s) / 60000);
                if (diff < 0) diff = 0;
                map[name].totalMinutes += diff;
            }
        } catch (err) {
            // ignore
        }
    });
    return Object.keys(map).map((k) => {
        const g = map[k];
        g.items.sort((a, b) => {
            if (!a.start) return 1;
            if (!b.start) return -1;
            return new Date(a.start) - new Date(b.start);
        });
        return g;
    });
});

// Assignment groups with per-user summaries
const assignmentGroups = computed(() => {
    const map = {};
    assignmentEventsArr.value.forEach((row) => {
        const name = row.assignment_name || `assignment-${row.assignment_id || 'unknown'}`;
        if (!map[name]) map[name] = { name, items: [], totalMinutes: 0, usersMap: {} };
        map[name].items.push(row);
        try {
            const s = row.start ? new Date(row.start) : null;
            const e = row.end ? new Date(row.end) : null;
            if (s && e && !isNaN(s.getTime()) && !isNaN(e.getTime())) {
                let diff = Math.round((e - s) / 60000);
                if (diff < 0) diff = 0;
                map[name].totalMinutes += diff;
                const uid = row.user_id ?? 'unknown';
                if (!map[name].usersMap[uid]) map[name].usersMap[uid] = { id: uid, name: row.user_name || null, totalMinutes: 0, count: 0 };
                map[name].usersMap[uid].totalMinutes += diff;
                map[name].usersMap[uid].count += 1;
            } else {
                // still increment event count even if duration unknown
                const uid = row.user_id ?? 'unknown';
                if (!map[name].usersMap[uid]) map[name].usersMap[uid] = { id: uid, name: row.user_name || null, totalMinutes: 0, count: 0 };
                map[name].usersMap[uid].count += 1;
            }
        } catch (err) {
            // ignore
        }
    });

    return Object.keys(map).map((k) => {
        const g = map[k];
        g.items.sort((a, b) => {
            if (!a.start) return 1;
            if (!b.start) return -1;
            return new Date(a.start) - new Date(b.start);
        });
        g.users = Object.keys(g.usersMap).map((uid) => g.usersMap[uid]);
        // sort users by totalMinutes desc
        g.users.sort((a, b) => b.totalMinutes - a.totalMinutes);
        return g;
    });
});

const hasEvents = computed(() => assignmentEventsArr.value.length > 0);

function goShow() {
    if (!job || !job.id) return;
    router.get(route('coordinator.project_jobs.show', job.id));
}

// Stage summaries: { id, name, totalMinutes, count }
const stageSummaries = computed(() => {
    const map = {};
    assignmentEventsArr.value.forEach((row) => {
        const id = row.stage_id ?? 'none';
        const name = row.stage_name ?? null;
        if (!map[id]) map[id] = { id, name, totalMinutes: 0, count: 0, items: [] };
        map[id].items.push(row);
        map[id].count += 1;
        try {
            const s = row.start ? new Date(row.start) : null;
            const e = row.end ? new Date(row.end) : null;
            if (s && e && !isNaN(s.getTime()) && !isNaN(e.getTime())) {
                let diff = Math.round((e - s) / 60000);
                if (diff < 0) diff = 0;
                map[id].totalMinutes += diff;
            }
        } catch (err) {
            // ignore
        }
    });
    return Object.keys(map).map((k) => map[k]);
});

// Worker summaries: { id, name, totalMinutes, count }
const workerSummaries = computed(() => {
    const map = {};
    assignmentEventsArr.value.forEach((row) => {
        const id = row.user_id ?? 'unknown';
        const name = row.user_name ?? null;
        if (!map[id]) map[id] = { id, name, totalMinutes: 0, count: 0 };
        map[id].count += 1;
        try {
            const s = row.start ? new Date(row.start) : null;
            const e = row.end ? new Date(row.end) : null;
            if (s && e && !isNaN(s.getTime()) && !isNaN(e.getTime())) {
                let diff = Math.round((e - s) / 60000);
                if (diff < 0) diff = 0;
                map[id].totalMinutes += diff;
            }
        } catch (err) {
            // ignore
        }
    });
    return Object.keys(map).map((k) => map[k]);
});

// Stage groups (detailed list per stage)
const stageGroups = computed(() => {
    const map = {};
    assignmentEventsArr.value.forEach((row) => {
        const id = row.stage_id ?? 'none';
        const name = row.stage_name ?? null;
        if (!map[id]) map[id] = { id, name, items: [], totalMinutes: 0 };
        map[id].items.push(row);
        try {
            const s = row.start ? new Date(row.start) : null;
            const e = row.end ? new Date(row.end) : null;
            if (s && e && !isNaN(s.getTime()) && !isNaN(e.getTime())) {
                let diff = Math.round((e - s) / 60000);
                if (diff < 0) diff = 0;
                map[id].totalMinutes += diff;
            }
        } catch (err) {
            // ignore
        }
    });
    return Object.keys(map).map((k) => {
        const g = map[k];
        g.items.sort((a, b) => {
            if (!a.start) return 1;
            if (!b.start) return -1;
            return new Date(a.start) - new Date(b.start);
        });
        return g;
    });
});

function formatDateTime(v) {
    if (!v) return '-';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return String(v);
        return d.toLocaleString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return String(v);
    }
}

function formatDuration(startV, endV) {
    if (!startV || !endV) return '-';
    try {
        const s = new Date(startV);
        const e = new Date(endV);
        if (isNaN(s.getTime()) || isNaN(e.getTime())) return '-';
        let diff = Math.round((e - s) / 60000); // minutes
        if (diff < 0) diff = 0;
        const h = Math.floor(diff / 60);
        const m = diff % 60;
        if (h > 0) return `${h}時間${m}分`;
        return `${m}分`;
    } catch (err) {
        return '-';
    }
}

function formatDurationFromMinutes(minutes) {
    if (!minutes && minutes !== 0) return '-';
    const m = Math.max(0, Math.round(Number(minutes) || 0));
    const h = Math.floor(m / 60);
    const mm = m % 60;
    if (h > 0) return `${h}時間${mm}分`;
    return `${mm}分`;
}
</script>

<style scoped>
/* small table tweaks can go here */
</style>
