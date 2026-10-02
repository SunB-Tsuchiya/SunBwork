<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const model = defineModel({ type: Array, required: true });
const emit = defineEmits(['update:activeIndex']);
const props = defineProps({
  columns: { type: Array, default: () => [] },
  activeIndex: { type: Number, default: 0 },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
});
const subjectColumns = [
  { code: 'japanese', name: '国語' },
  { code: 'math', name: '算数' },
  { code: 'social', name: '社会' },
  { code: 'science', name: '理科' },
];
const reproofSharedIndex = computed(() => props.columns.findIndex((column) => column.code === 'reproof_shared_on'));
const bands = computed(() => [
  { key: 'first', columns: props.columns.slice(0, reproofSharedIndex.value) },
  { key: 'second', columns: props.columns.slice(reproofSharedIndex.value) },
]);
const requestAt = (requestIndex) => model.value[requestIndex];

function value(requestIndex, field, code = '', subject = '') {
  return requestAt(requestIndex)?.conditions.find((row) => row.field === field && row.code === code && row.subject === subject)?.value ?? '';
}
function selectedSubject(requestIndex) {
  return requestAt(requestIndex)?.conditions.find((row) => row.field === 'subject')?.subject ?? '';
}
function setSubject(requestIndex, nextValue) {
  const request = requestAt(requestIndex);
  if (!request) return;
  request.conditions = request.conditions.filter((row) => row.field !== 'subject');
  if (nextValue !== '') request.conditions.push({ field: 'subject', code: '', subject: nextValue, value: nextValue });
}
function setValue(requestIndex, field, code, subject, nextValue) {
  const request = requestAt(requestIndex);
  if (!request) return;
  const index = request.conditions.findIndex((row) => row.field === field && row.code === code && row.subject === subject);
  if (nextValue === '') {
    if (index !== -1) request.conditions.splice(index, 1);
    return;
  }
  const condition = { field, code, subject, value: nextValue };
  if (index === -1) request.conditions.push(condition);
  else request.conditions[index] = condition;
}
function scopedActorOptions(code) {
  return props.actorOptions.by_stage?.[code] ?? props.actorOptions;
}
const columnClass = (tone) => ({
  intake: 'bg-[#ffccff]', prepress: 'bg-[#fff0cb]', scan: 'bg-[#dadada]', operation: 'bg-[#66ffcc]',
  output: 'bg-[#fffb83]', proof: 'bg-white', complete: 'bg-[#ccffcc]',
})[tone] ?? 'bg-white';
const inputClass = 'w-full border-0 bg-white/35 px-1 py-0 text-center text-[11px] placeholder:text-gray-500 focus:bg-white focus:ring-2 focus:ring-green-500';
const focusedCondition = ref(null);
function rememberCondition(requestIndex, field, code = '', subject = '') {
  focusedCondition.value = { requestIndex, field, code, subject };
  emit('update:activeIndex', requestIndex);
}
function insertOperator(event) {
  const target = focusedCondition.value;
  if (!target || typeof event.detail !== 'string') return;
  const current = value(target.requestIndex, target.field, target.code, target.subject);
  setValue(target.requestIndex, target.field, target.code, target.subject, `${event.detail}${current}`);
}
function applyCalendarDate(requestIndex, code, subject, date) {
  if (!date) return;
  const current = value(requestIndex, 'milestone', code, subject);
  const nextValue = /^(?:==|<=|>=|[=<>≤≥]|.*\.\.\.)$/.test(current) ? `${current}${date}` : date;
  setValue(requestIndex, 'milestone', code, subject, nextValue);
}
onMounted(() => window.addEventListener('mginbon-find-insert-operator', insertOperator));
onBeforeUnmount(() => window.removeEventListener('mginbon-find-insert-operator', insertOperator));
</script>

