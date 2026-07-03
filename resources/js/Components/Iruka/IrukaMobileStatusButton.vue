<template>
    <div>
        <button
            type="button"
            class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"
            @click="openModal"
        >
            <span class="text-base leading-none pointer-events-none">🐬</span>
            <span class="h-2.5 w-2.5 rounded-full shrink-0 pointer-events-none" :class="statusInfo.dot" />
            <span class="max-w-[90px] truncate pointer-events-none">{{ statusInfo.label }}</span>
        </button>

        <IrukaStatusModal
            v-if="modalTarget"
            :show="showModal"
            :target-user="modalTarget"
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
import { getStatus } from './statusConfig.js';
import IrukaStatusModal from './IrukaStatusModal.vue';
import useToasts from '@/Composables/useToasts';

const { showToast } = useToasts();
const authUser = inject('authUser', null);

const currentStatus  = ref('left');
const currentComment = ref('');
const showModal      = ref(false);
const modalTarget    = ref(null);
const statuses       = ref(null);

const statusInfo = computed(() => getStatus(currentStatus.value));

onMounted(async () => {
    await fetchSelf();
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
            if (modalTarget.value) {
                modalTarget.value = { ...modalTarget.value, status: me.status, comment: me.comment ?? '' };
            }
        }
    } catch (_) {}
}

async function fetchStatuses() {
    if (statuses.value) return;
    try {
        const res  = await window.axios.get('/presence/statuses');
        statuses.value = res.data;
    } catch (_) {}
}

async function openModal() {
    await fetchStatuses();
    modalTarget.value = {
        id:      authUser?.id,
        name:    authUser?.name ?? '',
        status:  currentStatus.value,
        comment: currentComment.value,
    };
    showModal.value = true;
}

async function handleSave({ userId, status, comment }) {
    showModal.value = false; // 先に閉じる（iOS Safari 対策）
    try {
        await window.axios.post(`/presence/${userId}`, { status, comment });
        await fetchSelf();
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {
        showToast('ステータスの更新に失敗しました', 'error');
    }
}

async function handleClear() {
    showModal.value = false; // 先に閉じる（iOS Safari 対策）
    try {
        await window.axios.post('/presence/self/clear');
        await fetchSelf();
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {
        showToast('ステータスの更新に失敗しました', 'error');
    }
}
</script>
