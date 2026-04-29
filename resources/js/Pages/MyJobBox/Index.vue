<template>
    <AppLayout :title="`MyJobBox - ${props.projectJob?.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">MyJobBox — マイジョブ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">MyJobBox：{{ props.projectJob?.name || '' }}</h1>

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="タイトル/詳細で検索"
                        class="w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-blue-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="typeof route === 'function' ? route('user.project_jobs.assignments.create') : '/project_jobs/assignments/create-user'"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm text-white"
                        >ジョブ作成（独自）</Link
                    >
                    <button
                        @click="openJobSheetModal"
                        class="rounded bg-purple-600 px-4 py-2 text-sm text-white"
                    >ジョブ作成（進行表から）</button>
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
                        <option v-for="mo in monthOptions" :key="mo.value" :value="mo.value">
                            {{ mo.label }}
                        </option>
                    </select>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                    <input type="checkbox" v-model="hideCompleted" class="h-4 w-4 rounded border-gray-300" />
                    完了を表示しない
                </label>
            </div>

            <!-- グループ表示切替タブ -->
            <div class="mt-4 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                <button
                    v-for="mode in viewModes"
                    :key="mode.key"
                    @click="viewMode = mode.key"
                    :class="viewMode === mode.key
                        ? 'bg-white text-blue-700 font-semibold shadow-sm'
                        : 'text-gray-600 hover:text-gray-900'"
                    class="rounded px-4 py-1.5 text-sm transition-all"
                >{{ mode.label }}</button>
            </div>

            <!-- グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    表示するデータがありません。
                </div>

                <template v-for="group in displayGroups" :key="group.key">
                    <!-- グループヘッダー -->
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full table-fixed border" style="min-width: 720px;">
                        <colgroup>
                            <col style="width: 140px"> <!-- 日付 -->
                            <col style="width: 25%">   <!-- タイトル -->
                            <col style="width: 160px"> <!-- クライアント -->
                            <col>                      <!-- 案件 -->
                            <col style="width: 160px">  <!-- 種類 (幅を広げる) -->
                            <col style="width: 88px">  <!-- ステータス -->
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">日付</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">クライアント</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">種類</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in group.items"
                                :key="m.id"
                                :class="['cursor-pointer hover:bg-gray-100', m.__is_new ? 'new-highlight' : '', m.__chain_depth > 0 ? 'bg-orange-50' : '']"
                                @click.prevent="rowClick(m, $event)"
                                role="button"
                            >
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ getDateDisplay(m) }}</td>
                                <td class="break-words border px-3 py-2 text-sm">
                                    <span v-if="m.__chain_depth > 0"
                                          class="mr-1 inline-block text-orange-300"
                                          :style="{ paddingLeft: (m.__chain_depth * 12) + 'px' }">└</span>
                                    <span v-if="m.source_assignment_id"
                                          class="mr-1 inline-flex items-center rounded-full bg-orange-100 px-1.5 py-0.5 text-xs font-medium text-orange-700">
                                        ↩続き#{{ m.source_assignment_id }}
                                    </span>
                                    <span v-if="m.proof_completed_at" class="mr-1 inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">校正済</span>{{ m.title || '-' }}
                                </td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ getClientName(m) }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ getProjectJobTitle(m) }}</td>
                                <td class="break-words border px-3 py-2 text-sm">
                                    <span
                                        v-if="getAssignmentKind(m)"
                                        class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-medium whitespace-nowrap"
                                        :style="{ backgroundColor: getAssignmentKind(m).color, color: getAssignmentKind(m).textColor }">
                                        {{ getAssignmentKind(m).label }}
                                    </span>
                                </td>
                                <td class="border px-3 py-2">
                                    <span
                                        :class="statusBadgeClass(getMyJobStatus(m))"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >{{ getMyJobStatus(m) }}</span>
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

        <!-- 進行表から案件選択モーダル -->
        <Teleport to="body">
            <div v-if="showJobSheetModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showJobSheetModal = false">
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h2 class="mb-4 text-lg font-bold">案件を選択（進行表から）</h2>

                    <div v-if="jobSheetLoading" class="py-8 text-center text-sm text-gray-500">読み込み中…</div>
                    <div v-else>
                        <!-- クライアント選択 -->
                        <div class="mb-3">
                            <label class="mb-1 block text-sm font-medium text-gray-700">クライアント</label>
                            <select v-model="jsSelectedClientId" @change="jsSelectedProjectId = ''" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="">— 選択してください —</option>
                                <option v-for="c in jsClients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>

                        <!-- 案件選択（クライアント選択後） -->
                        <div v-if="jsSelectedClientId" class="mb-3">
                            <label class="mb-1 block text-sm font-medium text-gray-700">案件</label>
                            <select v-model="jsSelectedProjectId" @change="onProjectChange" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="">— 選択してください —</option>
                                <option v-for="p in jsFilteredProjects" :key="p.id" :value="String(p.id)">{{ p.title || p.name }}</option>
                            </select>
                        </div>

                        <!-- 進行表選択（案件選択後・複数シートの場合） -->
                        <div v-if="jsSelectedProjectId && jsProgressSheets.length > 1" class="mb-3">
                            <label class="mb-1 block text-sm font-medium text-gray-700">進行表</label>
                            <select v-model="jsSelectedSheetId" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="">— 選択してください —</option>
                                <option v-for="s in jsProgressSheets" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                        </div>

                        <!-- 案件選択後のアクション -->
                        <div v-if="jsSelectedProjectId" class="mt-4 flex items-center justify-end gap-2">
                            <span v-if="jsSheetsLoading" class="text-sm text-gray-400">読み込み中…</span>
                            <span v-else-if="jsProgressSheets.length === 0 && !jsSheetsLoading" class="text-sm text-gray-400">進行表なし</span>
                            <button
                                @click="goToProgressSheet"
                                :disabled="!canGoToSheet"
                                :class="canGoToSheet
                                    ? 'rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700'
                                    : 'cursor-not-allowed rounded bg-gray-300 px-4 py-2 text-sm font-medium text-gray-500'"
                            >詳細を見る（進行表へ）</button>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button @click="showJobSheetModal = false" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">閉じる</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

