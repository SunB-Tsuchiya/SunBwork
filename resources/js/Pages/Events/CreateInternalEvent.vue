<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    eventItemTypes:     { type: Array,  default: () => [] },
    meetingDefinitions: { type: Array,  default: () => [] },
    event:              { type: Object, default: null },
    date:               { type: String, default: '' },
    startHour:          { type: String, default: '09' },
    startMinute:        { type: String, default: '00' },
    endHour:            { type: String, default: '10' },
    endMinute:          { type: String, default: '00' },
});

const isEdit = computed(() => !!props.event);

// ---- 種類 ----
const selectedTypeId = ref(props.event?.event_item_type_id ?? props.eventItemTypes[0]?.id ?? null);
const selectedType   = computed(() => props.eventItemTypes.find((t) => t.id === selectedTypeId.value));
const showMeetingSelect = computed(() => selectedType.value?.slug === 'conference');

// ---- 会議定義 ----
const selectedMeetingId = ref(null);
const titleManuallyEdited = ref(false);

/** 今日以降の次の曜日の日付を返す */
function calcNextDate(dayOfWeek) {
    const today = new Date();
    // JST に揃える
    const jstOffset = 9 * 60 * 60 * 1000;
    const todayJst = new Date(today.getTime() + jstOffset);
    todayJst.setHours(0, 0, 0, 0);
    const todayDow = todayJst.getDay();
    let diff = dayOfWeek - todayDow;
    if (diff <= 0) diff += 7;
    const next = new Date(todayJst.getTime() + diff * 24 * 60 * 60 * 1000);
    const yyyy = next.getFullYear();
    const mm   = String(next.getMonth() + 1).padStart(2, '0');
    const dd   = String(next.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

watch(selectedMeetingId, (id) => {
    if (!id) return;
    const meeting = props.meetingDefinitions.find((m) => m.id === id);
    if (!meeting) return;

    if (!titleManuallyEdited.value) {
        form.title = meeting.title;
    }
    form.description  = meeting.description ?? '';
    form.startHour    = String(meeting.start_time).split(':')[0].padStart(2, '0');
    form.startMinute  = String(meeting.start_time).split(':')[1].padStart(2, '0');
    form.endHour      = String(meeting.end_time).split(':')[0].padStart(2, '0');
    form.endMinute    = String(meeting.end_time).split(':')[1].padStart(2, '0');
    form.date         = calcNextDate(meeting.day_of_week);
});

watch(() => form.title, () => { titleManuallyEdited.value = true; });

// 分オプション（5分刻み）
const minuteOptions = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
function minsOptions(currentVal) {
    if (!currentVal || minuteOptions.includes(currentVal)) return minuteOptions;
    return [...minuteOptions, currentVal].sort((a, b) => Number(a) - Number(b));
}

const form = useForm({
    event_item_type_id:      selectedTypeId.value,
    title:                   props.event?.title ?? '',
    description:             props.event?.description ?? '',
    date:                    props.date,
    startHour:               props.startHour,
    startMinute:             props.startMinute,
    endHour:                 props.endHour,
    endMinute:               props.endMinute,
    meeting_definition_id:   null,
    interrupted_event_ids:   [],
    own_interruption_minutes: 0,
});

watch(selectedTypeId, (v) => {
    form.event_item_type_id = v;
    if (v !== props.eventItemTypes.find((t) => t.slug === 'conference')?.id) {
        selectedMeetingId.value = null;
    }
});
watch(selectedMeetingId, (v) => { form.meeting_definition_id = v; });

watch(
    () => [form.startHour, form.startMinute],
    ([h, m], [oldH, oldM]) => {
        if (form.endHour === oldH && form.endMinute === oldM) {
            form.endHour   = h;
            form.endMinute = m;
        }
    },
);

const errorMessage = ref('');

function setCurrentTime(target) {
    const now = new Date();
    const jstParts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Tokyo',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(now);
    const h = String(Number(jstParts.find((p) => p.type === 'hour').value)).padStart(2, '0');
    const m = String(Number(jstParts.find((p) => p.type === 'minute').value)).padStart(2, '0');
    if (target === 'start') {
        form.startHour   = h;
        form.startMinute = m;
    } else {
        form.endHour   = h;
        form.endMinute = m;
    }
}

function sendForm() {
    const routeName = isEdit.value ? 'events.internal-event.update' : 'events.internal-event.store';
    if (isEdit.value) {
        form.put(route(routeName, { event: props.event.id }), {
            onError: () => { errorMessage.value = '保存に失敗しました。'; },
        });
    } else {
        form.post(route(routeName), {
            onError: () => { errorMessage.value = '保存に失敗しました。'; },
        });
    }
}

const submit = () => {
    errorMessage.value = '';
    const newStart = new Date(`${form.date}T${form.startHour}:${form.startMinute}:00`);
    const newEnd   = new Date(`${form.date}T${form.endHour}:${form.endMinute}:00`);
    if (isNaN(newStart.getTime()) || isNaN(newEnd.getTime())) {
        errorMessage.value = '開始/終了時刻が無効です。'; return;
    }
    if (newEnd <= newStart) {
        errorMessage.value = '終了時刻は開始時刻より後にしてください。'; return;
    }
    if (newEnd - newStart < 15 * 60 * 1000) {
        errorMessage.value = '予定の最小長は15分です。'; return;
    }

    const evUrl = route('events.index') + `?date=${encodeURIComponent(form.date)}`;
    fetch(evUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((res) => { if (!res.ok) throw new Error(`HTTP ${res.status}`); return res.json(); })
        .then((events) => {
            const newDuration = newEnd - newStart;
            const excludeId   = props.event?.id ?? null;
            const overlapping = events.filter((ev) => {
                if (excludeId && ev.id === excludeId) return false;
                return newStart < new Date(ev.end) && newEnd > new Date(ev.start);
            });

            if (overlapping.length === 0) {
                form.interrupted_event_ids    = [];
                form.own_interruption_minutes = 0;
                sendForm(); return;
            }

            let interruptedIds = [];
            let ownOverlapMins = 0;
            const lines = overlapping.map((ev) => {
                const evStart    = new Date(ev.start);
                const evEnd      = new Date(ev.end);
                const evDuration = evEnd - evStart;
                const overlapStart = newStart > evStart ? newStart : evStart;
                const overlapEnd   = newEnd   < evEnd   ? newEnd   : evEnd;
                const overlapMins  = Math.max(0, Math.round((overlapEnd - overlapStart) / 60000));
                const sStr = evStart.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
                const eStr = evEnd.toLocaleTimeString('ja-JP',   { hour: '2-digit', minute: '2-digit' });
                if (newDuration >= evDuration) {
                    ownOverlapMins += overlapMins;
                    return `「${ev.title}」(${sStr}〜${eStr}) → ${overlapMins}分間重複（今回の予定から差し引き）`;
                } else {
                    interruptedIds.push(ev.id);
                    return `「${ev.title}」(${sStr}〜${eStr}) → ${overlapMins}分間重複（既存の予定から差し引き）`;
                }
            });

            let msg = '以下の予定と時間が重複しています。登録しますか？\n\n';
            msg += lines.join('\n');
            msg += '\n\n【OK】を押すと、時間の長い方の予定から重複時間が差し引かれます。';
            if (!confirm(msg)) return;

            form.interrupted_event_ids    = interruptedIds;
            form.own_interruption_minutes = ownOverlapMins;
            sendForm();
        })
        .catch(() => {
            form.interrupted_event_ids    = [];
            form.own_interruption_minutes = 0;
            sendForm();
        });
};

const recurrenceLabel = { weekly: '毎週', biweekly: '隔週', monthly: '毎月' };
const dayLabel = ['日', '月', '火', '水', '木', '金', '土'];
</script>

<template>
    <AppLayout :title="isEdit ? '社内予定 編集' : '社内予定'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('calendar.index')" class="rounded bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">
                    ← 戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">{{ isEdit ? '社内予定 編集' : '社内予定' }}</h2>
            </div>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">

                <div v-if="errorMessage" class="rounded border-l-4 border-red-500 bg-red-50 p-3 text-red-700 text-sm">
                    {{ errorMessage }}
                </div>

                <!-- 種類 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">種類 <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-3">
                        <label v-for="t in eventItemTypes" :key="t.id" class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" :value="t.id" v-model="selectedTypeId" class="accent-teal-600" />
                            <span class="text-sm">{{ t.name }}</span>
                        </label>
                    </div>
                </div>

                <!-- 会議種類セレクター（会議のみ） -->
                <div v-if="showMeetingSelect">
                    <label class="mb-1 block text-sm font-medium text-gray-700">会議種類</label>
                    <select v-model="selectedMeetingId" class="w-full rounded border p-2 text-sm">
                        <option :value="null">— 自由入力 —</option>
                        <option v-for="m in meetingDefinitions" :key="m.id" :value="m.id">
                            {{ m.title }}（{{ recurrenceLabel[m.recurrence] }}・{{ dayLabel[m.day_of_week] }}曜）
                        </option>
                    </select>
                </div>

                <!-- タイトル -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full rounded border p-2 text-sm"
                        required
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <!-- 概要 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">概要</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded border p-2 text-sm"></textarea>
                </div>

                <!-- 作業日 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">作業日 <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input v-model="form.date" type="date" class="rounded border px-3 py-2" required />
                    </div>
                </div>

                <!-- 時間 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">時間</label>
                    <div class="mt-1 flex items-end gap-4">
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-600">開始</label>
                            <div class="flex items-center gap-2">
                                <select v-model="form.startHour" class="w-20 rounded border px-3 py-2">
                                    <option v-for="h in Array.from({length:24},(_,i)=>String(i).padStart(2,'0'))" :key="h" :value="h">{{ h }}</option>
                                </select>
                                <select v-model="form.startMinute" class="w-20 rounded border px-3 py-2">
                                    <option v-for="m in minsOptions(form.startMinute)" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <button type="button" @click="setCurrentTime('start')" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100">現在時刻</button>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-600">終了</label>
                            <div class="flex items-center gap-2">
                                <select v-model="form.endHour" class="w-20 rounded border px-3 py-2">
                                    <option v-for="h in Array.from({length:24},(_,i)=>String(i).padStart(2,'0'))" :key="h" :value="h">{{ h }}</option>
                                </select>
                                <select v-model="form.endMinute" class="w-20 rounded border px-3 py-2">
                                    <option v-for="m in minsOptions(form.endMinute)" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <button type="button" @click="setCurrentTime('end')" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100">現在時刻</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 送信ボタン -->
                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
                    >
                        {{ form.processing ? '保存中...' : (isEdit ? '更新する' : '登録する') }}
                    </button>
                    <Link :href="route('calendar.index')" class="rounded border px-5 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        キャンセル
                    </Link>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
