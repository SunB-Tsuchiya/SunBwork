<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LedgerCellEditor from '@/Components/MGinbon/LedgerCellEditor.vue';

const props = defineProps({
  units: { type: Array, default: () => [] },
  columns: { type: Array, default: () => [] },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  projectLinked: { type: Boolean, default: false },
});
const emit = defineEmits(['saved']);

const subjectColumns = [
  { code: 'japanese', name: '国語' },
  { code: 'math', name: '算数' },
  { code: 'social', name: '社会' },
  { code: 'science', name: '理科' },
];
const records = computed(() => props.units.flatMap((unit) => unit.items.map((item) => ({ unit, item }))));
const subject = (item, code) => item.subjects.find((row) => row.code === code);
const task = (row, code) => row?.stages?.find((stage) => stage.code === code);
const matchesDescriptor = (item, field, code = '', subjectCode = '') => (item.match_descriptors ?? []).some((descriptor) => descriptor.field === field && (!code || descriptor.code === code) && (!descriptor.subject || !subjectCode || descriptor.subject === subjectCode));
const matchClass = (item, field, code = '', subjectCode = '') => matchesDescriptor(item, field, code, subjectCode) ? 'ring-2 ring-inset ring-emerald-600' : '';
const columnClass = (tone) => ({
  intake: 'bg-[#ffccff]', prepress: 'bg-[#fff0cb]', scan: 'bg-[#dadada]', operation: 'bg-[#66ffcc]',
  output: 'bg-[#fffb83]', proof: 'bg-white', complete: 'bg-[#ccffcc]',
})[tone] ?? 'bg-white';
const columnBorderClass = (tone) => tone === 'proof'
  ? 'border border-black/50 outline outline-1 -outline-offset-1 outline-dotted outline-black/50'
  : 'border border-white';
</script>

<template>
  <section class="overflow-x-auto bg-white">
    <table class="min-w-max table-fixed border-collapse text-[11px] leading-tight">
      <thead class="sticky top-0 z-20 bg-[#ccffff] text-center">
        <tr>
          <th rowspan="2" class="w-20 border border-gray-400 px-1 py-1 font-medium">みくにコード</th>
          <th rowspan="2" class="w-20 border border-gray-400 px-1 py-1 font-medium">日能研コード</th>
          <th rowspan="2" class="w-20 border border-gray-400 px-1 py-1 font-medium">分類</th>
          <th rowspan="2" class="w-52 border border-gray-400 px-1 py-1 font-medium">学校名</th>
          <th rowspan="2" class="w-20 border border-gray-400 px-1 py-1 font-medium">媒体</th>
          <th rowspan="2" class="w-36 border border-gray-400 px-1 py-1 font-medium">科目</th>
          <th rowspan="2" class="w-16 border border-gray-400 px-1 py-1 font-medium">銀本掲載</th>
          <th v-for="column in columns" :key="column.code" colspan="4" class="px-1 py-1 font-medium" :class="column.tone === 'proof' ? columnBorderClass(column.tone) : 'border border-gray-400'">{{ column.label }}</th>
          <th rowspan="2" class="w-12 border border-gray-400 px-1 py-1 font-medium">詳細</th>
        </tr>
        <tr>
          <template v-for="column in columns" :key="`subjects-${column.code}`">
            <th v-for="subjectColumn in subjectColumns" :key="`${column.code}-${subjectColumn.code}`" class="w-16 px-1 py-0.5 font-normal" :class="column.tone === 'proof' ? columnBorderClass(column.tone) : 'border border-gray-400'">{{ subjectColumn.name }}</th>
          </template>
        </tr>
      </thead>
      <tbody>
        <tr v-for="{ unit, item } in records" :key="item.id" class="hover:brightness-[0.98]">
          <td class="border border-gray-300 bg-gray-500 px-1 py-1 text-center text-white" :class="matchClass(item, 'mikuni_code')">{{ unit.mikuni_code || '' }}</td>
          <td class="border border-gray-300 px-1 py-1 text-center" :class="matchClass(item, 'n_code')">{{ unit.n_code || '' }}</td>
          <td class="border border-gray-300 px-1 py-1 text-center" :class="matchClass(item, 'category')">{{ unit.n_category || unit.school_category || '' }}</td>
          <td class="border border-gray-300 bg-[#f6fad3] px-1 py-1 font-semibold" :class="matchClass(item, 'school_name')"><span>{{ unit.display_name }}</span><details v-if="item.match_descriptors?.length" class="mt-1 text-[10px] font-normal text-emerald-900"><summary class="cursor-pointer font-semibold">一致理由 {{ item.match_descriptors.length }}件</summary><div v-for="(descriptor, index) in item.match_descriptors" :key="index">{{ descriptor.field }} {{ descriptor.code }}：{{ descriptor.value }}</div></details></td>
          <td class="border border-gray-300 px-1 py-1 text-center" :class="matchClass(item, 'media')">{{ item.media_name }}</td>
          <td class="border border-gray-300 bg-[#ccffcc] px-1 py-1 text-center font-semibold">{{ item.subjects.map((row) => row.name).join('・') }}</td>
          <td class="border border-gray-300 bg-[#ccffcc] px-1 py-1 text-center" :class="matchClass(item, 'publication_status')">{{ item.publication_status || '○' }}</td>
          <template v-for="column in columns" :key="column.code">
            <td v-for="subjectColumn in subjectColumns" :key="`${column.code}-${subjectColumn.code}`" class="h-7 p-0 text-center font-medium" :class="[columnClass(column.tone), columnBorderClass(column.tone), matchClass(item, column.type === 'stage' ? 'actor' : 'milestone', column.code, column.type === 'shared' ? '' : subjectColumn.code)]">
              <LedgerCellEditor
                v-if="subject(item, subjectColumn.code)"
                :item="item"
                :subject="subject(item, subjectColumn.code)"
                :column="column"
                :task="task(subject(item, subjectColumn.code), column.code)"
                :actor-options="actorOptions"
                :project-linked="projectLinked"
                @saved="emit('saved', item, subject(item, subjectColumn.code), $event)"
              />
              <span v-else class="text-gray-400">—</span>
            </td>
          </template>
          <td class="border border-gray-300 bg-gray-100 p-0 text-center"><Link :href="route('coordinator.mginbon.items.show', { item: item.id })" class="block px-1 py-1 hover:bg-gray-200">編集</Link></td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