// ─── 進行表から案件選択モーダル ───────────────────────────────────────────
const showJobSheetModal = ref(false);
const jobSheetLoading = ref(false);
const jsClients = ref([]);
const jsProjects = ref([]);
const jsSelectedClientId = ref('');
const jsSelectedProjectId = ref('');
const jsProgressSheets = ref([]);
const jsSelectedSheetId = ref('');
const jsSheetsLoading = ref(false);

const jsFilteredProjects = computed(() => {
    if (!jsSelectedClientId.value) return [];
    return jsProjects.value.filter((p) => String(p.client_id) === String(jsSelectedClientId.value));
});

const canGoToSheet = computed(() => {
    if (jsSheetsLoading.value || jsProgressSheets.value.length === 0) return false;
    if (jsProgressSheets.value.length === 1) return true;
    return !!jsSelectedSheetId.value;
});

async function openJobSheetModal() {
    jsSelectedClientId.value = '';
    jsSelectedProjectId.value = '';
    jsProgressSheets.value = [];
    jsSelectedSheetId.value = '';
    showJobSheetModal.value = true;

    if (jsClients.value.length === 0) {
        jobSheetLoading.value = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch(route('user.project_jobs.json'), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.clients?.length) jsClients.value = data.clients;
                if (data.projects?.length) jsProjects.value = data.projects;
            }
        } catch (e) {
            // ignore
        } finally {
            jobSheetLoading.value = false;
        }
    }
}

async function onProjectChange() {
    jsProgressSheets.value = [];
    jsSelectedSheetId.value = '';
    if (!jsSelectedProjectId.value) return;
    jsSheetsLoading.value = true;
    try {
        const res = await fetch(route('user.project_jobs.progress_sheets_json', { projectJob: jsSelectedProjectId.value }), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            jsProgressSheets.value = await res.json();
            if (jsProgressSheets.value.length === 1) {
                jsSelectedSheetId.value = String(jsProgressSheets.value[0].id);
            }
        }
    } catch (e) {
        // ignore
    } finally {
        jsSheetsLoading.value = false;
    }
}

