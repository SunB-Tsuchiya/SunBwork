<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import axios from 'axios';
import { route } from 'ziggy-js';
import TeamWeekPlanner from '@/Components/TeamRoom/TeamWeekPlanner.vue';

const props = defineProps({
    teamId:     { type: Number, required: true },
    authUserId: { type: Number, default: null },
});

// ────────────────── データ ──────────────────
// API から取得したイベント (FullCalendar 形式)
const events = ref([]);

async function fetchEvents() {
    try {
        const res = await axios.get(route('team-rooms.events.index', { team: props.teamId }));
        events.value = res.data;
    } catch { /* ignore */ }
}

onMounted(fetchEvents);

// ────────────────── ビュー切り替え ──────────────────
const currentView = ref('calendar'); // 'calendar' | 'week-planner'
const calendarRef = ref(null);

function switchView(v) {
    currentView.value = v;
    if (v === 'calendar') {
        nextTick(() => calendarRef.value?.getApi?.()?.changeView('dayGridMonth'));
    }
}

const updateCalendarSize = () => nextTick(() => calendarRef.value?.getApi?.()?.updateSize?.());
defineExpose({ updateCalendarSize, refreshCalendar: updateCalendarSize });

// ────────────────── FullCalendar イベント連携 ──────────────────
watch(events, (evs) => {
    nextTick(() => {
        const api = calendarRef.value?.getApi?.();
        if (!api) return;
        api.removeAllEvents();
        if (evs.length > 0) {
            api.addEventSource(evs.map(e => ({
                id:              String(e.id),
                title:           e.title,
                start:           e.start,
                end:             e.end,
                allDay:          e.allDay ?? e.all_day ?? false,
                backgroundColor: '#4f46e5',
                borderColor:     '#4338ca',
                extendedProps: {
                    description:  e.description,
                    user_name:    e.user_name,
                    editable:     e.editable,
                    teamEventId:  e.id,
                },
            })));
        }
    });
}, { immediate: true });

// ────────────────── スケジュールパネル ──────────────────
const schedulePanelOpen = ref(false);
const panelSortKey = ref('start');
const panelSortDir = ref('asc');

const panelSortedEvents = computed(() => {
    const dir = panelSortDir.value === 'asc' ? 1 : -1;
    return [...events.value].sort((a, b) => {
        const av = String(a[panelSortKey.value] ?? '').split('T')[0];
        const bv = String(b[panelSortKey.value] ?? '').split('T')[0];
        if (av < bv) return -1 * dir;
        if (av > bv) return  1 * dir;
        return 0;
    });
});

function togglePanelSort(key) {
    if (panelSortKey.value === key) {
        panelSortDir.value = panelSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        panelSortKey.value = key;
        panelSortDir.value = 'asc';
    }
}

function panelSortIcon(key) {
    if (panelSortKey.value !== key) return '↕';
    return panelSortDir.value === 'asc' ? '▲' : '▼';
}

// ────────────────── パネル編集モード ──────────────────
const panelEditMode = ref(false);
const panelEditRows = ref([]);
const panelSaving   = ref(false);
let   _keySeq       = 0;

function togglePanelEditMode() {
    if (panelEditMode.value) {
        cancelPanelEditMode();
    } else {
        panelEditRows.value = events.value.map(e => ({
            _key:        e.id,
            id:          e.id,
            start_date:  String(e.start ?? '').split('T')[0],
            end_date:    e.end ? String(e.end).split('T')[0] : '',
            name:        e.title ?? '',
            description: e.description ?? '',
        }));
        panelEditMode.value = true;
    }
}

function cancelPanelEditMode() {
    panelEditMode.value = false;
    panelEditRows.value = [];
}

function addPanelEditRow() {
    panelEditRows.value.push({
        _key:        'new_' + (++_keySeq),
        id:          null,
        start_date:  '',
        end_date:    '',
        name:        '',
        description: '',
    });
}

function removePanelEditRow(idx) {
    panelEditRows.value.splice(idx, 1);
}

