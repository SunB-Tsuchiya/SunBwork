<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import OcrModal from '@/Components/Prepress/OcrModal.vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    tickets: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
});

const COLUMNS = [
    { key: 'pending',     label: '準備',    color: 'border-yellow-400 bg-yellow-50', header: 'bg-yellow-100 text-yellow-800', barText: 'text-yellow-800' },
    { key: 'submitting',  label: '入稿予定', color: 'border-purple-400 bg-purple-50', header: 'bg-purple-100 text-purple-800', barText: 'text-purple-800' },
    { key: 'in_progress', label: '作業中',  color: 'border-blue-400 bg-blue-50',     header: 'bg-blue-100 text-blue-800',     barText: 'text-blue-800'   },
    { key: 'completed',   label: '完了',    color: 'border-green-500 bg-green-50',   header: 'bg-green-100 text-green-800',   barText: 'text-green-800'  },
];

// 遷移ルール（どの列からでも他の列へ移動可能）
const ALL_KEYS = ['pending', 'submitting', 'in_progress', 'completed'];
const VALID_TRANSITIONS = Object.fromEntries(
    ALL_KEYS.map(k => [k, ALL_KEYS.filter(t => t !== k)])
);

// カラム開閉状態: デフォルトは入稿予定・作業中の2列を開く
const openColumns = ref(new Set(['submitting', 'in_progress']));

function isOpen(key) {
    return openColumns.value.has(key);
}

function toggleColumn(key) {
    const s = new Set(openColumns.value);
    if (s.has(key)) {
        s.delete(key);
    } else {
        if (s.size >= 2) {
            // 一番右にある開いているカラムを自動で閉じる
            const rightmost = COLUMNS.map(c => c.key).filter(k => s.has(k)).at(-1);
            if (rightmost) s.delete(rightmost);
        }
        s.add(key);
    }
    openColumns.value = s;
}

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

// ── カラム別 ソート・絞り込み ──────────────────────────────────
const columnControls = ref(
    Object.fromEntries(COLUMNS.map(c => {
        const savedField = localStorage.getItem(`prepress_board_date_field_${c.key}`) ?? 'submission_date';
        return [c.key, { order: 'asc', dateFilter: '', dateRaw: '', dateField: savedField }];
    }))
);

function setDateField(colKey, field) {
    columnControls.value[colKey].dateField = field;
    columnControls.value[colKey].dateFilter = '';
    columnControls.value[colKey].dateRaw    = '';
    localStorage.setItem(`prepress_board_date_field_${colKey}`, field);
}

