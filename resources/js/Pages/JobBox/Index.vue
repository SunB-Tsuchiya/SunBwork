<template>
    <AppLayout :title="`JobBox - ${props.projectJob?.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">JobBox — ジョブ関連メッセージ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">JobBox：{{ props.projectJob?.name || '' }}</h1>

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="タイトル/詳細/担当で検索"
                        class="w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-blue-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
            </div>

            <!-- 月セレクター + 完了非表示チェック -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select
                        v-model="page.props.period_model"
                        @change="search"
                        class="rounded border px-3 py-2 text-sm"
                        style="width: 9.5em"
                    >
                        <option value="all">全期間</option>
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </option>
                    </select>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                    <input type="checkbox" v-model="hideCompleted" class="h-4 w-4 rounded border-gray-300" />
                    完了を表示しない
                </label>
            </div>

            <!-- 日グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    表示するデータがありません。
                </div>

                <template v-for="group in displayGroups" :key="group.date">
                    <!-- 日付ヘッダー -->
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
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">既読</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in group.items"
                                :key="m.id"
                                :class="['cursor-pointer hover:bg-gray-100', m.__is_new ? 'new-highlight' : '']"
                                @click.prevent="rowClick(m, $event)"
                                role="button"
                            >
                                <td class="border px-3 py-2">
                                    <span class="inline-flex items-center gap-1">
                                        <span
                                            :class="isSentByMe(m) ? 'bg-blue-500' : 'bg-gray-400'"
                                            class="inline-block h-3 w-3 rounded-full"
                                        ></span>
                                        <span class="text-xs text-gray-600">{{ isSentByMe(m) ? '送信' : '受信' }}</span>
                                    </span>
                                </td>
                                <td class="border px-3 py-2 text-sm text-gray-700">{{ getCounterparty(m) }}</td>
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ getStartTime(m) }}</td>
                                <td class="border px-3 py-2 text-sm">{{ m.subject || (m.body && m.body.slice(0, 60)) }}</td>
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ getClientName(m) }}</td>
                                <td class="border px-3 py-2">
                                    <template v-if="isUnread(m)">
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">未読</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-xs text-gray-500">既読</span>
                                    </template>
                                </td>
                                <td class="border px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <TooltipProvider :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger>
                                                    <span
                                                        :class="statusBadgeClass(getAssignmentStatus(m))"
                                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                    >
                                                        <span v-html="statusIcon(getAssignmentStatus(m))" class="mr-1 inline-flex h-3 w-3"></span>
                                                        {{ getAssignmentStatus(m) }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent class="jobbox-tooltip max-w-xs">{{ statusTooltip(getAssignmentStatus(m)) }}</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <button
                                            v-if="getAssignmentStatus(m) !== '完了'"
                                            @click.stop="completeAssignment(m)"
                                            class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700 active:bg-green-800"
                                        >完了</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    表示中 {{ totalDisplayCount }} 件
                    <span v-if="hideCompleted && hiddenCompletedCount > 0" class="ml-2 text-xs text-gray-400">（完了 {{ hiddenCompletedCount }} 件を非表示）</span>
                </div>
            </div>

            <div class="mt-4">
                <Link :href="getBackLink()" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps({ projectJob: Object, messages: Object });
const page = usePage();
page.props.q_model = page.props.q || '';
page.props.period_model = page.props.period ?? '';
const monthOptions = computed(() => (Array.isArray(page.props.monthOptions) ? page.props.monthOptions : []));

const sortState = reactive({ sort: page.props.sort || null, dir: page.props.dir || 'desc' });

// 完了非表示フラグ（デフォルト：完了を隠す）
const hideCompleted = ref(true);

// ===== ユーティリティ =====

function isSentByMe(m) {
    const authId = page.props.auth?.user?.id;
    return authId && m.sender?.id && authId === m.sender.id;
}

function formatDateLabel(dateStr) {
    if (!dateStr) return '日付なし';
    try {
        const d = new Date(dateStr + 'T00:00:00');
        const y = d.getFullYear();
        const mo = d.getMonth() + 1;
        const day = d.getDate();
        const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return `${y}年${mo}月${day}日（${dow}）`;
    } catch (e) {
        return dateStr;
    }
}

function getDateKey(m) {
    return (
        m.project_job_assignment?.desired_start_date ||
        m.project_job_assignment?.desired_end_date ||
        (m.created_at ? String(m.created_at).split('T')[0] : null) ||
        ''
    );
}

function getStartTime(m) {
    const t = m.project_job_assignment?.start_time || m.project_job_assignment?.desired_time || '';
    if (!t) return '-';
    // HH:MM:SS → HH:MM
    return String(t).slice(0, 5);
}

function getTimeKey(m) {
    return m.project_job_assignment?.start_time || m.project_job_assignment?.desired_time || '00:00';
}

// ===== 表示データ =====

const localMessages = ref(props.messages && props.messages.data ? [...props.messages.data] : []);

// 割当IDで重複排除（最新メッセージを採用）
function deduplicateByAssignment(arr) {
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

// 日グループ（日付降順、同日内は開始時刻昇順）
const displayGroups = computed(() => {
    let messages = deduplicateByAssignment(Array.isArray(localMessages.value) ? localMessages.value : []);

    // 完了非表示フィルター
    const allCount = messages.length;
    if (hideCompleted.value) {
        messages = messages.filter((m) => getAssignmentStatus(m) !== '完了');
    }

    // 日付でグループ化
    const grouped = new Map();
    for (const m of messages) {
        const dk = getDateKey(m);
        if (!grouped.has(dk)) grouped.set(dk, []);
        grouped.get(dk).push(m);
    }

    // 同日内を時刻昇順にソート
    for (const items of grouped.values()) {
        items.sort((a, b) => getTimeKey(a).localeCompare(getTimeKey(b)));
    }

    // 日付降順（31日→1日）
    const sortedKeys = Array.from(grouped.keys()).sort((a, b) => {
        if (!a) return 1;
        if (!b) return -1;
        return b.localeCompare(a);
    });

    return sortedKeys.map((dk) => ({
        date: dk,
        label: formatDateLabel(dk),
        items: grouped.get(dk),
    }));
});

const totalDisplayCount = computed(() => displayGroups.value.reduce((sum, g) => sum + g.items.length, 0));

const hiddenCompletedCount = computed(() => {
    if (!hideCompleted.value) return 0;
    const all = deduplicateByAssignment(Array.isArray(localMessages.value) ? localMessages.value : []);
    return all.filter((m) => getAssignmentStatus(m) === '完了').length;
});

// ===== 完了処理 =====

async function completeAssignment(m) {
    if (!confirm('完了しますか？')) return;
    const assignmentId = m.project_job_assignment?.id;
    if (!assignmentId) {
        alert('割当情報が見つかりません。');
        return;
    }
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const xsrfMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const xsrf = xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : null;
        const url = typeof route === 'function' ? route('jobbox.assignments.complete', { assignment: assignmentId }) : `/jobbox/assignments/${assignmentId}/complete`;
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            // ローカル状態を即時更新
            const idx = localMessages.value.findIndex((x) => x.id === m.id);
            if (idx >= 0) {
                const msg = localMessages.value[idx];
                if (msg.project_job_assignment) {
                    msg.project_job_assignment.status = { key: 'completed', name: '完了' };
                    msg.project_job_assignment.completed = true;
                    msg.project_job_assignment.status_id = null; // force re-evaluate via status key
                }
            }
        } else {
            alert('完了処理に失敗しました。');
        }
    } catch (err) {
        console.error('[JobBox] completeAssignment error', err);
        alert('完了処理に失敗しました。');
    }
}

