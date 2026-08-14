<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

// ─────────────────────────────────────────────────────────
//  Props
// ─────────────────────────────────────────────────────────
const props = defineProps({
    job:               { type: Object, default: () => ({}) },
    subCoordinatorIds: { type: Array,  default: () => [] },
    members:           { type: Array,  default: () => [] },
    events:            { type: Array,  default: () => [] },
    date:              { type: String, default: '' },
});

const page = usePage();

// ─────────────────────────────────────────────────────────
//  定数
// ─────────────────────────────────────────────────────────
const START_HOUR  = 8;
const END_HOUR    = 20;
const TOTAL_MINS  = (END_HOUR - START_HOUR) * 60;  // 720
const MEMBER_W    = 140;  // px（左カラム固定幅）
const ROW_H       = 64;   // px（メンバー行の高さ）
const HEADER_H    = 36;   // px（時刻ヘッダーの高さ）

// ─────────────────────────────────────────────────────────
//  State
// ─────────────────────────────────────────────────────────
// toISOString() は UTC を返すため JST 00:00〜08:59 に前日になる。ローカル日付を使う
const currentDate  = ref(props.date || new Date().toLocaleDateString('sv-SE'));
const localEvents  = ref(props.events.map(e => ({ ...e })));

const timelineAreaRef = ref(null);
const timelineW       = ref(1200);

// 詳細ポップオーバー
const detailEvent = ref(null);
const detailStyle = ref({});

// ─────────────────────────────────────────────────────────
//  Computed
// ─────────────────────────────────────────────────────────
const hours = computed(() =>
    Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i)
);

const gridHours = computed(() =>
    hours.value.filter(h => h > START_HOUR && h < END_HOUR)
);

