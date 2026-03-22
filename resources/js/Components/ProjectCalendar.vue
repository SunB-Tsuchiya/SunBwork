<template>
    <div class="calendar-container">
        <div class="mb-4 flex items-center gap-4">
            <!-- Link back to project details when project prop is present -->
            <button
                v-if="props.project"
                @click="goToProjectShow"
                class="rounded border bg-gray-100 px-4 py-2 text-gray-800"
                title="プロジェクト詳細に戻る"
            >
                ← プロジェクト詳細に戻る
            </button>

            <button @click="openEventModal" class="rounded bg-blue-600 px-4 py-2 text-white">予定作成</button>
            <button @click="goToDiaryCreate" class="rounded bg-orange-500 px-4 py-2 text-white">メモ作成</button>
        </div>
        <FullCalendar ref="calendarRef" :options="calendarOptions" :events="plainCalendarEvents" />

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
                    <label class="block text-sm font-medium">日付</label>
                    <div class="mt-1 flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="simpleEventIsRange" />
                            <span>範囲を指定</span>
                        </label>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-gray-600">開始</label>
                            <input type="date" v-model="simpleEventStartDate" class="w-full rounded border p-2" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">終了</label>
                            <input
                                type="date"
                                v-model="simpleEventEndDate"
                                :disabled="!simpleEventIsRange"
                                :class="['w-full rounded border p-2', !simpleEventIsRange ? 'opacity-50' : '']"
                            />
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
    schedules: { type: Array, default: () => [] },
    events: { type: [Array, Object], default: () => [] },
    comments: { type: Array, default: () => [] },
    memos: { type: Array, default: () => [] },
    project: { type: Object, default: null },
    diaryLabel: { type: String, default: 'メモ' },
});

const showModal = ref(false);
const form = ref({ title: '', description: '', startHour: '09', startMinute: '00', endHour: '10', endMinute: '00', date: '' });
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const selectedDate = ref(`${yyyy}-${mm}-${dd}`);
const selectedScheduleId = ref(null);
// schedule action UI
const showScheduleActionModal = ref(false);
const selectedScheduleForAction = ref(null);
const showMemoModal = ref(false);
const selectedScheduleIdForMemo = ref(null);
const memoBody = ref('');
const memoDate = ref(`${yyyy}-${mm}-${dd}`);

const showEditModal = ref(false);
const editingCommentId = ref(null);
const editingCommentBody = ref('');
const editingCommentDate = ref('');
const editingCommentAuthor = ref(null);

const startHourSelectRef = ref(null);
const endHourSelectRef = ref(null);
const memoDateRef = ref(null);

