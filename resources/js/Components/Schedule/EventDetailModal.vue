<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import useToasts from '@/Composables/useToasts';

const props = defineProps({
    show:  { type: Boolean, default: false },
    event: { type: Object, default: null },
});

const emit = defineEmits(['close', 'edit', 'open-room-reserve', 'responded', 'materialized']);

const { showToast } = useToasts();
const responding = ref(false);
const materializing = ref(false);

function formatDatetime(str) {
    if (!str) return '';
    const d = new Date(str);
    return d.toLocaleString('ja-JP', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
}

const visibilityLabel = computed(() => ({
    private: '非公開', company: '社内', group: 'グループ', public: '全体',
}[props.event?.visibility] ?? ''));

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

async function respond(status) {
    if (responding.value) return;
    responding.value = true;
    try {
        await axios.put(
            route('schedule.attendees.respond', { event: props.event.id }),
            { status },
            { headers: { 'X-CSRF-TOKEN': CSRF() } },
        );
        const label = status === 'accepted' ? '承認しました' : '辞退しました';
        showToast(label, status === 'accepted' ? 'success' : 'info', 3000);
        emit('responded', { eventId: props.event.id, status });
        emit('close');
    } catch {
        showToast('操作に失敗しました', 'error', 3000);
    } finally {
        responding.value = false;
    }
}

async function materialize() {
    if (materializing.value) return;
    materializing.value = true;
    try {
        await axios.post(
            route('schedule.events.materialize', { event: props.event.id }),
            {},
            { headers: { 'X-CSRF-TOKEN': CSRF() } },
        );
        showToast('実績として記録しました', 'success', 3000);
        emit('materialized');
        emit('close');
    } catch {
        showToast('操作に失敗しました', 'error', 3000);
    } finally {
        materializing.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show && event" class="fixed inset-0 z-[2000] flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
            <div class="relative z-10 w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <h2 class="mb-1 text-lg font-semibold text-gray-800">{{ event.title }}</h2>

                <div class="mb-4 space-y-1 text-sm text-gray-500">
                    <p>{{ formatDatetime(event.starts_at) }} 〜 {{ formatDatetime(event.ends_at) }}</p>
                    <p v-if="event.event_item_type">種別: {{ event.event_item_type.name }}</p>
                    <p>公開範囲: {{ visibilityLabel }}</p>
                </div>

                <p v-if="event.body" class="mb-4 whitespace-pre-wrap text-sm text-gray-700">{{ event.body }}</p>

                <!-- 参加者（自分のイベントのみフル表示） -->
                <div v-if="event.is_own && event.attendees?.length" class="mb-4">
                    <p class="mb-1 text-xs font-medium text-gray-500">参加者</p>
                    <div class="flex flex-wrap gap-1">
                        <span v-for="a in event.attendees" :key="a.id"
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                            {{ a.user?.name }}
                        </span>
                    </div>
                </div>

                <!-- 招待イベントの承認/辞退（pending のときのみ） -->
                <div v-if="event.as_attendee && event.my_attendee_status === 'pending'"
                    class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="mb-2 text-sm text-amber-800">この予定に招待されています。</p>
                    <div class="flex gap-2">
                        <button
                            class="rounded-md bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60"
                            :disabled="responding"
                            @click="respond('accepted')">承認</button>
                        <button
                            class="rounded-md bg-red-50 px-4 py-1.5 text-sm font-medium text-red-600 hover:bg-red-100 disabled:opacity-60"
                            :disabled="responding"
                            @click="respond('declined')">辞退</button>
                    </div>
                </div>

                <!-- 承認済みバッジ -->
                <div v-else-if="event.as_attendee && event.my_attendee_status === 'accepted'"
                    class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
                    ✓ 承認済み
                </div>

                <!-- 実績として記録する（招待された会議のみ・自分名義にコピーして以後自由編集できるようにする） -->
                <div v-if="!event.is_own && event.as_attendee" class="mb-4 flex items-center gap-1.5">
                    <button
                        class="rounded-md border border-emerald-400 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 disabled:opacity-60"
                        :disabled="materializing"
                        @click="materialize">実績として記録する</button>
                    <div class="group relative flex">
                        <span
                            class="flex h-4 w-4 cursor-help items-center justify-center rounded-full border border-gray-300 text-[10px] font-bold text-gray-500">？</span>
                        <div
                            class="pointer-events-none absolute left-1/2 top-6 z-50 w-64 -translate-x-1/2 rounded-md bg-gray-800 px-3 py-2 text-xs leading-relaxed text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                            この会議の内容をコピーして、あなた専用の予定として複製します。複製後は時刻や内容を自由に編集・削除でき、元の会議が変更・削除されても複製には影響しません。
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
                        @click="$emit('close')">閉じる</button>
                    <button v-if="event.is_own && !event.room_reservation_id"
                        class="rounded-md border border-blue-400 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50"
                        @click="$emit('open-room-reserve', event)">会議室を予約</button>
                    <button v-if="event.is_own"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        @click="$emit('edit', event)">編集</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
