<script setup>
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
  item: { type: Object, required: true },
  code: { type: String, required: true },
  value: { type: String, default: '' },
  subjectIds: { type: Array, default: () => [] },
  includeProgress: { type: Boolean, default: false },
  editable: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});
const emit = defineEmits(['saved']);
const saving = ref(false);

const shortDate = (value) => value ? value.slice(2).replaceAll('-', '/') : '';
const message = (error) => error?.response?.data?.errors?.updated_at?.[0]
  ?? error?.response?.data?.message
  ?? '日付を保存できませんでした。';

function requestEditing() {
  if (!props.includeProgress) {
    window.alert('日付を入力するには「現在値を反映」を選択してください。');
  }
}

async function save(event) {
  if (saving.value || !props.includeProgress || !props.subjectIds.length) return;
  saving.value = true;
  try {
    const response = await axios.patch(route('coordinator.mginbon.items.date_cell.update', { item: props.item.id }), {
      updated_at: props.item.updated_at,
      subject_ids: props.subjectIds,
      code: props.code,
      date: event.target.value || null,
    });
    emit('saved', { code: props.code, ...response.data, subjectIds: response.data.subjectIds ?? props.subjectIds });
  } catch (error) {
    window.alert(message(error));
    event.target.value = props.value || '';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <input
    v-if="editable && includeProgress"
    type="date"
    :value="value"
    :disabled="saving || !subjectIds.length"
    class="h-full w-full border-0 bg-transparent px-1 py-0 text-center focus:ring-2 focus:ring-green-700 disabled:opacity-50"
    :class="compact ? 'min-h-4 text-sm leading-4' : 'min-h-7 text-base'"
    title="クリックして日付を編集"
    @change="save"
  />
  <button
    v-else-if="editable"
    type="button"
    class="h-full w-full px-1 text-center hover:outline hover:outline-1 hover:outline-green-700"
    :class="compact ? 'min-h-4 text-sm leading-4' : 'min-h-7 text-base'"
    title="日付を入力するには現在値を反映にしてください"
    @click="requestEditing"
  >{{ shortDate(value) }}</button>
  <span v-else class="flex h-full items-start justify-center" :class="compact ? 'min-h-4 text-sm leading-4' : 'min-h-7 text-lg leading-6'">{{ includeProgress ? shortDate(value) : '' }}</span>
</template>
