<template>
    <div class="calendar-container">
        <div class="mb-4 flex items-center gap-4">
            <!-- ビュー切替（案件カレンダーのみ） -->
            <template v-if="props.project">
                <button
                    @click="currentView = 'calendar'"
                    class="rounded px-3 py-1.5 text-sm font-medium"
                    :class="currentView === 'calendar' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                >
                    月カレンダー
                </button>
                <button
                    @click="currentView = 'week-planner'"
                    class="rounded px-3 py-1.5 text-sm font-medium"
                    :class="currentView === 'week-planner' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                >
                    週間プランナー
                </button>
                <span class="mx-1 text-gray-300">|</span>
            </template>

            <template v-if="currentView === 'calendar'">
                <button v-if="!props.readonly" @click="openEventModal" class="rounded bg-blue-600 px-4 py-2 text-white">予定作成</button>
                <button v-if="!props.readonly && props.showMemoButton" @click="goToDiaryCreate" class="rounded bg-orange-500 px-4 py-2 text-white">メモ作成</button>

                <!-- CSV操作（案件に紐付いたカレンダーのみ表示） -->
                <template v-if="props.project && !props.readonly">
                    <button @click="handleCsvExport" class="rounded border border-green-600 px-4 py-2 text-green-700 hover:bg-green-50">
                        CSV出力
                    </button>
                    <button @click="openCsvImportModal" class="rounded border border-indigo-600 px-4 py-2 text-indigo-700 hover:bg-indigo-50">
                        CSV取込
                    </button>
                </template>
            </template>
        </div>

        <!-- 週間プランナービュー -->
        <ProjectWeekPlanner
            v-if="currentView === 'week-planner'"
            :schedules="props.schedules"
            :project="props.project"
            :weekPostsUrl="props.weekPostsUrl"
        />

        <FullCalendar v-if="currentView === 'calendar'" ref="calendarRef" :options="calendarOptions" :events="plainCalendarEvents" />

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

        <!-- 日付クリックは直接メモモーダルを開く（select modal を廃止） -->

        <!-- スケジュールをクリックしたときのアクションモーダル（表示 / メモ作成） -->
        <div v-if="showScheduleActionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-xs rounded-lg bg-white p-6 text-center shadow-lg">
                <h2 class="mb-4 text-lg font-bold">スケジュール操作</h2>
                <div class="mb-4">選択中のスケジュール ID: {{ selectedScheduleForAction }}</div>
                <div class="flex flex-col gap-3">
                    <button @click="goToScheduleShowFromAction" class="rounded bg-blue-600 px-4 py-2 text-white">スケジュール表示</button>
                    <button @click="openMemoModalFromAction" class="rounded bg-green-600 px-4 py-2 text-white">メモ作成</button>
                    <button @click="showScheduleActionModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                </div>
            </div>
        </div>

        <!-- スケジュール用メモ作成モーダル（日時 + テキストのみ） -->
        <div v-if="showMemoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">メモ作成</h2>
                <div v-if="selectedScheduleIdForMemo" class="mb-2 text-sm text-gray-600">スケジュールID: {{ selectedScheduleIdForMemo }}</div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">日付</label>
                    <div class="flex items-center gap-2">
                        <input type="date" v-model="memoDate" class="rounded border p-2" />
                    </div>
                </div>

                <!-- 簡易予定作成モーダル（時間なし：タイトル・日付・メモ） -->
                <!-- NOTE: moved out of the surrounding memo modal so it can open independently -->
                <div class="mb-2">
                    <label class="block text-sm font-medium">メモ</label>
                    <textarea v-model="memoBody" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showMemoModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button type="button" @click="submitScheduleMemo" class="rounded bg-green-600 px-4 py-2 text-white">保存</button>
                </div>
            </div>
        </div>

        <!-- コメント編集モーダル -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">メモ編集</h2>
                <div class="mb-2 text-sm text-gray-600">コメントID: {{ editingCommentId }}</div>
                <div v-if="editingCommentAuthor" class="mb-2 text-sm text-gray-600">
                    作成者: {{ editingCommentAuthor.id }} - {{ editingCommentAuthor.name }}
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">日付</label>
                    <input type="date" v-model="editingCommentDate" class="rounded border p-2" />
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">メモ</label>
                    <textarea v-model="editingCommentBody" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showEditModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button
                        v-if="commentCanEdit({ id: editingCommentId })"
                        type="button"
                        @click="submitEditComment"
                        class="rounded bg-blue-600 px-4 py-2 text-white"
                    >
                        更新
                    </button>
                    <!-- Show delete only when the editing id corresponds to a project memo (server or local) and user has permission -->
                    <button
                        v-if="commentCanEdit({ id: editingCommentId })"
                        type="button"
                        @click="deleteEditingMemo"
                        class="rounded bg-red-600 px-4 py-2 text-white"
                    >
                        削除
                    </button>
                </div>
            </div>
        </div>
        <!-- 簡易予定作成モーダル（時間なし：タイトル・日付・メモ） - top-level -->
        <div v-if="showSimpleEventModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">予定作成</h2>
                <div class="mb-2">
                    <label class="block text-sm font-medium">タイトル</label>
                    <input type="text" v-model="simpleEventTitle" class="w-full rounded border p-2" />
                </div>
                <div class="mb-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">開始日</label>
                            <input type="date" v-model="simpleEventStartDate" class="w-full rounded border p-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium">終了日</label>
                            <input type="date" v-model="simpleEventEndDate" :min="simpleEventStartDate" class="w-full rounded border p-2" />
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">メモ</label>
                    <textarea v-model="simpleEventMemo" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">ラベル（色）</label>
                    <div class="mt-2 flex gap-2">
                        <button
                            v-for="c in simpleEventLabelChoices"
                            :key="c"
                            type="button"
                            @click="simpleEventLabel = c"
                            :aria-pressed="simpleEventLabel === c"
                            :style="{ backgroundColor: c }"
                            class="h-8 w-8 rounded-full border-2"
                            :class="simpleEventLabel === c ? 'ring-2 ring-indigo-400 ring-offset-1' : ''"
                        ></button>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showSimpleEventModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button type="button" @click="submitSimpleEvent" class="rounded bg-green-600 px-4 py-2 text-white">保存</button>
                </div>
            </div>
        </div>
        <!-- 予定詳細モーダル（Show / Edit / Delete） -->
        <div v-if="showScheduleShowModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">予定詳細</h2>
                <div class="mb-2">
                    <label class="block text-sm font-medium">タイトル</label>
                    <input
                        v-if="!isEditingSchedule"
                        type="text"
                        :value="scheduleShowData.title"
                        disabled
                        class="w-full rounded border bg-gray-50 p-2"
                    />
                    <input v-else type="text" v-model="scheduleEditTitle" class="w-full rounded border p-2" />
                </div>
                <div class="mb-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs text-gray-600">開始</label>
                        <input
                            v-if="!isEditingSchedule"
                            type="date"
                            :value="scheduleShowData.start"
                            disabled
                            class="w-full rounded border bg-gray-50 p-2"
                        />
                        <input v-else type="date" v-model="scheduleEditStart" class="w-full rounded border p-2" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">終了</label>
                        <input
                            v-if="!isEditingSchedule"
                            type="date"
                            :value="scheduleShowData.end"
                            disabled
                            class="w-full rounded border bg-gray-50 p-2"
                        />
                        <input v-else type="date" v-model="scheduleEditEnd" class="w-full rounded border p-2" />
                    </div>
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">説明</label>
                    <textarea
                        v-if="!isEditingSchedule"
                        :value="scheduleShowData.description"
                        class="w-full rounded border bg-gray-50 p-2"
                        rows="4"
                        disabled
                    ></textarea>
                    <textarea v-else v-model="scheduleShowData.description" class="w-full rounded border p-2" rows="4"></textarea>
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">ラベル</label>
                    <div class="mt-2 flex gap-2">
                        <button
                            v-for="c in simpleEventLabelChoices"
                            :key="c + '_show'"
                            type="button"
                            @click="isEditingSchedule ? (scheduleEditColor = c) : null"
                            :style="{ backgroundColor: c }"
                            class="h-8 w-8 rounded-full border-2"
                            :class="
                                (isEditingSchedule ? scheduleEditColor : scheduleShowData.color) === c ? 'ring-2 ring-indigo-400 ring-offset-1' : ''
                            "
                        ></button>
                    </div>
                </div>
                <!-- 完了状態バッジ -->
                <div v-if="scheduleShowData.completed_at" class="mb-2 flex items-center justify-between rounded bg-green-50 px-3 py-2">
                    <span class="text-sm font-medium text-green-700">✓ 完了済み</span>
                    <button
                        v-if="scheduleCanEdit(scheduleShowData.id) && !isEditingSchedule"
                        type="button"
                        @click="uncompleteSchedule"
                        class="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-600 hover:bg-gray-50"
                    >
                        未完了に戻す
                    </button>
                </div>

                <!-- 連携設定リンク -->
                <div v-if="(props.items ?? []).length > 0" class="mb-2">
                    <label class="block text-sm font-medium">連携設定に紐づける</label>
                    <div v-if="!isEditingSchedule" class="mt-1 text-sm text-gray-600">
                        {{ scheduleShowData.item_id
                            ? (props.items.find(i => i.id === scheduleShowData.item_id)?.name ?? '—')
                            : '—（未設定）' }}
                    </div>
                    <select v-else v-model="scheduleEditItemId"
                        class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none">
                        <option :value="null">—（未設定）</option>
                        <option v-for="item in props.items" :key="item.id" :value="item.id">
                            {{ item.sheet_name ? '[' + item.sheet_name + '] ' : '' }}
                            {{ item.parent_label ? item.parent_label + ' › ' : '' }}{{ item.name }}
                        </option>
                    </select>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="
                            showScheduleShowModal = false;
                            isEditingSchedule = false;
                        "
                        class="rounded bg-gray-300 px-4 py-2"
                    >
                        閉じる
                    </button>
                    <button
                        v-if="scheduleCanEdit(scheduleShowData.id) && !isEditingSchedule"
                        type="button"
                        @click="toggleEdit(true)"
                        class="rounded bg-blue-600 px-4 py-2 text-white"
                    >
                        編集
                    </button>
                    <button
                        v-if="scheduleCanEdit(scheduleShowData.id) && isEditingSchedule"
                        type="button"
                        @click="submitScheduleUpdate"
                        class="rounded bg-green-600 px-4 py-2 text-white"
                    >
                        保存
                    </button>
                    <button
                        v-if="scheduleCanEdit(scheduleShowData.id)"
                        type="button"
                        @click="deleteSchedule"
                        class="rounded bg-red-600 px-4 py-2 text-white"
                    >
                        削除
                    </button>
                </div>
            </div>
        </div>

        <!-- CSV インポートモーダル -->
        <div v-if="showCsvImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">CSVインポート</h2>
                <div class="mb-4 rounded bg-gray-50 p-3 text-sm text-gray-600">
                    <p class="font-medium mb-1">CSVファイルのフォーマット（1行目はヘッダー行）：</p>
                    <code class="block text-xs bg-gray-100 p-2 rounded">イベント名,開始日(YYYY-MM-DD),終了日(YYYY-MM-DD),メモ,色(#hex)</code>
                    <p class="mt-1 text-xs text-gray-500">※ 終了日・メモ・色は省略可</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">CSVファイルを選択</label>
                    <input
                        type="file"
                        accept=".csv,text/csv"
                        @change="onCsvFileChange"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:rounded file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-indigo-700"
                    />
                </div>
                <div v-if="csvImportErrors.length > 0" class="mb-4 rounded border border-red-300 bg-red-50 p-3">
                    <p class="text-sm font-medium text-red-700 mb-1">エラーがあります（全行キャンセルされます）：</p>
                    <ul class="text-sm text-red-600 list-disc pl-4">
                        <li v-for="err in csvImportErrors" :key="err">{{ err }}</li>
                    </ul>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showCsvImportModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button
                        type="button"
                        @click="submitCsvImport"
                        :disabled="csvImportLoading"
                        class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50"
                    >
                        {{ csvImportLoading ? '取込中...' : 'インポート実行' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/vue3';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import ProjectWeekPlanner from '@/Components/ProjectWeekPlanner.vue';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
    events: { type: [Array, Object], default: () => [] },
    comments: { type: Array, default: () => [] },
    memos: { type: Array, default: () => [] },
    project: { type: Object, default: null },
    items: { type: Array, default: () => [] },
    diaryLabel: { type: String, default: 'メモ' },
    readonly: { type: Boolean, default: false },
    showMemoButton: { type: Boolean, default: true },
    weekPostsUrl: { type: String, default: null },
    uniformColors: { type: Boolean, default: false },
});

const UC_NORMAL    = { bg: '#dbeafe', border: '#1d4ed8', text: '#1e3a8a' };
const UC_COMPLETED = { bg: '#dcfce7', border: '#15803d', text: '#14532d' };

// items prop はカレンダー連携用ドロップダウンのみに使用（独立イベントとして描画しない）

// 現在のビュー（月カレンダー or 週間プランナー）
const currentView = ref('calendar');

// 今日の日付を取得する関数
const getTodayString = () => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

// ---------- 日付ユーティリティ（UTC変換なし・ローカル時刻基準）----------
// inclusive な YYYY-MM-DD 文字列を FullCalendar 用 exclusive（翌日）に変換
const addOneDay = (dateStr) => {
    if (!dateStr) return null;
    const s = String(dateStr).split('T')[0];
    const [y, m, d] = s.split('-').map(Number);
    if (!y) return null;
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() + 1);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
};
// exclusive を inclusive に（FullCalendar の endStr → DB 保存値）
const subOneDay = (dateStr) => {
    if (!dateStr) return null;
    const s = String(dateStr).split('T')[0];
    const [y, m, d] = s.split('-').map(Number);
    if (!y) return null;
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() - 1);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
};
// Date オブジェクト → ローカル YYYY-MM-DD 文字列
const localDay = (d) => {
    if (!d) return null;
    const dt = d instanceof Date ? d : new Date(d);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
};
// -----------------------------------------------------------------------

