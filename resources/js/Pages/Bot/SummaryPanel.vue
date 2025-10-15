<template>
    <div v-if="visible" :class="[widthClass, 'mb-4 rounded border border-gray-200 bg-gray-50 p-3']">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-sm font-medium text-gray-700">要約</div>
                <div class="text-xs text-gray-500">最新の自動要約を表示します</div>
            </div>
            <div>
                <button v-if="loading" class="text-xs text-gray-500">読み込み中…</button>
                <button v-else @click="refresh" class="text-xs text-blue-600">再読込</button>
            </div>
        </div>
        <div class="mt-2 whitespace-pre-wrap text-sm text-gray-800">{{ summary || '要約はまだありません。' }}</div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    conversationId: { type: [Number, String], required: false },
    visible: { type: Boolean, default: true },
    // Tailwind width class to apply to the panel. Default matches sidebar: md:w-64
    widthClass: { type: String, default: 'w-full md:w-64' },
});
const summary = ref('');
const loading = ref(false);

async function fetchSummary(id) {
    if (!id) return;
    loading.value = true;
    try {
        const res = await axios.get(`/bot/conversations/${id}/summary`, { withCredentials: true });
        if (res && res.data && res.data.summary) {
            summary.value = res.data.summary;
        } else if (res && res.data && res.data.data && res.data.data.summary) {
            summary.value = res.data.data.summary;
        } else {
            summary.value = '';
        }
    } catch (e) {
        // Fallback: leave existing summary or empty
        console.info('SummaryPanel: fetch failed', e && e.message);
    } finally {
        loading.value = false;
    }
}

function refresh() {
    if (props.conversationId) fetchSummary(props.conversationId);
}

watch(
    () => props.conversationId,
    (v) => {
        if (v) fetchSummary(v);
    },
);
onMounted(() => {
    if (props.conversationId) fetchSummary(props.conversationId);
});
</script>

<style scoped>
.summary-empty {
    color: #6b7280;
}
</style>
