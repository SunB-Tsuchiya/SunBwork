<template>
    <AppLayout title="案件詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">案件詳細</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- ── タイトル行 ──────────────────────────────────── -->
            <div class="mb-6 flex flex-wrap items-start gap-5">
                <div>
                    <p class="text-sm font-medium text-gray-400">
                        {{ job.client?.name || 'クライアント未設定' }}
                    </p>
                    <h1 class="mt-0.5 text-2xl font-bold text-gray-900">
                        {{ job.title || job.name || '（案件名なし）' }}
                    </h1>
                    <p class="mt-1 text-xs text-gray-500">
                        <span v-if="job.jobcode">伝票番号: {{ job.jobcode }}　</span>
                        <span v-if="job.user?.name">リーダー: {{ job.user.name }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        <span v-if="job.size?.name"
                            >版型: <span class="font-medium text-gray-700">{{ job.size.name }}</span
                            >　</span
                        >
                        <span v-if="job.page_count"
                            >総ページ数: <span class="font-medium text-gray-700">{{ job.page_count }} ページ</span></span
                        >
                    </p>
                    <p v-if="subCoordinators.length > 0" class="mt-0.5 text-xs text-gray-400">
                        サブCo: {{ subCoordinators.map((c) => c.name).join('、') }}
                    </p>
                    <span
                        v-if="job.completed"
                        class="mt-2 inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800"
                        >完了</span
                    >
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button
                        type="button"
                        class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                        @click="backToIndex"
                    >
                        一覧に戻る
                    </button>
                </div>
            </div>

            <!-- ── 詳細メモ ─────────────────────── -->
            <div v-if="job.detail" class="mb-6 whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                {{ typeof job.detail === 'string' ? job.detail : JSON.stringify(job.detail) }}
            </div>

            <div class="divide-y divide-gray-100">
                <!-- ── スケジュールセクション ──────────────────── -->
                <section class="py-5">
                    <h3 class="mb-3 font-semibold text-gray-800">スケジュール</h3>

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
                                <tr v-for="s in schedules" :key="s.id" class="hover:bg-gray-50">
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
                    <h3 class="mb-3 font-semibold text-gray-800">メンバー</h3>

                    <div v-if="hasMembers" class="flex flex-wrap gap-2">
                        <div
                            v-for="m in members"
                            :key="m.id"
                            class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm"
                        >
                            <span class="inline-block h-2 w-2 rounded-full bg-green-400"></span>
                            <span class="font-medium text-gray-800">{{ m.user ? m.user.name : '（ユーザー情報なし）' }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">メンバー未登録</p>
                </section>

                <!-- ── 進行表セクション ──────────────────────── -->
                <section v-if="localSheets.length > 0" class="py-5">
                    <h3 class="mb-4 font-semibold text-gray-800">進行管理表</h3>

                    <div v-for="sheet in localSheets" :key="sheet.id" class="mb-6">
                        <h4 class="mb-2 text-sm font-medium text-gray-700">{{ sheet.name }}</h4>

                        <div v-if="!sheet.column_config || sheet.column_config.length === 0" class="text-sm text-gray-400">
                            列が定義されていません。
                        </div>
                        <ProgressTable
                            v-else
                            :rows="sheet.rows"
                            :column-config="sheet.column_config"
                            :cells="sheet.cells"
                            :users="[]"
                            :stages="[]"
                            :sizes="[]"
                            :assignments="[]"
                            :work-item-types="[]"
                            :can-edit="false"
                            :edit-mode="false"
                            :job-link-only="true"
                            :auth-user-id="page.props.auth?.user?.id ?? null"
                            @job-link-open="openJobLink(sheet, $event)"
                            @job-link-detail="openJobLinkDetail($event)"
                            @complete-assignment="onCompleteAssignment(sheet, $event)"
                        />
                    </div>
                </section>

                <!-- ── ジョブ履歴セクション ───────────────────── -->
                <section class="py-5">
                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <h3 class="font-semibold text-gray-800">ジョブ履歴（自分のみ）</h3>
                        <label class="flex cursor-pointer select-none items-center gap-1.5 text-sm text-gray-600">
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
                            <table class="w-full table-fixed border" style="min-width: 760px">
                                <colgroup>
                                    <col style="width: 100px" />
                                    <!-- 発信者 -->
                                    <col style="width: 100px" />
                                    <!-- 受信者 -->
                                    <col style="width: 140px" />
                                    <!-- 締め切り -->
                                    <col />
                                    <!-- タイトル -->
                                    <col style="width: 56px" />
                                    <!-- 既読 -->
                                    <col style="width: 88px" />
                                    <!-- ステータス -->
                                </colgroup>
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">発信者</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">受信者</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">締め切り</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
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
                                        <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetSender(m) }}</td>
                                        <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetRecipients(m) }}</td>
                                        <td class="whitespace-pre-line break-words border px-3 py-2 text-sm text-gray-600">
                                            {{ historyGetDeadline(m) }}
                                        </td>
                                        <td class="break-words border px-3 py-2 text-sm">{{ m.subject || (m.body && m.body.slice(0, 60)) }}</td>
                                        <td class="border px-3 py-2">
                                            <span
                                                v-if="historyIsUnread(m)"
                                                class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
                                                >未読</span
                                            >
                                            <span v-else class="text-xs text-gray-500">既読</span>
                                        </td>
                                        <td class="border px-3 py-2">
                                            <span
                                                :class="statusBadgeClass(historyGetStatus(m))"
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                >{{ historyGetStatus(m) }}</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </template>
                    </div>

                    <div class="mt-2 text-sm text-gray-600">
                        表示中 {{ historyDisplayCount }} 件
                        <span v-if="hideHistoryCompleted && historyHiddenCount > 0" class="ml-2 text-xs text-gray-400"
                            >（完了 {{ historyHiddenCount }} 件を非表示）</span
                        >
                    </div>
                </section>
            </div>
            <!-- /divide-y -->
        </div>
        <!-- ジョブリンク詳細モーダル -->
        <div
            v-if="jobLinkDetailModal.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click.self="jobLinkDetailModal.open = false"
        >
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">登録済みジョブ</h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">タイトル</dt>
                        <dd class="text-gray-800">{{ jobLinkDetailModal.title }}</dd>
                    </div>
                    <div v-if="jobLinkDetailModal.endDate">
                        <dt class="text-xs font-medium text-gray-500">期限</dt>
                        <dd class="text-gray-800">{{ jobLinkDetailModal.endDate }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">状態</dt>
                        <dd :class="jobLinkDetailModal.completed ? 'text-yellow-700' : 'text-blue-700'">
                            {{ jobLinkDetailModal.completed ? '完了' : '未完了' }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-5 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        @click="jobLinkDetailModal.open = false"
                    >
                        閉じる
                    </button>
                    <button
                        v-if="jobLinkDetailModal.assignmentId && !jobLinkDetailModal.completed"
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        :disabled="jobLinkDetailModal.completing"
                        @click="completeFromModal"
                    >
                        {{ jobLinkDetailModal.completing ? '処理中…' : '完了にする' }}
                    </button>
                    <button
                        v-if="jobLinkDetailModal.assignmentId"
                        type="button"
                        class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                        @click="goToMyJob"
                    >
                        マイジョブを開く
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import ProgressTable from '@/Components/ProgressTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const page = usePage();
const job = page.props.job || {};
const schedules = computed(() => (Array.isArray(page.props.schedules) ? page.props.schedules : []));
const members = page.props.members || [];
const hasMembers = computed(() => Array.isArray(members) && members.length > 0);
const subCoordinators = computed(() => page.props.subCoordinators || []);
const progressSheets = computed(() => page.props.progressSheets || []);
const localSheets = ref(
    (page.props.progressSheets || []).map((s) => ({
        ...s,
        cells: (s.cells || []).map((c) => ({ ...c })),
    })),
);

// ── 進行表 ────────────────────────────────────────────────────────────────

const jobLinkDetailModal = ref({ open: false, title: '', endDate: '', completed: false, assignmentId: null, completing: false });

async function onCompleteAssignment(sheet, { assignmentId }) {
    if (!assignmentId) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('myjobbox.assignments.complete', { assignment: assignmentId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            // localSheets 内の該当セルを更新
            const sheetIdx = localSheets.value.findIndex((s) => s.id === sheet.id);
            if (sheetIdx >= 0) {
                const cells = localSheets.value[sheetIdx].cells;
                const cellIdx = cells.findIndex((c) => c.assignment_id === assignmentId);
                if (cellIdx >= 0) {
                    localSheets.value[sheetIdx].cells.splice(cellIdx, 1, { ...cells[cellIdx], assignment_completed: true });
                }
            }
        }
    } catch {
        /* ignore */
    }
}

async function completeFromModal() {
    const assignmentId = jobLinkDetailModal.value.assignmentId;
    if (!assignmentId || jobLinkDetailModal.value.completed) return;
    jobLinkDetailModal.value.completing = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('myjobbox.assignments.complete', { assignment: assignmentId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            jobLinkDetailModal.value.completed = true;
            // localSheets 内の全シートのセルも更新
            for (const sheet of localSheets.value) {
                const cellIdx = sheet.cells.findIndex((c) => c.assignment_id === assignmentId);
                if (cellIdx >= 0) {
                    sheet.cells.splice(cellIdx, 1, { ...sheet.cells[cellIdx], assignment_completed: true });
                }
            }
        }
    } catch {
        /* ignore */
    } finally {
        jobLinkDetailModal.value.completing = false;
    }
}

function openJobLink(sheet, { rowId, colKey }) {
    const row = (sheet.rows || []).find((r) => r.id === rowId);
    const title = row?.label ?? '';
    const params = {
        title,
        project_job_id: job.id,
        progress_sheet_id: sheet.id,
        row_id: rowId,
        col_key: colKey,
    };
    if (job.client?.id) params.client_id = job.client.id;
    try {
        router.visit(route('events.create_job', params));
    } catch {
        window.location.href = route('events.create_job', params);
    }
}

function openJobLinkDetail({ assignmentId, assignmentTitle, endDate, completed }) {
    jobLinkDetailModal.value = {
        open: true,
        title: assignmentTitle ?? '(タイトルなし)',
        endDate: endDate ?? null,
        completed: !!completed,
        assignmentId: assignmentId ?? null,
    };
}

function goToMyJob() {
    const id = jobLinkDetailModal.value.assignmentId;
    if (!id) return;
    try {
        router.visit(route('user.myjobbox.show', { assignment: id }));
    } catch {
        window.location.href = route('user.myjobbox.show', { assignment: id });
    }
}

// ── Navigation ────────────────────────────────────────────────────────────

function backToIndex() {
    try {
        router.visit(route('user.project_jobs.index'));
    } catch {
        window.location.href = route('user.project_jobs.index');
    }
}

// ── Formatters ────────────────────────────────────────────────────────────

function formatDate(v) {
    if (!v) return '-';
    try {
        return String(v).split('T')[0];
    } catch {
        return String(v);
    }
}

function truncate(text, len) {
    if (!text) return '-';
    const s = String(text);
    return s.length > len ? s.slice(0, len) + '…' : s;
}

// ── ジョブ履歴 ────────────────────────────────────────────────────────────

const hideHistoryCompleted = ref(true);

function historyGetDateKey(m) {
    return m.project_job_assignment?.desired_end_date || (m.created_at ? String(m.created_at).split('T')[0] : null) || '';
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
        const statusKey = assignment.status?.key || null;
        if (statusKey) {
            switch (statusKey) {
                case 'completed':
                    return '完了';
                case 'scheduled':
                    return 'セット済';
                case 'confirmed':
                    return '確認済';
                case 'received':
                case 'order':
                case 'in_progress':
                    return '受信済';
                default:
                    break;
            }
        }
        if (Boolean(m.completed) || Boolean(assignment.completed)) return '完了';
        if (Boolean(m.scheduled) || Boolean(assignment.scheduled)) return 'セット済';
        const readAt = m.read_at || assignment.read_at || null;
        if (readAt) return Boolean(m.accepted) || Boolean(assignment.accepted) ? '確認済' : '既読済';
        if (Boolean(m.accepted) || Boolean(assignment.accepted)) return '受信済';
        return '-';
    } catch {
        return '-';
    }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':
            return 'bg-yellow-100 text-yellow-800';
        case 'セット済':
            return 'bg-blue-100 text-blue-800';
        case '確認済':
            return 'bg-green-100 text-green-800';
        case '受信済':
            return 'bg-indigo-100 text-indigo-800';
        default:
            return 'bg-gray-100 text-gray-700';
    }
}

