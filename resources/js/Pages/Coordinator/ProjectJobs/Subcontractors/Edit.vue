<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  projectJob: { type: Object, required: true },
  subcontractors: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
});
const search = ref('');
const form = useForm({ subcontractor_ids: [...props.selectedIds] });
const filtered = computed(() => {
  const word = search.value.trim().toLowerCase();
  if (!word) return props.subcontractors;
  return props.subcontractors.filter((row) => [row.name, row.email, row.phone].some((value) => String(value ?? '').toLowerCase().includes(word)));
});
const selected = computed(() => props.subcontractors.filter((row) => form.subcontractor_ids.includes(row.id)));
function submit() {
  form.put(route('coordinator.project_jobs.subcontractors.update', { projectJob: props.projectJob.id }));
}
</script>

<template>
  <AppLayout title="案件外注先管理">
    <template #header><div class="flex items-center gap-3"><Link :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id })" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 案件詳細に戻る</Link><h2 class="text-base font-semibold text-gray-800 sm:text-xl">案件外注先管理</h2></div></template>
    <form class="rounded bg-white p-4 shadow sm:p-6" @submit.prevent="submit">
      <div class="border-b pb-4"><p class="text-xs text-gray-500">{{ projectJob.jobcode || '伝票番号なし' }}</p><h3 class="text-lg font-semibold text-gray-900">{{ projectJob.title }}</h3><p class="mt-2 text-sm text-gray-600">この案件の担当候補として使用する外注先を選択します。選択していない外注先は銀本や進行表の新規担当候補に表示されません。</p></div>
      <div v-if="selected.length" class="mt-4 rounded bg-purple-50 p-3"><p class="mb-2 text-xs font-semibold text-purple-800">選択中 {{ selected.length }}件</p><div class="flex flex-wrap gap-2"><span v-for="row in selected" :key="row.id" class="rounded-full border border-purple-200 bg-white px-3 py-1 text-sm text-purple-800">{{ row.name }}</span></div></div>
      <label class="mt-4 block text-xs text-gray-600">外注先を検索<input v-model="search" type="search" class="mt-1 w-full rounded border-gray-300 text-sm" placeholder="名称・メール・電話番号"></label>
      <div class="mt-3 max-h-[28rem] overflow-y-auto rounded border">
        <label v-for="row in filtered" :key="row.id" class="flex cursor-pointer items-center gap-3 border-b px-4 py-3 hover:bg-purple-50"><input v-model="form.subcontractor_ids" type="checkbox" :value="row.id" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"><span class="min-w-0"><b class="block text-sm text-gray-900">{{ row.name }}</b><small class="text-gray-500">{{ row.email || row.phone || '連絡先未登録' }}</small></span></label>
        <p v-if="!filtered.length" class="px-4 py-10 text-center text-sm text-gray-500">該当する外注先がありません。先に外注先管理へ登録してください。</p>
      </div>
      <p v-if="form.errors.subcontractor_ids" class="mt-2 text-sm text-red-600">{{ form.errors.subcontractor_ids }}</p>
      <div class="mt-5 flex justify-between gap-3"><Link :href="route('coordinator.subcontractors.index')" class="rounded border border-purple-300 px-4 py-2 text-sm text-purple-700 hover:bg-purple-50">外注先マスターを開く</Link><button :disabled="form.processing" class="rounded bg-purple-600 px-6 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">{{ form.processing ? '保存中…' : '案件外注先を保存' }}</button></div>
    </form>
  </AppLayout>
</template>