const selectedDate = ref(getTodayString());
// ホバーポップアップ
const hoverPopup = ref({ show: false, x: 0, y: 0, title: '', startDate: '', endDate: '', description: '' });
// schedule action UI
const showScheduleActionModal = ref(false);
const selectedScheduleForAction = ref(null);
const showMemoModal = ref(false);
const selectedScheduleIdForMemo = ref(null);
const memoBody = ref('');
const memoDate = ref(getTodayString());

const showEditModal = ref(false);
const editingCommentId = ref(null);
const editingCommentBody = ref('');
const editingCommentDate = ref('');
const editingCommentAuthor = ref(null);

const startHourSelectRef = ref(null);

// schedule show/edit modal state
const showScheduleShowModal = ref(false);
const scheduleShowData = ref({ id: null, title: '', start: '', end: '', description: '', color: null, item_id: null, completed_at: null });
const isEditingSchedule = ref(false);
const scheduleEditTitle = ref('');
const scheduleEditStart = ref('');
const scheduleEditEnd = ref('');
const scheduleEditColor = ref('');
const scheduleEditItemId = ref(null);

const page = usePage();
// prefer server-provided helper flags if available on the user props
// Support multiple shapes: page.props.user (proxy), page.props.value.auth.user, page.props.value.user, page.props.auth.user
const userProps = computed(() => {
    try {
        const p = page.props;
        // direct proxy property used by some Inertia usages
        if (p && p.user) return p.user;
        // p may be a Ref-like with value containing auth/user
        if (p && p.value) {
            const v = p.value;
            if (v.auth && v.auth.user) return v.auth.user;
            if (v.user) return v.user;
        }
        // fallback shapes
        if (p && p.auth && p.auth.user) return p.auth.user;
    } catch (e) {
        // ignore and fall through
    }
    return {};
});
const scheduleCanEdit = (_id) => {
    const u = userProps.value || {};
    // Support multiple shapes: functions, boolean flags, and role string
    try {
        // If helper functions exist, prefer them
        if (typeof u.isSuperAdmin === 'function' && u.isSuperAdmin()) return true;
        if (typeof u.isAdmin === 'function' && u.isAdmin()) return true;
        if (typeof u.isLeader === 'function' && u.isLeader()) return true;
        if (typeof u.isCoordinator === 'function' && u.isCoordinator()) return true;
        if (typeof u.isUser === 'function') return !u.isUser();

        // Boolean flags
        if (u.isSuperAdmin === true || u.isAdmin === true || u.isLeader === true || u.isCoordinator === true) return true;
        if (u.isUser === true) return false;

        // Role string (normalize)
        if (u.user_role && typeof u.user_role === 'string') {
            const role = u.user_role.toLowerCase();
            if (['superadmin', 'admin', 'leader', 'coordinator'].includes(role)) return true;
            if (role === 'user') return false;
        }
    } catch (e) {
        // ignore and fall through
    }
    // additional heuristics: check common role shapes (role, role.name, roles array)
    try {
        // single role string
        if (u.role && typeof u.role === 'string') {
            const r = u.role.toLowerCase();
            if (['superadmin', 'admin', 'leader', 'coordinator'].includes(r)) return true;
        }
        if (u.role_name && typeof u.role_name === 'string') {
            const r = u.role_name.toLowerCase();
            if (['superadmin', 'admin', 'leader', 'coordinator'].includes(r)) return true;
        }
        // nested object role: { name: 'Admin' }
        if (u.role && typeof u.role === 'object' && (u.role.name || u.role.slug)) {
            const name = (u.role.name || u.role.slug || '').toLowerCase();
            if (['superadmin', 'admin', 'leader', 'coordinator'].includes(name)) return true;
        }
        // roles array (strings or objects)
        if (Array.isArray(u.roles) && u.roles.length > 0) {
            const ok = u.roles.some((r) => {
                try {
                    if (typeof r === 'string') return ['superadmin', 'admin', 'leader', 'coordinator'].includes(r.toLowerCase());
                    if (r && (r.name || r.slug)) return ['superadmin', 'admin', 'leader', 'coordinator'].includes((r.name || r.slug).toLowerCase());
                } catch (ee) {}
                return false;
            });
            if (ok) return true;
        }
        // common snake_case flags
        if (u.is_superadmin === true || u.is_admin === true || u.is_leader === true || u.is_coordinator === true) return true;
    } catch (ee) {
        // ignore
    }
    return false;
};

