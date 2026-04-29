<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    tickets:        { type: Array,   default: () => [] },
    statuses:       { type: Object,  default: () => ({}) },
    monthOptions:   { type: Array,   default: () => [] },
    q:              { type: String,  default: '' },
    period:         { type: String,  default: '' },
    hide_completed: { type: Boolean, default: true },
});

const page = usePage();

// ── 検索・フィルター ─────────────────────────────
const qModel        = ref(props.q);
const periodModel   = ref(props.period || 'all');
const hideCompleted = ref(props.hide_completed);

watch(hideCompleted, () => applyFilters());
watch(periodModel,   () => applyFilters());

function applyFilters() {
    router.get(route('prepress.tickets.index'), {
        q:              qModel.value,
        period:         periodModel.value === 'all' ? '' : periodModel.value,
        hide_completed: hideCompleted.value,
    }, { preserveState: true, replace: true });
}

function search()      { applyFilters(); }
function clearSearch() { qModel.value = ''; applyFilters(); }

// ── グループ表示モード ────────────────────────────
const viewMode = ref('date');
const viewModes = [
    { key: 'date',    label: '日付ごと' },
    { key: 'client',  label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];

// ── ユーティリティ ────────────────────────────────
function formatDateLabel(dateStr) {
    if (!dateStr) return '日付なし';
    try {
        const d   = new Date(dateStr.slice(0, 10) + 'T00:00:00');
        const dow = ['日','月','火','水','木','金','土'][d.getDay()];
        return `${d.getFullYear()}年${d.getMonth()+1}月${d.getDate()}日（${dow}）`;
    } catch { return dateStr; }
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const p = String(dateStr).split('T')[0].split('-');
    return p.length === 3 ? `${p[0]}/${p[1]}/${p[2]}` : dateStr;
}

// ── グルーピング ──────────────────────────────────
const displayGroups = computed(() => {
    const items = props.tickets;
    if (!items || !items.length) return [];

    const map   = {};
    const order = [];

    for (const t of items) {
        let key, label;
        if (viewMode.value === 'client') {
            key   = t.client_name || '__none__';
            label = t.client_name || '（クライアント未設定）';
        } else if (viewMode.value === 'project') {
            key   = t.project_name || '__none__';
            label = t.project_name || '（案件名未設定）';
        } else {
            key   = t.created_at ? String(t.created_at).split('T')[0] : '__none__';
            label = key !== '__none__' ? formatDateLabel(key) : '日付なし';
        }
        if (!map[key]) { map[key] = { key, label, items: [] }; order.push(key); }
        map[key].items.push(t);
    }
    return order.map((k) => map[k]);
});

const totalCount = computed(() => (props.tickets ? props.tickets.length : 0));

// ── ステータスバッジ ──────────────────────────────
function statusBadgeClass(status) {
    switch (status) {
        case 'completed':   return 'bg-yellow-100 text-yellow-800';
        case 'in_progress': return 'bg-blue-100 text-blue-800';
        case 'pending':     return 'bg-red-100 text-red-800';
        default:            return 'bg-gray-100 text-gray-700';
    }
}

function statusLabel(status) {
    return props.statuses[status] ?? status;
}

// ── 詳細モーダル ──────────────────────────────────
const detail = ref(null);

function openDetail(ticket) { detail.value = ticket; }
function closeDetail()      { detail.value = null; }

// ステータス変更
const updatingStatus = ref(false);
async function changeStatus(ticket, newStatus) {
    if (updatingStatus.value) return;
    updatingStatus.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('prepress.tickets.updateStatus', { ticket: ticket.id }), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({ status: newStatus }),
        });
        if (res.ok) {
            const idx = props.tickets.findIndex((t) => t.id === ticket.id);
            if (idx >= 0) props.tickets[idx].status = newStatus;
            if (detail.value?.id === ticket.id) detail.value = { ...detail.value, status: newStatus };
        }
    } catch { /* ignore */ } finally {
        updatingStatus.value = false;
    }
}