async function savePanelEdits() {
    if (panelSaving.value) return;
    panelSaving.value = true;
    const originalIds = new Set(events.value.map(e => e.id));
    const editIds     = new Set(panelEditRows.value.filter(r => r.id).map(r => r.id));
    const deletedIds  = [...originalIds].filter(id => !editIds.has(id));
    try {
        for (const id of deletedIds) {
            await axios.delete(route('team-rooms.events.destroy', { team: props.teamId, event: id }));
        }
        for (const row of panelEditRows.value) {
            if (!row.name.trim()) continue;
            const payload = {
                title:       row.name,
                description: row.description || null,
                starts_at:   row.start_date || null,
                ends_at:     row.end_date || row.start_date || null,
                all_day:     true,
            };
            if (row.id) {
                await axios.put(route('team-rooms.events.update', { team: props.teamId, event: row.id }), payload);
            } else {
                await axios.post(route('team-rooms.events.store', { team: props.teamId }), payload);
            }
        }
        panelEditMode.value = false;
        panelEditRows.value = [];
        await fetchEvents();
    } catch {
        alert('保存に失敗しました');
    } finally {
        panelSaving.value = false;
    }
}

// ────────────────── 予定モーダル ──────────────────
const showEventModal   = ref(false);
const eventModalIsEdit = ref(false);
const eventForm        = ref({ id: null, title: '', description: '', start_date: '', end_date: '', all_day: true });
const eventFormSaving  = ref(false);

const getTodayString = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

function openCreateModal(startDate = null, endDate = null) {
    const today = getTodayString();
    eventForm.value = {
        id:          null,
        title:       '',
        description: '',
        start_date:  startDate || today,
        end_date:    endDate || startDate || today,
        all_day:     true,
    };
    eventModalIsEdit.value = false;
    showEventModal.value   = true;
}

