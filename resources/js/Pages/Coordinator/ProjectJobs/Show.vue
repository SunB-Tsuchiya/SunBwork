<!--
 プロジェクトジョブ詳細ページ（リデザイン版）
 - タイトル行にアクションボタンをまとめる
 - スケジュール / メンバー をセクション形式で表示
-->

<template>
    <AppLayout title="案件詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <div class="rounded bg-white p-6 shadow">

            <!-- ── タイトル行 ──────────────────────────────────── -->
            <div class="mb-6 flex flex-wrap items-start gap-5">
                <!-- 左：クライアント / 案件名 / サブ情報 -->
                <div>
                    <p class="text-sm font-medium text-gray-400">
                        {{ job.client?.name || 'クライアント未設定' }}
                    </p>
                    <h1 class="mt-0.5 text-2xl font-bold text-gray-900">
                        {{ job.title || job.name || '（案件名なし）' }}
                    </h1>
                    <p class="mt-1 text-xs text-gray-500">
                        <span v-if="job.jobcode">伝票番号: {{ job.jobcode }}　</span>
                        <span v-if="job.user?.name">担当: {{ job.user.name }}</span>
                    </p>
                </div>

                <!-- タイトル横：アクションボタン群 -->
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button
                        type="button"
                        class="rounded bg-yellow-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-yellow-700"
                        @click="goEdit"
                    >編集</button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="goJobAssign"
                    >ジョブ割り当て</button>
                    <button
                        type="button"
                        class="rounded bg-teal-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-700"
                        @click="goAnalysis"
                    >ジョブ詳細</button>
                    <button
                        type="button"
                        class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                        @click="backToIndex"
                    >一覧に戻る</button>
                </div>
            </div>

            <!-- ── 詳細メモ（あれば表示） ─────────────────────── -->
            <div
                v-if="job.detail"
                class="mb-6 whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
            >{{ typeof job.detail === 'string' ? job.detail : JSON.stringify(job.detail) }}</div>

            <div class="divide-y divide-gray-100">

                <!-- ── スケジュールセクション ──────────────────── -->
                <section class="py-5">
                    <div class="mb-3 flex items-center gap-4">
                        <h3 class="font-semibold text-gray-800">スケジュール</h3>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded border border-blue-300 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50"
                                @click="goSchedule"
                            >{{ hasScheduleFlag ? '編集' : '登録' }}</button>
                            <button
                                v-if="schedules.length > 0"
                                type="button"
                                class="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                @click="goScheduleCalendar"
                            >カレンダー</button>
                        </div>
                    </div>

                    <div v-if="schedules.length > 0" class="overflow-x-auto">
                        <table class="min-w-full border text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">開始日</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">終了日</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">内容</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="s in schedules"
                                    :key="s.id"
                                    class="cursor-pointer hover:bg-blue-50"
                                    @click="goScheduleCalendar"
                                >
                                    <td class="border px-3 py-2 text-gray-700">{{ formatDate(s.start_date) }}</td>
                                    <td class="border px-3 py-2 text-gray-700">{{ formatDate(s.end_date) }}</td>
                                    <td class="border px-3 py-2 font-medium text-gray-900">{{ s.name || '-' }}</td>
                                    <td class="border px-3 py-2 text-gray-600">{{ truncate(s.description, 40) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400">スケジュール未登録</p>
                </section>

                <!-- ── メンバーセクション ──────────────────────── -->
                <section class="py-5">
                    <div class="mb-3 flex items-center gap-4">
                        <h3 class="font-semibold text-gray-800">メンバー</h3>
                        <button
                            type="button"
                            class="rounded border border-green-300 px-3 py-1 text-xs font-medium text-green-600 hover:bg-green-50"
                            @click="hasMembers ? editMembers() : goProjectTeammember()"
                        >{{ hasMembers ? '編集' : '登録' }}</button>
                    </div>

                    <div v-if="hasMembers" class="flex flex-wrap gap-2">
                        <div
                            v-for="m in members"
                            :key="m.id"
                            class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm"
                        >
                            <span class="inline-block h-2 w-2 rounded-full bg-green-400"></span>
                            <span class="font-medium text-gray-800">
                                {{ m.user ? m.user.name : '（ユーザー情報なし）' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">メンバー未登録</p>
                </section>

                <!-- ── 未発信の割当セクション ───────────────────── -->
                <section v-if="localUnsent.length > 0" class="py-5">
                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <h3 class="font-semibold text-gray-800">未発信の割当</h3>
                        <span class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                            {{ localUnsent.length }} 件
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作成日</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">担当者</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">締切希望日</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">見積時間</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="a in localUnsent"
                                    :key="a.id"
                                    class="cursor-pointer hover:bg-gray-50"
                                    @click.stop="goAssignmentEdit(a, $event)"
                                >
                                    <td class="border px-3 py-2 text-gray-600">{{ a.created_at || '-' }}</td>
                                    <td class="border px-3 py-2 font-medium text-gray-800">{{ a.title || '-' }}</td>
                                    <td class="border px-3 py-2 text-gray-700">{{ a.user_name || '-' }}</td>
                                    <td class="border px-3 py-2 text-gray-600">{{ a.desired_end_date || '-' }}</td>
                                    <td class="border px-3 py-2 text-gray-600">{{ a.estimated_hours != null ? a.estimated_hours + 'h' : '-' }}</td>
                                    <td class="border px-3 py-2">
                                        <button
                                            type="button"
                                            class="rounded bg-blue-500 px-3 py-1 text-xs font-medium text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="sendingIds.has(a.id)"
                                            @click.stop="sendUnsent(a)"
                                        >
                                            {{ sendingIds.has(a.id) ? '送信中...' : '発信する' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- ── ジョブ履歴セクション ───────────────────── -->
                <section class="py-5">
                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <h3 class="font-semibold text-gray-800">ジョブ履歴</h3>
                        <label class="flex cursor-pointer items-center gap-1.5 text-sm text-gray-600 select-none">
                            <input type="checkbox" v-model="hideHistoryCompleted" class="h-4 w-4 rounded border-gray-300" />
                            完了を表示しない
                        </label>
                    </div>

                    <div v-if="historyGroups.length === 0" class="text-sm text-gray-400">
                        {{ (page.props.jobHistory || []).length === 0 ? 'ジョブ履歴なし' : '表示するデータがありません。' }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <template v-for="group in historyGroups" :key="group.key">
                            <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                                {{ group.label }}
                                <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                            </div>
                            <table class="min-w-full border">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">送受信</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">相手</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">時間</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">クライアント</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件名</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">既読</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="m in group.items"
                                        :key="m.id"
                                        class="cursor-pointer hover:bg-gray-100"
                                        @click.prevent="historyRowClick(m, $event)"
                                        role="button"
                                    >
                                        <td class="border px-3 py-2">
                                            <span class="inline-flex items-center gap-1">
                                                <span
                                                    :class="historyIsSentByMe(m) ? 'bg-blue-500' : 'bg-gray-400'"
                                                    class="inline-block h-3 w-3 rounded-full"
                                                ></span>
                                                <span class="text-xs text-gray-600">{{ historyIsSentByMe(m) ? '送信' : '受信' }}</span>
                                            </span>
                                        </td>
                                        <td class="border px-3 py-2 text-sm text-gray-700">{{ historyGetCounterparty(m) }}</td>
                                        <td class="border px-3 py-2 text-sm text-gray-600">{{ historyGetStartTime(m) }}</td>
                                        <td class="border px-3 py-2 text-sm">{{ m.subject || (m.body && m.body.slice(0, 60)) }}</td>
                                        <td class="border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                                        <td class="border px-3 py-2 text-sm text-gray-600">{{ job.title || job.name || '-' }}</td>
                                        <td class="border px-3 py-2">
                                            <template v-if="historyIsUnread(m)">
                                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">未読</span>
                                            </template>
                                            <template v-else>
                                                <span class="text-xs text-gray-500">既読</span>
                                            </template>
                                        </td>
                                        <td class="border px-3 py-2">
                                            <span
                                                :class="statusBadgeClass(historyGetStatus(m))"
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            >{{ historyGetStatus(m) }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </template>
                    </div>

                    <div class="mt-2 text-sm text-gray-600">
                        表示中 {{ historyDisplayCount }} 件
                        <span v-if="hideHistoryCompleted && historyHiddenCount > 0" class="ml-2 text-xs text-gray-400">（完了 {{ historyHiddenCount }} 件を非表示）</span>
                    </div>
                </section>

            </div><!-- /divide-y -->
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const job  = page.props.job || {};
const schedules = computed(() => Array.isArray(page.props.schedules) ? page.props.schedules : []);

// hasSchedule flag (server-side or derived)
const serverHasSchedule = page.props.hasSchedule;
const computedHasSchedule = computed(() => {
    const s = job.schedule;
    if (!s) return false;
    if (typeof s === 'boolean') return s === true;
    if (Array.isArray(s)) return s.length > 0;
    if (typeof s === 'object') return Object.keys(s).length > 0;
    return Boolean(s);
});
const hasScheduleFlag = computed(() =>
    typeof serverHasSchedule !== 'undefined' ? Boolean(serverHasSchedule) : computedHasSchedule.value
);

const members   = page.props.members || [];
const hasMembers = computed(() => Array.isArray(members) && members.length > 0);

// Confirm prompt after initial creation
onMounted(() => {
    const flags        = page.props.registerFlags || [];
    const createdJobId = page.props.jobid || null;
    if (flags.length && createdJobId) {
        if (flags.includes('teammember')) {
            if (confirm('プロジェクトを登録しました。続いてメンバーを登録しますか？')) {
                router.visit(route('coordinator.project_team_members.create') + '?project_job_id=' + createdJobId);
            }
        }
    }
});

// ── Navigation helpers ────────────────────────────────────────────────────
function goSchedule() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.schedule', { projectJob: id }));
}

function goScheduleCalendar() {
    const id = job.id;
    if (!id) return;
    router.visit(route('coordinator.project_schedules.calendar') + '?project_job_id=' + encodeURIComponent(id));
}

function goProjectTeammember() {
    const id = job.id;
    const url = route('coordinator.project_team_members.create') + (id ? '?project_job_id=' + id : '');
    router.visit(url);
}

function editMembers() {
    const id          = job.id;
    const selectedIds = members.filter(m => m.user).map(m => m.user.id);
    let url = route('coordinator.project_team_members.create');
    const params = [];
    if (id)                params.push('project_job_id='    + encodeURIComponent(id));
    if (selectedIds.length) params.push('selected_user_ids=' + encodeURIComponent(selectedIds.join(',')));
    if (params.length) url += '?' + params.join('&');
    router.visit(url);
}

function goEdit() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.edit', { projectJob: id }));
}

function backToIndex() {
    router.visit(route('coordinator.project_jobs.index'));
}

function goJobAssign() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.assignments.create', { projectJob: id }));
}

function goAnalysis() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.analysis', { projectJob: id }));
}

// ── Formatters ────────────────────────────────────────────────────────────
function formatDate(v) {
    if (!v) return '-';
    try { return String(v).split('T')[0]; } catch { return String(v); }
}

function truncate(text, len) {
    if (!text) return '-';
    const s = String(text);
    return s.length > len ? s.slice(0, len) + '…' : s;
}

// ── 未発信の割当 ──────────────────────────────────────────────────────────

const localUnsent = ref(Array.isArray(page.props.unsentAssignments) ? [...page.props.unsentAssignments] : []);
const sendingIds   = ref(new Set());

function sendUnsent(a) {
    if (sendingIds.value.has(a.id)) return;
    const toUserId = a.user_id;
    const payload = {
        project_job_assignment_id: a.id,
        to: toUserId ? [toUserId] : [],
        subject: a.title || null,
        body: `割り当て依頼\nジョブ: ${job.title || ''}\n割り当て: ${a.title || ''}\n\n担当ユーザー: ${a.user_name || '（未割当）'}\n\nアプリで詳しい情報を確認できます。`,
        attachments: [],
    };
    sendingIds.value = new Set([...sendingIds.value, a.id]);
    router.post(
        route('coordinator.project_jobs.jobbox.store', { projectJob: job.id }),
        payload,
        {
            onSuccess: () => {
                localUnsent.value = localUnsent.value.filter((x) => x.id !== a.id);
                sendingIds.value = new Set([...sendingIds.value].filter((id) => id !== a.id));
            },
            onError: () => {
                sendingIds.value = new Set([...sendingIds.value].filter((id) => id !== a.id));
                alert('発信に失敗しました。');
            },
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function goAssignmentEdit(a, event) {
    const tag = event?.target?.tagName?.toLowerCase() || '';
    if (tag === 'button' || event?.target?.closest?.('button')) return;
    try {
        router.visit(
            route('coordinator.project_jobs.assignments.edit', { projectJob: job.id, assignment: a.id }),
            { preserveState: false },
        );
    } catch {}
}

// ── ジョブ履歴 ────────────────────────────────────────────────────────────

const hideHistoryCompleted = ref(true);

function historyGetDateKey(m) {
    return (
        m.project_job_assignment?.desired_start_date ||
        m.project_job_assignment?.desired_end_date ||
        (m.created_at ? String(m.created_at).split('T')[0] : null) ||
        ''
    );
}

function historyGetTimeKey(m) {
    return m.project_job_assignment?.start_time || m.project_job_assignment?.desired_time || '00:00';
}

function historyFormatDateLabel(dateStr) {
    if (!dateStr) return '日付なし';
    try {
        const d = new Date(dateStr + 'T00:00:00');
        const y = d.getFullYear();
        const mo = d.getMonth() + 1;
        const day = d.getDate();
        const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return `${y}年${mo}月${day}日（${dow}）`;
    } catch {
        return dateStr;
    }
}

function historyGetStatus(m) {
    try {
        const assignment = m.project_job_assignment || {};
        const jam = m || {};
        const statusKey = assignment.status?.key || jam.status?.key || null;
        if (statusKey) {
            switch (statusKey) {
                case 'completed':  return '完了';
                case 'scheduled':  return 'セット済';
                case 'confirmed':  return '確認済';
                case 'received':
                case 'order':
                case 'in_progress': return '受信済';
                default: break;
            }
        }
        if (Boolean(jam.completed) || Boolean(assignment.completed)) return '完了';
        if (Boolean(jam.scheduled) || Boolean(assignment.scheduled) || Boolean(assignment.scheduled_at)) return 'セット済';
        const readAt = jam.read_at || assignment.read_at || null;
        if (readAt) return Boolean(jam.accepted) || Boolean(assignment.accepted) ? '確認済' : '既読済';
        if (Boolean(jam.accepted) || Boolean(assignment.accepted)) return '受信済';
        return '-';
    } catch {
        return '-';
    }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':    return 'bg-yellow-100 text-yellow-800';
        case 'セット済': return 'bg-blue-100 text-blue-800';
        case '確認済':  return 'bg-green-100 text-green-800';
        case '受信済':  return 'bg-indigo-100 text-indigo-800';
        case '既読済':  return 'bg-gray-100 text-gray-700';
        default:        return 'bg-gray-100 text-gray-700';
    }
}

function historyIsSentByMe(m) {
    const authId = page.props.auth?.user?.id;
    return authId && m.sender?.id && authId === m.sender.id;
}

function historyGetCounterparty(m) {
    try {
        const authId = page.props.auth?.user?.id;
        const isSender = authId && m.sender?.id && authId === m.sender.id;
        if (isSender) {
            const recs = m.message && Array.isArray(m.message.recipients) ? m.message.recipients : [];
            if (recs.length) {
                const names = recs.map((r) => r.user?.name || r.name || r.email || null).filter(Boolean);
                if (names.length) return names.join(', ');
            }
            if (m.project_job_assignment?.user?.name) return m.project_job_assignment.user.name;
            return '-';
        }
        if (m.message?.fromUser?.name) return m.message.fromUser.name;
        if (m.sender?.name) return m.sender.name;
        return '-';
    } catch {
        return '-';
    }
}

function historyGetStartTime(m) {
    const t = m.project_job_assignment?.start_time || m.project_job_assignment?.desired_time || '';
    if (!t) return '-';
    return String(t).slice(0, 5);
}

function historyIsUnread(m) {
    try {
        const authUser = page.props.auth?.user;
        if (!authUser) return false;
        const authId = Number(authUser.id);
        if (m.project_job_assignment?.user?.id && Number(m.project_job_assignment.user.id) === authId) return !m.read_at;
        if (m.sender?.id && Number(m.sender.id) === authId) return !m.read_at;
        if (m.read_at) return false;
        if (m.message && Array.isArray(m.message.recipients)) {
            const rec = m.message.recipients.find((r) => Number(r?.user_id || r?.user?.id) === authId);
            if (rec) return !rec.read_at;
        }
        return !(m.sender?.id && Number(m.sender.id) === authId);
    } catch {
        return false;
    }
}

// 割当IDで重複排除（最新メッセージを採用）
function historyDeduplicate(arr) {
    const byAssign = new Map();
    for (const m of arr) {
        const aid = m.project_job_assignment?.id ? String(m.project_job_assignment.id) : `noassign-${m.id}`;
        if (!byAssign.has(aid)) { byAssign.set(aid, m); continue; }
        const existing = byAssign.get(aid);
        const eCreated = existing?.created_at ? new Date(existing.created_at) : null;
        const mCreated = m?.created_at ? new Date(m.created_at) : null;
        if ((!eCreated && mCreated) || (eCreated && mCreated && mCreated > eCreated)) {
            byAssign.set(aid, m);
        }
    }
    return Array.from(byAssign.values());
}

const historyGroups = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    let messages = historyDeduplicate(raw);
    if (hideHistoryCompleted.value) {
        messages = messages.filter((m) => historyGetStatus(m) !== '完了');
    }
    const grouped = new Map();
    for (const m of messages) {
        const key = historyGetDateKey(m);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(m);
    }
    for (const items of grouped.values()) {
        items.sort((a, b) => historyGetTimeKey(a).localeCompare(historyGetTimeKey(b)));
    }
    const sortedKeys = Array.from(grouped.keys()).sort((a, b) => {
        if (!a) return 1;
        if (!b) return -1;
        return b.localeCompare(a); // 日付降順
    });
    return sortedKeys.map((key) => ({
        key,
        label: historyFormatDateLabel(key),
        items: grouped.get(key),
    }));
});

const historyDisplayCount = computed(() => historyGroups.value.reduce((sum, g) => sum + g.items.length, 0));

const historyHiddenCount = computed(() => {
    if (!hideHistoryCompleted.value) return 0;
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    return historyDeduplicate(raw).filter((m) => historyGetStatus(m) === '完了').length;
});

function historyRowClick(m, event) {
    const tag = event.target?.tagName?.toLowerCase() || '';
    if (tag === 'a' || tag === 'button' || event.target.closest?.('a,button')) return;

    try {
        const pjId = job.id;
        const msgId = m.id;
        if (pjId && msgId) {
            router.visit(
                route('coordinator.project_jobs.jobbox.show', { projectJob: pjId, message: msgId }),
                { preserveState: false },
            );
        }
    } catch {}
}
</script>
