<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({ preview: { type: Object, default: null } });
const upload = useForm({ year: new Date().getFullYear() + 1, file: null });
const confirm = useForm({
  token: props.preview?.token || '', year: props.preview?.year || '',
  rows: props.preview?.rows?.map(({ source_row_number, mikuni_code, n_code, alpha_group, school_name, exam_session }) =>
    ({ source_row_number, mikuni_code, n_code, alpha_group, school_name, exam_session, confirmed: false })) || [],
});
watch(() => props.preview, (preview) => {
  if (!preview) return;

  confirm.token = preview.token;
  confirm.year = preview.year;
  confirm.rows = preview.rows.map(({ source_row_number, mikuni_code, n_code, alpha_group, school_name, exam_session }) => ({
    source_row_number, mikuni_code, n_code, alpha_group, school_name, exam_session, confirmed: false,
  }));
  confirm.clearErrors();
}, { immediate: true });
const unresolved = computed(() => props.preview?.rows?.filter((row) => row.warnings.length).length || 0);
const previewFile = () => upload.post(route('coordinator.mginbon.annual_import.preview'), { forceFormData: true });
const store = () => confirm.post(route('coordinator.mginbon.annual_import.store'));
</script>

<template>
  <AppLayout title="銀本 年度対象校取込">
    <template #header><div class="flex items-center gap-3"><Link :href="route('coordinator.mginbon.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 銀本進行に戻る</Link><h2 class="text-xl font-semibold text-gray-800">年度対象校Excel取込</h2></div></template>
    <Head title="銀本 年度対象校取込" />
    <div class="space-y-4">
      <section class="rounded bg-white p-6 shadow">
        <form class="flex flex-wrap items-end gap-4" @submit.prevent="previewFile">
          <label class="text-sm">年度<input v-model.number="upload.year" type="number" min="2000" max="2100" class="mt-1 block rounded border-gray-300" /></label>
          <label class="text-sm">α版対象校リスト.xlsx<input type="file" accept=".xlsx" class="mt-1 block text-sm" @change="upload.file = $event.target.files[0]" /></label>
          <button :disabled="upload.processing || !upload.file" class="rounded bg-green-700 px-5 py-2 text-white disabled:opacity-40">プレビュー</button>
        </form>
        <div v-if="upload.errors.file || upload.errors.year" class="mt-3 text-sm text-red-600">{{ upload.errors.file || upload.errors.year }}</div>
      </section>
      <section v-if="preview" class="rounded bg-white p-4 shadow">
        <div class="mb-2 flex flex-wrap items-center gap-4 text-sm"><b>{{ preview.year }}年度 / {{ preview.filename }}</b><span>登録対象 {{ preview.total }}行</span><span>α版 {{ preview.alpha_total }}行</span><span :class="unresolved ? 'text-red-700' : 'text-green-700'">修正確認が必要 {{ unresolved }}行</span><button :disabled="confirm.processing || confirm.rows.length === 0" class="ml-auto rounded bg-green-700 px-5 py-2 font-semibold text-white disabled:opacity-40" @click="store">この内容で年度を作成</button></div>
        <p v-if="unresolved === 0" class="mb-3 text-sm text-green-700">全{{ preview.total }}行を登録できます。修正確認が必要な行はありません。</p>
        <p v-else class="mb-3 text-sm text-red-700">確認内容のある行を修正し、「確認済」にチェックしてから年度を作成してください。</p>
        <div v-if="confirm.errors.rows || confirm.errors.year || confirm.errors.token" class="mb-3 rounded bg-red-50 p-3 text-sm text-red-700">{{ confirm.errors.rows || confirm.errors.year || confirm.errors.token }}</div>
        <div class="max-h-[65vh] overflow-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="sticky top-0 bg-gray-50"><tr><th class="px-2 py-2">行</th><th class="px-2 py-2">Mコード</th><th class="px-2 py-2">Nコード</th><th class="px-2 py-2">α版</th><th class="px-2 py-2">学校名</th><th class="px-2 py-2">入試回</th><th class="px-2 py-2">確認内容</th><th class="px-2 py-2">確認済</th></tr></thead>
          <tbody><tr v-for="(row, i) in confirm.rows" :key="row.source_row_number" class="hover:bg-gray-50">
            <td class="border px-2">{{ row.source_row_number }}</td><td class="border p-1"><input v-model="row.mikuni_code" class="w-20 border-gray-300 text-sm" /></td><td class="border p-1"><input v-model="row.n_code" class="w-28 border-gray-300 text-sm" /></td><td class="border p-1"><input v-model="row.alpha_group" class="w-16 border-gray-300 text-sm" /></td><td class="border p-1"><input v-model="row.school_name" class="w-72 border-gray-300 text-sm" /></td><td class="border p-1"><input v-model="row.exam_session" class="w-52 border-gray-300 text-sm" /></td><td class="border px-2 text-xs text-red-700">{{ preview.rows[i].warnings.join(' / ') }}</td><td class="border px-2 text-center"><input v-if="preview.rows[i].warnings.length" v-model="row.confirmed" type="checkbox" class="rounded border-gray-300 text-green-700" /><span v-else class="text-green-700">―</span></td>
          </tr></tbody>
        </table></div>
      </section>
    </div>
  </AppLayout>
</template>
