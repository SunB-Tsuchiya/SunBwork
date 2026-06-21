<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import AttendeeSelector from './AttendeeSelector.vue';
import useToasts from '@/Composables/useToasts';

// 15分刻みの時刻オプション (00:00〜23:45)
const timeOptions = [];
for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += 15) {
        timeOptions.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
    }
}

const recurrenceLabel = { weekly: '毎週', biweekly: '隔週', monthly: '毎月' };
const dayLabel = ['日', '月', '火', '水', '木', '金', '土'];

const props = defineProps({
    show:               { type: Boolean, default: false },
    reservation:        { type: Object,  default: null },
    rooms:              { type: Array,   default: () => [] },
    eventItemTypes:     { type: Array,   default: () => [] },
    meetingDefinitions: { type: Array,   default: () => [] },
    defaultDate:        { type: String,  default: '' },
    defaultStartMin:    { type: Number,  default: null },
    defaultEndMin:      { type: Number,  default: null },
    defaultRoomId:      { type: Number,  default: null },
    defaultTitle:       { type: String,  default: '' },
    defaultAttendees:   { type: Array,   default: () => [] },  // [{id, name}]
    defaultTypeId:      { type: Number,  default: null },
    defaultDestination: { type: String,  default: '' },
    defaultNotes:       { type: String,  default: '' },
    linkEventId:        { type: Number,  default: null },      // 既存イベントへリンク
    readOnly:           { type: Boolean, default: false },
    companies:          { type: Array,   default: () => [] },
    departments:        { type: Array,   default: () => [] },
});

// 外出・顧客訪問・打ち合わせ顧客は会議室不要なので除外
const NO_ROOM_SLUGS = ['outing', 'customer_visit', 'meeting_client'];
const filteredEventItemTypes = computed(() =>
    props.eventItemTypes.filter(t => !NO_ROOM_SLUGS.includes(t.slug))
);

// 種別
const selectedTypeId = ref(null);
const selectedSlug = computed(() => props.eventItemTypes.find(t => t.id === selectedTypeId.value)?.slug ?? null);
const showDestination = computed(() => selectedSlug.value === 'client_visit');

// クライアント候補
const clients = ref([]);
onMounted(async () => {
    try {
        const res = await axios.get(route('schedule.clients.index'));
        clients.value = res.data;
    } catch {}
});

const emit = defineEmits(['close', 'saved', 'deleted']);

const CSRF     = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const authUser = usePage().props.auth?.user;
const { showToast } = useToasts();

const form = ref({
    meeting_room_id:    null,
    title:              '',
    date:               '',   // YYYY-MM-DD（表示専用）
    startTime:          '',   // HH:MM
    endTime:            '',   // HH:MM
    notes:              '',
    selfIncluded:       true,
    attendees:          [],   // [{id, name}] 自分以外の参加者
    event_item_type_id: null,
    destination:        '',
});
const errors  = ref({});
const loading = ref(false);

// ── 参加者競合チェック ─────────────────────────────────────────
const conflictWarnings = ref([]);
let conflictTimer = null;

