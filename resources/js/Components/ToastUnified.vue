<template>
    <div class="fixed left-1/2 top-4 z-50 transform -translate-x-1/2 w-full max-w-xl">
        <!-- Shared composable toasts (calls to showToast) -->
        <transition-group name="toast" tag="div" class="space-y-3 w-full">
            <div v-for="t in composableToasts" :key="t.id" class="mx-auto toast-wrapper w-full">
                <div :class="toastClass(t.type)">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 pr-4">{{ t.message }}</div>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="t.action && typeof t.action.handler === 'function'"
                                @click="() => { t.action.handler(); dismiss(t.id); }"
                                class="text-sm text-white underline"
                            >
                                {{ t.action.label || 'Action' }}
                            </button>
                            <button @click="dismiss(t.id)" class="ml-3 text-sm text-white">✕</button>
                        </div>
                    </div>
                </div>
            </div>
        </transition-group>

        <!-- Event-driven toasts (flash/errors/jobrequest/message) managed locally -->
        <transition-group name="toast" tag="div" class="mt-3">
            <div
                v-for="toast in localToasts"
                :key="toast.id"
                v-show="toast.visible"
                class="flex items-start justify-between rounded p-4 text-base shadow-lg"
                :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-orange-600 text-white'"
            >
                <div class="flex-1">
                    <div class="font-semibold text-lg">{{ toast.type === 'error' ? 'エラー' : '通知' }}</div>
                    <div class="mt-1">{{ toast.message }}</div>
                </div>
                <button @click="dismissLocal(toast.id)" class="ms-4 text-white opacity-90 hover:opacity-100">✕</button>
            </div>
        </transition-group>
    </div>
</template>

<script setup>
import { onMounted, reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';
import useToasts from '@/Composables/useToasts';

const { toasts: composableToasts, dismissToast, toastClass } = useToasts();

// local event-driven toasts (mimics previous Toast.vue behavior)
const page = usePage();
const localToasts = reactive([]);

function pushLocal({ id, type = 'success', message = '' }) {
    const toast = { id, type, message, visible: true };
    localToasts.push(toast);
    setTimeout(() => dismissLocal(id), 4000);
}

function dismissLocal(id) {
    const idx = localToasts.findIndex((t) => t.id === id);
    if (idx !== -1) {
        localToasts[idx].visible = false;
        setTimeout(() => {
            const i = localToasts.findIndex((t) => t.id === id);
            if (i !== -1) localToasts.splice(i, 1);
        }, 300);
    }
}

// helper to dismiss composable toasts
function dismiss(id) {
    dismissToast(id);
}

onMounted(() => {
    // initialize from page flash/errors
    const flash = page.props.value?.flash || {};
    if (flash.message) {
        pushLocal({ id: `flash-${Date.now()}`, type: flash.type || 'success', message: flash.message });
    }
    const errors = page.props.value?.errors || {};
    if (errors && Object.keys(errors).length) {
        pushLocal({ id: `errors-${Date.now()}`, type: 'error', message: 'エラーがあります。詳細はページをご確認ください。' });
    }

    // Listen for events dispatched by AppLayout (jobrequest/message)
    window.addEventListener('jobrequest:received', (ev) => {
        const message = ev?.detail?.message || '新しい依頼があります';
        pushLocal({ id: `jobreq-${Date.now()}`, type: 'success', message });
    });
    window.addEventListener('message:received', (ev) => {
        const message = ev?.detail?.message || '新しいメールがあります';
        pushLocal({ id: `message-${Date.now()}`, type: 'success', message });
    });
});
</script>

<style scoped>
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}
.toast-enter-active,
.toast-leave-active {
    transition: opacity 240ms ease, transform 240ms ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
.toast-enter-to,
.toast-leave-from {
    opacity: 1;
    transform: translateY(0);
}
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
</style>
