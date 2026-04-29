<template>
    <AppLayout title="案件詳細">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('user.project_jobs.index')" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 案件一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">案件詳細</h2>
            </div>
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

                    <!-- カレンダー（月ビュー・週間プランナー切替） -->
                    <div class="mt-4">
                        <ProjectCalendar
                            :schedules="schedules"
                            :project="job"
                            :weekPostsUrl="weekPostsUrl"
                            :readonly="true"
                            :events="[]"
                            :comments="[]"
                            :memos="[]"
                        />
                    </div>
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

                <!-- ── 進行管理表セクション ──────────────────────── -->
                <section v-if="progressSheets.length > 0" class="py-5">
                    <h3 class="mb-3 font-semibold text-gray-800">進行管理表</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">シート名</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="sheet in progressSheets" :key="sheet.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ sheet.name }}</td>
                                    <td class="px-4 py-2">
                                        <button
                                            type="button"
                                            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                            @click="openSheet(sheet)"
                                        >
                                            開く
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
                                    <!-- 作業日 -->
                                    <col />
                                    <!-- タイトル -->
                                    <col style="width: 88px" />
                                    <!-- ステータス -->
                                </colgroup>
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">発信者</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">受信者</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作業日</th>
                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
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
                                            {{ historyGetWorkDate(m) }}
                                        </td>
                                        <td class="break-words border px-3 py-2 text-sm">{{ m.subject || (m.body && m.body.slice(0, 60)) }}</td>
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

                <!-- ── 校正依頼セクション ──────────────────────── -->
                <section class="py-5">
                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <h3 class="font-semibold text-gray-800">校正依頼</h3>
                        <button
                            v-if="!job.completed"
                            @click="openProofModal(null)"
                            class="rounded border border-pink-300 bg-pink-50 px-3 py-1.5 text-sm font-medium text-pink-700 hover:bg-pink-100"
                        >
                            + 校正依頼を送る
                        </button>
                    </div>
                    <p class="text-sm text-gray-400">
                        この案件に関する校正は「校正状況」タブから確認できます。
                    </p>
                </section>
            </div>
            <!-- /divide-y -->
        </div>

        <ProofRequestModal
            :show="showProofModal"
            :initial-title="proofTargetAssignment?.title || job.title || ''"
            :project-job-assignment-id="proofTargetAssignment?.id || null"
            :project-job-id="job.id || null"
            @close="showProofModal = false"
        />
    </AppLayout>
</template>

<script setup>
import ProofRequestModal from '@/Components/ProofRequestModal.vue';
import ProjectCalendar from '@/Components/ProjectCalendar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const page = usePage();
const job = page.props.job || {};

const showProofModal = ref(false);
const proofTargetAssignment = ref(null);

function openProofModal(assignment = null) {
    proofTargetAssignment.value = assignment;
    showProofModal.value = true;
}
const schedules = computed(() => (Array.isArray(page.props.schedules) ? page.props.schedules : []));
const weekPostsUrl = computed(() =>
    job.id ? route('user.project_jobs.week_posts.index', { projectJob: job.id }) : null,
);
const members = page.props.members || [];
const hasMembers = computed(() => Array.isArray(members) && members.length > 0);
const subCoordinators = computed(() => page.props.subCoordinators || []);
const progressSheets = computed(() => page.props.progressSheets || []);

// ── 進行管理表を開く ──────────────────────────────────────────────

function openSheet(sheet) {
    try {
        router.visit(route('user.progress_sheets.show', { sheet: sheet.id }));
    } catch {
        window.location.href = route('user.progress_sheets.show', { sheet: sheet.id });
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

const hideHistoryCompleted = ref(false);

function historyGetDateKey(m) {
    if (m.event_starts_at) return String(m.event_starts_at).replace(' ', 'T').split('T')[0];
    return m.project_job_assignment?.desired_end_date || (m.created_at ? String(m.created_at).split('T')[0] : null) || '';
}

function historyGetTimeKey(m) {
    if (m.event_starts_at) {
        try {
            const d = new Date(String(m.event_starts_at).replace(' ', 'T'));
            return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
        } catch { /* fallthrough */ }
    }
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
        // 優先順位: 完了 > セット済み > 確認済み > 未読
        if (Boolean(m.completed) || Boolean(assignment.completed)) return '完了';
        if (Boolean(m.accepted) || Boolean(assignment.accepted) ||
            Boolean(m.scheduled) || Boolean(assignment.scheduled) || Boolean(assignment.scheduled_at)) return 'セット済み';
        const readAt = m.read_at || assignment.read_at || null;
        if (readAt) return '確認済み';
        return '未読';
    } catch {
        return '未読';
    }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':
            return 'bg-yellow-100 text-yellow-800';
        case 'セット済み':
            return 'bg-blue-100 text-blue-800';
        case '確認済み':
            return 'bg-green-100 text-green-800';
        case '未読':
            return 'bg-red-100 text-red-800';
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

function historyGetWorkDate(m) {
    try {
        if (m.event_starts_at) {
            const norm = String(m.event_starts_at).replace(' ', 'T');
            const dateStr = norm.split('T')[0];
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                const formatted = `${parts[0]}/${parts[1]}/${parts[2]}`;
                const d = new Date(norm);
                const startTime = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
                if (m.event_ends_at) {
                    const e = new Date(String(m.event_ends_at).replace(' ', 'T'));
                    const endTime = `${String(e.getHours()).padStart(2, '0')}:${String(e.getMinutes()).padStart(2, '0')}`;
                    return `${formatted}\n${startTime}〜${endTime}`;
                }
                return `${formatted}\n${startTime}〜`;
            }
        }
        const date = m.project_job_assignment?.desired_end_date || null;
        if (!date) return '-';
        const parts = String(date).split('T')[0].split('-');
        if (parts.length !== 3) return String(date).split('T')[0];
        return `${parts[0]}/${parts[1]}/${parts[2]}`;
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