function goToProgressSheet() {
    if (!canGoToSheet.value) return;
    const sheetId = jsProgressSheets.value.length === 1
        ? jsProgressSheets.value[0].id
        : jsSelectedSheetId.value;
    if (!sheetId) return;
    showJobSheetModal.value = false;
    router.visit(route('user.progress_sheets.show', { sheet: sheetId }));
}
// ──────────────────────────────────────────────────────────────────────────

const props = defineProps({ projectJob: Object, messages: Object, myAssignments: Object });
const page = usePage();
page.props.q_model = page.props.q || '';
page.props.period_model = page.props.period ?? '';
const monthOptions = computed(() => (Array.isArray(page.props.monthOptions) ? page.props.monthOptions : []));

// ローカル状態（完了ボタンで即時更新するため）
// Inertia プロキシをシャローコピーして純粋な JS オブジェクトにする（Vue リアクティビティのため）
const toPlain = (arr) => (Array.isArray(arr) ? arr.map((item) => ({ ...item })) : []);
const localAssignments = ref(toPlain(props.myAssignments?.data));

// Inertia が props を更新した際（DB 最新値）に反映する
watch(() => props.myAssignments?.data, (newData) => {
    localAssignments.value = toPlain(newData);
});

// 完了非表示
const hideCompleted = ref(true);

// グループ表示モード
const viewMode = ref('date');
const viewModes = [
    { key: 'date', label: '日付ごと' },
    { key: 'client', label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];

// ===== ユーティリティ =====

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

// UTC ISO 文字列（例 "2026-04-18T06:00:00+00:00"）を JST の {date, time} に変換
function toJST(s) {
    if (!s) return null;
    try {
        const d = new Date(s);
        if (isNaN(d.getTime())) return null;
        const fmt = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Tokyo',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: false,
        });
        const parts = Object.fromEntries(fmt.formatToParts(d).map(({ type, value }) => [type, value]));
        return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
    } catch { return null; }
}

function getDateKey(m) {
    const da = m.desired_at ? String(m.desired_at).split('T')[0].split(' ')[0] : null;
    const de = m.desired_end_date ? String(m.desired_end_date).split('T')[0] : null;
    if (m.event_date_jst) return da || de || m.event_date_jst;
    const firstEvent = getFirstEvent(m);
    if (firstEvent) {
        const s = firstEvent.start || firstEvent.starts_at || '';
        const jst = toJST(s);
        if (jst) return da || de || jst.date || '';
    }
    return da || de || (m.created_at ? String(m.created_at).split('T')[0] : '') || '';
}

function getStartTime(m) {
    if (m.event_start_jst) return m.event_start_jst;
    const firstEvent = getFirstEvent(m);
    if (firstEvent) {
        const s = firstEvent.start || firstEvent.starts_at || '';
        const jst = toJST(s);
        if (jst) return jst.time;
        if (s.includes(' ')) return s.split(' ')[1]?.slice(0, 5) || '-';
    }
    const t = m.desired_time || '';
    if (!t) return '-';
    return String(t).slice(0, 5);
}

function getTimeKey(m) {
    if (m.event_start_jst) return m.event_start_jst;
    const firstEvent = getFirstEvent(m);
    if (firstEvent) {
        const s = firstEvent.start || firstEvent.starts_at || '';
        const jst = toJST(s);
        if (jst) return jst.time;
        if (s.includes(' ')) return s.split(' ')[1]?.slice(0, 5) || '00:00';
    }
    return m.desired_time ? String(m.desired_time).slice(0, 5) : '00:00';
}

function getFirstEvent(m) {
    try {
        if (m.events) {
            if (Array.isArray(m.events) && m.events.length) return m.events[0];
            if (m.events.data && Array.isArray(m.events.data) && m.events.data.length) return m.events.data[0];
        }
    } catch (e) {}
    return null;
}

function getClientName(m) {
    try {
        return m.projectJob?.client?.name || m.project_job?.client?.name || '-';
    } catch { return '-'; }
}

