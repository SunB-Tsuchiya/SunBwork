<template>
    <div class="fixed bottom-4 right-4 z-50">
        <transition-group name="toast" tag="div" class="space-y-2">
            <div v-for="t in toasts" :key="t.id" class="toast-wrapper max-w-sm">
                <div :class="toastClass(t.type)">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 pr-3">{{ t.message }}</div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="t.action && typeof t.action.handler === 'function'"
                                @click="
                                    () => {
                                        t.action.handler();
                                        dismissToast(t.id);
                                    }
                                "
                                class="text-sm text-white underline"
                            >
                                {{ t.action.label || 'Action' }}
                            </button>
                            <button @click="dismissToast(t.id)" class="ml-1 text-xs text-white">✕</button>
                        </div>
                    </div>
                </div>
            </div>
        </transition-group>
    </div>
</template>

<script setup>
import useToasts from '@/Composables/useToasts';

// shared composable instance
const { toasts, dismissToast, toastClass } = useToasts();

defineExpose({ toasts, dismissToast, toastClass });
</script>

<style scoped>
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}
.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 240ms ease,
        transform 240ms ease;
}
</style>
