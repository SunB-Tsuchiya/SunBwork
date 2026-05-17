<template>
    <div>
        <button
            type="button"
            class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"
            @click="goToStatusUpdate"
        >
            <span class="text-base leading-none">🐬</span>
            <span class="h-2.5 w-2.5 rounded-full shrink-0" :class="statusInfo.dot" />
            <span class="max-w-[90px] truncate">{{ statusInfo.label }}</span>
        </button>
    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { getStatus } from './statusConfig.js';

const authUser = inject('authUser', null);

const currentStatus  = ref('left');
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
            currentStatus.value = me.status;
        }
    } catch (_) {}
}

function goToStatusUpdate() {
    router.visit(route('presence.status_update'));
}
</script>
