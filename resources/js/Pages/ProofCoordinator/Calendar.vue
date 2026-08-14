<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import jaLocale from '@fullcalendar/core/locales/ja';

// ─────────────────────────────────────────────────────────────────
//  Props
// ─────────────────────────────────────────────────────────────────
const props = defineProps({
    members:     { type: Array,  default: () => [] },
    schedules:   { type: Array,  default: () => [] },
    unassigned:  { type: Array,  default: () => [] },
    date:        { type: String, default: '' },
    monthEvents: { type: Array,  default: () => [] },
});

// ─────────────────────────────────────────────────────────────────
//  表示モード
// ─────────────────────────────────────────────────────────────────
const viewMode = ref('day'); // 'day' | 'month'

// 月ビュー用
const STATUS_COLORS_HEX = {
    pending:     '#9ca3af',
    assigned:    '#3b82f6',
    in_progress: '#f97316',
    completed:   '#22c55e',
    reservation: '#db2777',
};

const calendarEvents = computed(() =>
    props.monthEvents.map(e => ({
        id:    `${e.type ?? 'proof_request'}_${e.id}`,
        title: `${e.title}${e.proofreader ? ' (' + e.proofreader + ')' : ''}`,
        start: e.start,
        end: e.end || undefined,
        allDay: true,
        color: STATUS_COLORS_HEX[e.status] ?? '#9ca3af',
        extendedProps: e,
    }))
);

const calendarOptions = computed(() => ({
    plugins:      [dayGridPlugin],
    initialView:  'dayGridMonth',
    locale:       jaLocale,
    events:       calendarEvents.value,
    headerToolbar: {
        left:   'prev,next today',
        center: 'title',
        right:  '',
    },
    eventContent: (arg) => ({
        html: `<div class="overflow-hidden text-ellipsis whitespace-nowrap px-1 text-xs">${arg.event.title}</div>`,
    }),
    eventClick: (info) => {
        const event = info.event.extendedProps;
        if (event.type === 'proof_reservation') {
            router.get(route('proof_coordinator.reservations.show', { reservation: event.id }));
            return;
        }
        router.get(route('proof_coordinator.assignments.show', { proofRequest: event.id }));
    },
}));

// ─────────────────────────────────────────────────────────────────
//  定数
// ─────────────────────────────────────────────────────────────────
const START_HOUR  = 8;
const END_HOUR    = 18;
const TOTAL_MINS  = (END_HOUR - START_HOUR) * 60;  // 600
const MEMBER_W    = 150;  // px (左カラム固定幅)
const ROW_H       = 64;   // px (メンバー行の高さ)
const HEADER_H    = 40;   // px (時刻ヘッダーの高さ)
const SNAP        = 15;   // 分スナップ

// ─────────────────────────────────────────────────────────────────
//  State
// ─────────────────────────────────────────────────────────────────
// toISOString() は UTC を返すため JST 00:00〜08:59 に前日になる。ローカル日付を使う
const currentDate    = ref(props.date || new Date().toLocaleDateString('sv-SE'));
const localSchedules = ref(props.schedules.map(s => ({ ...s })));
const unassigned     = ref(props.unassigned.map(u => ({ ...u })));
const hideScheduled  = ref(false);

const timelineAreaRef = ref(null);  // タイムライン列の DOM ref
const timelineW       = ref(1200);  // 実際の幅（ResizeObserver で更新）

// ドラッグ状態
const drag = ref(null);
// { scheduleId, type: 'move'|'resize', origStartMin, origEndMin, origUserId, currentUserId,
//   previewStartMin, previewEndMin, startClientX, startClientY, rowIndex }

// 新規選択（空きエリアクリック）
const selecting = ref(null);
// { memberId, startMin, endMin, startClientX }

// モーダル
const showAssignModal  = ref(false);
const assignModalData  = ref({ memberId: null, startMin: null, endMin: null }); // 新規作成用
const showDetailModal  = ref(false);
const detailSchedule   = ref(null);

// ─────────────────────────────────────────────────────────────────
//  Computed
// ─────────────────────────────────────────────────────────────────
const hours = computed(() =>
    Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i)
);

const filteredHoursForLines = computed(() =>
    hours.value.filter(h => h > START_HOUR)
);

const filteredHoursFor30Mins = computed(() =>
    hours.value.filter(h => h < END_HOUR)
);

