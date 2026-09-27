<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
  item: { type: Object, required: true },
  subjects: { type: Array, default: () => [] },
  stageCode: { type: String, default: null },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  includeProgress: { type: Boolean, default: false },
  editable: { type: Boolean, default: false },
  projectLinked: { type: Boolean, default: false },
});
const emit = defineEmits(['saved']);
const saving = ref(false);

const rows = computed(() => props.subjects.map((subject) => ({
  subject,
  task: subject.stages?.find((stage) => stage.code === props.stageCode),
})).filter(({ task }) => task));
const actors = computed(() => [...new Set(rows.value.map(({ task }) => task.actor).filter(Boolean))]);
const display = computed(() => props.includeProgress ? actors.value.join('／') : '');
const editableRows = computed(() => rows.value.filter(({ task }) => !task.assignment_id && task.status === 'not_started'));
const stageId = computed(() => editableRows.value[0]?.task.stage_id ?? null);
const commonTarget = computed(() => {
  const targets = [...new Set(editableRows.value.map(({ task }) => task.target).filter(Boolean))];
  return targets.length === 1 ? targets[0] : '';
});
const hasPlanned = computed(() => editableRows.value.some(({ task }) => task.planned));

function message(error) {
  return error?.response?.data?.errors?.updated_at?.[0]
    ?? error?.response?.data?.message
    ?? '担当者を保存できませんでした。';
}
function explainUnavailable() {
  if (!props.includeProgress) window.alert('担当者を入力するには「現在値を反映」を選択してください。');
  else if (!props.projectLinked) window.alert('先に銀本年度をSBWork案件へ接続してください。');
  else if (!editableRows.value.length) window.alert('正式登録済み、開始済み、または完了済みの担当者はここでは変更できません。');
}
async function save(event) {
  const target = event.target.value;
  if (!target || saving.value || target === commonTarget.value) return;
  saving.value = true;
  try {
    const response = await axios.post(route('coordinator.mginbon.items.assign_stage', { item: props.item.id }), {
      stage_definition_id: stageId.value,
      subject_ids: editableRows.value.map(({ subject }) => subject.id),
      target,
      updated_at: props.item.updated_at,
    });
    emit('saved', {
      code: props.stageCode,
      subjectIds: editableRows.value.map(({ subject }) => subject.id),
      ...response.data,
    });
  } catch (error) {
    window.alert(message(error));
    event.target.value = commonTarget.value;
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <select
    v-if="editable && includeProgress && projectLinked && editableRows.length"
    :value="commonTarget"
    :disabled="saving"
    class="h-full min-h-8 w-full border-0 bg-transparent px-2 py-0 text-base font-normal focus:ring-2 focus:ring-green-700"
    @change="save"
  >
    <option value="" disabled>{{ display || '担当者を選択' }}</option>
    <option v-if="hasPlanned" value="clear">仮担当を解除</option>
    <optgroup v-if="actorOptions.users?.length" label="チームメンバー">
      <option v-for="user in actorOptions.users" :key="`u-${user.id}`" :value="`user:${user.id}`">{{ user.name }}{{ user.is_ghost ? '（テスト）' : '' }}</option>
    </optgroup>
    <optgroup v-if="actorOptions.subcontractors?.length" label="外注先">
      <option v-for="vendor in actorOptions.subcontractors" :key="`s-${vendor.id}`" :value="`subcontractor:${vendor.id}`">{{ vendor.name }}</option>
    </optgroup>
  </select>
  <button v-else-if="editable" type="button" class="h-full min-h-8 w-full truncate px-2 text-left text-base font-normal hover:outline hover:outline-1 hover:outline-green-700" @click="explainUnavailable">{{ display }}</button>
  <span v-else class="block truncate px-2 text-base font-normal">{{ display }}</span>
</template>
