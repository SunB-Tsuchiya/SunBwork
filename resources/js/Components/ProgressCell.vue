<template>
  <!-- チェックボックス型 -->
  <td v-if="colDef.type === 'checkbox'" class="border border-gray-200 px-2 py-1 text-center align-middle">
    <template v-if="canEdit">
      <input
        type="checkbox"
        :checked="!!cell.value_bool"
        class="h-4 w-4 cursor-pointer rounded border-gray-300 text-indigo-600"
        @change="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'bool', value: $event.target.checked })"
      />
    </template>
    <template v-else>
      <span v-if="cell.value_bool" class="text-green-600">✓</span>
    </template>
  </td>

  <!-- 日付型 -->
  <td v-else-if="colDef.type === 'date'" class="border border-gray-200 px-2 py-1 align-middle min-w-[110px]">
    <template v-if="canEdit">
      <input
        type="date"
        :value="cell.value_date || ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'date', value: $event.target.value || null })"
      />
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ cell.value_date ?? '' }}</span>
    </template>
  </td>

  <!-- ユーザー型 -->
  <td v-else-if="colDef.type === 'user'" class="border border-gray-200 px-2 py-1 align-middle min-w-[120px]">
    <template v-if="canEdit">
      <select
        :value="cell.value_user_id || ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'user', value: $event.target.value ? Number($event.target.value) : null })"
      >
        <option value="">—</option>
        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ cell.value_user_name ?? '' }}</span>
    </template>
  </td>

  <!-- テキスト型 -->
  <td v-else class="border border-gray-200 px-2 py-1 align-middle min-w-[120px]">
    <template v-if="canEdit">
      <input
        type="text"
        :value="cell.value_text || ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @blur="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'text', value: $event.target.value || null })"
      />
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ cell.value_text ?? '' }}</span>
    </template>
  </td>
</template>

<script setup>
const props = defineProps({
  cell: {
    type: Object,
    default: () => ({}),
  },
  colDef: {
    type: Object,
    required: true,
  },
  rowId: {
    type: Number,
    required: true,
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
  users: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update']);
</script>