// 削除
const deleting = ref(false);
function deleteTicket(ticket) {
    if (!confirm(`「${ticket.title}」を削除しますか？`)) return;
    deleting.value = true;
    router.delete(route('prepress.tickets.destroy', { ticket: ticket.id }), {
        onFinish: () => { deleting.value = false; closeDetail(); },
    });
}

const authUser = computed(() => page.props.auth?.user ?? null);
const isAdmin  = computed(() => ['admin', 'superadmin'].includes(authUser.value?.user_role));

// ── 詳細モーダル：画像アップロード ───────────────────────
const uploadingImage = ref(false);

async function uploadTicketImage(ticket, file) {
    if (!file || uploadingImage.value) return;
    uploadingImage.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const fd = new FormData();
    fd.append('image', file);
    try {
        const res = await fetch(route('prepress.tickets.updateImage', { ticket: ticket.id }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: fd,
        });
        if (res.ok) {
            const data = await res.json();
            const idx = props.tickets.findIndex(t => t.id === ticket.id);
            if (idx >= 0) {
                props.tickets[idx].image_path        = data.image_url ? data.image_url.replace('/storage/', '') : ticket.image_path;
                props.tickets[idx].original_filename = data.original_filename;
            }
            if (detail.value?.id === ticket.id) {
                detail.value = {
                    ...detail.value,
                    image_path:        props.tickets[idx]?.image_path ?? detail.value.image_path,
                    image_url:         data.image_url,
                    original_filename: data.original_filename,
                };
            }
        }
    } catch { /* ignore */ } finally {
        uploadingImage.value = false;
    }
}

// ── 伝票登録モーダル ─────────────────────────────────
const showCreateModal = ref(false);
const createMode = ref('new'); // 'new' | 'from_job'

const clientId   = ref('');
const clientName = ref('');
const clientSuggestions = ref([]);
const showClientSuggestions = ref(false);
const selectedClientSuggestionIndex = ref(-1);
let clientSearchTimer = null;

const selectedJobId = ref('');
const projectJobs   = ref([]);
const loadingJobs   = ref(false);

function openCreateModal() {
    showCreateModal.value = true;
    createMode.value = 'new';
    resetModalState();
}

function closeCreateModal() {
    showCreateModal.value = false;
}

function resetModalState() {
    clientId.value   = '';
    clientName.value = '';
    clientSuggestions.value = [];
    showClientSuggestions.value = false;
    selectedClientSuggestionIndex.value = -1;
    selectedJobId.value = '';
    projectJobs.value   = [];
}

function onClientNameInput() {
    clientId.value = '';
    selectedJobId.value = '';
    projectJobs.value = [];
    clearTimeout(clientSearchTimer);
    if (!clientName.value.trim()) {
        clientSuggestions.value = [];
        showClientSuggestions.value = false;
        return;
    }
    clientSearchTimer = setTimeout(async () => {
        const res = await axios.get(route('prepress.api.clients'), { params: { q: clientName.value } });
        clientSuggestions.value = res.data;
        showClientSuggestions.value = true;
        selectedClientSuggestionIndex.value = -1;
    }, 250);
}