// Determine whether the current user can edit/delete a project memo
const commentCanEdit = (memo) => {
    const u = userProps.value || {};
    try {
        if (!memo) return false;
        if (typeof u.isSuperAdmin === 'function' && u.isSuperAdmin()) return true;
        if (typeof u.isAdmin === 'function' && u.isAdmin()) return true;
        if (typeof u.isLeader === 'function' && u.isLeader()) return true;
        if (typeof u.isCoordinator === 'function' && u.isCoordinator()) return true;
        if (u.id && memo && (memo.user_id === u.id || memo.id === editingCommentId.value)) return true;
        if (u.isSuperAdmin === true || u.isAdmin === true || u.isLeader === true || u.isCoordinator === true) return true;
        if (u.user_role && ['superadmin', 'admin', 'leader', 'coordinator'].includes(String(u.user_role).toLowerCase())) return true;
    } catch (e) {}
    return false;
};

// simple event modal state
const showSimpleEventModal = ref(false);
const simpleEventTitle = ref('');
const simpleEventIsRange = ref(false);
const simpleEventStartDate = ref(getTodayString());
const simpleEventEndDate = ref(getTodayString());
const simpleEventMemo = ref('');
const simpleEventLabel = ref('');
const simpleEventLabelChoices = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#6b7280'];
const calendarRef = ref(null);
// plain (non-proxied) events copy for FullCalendar to avoid Proxy/reactivity issues
const plainCalendarEvents = ref([]);
// minimal calendar ref; avoid aggressive polling
// If FullCalendar sometimes reports zero events immediately after mount,
// perform a single guarded addEventSource to ensure events render.
const didForceAddEvents = ref(false);

onMounted(() => {
    nextTick(() => {
        const now = new Date();
        const currentHour = String(now.getHours()).padStart(2, '0');
        if (startHourSelectRef.value) {
            const idx = Array.from(startHourSelectRef.value.options).findIndex((opt) => opt.value === currentHour);
            if (idx >= 0) startHourSelectRef.value.selectedIndex = idx;
        }
        // TEMP DEBUG: log resolved user props to help diagnose permission issue
        try {
            console.info('[ProjectCalendar] userProps for permission debug', userProps.value);
        } catch (e) {}
        // TEMP DEBUG: log incoming props and computed events length to diagnose missing schedules
        try {
            console.info('[ProjectCalendar] incoming props', {
                schedules: props.schedules,
                events: props.events && props.events.value ? props.events.value : props.events,
                memos: props.memos,
                comments: props.comments,
            });
        } catch (e) {}
        // Small delayed injection attempt in case API is already ready shortly after mount
        try {
            setTimeout(() => {
                try {
                    const apiNow = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;

                    if (apiNow && Array.isArray(plainCalendarEvents.value) && plainCalendarEvents.value.length > 0) {
                        try {
                            apiNow.getEventSources().forEach((s) => s.remove());
                            apiNow.addEventSource(JSON.parse(JSON.stringify(plainCalendarEvents.value)));

                            didForceAddEvents.value = true;
                        } catch (e) {
                            // ProjectCalendar onMounted inject error debug suppressed
                        }
                    }
                } catch (e) {
                    // ProjectCalendar immediate inject error debug suppressed
                }
            }, 300);
        } catch (e) {}
    });
});

function openEventModal(startDate = null, endDate = null) {
    simpleEventTitle.value = '';
    simpleEventMemo.value = '';
    const start = startDate || selectedDate.value || getTodayString();
    simpleEventStartDate.value = start;
    simpleEventEndDate.value = endDate || start;
    // 開始と終了が異なる場合は自動的に範囲モードON
    simpleEventIsRange.value = !!(endDate && endDate !== start);
    showSimpleEventModal.value = true;
}
function goToDiaryCreate() {
    // トップの「メモ作成」は今日の日付でモーダルを開く
    memoDate.value = getTodayString();
    selectedScheduleIdForMemo.value = null;
    memoBody.value = '';
    showMemoModal.value = true;
}

// local memos created client-side to show immediately and merge with server props
const localMemos = ref([]);
// local calendar entries to hold server-created/updated schedules without full reload
const localCalendarEntries = ref([]);

const calendarEvents = computed(() => {
    const list = [];
    // normalize incoming events prop which may be an Array or a Ref
    const rawEvents = (() => {
        try {
            if (!props.events) return [];
            if (Array.isArray(props.events)) return props.events;
            if (props.events.value && Array.isArray(props.events.value)) return props.events.value;
            // props.events might be a plain object with numeric keys
            return Array.isArray(props.events) ? props.events : [];
        } catch (e) {
            return [];
        }
    })();

    // scheduled events from props.events (preferred)
    rawEvents.forEach((event, idx, arr) => {
        // normalize common variations from different backends (schedules vs events)
        const title = event.title ?? event.name ?? event.summary ?? '';
        const startRaw = event.start ?? event.start_date ?? event.date ?? null;
        const endRaw = event.end ?? event.end_date ?? null;
        const allDay = event.allDay ?? event.all_day ?? false;

        // compute overlap based on normalized start/end when both exist
        let overlapCount = 0;
        if (startRaw && endRaw) {
            const evStart = new Date(startRaw).getTime();
            const evEnd = new Date(endRaw).getTime();
            overlapCount = arr.filter((ev, i) => {
                if (i === idx) return false;
                const sRaw = ev.start ?? ev.start_date ?? ev.date ?? null;
                const eRaw = ev.end ?? ev.end_date ?? null;
                if (!sRaw || !eRaw) return false;
                const s = new Date(sRaw).getTime();
                const e = new Date(eRaw).getTime();
                return evStart < e && evEnd > s;
            }).length;
        }
        const alpha = Math.max(1 - overlapCount * 0.2, 0.2);

        // prefer explicit color fields; fall back to generated rgba
        let color =
            event.backgroundColor ??
            event.background_color ??
            event.color ??
            event.color_hex ??
            event.label_color ??
            event.metadata?.color ??
            `rgba(37,99,235,${alpha})`;

        // If the title indicates completion (prefix), override with dark yellow
        const isLegacyCompleted = typeof title === 'string' && title.indexOf('【完了】') === 0;
        if (isLegacyCompleted) color = '#b58900';

        // schedlink/project_schedule 完了: extendedProps.completed_at があればグレー
        const completedAt = event.extendedProps?.completed_at ?? event.completed_at ?? null;
        if (completedAt) color = '#9ca3af';

        const displayTitle = completedAt
            ? (title.startsWith('✓ ') ? title : '✓ ' + title)
            : title;

        list.push({
            // include canonical `id` so FullCalendar and getEvents() return stable identifiers
            id: event.id ?? event.event_id ?? event.eventId ?? undefined,
            title: displayTitle,
            start: startRaw,
            end: endRaw ?? undefined,
            allDay: allDay,
            color: color,
            backgroundColor: color,
            borderColor: color,
            event_id: event.id ?? event.event_id,
            description: event.description ?? event.extendedProps?.description ?? '',
            extendedProps: {
                ...(event.extendedProps ?? {}),
                completed_at: completedAt,
                original_color: event.extendedProps?.original_color ?? event.color ?? null,
            },
        });
    });

    // If parent did NOT provide normalized events, fall back to mapping props.schedules
    const hasParentEvents = rawEvents && rawEvents.length > 0;
    if (!hasParentEvents) {
        // map legacy props.schedules when events not provided by parent
        (props.schedules ?? []).forEach((s) => {
            if (!s || !s.start_date) return;
            // normalize to date-only for allDay usage
            const fmt = (v) => {
                try {
                    return String(v).split('T')[0];
                } catch (e) {
                    return String(v);
                }
            };
            const startDateOnly = fmt(s.start_date);
            let endDateOnly = s.end_date ? fmt(s.end_date) : undefined;
            // FullCalendar treats allDay end as exclusive; add one day (ローカル日付加算でUTCズレ回避)
            if (endDateOnly) {
                endDateOnly = addOneDay(endDateOnly);
            }
            const isCompleted = !!s.completed_at || (s.progress ?? 0) >= 100;
            let schedBg, schedBorder;
            if (props.uniformColors) {
                const uc = isCompleted ? UC_COMPLETED : UC_NORMAL;
                schedBg = uc.bg; schedBorder = uc.border;
            } else {
                schedBg = isCompleted ? '#9ca3af' : (s.color ?? '#3b82f6');
                schedBorder = schedBg;
            }
            list.push({
                id: s.id,
                title: s.name ?? '',
                start: startDateOnly,
                end: endDateOnly ?? undefined,
                allDay: true,
                color: schedBorder,
                backgroundColor: schedBg,
                borderColor: schedBorder,
                event_id: s.id,
                description: s.description ?? '',
                extendedProps: { project_schedule_id: s.id, completed_at: s.completed_at ?? null, progress: s.progress ?? 0, original_color: s.color ?? '#3b82f6' },
            });
        });
    }

    // comments
    (props.comments ?? []).forEach((c) => {
        if (!c.date) return;
        list.push({
            id: `comment-${c.id}`,
            title: '🗒️',
            start: c.date,
            allDay: true,
            color: '#f59e42',
            backgroundColor: '#f59e42',
            borderColor: '#f59e42',
            extendedProps: { comment_id: c.id, project_schedule_id: c.project_schedule_id, body: c.body },
        });
    });

    // server memos (prefer color from server if present)
    (props.memos ?? []).forEach((m) => {
        if (!m.date) return;
        const memoColor = m.color ?? m.label_color ?? '#60a5fa';
        // prefer passing a local Date object at midnight to avoid FullCalendar interpreting UTC strings and shifting
        let startDate = m.date;
        try {
            const dateOnly = String(m.date).split('T')[0];
            const parts = dateOnly.split('-').map((x) => parseInt(x, 10));
            if (parts.length === 3 && parts.every((n) => !Number.isNaN(n))) startDate = new Date(parts[0], parts[1] - 1, parts[2]);
        } catch (e) {}
        list.push({
            id: `memo-${m.id}`,
            title: '📝',
            start: startDate,
            allDay: true,
            color: memoColor,
            backgroundColor: memoColor,
            borderColor: memoColor,
            extendedProps: { memo_id: m.id, project_id: m.project_id, body: m.body },
        });
    });

    // local client-created memos (avoid duplicates by id)
    localMemos.value.forEach((m) => {
        if (!m.date) return;
        // don't duplicate if server already returned same memo id
        const exists = list.some((ev) => ev.extendedProps && ev.extendedProps.memo_id === m.id);
        if (!exists) {
            const memoColor = m.color ?? '#60a5fa';
            // build local Date for start
            let startDate = m.date;
            try {
                const parts = String(m.date)
                    .split('-')
                    .map((x) => parseInt(x, 10));
                if (parts.length === 3 && parts.every((n) => !Number.isNaN(n))) startDate = new Date(parts[0], parts[1] - 1, parts[2]);
            } catch (e) {}
            list.push({
                id: `memo-local-${m.id}`,
                title: '📝',
                start: startDate,
                allDay: true,
                color: memoColor,
                backgroundColor: memoColor,
                borderColor: memoColor,
                extendedProps: { memo_id: m.id, project_id: m.project_id, body: m.body },
            });
        }
    });

    // Apply localCalendarEntries as overrides/additions: remove any existing items with same id then append local entries
    (localCalendarEntries.value || []).forEach((e) => {
        const eid = String(e.id ?? e.event_id ?? '');
        if (!eid) return;
        // remove existing items with same id
        for (let i = list.length - 1; i >= 0; i--) {
            const ev = list[i];
            const existingId = String(ev.event_id ?? ev.id ?? '');
            if (existingId === eid) list.splice(i, 1);
        }
        if (e.deleted) return; // skip deleted markers
        const eCompleted = !!e.completed_at || (e.progress ?? 0) >= 100;
        let eBg, eBorder;
        if (props.uniformColors) {
            const uc = eCompleted ? UC_COMPLETED : UC_NORMAL;
            eBg = uc.bg; eBorder = uc.border;
        } else {
            eBg = eCompleted ? '#9ca3af' : (e.color ?? '#3b82f6');
            eBorder = eBg;
        }
        list.push({
            id: e.id ?? e.event_id ?? undefined,
            title: e.name ?? e.title ?? '',
            start: e.start_date ?? e.start ?? e.date,
            // FullCalendar は end を exclusive で扱う → DB の inclusive end_date に +1日
            end: addOneDay(e.end_date ?? e.end) ?? undefined,
            allDay: true,
            color: eBorder,
            backgroundColor: eBg,
            borderColor: eBorder,
            event_id: e.id ?? e.event_id,
            description: e.description ?? e.body ?? '',
            extendedProps: { project_schedule_id: e.id ?? e.event_id, completed_at: e.completed_at ?? null, progress: e.progress ?? 0 },
        });
    });

    return list;
});

