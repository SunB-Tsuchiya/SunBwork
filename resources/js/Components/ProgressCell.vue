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
    <!-- joblink によるロック（担当者固定表示） -->
    <template v-if="lockedSubcontractorId">
      <div class="flex items-center gap-1 rounded border border-purple-100 bg-purple-50 px-1 py-0.5">
        <span class="flex-1 text-sm text-purple-800">{{ lockedSubcontractorName }}</span>
        <span class="text-xs text-purple-400" title="外注先ジョブの担当者">🔒</span>
      </div>
    </template>
    <template v-else-if="lockedUserId">
      <div class="flex items-center gap-1 rounded border border-gray-200 bg-gray-50 px-1 py-0.5">
        <span class="flex-1 text-sm text-gray-700">{{ lockedUserName }}</span>
        <span class="text-xs text-gray-400" title="ジョブリンクの担当者">🔒</span>
      </div>
    </template>
    <template v-else-if="canEdit">
      <select
        :value="cell.value_subcontractor_id ? ('s_' + cell.value_subcontractor_id) : (cell.value_user_id ? ('u_' + cell.value_user_id) : '')"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="onUserCellChange($event.target.value)"
      >
        <option value="">—</option>
        <optgroup v-if="users.length" label="メンバー">
          <option v-for="u in users" :key="'u_' + u.id" :value="'u_' + u.id">{{ u.name }}</option>
        </optgroup>
        <optgroup v-if="subcontractors.length" label="外注先">
          <option v-for="s in subcontractors" :key="'s_' + s.id" :value="'s_' + s.id">{{ s.name }}</option>
        </optgroup>
      </select>
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ cell.value_subcontractor_name ?? cell.value_user_name ?? '' }}</span>
    </template>
  </td>

  <!-- 作業時間型 -->
  <td v-else-if="colDef.type === 'worktime'" class="border border-gray-200 px-2 py-1 align-middle min-w-[200px]">
    <template v-if="canEdit">
      <div class="flex items-center gap-1">
        <input
          type="time"
          :value="worktimeStart"
          class="rounded border border-gray-300 px-1 py-0.5 text-xs focus:border-indigo-400 focus:outline-none"
          @change="onWorktimeChange('start', $event.target.value)"
        />
        <span class="text-xs text-gray-400">〜</span>
        <input
          type="time"
          :value="worktimeEnd"
          class="rounded border border-gray-300 px-1 py-0.5 text-xs focus:border-indigo-400 focus:outline-none"
          @change="onWorktimeChange('end', $event.target.value)"
        />
      </div>
      <div v-if="worktimeDuration" class="mt-0.5 text-xs font-medium text-indigo-600">
        {{ worktimeDuration }}
      </div>
    </template>
    <template v-else>
      <div class="text-sm text-gray-700">
        <template v-if="worktimeStart || worktimeEnd">
          {{ worktimeStart || '?' }} 〜 {{ worktimeEnd || '?' }}
        </template>
        <div v-if="worktimeDuration" class="text-xs font-medium text-indigo-600">{{ worktimeDuration }}</div>
      </div>
    </template>
  </td>

  <!-- 校正担当者型 -->
  <td v-else-if="colDef.type === 'proof_user'" class="border border-gray-200 px-2 py-1 align-middle min-w-[130px]">
    <!-- 校正管理経由で依頼済みの場合（ロック表示） -->
    <template v-if="cell.proof_assignment_id">
      <span class="rounded bg-pink-100 px-1.5 py-0.5 text-xs font-medium text-pink-700">校正管理経由</span>
    </template>
    <!-- 編集可能（未依頼 or 直接割当） -->
    <template v-else-if="canEdit">
      <select
        :value="cell.value_user_id ? ('u_' + cell.value_user_id) : ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="onProofUserChange($event.target.value)"
      >
        <option value="">—</option>
        <option value="proof_coordinator" class="font-medium text-pink-700">📋 校正管理へ依頼</option>
        <optgroup v-if="users.length" label="直接割当（管理外）">
          <option v-for="u in users" :key="'u_' + u.id" :value="'u_' + u.id">{{ u.name }}</option>
        </optgroup>
      </select>
    </template>
    <!-- 読み取り専用 -->
    <template v-else>
      <span class="text-sm text-gray-700">{{ cell.value_user_name ?? '' }}</span>
    </template>
  </td>

  <!-- ジョブリンク型 -->
  <td
    v-else-if="colDef.type === 'joblink'"
    class="border border-gray-200 px-2 py-1 text-center align-middle min-w-[80px] transition-colors"
    :class="cell.assignment_completed ? 'bg-green-50' : ''"
  >
    <template v-if="canEdit || jobLinkOnly">
      <button
        v-if="!cell.assignment_id"
        type="button"
        class="h-7 w-full rounded border border-dashed border-gray-300 bg-gray-50 text-xs text-gray-400 hover:bg-gray-100 hover:text-gray-600"
        @click="emit('job-link-open', { rowId, colKey: colDef.key })"
      >＋ 登録</button>
      <div v-else class="flex flex-col items-center gap-0.5">
        <!-- 完了バッジ or 登録済バッジ -->
        <span
          v-if="cell.assignment_completed"
          class="rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800"
        >✓ 完了</span>
        <span v-else class="rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700">登録済</span>
        <!-- 詳細ボタン -->
        <button
          type="button"
          class="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-300"
          @click="emit('job-link-detail', { assignmentId: cell.assignment_id, assignmentTitle: cell.assignment_title, assigneeUserId: cell.assignment_user_id, assigneeSubcontractorId: cell.assignment_subcontractor_id, endDate: cell.assignment_end_date, completed: cell.assignment_completed, rowId, colKey: colDef.key })"
        >詳細</button>
        <!-- 担当者本人のみ: 完了にするボタン -->
        <button
          v-if="!cell.assignment_completed && authUserId && String(cell.assignment_user_id) === String(authUserId)"
          type="button"
          class="mt-0.5 rounded bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200"
          @click="emit('complete-assignment', { assignmentId: cell.assignment_id, rowId, colKey: colDef.key })"
        >完了にする</button>

      </div>
    </template>
    <template v-else>
      <!-- 閲覧のみ -->
      <div v-if="cell.assignment_id" class="flex flex-col items-center gap-0.5">
        <span
          v-if="cell.assignment_completed"
          class="rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800"
        >✓ 完了</span>
        <span v-else class="rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700">登録済</span>
      </div>
      <div v-else class="mx-auto h-6 w-full rounded border border-dashed border-gray-200 bg-gray-50"></div>
    </template>
  </td>

  <!-- ステージ型 -->
  <td v-else-if="colDef.type === 'stage'" class="border border-gray-200 px-2 py-1 align-middle min-w-[120px]">
    <template v-if="canEdit">
      <select
        :value="cell.value_text || ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'text', value: $event.target.value || null })"
      >
        <option value="">—</option>
        <option v-for="s in stages" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
      </select>
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ stageLabel }}</span>
    </template>
  </td>

  <!-- サイズ型 -->
  <td v-else-if="colDef.type === 'size'" class="border border-gray-200 px-2 py-1 align-middle min-w-[140px]">
    <template v-if="canEdit">
      <select
        :value="cell.value_text || ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'text', value: $event.target.value || null })"
      >
        <option value="">—</option>
        <template v-for="grp in sizesGrouped" :key="grp.group">
          <optgroup :label="grp.label">
            <option v-for="s in grp.items" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
          </optgroup>
        </template>
      </select>
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ sizeLabel }}</span>
    </template>
  </td>

  <!-- 作業分担型 -->
  <td v-else-if="colDef.type === 'assignment'" class="border border-gray-200 px-2 py-1 align-middle min-w-[120px]">
    <template v-if="canEdit">
      <select
        :value="cell.value_text || ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'text', value: $event.target.value || null })"
      >
        <option value="">—</option>
        <option v-for="a in assignments" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
      </select>
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ assignmentLabel }}</span>
    </template>
  </td>

  <!-- 作業種別型 -->
  <td v-else-if="colDef.type === 'workItemType'" class="border border-gray-200 px-2 py-1 align-middle min-w-[140px]">
    <template v-if="canEdit">
      <select
        :value="cell.value_text || ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="emit('update', { row_id: rowId, col_key: colDef.key, value_type: 'text', value: $event.target.value || null })"
      >
        <option value="">—</option>
        <template v-for="grp in workItemTypesGrouped" :key="grp.group">
          <optgroup :label="grp.label">
            <option v-for="t in grp.items" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
          </optgroup>
        </template>
      </select>
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ workItemTypeLabel }}</span>
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
import { computed } from 'vue';

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
  jobLinkOnly: {
    type: Boolean,
    default: false,
  },
  authUserId: {
    type: [Number, String, null],
    default: null,
  },
  lockedUserId: {
    type: [Number, String, null],
    default: null,
  },
  lockedSubcontractorId: {
    type: [Number, String, null],
    default: null,
  },
  users: {
    type: Array,
    default: () => [],
  },
  subcontractors: {
    type: Array,
    default: () => [],
  },
  stages: {
    type: Array,
    default: () => [],
  },
  sizes: {
    type: Array,
    default: () => [],
  },
  assignments: {
    type: Array,
    default: () => [],
  },
  workItemTypes: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update', 'job-link-open', 'job-link-detail', 'complete-assignment', 'proof-request-open', 'proof-direct-complete']);

