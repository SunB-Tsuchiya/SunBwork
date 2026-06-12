<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show:         { type: Boolean, default: false },
    reservation:  { type: Object,  default: null },   // null=新規
    rooms:        { type: Array,   default: () => [] }, // [{id, name, color}]
    defaultDate:  { type: String,  default: '' },
    defaultStartMin: { type: Number, default: null },
    defaultEndMin:   { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved', 'deleted']);

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const form = ref({
    meeting_room_id: null,
    title:           '',
    starts_at:       '',
    ends_at:         '',
    notes:           '',
});
const errors  = ref({});
const loading = ref(false);

function minToTime(min) {
    const h = String(Math.floor(min / 60)).padStart(2, '0');
    const m = String(min % 60).padStart(2, '0');
    return `${h}:${m}`;
}

function toLocalInput(d) {
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

watch(() => props.show, (v) => {
    if (!v) return;
    errors.value = {};
    if (props.reservation) {
        form.value = {
            meeting_room_id: props.reservation.meeting_room_id,
            title:           props.reservation.title,
            starts_at:       toLocalInput(new Date(props.reservation.starts_at)),
            ends_at:         toLocalInput(new Date(props.reservation.ends_at)),
            notes:           props.reservation.notes ?? '',
        };
    } else {
        const date     = props.defaultDate || new Date().toLocaleDateString('sv-SE');
        const startMin = props.defaultStartMin ?? 9 * 60;
        const endMin   = props.defaultEndMin   ?? startMin + 60;
        form.value = {
            meeting_room_id: props.rooms[0]?.id ?? null,
            title:           '',
            starts_at:       `${date}T${minToTime(startMin)}`,
            ends_at:         `${date}T${minToTime(endMin)}`,
            notes:           '',
        };
    }
});

async function submit() {
    if (!form.value.meeting_room_id) {
        errors.value = { meeting_room_id: '会議室を選択してください' };
        return;
    }
    loading.value = true;
    errors.value  = {};
    try {
        const body = {
            title:    form.value.title,
            starts_at: form.value.starts_at.replace('T', ' '),
            ends_at:   form.value.ends_at.replace('T', ' '),
            notes:    form.value.notes || null,
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
            if (!Object.keys(errors.value).length && data.message) {
                errors.value._general = data.message;
            }
        } else if (e.response?.status === 422 || e.response?.data?.message) {
            errors.value._general = e.response.data.message;
        } else {
            errors.value._general = '保存に失敗しました';
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
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- オーバーレイ -->
            <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />

            <!-- モーダル本体 -->
            <div class="relative z-10 w-full max-w-md rounded-lg bg-white shadow-xl">
                <!-- ヘッダー -->
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-800">
                        {{ reservation ? '会議室予約を編集' : '会議室を予約' }}
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="$emit('close')">✕</button>
                </div>

                <!-- フォーム -->
                <form @submit.prevent="submit" class="space-y-4 px-5 py-4">
                    <p v-if="errors._general" class="rounded bg-red-50 px-3 py-2 text-sm text-red-600">{{ errors._general }}</p>

                    <!-- 会議室選択 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">会議室 *</label>
                        <select
                            v-model="form.meeting_room_id"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            :disabled="!!reservation"
                        >
                            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                        <p v-if="errors.meeting_room_id" class="mt-1 text-xs text-red-600">{{ errors.meeting_room_id }}</p>
                    </div>

                    <!-- タイトル -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">タイトル *</label>
                        <input
                            v-model="form.title" type="text" required
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="会議名・用途"
                        />
                        <p v-if="errors.title" class="mt-1 text-xs text-red-600">{{ errors.title }}</p>
                    </div>

                    <!-- 開始・終了 -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">開始 *</label>
                            <input
                                v-model="form.starts_at" type="datetime-local" required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <p v-if="errors.starts_at" class="mt-1 text-xs text-red-600">{{ errors.starts_at }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">終了 *</label>
                            <input
                                v-model="form.ends_at" type="datetime-local" required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <p v-if="errors.ends_at" class="mt-1 text-xs text-red-600">{{ errors.ends_at }}</p>
                        </div>
                    </div>

                    <!-- 備考 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">備考</label>
                        <textarea
                            v-model="form.notes" rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <!-- ボタン -->
                    <div class="flex items-center justify-between pt-2">
                        <button
                            v-if="reservation"
                            type="button"
                            class="text-sm text-red-600 hover:underline"
                            :disabled="loading"
                            @click="deleteReservation"
                        >削除</button>
                        <div v-else />
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                @click="$emit('close')"
                            >キャンセル</button>
                            <button
                                type="submit"
                                class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                                :disabled="loading"
                            >{{ loading ? '保存中…' : '保存' }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