// Keep a plain JS copy of calendarEvents for the FullCalendar component.
// Some FullCalendar wrappers don't handle Vue proxies well; cloning to a plain array
// prevents the calendar from receiving an effectively-empty proxy at mount.
watch(
    calendarEvents,
    (val) => {
        try {
            if (!Array.isArray(val)) {
                plainCalendarEvents.value = [];
                return;
            }
            // prefer structuredClone to preserve Date objects; fallback to JSON cycle if not available
            try {
                if (typeof structuredClone === 'function') {
                    plainCalendarEvents.value = val.map((e) => structuredClone(e));
                } else {
                    plainCalendarEvents.value = val.map((e) => JSON.parse(JSON.stringify(e)));
                }
            } catch (e) {
                plainCalendarEvents.value = Array.isArray(val) ? val.slice() : [];
            }
        } catch (e) {
            plainCalendarEvents.value = Array.isArray(val) ? val.slice() : [];
        }
    },
    { immediate: true },
);

// If the FullCalendar API becomes available after mount and it has no events,
// push the plain events as an event source once.
watch(
    plainCalendarEvents,
    (events) => {
        try {
            // If API not ready, retry a few times with a short delay to allow FullCalendar to initialize
            const tryInject = (attempt = 0) => {
                try {
                    const apiNow = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
                    const current = apiNow && apiNow.getEvents ? apiNow.getEvents() : [];
                    if (
                        !didForceAddEvents.value &&
                        apiNow &&
                        (current == null || current.length === 0) &&
                        Array.isArray(events) &&
                        events.length > 0
                    ) {
                        try {
                            didForceAddEvents.value = true;
                            apiNow.getEventSources().forEach((s) => s.remove());
                            apiNow.addEventSource(events);
                            // debug

                            console.info('[ProjectCalendar] injected events into FullCalendar via retry', events.length);
                            return;
                        } catch (e) {
                            // ProjectCalendar plainCalendarEvents addEventSource error debug suppressed
                        }
                    }
                    // Not ready yet — schedule another attempt up to limit
                    const MAX = 12; // ~2.4s max
                    if (attempt < MAX) {
                        setTimeout(() => tryInject(attempt + 1), 200);
                    }
                } catch (e) {
                    // ProjectCalendar plainCalendarEvents retry error debug suppressed
                }
            };
            tryInject(0);
        } catch (e) {
            // ProjectCalendar plainCalendarEvents watcher error debug suppressed
        }
    },
    { immediate: true },
);

// watch calendarEvents for debugging - logs initial and subsequent values
watch(
    calendarEvents,
    (_val) => {
        try {
            // no-op: rely on FullCalendar's eventsSet and :events binding
        } catch (e) {
            // ProjectCalendar calendarEvents watch error debug suppressed
        }
    },
    { immediate: true },
);