// ── 作業時間ヘルパー ──────────────────────────────
// value_text に "HH:MM|HH:MM" 形式で開始・終了を保存

const worktimeStart = computed(() => {
  const raw = props.cell.value_text ?? '';
  return raw.includes('|') ? raw.split('|')[0] : raw;
});

const worktimeEnd = computed(() => {
  const raw = props.cell.value_text ?? '';
  return raw.includes('|') ? raw.split('|')[1] : '';
});

const worktimeDuration = computed(() => {
  const s = worktimeStart.value;
  const e = worktimeEnd.value;
  if (!s || !e) return '';
  const [sh, sm] = s.split(':').map(Number);
  const [eh, em] = e.split(':').map(Number);
  const totalMin = (eh * 60 + em) - (sh * 60 + sm);
  if (totalMin <= 0) return '';
  const h = Math.floor(totalMin / 60);
  const m = totalMin % 60;
  if (h === 0) return `${m}分`;
  if (m === 0) return `${h}時間`;
  return `${h}時間${m}分`;
});

function onProofUserChange(val) {
  if (val === 'proof_coordinator') {
    emit('proof-request-open', { rowId: props.rowId, colKey: props.colDef.key });
  } else if (val.startsWith('u_')) {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'user', value: Number(val.slice(2)) });
  } else {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'user', value: null });
  }
}

