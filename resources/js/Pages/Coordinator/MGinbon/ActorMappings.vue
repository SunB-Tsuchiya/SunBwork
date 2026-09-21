<script setup>
import { reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  project: { type: Object, required: true }, projects: { type: Array, default: () => [] },
  mappings: { type: Array, default: () => [] }, users: { type: Array, default: () => [] },
  subcontractors: { type: Array, default: () => [] }, filters: { type: Object, required: true },
});
const filters = reactive({ ...props.filters });
const savingValue = reactive({});

function search() {
  router.get(route('coordinator.mginbon.actor_mappings.index'), { ...filters }, { preserveState: true, replace: true });
}
function currentTarget(row) {
  return row.target_type && row.target_type !== 'ignore' ? `${row.target_type}:${row.target_id}` : (row.target_type === 'ignore' ? 'ignore:' : '');
}
function save(row) {
  if (!savingValue[row.legacy_value]) return;
  useForm({ project_id: props.project.id, legacy_value: row.legacy_value, target: savingValue[row.legacy_value] })
    .put(route('coordinator.mginbon.actor_mappings.update'), { preserveScroll: true });
}
</script>

<template>
  <AppLayout title="銀本・担当候補の対応付け">
    <template #header><div class="flex flex-wrap items-center gap-3"><Link :href="route('coordinator.mginbon.index', { year: project.year })" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 銀本進行へ戻る</Link><h2 class="text-base font-semibold text-gray-800 sm:text-xl">旧担当候補の対応付け</h2></div></template>
    <div class="space-y-4">
      <section class="rounded bg-white p-4 shadow sm:p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
          <label class="text-xs text-gray-600">年度<select v-model.number="filters.year" class="mt-1 block rounded border-gray-300 text-sm"><option v-for="item in projects" :key="item.id" :value="item.year">{{ item.year }}年</option></select></label>
          <label class="flex-1 text-xs text-gray-600">旧担当名<input v-model="filters.search" type="search" class="mt-1 w-full rounded border-gray-300 text-sm" placeholder="旧担当名を検索" /></label>
          <label class="text-xs text-gray-600">状態<select v-model="filters.status" class="mt-1 block rounded border-gray-300 text-sm"><option value="unresolved">未対応</option><option value="resolved">対応済み</option><option value="all">すべて</option></select></label>
          <button class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="search">検索</button>
        </div>
        <p class="mt-3 text-xs text-gray-500">旧担当名を一度対応付けると、同じ年度・同じ旧値を持つ全工程へ反映します。「赤ナシ」など担当者ではない値は「担当者ではない」を選択してください。</p>
      </section>

      <section class="overflow-hidden rounded bg-white shadow">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] divide-y text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-600"><tr><th class="px-4 py-3">旧担当名</th><th class="px-4 py-3">使用</th><th class="px-4 py-3">工程</th><th class="px-4 py-3">現在の対応</th><th class="px-4 py-3">対応先を選択</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y">
              <tr v-for="row in mappings" :key="row.legacy_value" class="align-top">
                <td class="px-4 py-3 font-semibold text-gray-900">{{ row.legacy_value }}</td>
                <td class="whitespace-nowrap px-4 py-3"><b>{{ row.usage_count }}</b>工程<br><span class="text-xs text-gray-500">{{ row.item_count }}媒体</span></td>
                <td class="max-w-sm px-4 py-3 text-xs text-gray-600">{{ row.stage_names }}</td>
                <td class="px-4 py-3 text-xs"><span v-if="row.target_type === 'user'" class="rounded bg-blue-50 px-2 py-1 text-blue-800">社員: {{ row.target_label }}</span><span v-else-if="row.target_type === 'subcontractor'" class="rounded bg-purple-50 px-2 py-1 text-purple-800">外注: {{ row.target_label }}</span><span v-else-if="row.target_type === 'ignore'" class="rounded bg-gray-100 px-2 py-1 text-gray-600">担当者ではない</span><span v-else class="rounded bg-orange-50 px-2 py-1 text-orange-800">未対応</span></td>
                <td class="px-4 py-3"><select v-model="savingValue[row.legacy_value]" class="w-full rounded border-gray-300 text-sm"><option value="">選択してください</option><optgroup label="社員"><option v-for="user in users" :key="`u${user.id}`" :value="`user:${user.id}`">{{ user.name }}</option></optgroup><optgroup label="外注先"><option v-for="vendor in subcontractors" :key="`s${vendor.id}`" :value="`subcontractor:${vendor.id}`">{{ vendor.name }}</option></optgroup><option value="ignore:">担当者ではない／状態値</option></select></td>
                <td class="px-4 py-3"><button :disabled="!savingValue[row.legacy_value]" class="rounded bg-green-600 px-3 py-2 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-40" @click="save(row)">保存</button></td>
              </tr>
              <tr v-if="!mappings.length"><td colspan="6" class="px-4 py-10 text-center text-gray-500">条件に一致する旧担当候補はありません。</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