// Modify calendarOptions to attach eventDidMount for native tooltip
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'ja',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth' },
    selectable: true,
    firstDay: 1,
    weekText: '\u9031',
    dayHeaderFormat: { weekday: 'short' },
    height: 'auto',
    editable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    eventDrop: async function (info) {
        const ev = info.event;
        const fmtLocal = (d) => {
            if (!d) return null;
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        };
        const newStart = ev.start ? fmtLocal(ev.start) : null;
        // allDay の end は exclusive なので -1日して inclusive に
        let newEnd = null;
        if (ev.end) {
            const d = new Date(ev.end);
            d.setDate(d.getDate() - 1);
            newEnd = fmtLocal(d);
        }

        const id =
            ev.extendedProps?.schedule_id ||
            ev.extendedProps?.project_schedule_id ||
            ev.extendedProps?.event_id ||
            ev.id ||
            null;

        if (!id) {
            alert('スケジュールIDが取得できないため移動を保存できませんでした');
            info.revert();
            return;
        }

        // memo / comment は移動不可
        if (String(id).startsWith('memo') || String(id).startsWith('comment')) {
            info.revert();
            return;
        }

        try {
            const url = route('coordinator.project_schedules.update', { project_schedule: id });
            await axios.patch(url, {
                start_date: newStart,
                end_date: newEnd || newStart,
            });
            // localCalendarEntries を更新して computed が最新を返すように
            const idx = localCalendarEntries.value.findIndex((x) => String(x.id) === String(id));
            const updated = { id, start_date: newStart, end_date: newEnd || newStart, name: ev.title, color: ev.backgroundColor };
            if (idx !== -1) localCalendarEntries.value.splice(idx, 1, updated);
            else localCalendarEntries.value.push(updated);
        } catch (e) {
            console.error('eventDrop save error', e);
            alert('予定の移動保存に失敗しました');
            info.revert();
        }
    },
    eventResize: async function (info) {
        const newStart = info.event.start;
        const newEnd = info.event.end;
        const fmtDateOnly = (d) => {
            if (!d) return null;
            const yyyy = d.getFullYear ? d.getFullYear() : new Date(d).getFullYear();
            const mm = String((d.getMonth ? d.getMonth() : new Date(d).getMonth()) + 1).padStart(2, '0');
            const dd = String(d.getDate ? d.getDate() : new Date(d).getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        };
        const displayStart = fmtDateOnly(newStart);
        let displayEndInclusive = null;
        if (newEnd) {
            if (info.event.allDay) {
                const d = new Date(newEnd);
                d.setDate(d.getDate() - 1);
                displayEndInclusive = fmtDateOnly(d);
            } else {
                displayEndInclusive = fmtDateOnly(newEnd);
            }
        }

        try {
            const ev = info.event;
            const extended = ev.extendedProps || {};
            const defExt = ev._def && ev._def.extendedProps ? ev._def.extendedProps : null;

            const id =
                extended.project_schedule_id ||
                extended.schedule_id ||
                extended.project_schedule ||
                (defExt && (defExt.project_schedule_id || defExt.schedule_id)) ||
                ev.id ||
                (ev._def && ev._def.publicId) ||
                null;

            if (id && !String(id).startsWith('memo') && !String(id).startsWith('comment')) {
                // プロジェクトスケジュール
                const url = route('coordinator.project_schedules.update', { project_schedule: id });
                const payload = {
                    start_date: displayStart,
                    end_date: displayEndInclusive || displayStart,
                };
                await axios.patch(url, payload);
                // localCalendarEntries を更新
                const idx = localCalendarEntries.value.findIndex((x) => String(x.id) === String(id));
                const updated = { id, start_date: displayStart, end_date: displayEndInclusive || displayStart, name: ev.title, color: ev.backgroundColor };
                if (idx !== -1) localCalendarEntries.value.splice(idx, 1, updated);
                else localCalendarEntries.value.push(updated);
            } else if (extended.event_id) {
                // 汎用イベント（個人カレンダー）
                await axios.put(`/events/${extended.event_id}/calendar`, {
                    date: displayStart,
                    startHour: newStart ? String(newStart.getHours()).padStart(2, '0') : undefined,
                    startMinute: newStart ? String(newStart.getMinutes()).padStart(2, '0') : undefined,
                    endHour: newEnd ? String(newEnd.getHours()).padStart(2, '0') : undefined,
                    endMinute: newEnd ? String(newEnd.getMinutes()).padStart(2, '0') : undefined,
                });
            } else {
                info.revert();
            }
        } catch (e) {
            console.error('eventResize error', e);
            alert('予定の更新に失敗しました');
            info.revert();
        }
    },
    eventDidMount: function (info) {
        // Lightweight styling: prefer event's backgroundColor/color provided by parent
        try {
            if (info.event.extendedProps && info.event.extendedProps.body) {
                info.el.setAttribute('title', info.event.extendedProps.body);
                info.el.style.cursor = 'pointer';
            }
            try {
                info.el.classList.add('sb-event');
            } catch (e) {}

            const bg     = info.event.backgroundColor || info.event.color || info.event.extendedProps?.color || null;
            const border = info.event.borderColor || bg;
            if (bg) {
                // simple contrast: pick white or near-black text based on background luminance
                let text = '#ffffff';
                try {
                    if (typeof bg === 'string' && bg.startsWith('#') && bg.length === 7) {
                        const r = parseInt(bg.slice(1, 3), 16);
                        const g = parseInt(bg.slice(3, 5), 16);
                        const b = parseInt(bg.slice(5, 7), 16);
                        const lum = 0.2126 * (r / 255) + 0.7152 * (g / 255) + 0.0722 * (b / 255);
                        text = lum < 0.6 ? '#ffffff' : '#111827';
                    }
                } catch (cErr) {}
                try {
                    info.el.style.backgroundColor = bg;
                    info.el.style.borderColor = border;
                    info.el.style.color = text;
                } catch (sErr) {}
            }
        } catch (e) {
            // ignore
        }
    },
    eventContent: props.uniformColors ? function (arg) {
        const ext = arg.event.extendedProps || {};
        if (!ext.project_schedule_id && !ext.schedule_id) return null;
        const safe = (s) => String(s ?? '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const title = safe(arg.event.title);
        const completed = !!ext.completed_at;
        const progress  = ext.progress ?? 0;
        const meta = completed ? '完了' : (progress > 0 ? progress + '%' : '');
        return {
            html: `<div class="fc-event-inner-uni" title="${title}">
                ${meta ? `<div class="fc-event-meta-uni">${safe(meta)}</div>` : ''}
                <div class="fc-event-name-uni">${title}</div>
            </div>`,
        };
    } : undefined,
    eventsSet: function (events) {
        try {
            const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
            const localCount = Array.isArray(calendarEvents?.value) ? calendarEvents.value.length : 0;
            // if FullCalendar has no events but our computed list has items, add as a source once
            if (api && !didForceAddEvents.value && (events == null || events.length === 0) && localCount > 0) {
                try {
                    didForceAddEvents.value = true;
                    api.getEventSources().forEach((s) => s.remove());
                    api.addEventSource(calendarEvents.value);
                } catch (e) {
                    // guarded eventsSet addEventSource error debug suppressed
                }
            }
        } catch (e) {
            // eventsSet error debug suppressed
        }
    },
    eventMouseEnter: function (info) {
        const ev = info.event;
        const ext = ev.extendedProps || {};
        const title = ev.title || '';
        const description = ext.description || ext.body || '';
        const startStr = ev.startStr ? ev.startStr.split('T')[0] : '';
        let endDate = '';
        if (ev.end) {
            const d = new Date(ev.end);
            d.setDate(d.getDate() - 1);
            endDate = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }
        const rect = info.el.getBoundingClientRect();
        const mx = info.jsEvent ? info.jsEvent.clientX : rect.left;
        const my = info.jsEvent ? info.jsEvent.clientY : rect.bottom;
        hoverPopup.value = {
            show: true,
            x: Math.min(mx + 12, window.innerWidth - 290),
            y: Math.min(my + 16, window.innerHeight - 120),
            title,
            startDate: startStr,
            endDate,
            description,
        };
    },
    eventMouseLeave: function () {
        hoverPopup.value.show = false;
    },
    eventClick: function (info) {
        // comment click -> open edit modal for comment
        if (info.event.extendedProps.comment_id) {
            // Prevent navigation; open inline modal
            // Find comment data from props.comments
            const cid = info.event.extendedProps.comment_id;
            const comment = (props.comments || []).find((c) => c.id === cid) || {
                id: cid,
                body: info.event.extendedProps.body || '',
                date: info.event.startStr ? info.event.startStr.split('T')[0] : info.event.start,
            };
            openEditModalForComment(comment);
            return;
        }
        // memo click -> open edit modal for project memo
        if (info.event.extendedProps.memo_id) {
            const mid = info.event.extendedProps.memo_id;
            const memo = (props.memos || []).find((m) => m.id === mid) ||
                (localMemos.value || []).find((m) => m.id === mid) || {
                    id: mid,
                    body: info.event.extendedProps.body || '',
                    date: info.event.startStr ? info.event.startStr.split('T')[0] : info.event.start,
                };
            openEditModalForComment({ id: memo.id, body: memo.body, date: memo.date, author: memo.author || null });
            return;
        }
        // For other events (schedules/personal events) open a modal showing details
        if (!info.event.extendedProps.comment_id && !info.event.extendedProps.memo_id) {
            try {
                console.info('[ProjectCalendar] eventClick — clicked event core fields', {
                    id: info.event.id,
                    event_id: info.event.event_id,
                    extendedProps: info.event.extendedProps,
                    defExtendedProps: info.event._def && info.event._def.extendedProps ? info.event._def.extendedProps : null,
                });
            } catch (e) {}
            openScheduleShowModal(info.event);
        }
    },
    select: handleDateSelect,
}));

