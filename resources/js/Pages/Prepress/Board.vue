<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import OcrModal from '@/Components/Prepress/OcrModal.vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    tickets:          { type: Array,  default: () => [] },
    statuses:         { type: Object, default: () => ({}) },
    salesReps:        { type: Array,  default: () => [] },
    prepressUsers:    { type: Array,  default: () => [] },
    colorAssignments: { type: Array,  default: () => [] },
});

const COLUMNS = [
    { key: 'pending',     label: '準備',    color: 'border-yellow-400 bg-yellow-50',  header: 'bg-yellow-100 text-yellow-800',  barText: 'text-yellow-800'  },
    { key: 'submitting',  label: '入稿予定', color: 'border-purple-400 bg-purple-50',  header: 'bg-purple-100 text-purple-800',  barText: 'text-purple-800'  },
    { key: 'in_progress', label: '作業中',  color: 'border-blue-400 bg-blue-50',      header: 'bg-blue-100 text-blue-800',      barText: 'text-blue-800'    },
    { key: 'outputting',  label: '出稿中',  color: 'border-orange-400 bg-orange-50',  header: 'bg-orange-100 text-orange-800',  barText: 'text-orange-800'  },
    { key: 'completed',   label: '完了',    color: 'border-green-500 bg-green-50',    header: 'bg-green-100 text-green-800',    barText: 'text-green-800'   },
];

// 遷移ルール（どの列からでも他の列へ移動可能）
const ALL_KEYS = ['pending', 'submitting', 'in_progress', 'outputting', 'completed'];
const VALID_TRANSITIONS = Object.fromEntries(
    ALL_KEYS.map(k => [k, ALL_KEYS.filter(t => t !== k)])
);

// カラム開閉状態: デフォルトは入稿予定・作業中・出稿中の3列を開く
const openColumns = ref(new Set(['submitting', 'in_progress', 'outputting']));

function isOpen(key) {
    return openColumns.value.has(key);
}

function toggleColumn(key) {
    const s = new Set(openColumns.value);
    if (s.has(key)) {
        s.delete(key);
    } else {
        if (s.size >= 4) {
            // 一番右にある開いているカラムを自動で閉じる
            const rightmost = COLUMNS.map(c => c.key).filter(k => s.has(k)).at(-1);
            if (rightmost) s.delete(rightmost);
        }
        s.add(key);
    }
    openColumns.value = s;
}

// グローバル検索
const boardSearch = ref('');

// 準備列 表示モード（list / card）
const pendingView = ref(localStorage.getItem('prepress_board_pending_view') ?? 'card');
function setPendingView(mode) {
    pendingView.value = mode;
    localStorage.setItem('prepress_board_pending_view', mode);
}