// ===== 既存ロジック（変更なし） =====

function deleteMessage(m) {
    if (!confirm('このメッセージを本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(route('coordinator.project_jobs.jobbox.destroy', { projectJob: props.projectJob?.id, message: m.id }), {
        onSuccess: () => router.reload(),
        onError: () => alert('削除に失敗しました。'),
    });
}

function formatDate(d) {
    if (!d) return '-';
    return String(d).split('T')[0];
}

function goto(url) {
    if (!url) return;
    router.visit(url, { preserveState: false });
}

async function rowClick(m, event) {
    const tag = event.target?.tagName?.toLowerCase() || '';
    if (tag === 'a' || tag === 'button' || event.target.closest?.('a,button')) return;

    try {
        const assId = m.project_job_assignment?.id || m.project_job_assignment_id || m.id || null;
        const userId = m.project_job_assignment?.user?.id || m.project_job_assignment?.user_id || page.props.auth.user?.id || '';
        if (assId) {
            let eventsUrl = null;
            try {
                eventsUrl = typeof route === 'function' ? route('events.index') : '/events';
                const query = [];
                if (userId) query.push('user_id=' + encodeURIComponent(userId));
                if (assId) query.push('job=' + encodeURIComponent(assId));
                if (query.length) eventsUrl += '?' + query.join('&');
            } catch (e) {
                eventsUrl = '/events?job=' + encodeURIComponent(assId);
            }
            try {
                const res = await fetch(eventsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) {
                    const payload = await res.json();
                    if (Array.isArray(payload) && payload.length > 0) {
                        const ev = payload[0];
                        const evId = ev.id || ev.event_id || ev.extendedProps?.event_id || ev.extendedProps?.id;
                        if (evId) {
                            try { router.get(typeof route === 'function' ? route('events.show', evId) : '/events/' + evId); return; } catch {}
                            try { window.location.href = '/events/' + evId; return; } catch {}
                        }
                    }
                }
            } catch {}
        }
    } catch {}

    const url = getMessageLink(m);
    if (url && url !== '#') router.visit(url, { preserveState: false });
}

