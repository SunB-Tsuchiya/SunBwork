<template>
    <AppLayout :title="`ジョブ割り当て一覧 - ${projectJob.title}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ割り当て一覧：{{ projectJob.title }}</h1>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="mb-4 flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="タイトル/詳細/担当で検索"
                        class="w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-blue-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
                <!-- <div class="mb-4 md:ml-4 md:mt-0">
                    <button @click.prevent="gotoCreate" class="rounded bg-blue-600 px-4 py-2 text-white">新規ジョブ割り当て</button>
                </div> -->
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('created_at')">
                                作成日 <SortIcon :active="sortBy === 'created_at'" :dir="sortDir" />
                            </th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('title')">
                                タイトル <SortIcon :active="sortBy === 'title'" :dir="sortDir" />
                            </th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('user')">
                                担当 <SortIcon :active="sortBy === 'user'" :dir="sortDir" />
                            </th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('desired_end_date')">
                                終了希望日 / 時刻 <SortIcon :active="sortBy === 'desired_end_date'" :dir="sortDir" />
                            </th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('estimated_hours')">
                                見積時間 <SortIcon :active="sortBy === 'estimated_hours'" :dir="sortDir" />
                            </th>
                            <th class="border px-4 py-2">依頼</th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('assigned')">
                                Status <SortIcon :active="sortBy === 'assigned'" :dir="sortDir" />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in assignments.data" :key="a.id" class="cursor-pointer hover:bg-gray-50" @click.prevent="rowClick(a)">
                            <td class="border px-4 py-2">
                                {{ formatDate(a.created_at) }}
                            </td>
                            <td class="border px-4 py-2">
                                <div class="font-semibold">{{ a.title }}</div>
                                <div class="text-sm text-gray-600">{{ projectJob.client?.name || '-' }}</div>
                            </td>
                            <td class="border px-4 py-2">{{ a.user?.name || '-' }}</td>
                            <td class="border px-4 py-2">
                                {{ a.desired_end_date || '-' }}
                                <span v-if="a.desired_time">
                                    {{ formatTime(a.desired_time) }}
                                </span>
                            </td>
                            <td class="border px-4 py-2">{{ formatEstimatedHours(a.estimated_hours) }}</td>
                            <td class="border px-4 py-2">
                                <button
                                    :disabled="a.assigned"
                                    @click.stop.prevent="sendRequest(a)"
                                    :class="['rounded px-3 py-1 text-white', a.assigned ? 'cursor-not-allowed bg-gray-400' : 'bg-blue-500']"
                                >
                                    {{ a.assigned ? '発信済み' : '発信' }}
                                </button>
                            </td>
                            <td class="border px-4 py-2">{{ statusText(a) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- pagination -->
            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600">全 {{ assignments.total }} 件</div>
                <div class="flex items-center space-x-2">
                    <button
                        :disabled="!assignments.prev_page_url"
                        @click.prevent="goto(assignments.prev_page_url)"
                        class="rounded border px-3 py-1 disabled:opacity-50"
                    >
                        前へ
                    </button>
                    <div class="text-sm">{{ assignments.current_page }} / {{ assignments.last_page }}</div>
                    <button
                        :disabled="!assignments.next_page_url"
                        @click.prevent="goto(assignments.next_page_url)"
                        class="rounded border px-3 py-1 disabled:opacity-50"
                    >
                        次へ
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { h } from 'vue';
const { projectJob, assignments } = defineProps({ projectJob: Object, assignments: Object });
const page = usePage();
// local search model bound to server-provided q
page.props.q_model = page.props.q || '';

// compute safe create URL (Ziggy may not include the route in some builds)
let createUrl = '';
try {
    createUrl = route('coordinator.project_jobs.assignments.create', { projectJob: projectJob.id });
} catch (err) {
    // fallback to manual path
    createUrl = `/coordinator/project_jobs/${projectJob.id}/assignments/create`;
}

// reactive sort state from server-provided props
const sortBy = page.props.sort_by || 'created_at';
const sortDir = page.props.sort_dir || 'desc';

function SortIcon({ active, dir }) {
    if (!active) return h('span', { class: 'opacity-30' }, '↕');
    return dir === 'asc' ? h('span', { class: 'inline-block' }, '↑') : h('span', { class: 'inline-block' }, '↓');
}

function changeSort(column) {
    let dir = 'desc';
    if (sortBy === column) {
        dir = sortDir === 'desc' ? 'asc' : 'desc';
    }
    // navigate with query params
    router.get(
        route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id }),
        { sort_by: column, sort_dir: dir, q: page.props.q_model },
        { preserveState: false, replace: true },
    );
}