// 日付選択ハンドラー関数を定義
function handleDateSelect(selectInfo) {
    const startStr = selectInfo.startStr ? selectInfo.startStr.split('T')[0] : null;
    // allDay の end は exclusive なので -1日して inclusive に
    let endStr = null;
    if (selectInfo.endStr) {
        const endRaw = selectInfo.endStr.split('T')[0];
        const d = new Date(endRaw);
        d.setDate(d.getDate() - 1);
        endStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
    selectedDate.value = startStr;
    openEventModal(startStr, endStr);
}

function goToScheduleShowFromAction() {
    if (!selectedScheduleForAction.value) return;
    showScheduleActionModal.value = false;
    router.get(route('coordinator.project_schedules.show', { project_schedule: selectedScheduleForAction.value }));
}


function openMemoModalFromAction() {
    if (!selectedScheduleForAction.value) return;
    selectedScheduleIdForMemo.value = selectedScheduleForAction.value;
    showScheduleActionModal.value = false;
    showMemoModal.value = true;
}

function openEditModalForComment(comment) {
    editingCommentId.value = comment.id;
    editingCommentBody.value = comment.body || '';
    editingCommentDate.value = comment.date || memoDate.value;
    // set author if provided by server; if not, try to find in props.memos
    editingCommentAuthor.value = comment.author || null;
    if (!editingCommentAuthor.value && comment.id) {
        const found = (props.memos || []).find((m) => m.id === comment.id);
        if (found && found.author) editingCommentAuthor.value = found.author;
    }
    showEditModal.value = true;
}

function openScheduleShowModal(event) {
    try {
        console.info('[ProjectCalendar] openScheduleShowModal — event core fields', {
            id: event.id,
            event_id: event.event_id,
            extendedProps: event.extendedProps,
            defExtendedProps: event._def && event._def.extendedProps ? event._def.extendedProps : null,
            publicId: event._def && event._def.publicId ? event._def.publicId : null,
        });
    } catch (e) {}
    scheduleShowData.value.id =
        event.extendedProps.project_schedule_id ||
        event.extendedProps.schedule_id ||
        event.extendedProps.event_id ||
        event.id ||
        null;
    scheduleShowData.value.title = event.title || '';
    // addOneDay/subOneDay はスクリプトトップで定義済みのグローバルヘルパーを使用
    scheduleShowData.value.start = event.startStr ? event.startStr.split('T')[0] : localDay(event.start);
    // endStr は exclusive (翌日) → subOneDay して inclusive (DB保存値) に
    const endExclStr = event.endStr ? event.endStr.split('T')[0] : (localDay(event.end) || null);
    scheduleShowData.value.end = endExclStr ? subOneDay(endExclStr) : scheduleShowData.value.start;
    // prefer description from several possible locations on the clicked event
    const extractDescFromEvent = (ev) => {
        if (!ev) return null;
        const tries = [];
        // common direct fields
        tries.push(ev.description ?? null);
        tries.push(ev.extendedProps && ev.extendedProps.description ? ev.extendedProps.description : null);
        tries.push(ev.extendedProps && ev.extendedProps.body ? ev.extendedProps.body : null);
        tries.push(ev.body ?? null);
        // metadata / meta variations
        tries.push(ev.metadata && ev.metadata.description ? ev.metadata.description : null);
        tries.push(
            ev.extendedProps && ev.extendedProps.metadata && ev.extendedProps.metadata.description ? ev.extendedProps.metadata.description : null,
        );
        tries.push(ev.extendedProps && ev.extendedProps.meta && ev.extendedProps.meta.description ? ev.extendedProps.meta.description : null);
        // note / comment like fields
        tries.push(ev.note ?? null);
        tries.push(ev.extendedProps && ev.extendedProps.note ? ev.extendedProps.note : null);
        for (const t of tries) {
            if (t !== undefined && t !== null && String(t).trim() !== '') return String(t);
        }
        return null;
    };

    scheduleShowData.value.description = extractDescFromEvent(event) || '';

    // If description missing, attempt to find a matching schedule object with description
    if (!scheduleShowData.value.description) {
        try {
            const findInList = (list, _listName = '') => {
                if (!Array.isArray(list)) return null;
                const wantId = scheduleShowData.value.id ? String(scheduleShowData.value.id) : null;
                const wantTitle = (scheduleShowData.value.title || '').toLowerCase().trim();
                const wantStart = scheduleShowData.value.start || null;

                const normalizeDate = (d) => {
                    if (!d) return null;
                    try {
                        if (typeof d === 'string') return d.split('T')[0];
                        const dt = new Date(d);
                        return dt.toISOString().split('T')[0];
                    } catch (e) {
                        return String(d);
                    }
                };

                // debug suppressed for findInList searching

                for (const ev of list) {
                    try {
                        // extract candidate id robustly
                        let evId = null;
                        if (ev) {
                            if (ev.id !== undefined && ev.id !== null) evId = String(ev.id);
                            else if (ev.event_id !== undefined && ev.event_id !== null) evId = String(ev.event_id);
                            else if (
                                ev.extendedProps &&
                                (ev.extendedProps.project_schedule_id !== undefined || ev.extendedProps.schedule_id !== undefined)
                            ) {
                                evId = String(ev.extendedProps.project_schedule_id ?? ev.extendedProps.schedule_id);
                            }
                        }

                        const desc = ev.description ?? ev.extendedProps?.description ?? ev.body ?? null;
                        if (wantId && evId && wantId === evId) {
                            if (desc) {
                                try {
                                    // findInList matched by id debug suppressed
                                } catch (e) {}
                                return desc;
                            }
                        }

                        // fallback: title+start match with normalized dates
                        const evTitle = (ev.title || ev.name || '').toString().toLowerCase().trim();
                        const evStartRaw = ev.start ?? ev.start_date ?? ev.date ?? null;
                        const evStart = normalizeDate(evStartRaw);
                        const wantStartNorm = normalizeDate(wantStart);
                        if (wantTitle && evTitle && wantTitle === evTitle && wantStartNorm && evStart && wantStartNorm === evStart) {
                            if (desc) {
                                try {
                                    // findInList matched by title+start debug suppressed
                                } catch (e) {}
                                return desc;
                            }
                        }
                    } catch (e) {}
                }
                return null;
            };

            // check local overrides first
            let found = findInList(localCalendarEntries.value || []);
            if (!found) found = findInList(calendarEvents.value || []);
            if (!found) found = findInList(props.events && props.events.value ? props.events.value : props.events);
            if (!found && Array.isArray(props.schedules)) found = findInList(props.schedules);
            if (found) scheduleShowData.value.description = found;
            else {
                // debug: emit small samples so developer can inspect why lookup failed
                try {
                    // debug suppressed: lookup failed samples removed
                } catch (e) {}
            }
        } catch (e) {
            // ignore
        }
    }
    scheduleShowData.value.color = event.backgroundColor || event.color || null;
    scheduleShowData.value.completed_at = event.extendedProps?.completed_at ?? null;
    // prep edit fields
    scheduleEditTitle.value = scheduleShowData.value.title.replace(/^✓ /, '');
    scheduleEditStart.value = scheduleShowData.value.start;
    scheduleEditEnd.value = scheduleShowData.value.end;
    scheduleEditColor.value = scheduleShowData.value.color;
    isEditingSchedule.value = false;
    showScheduleShowModal.value = true;
    try {
        console.info('[ProjectCalendar] openScheduleShowModal', { schedule: scheduleShowData.value, isEditingSchedule: isEditingSchedule.value });
    } catch (e) {}
}

async function uncompleteSchedule() {
    const id = scheduleShowData.value.id;
    if (!id) return;
    try {
        await axios.patch(route('coordinator.project_schedules.uncomplete', { project_schedule: id }));
        showScheduleShowModal.value = false;
        router.reload();
    } catch (e) {
        console.error('uncompleteSchedule error', e);
        alert('未完了への変更に失敗しました');
    }
}

function toggleEdit(enable) {
    try {
        console.info('[ProjectCalendar] toggleEdit requested', { enable, before: isEditingSchedule.value });
    } catch (e) {}
    isEditingSchedule.value = !!enable;
    if (enable) {
        scheduleEditTitle.value = scheduleShowData.value.title;
        scheduleEditStart.value = scheduleShowData.value.start;
        scheduleEditEnd.value = scheduleShowData.value.end;
        scheduleEditColor.value = scheduleShowData.value.color;
        scheduleEditItemId.value = scheduleShowData.value.item_id ?? null;
        try {
            console.info('[ProjectCalendar] toggleEdit entered edit mode', {
                title: scheduleEditTitle.value,
                start: scheduleEditStart.value,
                end: scheduleEditEnd.value,
            });
        } catch (e) {}
    } else {
        try {
            console.info('[ProjectCalendar] toggleEdit exited edit mode', { isEditingSchedule: isEditingSchedule.value });
        } catch (e) {}
    }
}

async function submitScheduleUpdate() {
    if (!scheduleEditTitle.value || scheduleEditTitle.value.trim() === '') {
        alert('タイトルを入力してください');
        return;
    }
    try {
        const payload = {
            name: scheduleEditTitle.value,
            start_date: scheduleEditStart.value,
            end_date: scheduleEditEnd.value,
            color: scheduleEditColor.value || null,
            description: scheduleShowData.value && scheduleShowData.value.description ? scheduleShowData.value.description : '',
            project_job_item_id: scheduleEditItemId.value || null,
        };
        // use coordinator project_schedules update endpoint if available
        // determine id robustly from several possible shapes (event, extendedProps, legacy names)
        let id = null;
        const sd = scheduleShowData.value || {};
        const candidates = [
            sd.id,
            sd.event_id,
            sd.eventId,
            sd.schedule_id,
            sd.project_schedule_id,
            sd.projectScheduleId,
            sd.projectScheduleID,
            sd.extendedProps && sd.extendedProps.project_schedule_id,
            sd.extendedProps && sd.extendedProps.event_id,
            sd.extendedProps && sd.extendedProps.eventId,
            sd.extendedProps && sd.extendedProps.schedule_id,
        ];
        for (const c of candidates) {
            if (c !== undefined && c !== null) {
                const s = String(c).trim();
                if (!(s === '' || s.toLowerCase() === 'undefined' || s.toLowerCase() === 'null')) {
                    // strip common prefixes like "memo-" or "comment-" to try to get raw id
                    const m = s.match(/^(?:memo-|comment-|memo-local-)?(.+)$/);
                    id = m && m[1] ? m[1] : s;
                    break;
                }
            }
        }
        // If no id found yet, try to locate by matching title/start/end against known event lists
        if (!id) {
            try {
                console.warn('[ProjectCalendar] submitScheduleUpdate no id in scheduleShowData, attempting lookup', scheduleShowData.value);
                const normalizeDate = (d) => {
                    if (!d) return null;
                    try {
                        return String(d).split('T')[0];
                    } catch (e) {
                        return String(d);
                    }
                };
                const wantTitle = (scheduleEditTitle.value || scheduleShowData.value.title || '').trim();
                const wantStart = scheduleEditStart.value || scheduleShowData.value.start || null;
                const wantEnd = scheduleEditEnd.value || scheduleShowData.value.end || null;

                const tryMatch = (list) => {
                    if (!Array.isArray(list)) return null;
                    const wantTitleLower = (wantTitle || '').toLowerCase().trim();
                    for (const ev of list) {
                        try {
                            const evTitleRaw = ev.title || ev.name || '';
                            const evTitle = evTitleRaw ? String(evTitleRaw).trim() : '';
                            const evTitleLower = evTitle.toLowerCase();
                            const evStart = normalizeDate(ev.start ?? ev.start_date ?? ev.date);
                            const evEndRaw = ev.end ?? ev.end_date ?? undefined;
                            const evEnd = evEndRaw ? normalizeDate(evEndRaw) : null;

                            // prefer ids from several possible fields on event and extendedProps
                            const candidateId =
                                (ev.extendedProps &&
                                    (ev.extendedProps.project_schedule_id || ev.extendedProps.event_id || ev.extendedProps.schedule_id)) ||
                                ev.schedule_id ||
                                ev.event_id ||
                                ev.id ||
                                null;

                            // 1) title (case-insensitive) + start exact match
                            if (wantTitleLower && evTitleLower && wantTitleLower === evTitleLower) {
                                if (wantStart && evStart && normalizeDate(wantStart) === evStart) return candidateId;
                                // if start not specified, accept title match
                                if (!wantStart) return candidateId;
                            }

                            // 2) fallback: start+end both match (dates normalized)
                            if (wantStart && evStart && normalizeDate(wantStart) === evStart) {
                                if (wantEnd && evEnd && normalizeDate(wantEnd) === evEnd) return candidateId;
                                // if event has no end or end not specified, still accept start-only match
                                if (!wantEnd) return candidateId;
                            }

                            // 3) last resort: title includes/startsWith or vice versa
                            if (wantTitleLower && evTitleLower && (evTitleLower.includes(wantTitleLower) || wantTitleLower.includes(evTitleLower))) {
                                return candidateId;
                            }
                        } catch (e) {}
                    }
                    return null;
                };

                // try computed list first
                id =
                    tryMatch(calendarEvents.value) ||
                    tryMatch(plainCalendarEvents.value) ||
                    tryMatch(props.events && props.events.value ? props.events.value : props.events);
                // fallback to props.schedules raw objects
                if (!id && Array.isArray(props.schedules)) {
                    for (const s of props.schedules) {
                        try {
                            const sTitle = (s.name || s.title || '').trim();
                            const sStart = normalizeDate(s.start_date || s.date || s.start);
                            if (wantTitle && sTitle && wantTitle === sTitle && wantStart && sStart && normalizeDate(wantStart) === sStart) {
                                id = s.id;
                                break;
                            }
                        } catch (e) {}
                    }
                }
                if (id) {
                    console.info('[ProjectCalendar] submitScheduleUpdate resolved id by lookup', id);
                } else {
                    console.error('[ProjectCalendar] submitScheduleUpdate failed to resolve id after lookup', scheduleShowData.value);
                }
            } catch (e) {
                console.error('[ProjectCalendar] submitScheduleUpdate lookup error', e);
            }
        }
        if (!id) {
            alert('保存に失敗しました: スケジュールIDが見つかりません');
            throw new Error('Schedule id missing');
        }
        const url = route('coordinator.project_schedules.update', { project_schedule: id });
        // debug: log resolved id, URL and payload to help diagnose 404 from server
        try {
            console.info('[ProjectCalendar] submitScheduleUpdate resolved id', id, 'url', url, 'payload', payload);
        } catch (e) {}
        // defensive: do not attempt request if url would contain 'undefined'
        if (String(url).includes('undefined')) {
            try {
                console.error('[ProjectCalendar] submitScheduleUpdate aborting: url contains undefined', { id, url });
            } catch (e) {}
            throw new Error('Invalid update URL, aborting');
        }
        let resp = null;
        try {
            resp = await axios.patch(url, payload);
        } catch (err) {
            // If Ziggy-produced URL yields 404, try explicit coordinator path fallback
            try {
                const status = err && err.response && err.response.status ? err.response.status : null;

                console.warn('[ProjectCalendar] submitScheduleUpdate first attempt failed', { url, status, err });
            } catch (ee) {}
            if (err && err.response && err.response.status === 404) {
                const explicit = `/coordinator/project_schedules/${id}`;
                try {
                    console.info('[ProjectCalendar] submitScheduleUpdate trying explicit URL', explicit);
                    resp = await axios.patch(explicit, payload);
                } catch (err2) {
                    try {
                        console.error('[ProjectCalendar] submitScheduleUpdate explicit attempt failed', { explicit, err2 });
                    } catch (eee) {}
                    throw err2;
                }
            } else {
                throw err;
            }
        }
        if (resp && resp.data && resp.data.schedule) {
            // debug: log server response to verify description and returned schedule
            try {
                console.info('[ProjectCalendar] submitScheduleUpdate response', resp.data);
            } catch (e) {}
            const s = resp.data.schedule;
            // replace or add in localCalendarEntries
            const idx = localCalendarEntries.value.findIndex((x) => String(x.id) === String(s.id));
            if (idx !== -1) localCalendarEntries.value.splice(idx, 1, s);
            else localCalendarEntries.value.push(s);

            // Try to update FullCalendar directly so the UI reflects the change immediately.
            try {
                const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
                if (api) {
                    const ev = api.getEventById(String(s.id));
                    const eventData = {
                        id: s.id,
                        title: s.name ?? s.title ?? '',
                        start: s.start_date ?? s.start ?? null,
                        end: s.end_date ?? s.end ?? null,
                        allDay: true,
                        backgroundColor: s.color ?? s.backgroundColor ?? null,
                        borderColor: s.color ?? s.backgroundColor ?? null,
                        color: s.color ?? s.backgroundColor ?? null,
                    };
                    if (ev) {
                        // update props on the existing event
                        try {
                            ev.setProp('title', eventData.title);
                        } catch (e) {}
                        try {
                            // end は exclusive（+1日）で渡す
                            ev.setDates(eventData.start || null, addOneDay(eventData.end) || null, { allDay: true });
                        } catch (e) {}
                        try {
                            ev.setExtendedProp('description', s.description ?? '');
                        } catch (e) {}
                        try {
                            ev.setProp('backgroundColor', eventData.backgroundColor);
                            ev.setProp('borderColor', eventData.borderColor);
                            ev.setProp('color', eventData.color);
                        } catch (e) {}
                    } else {
                        // event not present in FC yet — add it
                        try {
                            api.addEvent({ ...eventData, end: addOneDay(eventData.end) });
                        } catch (e) {}
                    }
                }
            } catch (e) {
                console.error('[ProjectCalendar] fullcalendar update error', e);
            }
        }
        showScheduleShowModal.value = false;
        isEditingSchedule.value = false;
    } catch (e) {
        console.error('submitScheduleUpdate error', e);
        alert('予定の更新に失敗しました');
    }
}

async function deleteSchedule() {
    // 確認なしで削除
    try {
        const id = scheduleShowData.value.id;
        if (!id) throw new Error('Schedule id missing');
        const url = route('coordinator.project_schedules.destroy', { project_schedule: id });
        await axios.delete(url);

        // FullCalendar から直接削除
        try {
            const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
            if (api) {
                const ev = api.getEventById(String(id));
                if (ev) ev.remove();
            }
        } catch (e) {}

        // localCalendarEntries からも除去
        const idx = localCalendarEntries.value.findIndex((x) => String(x.id) === String(id));
        if (idx !== -1) {
            localCalendarEntries.value.splice(idx, 1);
        } else {
            localCalendarEntries.value.push({ id: id, deleted: true });
        }

        showScheduleShowModal.value = false;
        isEditingSchedule.value = false;
    } catch (e) {
        console.error('deleteSchedule error', e);
        alert('予定の削除に失敗しました');
    }
}

async function submitEditComment() {
    if (!editingCommentBody.value || editingCommentBody.value.trim() === '') {
        alert('メモの内容を入力してください');
        return;
    }
    try {
        // Try to call named route via ziggy if available, otherwise fallback to URL
        const url = route('coordinator.project_schedule_comments.update', { comment: editingCommentId.value });
        await axios.put(url, {
            body: editingCommentBody.value,
            metadata: { date: editingCommentDate.value },
        });
        // After successful update, reload page or re-fetch via Inertia to refresh props/events
        // (Avoid mutating undefined local `events` variable)
        router.reload();
        showEditModal.value = false;
        editingCommentId.value = null;
        // Optionally refresh page or keep local state
    } catch (e) {
        console.error('submitEditComment error', e);
        alert('メモの更新に失敗しました');
    }
}

async function deleteEditingMemo() {
    if (!editingCommentId.value) return;
    if (!confirm('このメモを削除しますか？')) return;
    try {
        // use named route to handle base path correctly (e.g. /members prefix on Sakura)
        const url = route('project_memos.destroy', { memo: editingCommentId.value });
        await axios.delete(url);
        showEditModal.value = false;
        editingCommentId.value = null;
        // refresh to get server-provided memos
        router.reload();
    } catch (e) {
        console.error('deleteEditingMemo error', e);
        alert('メモの削除に失敗しました');
    }
}

async function submitScheduleMemo() {
    // If a schedule id is set, post to the schedule comments store
    if (selectedScheduleIdForMemo.value) {
        if (!memoBody.value || memoBody.value.trim() === '') {
            alert('メモの内容を入力してください');
            return;
        }
        try {
            const resp = await axios.post(
                route('coordinator.project_schedule_comments.store', { project_schedule: selectedScheduleIdForMemo.value }),
                {
                    body: memoBody.value,
                    metadata: { date: memoDate.value },
                },
            );
            // Attempt to immediately reflect the created comment on the calendar.
            try {
                const c = resp && resp.data && resp.data.comment ? resp.data.comment : null;
                const commentObj = c
                    ? {
                          id: `comment-${c.id}`,
                          title: '🗒️',
                          start: c.date || memoDate.value,
                          allDay: true,
                          color: '#f59e42',
                          backgroundColor: '#f59e42',
                          borderColor: '#f59e42',
                          extendedProps: { comment_id: c.id, project_schedule_id: c.project_schedule_id, body: c.body },
                      }
                    : {
                          id: `comment-temp-${Date.now()}`,
                          title: '🗒️',
                          start: memoDate.value,
                          allDay: true,
                          color: '#f59e42',
                          backgroundColor: '#f59e42',
                          borderColor: '#f59e42',
                          extendedProps: { comment_id: null, project_schedule_id: selectedScheduleIdForMemo.value, body: memoBody.value },
                      };

                // push into localCalendarEntries so our computed calendarEvents picks it up
                localCalendarEntries.value.push({
                    id: commentObj.id,
                    title: commentObj.title,
                    start: commentObj.start,
                    allDay: true,
                    color: commentObj.color,
                    backgroundColor: commentObj.backgroundColor,
                    borderColor: commentObj.borderColor,
                    event_id: commentObj.id,
                    description: commentObj.extendedProps.body || '',
                    extendedProps: commentObj.extendedProps,
                });

                // Try to add to FullCalendar immediately
                try {
                    const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
                    if (api) api.addEvent(commentObj);
                } catch (e) {
                    // submitScheduleMemo addEvent failed debug suppressed
                }
            } catch (e) {
                // submitScheduleMemo post-processing failed debug suppressed
            }

            showMemoModal.value = false;
            memoBody.value = '';
            // Navigate to schedule show to keep existing UX path
            router.get(route('coordinator.project_schedules.show', { project_schedule: selectedScheduleIdForMemo.value }));
        } catch (e) {
            console.error('submitScheduleMemo error', e);
            alert('メモの保存に失敗しました');
        }
        return;
    }
    // No schedule id: create a project-level memo (date-based note)
    try {
        // send a timezone-safe datetime (set to 13:00 local) to avoid date shifting when server treats as UTC
        const safeDateTime = (dStr) => {
            try {
                if (!dStr) return dStr;
                const dateOnly = String(dStr).split('T')[0];
                const dt = new Date(dateOnly + 'T13:00:00');
                return dt.toISOString();
            } catch (e) {
                return dStr;
            }
        };

        const payload = {
            project_id: props.project ? props.project.id : null,
            // send a datetime at 13:00 to avoid shifting to previous day in UTC
            date: safeDateTime(memoDate.value),
            body: memoBody.value,
        };
        const resp = await axios.post(route('coordinator.project_memos.store'), payload);
        // update local events with returned memo and inject into calendar
        try {
            if (resp && resp.data && resp.data.memo) {
                const m = resp.data.memo;
                localMemos.value.push({ id: m.id, project_id: m.project_id, date: m.date, body: m.body });

                const memoEvent = {
                    id: `memo-${m.id}`,
                    title: '📝',
                    start: m.date,
                    allDay: true,
                    color: m.color ?? '#60a5fa',
                    backgroundColor: m.color ?? '#60a5fa',
                    borderColor: m.color ?? '#60a5fa',
                    extendedProps: { memo_id: m.id, project_id: m.project_id, body: m.body },
                };

                // Add to localCalendarEntries for consistency
                localCalendarEntries.value.push({
                    id: memoEvent.id,
                    title: memoEvent.title,
                    start: memoEvent.start,
                    allDay: true,
                    color: memoEvent.color,
                    backgroundColor: memoEvent.backgroundColor,
                    borderColor: memoEvent.borderColor,
                    event_id: memoEvent.id,
                    description: memoEvent.extendedProps.body || '',
                    extendedProps: memoEvent.extendedProps,
                });

                try {
                    const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
                    if (api) api.addEvent(memoEvent);
                } catch (e) {
                    // submitScheduleMemo addEvent project memo failed debug suppressed
                }
            }
        } catch (e) {
            // submitScheduleMemo post-processing failed debug suppressed
        }
        showMemoModal.value = false;
        memoBody.value = '';
    } catch (e) {
        console.error('submitScheduleMemo (project memo) error', e);
        alert('メモの保存に失敗しました');
    }
}

async function submitSimpleEvent() {
    if (!simpleEventTitle.value || simpleEventTitle.value.trim() === '') {
        alert('タイトルを入力してください');
        return;
    }
    // validate start date
    if (!simpleEventStartDate.value) {
        alert('日付を指定してください');
        return;
    }
    if (simpleEventEndDate.value && simpleEventEndDate.value < simpleEventStartDate.value) {
        alert('終了日は開始日以降を指定してください');
        return;
    }
    try {
        // Create a project schedule entry instead of personal event
        const payload = {
            project_job_id: props.project ? props.project.id || props.project.project_job_id || props.project.project_job?.id : null,
            name: simpleEventTitle.value,
            description: simpleEventMemo.value || null,
            start_date: simpleEventStartDate.value,
            end_date: simpleEventEndDate.value || simpleEventStartDate.value,
            color: simpleEventLabel.value || null,
        };
        // Basic client-side validation for required project_job_id
        if (!payload.project_job_id) {
            // URLパラメータからproject_job_idを取得する場合も対応
            try {
                const params = new URLSearchParams(window.location.search);
                const urlProjectId = params.get('project_job_id');
                if (urlProjectId) {
                    payload.project_job_id = parseInt(urlProjectId);
                }
            } catch (e) {
                console.warn('パラメータ取得エラー:', e);
            }
        }
        
        if (!payload.project_job_id) {
            alert('プロジェクト（job）が指定されていません。プロジェクト詳細画面からアクセスしてください。');
            return;
        }
        const resp = await axios.post(route('coordinator.project_schedules.store'), payload);
        
        // 成功時の処理
        showSimpleEventModal.value = false;
        
        // フォームをリセット
        simpleEventTitle.value = '';
        simpleEventMemo.value = '';
        simpleEventLabel.value = '';
        simpleEventIsRange.value = false;
        if (resp && resp.data && resp.data.schedule) {
            // push the returned schedule into localCalendarEntries so calendar shows it immediately
            const sched = resp.data.schedule;
            localCalendarEntries.value.push(sched);

            // Also attempt to inject the new event directly into FullCalendar so the
            // created schedule is visible immediately even if the automatic re-injection
            // guard has already run.
            try {
                const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
                const eventObj = {
                    id: sched.id,
                    title: sched.name ?? sched.title ?? '',
                    start: sched.start_date ? String(sched.start_date).split('T')[0] : (sched.date ?? null),
                    end: sched.end_date ? String(sched.end_date).split('T')[0] : undefined,
                    allDay: true,
                    color: sched.color ?? undefined,
                    backgroundColor: sched.color ?? undefined,
                    borderColor: sched.color ?? undefined,
                    event_id: sched.id,
                    description: sched.description ?? '',
                    extendedProps: { project_schedule_id: sched.id, project_job_id: sched.project_job_id ?? null },
                };
                if (api) {
                    // If end date exists, FullCalendar expects exclusive end for allDay;
                    // keep the same behavior as mapping from props.schedules earlier.
                    if (eventObj.end) {
                        try {
                            const d = new Date(eventObj.end);
                            d.setDate(d.getDate() + 1);
                            eventObj.end = d.toISOString().split('T')[0];
                        } catch (e) {}
                    }
                    api.addEvent(eventObj);
                }
            } catch (e) {
                // submitSimpleEvent inject failed debug suppressed
                // fallback: reload if injection fails
                setTimeout(() => window.location.reload(), 200);
            }
        } else {
            // fallback: reload if no schedule returned
            setTimeout(() => window.location.reload(), 200);
        }
    } catch (e) {
        console.error('submitSimpleEvent error', e);
        let errorMessage = '予定の作成に失敗しました';
        
        if (e.response && e.response.data) {
            if (e.response.data.errors) {
                const messages = Object.values(e.response.data.errors).flat().join('\n');
                errorMessage += ':\n' + messages;
            } else if (e.response.data.message) {
                errorMessage += ': ' + e.response.data.message;
            }
        }
        
        alert(errorMessage);
    }
}

// ─── CSV エクスポート ────────────────────────────────────────
function handleCsvExport() {
    const projectJobId = props.project && props.project.id;
    if (!projectJobId) {
        alert('案件IDが取得できません');
        return;
    }
    const url = route('coordinator.project_schedules.csv_export', { project_job_id: projectJobId });
    window.location.href = url;
}

// ─── CSV インポート ────────────────────────────────────────
const showCsvImportModal = ref(false);
const csvImportFile = ref(null);
const csvImportErrors = ref([]);
const csvImportLoading = ref(false);

function openCsvImportModal() {
    csvImportFile.value = null;
    csvImportErrors.value = [];
    showCsvImportModal.value = true;
}

function onCsvFileChange(event) {
    csvImportFile.value = event.target.files[0] ?? null;
    csvImportErrors.value = [];
}

async function submitCsvImport() {
    if (!csvImportFile.value) {
        alert('CSVファイルを選択してください');
        return;
    }
    const projectJobId = props.project && props.project.id;
    if (!projectJobId) {
        alert('案件IDが取得できません');
        return;
    }
    csvImportLoading.value = true;
    csvImportErrors.value = [];
    try {
        const formData = new FormData();
        formData.append('project_job_id', projectJobId);
        formData.append('file', csvImportFile.value);
        const url = route('coordinator.project_schedules.csv_import');
        const resp = await axios.post(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const created = resp.data.created ?? 0;
        showCsvImportModal.value = false;
        alert(`${created}件の予定をインポートしました`);
        router.reload();
    } catch (e) {
        if (e.response && e.response.data && e.response.data.errors) {
            csvImportErrors.value = e.response.data.errors;
        } else {
            alert('インポートに失敗しました');
        }
    } finally {
        csvImportLoading.value = false;
    }
}
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

/* Force event colors when FullCalendar styles are more specific */
.sb-event {
    background-color: var(--sb-event-bg, transparent) !important;
    border-color: var(--sb-event-border, transparent) !important;
    color: var(--sb-event-color, #fff) !important;
}
.sb-event .fc-event-title,
.sb-event .fc-event-main-frame {
    color: inherit !important;
    background: transparent !important;
}

/* ── Uniform 2-line event layout ─────────────────────────── */
:deep(.fc-event-inner-uni) {
    overflow: hidden;
    max-width: 100%;
    padding: 2px 4px;
    line-height: 1.35;
}
:deep(.fc-event-meta-uni) {
    font-size: 0.62rem;
    opacity: 0.75;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
:deep(.fc-event-name-uni) {
    font-size: 0.72rem;
    font-weight: 600;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
</style>
