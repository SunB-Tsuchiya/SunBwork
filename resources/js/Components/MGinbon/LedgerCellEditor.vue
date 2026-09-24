<script setup>
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
  item: { type: Object, required: true },
  subject: { type: Object, required: true },
  column: { type: Object, required: true },
  task: { type: Object, default: null },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  projectLinked: { type: Boolean, default: false },
});
const emit = defineEmits(['saved']);
const editing = ref(false);
const saving = ref(false);
const editor = ref(null);
const root = ref(null);
const actorScope = ref('one');
const dateScope = ref('one');
const errorMessage = ref('');
function closeOnOutside(event) {
  if (editing.value && root.value && !root.value.contains(event.target)) editing.value = false;
}
function closeOnEscape(event) {
  if (editing.value && event.key === 'Escape') editing.value = false;
}
watch(editing, (open) => {
  const method = open ? 'addEventListener' : 'removeEventListener';
  document[method]('pointerdown', closeOnOutside, true);
  document[method]('keydown', closeOnEscape);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeOnOutside, true);
  document.removeEventListener('keydown', closeOnEscape);
});
const shortDate = (value) => value ? value.slice(5).replace('-', '/') : '';
const dateValue = () => props.column.type === 'shared'
  ? (props.item.shared_dates?.[props.column.code] || '')
  : (props.subject.dates?.[props.column.code] || '');
const display = () => props.column.type === 'stage' ? (props.task?.actor || '') : shortDate(dateValue());
const eligibleSubjects = computed(() => props.item.subjects.filter((subject) => {
  const row = subject.stages?.find((stage) => stage.code === props.column.code);
  return row?.stage_id === props.task?.stage_id && !row?.assignment_id && row?.status === 'not_started';
}));
const canEdit = () => props.column.type !== 'stage' || (props.projectLinked && props.task?.stage_id
  && !props.task?.assignment_id && props.task?.status === 'not_started');
const actorStateClass = () => {
  if (props.task?.planned) return 'border-b border-dashed border-amber-700 text-amber-900';
  if (props.task?.status === 'completed') return 'border-b-2 border-green-700 text-green-900';
  if (props.task?.status === 'in_progress') return 'border-b-2 border-orange-600 text-orange-900';
  if (props.task?.assignment_id) return 'border-b border-blue-700 text-blue-900';
  if (props.task?.resolution_status === 'unresolved' && props.task?.actor) return 'border-b border-dotted border-gray-500 text-gray-700';
  return '';
};
const cellTitle = () => {
  if (props.column.type !== 'stage') return 'クリックして編集';
  if (!props.projectLinked) return '先に連携案件を設定してください';
  if (props.task?.planned) return '仮担当。クリックして変更または解除';
  if (props.task?.status === 'completed') return '完了済みの正式担当';
  if (props.task?.status === 'in_progress') return '作業中の正式担当';
  if (props.task?.assignment_id) return '正式登録済みの担当';
  if (props.task?.resolution_status === 'unresolved' && props.task?.actor) return '旧FileMaker担当値。クリックして仮担当へ対応付け';
  return 'クリックして仮担当を設定';
};

async function open() {
  if (!canEdit() || saving.value) return;
  errorMessage.value = '';
  actorScope.value = 'one';
  dateScope.value = 'one';
  editing.value = true;
  await nextTick();
  editor.value?.focus();
  if (props.column.type === 'shared') editor.value?.showPicker?.();
}
function message(error) {
  return error?.response?.data?.errors?.updated_at?.[0]
    ?? error?.response?.data?.message
    ?? 'セルを保存できませんでした。';
}
async function saveDate(event) {
  if (saving.value) return;
  saving.value = true;
  try {
    const subjectIds = props.column.type === 'shared'
      ? null
      : (dateScope.value === 'all' ? props.item.subjects.map((subject) => subject.id) : [props.subject.id]);
    const response = await axios.patch(route('coordinator.mginbon.items.date_cell.update', { item: props.item.id }), {
      updated_at: props.item.updated_at,
      subject_id: props.column.type === 'shared' ? null : undefined,
      subject_ids: subjectIds,
      code: props.column.code,
      date: event.target.value || null,
    });
    emit('saved', { kind: 'date', code: props.column.code, ...response.data,
      subjectIds: response.data.subjectIds ?? response.data.subject_ids ?? subjectIds });
    editing.value = false;
  } catch (error) {
    errorMessage.value = message(error);
    editing.value = false;
  } finally { saving.value = false; }
}
async function saveActor(event) {
  const target = event.target.value;
  if (!target || (actorScope.value === 'one' && target === props.task?.target) || saving.value) { editing.value = false; return; }
  saving.value = true;
  try {
    const subjectIds = actorScope.value === 'all'
      ? eligibleSubjects.value.map((subject) => subject.id)
      : [props.subject.id];
    const response = await axios.post(route('coordinator.mginbon.items.assign_stage', { item: props.item.id }), {
      stage_definition_id: props.task.stage_id,
      subject_ids: subjectIds,
      target,
      updated_at: props.item.updated_at,
    });
    emit('saved', { kind: 'actor', code: props.column.code, subjectIds, ...response.data });
    editing.value = false;
  } catch (error) {
    errorMessage.value = message(error);
    editing.value = false;
  } finally { saving.value = false; }
}
</script>