function parseToYMD(raw) {
    if (!raw || !raw.trim()) return '';
    const cleaned = raw.trim().replace(/[^0-9/]/g, '');
    let month, day;
    if (cleaned.includes('/')) {
        const [m, d] = cleaned.split('/');
        month = parseInt(m, 10);
        day   = parseInt(d, 10);
    } else {
        if (cleaned.length < 3) return '';
        if (cleaned.length === 3) {
            month = parseInt(cleaned[0], 10);
            day   = parseInt(cleaned.slice(1), 10);
        } else {
            month = parseInt(cleaned.slice(0, 2), 10);
            day   = parseInt(cleaned.slice(2, 4), 10);
        }
    }
    if (!month || !day || isNaN(month) || isNaN(day) || month < 1 || month > 12 || day < 1 || day > 31) return '';
    const now  = new Date();
    const curM = now.getMonth() + 1;
    let year   = now.getFullYear();
    // 今日が12月なら1月は来年
    if (curM === 12 && month === 1) year += 1;
    return `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
}

function applyDateFilter(colKey) {
    columnControls.value[colKey].dateFilter = parseToYMD(columnControls.value[colKey].dateRaw);
}

function clearDateFilter(colKey) {
    columnControls.value[colKey].dateFilter = '';
    columnControls.value[colKey].dateRaw    = '';
}

function onCalendarChange(colKey, ymd) {
    if (!ymd) return;
    columnControls.value[colKey].dateFilter = ymd;
    const [, m, d] = ymd.split('-');
    columnControls.value[colKey].dateRaw = `${parseInt(m,10)}/${parseInt(d,10)}`;
}

function formatShortDate(dateStr) {
    if (!dateStr) return '—';
    const d = String(dateStr).split('T')[0].replace(/\//g, '-').split('-');
    if (d.length < 3) return '—';
    return `${parseInt(d[1])}/${parseInt(d[2])}`;
}

function sortedFilteredTickets(colKey) {
    let list = ticketsByStatus.value[colKey] ?? [];
    const ctrl  = columnControls.value[colKey];
    const field = ctrl.dateField ?? 'submission_date';

    if (ctrl.dateFilter) {
        list = list.filter(t => {
            const d = t[field] ? String(t[field]).split('T')[0].replace(/\//g, '-') : '';
            return d === ctrl.dateFilter;
        });
    }

    const dir = ctrl.order === 'asc' ? 1 : -1;
    return [...list].sort((a, b) => {
        const da = a[field] ? String(a[field]).split('T')[0].replace(/\//g, '-') : '9999-99-99';
        const db = b[field] ? String(b[field]).split('T')[0].replace(/\//g, '-') : '9999-99-99';
        if (da === db) return 0;
        return (da < db ? -1 : 1) * dir;
    });
}

// ── 削除モード ──────────────────────────────────────────────
const deleteMode = ref(false);
const selectedForDelete = ref(new Set());

function toggleDeleteMode() {
    deleteMode.value = !deleteMode.value;
    if (!deleteMode.value) selectedForDelete.value = new Set();
}

function toggleSelectForDelete(id) {
    const s = new Set(selectedForDelete.value);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    selectedForDelete.value = s;
}

function selectAllForDelete(tickets) {
    selectedForDelete.value = new Set(tickets.map(t => t.id));
}

async function executeDelete() {
    const ids = [...selectedForDelete.value];
    if (ids.length === 0) return;
    if (!confirm(
        `${ids.length}件の伝票を完了ボックスから削除します。\n` +
        `データは伝票履歴に残りますが、画像は削除されます。\n` +
        `よろしいですか？`
    )) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    for (const id of ids) {
        try {
            await axios.patch(
                route('prepress.board.archiveFromCompleted', { ticket: id }),
                {},
                { headers: { 'X-CSRF-TOKEN': csrf } }
            );
            const ticket = localTickets.value.find(t => t.id === id);
            if (ticket) {
                ticket.status     = 'deleted';
                ticket.image_url  = null;
                ticket.image_path = null;
            }
        } catch { /* ignore */ }
    }
    selectedForDelete.value = new Set();
    deleteMode.value = false;
}

// Drag & Drop
const draggedId  = ref(null);
const draggedStatus = ref(null);
const dragOverColumn = ref(null);

function canDropTo(colKey) {
    if (!draggedStatus.value) return false;
    return VALID_TRANSITIONS[draggedStatus.value]?.includes(colKey) ?? false;
}

function onDragStart(ticket) {
    draggedId.value     = ticket.id;
    draggedStatus.value = ticket.status;
}

function onDragOver(colKey) {
    if (canDropTo(colKey)) dragOverColumn.value = colKey;
}

function onDragLeave() {
    dragOverColumn.value = null;
}

function onDrop(colKey) {
    dragOverColumn.value = null;
    if (draggedId.value === null) return;
    if (!canDropTo(colKey)) { draggedId.value = null; draggedStatus.value = null; return; }

    const ticket = localTickets.value.find(t => t.id === draggedId.value);
    if (!ticket || ticket.status === colKey) { draggedId.value = null; draggedStatus.value = null; return; }

    const prevStatus = ticket.status;
    ticket.status    = colKey;
    const id         = draggedId.value;
    draggedId.value     = null;
    draggedStatus.value = null;

    axios.patch(
        route('prepress.board.updateStatus', { ticket: id }),
        { status: colKey }
    ).catch(() => {
        const t = localTickets.value.find(t => t.id === id);
        if (t) t.status = prevStatus;
    });
}

function onDragEnd() {
    draggedId.value     = null;
    draggedStatus.value = null;
    dragOverColumn.value = null;
}

// ── 詳細モーダル (Tickets/Index.vue と同じ内容) ──────────────
const page      = usePage();
const authUser  = computed(() => page.props.auth?.user ?? null);
const isAdmin   = computed(() => ['admin', 'superadmin'].includes(authUser.value?.user_role));

const detail         = ref(null);
const updatingStatus = ref(false);
const deleting       = ref(false);
const uploadingImage = ref(false);
const uploadError    = ref('');
const pendingFile    = ref(null);
const pendingPreview = ref(null);

function openDetail(ticket) { detail.value = ticket; cancelPendingFile(); }
function closeDetail()      { detail.value = null;  cancelPendingFile(); }

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const p = String(dateStr).split('T')[0].split('-');
    return p.length === 3 ? `${p[0]}/${p[1]}/${p[2]}` : dateStr;
}

function statusBadgeClass(status) {
    switch (status) {
        case 'completed':   return 'bg-yellow-100 text-yellow-800';
        case 'in_progress': return 'bg-blue-100 text-blue-800';
        case 'submitting':  return 'bg-purple-100 text-purple-800';
        case 'pending':     return 'bg-red-100 text-red-800';
        default:            return 'bg-gray-100 text-gray-700';
    }
}

function statusLabel(status) {
    return props.statuses[status] ?? status;
}

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
            const idx = localTickets.value.findIndex((t) => t.id === ticket.id);
            if (idx >= 0) localTickets.value[idx].status = newStatus;
            if (detail.value?.id === ticket.id) detail.value = { ...detail.value, status: newStatus };
        }
    } catch { /* ignore */ } finally {
        updatingStatus.value = false;
    }
}

function deleteTicket(ticket) {
    if (!confirm(`「${ticket.title}」を削除しますか？`)) return;
    deleting.value = true;
    router.delete(route('prepress.tickets.destroy', { ticket: ticket.id }), {
        onFinish: () => { deleting.value = false; closeDetail(); },
    });
}

function selectPendingFile(file) {
    if (!file) return;
    uploadError.value = '';
    pendingFile.value = file;
    if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        pendingPreview.value = '__pdf__';
    } else {
        const reader = new FileReader();
        reader.onload = (e) => { pendingPreview.value = e.target.result; };
        reader.readAsDataURL(file);
    }
}

function cancelPendingFile() {
    pendingFile.value    = null;
    pendingPreview.value = null;
    uploadError.value    = '';
}

async function savePendingImage() {
    if (!pendingFile.value || uploadingImage.value) return;
    uploadingImage.value = true;
    uploadError.value    = '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const fd = new FormData();
    fd.append('image', pendingFile.value);
    try {
        const res = await fetch(route('prepress.tickets.updateImage', { ticket: detail.value.id }), {
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
            const tidx = localTickets.value.findIndex(t => t.id === detail.value.id);
            if (tidx >= 0) {
                localTickets.value[tidx].image_path        = data.image_url ? data.image_url.replace('/storage/', '') : detail.value.image_path;
                localTickets.value[tidx].original_filename = data.original_filename;
                localTickets.value[tidx].image_url         = data.image_url;
            }
            detail.value = {
                ...detail.value,
                image_path:        tidx >= 0 ? localTickets.value[tidx].image_path : detail.value.image_path,
                image_url:         data.image_url,
                original_filename: data.original_filename,
            };
            cancelPendingFile();
        } else {
            uploadError.value = '保存に失敗しました。もう一度お試しください。';
        }
    } catch {
        uploadError.value = '通信エラーが発生しました。';
    } finally {
        uploadingImage.value = false;
    }
}

// ── 伝票登録モーダル ─────────────────────────────────
const showCreateModal = ref(false);
const createMode = ref('ocr');

const clientId   = ref('');
const clientName = ref('');
const clientSuggestions = ref([]);
const showClientSuggestions = ref(false);
const selectedClientSuggestionIndex = ref(-1);
let clientSearchTimer = null;

const selectedJobId = ref('');
const projectJobs   = ref([]);
const loadingJobs   = ref(false);

// ── OCR（ファイル読み込み）────────────────────────────
const isOcrLoading  = ref(false);
const isDragOver    = ref(false);
const showOcrModal  = ref(false);
const ocrResult     = ref({});

function openCreateModal() {
    showCreateModal.value = true;
    createMode.value = 'ocr';
    resetModalState();
}

function closeCreateModal() {
    showCreateModal.value = false;
    showOcrModal.value = false;
    isOcrLoading.value = false;
    isDragOver.value   = false;
    ocrResult.value    = {};
}

// バックドロップクリック: OCRモード中は閉じない
function handleModalBackdropClick() {
    if (createMode.value === 'ocr') return;
    closeCreateModal();
}

async function triggerOcrFromBoard(file) {
    isOcrLoading.value = true;
    showOcrModal.value = false;
    const fd   = new FormData();
    fd.append('image', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(route('prepress.ocr.analyze'), fd, {
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'multipart/form-data' },
        });
        ocrResult.value    = res.data;
        showOcrModal.value = true;
    } catch {
        alert('OCR解析に失敗しました。ファイルを確認して再試行してください。');
    } finally {
        isOcrLoading.value = false;
    }
}

function onOcrDrop(e) {
    isDragOver.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) triggerOcrFromBoard(file);
}

function onOcrFileInput(e) {
    const file = e.target.files?.[0];
    if (file) triggerOcrFromBoard(file);
    e.target.value = '';
}

function onOcrApplyFromBoard(result) {
    router.get(route('prepress.tickets.create'), {
        client_id:          result.client_id         || '',
        client_name:        result.client_name       || '',
        jobcode:            result.jobcode            || '',
        title:              result.title             || '',
        tmp_ocr_image_path: result.tmp_image_path    || '',
        ocr_image_url:      result.image_url         || '',
        original_filename:  result.original_filename || '',
    });
    closeCreateModal();
}

function resetModalState() {
    clientId.value   = '';
    clientName.value = '';
    clientSuggestions.value = [];
    showClientSuggestions.value = false;
    selectedClientSuggestionIndex.value = -1;
    selectedJobId.value = '';
    projectJobs.value   = [];
    isOcrLoading.value  = false;
    isDragOver.value    = false;
    showOcrModal.value  = false;
    ocrResult.value     = {};
}

function onClientNameInput() {
    clientId.value = '';
    selectedJobId.value = '';
    projectJobs.value = [];
    clearTimeout(clientSearchTimer);
    if (!clientName.value.trim()) { clientSuggestions.value = []; showClientSuggestions.value = false; return; }
    clientSearchTimer = setTimeout(async () => {
        const res = await axios.get(route('prepress.api.clients'), { params: { q: clientName.value } });
        clientSuggestions.value = res.data;
        showClientSuggestions.value = true;
        selectedClientSuggestionIndex.value = -1;
    }, 250);
}

function onClientNameKeydown(e) {
    if (!showClientSuggestions.value || clientSuggestions.value.length === 0) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); selectedClientSuggestionIndex.value = Math.min(selectedClientSuggestionIndex.value + 1, clientSuggestions.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); selectedClientSuggestionIndex.value = Math.max(selectedClientSuggestionIndex.value - 1, 0); }
    else if (e.key === 'Enter') { e.preventDefault(); const idx = selectedClientSuggestionIndex.value; if (idx >= 0 && clientSuggestions.value[idx]) selectClient(clientSuggestions.value[idx]); }
    else if (e.key === 'Escape') { showClientSuggestions.value = false; }
}

function onClientNameBlur() { setTimeout(() => { showClientSuggestions.value = false; }, 200); }

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
        if (found) { clientName.value = found.name; fetchProjectJobs(found.id); }
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
    <AppLayout title="伝票ボード">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">伝票ボード</h2>
                <button
                    type="button"
                    class="rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800"
                    @click="openCreateModal"
                >＋ 伝票登録</button>
            </div>
        </template>

        <!-- 進行表と同様 100vw 全幅に拡張 -->
        <div style="width: 100vw; margin-left: calc(-50vw + 50%); padding-left: 1.5rem; padding-right: 1.5rem; box-sizing: border-box;">

            <!-- ボードエリア: 4列フレックス（折りたたみ対応） -->
            <div class="flex gap-2" style="height: calc(100vh - 155px);">
                <div
                    v-for="col in COLUMNS"
                    :key="col.key"
                    class="flex flex-col rounded-xl border-2 overflow-hidden"
                    :class="[
                        col.color,
                        isOpen(col.key) ? 'flex-1 min-w-0' : 'shrink-0',
                        dragOverColumn === col.key && canDropTo(col.key) ? 'ring-2 ring-offset-1 ring-green-500' : '',
                        draggedStatus && !canDropTo(col.key) && draggedId !== null ? 'opacity-40' : '',
                    ]"
                    :style="isOpen(col.key) ? {} : { width: '2.75rem' }"
                    @dragover.prevent="onDragOver(col.key)"
                    @dragleave="onDragLeave"
                    @drop="onDrop(col.key)"
                >
                    <!-- 折りたたみ時: 縦バー -->
                    <div
                        v-if="!isOpen(col.key)"
                        class="flex h-full cursor-pointer flex-col items-center py-3 select-none"
                        :class="col.header"
                        @click="toggleColumn(col.key)"
                    >
                        <span class="text-xs font-bold" :class="col.barText">▶</span>
                        <span
                            class="mt-2 text-xs font-semibold leading-none"
                            :class="col.barText"
                            style="writing-mode: vertical-rl;"
                        >{{ col.label }}</span>
                        <span
                            class="mt-2 rounded bg-white/80 px-1 py-0.5 text-center font-semibold leading-none min-w-[1.6rem]"
                            style="font-size: 11px;"
                        >{{ ticketsByStatus[col.key].length }}</span>
                    </div>

                    <!-- 展開時: 通常カラム -->
                    <template v-else>
                        <!-- 列ヘッダー -->
                        <div class="flex shrink-0 items-center rounded-t-lg px-4 py-3" :class="col.header">
                            <span class="font-semibold text-base">{{ col.label }}</span>
                            <span class="ml-2 rounded-full bg-white/60 px-2 py-0.5 text-xs font-medium">
                                {{ ticketsByStatus[col.key].length }}
                            </span>
                            <!-- 完了列専用: 削除モードボタン -->
                            <template v-if="col.key === 'completed'">
                                <button
                                    type="button"
                                    class="ml-auto rounded px-3 py-1 text-xs font-semibold transition-colors"
                                    :class="deleteMode
                                        ? 'bg-red-600 text-white hover:bg-red-700'
                                        : 'bg-white/70 text-red-700 hover:bg-white'"
                                    @click.stop="toggleDeleteMode"
                                >{{ deleteMode ? '削除モード終了' : '削除モード' }}</button>
                                <button
                                    v-if="deleteMode"
                                    type="button"
                                    class="ml-2 rounded bg-white/70 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-white"
                                    @click.stop="selectAllForDelete(ticketsByStatus['completed'])"
                                >全件チェック</button>
                                <button
                                    v-if="deleteMode && selectedForDelete.size > 0"
                                    type="button"
                                    class="ml-2 rounded bg-red-700 px-3 py-1 text-xs font-semibold text-white hover:bg-red-800"
                                    @click.stop="executeDelete"
                                >削除（{{ selectedForDelete.size }}件）</button>
                            </template>
                            <!-- 折りたたみボタン -->
                            <button
                                type="button"
                                class="rounded px-2 py-0.5 text-xs hover:bg-white/50"
                                :class="col.key !== 'completed' ? 'ml-auto' : 'ml-2'"
                                title="折りたたむ"
                                @click.stop="toggleColumn(col.key)"
                            >◀</button>
                        </div>

                        <!-- ソート・絞り込みコントロール -->
                        <div class="shrink-0 flex flex-wrap items-center gap-2 border-b bg-white/70 px-3 py-1.5">
                            <!-- 日付フィールド選択 -->
                            <div class="flex items-center gap-2 text-xs text-gray-700">
                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                    <input
                                        type="radio"
                                        :name="`date_field_${col.key}`"
                                        value="submission_date"
                                        :checked="columnControls[col.key].dateField === 'submission_date'"
                                        class="accent-teal-600"
                                        @change="setDateField(col.key, 'submission_date')"
                                    />入稿日
                                </label>
                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                    <input
                                        type="radio"
                                        :name="`date_field_${col.key}`"
                                        value="sb_delivery_date"
                                        :checked="columnControls[col.key].dateField === 'sb_delivery_date'"
                                        class="accent-teal-600"
                                        @change="setDateField(col.key, 'sb_delivery_date')"
                                    />下版日
                                </label>
                            </div>
                            <div class="flex gap-1">
                                <button
                                    type="button"
                                    class="rounded px-2 py-0.5 text-xs font-semibold transition-colors"
                                    :class="columnControls[col.key].order === 'asc'
                                        ? 'bg-teal-600 text-white'
                                        : 'border border-gray-300 text-gray-600 hover:bg-gray-50'"
                                    @click="columnControls[col.key].order = 'asc'"
                                >↑ 昇順</button>
                                <button
                                    type="button"
                                    class="rounded px-2 py-0.5 text-xs font-semibold transition-colors"
                                    :class="columnControls[col.key].order === 'desc'
                                        ? 'bg-teal-600 text-white'
                                        : 'border border-gray-300 text-gray-600 hover:bg-gray-50'"
                                    @click="columnControls[col.key].order = 'desc'"
                                >↓ 降順</button>
                            </div>
                            <span class="text-xs text-gray-500">日付で絞込:</span>
                            <div class="flex items-center gap-1">
                                <input
                                    type="text"
                                    v-model="columnControls[col.key].dateRaw"
                                    placeholder="MM/DD"
                                    maxlength="5"
                                    class="w-20 rounded border border-gray-300 px-1.5 py-0.5 text-xs"
                                    @change="applyDateFilter(col.key)"
                                    @keydown.enter.prevent="applyDateFilter(col.key)"
                                />
                                <div class="relative">
                                    <button
                                        type="button"
                                        class="rounded border border-gray-300 px-1.5 py-0.5 text-sm hover:bg-gray-50"
                                        title="カレンダーから選択"
                                    >🗓</button>
                                    <input
                                        type="date"
                                        :value="columnControls[col.key].dateFilter"
                                        class="absolute inset-0 w-full opacity-0 cursor-pointer"
                                        tabindex="-1"
                                        @change="onCalendarChange(col.key, $event.target.value)"
                                    />
                                </div>
                                <button
                                    v-if="columnControls[col.key].dateFilter || columnControls[col.key].dateRaw"
                                    type="button"
                                    class="rounded bg-gray-200 px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-300"
                                    @click="clearDateFilter(col.key)"
                                >✕</button>
                            </div>
                        </div>

                        <!-- カードグリッド: 2列 -->
                        <div class="flex-1 min-h-0 overflow-y-auto p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div
                                    v-for="ticket in sortedFilteredTickets(col.key)"
                                    :key="ticket.id"
                                    :draggable="!(col.key === 'completed' && deleteMode)"
                                    class="relative cursor-grab rounded border border-indigo-400 bg-white shadow-sm transition-all hover:shadow-md active:cursor-grabbing select-none"
                                    :class="[
                                        { 'opacity-50 scale-95': draggedId === ticket.id },
                                        { 'ring-2 ring-red-500 ring-offset-1': col.key === 'completed' && deleteMode && selectedForDelete.has(ticket.id) },
                                    ]"
                                    @dragstart="!(col.key === 'completed' && deleteMode) && onDragStart(ticket)"
                                    @dragend="onDragEnd"
                                    @click="col.key === 'completed' && deleteMode ? toggleSelectForDelete(ticket.id) : openDetail(ticket)"
                                >
                                    <!-- 削除モード選択ハイライト -->
                                    <div
                                        v-if="col.key === 'completed' && deleteMode && selectedForDelete.has(ticket.id)"
                                        class="pointer-events-none absolute inset-0 z-10 rounded-lg bg-red-500/20"
                                />
                                <!-- 削除モード チェックボックス -->
                                <div
                                    v-if="col.key === 'completed' && deleteMode"
                                    class="absolute left-2 top-2 z-20"
                                    @click.stop="toggleSelectForDelete(ticket.id)"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedForDelete.has(ticket.id)"
                                        class="h-5 w-5 cursor-pointer rounded border-2 border-red-400 accent-red-600"
                                        @change.stop="toggleSelectForDelete(ticket.id)"
                                    />
                                </div>
                                <!-- A4縦上半分サムネイル -->
                                <div
                                    class="overflow-hidden rounded-t-sm bg-indigo-50"
                                    style="aspect-ratio: 210 / 26;"
                                    @click.stop="col.key === 'completed' && deleteMode ? toggleSelectForDelete(ticket.id) : openDetail(ticket)"
                                >
                                    <img
                                        v-if="ticket.image_url"
                                        :src="ticket.image_url"
                                        :alt="ticket.title"
                                        class="h-full w-full object-cover cursor-pointer"
                                        style="object-position: 0% 8%;"
                                        draggable="false"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-indigo-300"
                                    >
                                        <div class="text-center">
                                            <div class="text-2xl mb-1">📄</div>
                                            <div class="text-xs">画像なし</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- キャプション: 伝票番号 + 案件名 + 日付 -->
                                <div class="rounded-b-sm border-t border-indigo-400 bg-indigo-100 px-2 py-0.5">
                                    <p class="truncate text-xs text-indigo-900 leading-tight">
                                        <span v-if="ticket.jobcode" class="font-medium text-indigo-600">#{{ ticket.jobcode }}　</span>{{ ticket.title }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-indigo-600 leading-tight">
                                        入稿 {{ formatShortDate(ticket.submission_date) }}：下版 {{ formatShortDate(ticket.sb_delivery_date) }}
                                    </p>
                                </div>
                            </div>

                            <!-- 空エリア ドロップヒント -->
                            <div
                                v-if="sortedFilteredTickets(col.key).length === 0 && draggedId !== null && canDropTo(col.key)"
                                class="col-span-2 flex h-24 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-sm text-gray-400"
                            >
                                ここにドロップ
                            </div>
                        </div>
                    </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ── 詳細モーダル (Tickets/Index.vue と同じ内容) ─────── -->
        <Teleport to="body">
            <div
                v-if="detail"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 pt-10 pb-10"
                @click.self="closeDetail"
            >
                <div class="mx-auto w-full max-w-3xl space-y-4 px-4">

                    <!-- メインカード -->
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
                                class="inline-flex items-center gap-1.5 rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
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
                                v-if="detail.status !== 'submitting'"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded bg-purple-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-600"
                                :disabled="updatingStatus"
                                @click="changeStatus(detail, 'submitting')"
                            >入稿予定にする</button>

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
                            >準備に戻す</button>

                            <div class="ml-auto flex items-center gap-2">
                                <a
                                    :href="route('prepress.tickets.edit', { ticket: detail.id })"
                                    class="inline-flex items-center gap-1.5 rounded bg-indigo-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-600"
                                >編集</a>

                                <button
                                    v-if="isAdmin"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded bg-red-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-600"
                                    :disabled="deleting"
                                    @click="deleteTicket(detail)"
                                >削除</button>
                            </div>
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
                                <dt class="w-32 shrink-0 font-medium text-gray-500">伝票番号</dt>
                                <dd class="text-gray-800">{{ detail.jobcode || '—' }}</dd>
                            </div>
                            <div class="flex py-2">
                                <dt class="w-32 shrink-0 font-medium text-gray-500">作成日</dt>
                                <dd class="text-gray-800">{{ formatDate(detail.created_at) }}</dd>
                            </div>
                            <div class="flex py-2">
                                <dt class="w-32 shrink-0 font-medium text-gray-500">入稿日</dt>
                                <dd class="text-gray-800">{{ formatDate(detail.submission_date) || '—' }}</dd>
                            </div>
                            <div class="flex py-2">
                                <dt class="w-32 shrink-0 font-medium text-gray-500">下版日</dt>
                                <dd class="text-gray-800">{{ formatDate(detail.sb_delivery_date) || '—' }}</dd>
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
                            <label
                                v-if="!pendingFile"
                                class="cursor-pointer rounded border border-green-700 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-50"
                                :class="{ 'opacity-50 pointer-events-none': uploadingImage }"
                            >
                                {{ detail.image_path ? '画像を変更' : '画像を登録' }}
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    class="hidden"
                                    :disabled="uploadingImage"
                                    @change="e => selectPendingFile(e.target.files?.[0])"
                                />
                            </label>
                        </div>

                        <!-- ① 新しいファイルが選択されている（保存待ち）状態 -->
                        <div v-if="pendingFile" class="px-5 py-4 space-y-3">
                            <p class="text-xs font-semibold text-blue-700">新しい画像を確認して「保存」してください</p>
                            <div
                                v-if="pendingPreview === '__pdf__'"
                                class="flex h-32 w-40 items-center justify-center rounded-lg border border-gray-200 bg-gray-50"
                            >
                                <div class="text-center">
                                    <div class="text-3xl">📄</div>
                                    <p class="mt-1 text-xs text-gray-500">PDF</p>
                                    <p class="text-xs text-gray-400">保存時に変換</p>
                                </div>
                            </div>
                            <img
                                v-else-if="pendingPreview"
                                :src="pendingPreview"
                                alt="プレビュー"
                                class="max-h-60 rounded border border-gray-200 object-contain"
                            />
                            <p class="text-xs text-gray-500">{{ pendingFile.name }}</p>
                            <p v-if="uploadError" class="text-xs text-red-600">{{ uploadError }}</p>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded bg-green-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50"
                                    :disabled="uploadingImage"
                                    @click="savePendingImage"
                                >{{ uploadingImage ? '変換・保存中...' : '保存' }}</button>
                                <button
                                    type="button"
                                    class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                                    :disabled="uploadingImage"
                                    @click="cancelPendingFile"
                                >キャンセル</button>
                            </div>
                        </div>

                        <!-- ② 既存画像の表示 -->
                        <div v-else-if="detail.image_path" class="px-5 py-4">
                            <img
                                :src="detail.image_url ?? ('/storage/' + detail.image_path)"
                                :alt="detail.original_filename ?? 'image'"
                                class="max-w-full rounded border border-gray-200"
                            />
                            <p v-if="detail.original_filename" class="mt-1 text-xs text-gray-400">{{ detail.original_filename }}</p>
                        </div>

                        <!-- ③ 画像未登録 -->
                        <div v-else class="px-5 py-6 text-center text-sm text-gray-400">
                            <p>画像が登録されていません。</p>
                            <p class="mt-1 text-xs">上の「画像を登録」ボタンからファイルを選択してください。</p>
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
                @click.self="handleModalBackdropClick"
            >
                <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-800">伝票登録</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeCreateModal">✕</button>
                    </div>

                    <!-- モード選択（縦並び） -->
                    <div class="px-6 pt-5">
                        <div class="flex flex-col gap-3">
                            <!-- ファイル読み込み（OCR） -->
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg border-2 px-4 py-3 text-left text-sm font-medium transition-colors"
                                :class="createMode === 'ocr'
                                    ? 'border-blue-600 bg-blue-50 text-blue-700'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                                @click="createMode = 'ocr'; resetModalState()"
                            >
                                <span class="text-lg">📄</span>
                                <span>ファイル読み込み（OCR自動入力）</span>
                            </button>
                            <!-- 新規作成 -->
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg border-2 px-4 py-3 text-left text-sm font-medium transition-colors"
                                :class="createMode === 'new'
                                    ? 'border-green-600 bg-green-50 text-green-700'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                                @click="createMode = 'new'; resetModalState()"
                            >
                                <span class="text-lg">✏️</span>
                                <span>新規作成</span>
                            </button>
                            <!-- 案件から読み込む -->
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg border-2 px-4 py-3 text-left text-sm font-medium transition-colors"
                                :class="createMode === 'from_job'
                                    ? 'border-green-600 bg-green-50 text-green-700'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                                @click="createMode = 'from_job'; resetModalState()"
                            >
                                <span class="text-lg">📋</span>
                                <span>案件から読み込む</span>
                            </button>
                        </div>
                    </div>

                    <!-- ▼ ファイル読み込み（OCR）モード -->
                    <div v-if="createMode === 'ocr'" class="px-6 pt-5">
                        <div
                            class="rounded-lg border-2 border-dashed transition-colors"
                            :class="isDragOver
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-300 bg-gray-50 hover:border-gray-400'"
                            @dragenter.prevent="isDragOver = true"
                            @dragleave.prevent="isDragOver = false"
                            @dragover.prevent
                            @drop.prevent="onOcrDrop"
                        >
                            <div class="py-10 text-center">
                                <div v-if="isOcrLoading" class="text-blue-600">
                                    <svg class="mx-auto mb-3 h-9 w-9 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                    <p class="text-sm font-medium">OCR解析中...</p>
                                    <p class="mt-1 text-xs text-gray-400">しばらくお待ちください</p>
                                </div>
                                <div v-else>
                                    <p class="text-3xl mb-3">📥</p>
                                    <p class="text-sm font-medium text-gray-600">PDFや画像をここにドロップ</p>
                                    <p class="mt-1 text-xs text-gray-400">または</p>
                                    <label class="mt-3 inline-block cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                        ファイルを選択
                                        <input
                                            type="file"
                                            class="hidden"
                                            accept="application/pdf,image/jpeg,image/png,image/gif,image/webp"
                                            @change="onOcrFileInput"
                                        />
                                    </label>
                                    <p class="mt-3 text-xs text-gray-400">対応形式: PDF, JPG, PNG, GIF, WebP</p>
                                </div>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">※ OCR完了後に確認画面が表示されます</p>
                    </div>

                    <!-- ▼ 案件から読み込む -->
                    <div v-if="createMode === 'from_job'" class="space-y-4 px-6 pt-5">
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
                                                <span class="font-mono text-xs text-gray-400">{{ client.client_code || '―' }}</span>
                                            </div>
                                            <div v-if="client.is_dormant" class="text-xs text-red-500">※ 休眠中</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

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

                    <!-- ▼ 新規作成の説明 -->
                    <div v-if="createMode === 'new'" class="px-6 pt-5">
                        <p class="text-sm text-gray-500">空白の伝票登録フォームに移動します。</p>
                    </div>

                    <!-- フッター（OCRモードはキャンセルのみ） -->
                    <div v-if="createMode !== 'ocr'" class="flex items-center justify-end gap-3 border-t px-6 py-4 mt-5">
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
                    <div v-else class="flex items-center justify-end border-t px-6 py-4 mt-5">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                            @click="closeCreateModal"
                        >キャンセル</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- OCR確認モーダル -->
        <OcrModal
            :show="showOcrModal"
            :ocr-result="ocrResult"
            @apply="onOcrApplyFromBoard"
            @close="showOcrModal = false"
        />

    </AppLayout>
</template>