function goto(url) {
    if (!url) return;
    router.visit(url, { preserveState: false });
}

function rowClick(a) {
    try {
        let url;
        try {
            url = route('coordinator.project_jobs.assignments.show', { projectJob: projectJob.id, assignment: a.id });
        } catch (zigErr) {
            // Ziggy route not available in the client manifest; build fallback path
            // Ziggy route missing — fallback to manual path
            url = `/coordinator/project_jobs/${projectJob.id}/assignments/${a.id}`;
        }
        router.visit(url, { preserveState: false });
    } catch (err) {
        // rowClick error suppressed in production
    }
}

function search() {
    router.get(
        route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id }),
        { q: page.props.q_model, sort_by: sortBy, sort_dir: sortDir },
        { preserveState: false, replace: false },
    );
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

function gotoCreate() {
    if (!createUrl) return;
    router.visit(createUrl, { preserveState: false });
}

function deleteAssignment(a) {
    if (!confirm('この割当を本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(route('coordinator.project_jobs.assignments.destroy', { projectJob: projectJob.id, assignment: a.id }), {
        onSuccess: () => {
            // refresh the page data
            router.reload();
        },
        onError: (errors) => {
            console.error('deleteAssignment error', errors);
            alert('削除に失敗しました。詳細はコンソールを確認してください。');
        },
    });
}

function formatTime(t) {
    if (!t) return '';
    // t may be '09:00:00' or '09:00' or '09:00:00.000000Z'
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}

function formatDate(d) {
    if (!d) return '-';
    const s = String(d);
    // Normalize ISO-ish or date-time strings to YYYY-MM-DD
    if (s.includes('T')) return s.split('T')[0];
    if (s.includes(' ')) return s.split(' ')[0];
    return s;
}

function formatEstimatedHours(h) {
    if (h === null || h === undefined || h === '') return '-';
    const n = Number(h);
    if (Number.isNaN(n)) return '-';
    // show 1.5 as "1.5h" or integer as "1h"
    return Number.isInteger(n) ? `${n}h` : `${n}h`;
}

function statusText(a) {
    if (!a.assigned) return '未発信';
    // assigned にフラグが立っていて accepted が false の場合は「送信済み」と表示
    if (a.assigned && !a.accepted) return '送信済み';
    if (a.accepted) return '受諾';
    return '-';
}

function sendRequest(a) {
    if (!confirm('このジョブを発信しますか？')) return;

    // Build payload compatible with MessagesController@store
    // Messages API expects: { to: [userId,...], subject: string|null, body: string|null, attachments: [] }
    const toUserId = a.user_id || a.user?.id;
    // Build a readable details block for the email body
    const assignedName = a.user?.name || a.assigned_user_name || '（未割当）';
    const detailsText = a.details || a.detail || a.description || a.body || '';
    const start = a.desired_start_date || a.preferred_date || a.start_date || '-';
    const end = a.desired_end_date || a.end_date || '-';

    const payload = {
        to: toUserId ? [toUserId] : [],
        // Use provided subject if given; otherwise default to the assignment title (no auto-prefix)
        subject: a.title || null,
        body: `割り当て依頼\nジョブ: ${projectJob.title}\n割り当て: ${a.title}\n\n担当ユーザー: ${assignedName}\n詳細:\n${detailsText || '（詳細なし）'}\n\n希望開始日: ${start}\n希望終了日: ${end}\n\nアプリで詳しい情報を確認できます。`,
    };

    // Use JobBox store route so job-related messages are stored separately and notifications are sent
    const jbPayload = {
        project_job_assignment_id: a.id,
        to: toUserId ? [toUserId] : [],
        // Preserve explicit subject if provided; otherwise use assignment title without prefix
        subject: payload.subject || a.title || null,
        body: payload.body || null,
        attachments: [],
    };

    router.post(route('coordinator.project_jobs.jobbox.store', { projectJob: projectJob.id }), jbPayload, {
        onSuccess: () => {
            a.assigned = true;
            alert('発信しました（JobBox 経由）。受信者に通知されます。');
        },
        onError: (errors) => {
            console.error('sendRequest error', errors);
            alert('発信に失敗しました。詳細はコンソールを確認してください。');
        },
    });
}
</script>

<style scoped></style>