function getProjectJobTitle(m) {
    try {
        return m.projectJob?.title || m.projectJob?.name || m.project_job?.title || m.project_job?.name || '-';
    } catch { return '-'; }
}

/**
 * Determine assignment kind and badge color.
 * - ジョブ（依頼）: supersedes_assignment_id がある（依頼を受けて作成した応答） -> 紫
 * - ジョブ（独自）: sender_id === user_id -> インディゴ
 * - 予定: その他（予定作成など） -> エメラルド
 */
function getAssignmentKind(m) {
    try {
        const userId = page.props.auth?.user?.id || null;
        if (m.job_type === 'proof') {
            return { label: '校正', color: '#DB2777', textColor: '#FFFFFF' };
        }
        if (m.supersedes_assignment_id) {
            return { label: 'ジョブ（依頼）', color: '#7C3AED', textColor: '#FFFFFF' };
        }
            // If linked to progress sheet, mark as progress-linked (優先)
            if (m.has_progress_cell) {
                return { label: 'ジョブ（進行）', color: '#7C3AED', textColor: '#FFFFFF' };
            }
            // sender_id may be present; compare to user or to m.user_id
            const sender = m.sender_id ?? m.sender?.id ?? null;
            const owner = m.user_id ?? m.user?.id ?? userId;
            if (sender && owner && String(sender) === String(owner)) {
                return { label: 'ジョブ（独自）', color: '#4F46E5', textColor: '#FFFFFF' };
            }
        // fallback to 予定
        return { label: '予定', color: '#059669', textColor: '#FFFFFF' };
    } catch (e) {
        return { label: '予定', color: '#059669', textColor: '#FFFFFF' };
    }
}

function getMyJobStatus(m) {
    try {
        if (Boolean(m.completed)) return '完了';
        if (Boolean(m.accepted) || Boolean(m.scheduled) || m.scheduled_at) return 'セット済';
        if (m.read_at) return '確認済み';
        if (Boolean(m.assigned)) return '送信';
        return '-';
    } catch {
        return '-';
    }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':    return 'bg-yellow-100 text-yellow-800';
        case 'セット済':  return 'bg-blue-100 text-blue-800';
        case '確認済み': return 'bg-green-100 text-green-800';
        case '送信':    return 'bg-gray-200 text-gray-700';
        default:        return 'bg-gray-100 text-gray-500';
    }
}

function getDateDisplay(m) {
    const dk = getDateKey(m);
    if (!dk) return '-';
    const parts = String(dk).split('-');
    if (parts.length !== 3) return dk;
    const formatted = `${parts[0]}/${parts[1]}/${parts[2]}`;
    const time = getStartTime(m);
    if (time && time !== '-') return `${formatted}\n${time}`;
    return formatted;
}

function getGroupKey(m) {
    if (viewMode.value === 'client') return getClientName(m) || '未設定';
    if (viewMode.value === 'project') return getProjectJobTitle(m) || '未設定';
    return getDateKey(m);
}

function getGroupLabel(key) {
    if (viewMode.value === 'date') return formatDateLabel(key);
    return key || '未設定';
}

// ===== 表示データ =====

