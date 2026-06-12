<script setup>
import { ref, watch, computed, inject } from 'vue';
import axios from 'axios';
import AttendeeSelector from './AttendeeSelector.vue';

const props = defineProps({
    show:            { type: Boolean, default: false },
    event:           { type: Object, default: null },   // null=新規作成
    defaultDate:     { type: String, default: '' },
    defaultStartMin: { type: Number, default: null },   // 分(0-1439)
    defaultEndMin:   { type: Number, default: null },   // 分(0-1439)
    eventItemTypes:  { type: Array, default: () => [] },
    companies:       { type: Array, default: () => [] },
    departments:     { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved', 'deleted']);

const authUser = inject('authUser', null);

const CSRFTOKEN = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const form = ref({
    title:              '',
    starts_at:          '',
    ends_at:            '',
    event_item_type_id: null,
    body:               '',
    visibility:         'company',
    is_company_event:   true,
});
const errors         = ref({});
const loading        = ref(false);
const formAttendees  = ref([]); // 新規作成モード用の参加者リスト

function minToTime(min) {
    const h = String(Math.floor(min / 60)).padStart(2, '0');
    const m = String(min % 60).padStart(2, '0');
    return `${h}:${m}`;
}

watch(() => props.show, (v) => {
    if (!v) return;
    errors.value = {};
    if (props.event) {
        // 編集モード
        const s = new Date(props.event.starts_at);
        const e = new Date(props.event.ends_at);
        form.value = {
            title:              props.event.title ?? '',
            starts_at:          toLocalDatetimeInput(s),
            ends_at:            toLocalDatetimeInput(e),
            event_item_type_id: props.event.event_item_type_id ?? null,
            body:               props.event.body ?? '',
            visibility:         props.event.visibility ?? 'company',
            is_company_event:   props.event.is_company_event ?? true,
        };
    } else {
        // 新規作成モード
        const date     = props.defaultDate || new Date().toLocaleDateString('sv-SE');
        const startMin = props.defaultStartMin ?? 9 * 60;
        const endMin   = props.defaultEndMin   ?? (startMin + 60);
        form.value = {
            title:              '',
            starts_at:          `${date}T${minToTime(startMin)}`,
            ends_at:            `${date}T${minToTime(endMin)}`,
            event_item_type_id: null,
            body:               '',
            visibility:         'company',
            is_company_event:   true,
        };
        formAttendees.value = [];
    }
});

function toLocalDatetimeInput(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// 編集モードの参加者を {id, name} に正規化
const editAttendees = computed(() => {
    if (!props.event?.attendees) return [];
    return props.event.attendees.map(a => ({
        id:   a.user_id ?? a.user?.id,
        name: a.user?.name ?? '',
    })).filter(a => a.id);
});

async function submit() {
    loading.value = true;
    errors.value  = {};

    const payload = {
        ...form.value,
        starts_at: form.value.starts_at.replace('T', ' ') + ':00',
        ends_at:   form.value.ends_at.replace('T', ' ')   + ':00',
    };

    try {
        let res;
        if (props.event) {
            res = await axios.put(route('schedule.events.update', { event: props.event.id }), payload, {
                headers: { 'X-CSRF-TOKEN': CSRFTOKEN() },
            });
        } else {
            // 新規作成時: 参加者IDを含める
            payload.attendee_ids = formAttendees.value.map(a => a.id);
            res = await axios.post(route('schedule.events.store'), payload, {
                headers: { 'X-CSRF-TOKEN': CSRFTOKEN() },
            });
        }
        emit('saved', res.data);
        emit('close');
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        }
    } finally {
        loading.value = false;
    }
}

async function deleteEvent() {
    if (!confirm('この予定を削除しますか？')) return;
    loading.value = true;
    try {
        await axios.delete(route('schedule.events.destroy', { event: props.event.id }), {
            headers: { 'X-CSRF-TOKEN': CSRFTOKEN() },
        });
        emit('deleted', props.event.id);
        emit('close');
    } finally {
        loading.value = false;
    }
}

const isEdit = computed(() => !!props.event);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
            <div class="relative z-10 w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-800">
                    {{ isEdit ? '予定を編集' : '予定を作成' }}
                </h2>

                <form class="space-y-4" @submit.prevent="submit">
                    <!-- タイトル -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                        <input v-model="form.title" type="text" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        <p v-if="errors.title" class="mt-1 text-xs text-red-500">{{ errors.title[0] }}</p>
                    </div>

                    <!-- 開始・終了 -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">開始 <span class="text-red-500">*</span></label>
                            <input v-model="form.starts_at" type="datetime-local" required
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">終了 <span class="text-red-500">*</span></label>
                            <input v-model="form.ends_at" type="datetime-local" required
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                    </div>

                    <!-- 種別 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">種別</label>
                        <select v-model="form.event_item_type_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option :value="null">選択なし</option>
                            <option v-for="t in eventItemTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <!-- 公開範囲 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">公開範囲</label>
                        <select v-model="form.visibility"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="private">非公開（自分のみ）</option>
                            <option value="company">社内公開</option>
                            <option value="group">グループ公開</option>
                            <option value="public">全体公開</option>
                        </select>
                    </div>

                    <!-- メモ -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">メモ</label>
                        <textarea v-model="form.body" rows="2"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    </div>

                    <!-- 参加者 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">参加者</label>
                        <!-- 編集モード: ライブAPI呼び出し -->
                        <AttendeeSelector
                            v-if="isEdit"
                            :event-id="event.id"
                            :attendees="editAttendees"
                            :self-id="authUser?.id ?? null"
                            :companies="companies"
                            :departments="departments"
                        />
                        <!-- 新規作成モード: フォームで収集 -->
                        <AttendeeSelector
                            v-else
                            :event-id="null"
                            :attendees="formAttendees"
                            :self-id="authUser?.id ?? null"
                            :companies="companies"
                            :departments="departments"
                            @change="v => formAttendees = v"
                        />
                    </div>

                    <!-- ボタン -->
                    <div class="flex justify-between pt-2">
                        <button v-if="isEdit" type="button"
                            class="rounded-md bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100"
                            :disabled="loading"
                            @click="deleteEvent">
                            削除
                        </button>
                        <div v-else />
                        <div class="flex gap-2">
                            <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
                                @click="$emit('close')">キャンセル</button>
                            <button type="submit"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                                :disabled="loading">
                                {{ isEdit ? '更新' : '作成' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