// 準備列リスト表示 ソート
const pendingListSort = ref({ field: 'submission_date', dir: 'asc' });
function togglePendingSort(field) {
    if (pendingListSort.value.field === field) {
        pendingListSort.value.dir = pendingListSort.value.dir === 'asc' ? 'desc' : 'asc';
    } else {
        pendingListSort.value = { field, dir: 'asc' };
    }
}
const pendingListTickets = computed(() => {
    let list = [...(ticketsByStatus.value['pending'] ?? [])];
    const q = (columnControls.value['pending']?.search ?? '').trim().toLowerCase();
    if (q) {
        list = list.filter(t =>
            [t.jobcode, t.title, t.client_name].some(v => v && String(v).toLowerCase().includes(q))
        );
    }
    const { field, dir } = pendingListSort.value;
    return list.sort((a, b) => {
        const va = a[field] ? String(a[field]).split('T')[0].replace(/\//g, '-') : '9999-99-99';
        const vb = b[field] ? String(b[field]).split('T')[0].replace(/\//g, '-') : '9999-99-99';
        if (va === vb) return 0;
        return (va < vb ? -1 : 1) * (dir === 'asc' ? 1 : -1);
    });
});

// Local optimistic state
const localTickets = ref(props.tickets.map(t => ({ ...t })));

const ticketsByStatus = computed(() => {
    const map = {};
    COLUMNS.forEach(c => { map[c.key] = []; });
    const q = boardSearch.value.trim().toLowerCase();
    const list = q
        ? localTickets.value.filter(t =>
            [t.jobcode, t.client_name, String(t.client_id ?? ''), t.sales_rep, t.title]
                .some(v => v && String(v).toLowerCase().includes(q))
          )
        : localTickets.value;
    list.forEach(t => { if (map[t.status]) map[t.status].push(t); });
    return map;
});

// ── カラム別 ソート・絞り込み ──────────────────────────────────
const columnControls = ref(
    Object.fromEntries(COLUMNS.map(c => {
        const savedField = localStorage.getItem(`prepress_board_date_field_${c.key}`) ?? 'submission_date';
        return [c.key, { order: 'asc', dateFilter: '', dateRaw: '', dateField: savedField, search: '' }];
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

    if (ctrl.search?.trim()) {
        const q = ctrl.search.trim().toLowerCase();
        list = list.filter(t =>
            [t.jobcode, t.title, t.client_name].some(v => v && String(v).toLowerCase().includes(q))
        );
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

// ── カードカラー ───────────────────────────────────────────
const CARD_COLORS = {
    indigo: { swatch: 'bg-indigo-400',  border: 'border-indigo-400', bg: 'bg-indigo-100',  textMain: 'text-indigo-900', textSub: 'text-indigo-600' },
    blue:   { swatch: 'bg-blue-400',    border: 'border-blue-400',   bg: 'bg-blue-100',    textMain: 'text-blue-900',   textSub: 'text-blue-600'   },
    cyan:   { swatch: 'bg-cyan-400',    border: 'border-cyan-400',   bg: 'bg-cyan-100',    textMain: 'text-cyan-900',   textSub: 'text-cyan-700'   },
    teal:   { swatch: 'bg-teal-500',    border: 'border-teal-500',   bg: 'bg-teal-100',    textMain: 'text-teal-900',   textSub: 'text-teal-700'   },
    green:  { swatch: 'bg-green-500',   border: 'border-green-500',  bg: 'bg-green-100',   textMain: 'text-green-900',  textSub: 'text-green-700'  },
    yellow: { swatch: 'bg-yellow-400',  border: 'border-yellow-400', bg: 'bg-yellow-100',  textMain: 'text-yellow-900', textSub: 'text-yellow-700' },
    orange: { swatch: 'bg-orange-400',  border: 'border-orange-400', bg: 'bg-orange-100',  textMain: 'text-orange-900', textSub: 'text-orange-700' },
    red:    { swatch: 'bg-red-400',     border: 'border-red-400',    bg: 'bg-red-100',     textMain: 'text-red-900',    textSub: 'text-red-700'    },
    pink:   { swatch: 'bg-pink-400',    border: 'border-pink-400',   bg: 'bg-pink-100',    textMain: 'text-pink-900',   textSub: 'text-pink-700'   },
    purple: { swatch: 'bg-purple-400',  border: 'border-purple-400', bg: 'bg-purple-100',  textMain: 'text-purple-900', textSub: 'text-purple-600' },
    gray:   { swatch: 'bg-gray-400',    border: 'border-gray-400',   bg: 'bg-gray-200',    textMain: 'text-gray-800',   textSub: 'text-gray-600'   },
};

function cardColor(ticket) {
    return CARD_COLORS[ticket.card_color] ?? CARD_COLORS.indigo;
}

const CHECK_ITEMS = [
    { key: 'check_finish_size',      label: '仕上がりサイズ' },
    { key: 'check_trim_marks',       label: 'トンボ' },
    { key: 'check_imposition',       label: '面付' },
    { key: 'check_color_count',      label: '色数' },
    { key: 'check_screen_ruling',    label: '線数' },
    { key: 'check_n_mark_trap',      label: 'Nマークのトラップ処理' },
    { key: 'check_color_correction', label: '色調補正' },
];

const STAGES = ['初校', '再校', '三校', '下版'];

function emptyStageCheck() {
    return {
        check_finish_size: false,
        check_trim_marks: false,
        check_imposition: false,
        check_color_count: false,
        check_screen_ruling: false,
        check_n_mark_trap: false,
        check_color_correction: false,
        user_id: null,
    };
}

const APP_VERSIONS = ['CC', 'CC 2014', 'CC 2015', 'CC 2017', 'CC 2018', 'CC 2019', '2020', '2021', '2022', '2023', '2024', '2025', '2026'];

async function setTicketColor(ticket, colorKey) {
    const prev = ticket.card_color;
    const idx  = localTickets.value.findIndex(t => t.id === ticket.id);
    if (idx >= 0) localTickets.value[idx].card_color = colorKey;
    if (detail.value?.id === ticket.id) detail.value = { ...detail.value, card_color: colorKey };

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('prepress.board.updateColor', { ticket: ticket.id }),
            { card_color: colorKey },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
    } catch {
        if (idx >= 0) localTickets.value[idx].card_color = prev;
        if (detail.value?.id === ticket.id) detail.value = { ...detail.value, card_color: prev };
    }
}

// ── 色担当管理 ───────────────────────────────────────────
const localColorAssignments = ref(
    [...props.colorAssignments].sort((a, b) => a.sort_order - b.sort_order).map(a => ({ ...a }))
);
watch(() => props.colorAssignments, (val) => {
    localColorAssignments.value = [...val].sort((a, b) => a.sort_order - b.sort_order).map(a => ({ ...a }));
});
const showColorAssignPanel  = ref(false);
const colorPanelKey     = ref(0);
const dragSrcColorIdx   = ref(null);
const dragOverColorIdx  = ref(null);

const sortedColorKeys = computed(() =>
    localColorAssignments.value.map(a => a.color_key).filter(k => !!CARD_COLORS[k])
);

function colorUserName(key) {
    const a = localColorAssignments.value.find(a => a.color_key === key);
    if (!a?.user_id) return null;
    const u = props.prepressUsers.find(u => u.id === a.user_id);
    if (!u) return null;
    return u.name.split(/[\s　]+/)[0]; // 苗字のみ
}

async function setColorUser(colorKey, userId) {
    const a = localColorAssignments.value.find(a => a.color_key === colorKey);
    if (!a) return;

    const newUserId = userId ? parseInt(userId) : null;

    // 重複チェック（同じ人が別の色にすでに割り当てられていないか）
    if (newUserId) {
        const dup = localColorAssignments.value.find(
            x => x.color_key !== colorKey && x.user_id === newUserId
        );
        if (dup) {
            const u = props.prepressUsers.find(u => u.id === newUserId);
            alert(`「${u?.name ?? '選択した担当者'}」はすでに別の色に設定されています。`);
            // a.user_id は変えていないが select の DOM が先行しているため強制再レンダリング
            colorPanelKey.value++;
            return;
        }
    }

    const prev = a.user_id;
    a.user_id = newUserId;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('prepress.board.colorAssignment.update', { colorKey }),
            { user_id: a.user_id },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
    } catch {
        a.user_id = prev;
    }
}

function onColorDragStart(idx) { dragSrcColorIdx.value = idx; }
function onColorDragOver(idx)  { dragOverColorIdx.value = idx; }
function onColorDragEnd()      { dragSrcColorIdx.value = null; dragOverColorIdx.value = null; }

async function onColorDrop(targetIdx) {
    const srcIdx = dragSrcColorIdx.value;
    dragSrcColorIdx.value  = null;
    dragOverColorIdx.value = null;
    if (srcIdx === null || srcIdx === targetIdx) return;

    const arr = [...localColorAssignments.value];
    const [moved] = arr.splice(srcIdx, 1);
    arr.splice(targetIdx, 0, moved);
    arr.forEach((a, i) => { a.sort_order = i; });
    localColorAssignments.value = arr;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const orders = arr.map((a, i) => ({ color_key: a.color_key, sort_order: i }));
    try {
        await axios.post(
            route('prepress.board.colorAssignment.reorder'),
            { orders },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
    } catch { /* ignore */ }
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
const canDelete = computed(() => ['admin', 'superadmin', 'coordinator', 'leader', 'clerk'].includes(authUser.value?.user_role));

const detail          = ref(null);
const localMeta       = ref({});
const localStageChecks = ref({});
const updatingStatus  = ref(false);
const deleting        = ref(false);
const uploadingImage  = ref(false);
const uploadError     = ref('');
const pendingFile     = ref(null);
const pendingPreview  = ref(null);

function openDetail(ticket) {
    detail.value = ticket;
    localMeta.value = {
        indesign_version:    ticket.indesign_version ?? '2021',
        illustrator_version: ticket.illustrator_version ?? '2021',
        check_memo:          ticket.check_memo ?? '',
    };
    const byStage = {};
    for (const sc of (ticket.stage_checks ?? [])) {
        byStage[sc.stage] = sc;
    }
    const stageChecks = {};
    for (const stage of STAGES) {
        const sc = byStage[stage];
        stageChecks[stage] = sc
            ? {
                check_finish_size:      !!sc.check_finish_size,
                check_trim_marks:       !!sc.check_trim_marks,
                check_imposition:       !!sc.check_imposition,
                check_color_count:      !!sc.check_color_count,
                check_screen_ruling:    !!sc.check_screen_ruling,
                check_n_mark_trap:      !!sc.check_n_mark_trap,
                check_color_correction: !!sc.check_color_correction,
                user_id:                sc.user_id ?? null,
            }
            : emptyStageCheck();
    }
    localStageChecks.value = stageChecks;
    cancelPendingFile();
}
function closeDetail() {
    clearTimeout(checkMemoTimer);
    detail.value = null;
    showColorAssignPanel.value = false;
    cancelPendingFile();
}

let checkMemoTimer = null;

async function saveMeta(field) {
    if (!detail.value) return;
    const ticketId = detail.value.id;
    const val      = localMeta.value[field];
    const idx      = localTickets.value.findIndex(t => t.id === ticketId);
    const prev     = idx >= 0 ? localTickets.value[idx][field] : undefined;
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('prepress.board.updateMeta', { ticket: ticketId }),
            { [field]: val },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
        if (idx >= 0) localTickets.value[idx][field] = val;
    } catch (e) {
        if (e?.response?.status === 419) {
            window.location.reload();
            return;
        }
        if (prev !== undefined && detail.value?.id === ticketId) {
            localMeta.value[field] = prev;
        }
    }
}

function saveCheckMemoDebounced() {
    clearTimeout(checkMemoTimer);
    checkMemoTimer = setTimeout(() => saveMeta('check_memo'), 800);
}

// localStageChecks の内容を detail(=localTickets内の該当チケット) の stage_checks にも反映する。
// これをしないと、モーダルを閉じて開き直した際に openDetail() が古い ticket.stage_checks から
// 再構築してしまい、保存済みの値が一瞬で元に戻ったように見える。
function syncStageCheckToTicket(stage) {
    if (!detail.value) return;
    if (!Array.isArray(detail.value.stage_checks)) detail.value.stage_checks = [];
    const data     = localStageChecks.value[stage];
    const existing = detail.value.stage_checks.find(sc => sc.stage === stage);
    if (existing) {
        Object.assign(existing, data);
    } else {
        detail.value.stage_checks.push({ stage, ...data });
    }
}

async function saveStageCheckField(stage, field) {
    if (!detail.value) return;
    const val  = localStageChecks.value[stage][field];
    const prev = !val;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('prepress.board.updateStageCheck', { ticket: detail.value.id, stage }),
            { [field]: val },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
        syncStageCheckToTicket(stage);
    } catch (e) {
        if (e?.response?.status === 419) {
            window.location.reload();
            return;
        }
        localStageChecks.value[stage][field] = prev;
    }
}

async function saveStageUser(stage, userId) {
    if (!detail.value) return;
    const newUserId = userId ? parseInt(userId) : null;
    const prev      = localStageChecks.value[stage].user_id;
    localStageChecks.value[stage].user_id = newUserId;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('prepress.board.updateStageCheck', { ticket: detail.value.id, stage }),
            { user_id: newUserId },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
        syncStageCheckToTicket(stage);
    } catch (e) {
        if (e?.response?.status === 419) {
            window.location.reload();
            return;
        }
        localStageChecks.value[stage].user_id = prev;
    }
}

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
        case 'outputting':  return 'bg-orange-100 text-orange-800';
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

async function deleteTicket(ticket) {
    if (!confirm(`「${ticket.title}」を削除しますか？`)) return;
    deleting.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.delete(route('prepress.tickets.destroy', { ticket: ticket.id }), {
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        localTickets.value = localTickets.value.filter(t => t.id !== ticket.id);
        closeDetail();
    } catch {
        alert('削除に失敗しました。');
    } finally {
        deleting.value = false;
    }
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

// ── 伝票登録ドロップダウン + モーダル ────────────────────
const showCreateModal  = ref(false);
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

function openCreateModal(mode = 'ocr') {
    showCreateModal.value  = true;
    createMode.value       = mode;
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

// ── CSV一括登録モーダル ────────────────────────────────
const showCsvModal     = ref(false);
const csvAnalyzing     = ref(false);
const csvImporting     = ref(false);
const csvAnalysisRows  = ref([]); // サーバー解析結果
const csvFile          = ref(null);

function openCsvModal() {
    showCsvModal.value     = true;
    csvFile.value          = null;
    csvAnalyzing.value     = false;
    csvImporting.value     = false;
    csvAnalysisRows.value  = [];
}

function closeCsvModal() {
    showCsvModal.value    = false;
    csvFile.value         = null;
    csvAnalyzing.value    = false;
    csvImporting.value    = false;
    csvAnalysisRows.value = [];
}

async function onCsvFileSelect(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    csvFile.value = file;
    csvAnalyzing.value = true;
    csvAnalysisRows.value = [];
    const fd   = new FormData();
    fd.append('csv', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(route('prepress.tickets.analyzeCsv'), fd, {
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'multipart/form-data' },
        });
        csvAnalysisRows.value = res.data.rows ?? [];
    } catch {
        alert('CSV解析に失敗しました。ファイルを確認してください。');
    } finally {
        csvAnalyzing.value = false;
        e.target.value = '';
    }
}

function csvSelectCandidate(rowIndex, client) {
    const row = csvAnalysisRows.value[rowIndex];
    if (!row) return;
    row.resolved_client_id   = client.id;
    row.resolved_client_name = client.name;
    row.status               = 'matched';
}

function csvSelectFromSearch(rowIndex, client) {
    csvSelectCandidate(rowIndex, client);
    csvAnalysisRows.value[rowIndex].showSearch = false;
}

// 営業担当選択
function csvSelectSalesRepCandidate(rowIndex, rep) {
    const row = csvAnalysisRows.value[rowIndex];
    if (!row) return;
    row.resolved_sales_rep_id   = rep.id;
    row.resolved_sales_rep_name = rep.name;
    row.sales_rep_status        = 'matched';
}

function csvSelectSalesRepFromSearch(rowIndex, rep) {
    csvSelectSalesRepCandidate(rowIndex, rep);
    csvAnalysisRows.value[rowIndex].showSalesRepSearch = false;
}

// クライアントの未解決行のみカウント（営業担当は任意なので未解決でもOK）
const csvUnresolvedCount = computed(() =>
    csvAnalysisRows.value.filter(r => r.status !== 'matched').length
);
// 受注番号重複によりスキップされる行数
const csvDupSkipCount = computed(() =>
    csvAnalysisRows.value.filter(r => r.jobcode_dup && r.jobcode_dup !== 'none').length
);
// 実際に登録される件数（重複スキップ分を除く）
const csvImportableCount = computed(() =>
    csvAnalysisRows.value.length - csvDupSkipCount.value
);

const csvRowClientSearch = ref({});
const csvRowSalesRepSearch = ref({});
let csvSearchTimers = {};

function onCsvRowSearchInput(rowIndex) {
    clearTimeout(csvSearchTimers[`c_${rowIndex}`]);
    const q = csvRowClientSearch.value[rowIndex] ?? '';
    if (!q.trim()) { csvAnalysisRows.value[rowIndex].searchResults = []; return; }
    csvSearchTimers[`c_${rowIndex}`] = setTimeout(async () => {
        const res = await axios.get(route('prepress.api.clients'), { params: { q } });
        if (csvAnalysisRows.value[rowIndex]) csvAnalysisRows.value[rowIndex].searchResults = res.data;
    }, 250);
}

function onCsvRowSalesRepSearchInput(rowIndex) {
    clearTimeout(csvSearchTimers[`s_${rowIndex}`]);
    const q = csvRowSalesRepSearch.value[rowIndex] ?? '';
    if (!q.trim()) { csvAnalysisRows.value[rowIndex].salesRepSearchResults = []; return; }
    csvSearchTimers[`s_${rowIndex}`] = setTimeout(async () => {
        const res = await axios.get(route('prepress.api.salesReps'));
        if (csvAnalysisRows.value[rowIndex]) {
            csvAnalysisRows.value[rowIndex].salesRepSearchResults = res.data.filter(r =>
                r.name.includes(q)
            );
        }
    }, 250);
}

// ── インライン新規登録モーダル（クライアント） ────────────────
const showInlineClientModal = ref(false);
const inlineCsvRowIndex     = ref(null);
const inlineClientForm      = ref({ name: '', client_code: '' });
const inlineClientSaving    = ref(false);
const inlineClientNote      = ref(''); // 重複メッセージ用

function openInlineClientModal(rowIndex) {
    inlineCsvRowIndex.value = rowIndex;
    const row = csvAnalysisRows.value[rowIndex];
    inlineClientForm.value = { name: row?.raw_client_name ?? '', client_code: '' };
    inlineClientNote.value = '';
    showInlineClientModal.value = true;
}

async function saveInlineClient() {
    if (!inlineClientForm.value.name.trim()) return;
    inlineClientSaving.value = true;
    inlineClientNote.value   = '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(route('prepress.api.clientCreate'), {
            name:        inlineClientForm.value.name,
            client_code: inlineClientForm.value.client_code || null,
        }, { headers: { 'X-CSRF-TOKEN': csrf } });
        const newClient = res.data?.client;
        if (newClient?.id) {
            // トリガー行は無条件で更新
            csvSelectCandidate(inlineCsvRowIndex.value, newClient);
            // トリガー行のCSV元名称で他の未解決行も更新（完全一致）
            const triggeredRawName = csvAnalysisRows.value[inlineCsvRowIndex.value]?.raw_client_name;
            csvAnalysisRows.value.forEach((row, idx) => {
                if (idx !== inlineCsvRowIndex.value && row.raw_client_name === triggeredRawName && row.status !== 'matched') {
                    csvSelectCandidate(idx, newClient);
                }
            });
            if (res.data?.was_existing) {
                inlineClientNote.value = `「${newClient.name}」はすでにDBに登録されているクライアントです。新規登録は行いませんでした。既存のクライアントをCSVに適用しました。`;
                // メッセージを読んでもらうためモーダルを閉じない
            } else {
                showInlineClientModal.value = false;
            }
        }
    } catch {
        alert('クライアント登録に失敗しました。');
    } finally {
        inlineClientSaving.value = false;
    }
}

// ── インライン新規登録モーダル（営業担当） ────────────────────
const showInlineSalesRepModal = ref(false);
const inlineSalesRepRowIndex  = ref(null);
const inlineSalesRepForm      = ref({ name: '', company: '' });
const inlineSalesRepSaving    = ref(false);

function openInlineSalesRepModal(rowIndex) {
    inlineSalesRepRowIndex.value = rowIndex;
    const row = csvAnalysisRows.value[rowIndex];
    inlineSalesRepForm.value = { name: row?.sales_rep ?? '', company: '' };
    showInlineSalesRepModal.value = true;
}

async function saveInlineSalesRep() {
    if (!inlineSalesRepForm.value.name.trim()) return;
    inlineSalesRepSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(route('prepress.api.salesRepCreate'), {
            name:    inlineSalesRepForm.value.name,
            company: inlineSalesRepForm.value.company || null,
        }, { headers: { 'X-CSRF-TOKEN': csrf } });
        const newRep = res.data?.rep;
        if (newRep?.id) {
            csvSelectSalesRepCandidate(inlineSalesRepRowIndex.value, newRep);
            showInlineSalesRepModal.value = false;
        }
    } catch {
        alert('営業担当登録に失敗しました。');
    } finally {
        inlineSalesRepSaving.value = false;
    }
}

async function executeCsvImport() {
    if (csvUnresolvedCount.value > 0 || csvImporting.value) return;
    csvImporting.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const rows = csvAnalysisRows.value.map(r => ({
        jobcode:      r.jobcode,
        title:        r.title,
        sales_rep:    r.sales_rep,
        sales_rep_id: r.resolved_sales_rep_id ?? null,
        client_id:    r.resolved_client_id    ?? null,
        client_name:  r.resolved_client_name  ?? r.raw_client_name ?? null,
    }));
    try {
        const res = await axios.post(route('prepress.tickets.importCsv'), { rows }, {
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        closeCsvModal();
        const imported = res.data?.imported ?? 0;
        const skipped  = res.data?.skipped_dup ?? 0;
        let msg = `${imported}件の伝票を登録しました。`;
        if (skipped > 0) msg += `（受注番号重複により${skipped}件スキップ）`;
        router.reload({ onSuccess: () => alert(msg) });
    } catch {
        alert('インポートに失敗しました。');
    } finally {
        csvImporting.value = false;
    }
}
</script>

<template>
    <AppLayout title="伝票ボード">
        <template #header>
            <div class="flex items-center gap-3 justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">伝票ボード</h2>
                <!-- グローバル検索ボックス -->
                <div class="flex items-center gap-1">
                    <input
                        v-model="boardSearch"
                        type="text"
                        placeholder="ID、担当営業など"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm w-44 focus:border-green-600 focus:outline-none"
                    />
                    <button
                        v-if="boardSearch"
                        type="button"
                        class="rounded px-1.5 py-1 text-xs text-gray-400 hover:text-gray-600"
                        @click="boardSearch = ''"
                    >✕</button>
                </div>
                <!-- 伝票登録ボタン -->
                <button
                    type="button"
                    class="rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800"
                    @click="openCreateModal()"
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

                        <!-- 準備列専用: リスト/カード切替タブ -->
                        <div v-if="col.key === 'pending'" class="shrink-0 flex border-b bg-white/70">
                            <button
                                type="button"
                                class="flex-1 py-1.5 text-xs font-semibold transition-colors"
                                :class="pendingView === 'card'
                                    ? 'bg-yellow-200 text-yellow-900'
                                    : 'text-gray-500 hover:bg-gray-100'"
                                @click="setPendingView('card')"
                            >カード</button>
                            <button
                                type="button"
                                class="flex-1 py-1.5 text-xs font-semibold transition-colors"
                                :class="pendingView === 'list'
                                    ? 'bg-yellow-200 text-yellow-900'
                                    : 'text-gray-500 hover:bg-gray-100'"
                                @click="setPendingView('list')"
                            >リスト</button>
                        </div>

                        <!-- 列内検索 (常に表示) -->
                        <div class="shrink-0 flex items-center gap-1 border-b bg-white/70 px-3 py-1.5">
                            <input
                                v-model="columnControls[col.key].search"
                                type="text"
                                placeholder="受注番号・品名で絞込"
                                class="flex-1 min-w-0 rounded border border-gray-300 px-2 py-0.5 text-xs focus:border-teal-500 focus:outline-none"
                            />
                            <button
                                v-if="columnControls[col.key].search"
                                type="button"
                                class="shrink-0 rounded px-1 py-0.5 text-xs text-gray-400 hover:text-gray-600"
                                @click="columnControls[col.key].search = ''"
                            >✕</button>
                        </div>

                        <!-- ソート・絞り込みコントロール（リストモード時は非表示） -->
                        <div
                            v-if="!(col.key === 'pending' && pendingView === 'list')"
                            class="shrink-0 flex flex-wrap items-center gap-2 border-b bg-white/70 px-3 py-1.5"
                        >
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
                        </div><!-- /ソート・絞り込みコントロール -->

                        <!-- 準備列 リスト表示 -->
                        <div
                            v-if="col.key === 'pending' && pendingView === 'list'"
                            class="flex-1 min-h-0 overflow-y-auto"
                        >
                            <table class="w-full text-xs border-collapse" style="min-width: 380px;">
                                <thead class="sticky top-0 bg-yellow-100 z-10">
                                    <tr>
                                        <th
                                            v-for="h in [
                                                { field: 'jobcode',         label: '伝票番号' },
                                                { field: 'client_name',     label: 'クライアント' },
                                                { field: 'title',           label: '案件名' },
                                                { field: 'sales_rep',       label: '担当営業' },
                                                { field: 'submission_date', label: '入稿日' },
                                                { field: 'sb_delivery_date',label: '下版日' },
                                            ]"
                                            :key="h.field"
                                            class="cursor-pointer select-none border border-yellow-300 px-2 py-1.5 text-left font-semibold text-yellow-900 hover:bg-yellow-200 whitespace-nowrap"
                                            @click="togglePendingSort(h.field)"
                                        >
                                            {{ h.label }}
                                            <span v-if="pendingListSort.field === h.field" class="ml-0.5">
                                                {{ pendingListSort.dir === 'asc' ? '↑' : '↓' }}
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="ticket in pendingListTickets"
                                        :key="ticket.id"
                                        class="cursor-pointer hover:bg-yellow-50 even:bg-white odd:bg-yellow-50/30"
                                        @click="openDetail(ticket)"
                                    >
                                        <td class="border border-yellow-200 px-2 py-1 font-mono">{{ ticket.jobcode || '—' }}</td>
                                        <td class="border border-yellow-200 px-2 py-1 max-w-[100px] truncate">{{ ticket.client_name || '—' }}</td>
                                        <td class="border border-yellow-200 px-2 py-1 max-w-[140px] truncate">{{ ticket.title }}</td>
                                        <td class="border border-yellow-200 px-2 py-1 whitespace-nowrap">{{ ticket.sales_rep || '—' }}</td>
                                        <td class="border border-yellow-200 px-2 py-1 whitespace-nowrap">{{ formatShortDate(ticket.submission_date) }}</td>
                                        <td class="border border-yellow-200 px-2 py-1 whitespace-nowrap">{{ formatShortDate(ticket.sb_delivery_date) }}</td>
                                    </tr>
                                    <tr v-if="pendingListTickets.length === 0">
                                        <td colspan="6" class="py-6 text-center text-gray-400">件数なし</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- カードグリッド: 2列（準備列はカードモード時のみ表示） -->
                        <div
                            v-if="!(col.key === 'pending' && pendingView === 'list')"
                            class="flex-1 min-h-0 overflow-y-auto p-4"
                        >
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
                                        @click.stop
                                        @change="toggleSelectForDelete(ticket.id)"
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
                                <div
                                    class="rounded-b-sm border-t px-2 py-0.5"
                                    :class="[cardColor(ticket).border, cardColor(ticket).bg]"
                                >
                                    <p class="truncate text-xs leading-tight" :class="cardColor(ticket).textMain">
                                        <span v-if="ticket.jobcode" class="font-medium" :class="cardColor(ticket).textSub">#{{ ticket.jobcode }}　</span>{{ ticket.title }}
                                    </p>
                                    <p class="mt-0.5 text-xs leading-tight" :class="cardColor(ticket).textSub">
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
                        </div><!-- /カードグリッド -->
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
                                <div class="flex items-start justify-between gap-2">
                                    <h1 class="min-w-0 flex-1 text-base font-bold text-gray-900 truncate">{{ detail.title }}</h1>
                                    <!-- カードカラー選択スウォッチ＋担当色変更 -->
                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        <!-- 色丸＋担当者名 -->
                                        <div class="flex items-end gap-1.5">
                                            <div
                                                v-for="key in sortedColorKeys"
                                                :key="key"
                                                class="flex flex-col items-center gap-0.5"
                                            >
                                                <button
                                                    type="button"
                                                    :title="colorUserName(key) ?? key"
                                                    :class="[
                                                        CARD_COLORS[key].swatch,
                                                        'h-5 w-5 rounded-full border-2 transition-transform hover:scale-110',
                                                        (detail.card_color ?? 'indigo') === key ? 'border-gray-700 scale-110' : 'border-white',
                                                    ]"
                                                    @click="setTicketColor(detail, key)"
                                                />
                                                <span
                                                    v-if="colorUserName(key)"
                                                    :title="colorUserName(key)"
                                                    class="max-w-[2.6rem] truncate text-center text-[9px] leading-none text-gray-500"
                                                >{{ colorUserName(key) }}</span>
                                            </div>
                                        </div>
                                        <!-- 担当色変更ボタン -->
                                        <button
                                            type="button"
                                            class="text-[10px] text-gray-400 underline hover:text-gray-600"
                                            @click.stop="showColorAssignPanel = !showColorAssignPanel"
                                        >担当色変更</button>
                                    </div>
                                </div>

                                <!-- 担当色変更パネル（インライン展開） -->
                                <div
                                    v-if="showColorAssignPanel"
                                    :key="colorPanelKey"
                                    class="mt-2 rounded-lg border border-gray-200 bg-white p-3 shadow-sm"
                                    @click.stop
                                >
                                    <div class="mb-2 text-xs font-semibold text-gray-600">担当色の設定（ドラッグで並び替え可）</div>
                                    <div class="flex flex-wrap gap-x-2 gap-y-1">
                                        <div
                                            v-for="(assignment, idx) in localColorAssignments"
                                            :key="assignment.color_key"
                                            draggable="true"
                                            class="flex items-center gap-1.5 rounded px-2 py-1 cursor-grab select-none border"
                                            :class="dragOverColorIdx === idx ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-gray-50 hover:bg-white'"
                                            @dragstart="onColorDragStart(idx)"
                                            @dragover.prevent="onColorDragOver(idx)"
                                            @drop="onColorDrop(idx)"
                                            @dragend="onColorDragEnd"
                                        >
                                            <span class="text-gray-300 text-xs select-none">⠿</span>
                                            <span :class="[CARD_COLORS[assignment.color_key]?.swatch, 'h-3.5 w-3.5 rounded-full shrink-0']"></span>
                                            <select
                                                :value="assignment.user_id ?? ''"
                                                class="rounded border border-gray-200 py-0.5 pl-1.5 pr-5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none"
                                                @change="setColorUser(assignment.color_key, $event.target.value)"
                                            >
                                                <option value="">— 未選択 —</option>
                                                <option v-for="u in prepressUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-2 rounded border border-gray-300 px-3 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                        @click="showColorAssignPanel = false"
                                    >閉じる</button>
                                </div>

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
                                v-if="detail.status !== 'outputting'"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded bg-orange-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-600"
                                :disabled="updatingStatus"
                                @click="changeStatus(detail, 'outputting')"
                            >出稿中にする</button>

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
                                    v-if="canDelete"
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
                            <div class="flex items-baseline gap-2 py-2">
                                <dt class="shrink-0 font-medium text-gray-500">クライアント</dt>
                                <dd class="text-gray-800">{{ detail.client_name || '—' }}</dd>
                            </div>
                            <div class="flex items-baseline gap-2 py-2">
                                <dt class="shrink-0 font-medium text-gray-500">担当営業</dt>
                                <dd class="text-gray-800">
                                    <span v-if="detail.sales_rep_entry">
                                        {{ detail.sales_rep_entry.name }}
                                        <span class="ml-1 text-xs text-green-700 bg-green-50 rounded px-1 py-0.5">DB登録済</span>
                                    </span>
                                    <span v-else>{{ detail.sales_rep || '—' }}</span>
                                </dd>
                            </div>
                            <div class="flex items-baseline gap-2 py-2">
                                <dt class="shrink-0 font-medium text-gray-500">伝票番号</dt>
                                <dd class="text-gray-800">{{ detail.jobcode || '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 py-2">
                                <div class="flex items-baseline gap-2">
                                    <dt class="shrink-0 font-medium text-gray-500">作成日</dt>
                                    <dd class="text-gray-800">{{ formatDate(detail.created_at) }}</dd>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <dt class="shrink-0 font-medium text-gray-500">入稿日</dt>
                                    <dd class="text-gray-800">{{ formatDate(detail.submission_date) || '—' }}</dd>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <dt class="shrink-0 font-medium text-gray-500">下版日</dt>
                                    <dd class="text-gray-800">{{ formatDate(detail.sb_delivery_date) || '—' }}</dd>
                                </div>
                            </div>

                            <!-- 作業チェック（工程別） -->
                            <div
                                v-for="stage in STAGES"
                                :key="stage"
                                class="py-2.5"
                            >
                                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-medium text-gray-500">作業チェック：{{ stage }}</span>
                                    <select
                                        :value="localStageChecks[stage]?.user_id ?? ''"
                                        class="rounded border border-gray-300 py-0.5 pl-1.5 pr-5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none"
                                        @change="saveStageUser(stage, $event.target.value)"
                                    >
                                        <option value="">— 作業者未選択 —</option>
                                        <option v-for="u in prepressUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <div class="flex flex-wrap gap-x-7 gap-y-2">
                                    <label
                                        v-for="item in CHECK_ITEMS"
                                        :key="item.key"
                                        class="flex cursor-pointer select-none items-center gap-1"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="localStageChecks[stage][item.key]"
                                            class="h-3.5 w-3.5 rounded accent-indigo-600"
                                            @change="saveStageCheckField(stage, item.key)"
                                        />
                                        <span class="text-xs text-gray-700">{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- アプリバージョン -->
                            <div class="flex flex-wrap items-center gap-5 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="shrink-0 text-xs font-medium text-gray-500">InDesign</span>
                                    <select
                                        v-model="localMeta.indesign_version"
                                        class="rounded border border-gray-300 py-1 pl-2 pr-6 text-xs focus:border-indigo-400 focus:outline-none"
                                        @change="saveMeta('indesign_version')"
                                    >
                                        <option value="">—</option>
                                        <option v-for="v in APP_VERSIONS" :key="v" :value="v">{{ v }}</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="shrink-0 text-xs font-medium text-gray-500">Illustrator</span>
                                    <select
                                        v-model="localMeta.illustrator_version"
                                        class="rounded border border-gray-300 py-1 pl-2 pr-6 text-xs focus:border-indigo-400 focus:outline-none"
                                        @change="saveMeta('illustrator_version')"
                                    >
                                        <option value="">—</option>
                                        <option v-for="v in APP_VERSIONS" :key="v" :value="v">{{ v }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- 備考 -->
                            <div class="py-2">
                                <div class="mb-1 text-xs font-medium text-gray-500">備考</div>
                                <textarea
                                    v-model="localMeta.check_memo"
                                    rows="2"
                                    placeholder="メモを入力…"
                                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs text-gray-800 focus:border-indigo-400 focus:outline-none resize-none"
                                    @input="saveCheckMemoDebounced"
                                    @blur="saveMeta('check_memo')"
                                ></textarea>
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
                            <!-- CSV一括登録 -->
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg border-2 border-gray-200 bg-white px-4 py-3 text-left text-sm font-medium text-gray-600 transition-colors hover:border-gray-300"
                                @click="closeCreateModal(); openCsvModal()"
                            >
                                <span class="text-lg">📊</span>
                                <span>CSV一括登録</span>
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

        <!-- CSV一括登録モーダル -->
        <Teleport to="body">
            <div
                v-if="showCsvModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 px-2 py-6"
                @click.self="closeCsvModal"
            >
                <div class="w-full max-w-7xl rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-800">CSV一括登録</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeCsvModal">✕</button>
                    </div>

                    <!-- ファイル選択（解析前） -->
                    <div v-if="csvAnalysisRows.length === 0 && !csvAnalyzing" class="px-6 py-6">
                        <p class="mb-3 text-sm text-gray-600">
                            CSV形式: <code class="rounded bg-gray-100 px-1 text-xs">No, 受注No., 得意先, 品名, 営業担当</code>（CP932 / UTF-8 対応）
                        </p>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-6 py-4 hover:border-green-500 hover:bg-green-50">
                            <span class="text-2xl">📊</span>
                            <span class="text-sm font-medium text-gray-600">CSVファイルを選択</span>
                            <input type="file" accept=".csv,text/csv" class="hidden" @change="onCsvFileSelect" />
                        </label>
                    </div>

                    <!-- 解析中 -->
                    <div v-if="csvAnalyzing" class="flex items-center justify-center py-12 text-blue-600">
                        <svg class="mr-3 h-6 w-6 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        解析中...
                    </div>

                    <!-- 解析結果 -->
                    <div v-if="csvAnalysisRows.length > 0 && !csvAnalyzing" class="px-6 pb-4">
                        <div class="mb-3 flex items-center gap-3 pt-4 flex-wrap">
                            <span class="text-sm font-semibold text-gray-700">{{ csvAnalysisRows.length }}件</span>
                            <span v-if="csvUnresolvedCount > 0" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                クライアント未解決 {{ csvUnresolvedCount }}件
                            </span>
                            <span v-else class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                クライアント全件解決済み
                            </span>
                            <span v-if="csvDupSkipCount > 0" class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-700">
                                受注番号重複 {{ csvDupSkipCount }}件スキップ
                            </span>
                        </div>

                        <div class="max-h-[60vh] overflow-y-auto rounded-lg border">
                            <table class="w-full text-xs">
                                <thead class="sticky top-0 bg-gray-100">
                                    <tr>
                                        <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">伝票番号</th>
                                        <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">得意先(CSV)</th>
                                        <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">品名</th>
                                        <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap w-52">クライアント解決</th>
                                        <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">担当営業(CSV)</th>
                                        <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap w-52">営業担当解決</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, idx) in csvAnalysisRows"
                                        :key="idx"
                                        :class="row.jobcode_dup && row.jobcode_dup !== 'none'
                                            ? 'bg-gray-100 opacity-60'
                                            : row.status === 'matched' ? 'bg-white' : row.status === 'candidates' ? 'bg-yellow-50' : 'bg-red-50'"
                                    >
                                        <td class="border-b px-3 py-2 font-mono whitespace-nowrap">
                                            <span>{{ row.jobcode || '—' }}</span>
                                            <span v-if="row.jobcode_dup === 'db'"
                                                class="ml-1 inline-block rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700"
                                                title="DBに同じ受注番号が登録済みのためスキップされます">DB重複</span>
                                            <span v-else-if="row.jobcode_dup === 'csv'"
                                                class="ml-1 inline-block rounded-full bg-yellow-100 px-1.5 py-0.5 text-[10px] font-semibold text-yellow-700"
                                                title="CSV内に同じ受注番号が存在するためスキップされます">CSV重複</span>
                                        </td>
                                        <td class="border-b px-3 py-2 whitespace-nowrap">{{ row.raw_client_name || '—' }}</td>
                                        <td class="border-b px-3 py-2 max-w-[160px] truncate">{{ row.title }}</td>

                                        <!-- クライアント解決列 -->
                                        <td class="border-b px-3 py-2">
                                            <!-- 一致済み -->
                                            <div v-if="row.status === 'matched'" class="flex items-center gap-1 text-green-700">
                                                <span>✅</span>
                                                <span class="truncate max-w-[160px]">{{ row.resolved_client_name || row.raw_client_name }}</span>
                                            </div>
                                            <!-- 候補あり -->
                                            <div v-else-if="row.status === 'candidates'" class="space-y-1">
                                                <p class="text-xs text-yellow-700 font-medium">候補を選択:</p>
                                                <div
                                                    v-for="c in row.candidates"
                                                    :key="c.id"
                                                    class="cursor-pointer rounded border border-yellow-300 bg-white px-2 py-0.5 text-xs hover:bg-yellow-100"
                                                    @click="csvSelectCandidate(idx, c)"
                                                >{{ c.name }}</div>
                                                <div v-if="!row.showSearch" class="flex flex-wrap gap-1">
                                                    <button type="button" class="text-xs text-blue-600 underline"
                                                        @click="row.showSearch = true; csvRowClientSearch[idx] = ''">一覧から選択</button>
                                                    <span class="text-gray-400">|</span>
                                                    <button type="button" class="text-xs text-purple-600 underline"
                                                        @click="openInlineClientModal(idx)">新規登録</button>
                                                </div>
                                                <div v-else class="mt-1">
                                                    <input v-model="csvRowClientSearch[idx]" type="text"
                                                        placeholder="クライアント名で検索"
                                                        class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                        @input="onCsvRowSearchInput(idx)" />
                                                    <div v-for="c in (row.searchResults ?? [])" :key="c.id"
                                                        class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                        @click="csvSelectFromSearch(idx, c)">{{ c.name }}</div>
                                                </div>
                                            </div>
                                            <!-- 未マッチ -->
                                            <div v-else class="space-y-1">
                                                <p class="text-xs text-red-600 font-medium">未マッチ</p>
                                                <div v-if="!row.showSearch" class="flex flex-wrap gap-1">
                                                    <button type="button" class="text-xs text-blue-600 underline"
                                                        @click="row.showSearch = true; csvRowClientSearch[idx] = ''">一覧から選択</button>
                                                    <span class="text-gray-400">|</span>
                                                    <button type="button" class="text-xs text-gray-500 underline"
                                                        @click="row.status = 'matched'; row.resolved_client_name = row.raw_client_name; row.resolved_client_id = null">名前のまま</button>
                                                    <span class="text-gray-400">|</span>
                                                    <button type="button" class="text-xs text-purple-600 underline"
                                                        @click="openInlineClientModal(idx)">新規登録</button>
                                                </div>
                                                <div v-else class="mt-1">
                                                    <input v-model="csvRowClientSearch[idx]" type="text"
                                                        placeholder="クライアント名で検索"
                                                        class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                        @input="onCsvRowSearchInput(idx)" />
                                                    <div v-for="c in (row.searchResults ?? [])" :key="c.id"
                                                        class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                        @click="csvSelectFromSearch(idx, c)">{{ c.name }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 担当営業(CSV)列 -->
                                        <td class="border-b px-3 py-2 whitespace-nowrap">{{ row.sales_rep || '—' }}</td>

                                        <!-- 営業担当解決列 -->
                                        <td class="border-b px-3 py-2">
                                            <div v-if="!row.sales_rep" class="text-gray-400 text-xs">—</div>
                                            <!-- 一致済み -->
                                            <div v-else-if="row.sales_rep_status === 'matched'" class="flex items-center gap-1 text-green-700">
                                                <span>✅</span>
                                                <span>{{ row.resolved_sales_rep_name }}</span>
                                            </div>
                                            <!-- 候補あり -->
                                            <div v-else-if="row.sales_rep_status === 'candidates'" class="space-y-1">
                                                <p class="text-xs text-yellow-700 font-medium">候補を選択:</p>
                                                <div
                                                    v-for="r in row.sales_rep_candidates"
                                                    :key="r.id"
                                                    class="cursor-pointer rounded border border-yellow-300 bg-white px-2 py-0.5 text-xs hover:bg-yellow-100"
                                                    @click="csvSelectSalesRepCandidate(idx, r)"
                                                >{{ r.name }}</div>
                                                <div v-if="!row.showSalesRepSearch" class="flex flex-wrap gap-1">
                                                    <button type="button" class="text-xs text-blue-600 underline"
                                                        @click="row.showSalesRepSearch = true; csvRowSalesRepSearch[idx] = ''">一覧から選択</button>
                                                    <span class="text-gray-400">|</span>
                                                    <button type="button" class="text-xs text-gray-500 underline"
                                                        @click="row.sales_rep_status = 'matched'; row.resolved_sales_rep_name = row.sales_rep; row.resolved_sales_rep_id = null">テキストのまま</button>
                                                    <span class="text-gray-400">|</span>
                                                    <button type="button" class="text-xs text-purple-600 underline"
                                                        @click="openInlineSalesRepModal(idx)">新規登録</button>
                                                </div>
                                                <div v-else class="mt-1">
                                                    <input v-model="csvRowSalesRepSearch[idx]" type="text" placeholder="氏名で検索"
                                                        class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                        @input="onCsvRowSalesRepSearchInput(idx)" />
                                                    <div v-for="r in (row.salesRepSearchResults ?? [])" :key="r.id"
                                                        class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                        @click="csvSelectSalesRepFromSearch(idx, r)">{{ r.name }}</div>
                                                </div>
                                            </div>
                                            <!-- 未マッチ -->
                                            <div v-else class="space-y-1">
                                                <p class="text-xs text-orange-600 font-medium">未マッチ</p>
                                                <div v-if="!row.showSalesRepSearch" class="flex flex-wrap gap-1">
                                                    <button type="button" class="text-xs text-blue-600 underline"
                                                        @click="row.showSalesRepSearch = true; csvRowSalesRepSearch[idx] = ''">一覧から選択</button>
                                                    <span class="text-gray-400">|</span>
                                                    <button type="button" class="text-xs text-gray-500 underline"
                                                        @click="row.sales_rep_status = 'matched'; row.resolved_sales_rep_name = row.sales_rep; row.resolved_sales_rep_id = null">テキストのまま</button>
                                                    <span class="text-gray-400">|</span>
                                                    <button type="button" class="text-xs text-purple-600 underline"
                                                        @click="openInlineSalesRepModal(idx)">新規登録</button>
                                                </div>
                                                <div v-else class="mt-1">
                                                    <input v-model="csvRowSalesRepSearch[idx]" type="text" placeholder="氏名で検索"
                                                        class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                        @input="onCsvRowSalesRepSearchInput(idx)" />
                                                    <div v-for="r in (row.salesRepSearchResults ?? [])" :key="r.id"
                                                        class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                        @click="csvSelectSalesRepFromSearch(idx, r)">{{ r.name }}</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex items-center justify-end gap-3">
                            <button type="button"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                                @click="closeCsvModal">キャンセル</button>
                            <button type="button"
                                class="rounded-lg bg-green-700 px-6 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50"
                                :disabled="csvUnresolvedCount > 0 || csvImporting"
                                @click="executeCsvImport"
                            >{{ csvImporting ? '保存中...'
                                : csvDupSkipCount > 0
                                    ? `一括保存 (${csvImportableCount}件) ※${csvDupSkipCount}件スキップ`
                                    : `一括保存 (${csvAnalysisRows.length}件)` }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- インライン クライアント新規登録モーダル -->
            <div
                v-if="showInlineClientModal"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
                @click.self="showInlineClientModal = false"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                    <h4 class="mb-4 text-base font-semibold text-gray-800">クライアント新規登録</h4>

                    <!-- 重複メッセージ -->
                    <div v-if="inlineClientNote" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                        {{ inlineClientNote }}
                    </div>

                    <template v-if="!inlineClientNote">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">クライアント名 <span class="text-red-500">*</span></label>
                                <input v-model="inlineClientForm.name" type="text"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Client ID（任意）</label>
                                <input v-model="inlineClientForm.client_code" type="text"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">※ 製版部署に紐づけて登録されます</p>
                    </template>

                    <div class="mt-4 flex gap-2 justify-end">
                        <template v-if="inlineClientNote">
                            <button type="button" @click="showInlineClientModal = false"
                                class="rounded bg-blue-600 px-4 py-1.5 text-sm text-white hover:bg-blue-700">OK</button>
                        </template>
                        <template v-else>
                            <button type="button" @click="showInlineClientModal = false"
                                class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">キャンセル</button>
                            <button type="button" @click="saveInlineClient" :disabled="!inlineClientForm.name || inlineClientSaving"
                                class="rounded bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50">
                                {{ inlineClientSaving ? '登録中...' : '登録' }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- インライン 営業担当新規登録モーダル -->
            <div
                v-if="showInlineSalesRepModal"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
                @click.self="showInlineSalesRepModal = false"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                    <h4 class="mb-4 text-base font-semibold text-gray-800">営業担当新規登録</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">氏名 <span class="text-red-500">*</span></label>
                            <input v-model="inlineSalesRepForm.name" type="text"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">会社（任意）</label>
                            <input v-model="inlineSalesRepForm.company" type="text"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                                placeholder="株式会社サンエー印刷 など" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2 justify-end">
                        <button type="button" @click="showInlineSalesRepModal = false"
                            class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">キャンセル</button>
                        <button type="button" @click="saveInlineSalesRep" :disabled="!inlineSalesRepForm.name || inlineSalesRepSaving"
                            class="rounded bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50">
                            {{ inlineSalesRepSaving ? '登録中...' : '登録' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
