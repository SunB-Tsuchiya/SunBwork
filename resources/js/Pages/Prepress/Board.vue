<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    tickets: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
});

const COLUMNS = [
    { key: 'pending',     label: '予定',   color: 'border-yellow-400 bg-yellow-50', header: 'bg-yellow-100 text-yellow-800' },
    { key: 'in_progress', label: '作業中', color: 'border-blue-400 bg-blue-50',     header: 'bg-blue-100 text-blue-800' },
    { key: 'completed',   label: '完了',   color: 'border-green-500 bg-green-50',   header: 'bg-green-100 text-green-800' },
];

// Local optimistic state
const localTickets = ref(props.tickets.map(t => ({ ...t })));

const ticketsByStatus = computed(() => {
    const map = {};
    COLUMNS.forEach(c => { map[c.key] = []; });
    localTickets.value.forEach(t => {
        if (map[t.status]) map[t.status].push(t);
    });
    return map;
});

// Drag & Drop state
const draggedId = ref(null);
const dragOverColumn = ref(null);

// Lightbox
const lightboxTicket = ref(null);
function openLightbox(ticket) {
    if (!ticket.image_url) return;
    lightboxTicket.value = ticket;
}
function closeLightbox() {
    lightboxTicket.value = null;
}

function onDragStart(ticket) {
    draggedId.value = ticket.id;
}

function onDragOver(colKey) {
    dragOverColumn.value = colKey;
}

function onDragLeave() {
    dragOverColumn.value = null;
}

function onDrop(colKey) {
    dragOverColumn.value = null;
    if (draggedId.value === null) return;

    const ticket = localTickets.value.find(t => t.id === draggedId.value);
    if (!ticket || ticket.status === colKey) {
        draggedId.value = null;
        return;
    }

    // Optimistic update
    const prevStatus = ticket.status;
    ticket.status = colKey;
    const id = draggedId.value;
    draggedId.value = null;

    axios.patch(
        route('prepress.board.updateStatus', { ticket: id }),
        { status: colKey }
    ).catch(() => {
        const t = localTickets.value.find(t => t.id === id);
        if (t) t.status = prevStatus;
    });
}

function onDragEnd() {
    draggedId.value = null;
    dragOverColumn.value = null;
}

// ── 伝票登録モーダル ─────────────────────────────────
const showCreateModal = ref(false);
const createMode = ref('new'); // 'new' | 'from_job'

// 案件から読み込む
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

// クライアント名オートコンプリート
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

// クライアントID直接入力
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

// 作成ボタン
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
    <AppLayout title="伝票ボード">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">伝票ボード</h2>
                <button
                    type="button"
                    class="rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800"
                    @click="openCreateModal"
                >＋ 伝票登録</button>
            </div>
        </template>

        <!-- 画面幅いっぱいに広げる -->
        <div class="-mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="min-w-[720px] px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-3 gap-4">
                    <div
                        v-for="col in COLUMNS"
                        :key="col.key"
                        class="flex flex-col rounded-xl border-2 transition-colors"
                        :class="[
                            col.color,
                            dragOverColumn === col.key ? 'ring-2 ring-green-500 ring-offset-1' : '',
                        ]"
                        @dragover.prevent="onDragOver(col.key)"
                        @dragleave="onDragLeave"
                        @drop="onDrop(col.key)"
                    >
                        <!-- 列ヘッダー -->
                        <div class="rounded-t-lg px-4 py-3" :class="col.header">
                            <span class="font-semibold">{{ col.label }}</span>
                            <span class="ml-2 rounded-full bg-white/60 px-2 py-0.5 text-xs font-medium">
                                {{ ticketsByStatus[col.key].length }}
                            </span>
                        </div>

                        <!-- カードエリア -->
                        <div class="min-h-[480px] space-y-3 p-3">
                            <div
                                v-for="ticket in ticketsByStatus[col.key]"
                                :key="ticket.id"
                                draggable="true"
                                class="cursor-grab rounded-lg border border-white bg-white px-4 py-3 shadow-sm transition-shadow hover:shadow-md active:cursor-grabbing"
                                :class="{ 'opacity-50 scale-95': draggedId === ticket.id }"
                                @dragstart="onDragStart(ticket)"
                                @dragend="onDragEnd"
                                @click="openLightbox(ticket)"
                            >
                                <!-- 伝票番号 + 画像インジケーター -->
                                <div class="mb-1 flex items-center justify-between">
                                    <span v-if="ticket.image_url" class="text-xs text-green-600" title="画像あり（クリックで表示）">🖼</span>
                                    <span v-else class="text-xs text-transparent">🖼</span>
                                    <span v-if="ticket.jobcode" class="text-xs text-gray-400">#{{ ticket.jobcode }}</span>
                                </div>
                                <!-- タイトル -->
                                <p class="font-semibold text-gray-800 leading-snug">{{ ticket.title }}</p>
                                <!-- 案件名 / クライアント名 -->
                                <p v-if="ticket.project_name || ticket.client_name" class="mt-1 text-xs text-gray-500">
                                    <span v-if="ticket.client_name">{{ ticket.client_name }}</span>
                                    <span v-if="ticket.client_name && ticket.project_name" class="mx-1 text-gray-300">／</span>
                                    <span v-if="ticket.project_name">{{ ticket.project_name }}</span>
                                </p>
                                <!-- メモ -->
                                <p v-if="ticket.memo" class="mt-1 line-clamp-2 text-xs text-gray-500">{{ ticket.memo }}</p>
                            </div>

                            <!-- 空エリアのドロップヒント -->
                            <div
                                v-if="ticketsByStatus[col.key].length === 0 && draggedId !== null"
                                class="flex h-20 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-sm text-gray-400"
                            >
                                ここにドロップ
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <!-- 画像ライトボックス -->
    <Teleport to="body">
        <div
            v-if="lightboxTicket"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85"
            @click.self="closeLightbox"
        >
            <div class="relative max-h-[92vh] max-w-[92vw]">
                <img
                    :src="lightboxTicket.image_url"
                    :alt="lightboxTicket.title"
                    class="max-h-[88vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                />
                <!-- 閉じるボタン -->
                <button
                    type="button"
                    class="absolute -right-3 -top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white text-gray-800 shadow-lg hover:bg-gray-100"
                    @click="closeLightbox"
                >✕</button>
                <!-- タイトル -->
                <div class="mt-3 rounded-lg bg-black/60 px-4 py-2 text-center text-sm text-white">
                    <span v-if="lightboxTicket.jobcode" class="mr-2 text-gray-400">#{{ lightboxTicket.jobcode }}</span>
                    {{ lightboxTicket.title }}
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

    </AppLayout>
</template>
