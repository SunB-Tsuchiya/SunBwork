<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const toasts = reactive([]);

function pushToast({ id, type = 'success', message = '' }) {
  const toast = { id, type, message, visible: true };
  toasts.push(toast);
  // auto dismiss after 4s
  setTimeout(() => dismissToast(id), 4000);
}

function dismissToast(id) {
  const idx = toasts.findIndex(t => t.id === id);
  if (idx !== -1) {
    toasts[idx].visible = false;
    // remove after transition
    setTimeout(() => {
      const i = toasts.findIndex(t => t.id === id);
      if (i !== -1) toasts.splice(i, 1);
    }, 300);
  }
}

// Initialize from Inertia page props
onMounted(() => {
  const flash = page.props.value?.flash || {};
  if (flash.message) {
    pushToast({ id: `flash-${Date.now()}`, type: flash.type || 'success', message: flash.message });
  }

  const errors = page.props.value?.errors || {};
  if (errors && Object.keys(errors).length) {
    // show a summarized error toast
    pushToast({ id: `errors-${Date.now()}`, type: 'error', message: 'エラーがあります。詳細はページをご確認ください。' });
  }
});

// Also watch for changes to flash/errors (e.g., page reloads)
watch(() => page.props.value?.flash, (newVal) => {
  if (newVal && newVal.message) {
    pushToast({ id: `flash-${Date.now()}`, type: newVal.type || 'success', message: newVal.message });
  }
});

watch(() => page.props.value?.errors, (newVal) => {
  if (newVal && Object.keys(newVal).length) {
    pushToast({ id: `errors-${Date.now()}`, type: 'error', message: 'エラーがあります。詳細はページをご確認ください。' });
  }
});
</script>

<template>
  <div class="fixed top-6 right-6 z-50 space-y-2 w-80">
    <transition-group name="toast" tag="div">
      <div v-for="toast in toasts" :key="toast.id" v-show="toast.visible" class="flex items-start space-x-3 p-3 rounded shadow-lg text-sm" :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-green-600 text-white'">
        <div class="flex-1">
          <div class="font-medium">{{ toast.type === 'error' ? 'エラー' : '完了' }}</div>
          <div class="mt-1">{{ toast.message }}</div>
        </div>
        <button @click="dismissToast(toast.id)" class="ms-3 opacity-90 hover:opacity-100">✕</button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-6px); }
.toast-enter-to, .toast-leave-from { opacity: 1; transform: translateY(0); }
.toast-enter-active, .toast-leave-active { transition: all .25s ease; }
</style>
