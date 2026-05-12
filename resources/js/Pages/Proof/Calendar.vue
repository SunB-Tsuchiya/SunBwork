<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import jaLocale from '@fullcalendar/core/locales/ja';

// ─────────────────────────────────────────────────────────────────
//  Props
// ─────────────────────────────────────────────────────────────────
const props = defineProps({
    events:          { type: Array,  default: () => [] },   // 月ビュー用
    dailySchedules:  { type: Array,  default: () => [] },   // 日ビュー初期データ
    currentDate:     { type: String, default: '' },
    currentUser:     { type: Object, default: () => ({}) },
});

// ─────────────────────────────────────────────────────────────────
//  表示モード
// ─────────────────────────────────────────────────────────────────
const viewMode = ref('month'); // 'month' | 'day'

// ─────────────────────────────────────────────────────────────────
//  月ビュー（FullCalendar）
// ─────────────────────────────────────────────────────────────────
const STATUS_COLORS_HEX = {
    pending:     '#9ca3af',
    assigned:    '#3b82f6',
    in_progress: '#f97316',
    completed:   '#22c55e',
};

const calendarEvents = computed(() =>
    props.events.map(e => ({
        id:    String(e.id),
        title: e.title,
        start: e.start,
        color: STATUS_COLORS_HEX[e.status] ?? '#9ca3af',
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
}));

// ─────────────────────────────────────────────────────────────────
//  日ビュー（読み取り専用タイムライン）
// ─────────────────────────────────────────────────────────────────
const START_HOUR = 8;
const END_HOUR   = 18;
const TOTAL_MINS = (END_HOUR - START_HOUR) * 60;
const ROW_H      = 64;
const HEADER_H   = 40;

const currentDate    = ref(props.currentDate || new Date().toISOString().slice(0, 10));
const localSchedules = ref(props.dailySchedules.map(s => ({ ...s })));

const timelineAreaRef = ref(null);
const timelineW       = ref(1200);

const hours = computed(() =>
    Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i)
);

const filteredHoursForLines = computed(() =>
    hours.value.filter(h => h > START_HOUR)
);

const filteredHoursFor30Mins = computed(() =>
    hours.value.filter(h => h < END_HOUR)
);

