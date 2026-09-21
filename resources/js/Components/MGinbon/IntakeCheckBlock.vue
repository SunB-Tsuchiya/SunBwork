<script setup>
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';

const props = defineProps({ item: { type: Object, required: true } });
const subjectOrder = ['japanese', 'math', 'social', 'science'];
const subject = (code) => props.item.subjects?.find((row) => row.code === code);
const date = (code, key) => subject(code)?.dates?.[key]?.slice(5).replace('-', '/') || '—';
const actor = (code, stage) => subject(code)?.stages?.find((row) => row.code === stage)?.actor || '—';
const qty = (code, type, execution) => subject(code)?.measurements?.find((row) => row.work_type === type && row.execution_type === execution)?.quantity ?? 0;
const headers = { japanese: '国語', math: '算数', social: '社会', science: '理科' };
const saving = ref(false);
const saved = ref(false);
const error = ref('');
const form = reactive(Object.fromEntries((props.item.subjects ?? []).map((row) => [row.id, {
  'scan:subcontracted': qty(row.code, 'scan', 'subcontracted'),
  'drawing:subcontracted': qty(row.code, 'drawing', 'subcontracted'),
  'scan:internal': qty(row.code, 'scan', 'internal'),
  'drawing:internal': qty(row.code, 'drawing', 'internal'),
}])));
let updatedAt = props.item.updated_at;
const inputValue = (code, key) => form[subject(code)?.id]?.[key] ?? 0;
function setInput(code, key, value) {
  const id = subject(code)?.id;
  if (id) form[id][key] = Math.max(0, Number.parseInt(value || 0, 10) || 0);
  saved.value = false;
}
function total(key) { return Object.values(form).reduce((sum, row) => sum + (Number(row[key]) || 0), 0); }
async function save() {
  saving.value = true; error.value = ''; saved.value = false;
  try {
    const response = await axios.patch(route('coordinator.mginbon.items.intake_check.update', { item: props.item.id }), {
      updated_at: updatedAt,
      subjects: Object.entries(form).map(([id, measurements]) => ({ id: Number(id), measurements })),
    });
    updatedAt = response.data.updated_at;
    saved.value = true;
  } catch (e) {
    error.value = e?.response?.data?.errors?.updated_at?.[0] ?? e?.response?.data?.message ?? '保存できませんでした。';
  } finally { saving.value = false; }
}
</script>

<template>
  <section class="grid border-b border-gray-500 bg-white lg:grid-cols-[17rem_minmax(42rem,1fr)]">
    <div class="border-r border-gray-500 bg-[#f6fad3] p-2 text-xs">
      <div class="flex items-center gap-2"><span class="bg-gray-700 px-2 py-1 font-semibold text-white">{{ item.media_name }}</span><span class="font-semibold">銀本掲載 {{ item.publication_status || '—' }}</span></div>
      <div class="mt-2 grid grid-cols-2 gap-1"><span class="bg-[#ccffcc] px-2 py-1">科目 {{ item.subjects.map((s) => s.name).join('・') }}</span><span class="bg-[#ccffcc] px-2 py-1">初校OP {{ actor('japanese', 'initial_operation') }}</span><span class="bg-[#ccffcc] px-2 py-1">原本入稿 {{ item.shared_dates.original_received_on || '—' }}</span><span class="bg-[#ccffcc] px-2 py-1">銀本掲載</span></div>
      <div class="mt-2 min-h-12 border border-dotted border-gray-500 bg-white p-1 text-gray-600">{{ item.note || '' }}</div>
      <div class="mt-2 flex flex-wrap items-center gap-2"><button type="button" :disabled="saving" class="rounded bg-green-700 px-3 py-1 font-medium text-white disabled:opacity-50" @click="save">{{ saving ? '保存中…' : '点数を保存' }}</button><Link :href="route('coordinator.mginbon.items.show', { item: item.id })" class="rounded border border-green-700 bg-white px-2 py-1 font-medium text-green-800">担当・詳細</Link><span v-if="saved" class="font-medium text-green-700">保存しました</span></div>
      <p v-if="error" class="mt-1 text-red-600">{{ error }}</p>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full min-w-[42rem] border-collapse text-xs">
        <thead><tr class="bg-[#ccffff]"><th class="w-28 border-r border-gray-400 px-2 py-1 text-left"></th><th v-for="code in subjectOrder" :key="code" class="border-r border-gray-400 px-2 py-1">{{ headers[code] }}</th><th class="w-12 px-2 py-1 text-blue-700">合計</th></tr></thead>
        <tbody>
          <tr><th class="border-r border-t border-gray-400 px-2 py-1 text-right">入稿</th><td v-for="code in subjectOrder" :key="code" class="border-r border-t border-gray-400 bg-[#ffccff] px-2 py-1 text-center">{{ date(code, 'manuscript_received_on') }}</td><td></td></tr>
          <tr><th class="border-r border-gray-400 px-2 py-1 text-right">文字入力</th><td v-for="code in subjectOrder" :key="code" class="border-r border-gray-400 bg-[#ccffff] px-2 py-1 text-center">{{ actor(code, 'text_input') }}</td><td></td></tr>
          <tr><th class="border-r border-gray-400 px-2 py-1 text-right">作図・スキャン</th><td v-for="code in subjectOrder" :key="code" class="border-r border-gray-400 bg-[#e8ebff] px-2 py-1 text-center">{{ actor(code, 'drawing') }}</td><td></td></tr>
          <tr v-for="row in [['scan','subcontracted','社外／scan点数'],['drawing','subcontracted','社外／作図点数'],['scan','internal','社内／scan点数'],['drawing','internal','社内／作図点数']]" :key="`${row[0]}-${row[1]}`"><th class="border-r border-gray-400 px-2 py-1 text-right">{{ row[2] }}</th><td v-for="code in subjectOrder" :key="code" class="border-r border-gray-400 bg-[#d1d1d1] p-0.5 text-center"><input v-if="subject(code)" type="number" min="0" max="999999" :value="inputValue(code, `${row[0]}:${row[1]}`)" class="w-full border-0 bg-white/70 px-1 py-0.5 text-center text-xs focus:ring-2 focus:ring-green-500" @input="setInput(code, `${row[0]}:${row[1]}`, $event.target.value)" /><span v-else>—</span></td><td class="px-2 py-1 text-center font-semibold text-blue-700">{{ total(`${row[0]}:${row[1]}`) || '' }}</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