function onClientNameKeydown(e) {
    if (!showClientSuggestions.value || clientSuggestions.value.length === 0) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedClientSuggestionIndex.value = Math.min(selectedClientSuggestionIndex.value + 1, clientSuggestions.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedClientSuggestionIndex.value = Math.max(selectedClientSuggestionIndex.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const idx = selectedClientSuggestionIndex.value;
        if (idx >= 0 && clientSuggestions.value[idx]) {
            selectClient(clientSuggestions.value[idx]);
        }
    } else if (e.key === 'Escape') {
        showClientSuggestions.value = false;
    }
}

function onClientNameBlur() {
    setTimeout(() => { showClientSuggestions.value = false; }, 200);
}

function selectClient(client) {
    clientId.value   = client.id;
    clientName.value = client.name;
    showClientSuggestions.value = false;
    fetchProjectJobs(client.id);
}

let clientIdTimer = null;
function onClientIdInput() {
    clientName.value = '';
    selectedJobId.value = '';
    projectJobs.value = [];
    clearTimeout(clientIdTimer);
    if (!clientId.value) return;
    clientIdTimer = setTimeout(async () => {
        const res = await axios.get(route('prepress.api.clients'), { params: { q: '' } });
        const found = res.data.find(c => c.id == clientId.value);
        if (found) {
            clientName.value = found.name;
            fetchProjectJobs(found.id);
        }
    }, 400);
}

async function fetchProjectJobs(cId) {
    if (!cId) return;
    loadingJobs.value = true;
    selectedJobId.value = '';
    try {
        const res = await axios.get(route('prepress.api.projectJobs'), { params: { client_id: cId } });
        projectJobs.value = res.data;
    } finally {
        loadingJobs.value = false;
    }
}

function handleCreate() {
    if (createMode.value === 'new') {
        router.get(route('prepress.tickets.create'));
    } else {
        if (!selectedJobId.value) return;
        router.get(route('prepress.tickets.create'), { project_job_id: selectedJobId.value });
    }
    closeCreateModal();
}

const canCreate = computed(() => {
    if (createMode.value === 'new') return true;
    return !!selectedJobId.value;
});
</script>

<template>
    <AppLayout title="伝票一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">伝票一覧</h2>
        </template>
        <template #headerExtras>
            <button
                type="button"
                class="rounded bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800"
                @click="openCreateModal"
            >＋ 伝票登録</button>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- 検索行 -->
            <div class="flex items-center gap-2">
                <input
                    v-model="qModel"
                    @keyup.enter="search"
                    placeholder="タイトル/詳細/担当で検索"
                    class="w-72 rounded border px-3 py-2 text-sm"
                />
                <button class="rounded bg-blue-600 px-3 py-2 text-sm text-white" @click.prevent="search">検索</button>
                <button class="ml-2 rounded border px-3 py-2 text-sm" @click.prevent="clearSearch">クリア</button>
            </div>

            <!-- 月セレクター + 完了非表示チェック -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select
                        v-model="periodModel"
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

            <!-- グループ表示切替 -->
            <div class="mt-4 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                <button
                    v-for="mode in viewModes"
                    :key="mode.key"
                    @click="viewMode = mode.key"
                    :class="viewMode === mode.key
                        ? 'bg-white text-green-700 font-semibold shadow-sm'
                        : 'text-gray-600 hover:text-gray-900'"
                    class="rounded px-4 py-1.5 text-sm transition-all"
                >{{ mode.label }}</button>
            </div>

            <!-- グループテーブル -->
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

                    <table class="w-full table-fixed border" style="min-width: 960px;">
                        <colgroup>
                            <col style="width: 100px">
                            <col style="width: 100px">
                            <col style="width: 120px">
                            <col>
                            <col style="width: 120px">
                            <col style="width: 150px">
                            <col style="width: 100px">
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作成者</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">受信者</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作成日</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">クライアント</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件名</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ticket in group.items"
                                :key="ticket.id"
                                class="cursor-pointer hover:bg-gray-100"
                                @click="openDetail(ticket)"
                                role="button"
                            >
                                <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ ticket.user?.name ?? '—' }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-400">—</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ formatDate(ticket.created_at) }}</td>
                                <td class="break-words border px-3 py-2 text-sm font-medium text-gray-800">{{ ticket.title }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ ticket.client_name || '—' }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ ticket.project_name || '—' }}</td>
                                <td class="border px-3 py-2">
                                    <span
                                        :class="statusBadgeClass(ticket.status)"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >{{ statusLabel(ticket.status) }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 text-sm text-gray-600">
                表示中 {{ totalCount }} 件
            </div>
        </div>
    </AppLayout>

    <!-- ── 詳細モーダル ─────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="detail"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 pt-10 pb-10"
            @click.self="closeDetail"
        >
            <div class="mx-auto w-full max-w-3xl space-y-4 px-4">

                <!-- メインカード（JobBox/Show.vue と同じ構造） -->
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <!-- カードヘッダー -->
                    <div class="flex items-start justify-between gap-3 border-b bg-gray-50 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap gap-1.5">
                                <span
                                    :class="statusBadgeClass(detail.status)"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                >{{ statusLabel(detail.status) }}</span>
                                <span v-if="detail.jobcode" class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">
                                    # {{ detail.jobcode }}
                                </span>
                            </div>
                            <h1 class="text-base font-bold text-gray-900">{{ detail.title }}</h1>
                            <p class="mt-0.5 text-sm text-gray-500">作成者: {{ detail.user?.name ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- ボタン類 -->
                    <div class="flex flex-wrap items-center gap-2 border-t bg-gray-50 px-5 py-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                            @click="closeDetail"
                        >閉じる</button>

                        <button
                            v-if="detail.status !== 'in_progress'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded bg-blue-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-600"
                            :disabled="updatingStatus"
                            @click="changeStatus(detail, 'in_progress')"
                        >作業中にする</button>

                        <button
                            v-if="detail.status !== 'completed'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded bg-yellow-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-600"
                            :disabled="updatingStatus"
                            @click="changeStatus(detail, 'completed')"
                        >完了にする</button>

                        <button
                            v-if="detail.status !== 'pending'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded bg-gray-400 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-500"
                            :disabled="updatingStatus"
                            @click="changeStatus(detail, 'pending')"
                        >予定に戻す</button>

                        <button
                            v-if="isAdmin"
                            type="button"
                            class="ml-auto inline-flex items-center gap-1.5 rounded bg-red-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-600"
                            :disabled="deleting"
                            @click="deleteTicket(detail)"
                        >削除</button>
                    </div>
                </div>

                <!-- 詳細情報カード -->
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">伝票詳細</h2>
                    </div>
                    <dl class="divide-y divide-gray-100 px-5 py-2 text-sm">
                        <div class="flex py-2">
                            <dt class="w-32 shrink-0 font-medium text-gray-500">クライアント</dt>
                            <dd class="text-gray-800">{{ detail.client_name || '—' }}</dd>
                        </div>
                        <div class="flex py-2">
                            <dt class="w-32 shrink-0 font-medium text-gray-500">案件名</dt>
                            <dd class="text-gray-800">{{ detail.project_name || '—' }}</dd>
                        </div>
                        <div class="flex py-2">
                            <dt class="w-32 shrink-0 font-medium text-gray-500">伝票番号</dt>
                            <dd class="text-gray-800">{{ detail.jobcode || '—' }}</dd>
                        </div>
                        <div class="flex py-2">
                            <dt class="w-32 shrink-0 font-medium text-gray-500">作成日</dt>
                            <dd class="text-gray-800">{{ formatDate(detail.created_at) }}</dd>
                        </div>
                        <div v-if="detail.memo" class="flex py-2">
                            <dt class="w-32 shrink-0 font-medium text-gray-500">メモ</dt>
                            <dd class="whitespace-pre-wrap text-gray-800">{{ detail.memo }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- 添付画像カード -->
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">添付画像（伝票画像）</h2>
                        <label class="cursor-pointer rounded border border-green-700 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-50">
                            {{ detail.image_path ? '画像を変更' : '画像を登録' }}
                            <input
                                type="file"
                                accept="image/*,.pdf"
                                class="hidden"
                                :disabled="uploadingImage"
                                @change="e => uploadTicketImage(detail, e.target.files?.[0])"
                            />
                        </label>
                    </div>
                    <div v-if="detail.image_path" class="px-5 py-4">
                        <img
                            :src="detail.image_url ?? ('/storage/' + detail.image_path)"
                            :alt="detail.original_filename ?? 'image'"
                            class="max-w-full rounded border border-gray-200"
                        />
                        <p v-if="detail.original_filename" class="mt-1 text-xs text-gray-400">{{ detail.original_filename }}</p>
                    </div>
                    <div v-else class="px-5 py-6 text-center text-sm text-gray-400">
                        <p>画像が登録されていません。</p>
                        <p v-if="uploadingImage" class="mt-1 text-blue-500">アップロード中...</p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- 伝票登録モーダル -->
    <Teleport to="body">
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
            @click.self="closeCreateModal"
        >
            <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
                <!-- モーダルヘッダー -->
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">伝票登録</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeCreateModal">✕</button>
                </div>

                <!-- モード選択 -->
                <div class="px-6 pt-5">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border-2 py-3 text-sm font-medium transition-colors"
                            :class="createMode === 'new'
                                ? 'border-green-600 bg-green-50 text-green-700'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                            @click="createMode = 'new'; resetModalState()"
                        >新規作成</button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border-2 py-3 text-sm font-medium transition-colors"
                            :class="createMode === 'from_job'
                                ? 'border-green-600 bg-green-50 text-green-700'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                            @click="createMode = 'from_job'; resetModalState()"
                        >案件から読み込む</button>
                    </div>
                </div>

                <!-- 案件から読み込む -->
                <div v-if="createMode === 'from_job'" class="space-y-4 px-6 pt-5">
                    <!-- クライアント -->
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">クライアント</label>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1">
                                <label class="text-sm text-gray-500">ID:</label>
                                <input
                                    v-model="clientId"
                                    type="number"
                                    class="w-20 rounded border border-gray-300 px-2 py-2 text-sm focus:border-green-600 focus:outline-none"
                                    placeholder="ID"
                                    @input="onClientIdInput"
                                />
                            </div>
                            <div class="relative flex-1">
                                <input
                                    v-model="clientName"
                                    type="text"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none"
                                    placeholder="名前を入力（オートコンプリート）"
                                    @input="onClientNameInput"
                                    @keydown="onClientNameKeydown"
                                    @blur="onClientNameBlur"
                                />
                                <div
                                    v-if="showClientSuggestions && clientSuggestions.length > 0"
                                    class="absolute top-full z-50 mt-1 w-full overflow-y-auto rounded border border-gray-300 bg-white shadow-lg max-h-52"
                                >
                                    <div
                                        v-for="(client, idx) in clientSuggestions"
                                        :key="client.id"
                                        class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                                        :class="{ 'bg-blue-100': idx === selectedClientSuggestionIndex }"
                                        @mousedown.prevent="selectClient(client)"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">{{ client.name }}</span>
                                            <span class="text-xs text-gray-400">ID: {{ client.id }}</span>
                                        </div>
                                        <div v-if="client.is_dormant" class="text-xs text-red-500">※ 休眠中</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 案件セレクター -->
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">案件</label>
                        <div v-if="loadingJobs" class="text-sm text-blue-600">読込中...</div>
                        <select
                            v-else
                            v-model="selectedJobId"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none"
                            :disabled="!clientId || projectJobs.length === 0"
                        >
                            <option value="">
                                {{ !clientId ? 'クライアントを先に選択してください' : projectJobs.length === 0 ? '案件がありません' : '案件を選択してください' }}
                            </option>
                            <option v-for="job in projectJobs" :key="job.id" :value="job.id">
                                {{ job.jobcode ? `[${job.jobcode}] ` : '' }}{{ job.title }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- 新規作成の説明 -->
                <div v-if="createMode === 'new'" class="px-6 pt-5">
                    <p class="text-sm text-gray-500">空白の伝票登録フォームに移動します。</p>
                </div>

                <!-- フッター -->
                <div class="flex items-center justify-end gap-3 border-t px-6 py-4 mt-5">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                        @click="closeCreateModal"
                    >キャンセル</button>
                    <button
                        type="button"
                        class="rounded-lg bg-green-700 px-6 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50"
                        :disabled="!canCreate"
                        @click="handleCreate"
                    >作成</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