const displayDate = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    const days = ['日', '月', '火', '水', '木', '金', '土'];
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${days[d.getDay()]}）`;
});

function isoToMinutes(isoStr) {
    const d = new Date(isoStr);
    const jstTotalMin = d.getUTCHours() * 60 + d.getUTCMinutes() + 9 * 60;
    return jstTotalMin - START_HOUR * 60;
}

function minsToTimeStr(mins) {
    const total = START_HOUR * 60 + mins;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

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
        left:   left + '%',
        width:  width + '%',
        top:    '4px',
        height: (ROW_H - 8) + 'px',
    };
}

function blockColor(schedule) {
    return STATUS_COLORS[schedule.status] ?? STATUS_COLORS.pending;
}

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

// 詳細ポップアップ
const detailSchedule = ref(null);
const showDetail     = ref(false);

function onBlockClick(schedule) {
    detailSchedule.value = { ...schedule };
    showDetail.value = true;
}

// 日付ナビゲーション
async function changeDate(newDate) {
    currentDate.value = newDate;
    try {
        const res = await fetch(route('proof.calendar.data') + '?date=' + newDate, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error();
        const data = await res.json();
        localSchedules.value = data.schedules.map(s => ({ ...s }));
    } catch (err) {
        console.error('Failed to load schedule data', err);
    }
}

function prevDay() {
    const d = new Date(currentDate.value + 'T00:00:00');
    d.setDate(d.getDate() - 1);
    changeDate(d.toISOString().slice(0, 10));
}

function nextDay() {
    const d = new Date(currentDate.value + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    changeDate(d.toISOString().slice(0, 10));
}

// ResizeObserver
let resizeObserver = null;

onMounted(() => {
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
    resizeObserver?.disconnect();
});
</script>

<template>
    <AppLayout title="校正カレンダー">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正カレンダー</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- ─── 表示切り替えボタン ────────────────────────────── -->
            <div class="mb-4 flex items-center justify-between">
                <div class="flex overflow-hidden rounded border border-gray-300 text-sm">
                    <button
                        @click="viewMode = 'month'"
                        :class="viewMode === 'month' ? 'bg-pink-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-1.5 font-medium transition-colors">
                        月ごと
                    </button>
                    <button
                        @click="viewMode = 'day'"
                        :class="viewMode === 'day' ? 'bg-pink-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="border-l border-gray-300 px-4 py-1.5 font-medium transition-colors">
                        日ごと
                    </button>
                </div>

                <!-- 凡例 -->
                <div class="flex flex-wrap gap-3 text-xs text-gray-600">
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-gray-400"></span>依頼中</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-blue-500"></span>割り当て済み</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-orange-500"></span>校正中</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-green-500"></span>完了</span>
                </div>
            </div>

            <!-- ─── 月ビュー ──────────────────────────────────────── -->
            <FullCalendar v-if="viewMode === 'month'" :options="calendarOptions" />

            <!-- ─── 日ビュー（読み取り専用タイムライン） ─────────── -->
            <template v-else>
                <!-- 日付ナビ -->
                <div class="mb-3 flex flex-wrap items-center gap-3">
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
                        @change="e => e.target.value && changeDate(e.target.value)"
                        class="rounded border-gray-300 text-sm"
                    />
                </div>

                <!-- タイムライン -->
                <div class="overflow-x-auto" style="user-select: none;">
                    <div :style="{ minWidth: '700px' }">

                        <!-- 時刻ヘッダー -->
                        <div class="flex border-b border-gray-200 bg-gray-50"
                             :style="{ height: HEADER_H + 'px' }">
                            <div class="flex-shrink-0 border-r border-gray-200 bg-gray-50" style="width: 120px;"></div>
                            <div class="relative flex-1" ref="timelineAreaRef">
                                <div v-for="h in hours" :key="h"
                                     class="absolute top-0 flex h-full items-center"
                                     :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }">
                                    <span class="pl-1 text-xs text-gray-500 whitespace-nowrap">{{ h }}:00</span>
                                </div>
                            </div>
                        </div>

                        <!-- ユーザー行 -->
                        <div class="flex border-b border-gray-100 bg-white"
                             :style="{ height: ROW_H + 'px' }">
                            <!-- ユーザー名 -->
                            <div class="sticky left-0 z-10 flex flex-shrink-0 items-center border-r border-gray-200 bg-white px-3"
                                 style="width: 120px;">
                                <span class="truncate text-sm font-medium text-gray-700">
                                    {{ currentUser.name ?? '自分' }}
                                </span>
                            </div>

                            <!-- タイムライン領域 -->
                            <div class="relative flex-1">
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

                                <!-- スケジュールブロック（クリックで詳細） -->
                                <div v-for="schedule in localSchedules"
                                     :key="schedule.id"
                                     class="absolute z-10 flex cursor-pointer flex-col overflow-hidden rounded border px-1.5 py-0.5 shadow-sm"
                                     :class="blockColor(schedule)"
                                     :style="blockStyle(schedule)"
                                     @click="onBlockClick(schedule)">
                                    <span class="flex items-center gap-1 truncate text-xs font-semibold leading-tight">
                                        <span v-if="schedule.status === 'completed'"
                                              class="inline-flex shrink-0 items-center rounded-full bg-yellow-400 px-1.5 py-0.5 text-xs font-bold leading-none text-white">
                                            ✓
                                        </span>
                                        <span class="truncate">{{ schedule.title }}</span>
                                    </span>
                                    <span v-if="schedule.job_title" class="truncate text-xs leading-tight opacity-75">{{ schedule.job_title }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- スケジュールなし -->
                        <div v-if="localSchedules.length === 0"
                             class="flex items-center justify-center py-12 text-sm text-gray-400">
                            この日に割り当てられた校正はありません。
                        </div>

                    </div>
                </div>

                <!-- 詳細ポップアップ -->
                <Teleport to="body">
                    <div v-if="showDetail && detailSchedule"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                         @click.self="showDetail = false">
                        <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                            <h3 class="mb-3 text-base font-semibold text-gray-800">{{ detailSchedule.title }}</h3>
                            <dl class="space-y-1.5 text-sm">
                                <div v-if="detailSchedule.job_title">
                                    <dt class="text-xs text-gray-400">案件</dt>
                                    <dd class="text-gray-700">{{ detailSchedule.job_title }}</dd>
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
                            <div class="mt-5 flex justify-end">
                                <button @click="showDetail = false"
                                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    閉じる
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>
            </template>

        </div>
    </AppLayout>
</template>