function onWorktimeChange(which, val) {
  const s = which === 'start' ? val : worktimeStart.value;
  const e = which === 'end'   ? val : worktimeEnd.value;
  const combined = (s || e) ? `${s}|${e}` : null;
  emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'text', value: combined });
}

// ── ステージ / サイズ / 作業分担 / 作業種別 ──────────────────────────
const GROUP_LABELS = { paper: '紙媒体', digital: 'デジタル', web: 'Web', other: 'その他', dtp: 'DTP・組版', proof: '校正', design: 'デザイン', common: '共通' };

const lockedUserName = computed(() => {
  if (!props.lockedUserId) return null;
  return props.users.find((u) => String(u.id) === String(props.lockedUserId))?.name ?? String(props.lockedUserId);
});

const lockedSubcontractorName = computed(() => {
  if (!props.lockedSubcontractorId) return null;
  return props.subcontractors.find((s) => String(s.id) === String(props.lockedSubcontractorId))?.name ?? String(props.lockedSubcontractorId);
});

function onUserCellChange(val) {
  if (!val) {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'user', value: null });
    return;
  }
  if (val.startsWith('s_')) {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'subcontractor', value: Number(val.slice(2)) });
  } else {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'user', value: Number(val.slice(2)) });
  }
}

const stageLabel = computed(() => {
  const id = props.cell?.value_text;
  if (!id) return '';
  return props.stages.find((s) => String(s.id) === String(id))?.name ?? id;
});

const sizeLabel = computed(() => {
  const id = props.cell?.value_text;
  if (!id) return '';
  return props.sizes.find((s) => String(s.id) === String(id))?.name ?? id;
});

const assignmentLabel = computed(() => {
  const id = props.cell?.value_text;
  if (!id) return '';
  return props.assignments.find((a) => String(a.id) === String(id))?.name ?? id;
});

const sizesGrouped = computed(() => {
  const map = new Map();
  for (const s of props.sizes) {
    const g = s.group || 'other';
    if (!map.has(g)) map.set(g, []);
    map.get(g).push(s);
  }
  return [...map.entries()].map(([group, items]) => ({
    group,
    label: GROUP_LABELS[group] ?? group,
    items,
  }));
});

const workItemTypeLabel = computed(() => {
  const id = props.cell?.value_text;
  if (!id) return '';
  return props.workItemTypes.find((t) => String(t.id) === String(id))?.name ?? id;
});

const workItemTypesGrouped = computed(() => {
  const map = new Map();
  for (const t of props.workItemTypes) {
    const g = t.group || 'common';
    if (!map.has(g)) map.set(g, []);
    map.get(g).push(t);
  }
  return [...map.entries()].map(([group, items]) => ({
    group,
    label: GROUP_LABELS[group] ?? group,
    items,
  }));
});
</script>