function openEditModal(fcEvent) {
    const startStr = fcEvent.startStr ? fcEvent.startStr.split('T')[0] : '';
    let endStr = '';
    if (fcEvent.end) {
        const d = new Date(fcEvent.end);
        if (fcEvent.allDay) d.setDate(d.getDate() - 1);
        endStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
    const eid = fcEvent.extendedProps?.teamEventId ?? fcEvent.id;
    eventForm.value = {
        id:          eid,
        title:       fcEvent.title ?? '',
        description: fcEvent.extendedProps?.description ?? '',
        start_date:  startStr,
        end_date:    endStr || startStr,
        all_day:     fcEvent.allDay ?? true,
    };
    eventModalIsEdit.value = true;
    showEventModal.value   = true;
}

async function saveEvent() {
    if (!eventForm.value.title.trim()) { alert('タイトルを入力してください'); return; }
    if (!eventForm.value.start_date)   { alert('開始日を指定してください');    return; }
    eventFormSaving.value = true;
    try {
        const payload = {
            title:       eventForm.value.title,
            description: eventForm.value.description || null,
            starts_at:   eventForm.value.start_date,
            ends_at:     eventForm.value.end_date || eventForm.value.start_date,
            all_day:     eventForm.value.all_day,
        };
        if (eventModalIsEdit.value && eventForm.value.id) {
            await axios.put(route('team-rooms.events.update', { team: props.teamId, event: eventForm.value.id }), payload);
        } else {
            await axios.post(route('team-rooms.events.store', { team: props.teamId }), payload);
        }
        showEventModal.value = false;
        await fetchEvents();
    } catch {
        alert('保存に失敗しました');
    } finally {
        eventFormSaving.value = false;
    }
}

async function deleteEvent() {
    if (!eventForm.value.id) return;
    if (!confirm('この予定を削除しますか？')) return;
    eventFormSaving.value = true;
    try {
        await axios.delete(route('team-rooms.events.destroy', { team: props.teamId, event: eventForm.value.id }));
        showEventModal.value = false;
        await fetchEvents();
    } catch {
        alert('削除に失敗しました');
    } finally {
        eventFormSaving.value = false;
    }
}

// ────────────────── ホバーポップアップ ──────────────────
const hoverPopup = ref({ show: false, x: 0, y: 0, title: '', startDate: '', endDate: '', description: '' });

// ────────────────── ドラッグ＆ドロップ / リサイズ ──────────────────
const fmtLocal = (d) => {
    if (!d) return null;
    const yyyy = d.getFullYear();
    const mm   = String(d.getMonth() + 1).padStart(2, '0');
    const dd   = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

async function handleEventDrop(info) {
    const ev  = info.event;
    const eid = ev.extendedProps?.teamEventId ?? ev.id;
    const newStart = ev.start ? fmtLocal(ev.start) : null;
    let newEnd = null;
    if (ev.end) {
        const d = new Date(ev.end);
        d.setDate(d.getDate() - 1);
        newEnd = fmtLocal(d);
    }
    try {
        await axios.put(route('team-rooms.events.update', { team: props.teamId, event: eid }), {
            title:     ev.title,
            starts_at: newStart,
            ends_at:   newEnd || newStart,
            all_day:   true,
        });
        await fetchEvents();
    } catch {
        alert('移動の保存に失敗しました');
        info.revert();
    }
}

async function handleEventResize(info) {
    const ev  = info.event;
    const eid = ev.extendedProps?.teamEventId ?? ev.id;
    const newStart = ev.start ? fmtLocal(ev.start) : null;
    let newEnd = null;
    if (ev.end) {
        const d = new Date(ev.end);
        if (ev.allDay) d.setDate(d.getDate() - 1);
        newEnd = fmtLocal(d);
    }
    try {
        await axios.put(route('team-rooms.events.update', { team: props.teamId, event: eid }), {
            title:     ev.title,
            starts_at: newStart,
            ends_at:   newEnd || newStart,
            all_day:   true,
        });
        await fetchEvents();
    } catch {
        alert('予定の更新に失敗しました');
        info.revert();
    }
}

// 日付選択 (allDay end は exclusive → -1日)
const subOneDayStr = (dateStr) => {
    if (!dateStr) return null;
    const s = String(dateStr).split('T')[0];
    const [y, m, d] = s.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() - 1);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
};

// ────────────────── CSV ──────────────────
function handleCsvExport() {
    window.location.href = route('team-rooms.events.csv_export', { team: props.teamId });
}

const showCsvImportModal = ref(false);
const csvImportFile      = ref(null);
const csvImportErrors    = ref([]);
const csvImportLoading   = ref(false);

function openCsvImportModal() {
    csvImportFile.value   = null;
    csvImportErrors.value = [];
    showCsvImportModal.value = true;
}

function onCsvFileChange(e) {
    csvImportFile.value   = e.target.files[0] ?? null;
    csvImportErrors.value = [];
}

async function submitCsvImport() {
    if (!csvImportFile.value) { alert('CSVファイルを選択してください'); return; }
    csvImportLoading.value = true;
    csvImportErrors.value  = [];
    try {
        const formData = new FormData();
        formData.append('file', csvImportFile.value);
        const res = await axios.post(
            route('team-rooms.events.csv_import', { team: props.teamId }),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        const created = res.data.created ?? 0;
        showCsvImportModal.value = false;
        alert(`${created}件の予定をインポートしました`);
        await fetchEvents();
    } catch (e) {
        if (e.response?.data?.errors) {
            csvImportErrors.value = e.response.data.errors;
        } else {
            alert('インポートに失敗しました');
        }
    } finally {
        csvImportLoading.value = false;
    }
}

// ────────────────── FullCalendar オプション ──────────────────
const calendarOptions = {
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'ja',
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    selectable: true,
    firstDay: 1,
    weekText: '週',
    dayHeaderFormat: { weekday: 'short' },
    height: 'auto',
    editable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    eventDrop: handleEventDrop,
    eventResize: handleEventResize,
    eventDidMount(info) {
        try {
            info.el.style.backgroundColor = '#4f46e5';
            info.el.style.borderColor     = '#4338ca';
            info.el.style.color           = '#ffffff';
            const mainEl = info.el.querySelector('.fc-event-main');
            if (mainEl) mainEl.style.color = '#ffffff';
        } catch { /* ignore */ }
    },
    eventMouseEnter(info) {
        const ev  = info.event;
        const ext = ev.extendedProps || {};
        const startStr = ev.startStr ? ev.startStr.split('T')[0] : '';
        let endDate = '';
        if (ev.end) {
            const d = new Date(ev.end);
            d.setDate(d.getDate() - 1);
            endDate = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }
        const mx = info.jsEvent ? info.jsEvent.clientX : info.el.getBoundingClientRect().left;
        const my = info.jsEvent ? info.jsEvent.clientY : info.el.getBoundingClientRect().bottom;
        hoverPopup.value = {
            show: true,
            x: Math.min(mx + 12, window.innerWidth - 290),
            y: Math.min(my + 16, window.innerHeight - 120),
            title:       ev.title,
            startDate:   startStr,
            endDate,
            description: ext.description || '',
        };
    },
    eventMouseLeave() {
        hoverPopup.value.show = false;
    },
    eventClick(info) {
        openEditModal(info.event);
    },
    select(selectInfo) {
        const start = selectInfo.startStr.split('T')[0];
        const end   = selectInfo.endStr ? subOneDayStr(selectInfo.endStr.split('T')[0]) : start;
        openCreateModal(start, end);
    },
};
</script>

<template>
    <div>
        <!-- ── ボタンバー ── -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button
                @click="switchView('calendar')"
                :class="currentView === 'calendar' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="rounded px-3 py-1.5 text-sm font-medium"
            >月カレンダー</button>
            <button
                @click="switchView('week-planner')"
                :class="currentView === 'week-planner' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="rounded px-3 py-1.5 text-sm font-medium"
            >週間プランナー</button>
            <span class="mx-1 text-gray-300">|</span>
            <button
                @click="openCreateModal()"
                class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >予定作成</button>
            <button
                @click="schedulePanelOpen = !schedulePanelOpen"
                :class="schedulePanelOpen ? 'bg-indigo-700 text-white' : 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200'"
                class="rounded px-4 py-2 text-sm font-medium"
            >スケジュール</button>
            <button
                @click="handleCsvExport"
                class="rounded border border-green-600 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50"
            >CSV出力</button>
            <button
                @click="openCsvImportModal"
                class="rounded border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
            >CSV取込</button>
        </div>

        <!-- ── スケジュールパネル ── -->
        <div v-if="schedulePanelOpen" class="mb-4 rounded border border-gray-200 bg-gray-50 p-4">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-gray-800">スケジュール一覧</h3>
                <div class="ml-2 flex flex-wrap gap-2">
                    <button
                        type="button"
                        :class="panelEditMode
                            ? 'rounded border border-gray-400 bg-gray-600 px-3 py-1 text-xs font-medium text-white hover:bg-gray-700'
                            : 'rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50'"
                        @click="togglePanelEditMode"
                    >{{ panelEditMode ? '編集モードを終了' : '編集モード' }}</button>
                    <button v-if="!panelEditMode" type="button"
                        class="rounded border border-green-600 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-50"
                        @click="handleCsvExport"
                    >CSV出力</button>
                    <button v-if="!panelEditMode" type="button"
                        class="rounded border border-indigo-500 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                        @click="openCsvImportModal"
                    >CSV取込</button>
                </div>
            </div>

            <!-- 閲覧モード -->
            <template v-if="!panelEditMode">
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="cursor-pointer select-none border px-3 py-1.5 text-left text-xs font-medium text-gray-500 hover:bg-gray-200"
                                    @click="togglePanelSort('start')">開始日 <span class="ml-0.5">{{ panelSortIcon('start') }}</span></th>
                                <th class="cursor-pointer select-none border px-3 py-1.5 text-left text-xs font-medium text-gray-500 hover:bg-gray-200"
                                    @click="togglePanelSort('end')">終了日 <span class="ml-0.5">{{ panelSortIcon('end') }}</span></th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">内容</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in panelSortedEvents" :key="e.id" class="hover:bg-white">
                                <td class="border px-3 py-2 text-gray-700">{{ e.start ? String(e.start).split('T')[0] : '-' }}</td>
                                <td class="border px-3 py-2 text-gray-700">{{ e.end ? String(e.end).split('T')[0] : '-' }}</td>
                                <td class="border px-3 py-2 font-medium text-gray-900">{{ e.title || '-' }}</td>
                                <td class="border px-3 py-2 text-gray-600">{{ e.description ? String(e.description).slice(0, 40) : '' }}</td>
                            </tr>
                            <tr v-if="events.length === 0">
                                <td colspan="4" class="border px-3 py-4 text-center text-xs text-gray-400">予定未登録</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- 編集モード -->
            <template v-else>
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">開始日</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">終了日</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">内容</th>
                                <th class="border px-2 py-1.5 text-xs font-medium text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in panelEditRows" :key="row._key" class="bg-white">
                                <td class="border px-2 py-1.5">
                                    <input type="date" v-model="row.start_date"
                                        class="w-36 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none" />
                                </td>
                                <td class="border px-2 py-1.5">
                                    <input type="date" v-model="row.end_date"
                                        class="w-36 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none" />
                                </td>
                                <td class="border px-2 py-1.5">
                                    <input type="text" v-model="row.name" placeholder="タイトル"
                                        class="w-full min-w-32 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none" />
                                </td>
                                <td class="border px-2 py-1.5">
                                    <input type="text" v-model="row.description" placeholder="内容（任意）"
                                        class="w-full min-w-40 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none" />
                                </td>
                                <td class="border px-2 py-1.5 text-center">
                                    <button type="button"
                                        class="rounded px-2 py-0.5 text-xs text-red-500 hover:bg-red-50 hover:text-red-700"
                                        @click="removePanelEditRow(idx)">×</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button"
                    class="mt-2 rounded border border-dashed border-gray-300 px-4 py-1.5 text-xs text-gray-500 hover:border-indigo-300 hover:text-indigo-600"
                    @click="addPanelEditRow">＋ 行を追加</button>
                <div class="mt-3 flex gap-2">
                    <button type="button" :disabled="panelSaving"
                        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        @click="savePanelEdits">{{ panelSaving ? '保存中…' : '保存' }}</button>
                    <button type="button" :disabled="panelSaving"
                        class="rounded bg-gray-100 px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-200 disabled:opacity-50"
                        @click="cancelPanelEditMode">キャンセル</button>
                </div>
            </template>
        </div>

        <!-- FullCalendar（月カレンダー） -->
        <FullCalendar v-show="currentView === 'calendar'" ref="calendarRef" :options="calendarOptions" />

        <!-- 週間プランナー -->
        <TeamWeekPlanner
            v-if="currentView === 'week-planner'"
            :team-id="teamId"
            :events="events"
        />

        <!-- ホバーポップアップ -->
        <Teleport to="body">
            <div
                v-if="hoverPopup.show"
                class="pointer-events-none fixed z-[9999] max-w-xs rounded-lg border border-gray-200 bg-white p-3 text-sm shadow-lg"
                :style="{ left: hoverPopup.x + 'px', top: hoverPopup.y + 'px' }"
            >
                <div class="mb-1 font-bold text-gray-800">{{ hoverPopup.title }}</div>
                <div class="mb-1 text-xs text-gray-500">
                    {{ hoverPopup.startDate }}
                    <template v-if="hoverPopup.endDate && hoverPopup.endDate !== hoverPopup.startDate"> 〜 {{ hoverPopup.endDate }}</template>
                </div>
                <div v-if="hoverPopup.description" class="whitespace-pre-wrap text-gray-700">{{ hoverPopup.description }}</div>
            </div>
        </Teleport>

        <!-- 予定モーダル（作成・編集） -->
        <div v-if="showEventModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">{{ eventModalIsEdit ? '予定詳細' : '予定作成' }}</h2>
                <div class="mb-2">
                    <label class="block text-sm font-medium">タイトル</label>
                    <input type="text" v-model="eventForm.title" class="w-full rounded border p-2 focus:border-indigo-400 focus:outline-none" />
                </div>
                <div class="mb-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">開始日</label>
                            <input type="date" v-model="eventForm.start_date" class="w-full rounded border p-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium">終了日</label>
                            <input type="date" v-model="eventForm.end_date" :min="eventForm.start_date" class="w-full rounded border p-2" />
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">内容</label>
                    <textarea v-model="eventForm.description" class="w-full rounded border p-2 focus:border-indigo-400 focus:outline-none" rows="4"></textarea>
                </div>
                <div class="mt-4 flex justify-between">
                    <button v-if="eventModalIsEdit" type="button" @click="deleteEvent"
                        :disabled="eventFormSaving"
                        class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">削除</button>
                    <div v-else></div>
                    <div class="flex gap-2">
                        <button type="button" @click="showEventModal = false"
                            class="rounded bg-gray-300 px-4 py-2 text-sm font-medium">キャンセル</button>
                        <button type="button" @click="saveEvent"
                            :disabled="eventFormSaving"
                            class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">{{ eventFormSaving ? '保存中...' : '保存' }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV インポートモーダル -->
        <div v-if="showCsvImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">CSVインポート</h2>
                <div class="mb-4 rounded bg-gray-50 p-3 text-sm text-gray-600">
                    <p class="mb-1 font-medium">CSVファイルのフォーマット（1行目はヘッダー行）：</p>
                    <code class="block rounded bg-gray-100 p-2 text-xs">タイトル,開始日(YYYY-MM-DD),終了日(YYYY-MM-DD),内容</code>
                    <p class="mt-1 text-xs text-gray-500">※ 終了日・内容は省略可</p>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium">CSVファイルを選択</label>
                    <input type="file" accept=".csv,text/csv" @change="onCsvFileChange"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:rounded file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-indigo-700" />
                </div>
                <div v-if="csvImportErrors.length > 0" class="mb-4 rounded border border-red-300 bg-red-50 p-3">
                    <p class="mb-1 text-sm font-medium text-red-700">エラーがあります：</p>
                    <ul class="list-disc pl-4 text-sm text-red-600">
                        <li v-for="err in csvImportErrors" :key="err">{{ err }}</li>
                    </ul>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showCsvImportModal = false"
                        class="rounded bg-gray-300 px-4 py-2 text-sm font-medium">キャンセル</button>
                    <button type="button" @click="submitCsvImport" :disabled="csvImportLoading"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">{{ csvImportLoading ? '取込中...' : 'インポート実行' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