function historyGetSender(m) {
    try {
        return m.sender?.name || m.message?.fromUser?.name || '-';
    } catch {
        return '-';
    }
}

function historyGetRecipients(m) {
    try {
        const recs = m.message && Array.isArray(m.message.recipients) ? m.message.recipients : [];
        if (recs.length) {
            const names = recs.map((r) => r.user?.name || r.name || null).filter(Boolean);
            if (names.length) return names.join(', ');
        }
        if (m.project_job_assignment?.user?.name) return m.project_job_assignment.user.name;
        return '-';
    } catch {
        return '-';
    }
}

function historyGetDeadline(m) {
    try {
        const date = m.project_job_assignment?.desired_end_date || null;
        if (!date) return '-';
        const parts = String(date).split('T')[0].split('-');
        if (parts.length !== 3) return String(date).split('T')[0];
        const formatted = `${parts[0]}/${parts[1]}/${parts[2]}`;
        const time = m.project_job_assignment?.start_time || m.project_job_assignment?.desired_time || '';
        if (time) return `${formatted}\n${String(time).slice(0, 5)}`;
        return formatted;
    } catch {
        return '-';
    }
}

function historyIsUnread(m) {
    try {
        const authUser = page.props.auth?.user;
        if (!authUser) return false;
        const authId = Number(authUser.id);
        if (m.project_job_assignment?.user?.id && Number(m.project_job_assignment.user.id) === authId) return !m.read_at;
        if (m.sender?.id && Number(m.sender.id) === authId) return false; // 自分が送った場合は未読表示しない
        if (m.read_at) return false;
        if (m.message && Array.isArray(m.message.recipients)) {
            const rec = m.message.recipients.find((r) => Number(r?.user_id || r?.user?.id) === authId);
            if (rec) return !rec.read_at;
        }
        return true;
    } catch {
        return false;
    }
}

function historyDeduplicate(arr) {
    const byAssign = new Map();
    for (const m of arr) {
        const aid = m.project_job_assignment?.id ? String(m.project_job_assignment.id) : `noassign-${m.id}`;
        if (!byAssign.has(aid)) {
            byAssign.set(aid, m);
            continue;
        }
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
        return b.localeCompare(a);
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
            router.visit(route('user.project_jobs.jobbox.show', { projectJob: pjId, message: msgId }) + '?from=project', { preserveState: false });
        }
    } catch {}
}
</script>