<template>
  <span ref="root" class="relative block h-full min-h-6 w-full">
    <span v-if="editing && column.type === 'stage'" class="absolute left-0 top-0 z-40 block w-56 border border-gray-500 bg-white p-1 text-left shadow-lg">
      <span v-if="eligibleSubjects.length > 1" class="mb-1 grid grid-cols-2 gap-1 text-[10px]">
        <label class="flex items-center gap-1 bg-gray-100 px-1 py-0.5"><input v-model="actorScope" type="radio" value="one" class="h-3 w-3" />この教科</label>
        <label class="flex items-center gap-1 bg-gray-100 px-1 py-0.5"><input v-model="actorScope" type="radio" value="all" class="h-3 w-3" />未登録の全教科</label>
      </span>
      <select ref="editor" :value="task?.target || ''" class="h-7 w-full border border-green-700 bg-white px-1 py-0 text-[11px] focus:ring-1 focus:ring-green-600" :disabled="saving" @change="saveActor" @keydown.esc="editing = false">
        <option value="">担当者を選択</option>
        <option v-if="task?.planned" value="clear">仮担当を解除</option>
        <optgroup v-if="actorOptions.users?.length" label="メンバー">
          <option v-for="user in actorOptions.users" :key="`u-${user.id}`" :value="`user:${user.id}`">{{ user.name }}{{ user.is_ghost ? '（テスト）' : '' }}</option>
        </optgroup>
        <optgroup v-if="actorOptions.subcontractors?.length" label="外注先">
          <option v-for="vendor in actorOptions.subcontractors" :key="`s-${vendor.id}`" :value="`subcontractor:${vendor.id}`">{{ vendor.name }}</option>
        </optgroup>
      </select>
      <button type="button" class="mt-1 w-full border border-gray-300 bg-gray-50 py-0.5 text-center text-[10px] hover:bg-gray-100" @click="editing = false">閉じる</button>
    </span>
    <span v-else-if="editing && column.type === 'date'" class="absolute left-0 top-0 z-40 block w-56 border border-gray-500 bg-white p-1 text-left shadow-lg">
      <span v-if="item.subjects.length > 1" class="mb-1 grid grid-cols-2 gap-1 text-[10px]">
        <label class="flex items-center gap-1 bg-gray-100 px-1 py-0.5"><input v-model="dateScope" type="radio" value="one" class="h-3 w-3" />この教科</label>
        <label class="flex items-center gap-1 bg-gray-100 px-1 py-0.5"><input v-model="dateScope" type="radio" value="all" class="h-3 w-3" />全教科</label>
      </span>
      <input ref="editor" type="date" :value="dateValue()" class="h-7 w-full border border-green-700 bg-white px-1 py-0 text-[11px] focus:ring-1 focus:ring-green-600" :disabled="saving" @change="saveDate" @keydown.esc="editing = false" />
      <button type="button" class="mt-1 w-full border border-gray-300 bg-gray-50 py-0.5 text-center text-[10px] hover:bg-gray-100" @click="editing = false">閉じる</button>
    </span>
    <input v-else-if="editing" ref="editor" type="date" :value="dateValue()" class="h-6 w-full border border-green-700 bg-white px-0 py-0 text-[10px] focus:ring-1 focus:ring-green-600" :disabled="saving" @change="saveDate" @blur="editing = false" @keydown.esc="editing = false" />
    <button v-else type="button" class="h-full min-h-6 w-full truncate px-0.5 text-center hover:outline hover:outline-1 hover:outline-green-700 disabled:cursor-not-allowed disabled:opacity-75" :class="actorStateClass()" :disabled="!canEdit()" :title="cellTitle()" @click="open">
      {{ saving ? '…' : display() }}
    </button>
    <button v-if="errorMessage" type="button" class="absolute left-0 top-full z-50 w-56 border border-red-600 bg-red-50 p-1 text-left text-[10px] leading-tight text-red-800 shadow-lg" title="クリックして閉じる" @click="errorMessage = ''">
      {{ errorMessage }}
    </button>
  </span>
</template>