const displayDate = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    const days = ['日', '月', '火', '水', '木', '金', '土'];
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${days[d.getDay()]}）`;
});

function eventsForMember(userId) {
    return localEvents.value.filter(e => e.user_id === userId);
}

// ─────────────────────────────────────────────────────────
//  日付変換
// ─────────────────────────────────────────────────────────
/** ISO文字列 → JSTでの分（START_HOUR起点）*/
function isoToMinutes(isoStr) {
    if (!isoStr) return 0;
    const d = new Date(isoStr);
    const jstTotalMin = d.getUTCHours() * 60 + d.getUTCMinutes() + 9 * 60;
    return jstTotalMin - START_HOUR * 60;
}

function minsToTimeStr(mins) {
    const total = START_HOUR * 60 + Math.max(0, mins);
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

// ─────────────────────────────────────────────────────────
//  ブロックスタイル
// ─────────────────────────────────────────────────────────
function blockStyle(ev) {
    const startMin = isoToMinutes(ev.starts_at);
    const endMin   = isoToMinutes(ev.ends_at);
    const clampedStart = Math.max(0, startMin);
    const clampedEnd   = Math.min(TOTAL_MINS, endMin);
    const left  = clampedStart / TOTAL_MINS * 100;
    const width = Math.max(0.5, (clampedEnd - clampedStart) / TOTAL_MINS * 100);
    return {
        left:             left + '%',
        width:            width + '%',
        top:              '4px',
        height:           (ROW_H - 8) + 'px',
        backgroundColor:  ev.color + '33', // 20% opacity background
        borderLeftColor:  ev.color,
        borderLeftWidth:  '3px',
        borderLeftStyle:  'solid',
        borderRadius:     '3px',
        cursor:           ev.related ? 'pointer' : 'default',
    };
}

// ─────────────────────────────────────────────────────────
//  詳細ポップオーバー（関連イベントのみ）
// ─────────────────────────────────────────────────────────
function onBlockClick(ev, domEvent) {
    if (!ev.related) return;
    if (detailEvent.value && detailEvent.value.id === ev.id) {
        detailEvent.value = null;
        return;
    }
    detailEvent.value = ev;
    nextTick(() => {
        const rect = domEvent.currentTarget.getBoundingClientRect();
        const top  = rect.bottom + window.scrollY + 4;
        let   left = rect.left + window.scrollX;
        // 画面右端をはみ出さないよう調整
        if (left + 260 > window.innerWidth) left = window.innerWidth - 264;
        detailStyle.value = { top: top + 'px', left: left + 'px' };
    });
}

function closeDetail() {
    detailEvent.value = null;
}

// ─────────────────────────────────────────────────────────
//  日付ナビゲーション
// ─────────────────────────────────────────────────────────
async function changeDate(newDate) {
    currentDate.value = newDate;
    try {
        const res = await fetch(
            route('coordinator.project_jobs.member_schedule.data', { projectJob: props.job.id })
            + '?date=' + newDate,
            { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }
        );
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        localEvents.value = data.events.map(e => ({ ...e }));
    } catch (err) {
        console.error('Failed to load event data', err);
    }
    closeDetail();
}

function addDays(dateStr, days) {
    const [y, m, d] = dateStr.split('-').map(Number);
    const date = new Date(y, m - 1, d + days);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function prevDay() { changeDate(addDays(currentDate.value, -1)); }
function nextDay() { changeDate(addDays(currentDate.value, +1)); }
function onDatePickerChange(e) { if (e.target.value) changeDate(e.target.value); }

// ─────────────────────────────────────────────────────────
//  ナビゲーション
// ─────────────────────────────────────────────────────────
function goBack() {
    router.visit(route('coordinator.project_jobs.show', { projectJob: props.job.id }));
}

// ─────────────────────────────────────────────────────────
//  ライフサイクル
// ─────────────────────────────────────────────────────────
let resizeObserver = null;

function onDocumentClick(e) {
    if (detailEvent.value && !e.target.closest('.event-block') && !e.target.closest('.detail-popover')) {
        closeDetail();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
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
    document.removeEventListener('click', onDocumentClick);
    resizeObserver?.disconnect();
});
</script>

<template>
    <AppLayout :title="`メンバー予定表 — ${job.title}`">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                メンバー予定表
            </h2>
        </template>

        <div class="rounded bg-white shadow">

            <!-- ── ツールバー ──────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 px-5 py-3">
                <button
                    type="button"
                    class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    @click="goBack"
                >← 案件に戻る</button>

                <span class="text-sm font-semibold text-gray-700">{{ job.title }}</span>

                <div class="ml-auto flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50"
                        @click="prevDay"
                    >◀ 前日</button>
                    <span class="min-w-[180px] text-center text-sm font-semibold text-gray-800">
                        {{ displayDate }}
                    </span>
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50"
                        @click="nextDay"
                    >翌日 ▶</button>
                    <input
                        type="date"
                        :value="currentDate"
                        @change="onDatePickerChange"
                        class="rounded border-gray-300 text-sm"
                    />
                </div>
            </div>

            <!-- ── 凡例 ────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 px-5 py-2 text-xs text-gray-600">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm border-l-4 border-l-[#1fb6b3] bg-[#1fb6b3]/20"></span>予定
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm border-l-4 border-l-[#4F46E5] bg-[#4F46E5]/20"></span>独自ジョブ
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm border-l-4 border-l-[#7C3AED] bg-[#7C3AED]/20"></span>進行表のジョブ
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm border-l-4 border-l-[#059669] bg-[#059669]/20"></span>割り当てジョブ
                </span>
                <span class="ml-2 text-gray-400">※ この案件のジョブはクリックで詳細表示</span>
            </div>

            <!-- ── タイムライン ───────────────────────────────── -->
            <div class="overflow-x-auto" style="user-select: none;">
                <div :style="{ minWidth: (MEMBER_W + 600) + 'px' }">

                    <!-- 時刻ヘッダー -->
                    <div
                        class="flex border-b border-gray-200 bg-gray-50"
                        :style="{ height: HEADER_H + 'px' }"
                    >
                        <div
                            class="flex-shrink-0 border-r border-gray-200"
                            :style="{ width: MEMBER_W + 'px' }"
                        ></div>
                        <div class="relative flex-1" ref="timelineAreaRef">
                            <div
                                v-for="h in hours"
                                :key="h"
                                class="absolute top-0 flex h-full items-center"
                                :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }"
                            >
                                <span class="pl-1 text-xs text-gray-500">{{ h }}:00</span>
                            </div>
                        </div>
                    </div>

                    <!-- メンバー行 -->
                    <div
                        v-for="(member, idx) in members"
                        :key="member.id"
                        class="flex border-b border-gray-100"
                        :style="{ height: ROW_H + 'px' }"
                        :class="[
                            member.role === 'leader'      ? 'bg-yellow-50/60' :
                            member.role === 'sub_leader'  ? 'bg-orange-50/60' :
                            member.role === 'coordinator' ? 'bg-indigo-50/60' :
                            idx % 2 === 0                ? 'bg-white'         : 'bg-gray-50/50'
                        ]"
                    >
                        <!-- メンバー名 + バッジ -->
                        <div
                            class="sticky left-0 z-10 flex flex-shrink-0 flex-col justify-center gap-0.5 border-r border-gray-200 px-2"
                            :style="{ width: MEMBER_W + 'px' }"
                            :class="[
                                member.role === 'leader'      ? 'bg-yellow-50' :
                                member.role === 'sub_leader'  ? 'bg-orange-50' :
                                member.role === 'coordinator' ? 'bg-indigo-50' :
                                idx % 2 === 0                ? 'bg-white'      : 'bg-gray-50'
                            ]"
                        >
                            <!-- ロールバッジ -->
                            <span
                                v-if="member.role === 'leader'"
                                class="self-start rounded px-1 py-0.5 text-[10px] font-bold leading-none text-yellow-700 bg-yellow-200"
                            >リーダー</span>
                            <span
                                v-else-if="member.role === 'sub_leader'"
                                class="self-start rounded px-1 py-0.5 text-[10px] font-bold leading-none text-orange-700 bg-orange-200"
                            >サブリーダー</span>
                            <span
                                v-else-if="member.role === 'coordinator'"
                                class="self-start rounded px-1 py-0.5 text-[10px] font-bold leading-none text-indigo-700 bg-indigo-200"
                            >Coordinator</span>
                            <span
                                v-else
                                class="self-start rounded px-1 py-0.5 text-[10px] font-bold leading-none text-gray-600 bg-gray-200"
                            >User</span>
                            <span class="truncate text-sm font-medium text-gray-700">{{ member.name }}</span>
                        </div>

                        <!-- タイムライン領域 -->
                        <div class="relative flex-1">
                            <!-- グリッド縦線 -->
                            <div
                                v-for="h in gridHours"
                                :key="h"
                                class="pointer-events-none absolute top-0 h-full w-px bg-gray-100"
                                :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }"
                            ></div>

                            <!-- 30分グリッド -->
                            <div
                                v-for="h in hours.filter(hh => hh < END_HOUR)"
                                :key="'h30-' + h"
                                class="pointer-events-none absolute top-0 h-full w-px bg-gray-100/50"
                                :style="{ left: (((h - START_HOUR) * 60 + 30) / TOTAL_MINS * 100) + '%' }"
                            ></div>

                            <!-- イベントブロック -->
                            <div
                                v-for="ev in eventsForMember(member.id)"
                                :key="ev.id"
                                class="event-block absolute z-10 overflow-hidden"
                                :style="blockStyle(ev)"
                                @click="onBlockClick(ev, $event)"
                            >
                                <!-- 関連イベントはタイトルを表示 -->
                                <span
                                    v-if="ev.related"
                                    class="block truncate px-1.5 text-xs font-semibold leading-snug"
                                    :style="{ color: ev.color }"
                                >{{ ev.title }}</span>
                                <!-- 非関連は空（色帯のみ） -->
                            </div>
                        </div>
                    </div>

                    <!-- メンバーが0人 -->
                    <div
                        v-if="members.length === 0"
                        class="flex items-center justify-center py-16 text-sm text-gray-400"
                    >
                        メンバーが登録されていません。
                    </div>

                </div>
            </div><!-- /overflow-x-auto -->

        </div><!-- /rounded bg-white shadow -->

        <!-- ── 詳細ポップオーバー ───────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="detailEvent"
                class="detail-popover fixed z-50 w-64 rounded-lg border border-gray-200 bg-white p-4 shadow-xl"
                :style="detailStyle"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-800 leading-snug">{{ detailEvent.title }}</p>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeDetail">✕</button>
                </div>
                <dl class="space-y-1 text-xs text-gray-600">
                    <div>
                        <dt class="text-gray-400">時間</dt>
                        <dd>{{ minsToTimeStr(isoToMinutes(detailEvent.starts_at)) }} – {{ minsToTimeStr(isoToMinutes(detailEvent.ends_at)) }}</dd>
                    </div>
                    <div v-if="detailEvent.detail">
                        <dt class="text-gray-400">内容</dt>
                        <dd class="whitespace-pre-wrap">{{ detailEvent.detail }}</dd>
                    </div>
                </dl>
            </div>
        </Teleport>

    </AppLayout>
</template>