function search() {
    const pjId = props.projectJob?.id;
    if (!pjId) {
        const target = page.props.auth.user?.isCoordinator ? 'project_jobs.index' : 'user.jobbox.index';
        router.get(route(target), { q: page.props.q_model, period: page.props.period_model }, { preserveState: false });
        return;
    }
    const r = page.props.auth.user?.isCoordinator ? 'coordinator.project_jobs.jobbox.index' : 'project_jobs.jobbox.index';
    router.get(route(r, { projectJob: pjId }), { q: page.props.q_model, period: page.props.period_model }, { preserveState: false });
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

function getBackLink() {
    const pjId = props.projectJob?.id;
    try {
        if (!pjId) return '/jobbox';
        if (page.props.auth.user?.isCoordinator) return route('coordinator.project_jobs.show', { projectJob: pjId });
        return route('project_jobs.show', { projectJob: pjId });
    } catch {
        return '/jobbox';
    }
}

function getMessageLink(m) {
    let pjId = props.projectJob?.id;
    try {
        if (!pjId) pjId = m.project_job_assignment?.project_job?.id || m.project_job_assignment?.project_job_id || null;
        if (!pjId) return '#';
        if (page.props.auth.user?.isCoordinator) return route('coordinator.project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
        return route('project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
    } catch {
        return '#';
    }
}

function getCounterparty(m) {
    try {
        const authId = page.props.auth.user?.id;
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

function getClientName(m) {
    try {
        if (m.project_job_assignment?.project_job?.client?.name) return m.project_job_assignment.project_job.client.name;
        if (props.projectJob?.client?.name) return props.projectJob.client.name;
        return '-';
    } catch {
        return '-';
    }
}

function getAssignmentStatus(m) {
    try {
        const jam = m || {};
        const assignment = m.project_job_assignment || {};
        const statusKey = assignment.status?.key || jam.status?.key || null;
        if (statusKey) {
            switch (statusKey) {
                case 'completed': return '完了';
                case 'scheduled': return 'セット済み';
                case 'confirmed': return '確認済み';
                case 'received':
                case 'order':
                case 'in_progress': return '受信済み';
                default: break;
            }
        }
        if (Boolean(jam.completed) || Boolean(assignment.completed)) return '完了';
        if (Boolean(jam.scheduled) || Boolean(assignment.scheduled) || Boolean(assignment.scheduled_at)) return 'セット済み';
        const readAt = jam.read_at || assignment.read_at || null;
        if (readAt) return Boolean(jam.accepted) || Boolean(assignment.accepted) ? '確認済み' : '既読済み';
        if (Boolean(jam.accepted) || Boolean(assignment.accepted)) return '受信済み';
        return '-';
    } catch {
        return '-';
    }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了': return 'bg-yellow-100 text-yellow-800';
        case 'セット済み': return 'bg-blue-100 text-blue-800';
        case '確認済み': return 'bg-green-100 text-green-800';
        case '受信済み': return 'bg-indigo-100 text-indigo-800';
        case '既読済み': return 'bg-gray-100 text-gray-700';
        default: return 'bg-gray-100 text-gray-700';
    }
}

function statusTooltip(status) {
    switch (status) {
        case '完了': return '作業が完了しています。';
        case 'セット済み': return '作業の予定がカレンダーにセットされています。';
        case '確認済み': return '受信者が内容を確認しました（既読）。';
        case '受信済み': return '受信者にメッセージが届いています（未確認）。';
        case '既読済み': return '既に既読となっています。';
        default: return '';
    }
}

function statusIcon(status) {
    switch (status) {
        case '完了': return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-yellow-800"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L7 12.172 4.707 9.879a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l9-9z" clip-rule="evenodd"/></svg>`;
        case 'セット済み': return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-blue-800"><path d="M6 2a1 1 0 000 2h8a1 1 0 100-2H6zM3 6a1 1 0 011-1h12a1 1 0 011 1v9a2 2 0 01-2 2H5a2 2 0 01-2-2V6z"/></svg>`;
        case '確認済み': return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-green-800"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 7h2v5H9V7zm0 7h2v2H9v-2z"/></svg>`;
        case '受信済み': return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-indigo-800"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v1l-8 4.5L2 6V5z"/><path d="M18 8.118V15a2 2 0 01-2 2H4a2 2 0 01-2-2V8.118l8 4.5 8-4.5z"/></svg>`;
        case '既読済み': return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-gray-800"><path d="M3 5a1 1 0 011-1h12a1 1 0 011 1v9a2 2 0 01-2 2H5a2 2 0 01-2-2V5z"/></svg>`;
        default: return '';
    }
}

function isUnread(m) {
    try {
        const authUser = page.props.auth.user;
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

const { showToast } = useToasts();

onMounted(() => {
    try {
        const authUser = page.props.auth.user;
        if (!authUser || !window.Echo) return;
        const channel = window.Echo.private('jobmessages.' + authUser.id);
        channel.listen('JobMessageCreated', async (e) => {
            try {
                let jam = null;
                if (e.jam) {
                    jam = e.jam;
                } else if (e.job_assignment_message_id) {
                    try {
                        const resp = await fetch(route('api.jobbox.show', { id: e.job_assignment_message_id }), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                        if (resp.ok) { const json = await resp.json(); jam = json.data || json; }
                    } catch {}
                }

                const newJamBase = jam ? { ...jam } : {
                    id: e.job_assignment_message_id || e.message_id || `tmp-${Date.now()}`,
                    subject: e.subject || null,
                    body: e.jam?.body || null,
                    sender: { name: e.from_user_name || null, id: e.from_user_id || e.jam?.sender?.id || null },
                    project_job_assignment: null,
                    read_at: e.jam?.read_at || null,
                    from_user_id: e.from_user_id || e.jam?.sender?.id || null,
                };

                try {
                    const bodyHtml = (newJamBase.body || '') + (e.jam?.body || '');
                    const match = bodyHtml.match(/希望開始日[:：\s]*([0-9]{4}-[0-9]{2}-[0-9]{2})/i) || bodyHtml.match(/希望日[:：\s]*([0-9]{4}-[0-9]{2}-[0-9]{2})/i);
                    if (match?.[1]) {
                        newJamBase.project_job_assignment = newJamBase.project_job_assignment || {};
                        newJamBase.project_job_assignment.desired_start_date = match[1];
                    } else if (e.jam?.project_job_assignment?.desired_start_date) {
                        newJamBase.project_job_assignment = newJamBase.project_job_assignment || {};
                        newJamBase.project_job_assignment.desired_start_date = e.jam.project_job_assignment.desired_start_date;
                    }
                    if (e.jam?.read_at) newJamBase.read_at = e.jam.read_at;
                } catch {}

                const newJam = { ...newJamBase, __is_new: true };
                localMessages.value.unshift(newJam);
                setTimeout(() => {
                    const idx = localMessages.value.findIndex((x) => x.id === newJam.id);
                    if (idx >= 0) localMessages.value[idx].__is_new = false;
                }, 20000);
            } catch {}
        });

        channel.listen('JobMessageRead', (e) => {
            try {
                const mid = e?.message_id;
                if (!mid) return;
                const idx = localMessages.value.findIndex((x) => Number(x.id) === Number(mid));
                if (idx >= 0) localMessages.value[idx].read_at = e?.read_at || new Date().toISOString();
            } catch {}
        });
    } catch {}
});
</script>

<style>
.jobbox-tooltip {
    background-color: #eff6ff !important;
    color: #0f172a !important;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06) !important;
    border: 1px solid rgba(14, 165, 233, 0.12) !important;
    border-radius: 8px !important;
    padding: 0.5rem 0.75rem !important;
    font-size: 0.875rem !important;
}
.jobbox-tooltip .bg-primary { background-color: #eff6ff !important; }
.jobbox-tooltip .fill-primary { fill: #eff6ff !important; }
.jobbox-tooltip .size-2\.5 { width: 10px; height: 10px; }
</style>

<style scoped>
.new-highlight { background-color: #fff7cc; }
</style>
