<script setup>
import { computed } from 'vue';

const props = defineProps({
    show:  { type: Boolean, default: false },
    event: { type: Object, default: null },
});

const emit = defineEmits(['close', 'edit']);

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
</script>

<template>
    <Teleport to="body">
        <div v-if="show && event" class="fixed inset-0 z-50 flex items-center justify-center">
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

                <div class="flex justify-end gap-2">
                    <button class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
                        @click="$emit('close')">閉じる</button>
                    <button v-if="event.is_own"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        @click="$emit('edit', event)">編集</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