const displayGroups = computed(() => {
    let assignments = Array.isArray(localAssignments.value) ? localAssignments.value : [];
    if (hideCompleted.value) {
        assignments = assignments.filter((m) => getMyJobStatus(m) !== '完了');
    }

    // チェーン順にソート: source_assignment_id を持つアイテムをその親の直後に並べる
    function sortWithChain(items) {
        const byId = new Map(items.map((m) => [m.id, m]));
        // ルート（source_assignment_id を持たない、またはグループ外参照）を日付順に並べる
        const roots = items.filter((m) => !m.source_assignment_id || !byId.has(m.source_assignment_id));
        const result = [];
        const visited = new Set();
        function appendWithChildren(item, depth) {
            if (visited.has(item.id)) return;
            visited.add(item.id);
            result.push({ ...item, __chain_depth: depth });
            // このアイテムを source とする続きジョブを探して追加
            const children = items
                .filter((m) => String(m.source_assignment_id) === String(item.id))
                .sort((a, b) => (getDateKey(a) || '').localeCompare(getDateKey(b) || ''));
            for (const child of children) {
                appendWithChildren(child, depth + 1);
            }
        }
        // 日付順にルートを並べてから追加
        roots.sort((a, b) => {
            const da = getDateKey(a) || '';
            const db = getDateKey(b) || '';
            if (da !== db) return da.localeCompare(db);
            return getTimeKey(a).localeCompare(getTimeKey(b));
        });
        for (const root of roots) {
            appendWithChildren(root, 0);
        }
        // まだ未追加のアイテム（孤立チェーン）を追加
        for (const m of items) {
            if (!visited.has(m.id)) result.push({ ...m, __chain_depth: 0 });
        }
        return result;
    }

    const grouped = new Map();
    for (const m of assignments) {
        const key = getGroupKey(m);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(m);
    }

    for (const [key, items] of grouped.entries()) {
        grouped.set(key, sortWithChain(items));
    }

    const sortedKeys = Array.from(grouped.keys());
    if (viewMode.value === 'date') {
        sortedKeys.sort((a, b) => {
            if (!a) return 1;
            if (!b) return -1;
            return b.localeCompare(a);
        });
    } else {
        sortedKeys.sort((a, b) => a.localeCompare(b, 'ja'));
    }

    return sortedKeys.map((key) => ({
        key,
        date: key,
        label: getGroupLabel(key),
        items: grouped.get(key),
    }));
});

const totalDisplayCount = computed(() => displayGroups.value.reduce((sum, g) => sum + g.items.length, 0));

const hiddenCompletedCount = computed(() => {
    if (!hideCompleted.value) return 0;
    const all = Array.isArray(localAssignments.value) ? localAssignments.value : [];
    return all.filter((m) => getMyJobStatus(m) === '完了').length;
});

// ===== ナビゲーション =====

function search() {
    try {
        router.get(route('user.myjobbox.index'), { q: page.props.q_model, period: page.props.period_model }, { preserveState: false });
    } catch (err) {
        const params = new URLSearchParams();
        params.set('q', page.props.q_model || '');
        params.set('period', page.props.period_model === undefined ? '' : page.props.period_model);
        window.location.href = route('user.myjobbox.index') + '?' + params.toString();
    }
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

function getBackLink() {
    try {
        if (props.projectJob?.id) {
            return typeof route === 'function' ? route('project_jobs.show', props.projectJob.id) : `/project_jobs/${props.projectJob.id}`;
        }
        return typeof route === 'function' ? route('dashboard') : '/';
    } catch (e) {
        return '/';
    }
}

function getAssignmentLink(m) {
    try {
        return typeof route === 'function' ? route('user.myjobbox.show', { assignment: m.id }) : `/myjobbox/${m.id}`;
    } catch (e) {
        return '#';
    }
}

async function rowClick(m, event) {
    const tag = event.target?.tagName?.toLowerCase() || '';
    if (tag === 'a' || tag === 'button' || event.target.closest?.('a,button')) return;

    // Try to navigate to linked event first
    try {
        const assId = m.id;
        const userId = m.user?.id || m.user_id || page.props.auth?.user?.id || '';
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
                const res = await fetch(eventsUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.ok) {
                    const payload = await res.json();
                    if (Array.isArray(payload) && payload.length > 0) {
                        const ev = payload[0];
                        const evId = ev.id || ev.event_id || ev.extendedProps?.event_id || ev.extendedProps?.id;
                        if (evId) {
                            try { router.get(typeof route === 'function' ? route('events.show', evId) : '/events/' + evId); return; } catch {}
                            try { window.location.href = route('events.show', { event: evId }); return; } catch {}
                        }
                    }
                }
            } catch {}
        }
    } catch {}

    const url = getAssignmentLink(m);
    if (url && url !== '#') {
        try { router.visit(url, { preserveState: false }); } catch (e) { window.location.href = url; }
    }
}

</script>

<style scoped>
.new-highlight { background-color: #fff7cc; }
</style>