<template>
  <section
    v-for="(request, requestIndex) in model"
    :key="requestIndex"
    class="mb-2 w-fit bg-white ring-offset-1"
    :class="requestIndex === activeIndex ? 'ring-2 ring-green-600' : 'ring-1 ring-gray-300'"
    @pointerdown="emit('update:activeIndex', requestIndex)"
  >
    <div class="border-b border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
      検索条件 {{ requestIndex + 1 }} / {{ model.length }}{{ request.omit ? '（除外）' : '（含める）' }}{{ requestIndex === activeIndex ? '・選択中' : '' }}：同じ箱の入力はすべて一致（AND）、追加した箱同士はいずれか一致（OR）
    </div>
    <div class="grid w-[96.25rem] max-w-none border-b border-gray-700 lg:grid-cols-[17rem_79.25rem]">
      <div class="bg-white p-1 text-xs leading-snug">
        <div class="grid h-6 grid-cols-4 items-center bg-[#ccffff] text-center text-[11px]">
          <span>みくにコード</span><span>日能研コード</span><span>分類</span><span>媒体</span>
        </div>
        <div class="grid h-7 grid-cols-4 gap-0.5">
          <input :class="inputClass" class="h-full border border-gray-400 bg-gray-100" placeholder="コード" :value="value(requestIndex, 'mikuni_code')" @focus="rememberCondition(requestIndex, 'mikuni_code')" @input="setValue(requestIndex, 'mikuni_code', '', '', $event.target.value)" />
          <input :class="inputClass" class="h-full border border-gray-400 bg-white" placeholder="コード" :value="value(requestIndex, 'n_code')" @focus="rememberCondition(requestIndex, 'n_code')" @input="setValue(requestIndex, 'n_code', '', '', $event.target.value)" />
          <input :class="inputClass" class="h-full border border-gray-400 bg-white" placeholder="分類" :value="value(requestIndex, 'category')" @focus="rememberCondition(requestIndex, 'category')" @input="setValue(requestIndex, 'category', '', '', $event.target.value)" />
          <input :class="inputClass" class="h-full border border-gray-400 bg-white" placeholder="媒体" :value="value(requestIndex, 'media')" @focus="rememberCondition(requestIndex, 'media')" @input="setValue(requestIndex, 'media', '', '', $event.target.value)" />
        </div>
        <input :class="inputClass" class="mt-0.5 h-8 bg-[#f6fad3] px-2 text-left text-sm font-semibold" placeholder="学校名" :value="value(requestIndex, 'school_name')" @focus="rememberCondition(requestIndex, 'school_name')" @input="setValue(requestIndex, 'school_name', '', '', $event.target.value)" />
        <div class="grid h-8 grid-cols-[1fr_4.25rem] items-center gap-0.5">
          <select :class="inputClass" class="h-full bg-[#ccffcc] text-left" :value="selectedSubject(requestIndex)" @focus="emit('update:activeIndex', requestIndex)" @change="setSubject(requestIndex, $event.target.value)">
            <option value="">科目：すべて</option>
            <option v-for="subject in subjectColumns" :key="subject.code" :value="subject.code">{{ subject.name }}</option>
          </select>
          <input :class="inputClass" class="h-full bg-[#ccffcc]" placeholder="掲載" :value="value(requestIndex, 'publication_status')" @focus="rememberCondition(requestIndex, 'publication_status')" @input="setValue(requestIndex, 'publication_status', '', '', $event.target.value)" />
        </div>
        <textarea class="mt-1 h-[7.25rem] w-full resize-none border-0 bg-white px-1 py-1 text-xs leading-7 placeholder:text-gray-500 focus:ring-2 focus:ring-green-500" placeholder="備考" :value="value(requestIndex, 'note')" @focus="rememberCondition(requestIndex, 'note')" @input="setValue(requestIndex, 'note', '', '', $event.target.value)"></textarea>
      </div>

      <div class="overflow-x-auto bg-white">
        <table v-for="band in bands" :key="band.key" class="w-[79.25rem] min-w-[79.25rem] table-fixed border-collapse border-b border-gray-600 text-[11px] leading-tight last:border-b-0">
          <colgroup><col class="w-11" /><col v-for="column in band.columns" :key="`width-${column.code}`" class="w-[4.5rem]" /></colgroup>
          <thead>
            <tr class="h-6 bg-[#ccffff] text-center">
              <th class="w-11 px-1 py-1"></th>
              <th v-for="column in band.columns" :key="column.code" class="border-r border-white px-1 py-1 font-medium">{{ column.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="subject in subjectColumns" :key="subject.code" class="border-b border-white text-center last:border-b-0">
              <th class="h-7 w-11 bg-white px-1 py-1 text-right text-xs font-semibold">{{ subject.name }}</th>
              <td v-for="column in band.columns" :key="column.code" class="h-7 border-r border-white p-0" :class="columnClass(column.tone)">
                <select
                  v-if="column.type === 'stage'"
                  :class="[inputClass, 'h-full']"
                  :value="value(requestIndex, 'actor', column.code, subject.code)"
                  :title="value(requestIndex, 'actor', column.code, subject.code) || '作業者を選択'"
                  @focus="rememberCondition(requestIndex, 'actor', column.code, subject.code)"
                  @change="setValue(requestIndex, 'actor', column.code, subject.code, $event.target.value)"
                >
                  <option value="">作業者</option>
                  <optgroup v-if="scopedActorOptions(column.code).users?.length" label="メンバー">
                    <option v-for="user in scopedActorOptions(column.code).users" :key="`u-${column.code}-${user.id}`" :value="user.name">{{ user.name }}{{ user.is_ghost ? '（テスト）' : '' }}</option>
                  </optgroup>
                  <optgroup v-if="scopedActorOptions(column.code).subcontractors?.length" label="外注先">
                    <option v-for="vendor in scopedActorOptions(column.code).subcontractors" :key="`s-${column.code}-${vendor.id}`" :value="vendor.name">{{ vendor.name }}</option>
                  </optgroup>
                </select>
                <span v-else class="relative block h-full w-full">
                  <input
                    type="text"
                    inputmode="numeric"
                    :class="[inputClass, 'h-full pr-5']"
                    placeholder="yyyy/mm/dd"
                    :value="value(requestIndex, 'milestone', column.code, column.type === 'shared' ? '' : subject.code)"
                    :title="value(requestIndex, 'milestone', column.code, column.type === 'shared' ? '' : subject.code) || '日付または検索式を入力'"
                    @focus="rememberCondition(requestIndex, 'milestone', column.code, column.type === 'shared' ? '' : subject.code)"
                    @input="setValue(requestIndex, 'milestone', column.code, column.type === 'shared' ? '' : subject.code, $event.target.value)"
                  />
                  <span class="pointer-events-none absolute right-0.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-600">▣</span>
                  <input
                    type="date"
                    class="absolute inset-y-0 right-0 w-5 cursor-pointer opacity-0"
                    aria-label="カレンダーから日付を選択"
                    @focus="rememberCondition(requestIndex, 'milestone', column.code, column.type === 'shared' ? '' : subject.code)"
                    @change="applyCalendarDate(requestIndex, column.code, column.type === 'shared' ? '' : subject.code, $event.target.value); $event.target.value = ''"
                  />
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