// schedule show/edit modal state
const showScheduleShowModal = ref(false);
const scheduleShowData = ref({ id: null, title: '', start: '', end: '', description: '', color: null });
const isEditingSchedule = ref(false);
const scheduleEditTitle = ref('');
const scheduleEditStart = ref('');
const scheduleEditEnd = ref('');
const scheduleEditColor = ref('');

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
const scheduleCanEdit = (id) => {
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
const simpleEventStartDate = ref(`${yyyy}-${mm}-${dd}`);
const simpleEventEndDate = ref(`${yyyy}-${mm}-${dd}`);
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

function openEventModal() {
    // Open simple event modal (no time selection)
    simpleEventTitle.value = '';
    // initialize range state and dates
    simpleEventIsRange.value = false;
    simpleEventStartDate.value = selectedDate.value || `${yyyy}-${mm}-${dd}`;
    simpleEventEndDate.value = simpleEventStartDate.value;
    simpleEventMemo.value = '';
    showSimpleEventModal.value = true;
}
function openEventModalFromSelect() {
    router.get(route('events.create', { date: selectedDate.value }));
}
function goToDiaryCreate() {
    // トップの「メモ作成」は今日の日付でモーダルを開く
    memoDate.value = `${yyyy}-${mm}-${dd}`;
    selectedScheduleIdForMemo.value = null;
    memoBody.value = '';
    showMemoModal.value = true;
}
function handleDateSelect(selectionInfo) {
    const dateStr = selectionInfo.startStr.split('T')[0];
    selectedDate.value = dateStr;
    // project_job 経由のカレンダーでは予定作成モーダルを開く
    if (props.project) {
        simpleEventTitle.value = '';
        simpleEventIsRange.value = false;
        simpleEventStartDate.value = dateStr;
        simpleEventEndDate.value = dateStr;
        simpleEventMemo.value = '';
        showSimpleEventModal.value = true;
        return;
    }
    // それ以外はメモモーダルを開く
    memoDate.value = dateStr;
    selectedScheduleIdForMemo.value = null;
    memoBody.value = '';
    showMemoModal.value = true;
}
function goToScheduleMemoCreate(scheduleId) {
    // open schedule memo creation page as fallback (not modal)
    router.get(route('coordinator.project_schedule_comments.create', { project_schedule: scheduleId }));
}

function focusMemoDate() {
    if (memoDateRef.value) memoDateRef.value.showPicker ? memoDateRef.value.showPicker() : memoDateRef.value.focus();
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
        const isCompleted = typeof title === 'string' && title.indexOf('【完了】') === 0;
        if (isCompleted) color = '#b58900';

        list.push({
            // include canonical `id` so FullCalendar and getEvents() return stable identifiers
            id: event.id ?? event.event_id ?? event.eventId ?? undefined,
            title: title,
            start: startRaw,
            end: endRaw ?? undefined,
            allDay: allDay,
            color: color,
            backgroundColor: color,
            borderColor: color,
            event_id: event.id ?? event.event_id,
            description: event.description ?? event.extendedProps?.description ?? '',
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
            // FullCalendar treats allDay end as exclusive; add one day if end exists
            if (endDateOnly) {
                try {
                    const d = new Date(endDateOnly);
                    d.setDate(d.getDate() + 1);
                    endDateOnly = d.toISOString().split('T')[0];
                } catch (e) {
                    // leave as-is
                }
            }
            const schedColor = s.color ?? '#3b82f6';
            list.push({
                id: s.id,
                title: s.name ?? '',
                start: startDateOnly,
                end: endDateOnly ?? undefined,
                allDay: true,
                color: schedColor,
                backgroundColor: schedColor,
                borderColor: schedColor,
                event_id: s.id,
                description: s.description ?? '',
                extendedProps: { project_schedule_id: s.id },
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
        list.push({
            id: e.id ?? e.event_id ?? undefined,
            title: e.name ?? e.title ?? '',
            start: e.start_date ?? e.start ?? e.date,
            end: e.end_date ?? e.end ?? undefined,
            allDay: true,
            color: e.color ?? '#3b82f6',
            backgroundColor: e.color ?? '#3b82f6',
            borderColor: e.color ?? '#3b82f6',
            event_id: e.id ?? e.event_id,
            description: e.description ?? e.body ?? '',
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
            const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
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
    (val) => {
        try {
            const count = Array.isArray(val) ? val.length : 0;
            const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
            // no-op: rely on FullCalendar's eventsSet and :events binding
        } catch (e) {
            // ProjectCalendar calendarEvents watch error debug suppressed
        }
    },
    { immediate: true },
);

// Modify calendarOptions to attach eventDidMount for native tooltip
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    // Use merged calendarEvents (server+comments+memos+local) to decide view
    initialView:
        calendarEvents.value && calendarEvents.value.length > 0 && calendarEvents.value.every((ev) => ev.allDay) ? 'dayGridMonth' : 'timeGridWeek',
    locale: 'ja',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
    selectable: true,
    slotMinTime: '07:00:00',
    slotMaxTime: '24:00:00',
    firstDay: 1,
    weekText: '\u9031',
    dayHeaderFormat: { weekday: 'short' },
    slotDuration: '00:15:00',
    slotLabelInterval: '00:30:00',
    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    height: 'auto',
    editable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    eventResize: async function (info) {
        const newStart = info.event.start;
        const newEnd = info.event.end;
        const startStr = info.event.startStr || (newStart ? newStart.toISOString() : null);
        const endStr = info.event.endStr || (newEnd ? newEnd.toISOString() : null);
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
                // Debug: log event core fields to help diagnose missing project_schedule_id/project_job_id
                try {
                    console.info('[ProjectCalendar] eventResize start — event core fields', {
                        id: info.event.id,
                        event_id: info.event.event_id,
                        extendedProps: info.event.extendedProps,
                        defExtendedProps: info.event._def && info.event._def.extendedProps ? info.event._def.extendedProps : null,
                        publicId: info.event._def && info.event._def.publicId ? info.event._def.publicId : null,
                    });
                } catch (e) {}
                function stripTags(str) {
                    return str ? str.replace(/<[^>]*>?/gm, '') : '';
                }
                const safeTitle = info.event.title && stripTags(info.event.title).trim() !== '' ? info.event.title : 'タイトル未設定';
                const safeDescription =
                    info.event.extendedProps.description && stripTags(info.event.extendedProps.description).trim() !== ''
                        ? info.event.extendedProps.description
                        : '内容未設定';
                let logPayload = {};
                if (info.event.allDay) {
                    logPayload = {
                        start_date: displayStart,
                        end_date: displayEndInclusive || displayStart,
                        title: safeTitle,
                        description: safeDescription,
                    };
                } else {
                    const startDateObj2 = new Date(newStart);
                    const endDateObj2 = new Date(newEnd);
                    const date2 = startDateObj2.toISOString().slice(0, 10);
                    const startHour2 = String(startDateObj2.getHours()).padStart(2, '0');
                    const startMinute2 = String(startDateObj2.getMinutes()).padStart(2, '0');
                    const endHour2 = String(endDateObj2.getHours()).padStart(2, '0');
                    const endMinute2 = String(endDateObj2.getMinutes()).padStart(2, '0');
                    logPayload = {
                        date: date2,
                        startHour: startHour2,
                        startMinute: startMinute2,
                        endHour: endHour2,
                        endMinute: endMinute2,
                        title: safeTitle,
                        description: safeDescription,
                    };
                }
                // Prefer project schedule update when this event is part of a project
                const ev = info.event;
                const defExt = ev._def && ev._def.extendedProps ? ev._def.extendedProps : null;
                const extended = ev.extendedProps || {};
                const projectJobId =
                    extended.project_job_id ||
                    extended.project_job ||
                    (defExt && (defExt.project_job_id || defExt.project_job)) ||
                    (props.project &&
                        (props.project.id || props.project.project_job_id || (props.project.project_job && props.project.project_job.id))) ||
                    null;
                // Try common fields for project schedule id
                const inferredScheduleId =
                    extended.project_schedule_id ||
                    extended.project_schedule ||
                    (defExt && (defExt.project_schedule_id || defExt.project_schedule)) ||
                    extended.schedule_id ||
                    ev.id ||
                    ev.event_id ||
                    (ev._def && ev._def.publicId) ||
                    null;

                if (projectJobId) {
                    // project event resize detected — debug suppressed
                    // If we have a schedule id, call coordinator.project_schedules.update
                    if (inferredScheduleId) {
                        try {
                            const url = route('coordinator.project_schedules.update', { project_schedule: inferredScheduleId });
                            const payload = ev.allDay
                                ? {
                                      name: ev.title || undefined,
                                      start_date: displayStart,
                                      end_date: displayEndInclusive || displayStart,
                                      color: ev.backgroundColor || ev.color,
                                  }
                                : {
                                      name: ev.title || undefined,
                                      start_date: displayStart,
                                      end_date: newEnd ? newEnd.toISOString() : undefined,
                                      color: ev.backgroundColor || ev.color,
                                  };
                            await axios.patch(url, payload);
                            alert('予定を更新しました');
                        } catch (err) {
                            console.error('project schedule update failed', err);
                            alert('予定の更新に失敗しました');
                            info.revert();
                        }
                    } else {
                        // Try to resolve schedule id by lookup (title / start / end) similar to submitScheduleUpdate
                        try {
                            const normalizeDate = (d) => {
                                if (!d) return null;
                                try {
                                    return String(d).split('T')[0];
                                } catch (e) {
                                    return String(d);
                                }
                            };
                            const wantTitle = (ev.title || '').trim();
                            const wantStart = ev.start || null;
                            const wantEnd = ev.end || null;

                            const tryMatch = (list) => {
                                if (!Array.isArray(list)) return null;
                                const wantTitleLower = (wantTitle || '').toLowerCase().trim();
                                for (const item of list) {
                                    try {
                                        const itemTitleRaw = item.title || item.name || '';
                                        const itemTitle = itemTitleRaw ? String(itemTitleRaw).trim() : '';
                                        const itemTitleLower = itemTitle.toLowerCase();
                                        const itemStart = normalizeDate(item.start ?? item.start_date ?? item.date);
                                        const itemEndRaw = item.end ?? item.end_date ?? undefined;
                                        const itemEnd = itemEndRaw ? normalizeDate(itemEndRaw) : null;

                                        const candidateId =
                                            (item.extendedProps &&
                                                (item.extendedProps.project_schedule_id ||
                                                    item.extendedProps.event_id ||
                                                    item.extendedProps.schedule_id)) ||
                                            item.schedule_id ||
                                            item.event_id ||
                                            item.id ||
                                            null;

                                        if (wantTitleLower && itemTitleLower && wantTitleLower === itemTitleLower) {
                                            if (wantStart && itemStart && normalizeDate(wantStart) === itemStart) return candidateId;
                                            if (!wantStart) return candidateId;
                                        }
                                        if (wantStart && itemStart && normalizeDate(wantStart) === itemStart) {
                                            if (wantEnd && itemEnd && normalizeDate(wantEnd) === itemEnd) return candidateId;
                                            if (!wantEnd) return candidateId;
                                        }
                                        if (
                                            wantTitleLower &&
                                            itemTitleLower &&
                                            (itemTitleLower.includes(wantTitleLower) || wantTitleLower.includes(itemTitleLower))
                                        ) {
                                            return candidateId;
                                        }
                                    } catch (e) {}
                                }
                                return null;
                            };

                            let resolved = null;
                            resolved =
                                tryMatch(calendarEvents.value) ||
                                tryMatch(plainCalendarEvents.value) ||
                                tryMatch(props.events && props.events.value ? props.events.value : props.events);
                            if (!resolved && Array.isArray(props.schedules)) {
                                for (const s of props.schedules) {
                                    try {
                                        const sTitle = (s.name || s.title || '').trim();
                                        const sStart = normalizeDate(s.start_date || s.date || s.start);
                                        if (
                                            wantTitle &&
                                            sTitle &&
                                            wantTitle === sTitle &&
                                            wantStart &&
                                            sStart &&
                                            normalizeDate(wantStart) === sStart
                                        ) {
                                            resolved = s.id;
                                            break;
                                        }
                                    } catch (e) {}
                                }
                            }

                            if (resolved) {
                                console.info('[ProjectCalendar] eventResize resolved schedule id by lookup', resolved);
                                try {
                                    const url = route('coordinator.project_schedules.update', { project_schedule: resolved });
                                    const payload = ev.allDay
                                        ? {
                                              name: ev.title || undefined,
                                              start_date: displayStart,
                                              end_date: displayEndInclusive || displayStart,
                                              color: ev.backgroundColor || ev.color,
                                          }
                                        : {
                                              name: ev.title || undefined,
                                              start_date: displayStart,
                                              end_date: newEnd ? newEnd.toISOString() : undefined,
                                              color: ev.backgroundColor || ev.color,
                                          };
                                    await axios.patch(url, payload);
                                    alert('予定を更新しました');
                                } catch (err2) {
                                    console.error('project schedule update failed (resolved)', err2);
                                    alert('予定の更新に失敗しました');
                                    info.revert();
                                }
                            } else {
                                console.warn('[ProjectCalendar] cannot infer schedule id for project event; event shape:', ev);
                                alert('プロジェクトに紐づくスケジュールIDが見つからないため更新できませんでした');
                                info.revert();
                            }
                        } catch (e) {
                            console.error('[ProjectCalendar] eventResize lookup error', e);
                            alert('プロジェクトに紐づくスケジュールIDが見つからないため更新できませんでした');
                            info.revert();
                        }
                    }
                } else {
                    // Only update generic events for personal calendar
                    await axios.put(`/events/${extended.event_id}/calendar`, {
                        date: displayStart,
                        startHour: newStart ? String(newStart.getHours()).padStart(2, '0') : undefined,
                        startMinute: newStart ? String(newStart.getMinutes()).padStart(2, '0') : undefined,
                        endHour: newEnd ? String(newEnd.getHours()).padStart(2, '0') : undefined,
                        endMinute: newEnd ? String(newEnd.getMinutes()).padStart(2, '0') : undefined,
                    });
                    alert('予定を更新しました');
                }
            } catch (e) {
                // eventResize error suppressed; keep alert and revert
                if (e.response && e.response.data) {
                    alert('予定の更新に失敗しました');
                    // API error detail suppressed
                } else {
                    alert('予定の更新に失敗しました');
                }
                info.revert();
            }
        } else {
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

            const bg = info.event.backgroundColor || info.event.color || info.event.extendedProps?.color || null;
            if (bg) {
                // simple contrast: pick white or near-black text
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
                    info.el.style.borderColor = bg;
                    info.el.style.color = text;
                } catch (sErr) {}
            }
        } catch (e) {
            // ignore
        }
    },
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

function goToScheduleShowFromAction() {
    if (!selectedScheduleForAction.value) return;
    showScheduleActionModal.value = false;
    router.get(route('coordinator.project_schedules.show', { project_schedule: selectedScheduleForAction.value }));
}

function goToProjectShow() {
    try {
        const pid = props.project && (props.project.id || props.project.project_job_id || props.project.project_job?.id);
        if (!pid) return;
        // prefer named Ziggy route; fallback to explicit path
        try {
            router.get(route('coordinator.project_jobs.show', { projectJob: pid }));
        } catch (e) {
            router.get(`/coordinator/project_jobs/${pid}`);
        }
    } catch (e) {
        // ignore navigation errors
    }
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
    scheduleShowData.value.id = event.extendedProps.project_schedule_id || event.extendedProps.event_id || event.id || null;
    scheduleShowData.value.title = event.title || '';
    scheduleShowData.value.start = event.startStr
        ? event.startStr.split('T')[0]
        : event.start
          ? new Date(event.start).toISOString().split('T')[0]
          : '';
    scheduleShowData.value.end = event.endStr
        ? event.endStr.split('T')[0]
        : event.end
          ? new Date(event.end).toISOString().split('T')[0]
          : scheduleShowData.value.start;
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
            const findInList = (list, listName = '') => {
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
    // prep edit fields
    scheduleEditTitle.value = scheduleShowData.value.title;
    scheduleEditStart.value = scheduleShowData.value.start;
    scheduleEditEnd.value = scheduleShowData.value.end;
    scheduleEditColor.value = scheduleShowData.value.color;
    isEditingSchedule.value = false;
    showScheduleShowModal.value = true;
    try {
        console.info('[ProjectCalendar] openScheduleShowModal', { schedule: scheduleShowData.value, isEditingSchedule: isEditingSchedule.value });
    } catch (e) {}
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
                            // setDates accepts string or Date; prefer ISO date strings
                            ev.setDates(eventData.start || null, eventData.end || null);
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
                            api.addEvent(eventData);
                        } catch (e) {}
                    }
                }
            } catch (e) {
                console.error('[ProjectCalendar] fullcalendar update error', e);
            }

            // Small sanity check: if FullCalendar still doesn't show the updated event, reload as a fallback
            nextTick(() => {
                try {
                    const api = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
                    const found = api && api.getEventById ? api.getEventById(String(s.id)) : null;
                    if (!found) {
                        console.warn('[ProjectCalendar] schedule update not reflected in FullCalendar; performing full reload');
                        router.reload();
                    }
                } catch (e) {
                    console.error('[ProjectCalendar] post-update check error', e);
                    router.reload();
                }
            });
        }
        showScheduleShowModal.value = false;
        isEditingSchedule.value = false;
    } catch (e) {
        console.error('submitScheduleUpdate error', e);
        alert('予定の更新に失敗しました');
    }
}

