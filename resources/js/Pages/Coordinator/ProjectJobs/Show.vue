<!--
 この画面は「プロジェクトジョブ登録フロー」のshow（詳細・確認）用です。
 1. 最初は詳細までを登録し、登録後にこの画面で確認・案内を出す。
 2. 「続いてメンバーを登録しますか？」の確認を出し、OKならProjectTeamMember/indexへ遷移。
 3. スケジュール登録はその後に実装予定。
 teammember/scheduleはnullのまま、あとで登録します。
-->

<template>
    <AppLayout title="プロジェクトジョブ作成">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <div class="mx-auto max-w-4xl rounded bg-white p-6 shadow">
            <h1 class="mb-6 text-2xl font-bold">プロジェクトジョブ作成</h1>

            <div class="mb-4">
                <table class="min-w-full table-auto border">
                    <tbody>
                        <tr class="border-b">
                            <th class="w-40 px-4 py-2 text-left">伝票番号</th>
                            <td class="px-4 py-2">{{ job.jobcode || '-' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left">案件タイトル</th>
                            <td class="px-4 py-2">{{ job.title || job.name || '-' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left">担当ユーザー</th>
                            <td class="px-4 py-2">{{ job.user?.name || '-' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left">クライアント</th>
                            <td class="px-4 py-2">{{ job.client?.name || '-' }}</td>
                        </tr>
                        <tr>
                            <th class="px-4 py-2 text-left align-top">詳細</th>
                            <td class="whitespace-pre-wrap px-4 py-2">
                                {{ job.detail ? (typeof job.detail === 'string' ? job.detail : JSON.stringify(job.detail)) : '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                <div class="mb-4">
                    <h3 class="mb-1 font-semibold">スケジュール設定</h3>
                    <div class="flex items-center gap-4">
                        <div
                            :class="[
                                'status-box w-32 rounded px-4 py-2',
                                hasScheduleFlag ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500',
                            ]"
                        >
                            {{ hasScheduleFlag ? '決定済み' : '未設定' }}
                        </div>
                        <button
                            type="button"
                            :class="['rounded px-4 py-2', hasScheduleFlag ? 'bg-gray-200 text-gray-800' : 'bg-blue-100 text-blue-700']"
                            @click="goSchedule"
                        >
                            {{ hasScheduleFlag ? 'スケジュール詳細' : 'スケジュール登録' }}
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="mb-1 font-semibold">メンバー選定</h3>
                    <div class="flex items-center gap-4">
                        <div :class="['status-box w-32 rounded px-4 py-2', hasMembers ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500']">
                            {{ hasMembers ? '決定済み' : '未設定' }}
                        </div>
                        <button
                            type="button"
                            :class="['rounded px-4 py-2', hasMembers ? 'bg-gray-200 text-gray-800' : 'bg-green-100 text-green-700']"
                            @click="hasMembers ? openMembersModal() : goProjectTeammember()"
                        >
                            {{ hasMembers ? 'メンバー詳細' : 'メンバー登録' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="button" class="rounded bg-yellow-600 px-6 py-2 text-white hover:bg-yellow-700" @click="goEdit">編集</button>
                <button type="button" class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" @click="goJobAssign">
                    ジョブ割り当て
                </button>
                <button type="button" class="rounded bg-gray-300 px-4 py-2 text-gray-800 hover:bg-gray-400" @click="backToIndex">一覧に戻る</button>
                <button type="button" class="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700" @click="goAnalysis">ジョブ分析</button>
            </div>
            <!-- Assignment events moved to Analysis page -->

            <!-- (表示専用) クライアント検索モーダルは非表示 -->

            <!-- Members modal -->
            <div v-if="showMembersModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                <div class="w-3/4 max-w-3xl rounded bg-white p-6">
                    <h3 class="mb-4 text-lg font-semibold">メンバー一覧</h3>
                    <div class="max-h-72 overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500">ID</th>
                                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500">名前</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="m in members" :key="m.id">
                                    <td class="px-6 py-2 text-sm text-gray-700">{{ m.user ? m.user.id : m.user_id }}</td>
                                    <td class="px-6 py-2 text-sm font-medium text-gray-900">{{ m.user ? m.user.name : '（ユーザー情報なし）' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end space-x-2">
                        <button class="rounded bg-gray-200 px-4 py-2" @click="closeMembersModal">閉じる</button>
                        <button class="rounded bg-blue-600 px-4 py-2 text-white" @click="editMembers">編集</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const job = page.props.job || {};

// allow server to pass an explicit hasSchedule flag (more reliable)
const serverHasSchedule = page.props.hasSchedule;
// job.schedule in DB may be stored as JSON/array or boolean; normalize check as fallback
const computedHasSchedule = computed(() => {
    const s = job.schedule;
    if (!s) return false;
    if (typeof s === 'boolean') return s === true;
    if (Array.isArray(s)) return s.length > 0;
    if (typeof s === 'object') return Object.keys(s).length > 0;
    return Boolean(s);
});

const hasScheduleFlag = computed(() => {
    if (typeof serverHasSchedule !== 'undefined') return Boolean(serverHasSchedule);
    return computedHasSchedule.value;
});
const members = page.props.members || [];
const showMembersModal = ref(false);
const hasMembers = computed(() => Array.isArray(members) && members.length > 0);

function openMembersModal() {
    showMembersModal.value = true;
}

function closeMembersModal() {
    showMembersModal.value = false;
}

onMounted(() => {
    const flags = page.props.registerFlags || [];
    const createdJobId = page.props.jobid || null;
    if (flags.length && createdJobId) {
        if (flags.includes('teammember')) {
            if (confirm('プロジェクトを登録しました。続いてメンバーを登録しますか？')) {
                const url = route('coordinator.project_team_members.create') + '?project_job_id=' + createdJobId;
                router.visit(url);
                return;
            }
        }
    }
});

function goSchedule() {
    const id = job.id || null;
    if (id) router.visit(route('coordinator.project_jobs.schedule', { projectJob: id }));
}

function goProjectTeammember() {
    const id = job.id || null;
    if (id) {
        const url = route('coordinator.project_team_members.create') + '?project_job_id=' + id;
        router.visit(url);
    } else {
        router.visit(route('coordinator.project_team_members.create'));
    }
}

function goEdit() {
    const id = job.id || null;
    if (id) router.visit(route('coordinator.project_jobs.edit', { projectJob: id }));
}

function backToIndex() {
    router.visit(route('coordinator.project_jobs.index'));
}

function editMembers() {
    // Gather selected user ids from members prop
    const selectedIds = members.filter((m) => m.user).map((m) => m.user.id);
    const id = job.id || null;
    let url = route('coordinator.project_team_members.create');
    const params = [];
    if (id) params.push('project_job_id=' + encodeURIComponent(id));
    if (selectedIds.length) params.push('selected_user_ids=' + encodeURIComponent(selectedIds.join(',')));
    if (params.length) url += '?' + params.join('&');
    router.visit(url);
}
function goJobAssign() {
    const id = job.id || null;
    if (id) router.visit(route('coordinator.project_jobs.assignments.create', { projectJob: id }));
}

function goAnalysis() {
    const id = job.id || null;
    if (id) router.visit(route('coordinator.project_jobs.analysis', { projectJob: id }));
}

// assignment events passed from server
const assignmentEvents = computed(() => {
    return Array.isArray(page.props.assignmentEvents) ? page.props.assignmentEvents : [];
});

// Group assignmentEvents by assignment_name (fallback to assignment_id) and sum total minutes per group
const groupedAssignments = computed(() => {
    const map = {};
    assignmentEvents.value.forEach((row) => {
        const name = row.assignment_name || `assignment-${row.assignment_id || 'unknown'}`;
        if (!map[name]) map[name] = { name, items: [], totalMinutes: 0 };
        map[name].items.push(row);

        // accumulate minutes if start/end are valid
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

    // convert to array and sort each group's items by start
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

function formatDurationFromMinutes(minutes) {
    if (!minutes && minutes !== 0) return '-';
    const m = Math.max(0, Math.round(Number(minutes) || 0));
    const h = Math.floor(m / 60);
    const mm = m % 60;
    if (h > 0) return `${h}時間${mm}分`;
    return `${mm}分`;
}

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
</script>

<style scoped>
/* 必要に応じてスタイル追加 */
</style>
