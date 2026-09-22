<script setup>
import axios from 'axios';
import { nextTick, ref } from 'vue';

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
const shortDate = (value) => value ? value.slice(5).replace('-', '/') : '';
const dateValue = () => props.column.type === 'shared'
  ? (props.item.shared_dates?.[props.column.code] || '')
  : (props.subject.dates?.[props.column.code] || '');
const display = () => props.column.type === 'stage' ? (props.task?.actor || '') : shortDate(dateValue());
const canEdit = () => props.column.type !== 'stage' || (props.projectLinked && props.task?.stage_id);

async function open() {
  if (!canEdit() || saving.value) return;
  editing.value = true;
  await nextTick();
  editor.value?.focus();
  if (props.column.type !== 'stage') editor.value?.showPicker?.();
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
    const response = await axios.patch(route('coordinator.mginbon.items.date_cell.update', { item: props.item.id }), {
      updated_at: props.item.updated_at,
      subject_id: props.column.type === 'shared' ? null : props.subject.id,
      code: props.column.code,
      date: event.target.value || null,
    });
    emit('saved', { kind: 'date', code: props.column.code, subjectId: props.column.type === 'shared' ? null : props.subject.id, ...response.data });
    editing.value = false;
  } catch (error) {
    window.alert(message(error));
  } finally { saving.value = false; }
}
async function saveActor(event) {
  const target = event.target.value;
  if (!target || target === props.task?.target || saving.value) { editing.value = false; return; }
  saving.value = true;
  try {
    const response = await axios.post(route('coordinator.mginbon.items.assign_stage', { item: props.item.id }), {
      stage_definition_id: props.task.stage_id,
      subject_ids: [props.subject.id],
      target,
      updated_at: props.item.updated_at,
    });
    emit('saved', { kind: 'actor', code: props.column.code, subjectId: props.subject.id, ...response.data });
    editing.value = false;
  } catch (error) {
    window.alert(message(error));
  } finally { saving.value = false; }
}
</script>

<template>
  <span class="block h-full min-h-6 w-full">
    <select v-if="editing && column.type === 'stage'" ref="editor" :value="task?.target || ''" class="h-6 w-full border border-green-700 bg-white px-0 py-0 text-[11px] focus:ring-1 focus:ring-green-600" :disabled="saving" @change="saveActor" @blur="editing = false" @keydown.esc="editing = false">
      <option value="">担当者を選択</option>
      <optgroup v-if="actorOptions.users?.length" label="メンバー">
        <option v-for="user in actorOptions.users" :key="`u-${user.id}`" :value="`user:${user.id}`">{{ user.name }}{{ user.is_ghost ? '（テスト）' : '' }}</option>
      </optgroup>
      <optgroup v-if="actorOptions.subcontractors?.length" label="外注先">
        <option v-for="vendor in actorOptions.subcontractors" :key="`s-${vendor.id}`" :value="`subcontractor:${vendor.id}`">{{ vendor.name }}</option>
      </optgroup>
    </select>
    <input v-else-if="editing" ref="editor" type="date" :value="dateValue()" class="h-6 w-full border border-green-700 bg-white px-0 py-0 text-[10px] focus:ring-1 focus:ring-green-600" :disabled="saving" @change="saveDate" @blur="editing = false" @keydown.esc="editing = false" />
    <button v-else type="button" class="h-full min-h-6 w-full truncate px-0.5 text-center hover:outline hover:outline-1 hover:outline-green-700 disabled:cursor-not-allowed disabled:opacity-60" :class="task?.planned ? 'border-b border-dashed border-amber-700 text-amber-900' : ''" :disabled="!canEdit()" :title="canEdit() ? (task?.planned ? '仮担当。クリックして変更' : 'クリックして編集') : '先に連携案件を設定してください'" @click="open">
      {{ saving ? '…' : display() }}
    </button>
  </span>
</template>