async function deleteSchedule() {
    if (!confirm('この予定を削除しますか？')) return;
    try {
        const id = scheduleShowData.value.id;
        if (!id) throw new Error('Schedule id missing');
        const url = route('coordinator.project_schedules.destroy', { project_schedule: id });
        const resp = await axios.delete(url);
        // mark as deleted in localCalendarEntries (or remove)
        const idx = localCalendarEntries.value.findIndex((x) => String(x.id) === String(id));
        if (idx !== -1) {
            localCalendarEntries.value.splice(idx, 1);
        } else {
            // add a deleted marker so computed will remove any existing
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
        // call destroy via explicit URL to avoid Ziggy resolving to coordinator-prefixed route
        const url = `/project_memos/${editingCommentId.value}`;
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
    if (simpleEventIsRange.value && !simpleEventEndDate.value) {
        alert('終了日を指定してください');
        return;
    }
    if (simpleEventIsRange.value && simpleEventEndDate.value < simpleEventStartDate.value) {
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
            end_date: simpleEventIsRange.value ? simpleEventEndDate.value : simpleEventStartDate.value,
            color: simpleEventLabel.value || null,
        };
        // Basic client-side validation for required project_job_id
        if (!payload.project_job_id) {
            alert('プロジェクト（job）が指定されていません');
            return;
        }
        const resp = await axios.post(route('coordinator.project_schedules.store'), payload);
        showSimpleEventModal.value = false;
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
        alert('予定の作成に失敗しました');
    }
}

const submitEvent = async () => {
    const start = `${form.value.date} ${form.value.startHour}:${form.value.startMinute}:00`;
    const end = `${form.value.date} ${form.value.endHour}:${form.value.endMinute}:00`;
    try {
        await axios.post('/events', { title: form.value.title, description: form.value.description, start, end });
        showModal.value = false;
        // Refresh via Inertia to obtain server-authoritative events
        router.reload();
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
</style>
