<script setup>
import axios from 'axios';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { route } from 'ziggy-js';
import BroadProjectForm from '@/Pages/SuperAdmin/BroadProjects/BroadProjectForm.vue';
const props = defineProps({ initialName: { type: String, default: '' } });
const emit = defineEmits(['saved', 'cancel']);
const projects = ref([]);
const scope = ref(null);
const loading = ref(true);
const error = ref('');
const dialog = ref(null);
const busy = ref(false);
let live = true;
let previousOverflow;
async function load() {
    loading.value = true; error.value = '';
    try {
        const { data } = await axios.get(route('user.work.broad_projects.quick_options'));
        if (!live) return;
        projects.value = data.projects; scope.value = data.scope;
    } catch { if (live) error.value = '追加先を読み込めませんでした。再試行するか、案件検索に戻ってください。'; }
    finally {
        if (live) { loading.value = false; await nextTick(); dialog.value?.querySelector('input,select,button')?.focus(); }
    }
}
function onKeydown(event) {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); return; }
    if (event.key !== 'Tab') return;
    const elements = [...(dialog.value?.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), [tabindex="0"]') || [])].filter(el => !el.closest('fieldset:disabled'));
    if (!elements.length) { event.preventDefault(); dialog.value?.focus(); return; }
    const first = elements[0], last = elements.at(-1);
    if (!dialog.value.contains(document.activeElement) || (event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
        event.preventDefault(); (event.shiftKey ? last : first).focus();
    }
}
onMounted(() => {
    previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);
    load();
});
onUnmounted(() => { live = false; document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', onKeydown); });
</script>
<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[2300] flex items-center justify-center overscroll-contain bg-black/50 px-4">
            <div ref="dialog" role="dialog" aria-modal="true" aria-labelledby="quick-broad-heading" tabindex="-1" class="max-h-[90vh] w-full max-w-xl overflow-y-auto overscroll-contain rounded-xl bg-white p-5 shadow-xl" :aria-busy="loading || busy">
                <h3 id="quick-broad-heading" class="text-lg font-semibold">大まかな案件を追加</h3>
                <p class="my-3 text-xs leading-relaxed text-gray-600">追加した分類は情報出版の皆さんと共有されます。マイジョブはまだ保存されません。あとでマイジョブの入力を取り消しても、この分類は残ります。</p>
                <p v-if="loading" class="py-3 text-sm text-gray-500">追加先を読み込んでいます…</p>
                <div v-else-if="error" class="space-y-3"><p role="alert" class="text-sm text-red-700">{{ error }}</p><button type="button" class="text-sm text-blue-700 underline" @click="load">再試行</button></div>
                <BroadProjectForm v-else :projects="projects" :scope="scope" :initial-name="initialName" inline-create :allow-status="false" route-prefix="user.work.broad_projects" @busy="busy = $event" @saved="emit('saved', $event)" @existing="emit('saved', $event)" @cancel="emit('cancel')" />
                <button v-if="loading || error" type="button" class="mt-4 text-sm text-gray-600 underline" @click="emit('cancel')">案件検索に戻る</button>
            </div>
        </div>
    </Teleport>
</template>