const pxPerMin = computed(() => timelineW.value / TOTAL_MINS);

// 日付表示
const displayDate = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    const days = ['日', '月', '火', '水', '木', '金', '土'];
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${days[d.getDay()]}）`;
});

// 非表示フィルタ適用後のスケジュール
const filteredSchedules = computed(() => {
    if (!hideScheduled.value) return localSchedules.value;
    const scheduledIds = new Set(localSchedules.value.map(s => s.proof_request_id));
    // 既に1件でもスケジュール済みの proof_request_id はフィルタしない（ドラッグ済みブロック自体は消さない）
    return localSchedules.value;
});

function schedulesForMember(userId) {
    return filteredSchedules.value.filter(s => s.user_id === userId);
}

// ─────────────────────────────────────────────────────────────────
//  日付変換ユーティリティ
// ─────────────────────────────────────────────────────────────────

/** ISO文字列 → JSTでの分（8:00起点）*/
function isoToMinutes(isoStr) {
    const d = new Date(isoStr);
    // JST = UTC + 9h
    const jstTotalMin = d.getUTCHours() * 60 + d.getUTCMinutes() + 9 * 60;
    return jstTotalMin - START_HOUR * 60;
}

/** 8:00起点の分 → ISO文字列（JST→UTC変換）*/
function minutesToIso(date, minsFromStart) {
    const totalMinFromMidnight = START_HOUR * 60 + minsFromStart;
    const h = Math.floor(totalMinFromMidnight / 60);
    const m = totalMinFromMidnight % 60;
    return new Date(
        `${date}T${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:00+09:00`
    ).toISOString();
}

function minsToTimeStr(mins) {
    const total = START_HOUR * 60 + mins;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function snapMins(mins) {
    return Math.round(mins / SNAP) * SNAP;
}

function clampMins(mins) {
    return Math.max(0, Math.min(TOTAL_MINS, mins));
}

// ─────────────────────────────────────────────────────────────────
//  ブロックスタイル
// ─────────────────────────────────────────────────────────────────
const STATUS_COLORS = {
    pending:     'bg-gray-200 border-gray-400 text-gray-700',
    assigned:    'bg-blue-200 border-blue-400 text-blue-800',
    in_progress: 'bg-pink-200 border-pink-400 text-pink-800',
    completed:   'bg-white border-green-400 text-green-800',
};

function blockStyle(schedule) {
    const startMin = isoToMinutes(schedule.starts_at);
    const endMin   = isoToMinutes(schedule.ends_at);
    const left  = Math.max(0, startMin) / TOTAL_MINS * 100;
    const width = Math.max(0, Math.min(endMin, TOTAL_MINS) - Math.max(0, startMin)) / TOTAL_MINS * 100;
    return {
        left:   left  + '%',
        width:  width + '%',
        top:    '4px',
        height: (ROW_H - 8) + 'px',
    };
}

function blockColor(schedule) {
    return STATUS_COLORS[schedule.status] ?? STATUS_COLORS.pending;
}

// ドラッグ中プレビューブロックスタイル
function previewStyle(drag) {
    if (!drag) return {};
    const left  = Math.max(0, drag.previewStartMin) / TOTAL_MINS * 100;
    const width = Math.max(0, drag.previewEndMin - drag.previewStartMin) / TOTAL_MINS * 100;
    return {
        left:   left  + '%',
        width:  width + '%',
        top:    '4px',
        height: (ROW_H - 8) + 'px',
    };
}

// 新規選択プレビュー
function selectionStyle(sel) {
    if (!sel) return {};
    const s = Math.min(sel.startMin, sel.endMin);
    const e = Math.max(sel.startMin, sel.endMin);
    const left  = Math.max(0, s) / TOTAL_MINS * 100;
    const width = Math.max(0, e - s) / TOTAL_MINS * 100;
    return {
        left:   left  + '%',
        width:  width + '%',
        top:    '4px',
        height: (ROW_H - 8) + 'px',
    };
}

// ─────────────────────────────────────────────────────────────────
//  マウスX → 分変換
// ─────────────────────────────────────────────────────────────────
function clientXToMins(clientX) {
    if (!timelineAreaRef.value) return 0;
    const rect = timelineAreaRef.value.getBoundingClientRect();
    const relX = clientX - rect.left;
    return clampMins(snapMins(relX / timelineW.value * TOTAL_MINS));
}

// ─────────────────────────────────────────────────────────────────
//  ドラッグ開始（既存ブロック）
// ─────────────────────────────────────────────────────────────────
function onBlockMouseDown(e, schedule, type) {
    e.preventDefault();
    e.stopPropagation();

    const rowIndex = props.members.findIndex(m => m.id === schedule.user_id);
    drag.value = {
        scheduleId:      schedule.id,
        type,
        origStartMin:    isoToMinutes(schedule.starts_at),
        origEndMin:      isoToMinutes(schedule.ends_at),
        origUserId:      schedule.user_id,
        currentUserId:   schedule.user_id,
        previewStartMin: isoToMinutes(schedule.starts_at),
        previewEndMin:   isoToMinutes(schedule.ends_at),
        startClientX:    e.clientX,
        startClientY:    e.clientY,
        rowIndex,
    };
}

// ─────────────────────────────────────────────────────────────────
//  空きエリア mousedown → 新規選択
// ─────────────────────────────────────────────────────────────────
function onTimelineMouseDown(e, member) {
    if (e.button !== 0) return;
    if (e.target.closest('.schedule-block')) return;
    const mins = clientXToMins(e.clientX);
    selecting.value = {
        memberId:     member.id,
        startMin:     mins,
        endMin:       mins,
        startClientX: e.clientX,
    };
}

// ─────────────────────────────────────────────────────────────────
//  マウス移動（ドラッグ / リサイズ / 選択）
// ─────────────────────────────────────────────────────────────────
function onMouseMove(e) {
    if (drag.value) {
        const dx = e.clientX - drag.value.startClientX;
        const deltaMins = snapMins(dx / pxPerMin.value);
        const duration = drag.value.origEndMin - drag.value.origStartMin;

        if (drag.value.type === 'move') {
            let newStart = clampMins(snapMins(drag.value.origStartMin + deltaMins));
            let newEnd   = newStart + duration;
            if (newEnd > TOTAL_MINS) { newEnd = TOTAL_MINS; newStart = newEnd - duration; }
            drag.value.previewStartMin = newStart;
            drag.value.previewEndMin   = newEnd;

            // メンバー行の切り替え
            if (timelineAreaRef.value) {
                const containerEl = timelineAreaRef.value.closest('.timeline-wrapper');
                if (containerEl) {
                    const rows = containerEl.querySelectorAll('.member-row');
                    for (let i = 0; i < rows.length; i++) {
                        const rect = rows[i].getBoundingClientRect();
                        if (e.clientY >= rect.top && e.clientY <= rect.bottom) {
                            drag.value.currentUserId = props.members[i]?.id ?? drag.value.origUserId;
                            drag.value.rowIndex = i;
                            break;
                        }
                    }
                }
            }
        } else if (drag.value.type === 'resize') {
            const newEnd = clampMins(snapMins(drag.value.origEndMin + deltaMins));
            drag.value.previewEndMin = Math.max(drag.value.previewStartMin + SNAP, newEnd);
        }
    }

    if (selecting.value) {
        selecting.value.endMin = clientXToMins(e.clientX);
    }
}

// ─────────────────────────────────────────────────────────────────
//  マウスアップ（コミット）
// ─────────────────────────────────────────────────────────────────
async function onMouseUp() {
    if (drag.value) {
        const d = drag.value;
        drag.value = null;

        const schedule = localSchedules.value.find(s => s.id === d.scheduleId);
        if (!schedule) return;

        const newStartsAt = minutesToIso(currentDate.value, d.previewStartMin);
        const newEndsAt   = minutesToIso(currentDate.value, d.previewEndMin);
        const newUserId   = d.currentUserId;

        // ローカル即時反映
        schedule.starts_at = newStartsAt;
        schedule.ends_at   = newEndsAt;
        schedule.user_id   = newUserId;

        // API保存
        await apiPut(route('proof_coordinator.schedules.update', { proofSchedule: d.scheduleId }), {
            starts_at: newStartsAt,
            ends_at:   newEndsAt,
            user_id:   newUserId,
        });
    }

    if (selecting.value) {
        const sel = selecting.value;
        selecting.value = null;

        const s = Math.min(sel.startMin, sel.endMin);
        const e = Math.max(sel.startMin, sel.endMin);
        if (e - s < SNAP) return; // 短すぎるクリックは無視

        assignModalData.value = {
            memberId: sel.memberId,
            startMin: s,
            endMin:   e,
        };
        showAssignModal.value = true;
    }
}

// ─────────────────────────────────────────────────────────────────
//  ブロッククリック（詳細モーダル）
// ─────────────────────────────────────────────────────────────────
function onBlockClick(e, schedule) {
    if (drag.value) return; // ドラッグ直後は無視
    detailSchedule.value = { ...schedule };
    showDetailModal.value = true;
}

// ─────────────────────────────────────────────────────────────────
//  スケジュール作成（モーダルから）
// ─────────────────────────────────────────────────────────────────
const selectedRequestId = ref(null);

async function createSchedule() {
    if (!selectedRequestId.value) return;
    const d = assignModalData.value;
    const body = {
        proof_request_id: selectedRequestId.value,
        user_id:          d.memberId,
        starts_at:        minutesToIso(currentDate.value, d.startMin),
        ends_at:          minutesToIso(currentDate.value, d.endMin),
    };
    try {
        const result = await apiPost(route('proof_coordinator.schedules.store'), body);
        localSchedules.value.push({ ...result });
        showAssignModal.value = false;
        selectedRequestId.value = null;
    } catch (err) {
        alert('登録に失敗しました: ' + err.message);
    }
}

// ─────────────────────────────────────────────────────────────────
//  スケジュール削除（詳細モーダルから）
// ─────────────────────────────────────────────────────────────────
async function deleteSchedule() {
    if (!detailSchedule.value) return;
    if (!confirm('このスケジュールを削除しますか？')) return;
    try {
        await apiDelete(route('proof_coordinator.schedules.destroy', { proofSchedule: detailSchedule.value.id }));
        localSchedules.value = localSchedules.value.filter(s => s.id !== detailSchedule.value.id);
        showDetailModal.value = false;
        detailSchedule.value = null;
    } catch (err) {
        alert('削除に失敗しました: ' + err.message);
    }
}

// ─────────────────────────────────────────────────────────────────
//  スケジュール完了
// ─────────────────────────────────────────────────────────────────
async function completeSchedule() {
    if (!detailSchedule.value) return;
    if (!confirm('この校正を完了にしますか？依頼者に通知されます。')) return;
    const proofRequestId = detailSchedule.value.proof_request_id;
    try {
        await apiPut(route('proof_coordinator.assignments.complete', { proofRequest: proofRequestId }), {});
        // ローカルの全スケジュールブロックを更新
        localSchedules.value.forEach(s => {
            if (s.proof_request_id === proofRequestId) s.status = 'completed';
        });
        detailSchedule.value = { ...detailSchedule.value, status: 'completed' };
    } catch (err) {
        alert('完了処理に失敗しました: ' + err.message);
    }
}

function goToDetail() {
    if (!detailSchedule.value) return;
    router.get(route('proof_coordinator.assignments.show', { proofRequest: detailSchedule.value.proof_request_id }));
}

// ─────────────────────────────────────────────────────────────────
//  日付ナビゲーション
// ─────────────────────────────────────────────────────────────────
async function changeDate(newDate) {
    currentDate.value = newDate;
    try {
        const data = await apiFetch(route('proof_coordinator.calendar.data') + '?date=' + newDate);
        localSchedules.value = data.schedules.map(s => ({ ...s }));
        unassigned.value     = data.unassigned.map(u => ({ ...u }));
    } catch (err) {
        console.error('Failed to load schedule data', err);
    }
}

function addDays(dateStr, days) {
    const [y, m, d] = dateStr.split('-').map(Number);
    const date = new Date(y, m - 1, d + days);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function prevDay() {
    changeDate(addDays(currentDate.value, -1));
}

function nextDay() {
    changeDate(addDays(currentDate.value, 1));
}

function onDatePickerChange(e) {
    if (e.target.value) changeDate(e.target.value);
}

// ─────────────────────────────────────────────────────────────────
//  未配分一覧フィルタ（「登録済み非表示」チェック）
// ─────────────────────────────────────────────────────────────────
const scheduledRequestIds = computed(() => new Set(localSchedules.value.map(s => s.proof_request_id)));

const filteredUnassigned = computed(() => {
    if (!hideScheduled.value) return unassigned.value;
    return unassigned.value.filter(r => !scheduledRequestIds.value.has(r.id));
});

// ─────────────────────────────────────────────────────────────────
//  締め切りフォーマット
// ─────────────────────────────────────────────────────────────────
function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        month: 'numeric', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.month}/${p.day} ${p.hour}:${p.minute}`;
}