function fmtConflictTime(isoStr) {
    const d = new Date(isoStr);
    return d.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

async function checkConflicts() {
    const userIds = (form.value.attendees ?? []).map(a => a.id).filter(Boolean);
    const { date, startTime, endTime } = form.value;
    if (!userIds.length || !date || !startTime || !endTime || startTime >= endTime) {
        conflictWarnings.value = [];
        return;
    }
    try {
        const res = await axios.get(route('schedule.events.conflicts'), {
            params: {
                starts_at:        `${date} ${startTime}:00`,
                ends_at:          `${date} ${endTime}:00`,
                user_ids:         userIds,
                ...(props.reservation?.event_id
                    ? { exclude_event_id: props.reservation.event_id }
                    : props.linkEventId
                        ? { exclude_event_id: props.linkEventId }
                        : {}),
            },
        });
        conflictWarnings.value = res.data;
    } catch {
        conflictWarnings.value = [];
    }
}

function scheduleConflictCheck() {
    clearTimeout(conflictTimer);
    conflictTimer = setTimeout(checkConflicts, 500);
}

watch([() => form.value.attendees, () => form.value.date, () => form.value.startTime, () => form.value.endTime],
    scheduleConflictCheck, { deep: true });

// 会議種類ドロップダウン
const selectedMeetingId   = ref(null);
const titleManuallyEdited = ref(false);

watch(selectedTypeId, (v) => {
    form.value.event_item_type_id = v;
    if (selectedSlug.value !== 'client_visit') {
        form.value.destination = '';
    }
});

watch(selectedMeetingId, (id) => {
    if (!id) return;
    const meeting = props.meetingDefinitions.find(m => m.id === id);
    if (!meeting) return;
    if (!titleManuallyEdited.value) form.value.title = meeting.title;
});

function pad(n) { return String(n).padStart(2, '0'); }

function minToTime(min) {
    const snapped = Math.round(min / 15) * 15; // 15分刻みにスナップ
    return `${pad(Math.floor(snapped / 60))}:${pad(snapped % 60)}`;
}

function isoToDatetime(isoStr) {
    // UTC ISO 文字列を JST に変換してから日付・時刻を取得
    const d = new Date(new Date(isoStr).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' }));
    return {
        date: `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`,
        time: `${pad(d.getHours())}:${pad(d.getMinutes())}`,
    };
}

watch([() => props.show, () => props.reservation], ([showVal]) => {
    if (!showVal) {
        conflictWarnings.value = [];
        clearTimeout(conflictTimer);
        return;
    }
    errors.value = {};
    selectedMeetingId.value   = null;
    titleManuallyEdited.value = false;
    if (props.reservation) {
        const s = isoToDatetime(props.reservation.starts_at);
        const e = isoToDatetime(props.reservation.ends_at);
        const rawAttendees = props.reservation?.event?.attendees ?? [];
        const selfIn = rawAttendees.length === 0
            ? true
            : rawAttendees.some(a => (a.user_id ?? a.user?.id) === authUser?.id);
        const others = rawAttendees
            .filter(a => (a.user_id ?? a.user?.id) !== authUser?.id)
            .map(a => ({ id: a.user_id ?? a.user?.id, name: a.user?.name ?? '' }));
        const typeId = props.reservation.event?.event_item_type_id ?? null;
        selectedTypeId.value = typeId;
        form.value = {
            meeting_room_id:    props.reservation.meeting_room_id,
            title:              props.reservation.title,
            date:               s.date,
            startTime:          s.time,
            endTime:            e.time,
            notes:              props.reservation.notes ?? '',
            selfIncluded:       selfIn !== false,
            attendees:          others,
            event_item_type_id: typeId,
            destination:        props.reservation.event?.destination ?? '',
        };
    } else {
        const date      = props.defaultDate || new Date().toLocaleDateString('sv-SE');
        const startMin  = props.defaultStartMin ?? 9 * 60;
        const endMin    = props.defaultEndMin   ?? startMin + 60;
        const roomId    = props.defaultRoomId   ?? props.rooms[0]?.id ?? null;
        selectedTypeId.value = props.defaultTypeId ?? null;
        form.value = {
            meeting_room_id:    roomId,
            title:              props.defaultTitle || '',
            date,
            startTime:          minToTime(startMin),
            endTime:            minToTime(endMin),
            notes:              props.defaultNotes || '',
            selfIncluded:       true,
            attendees:          [...(props.defaultAttendees ?? [])],
            event_item_type_id: props.defaultTypeId ?? null,
            destination:        props.defaultDestination || '',
        };
    }
});

async function submit() {
    if (!form.value.meeting_room_id) {
        errors.value = { meeting_room_id: ['会議室を選択してください'] };
        return;
    }
    if (form.value.startTime >= form.value.endTime) {
        errors.value = { _general: '終了時刻は開始時刻より後に設定してください' };
        return;
    }
    if (!form.value.selfIncluded && form.value.attendees.length === 0) {
        errors.value = { _general: '参加者を1名以上選択してください' };
        return;
    }
    if (conflictWarnings.value.length > 0) {
        showToast('参加者に時間が重複する予定があります。内容を確認してください。', 'error', 5000);
        return;
    }
    loading.value = true;
    errors.value  = {};
    try {
        const body = {
            title:               form.value.title,
            starts_at:           `${form.value.date} ${form.value.startTime}:00`,
            ends_at:             `${form.value.date} ${form.value.endTime}:00`,
            notes:               form.value.notes || null,
            event_item_type_id:  form.value.event_item_type_id || null,
            destination:         showDestination.value ? (form.value.destination || null) : null,
            self_included:       form.value.selfIncluded,
            attendee_ids:        form.value.attendees.map(a => a.id),
            ...(props.linkEventId ? { link_event_id: props.linkEventId } : {}),
        };
        if (props.reservation) {
            await axios.put(
                route('schedule.room-reservations.update', { reservation: props.reservation.id }),
                body,
                { headers: { 'X-CSRF-TOKEN': CSRF() } }
            );
        } else {
            await axios.post(
                route('schedule.room-reservations.store', { room: form.value.meeting_room_id }),
                body,
                { headers: { 'X-CSRF-TOKEN': CSRF() } }
            );
        }
        emit('saved');
        emit('close');
    } catch (e) {
        if (e.response?.status === 422) {
            const data = e.response.data;
            errors.value = data.errors ?? {};
            if (!Object.keys(errors.value).length) {
                errors.value._general = data.message ?? '保存に失敗しました';
            }
        } else {
            errors.value._general = e.response?.data?.message ?? '保存に失敗しました';
        }
    } finally {
        loading.value = false;
    }
}

async function deleteReservation() {
    if (!confirm('この会議室予約を削除してもよろしいですか？')) return;
    loading.value = true;
    try {
        await axios.delete(
            route('schedule.room-reservations.destroy', { reservation: props.reservation.id }),
            { headers: { 'X-CSRF-TOKEN': CSRF() } }
        );
        emit('deleted');
        emit('close');
    } catch {
        alert('削除に失敗しました');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />

            <div class="relative z-10 w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl overflow-y-auto" style="max-height: 90vh">
                <h2 class="mb-4 text-lg font-semibold text-gray-800">
                    {{ readOnly ? '会議室予約（閲覧）' : reservation ? '会議室予約を編集' : linkEventId ? '予定に会議室を紐づける' : '会議室を予約' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <p v-if="errors._general" class="rounded bg-red-50 px-3 py-2 text-sm text-red-600">{{ errors._general }}</p>

                    <!-- 予約者情報（閲覧モード） -->
                    <div v-if="readOnly && reservation?.user" class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
                        予約者: <span class="font-medium">{{ reservation.user.name }}</span>
                    </div>

                    <!-- 種別 -->
                    <div v-if="filteredEventItemTypes.length">
                        <label class="block text-sm font-medium text-gray-700">種別</label>
                        <select v-model="selectedTypeId" :disabled="readOnly"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:text-gray-500">
                            <option :value="null">— 選択なし —</option>
                            <option v-for="t in filteredEventItemTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <!-- 取引先名（来客対応のみ） -->
                    <div v-if="showDestination || (readOnly && form.destination)">
                        <label class="block text-sm font-medium text-gray-700">取引先名</label>
                        <input v-if="!readOnly"
                            v-model="form.destination"
                            type="text"
                            list="room-clients-list"
                            placeholder="取引先名（自由入力可）"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        <datalist id="room-clients-list">
                            <option v-for="c in clients" :key="c.id" :value="c.name" />
                        </datalist>
                        <p v-if="readOnly" class="mt-1 text-sm text-gray-700">{{ form.destination }}</p>
                    </div>

                    <!-- 会議種類（種別=会議のときのみ） -->
                    <div v-if="!readOnly && meetingDefinitions.length && selectedSlug === 'conference'">
                        <label class="block text-sm font-medium text-gray-700">会議種類（任意）</label>
                        <select v-model="selectedMeetingId"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option :value="null">— 選択なし —</option>
                            <option v-for="m in meetingDefinitions" :key="m.id" :value="m.id">
                                {{ m.title }}（{{ recurrenceLabel[m.recurrence] }}・{{ dayLabel[m.day_of_week] }}曜）
                            </option>
                        </select>
                    </div>

                    <!-- 会議室選択 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">会議室 <span class="text-red-500">*</span></label>
                        <select
                            v-model="form.meeting_room_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:text-gray-500"
                            :disabled="!!reservation || readOnly"
                        >
                            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                        <p v-if="errors.meeting_room_id?.[0]" class="mt-1 text-xs text-red-600">{{ errors.meeting_room_id[0] }}</p>
                    </div>

                    <!-- タイトル -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.title" type="text" :required="!readOnly" :disabled="readOnly"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:text-gray-500"
                            placeholder="会議名・用途"
                            @input="titleManuallyEdited = true"
                        />
                        <p v-if="errors.title?.[0]" class="mt-1 text-xs text-red-600">{{ errors.title[0] }}</p>
                    </div>

                    <!-- 日付 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">日付 <span v-if="!readOnly" class="text-red-500">*</span></label>
                        <input v-model="form.date" type="date" :required="!readOnly" :disabled="readOnly"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:text-gray-500" />
                    </div>

                    <!-- 開始・終了時刻 -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">開始時刻 <span class="text-red-500">*</span></label>
                            <select
                                v-model="form.startTime" :required="!readOnly" :disabled="readOnly"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:text-gray-500"
                            >
                                <option v-for="t in timeOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="errors.starts_at?.[0]" class="mt-1 text-xs text-red-600">{{ errors.starts_at[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">終了時刻 <span class="text-red-500">*</span></label>
                            <select
                                v-model="form.endTime" :required="!readOnly" :disabled="readOnly"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:text-gray-500"
                            >
                                <option v-for="t in timeOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="errors.ends_at?.[0]" class="mt-1 text-xs text-red-600">{{ errors.ends_at[0] }}</p>
                        </div>
                    </div>

                    <!-- 備考 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">備考</label>
                        <textarea
                            v-model="form.notes" rows="2" :disabled="readOnly"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:text-gray-500"
                        ></textarea>
                    </div>

                    <!-- 参加者 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">参加者</label>

                        <!-- 自分 -->
                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="selfIncluded"
                                v-model="form.selfIncluded"
                                :disabled="readOnly"
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                            />
                            <label for="selfIncluded" class="text-sm text-gray-700">
                                自分（{{ authUser?.name }}）
                            </label>
                        </div>

                        <!-- 他の参加者（編集モード: AttendeeSelector） -->
                        <div v-if="!readOnly" class="mt-2">
                            <AttendeeSelector
                                :event-id="null"
                                :attendees="form.attendees"
                                :self-id="authUser?.id ?? null"
                                :companies="companies"
                                :departments="departments"
                                @change="v => form.attendees = v"
                            />
                        </div>

                        <!-- 他の参加者（閲覧モード: チップ表示のみ） -->
                        <div v-else-if="form.attendees.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="a in form.attendees" :key="a.id"
                                class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"
                            >
                                {{ a.name }}
                            </span>
                        </div>
                        <p v-else-if="readOnly" class="mt-1 text-xs text-gray-400">他の参加者なし</p>
                    </div>

                    <!-- 参加者スケジュール競合警告 -->
                    <div v-if="conflictWarnings.length && !readOnly"
                        class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        <div class="mb-1 font-medium">⚠ 参加者のスケジュール競合</div>
                        <ul class="space-y-0.5 text-xs">
                            <li v-for="w in conflictWarnings" :key="w.user_id">
                                <span class="font-medium">{{ w.user_name }}</span>：
                                <span v-for="(ev, i) in w.events" :key="i">
                                    {{ fmtConflictTime(ev.starts_at) }}〜{{ fmtConflictTime(ev.ends_at) }}「{{ ev.title }}」<span v-if="i < w.events.length - 1">、</span>
                                </span>
                                に別の予定が入っています
                            </li>
                        </ul>
                    </div>

                    <!-- ボタン -->
                    <div class="flex justify-between pt-2">
                        <button v-if="reservation && !readOnly" type="button"
                            class="rounded-md bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100"
                            :disabled="loading"
                            @click="deleteReservation">削除</button>
                        <div v-else />
                        <div class="flex gap-2">
                            <button type="button"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
                                @click="$emit('close')">{{ readOnly ? '閉じる' : 'キャンセル' }}</button>
                            <button v-if="!readOnly" type="submit"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                                :disabled="loading || conflictWarnings.length > 0">{{ loading ? '保存中…' : '保存' }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
