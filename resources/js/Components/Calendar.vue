<template>
    <div class="calendar-container">
            <div class="mb-4">
            <!-- モバイル: アクションドロップダウン -->
            <div class="sm:hidden">
                <select @change="onMobileActionSelect" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">— 操作を選択 —</option>
                    <option value="client_event">案件打合せ・外出</option>
                    <option value="internal_event">社内予定</option>
                    <option value="myjob">マイジョブ</option>
                    <option value="job_sheet">進行表ジョブ</option>
                    <option value="diary">{{ props.diaryLabel }}入力</option>
                    <option value="schedule">日程設定</option>
                    <option value="break">休憩設定</option>
                </select>
            </div>
            <!-- デスクトップ: ボタン列 -->
            <div class="hidden sm:flex flex-wrap gap-2">
                <button @click="openClientEventModal" class="rounded bg-emerald-600 px-4 py-2 text-white">案件打合せ・外出</button>
                <button @click="openInternalEventModal" class="rounded bg-teal-600 px-4 py-2 text-white">社内予定</button>
                <button @click="goToJobCreate" class="rounded bg-indigo-600 px-4 py-2 text-white">マイジョブ</button>
                <button @click="openJobSheetModal" class="rounded bg-purple-600 px-4 py-2 text-white">進行表ジョブ</button>
                <button @click="goToDiaryCreate" class="rounded bg-orange-500 px-4 py-2 text-white">{{ props.diaryLabel }}入力</button>
                <button @click="openScheduleModal" class="rounded border border-gray-400 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">日程設定</button>
                <button @click="openBreakModal" class="rounded border border-teal-400 bg-white px-4 py-2 text-sm text-teal-700 hover:bg-teal-50">休憩設定</button>
            </div>
        </div>
        <FullCalendar ref="fullCalendarRef" :options="calendarOptions" />
        <!-- 予定作成モーダル -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">イベント追加</h2>
                <form @submit.prevent="submitEvent">
                    <!-- ...既存のイベントフォーム... -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium">タイトル</label>
                        <input v-model="form.title" type="text" class="w-full rounded border p-2" required />
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium">内容</label>
                        <textarea v-model="form.description" class="w-full rounded border p-2" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium">日付</label>
                        <div class="w-full rounded border bg-gray-100 p-2">{{ form.date }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="flex items-center gap-6">
                            <div>
                                <label class="mb-1 block text-sm font-medium">開始時刻</label>
                                <div class="flex gap-2">
                                    <select v-model="form.startHour" class="w-20 rounded border p-1" ref="startHourSelectRef">
                                        <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                            {{ String(h - 1).padStart(2, '0') }}
                                        </option>
                                    </select>
                                    <select v-model="form.startMinute" class="w-20 rounded border p-1">
                                        <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                            {{ String(m).padStart(2, '0') }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">終了時刻</label>
                                <div class="flex gap-2">
                                    <select v-model="form.endHour" class="w-20 rounded border p-1" ref="endHourSelectRef">
                                        <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                            {{ String(h - 1).padStart(2, '0') }}
                                        </option>
                                    </select>
                                    <select v-model="form.endMinute" class="w-20 rounded border p-1">
                                        <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                            {{ String(m).padStart(2, '0') }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" @click="showModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">登録</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- 日付クリック時の選択モーダル -->
        <div v-if="showSelectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-xs rounded-lg bg-white p-6 text-center shadow-lg">
                <h2 class="mb-4 text-lg font-bold">{{ selectedDate }} の操作</h2>
                <div class="flex flex-col gap-4">
                    <button @click="openClientEventModalFromSelect" class="rounded bg-emerald-600 px-4 py-2 text-white">案件打合せ・外出</button>
                    <button @click="openInternalEventModalFromSelect" class="rounded bg-teal-600 px-4 py-2 text-white">社内予定</button>
                    <button @click="goToJobCreate" class="rounded bg-indigo-600 px-4 py-2 text-white">マイジョブ</button>
                    <button @click="openJobSheetModal" class="rounded bg-purple-600 px-4 py-2 text-white">進行表ジョブ</button>
                    <button
                        v-if="isProofMember"
                        @click="showSelectModal = false; router.get(route('user.proof_jobs.index'))"
                        class="rounded bg-pink-600 px-4 py-2 text-white"
                    >
                        校正をセット →
                    </button>

                    <button v-if="selectedScheduleId === null" @click="goToDiaryCreateFromSelect" class="rounded bg-orange-500 px-4 py-2 text-white">
                        日報入力
                    </button>
                    <button v-else @click="goToScheduleMemoCreate(selectedScheduleId)" class="rounded bg-green-600 px-4 py-2 text-white">
                        メモ作成
                    </button>
                    <button @click="showSelectModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                </div>
            </div>
        </div>

        <!-- 進行表から案件選択モーダル -->
        <div
            v-if="showJobSheetModal"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-50"
            @click.self="showJobSheetModal = false"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold">案件を選択（進行表から）</h2>

                <div v-if="jobSheetLoading" class="py-8 text-center text-sm text-gray-500">読み込み中…</div>
                <div v-else>
                    <!-- クライアント選択 -->
                    <div class="mb-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">クライアント</label>
                        <select v-model="jsSelectedClientId" @change="onClientChange" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— 選択してください —</option>
                            <option v-for="c in jsClients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                        </select>
                    </div>

                    <!-- 案件選択（クライアント選択後に表示） -->
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

                    <!-- 案件選択後のアクションボタン -->
                    <div v-if="jsSelectedProjectId" class="mt-4 flex items-center justify-end gap-2">
                        <span v-if="jsSheetsLoading" class="text-sm text-gray-400">読み込み中…</span>
                        <span v-else-if="jsProgressSheets.length === 0 && !jsSheetsLoading" class="text-sm text-gray-400">進行表なし</span>
                        <button
                            @click="goToProgressSheet"
                            :disabled="!canGoToSheet"
                            :class="canGoToSheet
                                ? 'rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700'
                                : 'cursor-not-allowed rounded bg-gray-300 px-4 py-2 text-sm font-medium text-gray-500'"
                        >
                            詳細を見る（進行表へ）
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        @click="showJobSheetModal = false"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                    >
                        閉じる
                    </button>
                </div>
            </div>
        </div>

        <!-- 週間日程設定モーダル -->
        <div v-if="showScheduleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">週間日程設定</h2>
                <p class="mb-3 text-xs text-gray-500">空白はデフォルト設定を使用します。</p>
                <table class="w-full text-sm">
                    <tbody>
                        <!-- 全日一括変更 -->
                        <tr class="border-b-2 border-gray-300 bg-gray-50">
                            <td class="w-24 py-2 pr-3 font-bold text-gray-800">全日</td>
                            <td class="py-2">
                                <select
                                    :value="null"
                                    @change="
                                        (e) => {
                                            const v = e.target.value ? Number(e.target.value) : null;
                                            weekDays.forEach((d) => (d.worktype_id = v));
                                            e.target.value = '';
                                        }
                                    "
                                    class="w-full rounded border-gray-300 text-sm focus:border-blue-400 focus:ring-blue-400"
                                >
                                    <option value="">— 一括選択 —</option>
                                    <option :value="null">— デフォルト —</option>
                                    <option v-for="wt in worktypes" :key="wt.id" :value="wt.id">
                                        {{ wt.name }}
                                        <template v-if="wt.start_time"> ({{ wt.start_time.substring(0, 5) }}〜)</template>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <!-- 曜日ごと -->
                        <tr v-for="day in weekDays" :key="day.date" class="border-b last:border-0">
                            <td class="w-24 py-2 pr-3 font-medium text-gray-700">{{ day.label }}</td>
                            <td class="py-2">
                                <select
                                    v-model="day.worktype_id"
                                    class="w-full rounded border-gray-300 text-sm focus:border-blue-400 focus:ring-blue-400"
                                >
                                    <option :value="null">— デフォルト —</option>
                                    <option v-for="wt in worktypes" :key="wt.id" :value="wt.id">
                                        {{ wt.name }}
                                        <template v-if="wt.start_time"> ({{ wt.start_time.substring(0, 5) }}〜)</template>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="showScheduleModal = false" class="rounded bg-gray-200 px-4 py-2 text-sm">キャンセル</button>
                    <button
                        @click="saveWeekSchedule"
                        :disabled="savingSchedule"
                        class="rounded bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50"
                    >
                        {{ savingSchedule ? '保存中…' : '保存' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- 週間休憩設定モーダル -->
        <div v-if="showBreakModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-1 text-lg font-bold">週間休憩設定</h2>
                <p class="mb-3 text-xs text-gray-500">チェックを入れた日に休憩時間が適用されます。時間を変更するとチェックが自動で入ります。</p>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-gray-300 bg-gray-100 text-xs text-gray-600">
                            <th class="py-2 pl-1 pr-3 text-left font-medium">日付</th>
                            <th class="py-2 w-10 text-center font-medium">有効</th>
                            <th class="py-2 pr-8 text-left font-medium">開始</th>
                            <th class="py-2 pl-8 text-left font-medium">終了</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 全日一括変更 -->
                        <tr class="border-b-2 border-gray-300 bg-teal-50">
                            <td class="py-2 pl-1 pr-3 font-bold text-teal-800">全日一括</td>
                            <td class="py-2 text-center">
                                <input type="checkbox" v-model="batchAllEnabled"
                                       @change="applyBatchAllEnabled"
                                       class="h-4 w-4 rounded border-gray-300 accent-teal-600" />
                            </td>
                            <td class="py-2 pr-8">
                                <div class="flex items-center gap-1">
                                    <select v-model="batchStartH" @change="applyBatchBreakTime"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="h in 24" :key="h" :value="String(h-1).padStart(2,'0')">{{ String(h-1).padStart(2,'0') }}</option>
                                    </select>
                                    <span class="text-gray-500">:</span>
                                    <select v-model="batchStartM" @change="applyBatchBreakTime"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="m in [0,15,30,45]" :key="m" :value="String(m).padStart(2,'0')">{{ String(m).padStart(2,'0') }}</option>
                                    </select>
                                </div>
                            </td>
                            <td class="py-2 pl-8">
                                <div class="flex items-center gap-1">
                                    <select v-model="batchEndH" @change="applyBatchBreakTime"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="h in 24" :key="h" :value="String(h-1).padStart(2,'0')">{{ String(h-1).padStart(2,'0') }}</option>
                                    </select>
                                    <span class="text-gray-500">:</span>
                                    <select v-model="batchEndM" @change="applyBatchBreakTime"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="m in [0,15,30,45]" :key="m" :value="String(m).padStart(2,'0')">{{ String(m).padStart(2,'0') }}</option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <!-- 日別 -->
                        <tr v-for="day in breakDays" :key="day.date"
                            class="border-b last:border-0"
                            :class="day.enabled ? '' : 'opacity-50'">
                            <td class="py-2 pl-1 pr-3 font-medium text-gray-700">{{ day.label }}</td>
                            <td class="py-2 text-center">
                                <input type="checkbox" v-model="day.enabled"
                                       class="h-4 w-4 rounded border-gray-300 accent-teal-600" />
                            </td>
                            <td class="py-2 pr-8">
                                <div class="flex items-center gap-1">
                                    <select v-model="day.startH"
                                            @change="day.enabled = true"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="h in 24" :key="h" :value="String(h-1).padStart(2,'0')">{{ String(h-1).padStart(2,'0') }}</option>
                                    </select>
                                    <span class="text-gray-500">:</span>
                                    <select v-model="day.startM"
                                            @change="day.enabled = true"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="m in [0,15,30,45]" :key="m" :value="String(m).padStart(2,'0')">{{ String(m).padStart(2,'0') }}</option>
                                    </select>
                                </div>
                            </td>
                            <td class="py-2 pl-8">
                                <div class="flex items-center gap-1">
                                    <select v-model="day.endH"
                                            @change="day.enabled = true"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="h in 24" :key="h" :value="String(h-1).padStart(2,'0')">{{ String(h-1).padStart(2,'0') }}</option>
                                    </select>
                                    <span class="text-gray-500">:</span>
                                    <select v-model="day.endM"
                                            @change="day.enabled = true"
                                            class="rounded border-gray-300 text-sm focus:border-teal-400 focus:ring-teal-400">
                                        <option v-for="m in [0,15,30,45]" :key="m" :value="String(m).padStart(2,'0')">{{ String(m).padStart(2,'0') }}</option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="showBreakModal = false" class="rounded bg-gray-200 px-4 py-2 text-sm">キャンセル</button>
                    <button @click="saveWeekBreaks" :disabled="savingBreak"
                            class="rounded bg-teal-600 px-4 py-2 text-sm text-white disabled:opacity-50">
                        {{ savingBreak ? '保存中…' : '保存' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- schedule-specific UI removed — this Calendar is personal-only -->
    </div>
</template>

<script setup>
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    diaries: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
    diaryLabel: {
        type: String,
        default: '日報',
    },
    jobs: {
        type: Array,
        default: () => [],
    },
    initialView: {
        type: String,
        default: 'timeGridWeek',
    },
    defaultWorktype: {
        type: Object,
        default: null,
    },
    worktypes: {
        type: Array,
        default: () => [],
    },
    dailyWorktypes: {
        type: Array,
        default: () => [],
    },
    dailyBreaks: {
        type: Array,
        default: () => [],
    },
    defaultBreak: {
        type: Object,
        default: () => ({ start: '12:00', end: '13:00' }),
    },
});

const page = usePage();
const isProofMember = computed(() => page.props.auth?.isProofMember ?? false);

// 日ごとの勤務形態マップ { 'YYYY-MM-DD': worktype_id }（ローカル更新可能）
const localDailyWorktypes = ref([...(props.dailyWorktypes ?? [])]);

const dailyWorktypeMap = computed(() => {
    const map = {};
    localDailyWorktypes.value.forEach((d) => {
        if (d.date) map[d.date] = d.worktype_id;
    });
    return map;
});

// 指定日の有効な勤務形態を返す（日次設定 > デフォルト）
function getWorktypeForDate(dateStr) {
    const wid = dailyWorktypeMap.value[dateStr];
    if (wid) {
        const wt = props.worktypes.find((w) => w.id === wid);
        if (wt) return wt;
    }
    return props.defaultWorktype ?? null;
}

// 夜勤判定: デフォルト勤務形態の start_time が 16:00 以降なら夜勤
const isNightShift = computed(() => {
    if (!props.defaultWorktype?.start_time) return false;
    const h = parseInt(props.defaultWorktype.start_time.substring(0, 2), 10);
    return h >= 16;
});

// スロット表示範囲
// 夜勤: 16:00 〜 30:00 (翌 06:00)、通常: 07:00 〜 24:00
const slotMinTime = computed(() => (isNightShift.value ? '16:00:00' : '07:00:00'));
const slotMaxTime = computed(() => (isNightShift.value ? '30:00:00' : '24:00:00'));

// 初期スクロール位置: 現在時刻の 1 時間前
// 夜勤かつ深夜 0〜6 時は FullCalendar の 24+h 表記に合わせる
const scrollTime = computed(() => {
    const now = new Date();
    let h = now.getHours();
    const m = now.getMinutes();
    if (isNightShift.value && h < 6) {
        h += 24; // 深夜帯は 24xx 表記
    }
    const scrollH = Math.max(isNightShift.value ? 16 : 7, h - 1);
    return `${String(scrollH).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
});

// ---- 表示中の日付範囲（datesSet で更新） ----
const viewStart = ref(null); // Date
const viewEnd = ref(null); // Date（exclusive）

// ---- 始業前グレー背景イベント ----
const backgroundEvents = computed(() => {
    if (!viewStart.value) return [];
    const results = [];
    const cur = new Date(viewStart.value);
    const end = viewEnd.value ? new Date(viewEnd.value) : new Date(viewStart.value);
    end.setDate(end.getDate() + 1);
    const slotMin = isNightShift.value ? 16 : 7;

    while (cur < end) {
        const dateStr = cur.getFullYear() + '-' + String(cur.getMonth() + 1).padStart(2, '0') + '-' + String(cur.getDate()).padStart(2, '0');
        const wt = getWorktypeForDate(dateStr);
        if (wt?.start_time) {
            const [sh, sm] = wt.start_time.split(':').map(Number);
            const startMins = sh * 60 + sm;
            if (startMins > slotMin * 60) {
                results.push({
                    start: `${dateStr}T${String(slotMin).padStart(2, '0')}:00:00`,
                    end: `${dateStr}T${wt.start_time.substring(0, 5)}:00`,
                    display: 'background',
                    color: 'rgba(0,0,0,0.08)',
                });
            }
        }
        cur.setDate(cur.getDate() + 1);
    }
    return results;
});

// ---- 日ごと休憩マップ { 'YYYY-MM-DD': { start, end } }（ローカル更新可能） ----
const localDailyBreaks = ref([...(props.dailyBreaks ?? [])]);
const dailyBreakMap = computed(() => {
    const map = {};
    localDailyBreaks.value.forEach((d) => {
        if (d.date) map[d.date] = { start: d.start, end: d.end };
    });
    return map;
});

// ---- 週間休憩設定モーダル ----
const showBreakModal = ref(false);
const breakDays = ref([]);
const savingBreak = ref(false);
const batchAllEnabled = ref(false);
const batchStartH = ref('12');
const batchStartM = ref('00');
const batchEndH = ref('13');
const batchEndM = ref('00');

function parseHM(timeStr) {
    if (!timeStr) return ['12', '00'];
    const [h, m] = timeStr.split(':');
    return [h ?? '12', m ?? '00'];
}

function openBreakModal() {
    const refDate = viewStart.value ? new Date(viewStart.value) : new Date(selectedDate.value);
    const monday = getMondayOfWeek(refDate);
    const defStart = props.defaultBreak?.start ?? '12:00';
    const defEnd   = props.defaultBreak?.end   ?? '13:00';
    const [defSH, defSM] = parseHM(defStart);
    const [defEH, defEM] = parseHM(defEnd);

    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        const saved = dailyBreakMap.value[dateStr];
        const [sh, sm] = saved ? parseHM(saved.start) : [defSH, defSM];
        const [eh, em] = saved ? parseHM(saved.end)   : [defEH, defEM];
        days.push({
            date: dateStr,
            label: `${d.getMonth() + 1}/${d.getDate()}(${DAY_NAMES[i]})`,
            enabled: saved !== undefined,
            startH: sh, startM: sm,
            endH:   eh, endM:   em,
        });
    }
    breakDays.value = days;
    batchAllEnabled.value = false;
    showBreakModal.value = true;
}

function applyBatchAllEnabled() {
    breakDays.value.forEach((d) => (d.enabled = batchAllEnabled.value));
}

function applyBatchBreakTime() {
    breakDays.value.forEach((d) => {
        d.enabled = true;
        d.startH = batchStartH.value;
        d.startM = batchStartM.value;
        d.endH   = batchEndH.value;
        d.endM   = batchEndM.value;
    });
}

async function saveWeekBreaks() {
    savingBreak.value = true;
    try {
        const days = breakDays.value.map((day) => ({
            date:        day.date,
            break_start: day.enabled ? `${day.startH}:${day.startM}` : null,
            break_end:   day.enabled ? `${day.endH}:${day.endM}`   : null,
        }));
        await axios.post(route('user.daily_breaks.store'), { days });

        // ローカル状態を更新
        days.forEach((day) => {
            const idx = localDailyBreaks.value.findIndex((d) => d.date === day.date);
            if (!day.break_start) {
                if (idx >= 0) localDailyBreaks.value.splice(idx, 1);
            } else if (idx >= 0) {
                localDailyBreaks.value.splice(idx, 1, { date: day.date, start: day.break_start, end: day.break_end });
            } else {
                localDailyBreaks.value.push({ date: day.date, start: day.break_start, end: day.break_end });
            }
        });
        showBreakModal.value = false;
    } catch {
        alert('保存に失敗しました');
    } finally {
        savingBreak.value = false;
    }
}

// ---- 週間日程設定モーダル ----
const showScheduleModal = ref(false);
const weekDays = ref([]); // [{ date, label, worktype_id }]
const savingSchedule = ref(false);
const DAY_NAMES = ['月', '火', '水', '木', '金', '土', '日'];

function getMondayOfWeek(d) {
    const day = d.getDay(); // 0=Sun
    const diff = day === 0 ? -6 : 1 - day;
    const mon = new Date(d);
    mon.setDate(d.getDate() + diff);
    return mon;
}

function openScheduleModal() {
    const refDate = viewStart.value ? new Date(viewStart.value) : new Date(selectedDate.value);
    const monday = getMondayOfWeek(refDate);

    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        days.push({
            date: dateStr,
            label: `${d.getMonth() + 1}/${d.getDate()}(${DAY_NAMES[i]})`,
            worktype_id: dailyWorktypeMap.value[dateStr] ?? props.defaultWorktype?.id ?? null,
        });
    }
    weekDays.value = days;
    showScheduleModal.value = true;
}

async function saveWeekSchedule() {
    savingSchedule.value = true;
    try {
        await axios.post(route('user.daily_worktypes.store'), { days: weekDays.value });
        // ローカル状態を更新
        weekDays.value.forEach((day) => {
            const idx = localDailyWorktypes.value.findIndex((d) => d.date === day.date);
            if (!day.worktype_id) {
                if (idx >= 0) localDailyWorktypes.value.splice(idx, 1);
            } else if (idx >= 0) {
                localDailyWorktypes.value.splice(idx, 1, { date: day.date, worktype_id: day.worktype_id });
            } else {
                localDailyWorktypes.value.push({ date: day.date, worktype_id: day.worktype_id });
            }
        });
        showScheduleModal.value = false;
        // ヘッダーの勤務形態名・グレー背景を即時反映
        await nextTick();
        fullCalendarRef.value?.getApi()?.render();
    } catch {
        alert('保存に失敗しました');
    } finally {
        savingSchedule.value = false;
    }
}

const showModal = ref(false);
const showSelectModal = ref(false);
const form = ref({
    title: '',
    description: '',
    startHour: '09',
    startMinute: '00',
    endHour: '10',
    endMinute: '00',
    date: '',
});

// clicked/dragged time range (used by select modal to prefill create)
const clickedStartHour = ref(null);
const clickedStartMinute = ref(null);
const clickedEndHour = ref(null);
const clickedEndMinute = ref(null);

// カレンダーで選択中の日付（初期値は今日）
const today = new Date();
const getTodayString = () => {
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};
const selectedDate = ref(getTodayString());
// If the page was opened with a ?date=YYYY-MM-DD query (or Inertia supplied it in the URL),
// prefer that as the initial selected date so links like "予定を編集" focus the correct day.
try {
    const params = new URLSearchParams(window.location.search);
    const qd = params.get('date');
    if (qd && typeof qd === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
        selectedDate.value = qd;
    }
} catch (e) {
    // ignore malformed URL or environments without window
}
// personal-only calendar: no schedule scoping
const selectedScheduleId = ref(null);

const startHourSelectRef = ref(null);
const endHourSelectRef = ref(null);
// Reference to FullCalendar component so we can programmatically navigate to dates
const fullCalendarRef = ref(null);

onMounted(() => {
    nextTick(() => {
        const now = new Date();
        const currentHour = String(now.getHours()).padStart(2, '0');
        // 開始時刻のselect
        if (startHourSelectRef.value) {
            const idx = Array.from(startHourSelectRef.value.options).findIndex((opt) => opt.value === currentHour);
            if (idx >= 0) startHourSelectRef.value.selectedIndex = idx;
        }
        // 終了時刻のselect（今回はデフォルト10時のまま）
    });
});

/** JST の現在時刻を5分刻みに切り捨てて { h, m } を返す */
function currentTimeSnapped5() {
    const now = new Date();
    const jstParts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Tokyo',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(now);
    const rawH = Number(jstParts.find((p) => p.type === 'hour').value);
    const rawM = Number(jstParts.find((p) => p.type === 'minute').value);
    const snappedM = Math.floor(rawM / 5) * 5;
    return {
        h: String(rawH).padStart(2, '0'),
        m: String(snappedM).padStart(2, '0'),
    };
}

function buildDateTimeParams() {
    const params = { date: selectedDate.value };
    if (clickedStartHour.value !== null && clickedStartMinute.value !== null) {
        // カレンダーの時間スロットをクリックした場合
        const hh = String(clickedStartHour.value).padStart(2, '0');
        const mm = String(clickedStartMinute.value).padStart(2, '0');
        params.startHour = hh;
        params.startMinute = mm;
        if (clickedEndHour.value !== null && clickedEndMinute.value !== null) {
            params.endHour = String(clickedEndHour.value).padStart(2, '0');
            params.endMinute = String(clickedEndMinute.value).padStart(2, '0');
        } else {
            params.endHour = String((Number(hh) + 1) % 24).padStart(2, '0');
            params.endMinute = mm;
        }
    } else {
        // ボタンメニューから開いた場合 → 現在時刻（5分刻み）をデフォルトにする
        const { h, m } = currentTimeSnapped5();
        params.startHour = h;
        params.startMinute = m;
        params.endHour = String((Number(h) + 1) % 24).padStart(2, '0');
        params.endMinute = m;
    }
    return params;
}

function openClientEventModal() {
    const params = buildDateTimeParams();
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('events.client-event.create', { ...params, return_to: current }));
        return;
    } catch (e) {
        router.get(route('events.client-event.create', params));
    }
}

function openInternalEventModal() {
    const params = buildDateTimeParams();
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('events.internal-event.create', { ...params, return_to: current }));
        return;
    } catch (e) {
        router.get(route('events.internal-event.create', params));
    }
}

/** @deprecated 旧互換用（goToJobCreate から参照） */
function openEventModal() {
    openClientEventModal();
}

function openClientEventModalFromSelect() {
    const params = buildDateTimeParams();
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('events.client-event.create', { ...params, return_to: current }));
    } catch (e) {
        router.get(route('events.client-event.create', params));
    }
    showSelectModal.value = false;
    clickedStartHour.value = null;
    clickedStartMinute.value = null;
    clickedEndHour.value = null;
    clickedEndMinute.value = null;
}

function openInternalEventModalFromSelect() {
    const params = buildDateTimeParams();
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('events.internal-event.create', { ...params, return_to: current }));
    } catch (e) {
        router.get(route('events.internal-event.create', params));
    }
    showSelectModal.value = false;
    clickedStartHour.value = null;
    clickedStartMinute.value = null;
    clickedEndHour.value = null;
    clickedEndMinute.value = null;
}

function goToDiaryCreateFromSelect() {
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('diaries.create', { date: selectedDate.value, return_to: current }));
    } catch (e) {
        router.get(route('diaries.create', { date: selectedDate.value }));
    }
    showSelectModal.value = false;
}

// 日付クリック時の遷移処理を削除

function goToDiaryCreate() {
    // 選択中の日付で作成画面へ遷移
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('diaries.create', { date: selectedDate.value, return_to: current }));
    } catch (e) {
        router.get(route('diaries.create', { date: selectedDate.value }));
    }
}

function goToJobCreate() {
    showSelectModal.value = false;
    try {
        const params = buildDateTimeParams();
        try {
            router.get(route('events.create_job', params));
            return;
        } catch (e) {
            // fallback: open the generic event create page
            openEventModal();
            return;
        }
    } catch (e) {
        // fallback to existing events.create
        openEventModal();
    }
}

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
    showSelectModal.value = false;
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
            // ignore fetch errors
        } finally {
            jobSheetLoading.value = false;
        }
    }
}

// クライアント選択時に案件選択をリセット
function onClientChange() {
    jsSelectedProjectId.value = '';
    jsProgressSheets.value = [];
    jsSelectedSheetId.value = '';
}

// 案件選択時に進行表リストを取得
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

function handleDateSelect(selectionInfo) {
    // カレンダーで日付選択時に選択日を保持
    const dateStr = selectionInfo.startStr.split('T')[0];
    selectedDate.value = dateStr;
    selectedScheduleId.value = null;

    // ドラッグで時間範囲が選択されたとき start/end を抽出
    try {
        const startObj = selectionInfo.start;
        const endObj = selectionInfo.end;
        if (startObj && (startObj.getHours() !== 0 || selectionInfo.startStr.includes('T'))) {
            clickedStartHour.value = startObj.getHours();
            clickedStartMinute.value = startObj.getMinutes() < 30 ? 0 : 30;
        } else {
            clickedStartHour.value = null;
            clickedStartMinute.value = null;
        }
        if (endObj && (endObj.getHours() !== 0 || selectionInfo.endStr.includes('T'))) {
            clickedEndHour.value = endObj.getHours();
            clickedEndMinute.value = endObj.getMinutes() < 30 ? 0 : 30;
        } else {
            clickedEndHour.value = null;
            clickedEndMinute.value = null;
        }
    } catch (e) {
        clickedStartHour.value = null;
        clickedStartMinute.value = null;
        clickedEndHour.value = null;
        clickedEndMinute.value = null;
    }

    showSelectModal.value = true;
}

// Handle clicking a time slot or date cell. Snap minutes to 00 or 30 and open select modal.
function handleTimeSlotClick(info) {
    try {
        // info may be a Date or an object depending on FullCalendar hook
        let dateObj = null;
        if (info && info.date)
            dateObj = info.date; // dateClick provides { date, ... }
        else if (info && info.start)
            dateObj = info.start; // select provides start
        else if (info instanceof Date) dateObj = info;
        if (!dateObj) return;
        // convert to local YYYY-MM-DD and hours/minutes (avoid toISOString which shifts to UTC)
        const y = dateObj.getFullYear();
        const mo = String(dateObj.getMonth() + 1).padStart(2, '0');
        const da = String(dateObj.getDate()).padStart(2, '0');
        const dateOnly = `${y}-${mo}-${da}`;
        const h = dateObj.getHours();
        const m = dateObj.getMinutes();
        const snappedM = m < 30 ? 0 : 30;
        selectedDate.value = dateOnly;
        clickedStartHour.value = h;
        clickedStartMinute.value = snappedM;
        showSelectModal.value = true;
    } catch (e) {
        // ignore
    }
}

// project schedule flows removed from personal calendar

// 日報がある日をイベントとして表示（タイトルは●アイコン）
// Merge diaries, events, and assigned jobs into FullCalendar events
const baseEvents = computed(() => [
    // 日報（オレンジ）
    ...props.diaries.map((diary) => {
        // UTC→JST(+9h)変換
        const d = new Date(diary.date);
        d.setHours(d.getHours() + 9);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return {
            title: `● ${props.diaryLabel}`,
            start: `${yyyy}-${mm}-${dd}`,
            allDay: true,
            color: '#f59e42',
            diary_id: diary.id,
        };
    }),
    // 予定（青）
    ...(props.events ?? []).map((event) => {
        // If title starts with completion prefix, use dark yellow color
        const isCompleted = typeof event.title === 'string' && event.title.indexOf('【完了】') === 0;

        // Determine linkage id coming from server (canonical assignment id)
        const pjAssignmentId = event.extendedProps?.project_job_assignment_id ?? event.project_job_assignment_id ?? null;
        const isProgressLinked = event.extendedProps?.has_progress_cell ?? false;
        const isSelfAssigned = event.extendedProps?.is_self_assigned ?? false;

        // If linkage id is not present, treat as a 'personal unlinked' event — use a distinctive color
        if (!pjAssignmentId) {
            return {
                title: event.title,
                start: event.start,
                end: event.end ?? undefined,
                allDay: event.allDay ?? false,
                // distinct color for user's own unlinked events. Default to a teal color that's different
                // from assignment-status colors (which are likely red/orange/blue). Use a muted teal: #1fb6b3
                color: event.color ?? '#1fb6b3',
                event_id: event.id,
                schedule_id: event.extendedProps?.schedule_id ?? event.schedule_id ?? undefined,
                description: event.description ?? event.extendedProps?.description ?? '',
            };
        }

        // default coloring path
        const chosenColor = isCompleted
                ? '#b58900'
                : (isProgressLinked
                    ? (event.color ?? '#7C3AED')
                    : (isSelfAssigned
                        ? (event.color ?? '#4F46E5')
                        : (event.color ?? '#059669')
                    )
                );

        return {
            title: event.title,
            start: event.start,
            end: event.end ?? undefined,
            allDay: event.allDay ?? false,
            color: chosenColor,
            event_id: event.id,
            schedule_id: event.extendedProps?.schedule_id ?? event.schedule_id ?? undefined,
            description: event.description ?? event.extendedProps?.description ?? '',
        };
    }),
    // Assigned jobs display removed per UX request: do not include props.jobs in calendar events
]);


// 通常イベント + 始業前背景イベントを結合
const allEvents = computed(() => [...baseEvents.value, ...backgroundEvents.value]);

const isMobileScreen = typeof window !== 'undefined' && window.innerWidth < 640;

function onMobileActionSelect(e) {
    const val = e.target.value;
    e.target.value = '';
    if (val === 'client_event') openClientEventModal();
    else if (val === 'internal_event') openInternalEventModal();
    else if (val === 'myjob') goToJobCreate();
    else if (val === 'job_sheet') openJobSheetModal();
    else if (val === 'diary') goToDiaryCreate();
    else if (val === 'schedule') openScheduleModal();
    else if (val === 'break') openBreakModal();
}

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    // choose initial view: if all events are all-day, use month grid so they are visible
    initialView: isMobileScreen ? 'dayGridMonth' : props.initialView,
    // initialDate allows FullCalendar to open on a specific day (YYYY-MM-DD)
    initialDate: selectedDate.value,
    events: allEvents.value,
    datesSet: function (info) {
        viewStart.value = info.start;
        viewEnd.value = info.end;
    },
    locale: 'ja',
    headerToolbar: isMobileScreen ? {
        left: 'prev,next',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek',
    } : {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    selectable: true,
    dateClick: handleTimeSlotClick,
    slotMinTime: slotMinTime.value,
    slotMaxTime: slotMaxTime.value,
    scrollTime: scrollTime.value,
    scrollTimeReset: false,
    firstDay: 1,
    weekText: '\u9031',
    dayHeaderFormat: { weekday: 'short' },
    // add just after dayHeaderFormat
    // dayHeaderContent: 月/日を表示。月ビューでは日付は表示しない。
    dayHeaderContent: function (arg) {
        // arg.date は Date、arg.text はロケールに沿った曜ラベル（例: "月"）
        const viewType = arg.view && arg.view.type ? String(arg.view.type) : '';
        const d = arg.date;
        const month = d ? d.getMonth() + 1 : '';
        const day = d ? d.getDate() : '';
        const md = month && day ? `${month}/${day}` : '';
        const weekdayText = arg.text || '';

        // 勤務形態名を取得（dayGridMonth では表示しない）
        let wtHtml = '';
        if (viewType !== 'dayGridMonth' && d) {
            const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            const wt = getWorktypeForDate(dateStr);
            if (wt?.name) {
                wtHtml = `<div class="fc-day-worktype">${wt.name}</div>`;
            }
        }

        // 月表示（dayGridMonth）のときは日付 (md) を表示しない
        if (viewType === 'dayGridMonth') {
            return { html: `<div class="fc-day-header-bottom">${weekdayText}</div>` };
        }

        // それ以外のビューでは「12/1」(上段) + 曜日(下段) + 勤務形態(下段) を表示
        return {
            html: `<div class="fc-day-header-top">${md}</div><div class="fc-day-header-bottom">${weekdayText}</div>${wtHtml}`,
        };
    },
    // keep internal grid at 15-minute increments but show labels every 30 minutes
    slotDuration: '00:15:00',
    slotLabelInterval: '00:30:00',
    // force two-digit hour/minute labels (e.g. 09:00, 09:30)
    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    // ensure the calendar has enough height so time slots aren't cramped
    height: 720,
    editable: true, // イベントのドラッグ・リサイズを有効化
    eventDurationEditable: true,
    eventResizableFromStart: true,
    eventResize: async function (info) {
        const newStart = info.event.start;
        const newEnd = info.event.end;
        // Prefer FullCalendar provided ISO strings to avoid TZ-shift issues
        const startStr = info.event.startStr || (newStart ? newStart.toISOString() : null);
        const endStr = info.event.endStr || (newEnd ? newEnd.toISOString() : null);
        // derive display dates (for allDay, endStr is exclusive -> subtract 1 day)
        const fmtDateOnly = (iso) => (iso ? String(iso).split('T')[0] : null);
        const displayStart = fmtDateOnly(startStr);
        let displayEndInclusive = null;
        if (endStr) {
            const endDateOnly = fmtDateOnly(endStr);
            if (info.event.allDay) {
                const d = new Date(endDateOnly);
                d.setDate(d.getDate() - 1);
                displayEndInclusive = d.toISOString().split('T')[0];
            } else {
                displayEndInclusive = endDateOnly;
            }
        }
        // For non-allDay show times, otherwise just dates
        let confirmMessage = '';
        if (info.event.allDay) {
            confirmMessage = `予定を変更しますか？\n開始: ${displayStart}\n終了: ${displayEndInclusive || displayStart}`;
        } else {
            const startDateObj = new Date(newStart);
            const endDateObj = new Date(newEnd);
            const date = startDateObj.toISOString().slice(0, 10);
            const startHour = String(startDateObj.getHours()).padStart(2, '0');
            const startMinute = String(startDateObj.getMinutes()).padStart(2, '0');
            const endHour = String(endDateObj.getHours()).padStart(2, '0');
            const endMinute = String(endDateObj.getMinutes()).padStart(2, '0');
            confirmMessage = `予定の時間を変更しますか？\n開始: ${date} ${startHour}:${startMinute}\n終了: ${date} ${endHour}:${endMinute}`;
        }
        if (confirm(confirmMessage)) {
            try {
                // update personal event via events endpoint
                await axios.put(`/events/${info.event.extendedProps.event_id}/calendar`, {
                    date: displayStart,
                    startHour: newStart ? String(newStart.getHours()).padStart(2, '0') : undefined,
                    startMinute: newStart ? String(newStart.getMinutes()).padStart(2, '0') : undefined,
                    endHour: newEnd ? String(newEnd.getHours()).padStart(2, '0') : undefined,
                    endMinute: newEnd ? String(newEnd.getMinutes()).padStart(2, '0') : undefined,
                });
                alert('予定を更新しました');
            } catch (e) {
                // eventResize error log suppressed (keep error thrown)
                if (e.response && e.response.data) {
                    alert('予定の更新に失敗しました');
                    // API error detail log suppressed
                } else {
                    alert('予定の更新に失敗しました');
                }
                info.revert(); // 失敗時は元に戻す
            }
        } else {
            info.revert(); // キャンセル時は元に戻す
        }
    },
    eventClick: async function (info) {
        try {
            // If the clicked event is an all-day event, prefer navigating to the corresponding show page
            if (info.event.allDay) {
                if (info.event.extendedProps.diary_id) {
                    try {
                        router.get(route('diaries.show', { diary: info.event.extendedProps.diary_id }));
                    } catch (e) {
                        // Ziggy route may not be available in some contexts - fallback to a safe URL
                        window.location.href = route('diaries.show', { diary: info.event.extendedProps.diary_id });
                    }
                    return;
                }

                if (info.event.extendedProps.event_id || info.event.extendedProps.id || info.event.id) {
                    // derive a best-effort event id from multiple possible locations
                    const evId =
                        info.event.extendedProps.event_id ||
                        info.event.extendedProps.id ||
                        info.event.id ||
                        (info.event._def && info.event._def.publicId) ||
                        null;
                    if (evId) {
                        try {
                            router.get(route('events.show', { event: evId }));
                        } catch (e) {
                            window.location.href = route('events.show', { event: evId });
                        }
                        return;
                    }
                }

                // Assigned job items: if the event has an explicit event id candidate, prefer navigating to that
                // (this covers cases where an assigned-job entry actually points to an event). Otherwise,
                // attempt existence probe on /events/:job_id and fall back to assigned-jobs.
                if (info.event.extendedProps.job_id) {
                    const jid = info.event.extendedProps.job_id;
                    // Check for explicit event id fields that may be present on the event
                    const explicitEvId =
                        (info.event.extendedProps && (info.event.extendedProps.event_id || info.event.extendedProps.id)) ||
                        info.event.id ||
                        (info.event._def && info.event._def.publicId) ||
                        null;
                    if (explicitEvId) {
                        try {
                            // Prefer navigating to the explicit event id
                            router.get(route('events.show', { event: explicitEvId }));
                        } catch (e) {
                            window.location.href = route('events.show', { event: explicitEvId });
                        }
                        return;
                    }
                    try {
                        // Quick existence probe: try HEAD first, then GET as a fallback if HEAD isn't supported.
                        let exists = false;
                        try {
                            const headResp = await fetch(route('events.show', { event: jid }), { method: 'HEAD', credentials: 'same-origin' });
                            exists = headResp.ok;
                        } catch (headErr) {
                            exists = false;
                        }

                        if (!exists) {
                            try {
                                const getResp = await fetch(route('events.show', { event: jid }), { method: 'GET', credentials: 'same-origin' });
                                exists = getResp.ok;
                            } catch (getErr) {
                                exists = false;
                            }
                        }

                        if (exists) {
                            try {
                                router.get(route('events.show', { event: jid }));
                            } catch (e) {
                                window.location.href = route('events.show', { event: jid });
                            }
                        } else {
                            try {
                                router.get(route('user.assigned-jobs.show', { assigned_job: jid }));
                            } catch (e) {
                                try {
                                    router.get(route('assigned-jobs.show', { id: jid }));
                                } catch (e2) {
                                    window.location.href = route('user.assigned-jobs.show', { id: jid });
                                }
                            }
                        }
                    } catch (outerErr) {
                        // On any probe/navigation error, fallback to assigned-jobs
                        try {
                            router.get(route('assigned-jobs.show', { id: jid }));
                        } catch (e) {
                            window.location.href = route('user.assigned-jobs.show', { id: jid });
                        }
                    }
                    return;
                }
            }

            // 日報ラベルクリック時のみ遷移（既存の挙動を保持）
            if (info.event.extendedProps.diary_id) {
                router.get(route('diaries.show', { diary: info.event.extendedProps.diary_id }));
            }
            // 予定ラベルクリック時はShow.vueへ遷移
            if ((info.event.extendedProps && (info.event.extendedProps.event_id || info.event.extendedProps.id)) || info.event.id) {
                const evId =
                    (info.event.extendedProps && (info.event.extendedProps.event_id || info.event.extendedProps.id)) ||
                    info.event.id ||
                    (info.event._def && info.event._def.publicId) ||
                    null;
                if (evId) {
                    try {
                        router.get(route('events.show', { event: evId }));
                    } catch (e) {
                        window.location.href = route('events.show', { event: evId });
                    }
                }
            }
            // project schedule clicks not handled by personal calendar
        } catch {
            // swallow errors to avoid breaking the calendar UI
        }
    },
    select: handleDateSelect,
}));

// When selectedDate changes (including initial value set from query param), navigate FullCalendar to it.
watch(
    selectedDate,
    async (newDate) => {
        try {
            // Wait for calendar to mount
            await nextTick();
            const fc = fullCalendarRef.value && fullCalendarRef.value.getApi ? fullCalendarRef.value : null;
            if (fc && typeof fc.getApi === 'function') {
                const api = fc.getApi();
                if (api && typeof api.gotoDate === 'function') {
                    api.gotoDate(newDate);
                }
            }
        } catch {
            // swallow errors to avoid breaking UI
        }
    },
    { immediate: true },
);

const submitEvent = async () => {
    const start = `${form.value.date} ${form.value.startHour}:${form.value.startMinute}:00`;
    const end = `${form.value.date} ${form.value.endHour}:${form.value.endMinute}:00`;
    // send start/end debug suppressed
    try {
        await axios.post('/events', {
            title: form.value.title,
            description: form.value.description,
            start,
            end,
        });
        showModal.value = false;
        // カレンダーに即時反映（青色ラベル）
        events.value.push({
            title: form.value.title,
            start,
            end,
            color: '#2563eb', // 青色
            allDay: false,
        });
    } catch (e) {
        if (e.response && e.response.data && e.response.data.errors) {
            const messages = Object.values(e.response.data.errors).flat().join('\n');
            alert('登録に失敗しました:\n' + messages);
        } else {
            alert('登録に失敗しました');
        }
    }
};
</script>

<style scoped>
.calendar-container {
    padding: 1rem;
}

/* FullCalendarの基本スタイル */
.fc {
    font-family: inherit;
}

.fc-button {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

.fc-button:hover {
    background-color: #2563eb;
    border-color: #2563eb;
}

.fc-daygrid-day {
    cursor: pointer;
}

.fc-daygrid-day:hover {
    background-color: #f3f4f6;
}

/* emphasize hour boundaries (every 4 slots when slotDuration is 15min => 60min) */
.fc .fc-timegrid .fc-scrollgrid .fc-timegrid-slot-lane tr:nth-child(4n) td {
    border-top: 4px solid rgba(15, 23, 42, 0.22) !important;
    /* add slight shadow so the separator stands out against white */
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

/* emphasize labels at hour marks and make them two-digit bold (e.g. 09:00) */
.fc .fc-timegrid .fc-timegrid-slot-labels tr:nth-child(4n) td {
    border-top: 4px solid rgba(15, 23, 42, 0.28) !important;
    font-weight: 800;
    color: rgba(15, 23, 42, 0.98);
    background-color: rgba(15, 23, 42, 0.03);
    /* keep label visually aligned by adding small padding */
    padding-top: 4px !important;
}

/* give each slot a bit more vertical padding to avoid cramped appearance */
.fc .fc-timegrid .fc-scrollgrid .fc-timegrid-slot-lane td {
    padding-top: 6px;
    padding-bottom: 6px;
}
/* 月表示時に曜日のみを中央寄せ表示する場合の補正 */
.fc .fc-col-header-cell .fc-day-header-bottom {
    display: block;
    text-align: center;
    /* 既存のスタイルと衝突しないように調整 */
}

/* 通常（非月ビュー）で日付（上段）をやや強調 */
.fc .fc-col-header-cell .fc-day-header-top {
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1;
    padding-bottom: 0.06rem;
    color: rgba(15, 23, 42, 0.95);
}
.fc .fc-col-header-cell .fc-day-header-bottom {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(15, 23, 42, 0.7);
}
/* 勤務形態名（ヘッダー内） */
.fc .fc-col-header-cell .fc-day-worktype {
    font-size: 0.68rem;
    font-weight: 500;
    color: rgba(37, 99, 235, 0.85);
    line-height: 1.2;
    padding-top: 0.1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* モバイル: カレンダーヘッダーのタイトル・ボタンを小さく */
@media (max-width: 639px) {
    :deep(.fc-toolbar-title) {
        font-size: 1rem;
        font-weight: 600;
    }
    :deep(.fc-button) {
        padding: 0.2rem 0.45rem !important;
        font-size: 0.75rem !important;
    }
    :deep(.fc-toolbar.fc-header-toolbar) {
        gap: 0.25rem;
    }
}
</style>