// ─────────────────────────────────────────────────────────────────
//  API ユーティリティ
// ─────────────────────────────────────────────────────────────────
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function apiFetch(url) {
    const res = await fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

async function apiPost(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

async function apiPut(url, body) {
    const res = await fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

async function apiDelete(url) {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

// ─────────────────────────────────────────────────────────────────
//  ライフサイクル
// ─────────────────────────────────────────────────────────────────
let resizeObserver = null;

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);

    nextTick(() => {
        if (timelineAreaRef.value) {
            resizeObserver = new ResizeObserver(entries => {
                for (const entry of entries) {
                    timelineW.value = entry.contentRect.width;
                }
            });
            resizeObserver.observe(timelineAreaRef.value);
            timelineW.value = timelineAreaRef.value.clientWidth;
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
    resizeObserver?.disconnect();
});
</script>

<template>
    <AppLayout title="校正カレンダー">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正カレンダー</h2>
        </template>
        <template #tabs>
            <ProofCoordinatorNavigationTabs active="calendar" />
        </template>

        <!-- max-w-7xl を突き破って全幅使用 -->
        <div class="-mx-4 sm:-mx-6 lg:-mx-8">

            <!-- ─── ツールバー ──────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-3 bg-white px-4 py-3 shadow-sm sm:px-6 lg:px-8">
                <!-- 表示切り替え -->
                <div class="flex overflow-hidden rounded border border-gray-300 text-sm">
                    <button
                        @click="viewMode = 'day'"
                        :class="viewMode === 'day' ? 'bg-pink-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-1.5 font-medium transition-colors">
                        日ごと
                    </button>
                    <button
                        @click="viewMode = 'month'"
                        :class="viewMode === 'month' ? 'bg-pink-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="border-l border-gray-300 px-4 py-1.5 font-medium transition-colors">
                        月ごと
                    </button>
                </div>

                <!-- 日付ナビ（日ごとのみ） -->
                <template v-if="viewMode === 'day'">
                    <button @click="prevDay"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">
                        ◀ 前日
                    </button>
                    <span class="min-w-[180px] text-center text-sm font-semibold text-gray-800">
                        {{ displayDate }}
                    </span>
                    <button @click="nextDay"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">
                        翌日 ▶
                    </button>
                    <input
                        type="date"
                        :value="currentDate"
                        @change="onDatePickerChange"
                        class="rounded border-gray-300 text-sm"
                    />
                    <div class="ml-auto flex items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-1.5 text-sm text-gray-600">
                            <input type="checkbox" v-model="hideScheduled" class="rounded border-gray-300" />
                            登録済みを非表示
                        </label>
                    </div>
                </template>
            </div>

            <!-- ─── 月ビュー ──────────────────────────────────────── -->
            <div v-if="viewMode === 'month'" class="bg-white px-4 pb-6 pt-4 sm:px-6 lg:px-8">
                <!-- 凡例 -->
                <div class="mb-4 flex flex-wrap gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-gray-400"></span>依頼中</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-blue-500"></span>割り当て済み</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-orange-500"></span>校正中</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-green-500"></span>完了</span>
                </div>
                <FullCalendar :options="calendarOptions" />
            </div>

            <!-- ─── タイムライン（日ごと） ────────────────────────── -->
            <div v-if="viewMode === 'day'" class="timeline-wrapper overflow-x-auto" style="user-select: none;">
                <div :style="{ minWidth: (MEMBER_W + 700) + 'px' }">

                    <!-- 時刻ヘッダー -->
                    <div class="flex border-b border-gray-200 bg-gray-50"
                         :style="{ height: HEADER_H + 'px' }">
                        <!-- 左カラム（空） -->
                        <div class="flex-shrink-0 border-r border-gray-200 bg-gray-50"
                             :style="{ width: MEMBER_W + 'px' }">
                        </div>
                        <!-- 時刻目盛り -->
                        <div class="relative flex-1" ref="timelineAreaRef">
                            <div v-for="h in hours" :key="h"
                                 class="absolute top-0 flex h-full items-center"
                                 :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }">
                                <span class="pl-1 text-xs text-gray-500 whitespace-nowrap">{{ h }}:00</span>
                                <!-- 縦グリッド線（ヘッダー） -->
                                <div v-if="h < END_HOUR"
                                     class="absolute top-0 h-full w-px bg-gray-200"
                                     :style="{ left: '0' }">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- メンバー行 -->
                    <div v-for="(member, idx) in members" :key="member.id"
                         class="member-row flex border-b border-gray-100"
                         :style="{ height: ROW_H + 'px' }"
                         :class="drag && drag.currentUserId === member.id ? 'bg-pink-50' : idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'">

                        <!-- メンバー名（sticky left） -->
                        <div class="sticky left-0 z-10 flex flex-shrink-0 items-center border-r border-gray-200 px-3"
                             :style="{ width: MEMBER_W + 'px' }"
                             :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                            <span class="truncate text-sm font-medium text-gray-700">{{ member.name }}</span>
                        </div>

                        <!-- タイムライン領域 -->
                        <div class="relative flex-1 cursor-crosshair"
                             @mousedown="onTimelineMouseDown($event, member)">

                            <!-- グリッド縦線 -->
                            <div v-for="h in filteredHoursForLines" :key="h"
                                 class="pointer-events-none absolute top-0 h-full w-px bg-gray-100"
                                 :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }">
                            </div>

                            <!-- 30分グリッド -->
                            <div v-for="h in filteredHoursFor30Mins" :key="'h30-' + h"
                                 class="pointer-events-none absolute top-0 h-full w-px bg-gray-100/60"
                                 :style="{ left: (((h - START_HOUR) * 60 + 30) / TOTAL_MINS * 100) + '%' }">
                            </div>

                            <!-- 既存スケジュールブロック -->
                            <div v-for="schedule in schedulesForMember(member.id)"
                                 :key="schedule.id"
                                 class="schedule-block absolute z-10 flex cursor-grab flex-col overflow-hidden rounded border px-1.5 py-0.5 shadow-sm active:cursor-grabbing"
                                 :class="[blockColor(schedule), drag && drag.scheduleId === schedule.id ? 'opacity-30' : '']"
                                 :style="blockStyle(schedule)"
                                 @mousedown.stop="onBlockMouseDown($event, schedule, 'move')"
                                 @click.stop="onBlockClick($event, schedule)">
                                <span class="flex items-center gap-1 truncate text-xs font-semibold leading-tight">
                                    <span v-if="schedule.status === 'completed'"
                                          class="inline-flex shrink-0 items-center rounded-full bg-yellow-400 px-1.5 py-0.5 text-xs font-bold leading-none text-white">
                                        ✓
                                    </span>
                                    <span class="truncate">{{ schedule.title }}</span>
                                </span>
                                <span v-if="schedule.job_title" class="truncate text-xs leading-tight opacity-75">{{ schedule.job_title }}</span>
                                <!-- リサイズハンドル -->
                                <div class="absolute right-0 top-0 h-full w-2 cursor-ew-resize"
                                     @mousedown.stop="onBlockMouseDown($event, schedule, 'resize')">
                                </div>
                            </div>

                            <!-- ドラッグ中プレビュー（このメンバー行が対象のとき） -->
                            <div v-if="drag && drag.currentUserId === member.id"
                                 class="pointer-events-none absolute z-20 rounded border-2 border-pink-400 bg-pink-100 opacity-80"
                                 :style="previewStyle(drag)">
                                <span class="px-1 text-xs font-semibold text-pink-800">
                                    {{ minsToTimeStr(drag.previewStartMin) }}–{{ minsToTimeStr(drag.previewEndMin) }}
                                </span>
                            </div>

                            <!-- 新規選択プレビュー -->
                            <div v-if="selecting && selecting.memberId === member.id"
                                 class="pointer-events-none absolute z-20 rounded border-2 border-blue-400 bg-blue-50 opacity-70"
                                 :style="selectionStyle(selecting)">
                                <span class="px-1 text-xs text-blue-700">
                                    {{ minsToTimeStr(Math.min(selecting.startMin, selecting.endMin)) }}–
                                    {{ minsToTimeStr(Math.max(selecting.startMin, selecting.endMin)) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- メンバーが0人の場合 -->
                    <div v-if="members.length === 0"
                         class="flex items-center justify-center py-16 text-gray-400">
                        校正チームにメンバーが登録されていません。
                    </div>

                </div>
            </div><!-- /timeline-wrapper -->

        </div><!-- /-mx -->

        <!-- ─── 校正依頼割り当てモーダル ──────────────────────────── -->
        <Teleport to="body">
            <div v-if="showAssignModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800">校正依頼を割り当て</h3>

                    <div class="mb-3 text-sm text-gray-600">
                        <span class="font-medium">担当:</span>
                        {{ members.find(m => m.id === assignModalData.memberId)?.name ?? '—' }}
                        &nbsp;|&nbsp;
                        <span class="font-medium">時間:</span>
                        {{ minsToTimeStr(assignModalData.startMin) }}–{{ minsToTimeStr(assignModalData.endMin) }}
                    </div>

                    <!-- 未配分一覧 -->
                    <div class="mb-4 max-h-72 overflow-y-auto rounded border border-gray-200">
                        <div v-if="filteredUnassigned.length === 0"
                             class="p-4 text-center text-sm text-gray-400">
                            割り当て可能な校正依頼がありません。
                        </div>
                        <label
                            v-for="req in filteredUnassigned"
                            :key="req.id"
                            class="flex cursor-pointer items-start gap-3 border-b border-gray-100 p-3 hover:bg-pink-50"
                            :class="selectedRequestId === req.id ? 'bg-pink-50' : ''"
                        >
                            <input
                                type="radio"
                                :value="req.id"
                                v-model="selectedRequestId"
                                class="mt-0.5 accent-pink-600"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800">{{ req.title }}</p>
                                <p v-if="req.job_title" class="text-xs text-gray-500">{{ req.job_title }}</p>
                                <p class="text-xs text-gray-400">
                                    締め切り: {{ fmtDeadline(req.deadline) }}
                                    <span v-if="scheduledRequestIds.has(req.id)"
                                          class="ml-2 rounded bg-blue-100 px-1 py-0.5 text-xs text-blue-700">
                                        登録済み
                                    </span>
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            @click="showAssignModal = false; selectedRequestId = null"
                            class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            キャンセル
                        </button>
                        <button
                            @click="createSchedule"
                            :disabled="!selectedRequestId"
                            class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            割り当てる
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── 詳細モーダル ──────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showDetailModal && detailSchedule"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                 @click.self="showDetailModal = false">
                <div class="relative w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-3 text-base font-semibold text-gray-800">{{ detailSchedule.title }}</h3>
                    <dl class="space-y-1.5 text-sm">
                        <div v-if="detailSchedule.job_title">
                            <dt class="text-xs text-gray-400">案件</dt>
                            <dd class="text-gray-700">{{ detailSchedule.job_title }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">担当</dt>
                            <dd class="text-gray-700">{{ detailSchedule.user_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">時間</dt>
                            <dd class="text-gray-700">
                                {{ minsToTimeStr(isoToMinutes(detailSchedule.starts_at)) }}–{{ minsToTimeStr(isoToMinutes(detailSchedule.ends_at)) }}
                            </dd>
                        </div>
                        <div v-if="detailSchedule.deadline">
                            <dt class="text-xs text-gray-400">校正締め切り</dt>
                            <dd class="text-gray-700">{{ fmtDeadline(detailSchedule.deadline) }}</dd>
                        </div>
                    </dl>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                        <button
                            @click="deleteSchedule"
                            class="rounded bg-red-50 px-3 py-1.5 text-sm text-red-600 hover:bg-red-100">
                            削除
                        </button>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="detailSchedule.status !== 'completed'"
                                @click="completeSchedule"
                                class="rounded bg-yellow-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-600">
                                完了にする
                            </button>
                            <span
                                v-else
                                class="rounded bg-yellow-100 px-3 py-1.5 text-sm font-medium text-yellow-700">
                                完了済み
                            </span>
                            <button
                                @click="goToDetail"
                                class="rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700">
                                詳細
                            </button>
                            <button
                                @click="showDetailModal = false"
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                閉じる
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
