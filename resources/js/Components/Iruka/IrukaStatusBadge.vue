<template>
    <div class="relative">
        <!-- バッジボタン -->
        <button
            type="button"
            class="flex cursor-pointer items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium shadow-sm transition-colors hover:bg-gray-50"
            @click="showModal = true"
        >
            <span class="text-base leading-none pointer-events-none">🐬</span>
            <span class="h-2 w-2 rounded-full shrink-0 pointer-events-none" :class="statusInfo.dot" />
            <span class="hidden sm:inline max-w-[80px] truncate pointer-events-none" :class="currentStatus ? 'text-gray-600' : 'text-gray-400'">{{ statusInfo.label }}</span>
        </button>

        <!-- ステータスモーダル -->
        <IrukaStatusModal
            :show="showModal"
            :target-user="selfUser"
            :is-self="true"
            :statuses="statuses"
            @close="showModal = false"
            @save="handleSave"
            @clear="handleClear"
        />
    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue';
import IrukaStatusModal from './IrukaStatusModal.vue';
import { getStatus } from './statusConfig.js';

const authUser = inject('authUser', null);

const currentStatus  = ref(null); // null = ロード中（退社で一瞬チカチカするのを防ぐ）
const currentComment = ref('');
const showModal      = ref(false);
const statuses       = ref(null); // DB順のステータスリスト

const statusInfo = computed(() => currentStatus.value ? getStatus(currentStatus.value) : { dot: 'bg-gray-300', label: '読込中' });

const selfUser = computed(() => ({
    id:      authUser?.id,
    name:    authUser?.name ?? '',
    status:  currentStatus.value,
    comment: currentComment.value,
}));

onMounted(async () => {
    await Promise.all([fetchSelf(), fetchStatuses()]);
    window.addEventListener('iruka:refresh', fetchSelf);
});

onUnmounted(() => {
    window.removeEventListener('iruka:refresh', fetchSelf);
});

async function fetchSelf() {
    try {
        const res = await window.axios.get('/presence');
        const me  = res.data.find(u => u.id === authUser?.id);
        if (me) {
            currentStatus.value  = me.status;
            currentComment.value = me.comment ?? '';
        }
    } catch (_) {}
}

async function fetchStatuses() {
    try {
        const res    = await window.axios.get('/presence/statuses');
        statuses.value = res.data;
    } catch (_) {}
}

async function handleSave({ userId, status, comment }) {
    try {
        await window.axios.post(`/presence/${userId}`, { status, comment });
        currentStatus.value  = status;
        currentComment.value = comment ?? '';
        showModal.value = false;
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {}
}

async function handleClear() {
    try {
        await window.axios.post('/presence/self/clear');
        currentStatus.value  = 'left';
        currentComment.value = '';
        showModal.value = false;
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {}
}
</script>
