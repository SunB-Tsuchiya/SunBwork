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

  <!-- 校正担当者型（旧型：担当者選択のみ） -->
  <td v-else-if="colDef.type === 'proof_user'" class="border border-gray-200 px-2 py-1 align-middle min-w-[120px]">
    <template v-if="canEdit">
      <select
        :value="cell.value_user_id ? ('u_' + cell.value_user_id) : ''"
        class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
        @change="onProofUserSimpleChange($event.target.value)"
      >
        <option value="">— 校正担当者 —</option>
        <option v-for="u in users" :key="'u_' + u.id" :value="'u_' + u.id">{{ u.name }}</option>
      </select>
    </template>
    <template v-else>
      <span class="text-sm text-gray-700">{{ cell.value_user_name ?? '' }}</span>
    </template>
  </td>

  <!-- 校正担当者型（V2: 担当＋ジョブ統合セル） -->
  <td
    v-else-if="colDef.type === 'proof_v2'"
    class="border border-gray-200 px-0 py-0 align-middle min-w-[200px] transition-colors"
    :class="proofCellBg"
    :style="proofCellBorder"
  >
    <div class="flex min-h-[52px]">
      <!-- 左70%: 担当者 + 締切/完了 -->
      <div class="flex-1 px-2 py-1 flex flex-col justify-center gap-0.5" style="min-width:0">
        <!-- 完了済み -->
        <template v-if="proofCellCompleted">
          <div class="flex items-center gap-1">
            <span class="text-xs text-green-600">✓</span>
            <span class="text-sm font-medium text-gray-700 truncate">{{ proofAssigneeName }}</span>
          </div>
          <span class="text-xs text-green-600">
            完了: {{ cell.completed_at ? formatDate(cell.completed_at) : '済' }}
          </span>
        </template>
        <!-- 登録済み・未完了: ロック表示 -->
        <template v-else-if="cell.assignment_id || cell.proof_assignment_id">
          <div class="flex items-center gap-1">
            <span class="text-xs text-gray-400">🔒</span>
            <span class="text-sm font-medium text-gray-700 truncate">{{ proofAssigneeName }}</span>
          </div>
          <span v-if="proofDeadline" class="text-xs" :class="proofDeadlineColor">
            締切: {{ formatShortDate(proofDeadline) }}
          </span>
        </template>
        <!-- 校正依頼中（pending・未受理） -->
        <template v-else-if="cell.proof_request_pending">
          <div class="flex items-center gap-1">
            <span class="text-xs text-yellow-500">📋</span>
            <span class="text-sm font-medium text-yellow-700 truncate">校正管理へ依頼中</span>
          </div>
        </template>
        <!-- 担当者設定済み・ジョブ未登録 -->
        <template v-else-if="cell.value_user_id || cell.value_subcontractor_id">
          <div class="flex items-center gap-1">
            <span class="text-xs text-gray-400">🔒</span>
            <span class="text-sm font-medium text-gray-700 truncate">{{ proofAssigneeName }}</span>
          </div>
          <span v-if="proofDeadline" class="text-xs" :class="proofDeadlineColor">
            締切: {{ formatShortDate(proofDeadline) }}
          </span>
        </template>
        <!-- 未設定: セレクター -->
        <template v-else-if="canEdit">
          <select
            :value="cell.value_subcontractor_id ? ('s_' + cell.value_subcontractor_id) : (cell.value_user_id ? ('u_' + cell.value_user_id) : '')"
            class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
            @change="onProofUserChange($event.target.value)"
          >
            <option value="">— 校正担当者 —</option>
            <option value="proof_coordinator" class="font-medium text-pink-700">📋 校正管理へ依頼</option>
            <optgroup v-if="users.length" label="直接割当（管理外）">
              <option v-for="u in users" :key="'u_' + u.id" :value="'u_' + u.id">{{ u.name }}</option>
            </optgroup>
            <optgroup v-if="subcontractors.length" label="外注先">
              <option v-for="s in subcontractors" :key="'s_' + s.id" :value="'s_' + s.id">{{ s.name }}</option>
            </optgroup>
          </select>
        </template>
        <!-- 読み取り専用 -->
        <template v-else>
          <span class="text-sm text-gray-700 truncate">{{ proofAssigneeName }}</span>
        </template>
      </div>

      <!-- 右30%: ステータス・操作ボタン -->
      <div class="flex flex-col items-center justify-center gap-1 border-l border-gray-200 px-1.5 py-1" style="min-width:70px;max-width:80px">
        <!-- 完了済み -->
        <template v-if="proofCellCompleted">
          <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">✓ 完了</span>
          <button
            v-if="cell.assignment_id && canEdit"
            type="button"
            class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-200"
            @click="emit('worker-job-detail', { assignmentId: cell.assignment_id, rowId, colKey: colDef.key })"
          >詳細</button>
        </template>
        <!-- 校正管理経由で登録済み・未完了 -->
        <template v-else-if="cell.proof_assignment_id">
          <span class="rounded bg-pink-100 px-1.5 py-0.5 text-xs text-pink-700">校正管理済</span>
          <button
            v-if="canEdit"
            type="button"
            class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200"
            @click="emit('proof-direct-complete', { assignmentId: cell.proof_assignment_id })"
          >完了にする</button>
        </template>
        <!-- 直接登録済み・未完了 -->
        <template v-else-if="cell.assignment_id">
          <span class="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700">登録済</span>
          <button
            type="button"
            class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-200"
            @click="emit('worker-job-detail', { assignmentId: cell.assignment_id, rowId, colKey: colDef.key })"
          >詳細</button>
          <button
            v-if="canEdit || (authUserId && String(cell.value_user_id) === String(authUserId))"
            type="button"
            class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200"
            @click="emit('worker-complete', { cellId: cell.id, assignmentId: cell.assignment_id, rowId, colKey: colDef.key })"
          >完了にする</button>
        </template>
        <!-- 校正依頼中（pending・未受理） -->
        <template v-else-if="cell.proof_request_pending">
          <span class="rounded bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-700">📋 依頼中</span>
        </template>
        <!-- 担当者設定済み・未登録 -->
        <template v-else-if="cell.value_user_id || cell.value_subcontractor_id">
          <span class="text-xs text-gray-400">┄ 未登録 ┄</span>
          <button
            v-if="authUserId"
            type="button"
            class="rounded border border-dashed border-pink-300 bg-pink-50 px-2 py-0.5 text-xs text-pink-700 hover:bg-pink-100"
            @click="emit('worker-job-register', { rowId, colKey: colDef.key, userId: cell.value_user_id, subcontractorId: cell.value_subcontractor_id ?? null })"
          >＋ 登録</button>
        </template>
        <!-- 未設定 -->
        <template v-else>
          <button
            v-if="authUserId"
            type="button"
            class="rounded border border-dashed border-pink-300 bg-pink-50 px-2 py-0.5 text-xs text-pink-700 hover:bg-pink-100"
            @click="emit('worker-job-register', { rowId, colKey: colDef.key, userId: null, subcontractorId: null })"
          >＋ 登録</button>
          <span v-else class="text-xs text-gray-300">未設定</span>
        </template>
      </div>
    </div>
    <!-- メモ行 -->
    <div v-if="cell.cell_note || canEdit" class="border-t border-gray-100 px-2 py-0.5">
      <template v-if="!showNoteEdit">
        <div class="relative">
          <button
            type="button"
            class="flex w-full items-center gap-1 text-left text-xs"
            :class="cell.cell_note ? 'text-blue-500 hover:text-blue-700' : 'text-gray-300 hover:text-gray-400'"
            @mouseenter="cell.cell_note && (showNotePopup = true)"
            @mouseleave="showNotePopup = false"
            @click="canEdit && startNoteEdit()"
          >
            <span>📝</span>
            <span v-if="cell.cell_note" class="min-w-0 truncate"><span v-if="cell.cell_note_user_name" :class="roleColorClass(cell.cell_note_user_role)" class="font-semibold">{{ cell.cell_note_user_name }}：</span>{{ noteFirstLine }}</span>
            <span v-else class="text-xs text-gray-400">メモ</span>
          </button>
          <div
            v-if="showNotePopup && cell.cell_note"
            class="absolute bottom-full left-0 z-50 mb-1 w-64 rounded-lg border border-gray-200 bg-white p-2 shadow-lg"
            @mouseenter="showNotePopup = true"
            @mouseleave="showNotePopup = false"
          >
            <p class="whitespace-pre-wrap text-xs text-gray-700">{{ cell.cell_note }}</p>
            <div v-if="cell.cell_note_user_name" class="mt-1 flex items-center gap-1 border-t border-gray-100 pt-1">
              <span class="text-xs font-semibold" :class="roleColorClass(cell.cell_note_user_role)">{{ cell.cell_note_user_name }}</span>
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="flex items-center gap-1">
          <input
            v-model="editingNote"
            :ref="el => { if (el) el.focus() }"
            type="text"
            class="min-w-0 flex-1 rounded border border-gray-300 px-1 py-0.5 text-xs focus:border-indigo-400 focus:outline-none"
            placeholder="メモを入力..."
            @keydown.enter.prevent="saveNote"
            @keydown.escape.prevent="cancelNoteEdit"
          />
          <button type="button" class="shrink-0 rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200" @click="saveNote">保存</button>
          <button type="button" class="shrink-0 rounded bg-gray-100 px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-200" @click="cancelNoteEdit">✕</button>
        </div>
      </template>
    </div>
  </td>

  <!-- worker型（担当＋ジョブ統合セル） -->
  <td
    v-else-if="colDef.type === 'worker' || colDef.type === 'coordinator'"
    class="border border-gray-200 px-0 py-0 align-middle min-w-[200px] transition-colors"
    :class="workerCellBg"
    :style="workerCellBorder"
  >
    <div class="flex min-h-[52px]">
      <!-- 左70%: 担当者 + 締切/完了 -->
      <div class="flex-1 px-2 py-1 flex flex-col justify-center gap-0.5" style="min-width:0">
        <!-- 完了済み / 登録済み: 担当者ロック表示 -->
        <template v-if="cell.assignment_id || cell.completed_at || cell.assignment_completed">
          <div class="flex items-center gap-1">
            <span class="text-xs text-gray-400">🔒</span>
            <span class="text-sm font-medium text-gray-700 truncate">{{ workerAssigneeName }}</span>
          </div>
          <span v-if="cell.completed_at || cell.assignment_completed" class="text-xs text-green-600">
            完了: {{ cell.completed_at ? formatDate(cell.completed_at) : '済' }}
          </span>
          <span v-else-if="workerDeadline" class="text-xs" :class="workerDeadlineColor">
            締切: {{ formatShortDate(workerDeadline) }}
          </span>
          <span v-if="cell.work_minutes" class="text-xs text-gray-500">作業: {{ formatWorkMinutes(cell.work_minutes) }}</span>
        </template>
        <!-- 未登録: 担当者セレクター -->
        <template v-else-if="canEdit">
          <select
            :value="cell.value_subcontractor_id ? ('s_' + cell.value_subcontractor_id) : (cell.value_user_id ? ('u_' + cell.value_user_id) : '')"
            class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
            @change="onWorkerAssigneeChange($event.target.value)"
          >
            <option value="">— 担当者 —</option>
            <optgroup v-if="users.length" label="メンバー">
              <option v-for="u in users" :key="'u_' + u.id" :value="'u_' + u.id">{{ u.name }}</option>
            </optgroup>
            <optgroup v-if="subcontractors.length" label="外注先">
              <option v-for="s in subcontractors" :key="'s_' + s.id" :value="'s_' + s.id">{{ s.name }}</option>
            </optgroup>
          </select>
          <span v-if="workerDeadline" class="text-xs" :class="workerDeadlineColor">
            締切: {{ formatShortDate(workerDeadline) }}
          </span>
        </template>
        <!-- 読み取り専用 -->
        <template v-else>
          <span class="text-sm text-gray-700 truncate">{{ workerAssigneeName }}</span>
          <span v-if="workerDeadline" class="text-xs" :class="workerDeadlineColor">
            締切: {{ formatShortDate(workerDeadline) }}
          </span>
        </template>
      </div>

      <!-- 右30%: ステータス・操作ボタン -->
      <div class="flex flex-col items-center justify-center gap-1 border-l border-gray-200 px-1.5 py-1" style="min-width:70px;max-width:80px">
        <!-- 完了済み -->
        <template v-if="cell.completed_at || cell.assignment_completed">
          <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">✓ 完了</span>
          <button
            v-if="cell.assignment_id && canEdit"
            type="button"
            class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-200"
            @click="emit('worker-job-detail', { assignmentId: cell.assignment_id, rowId, colKey: colDef.key })"
          >詳細</button>
        </template>
        <!-- 登録済み・未完了 -->
        <template v-else-if="cell.assignment_id">
          <span class="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700">登録済</span>
          <button
            type="button"
            class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-200"
            @click="emit('worker-job-detail', { assignmentId: cell.assignment_id, rowId, colKey: colDef.key })"
          >詳細</button>
          <button
            v-if="canEdit || (authUserId && String(cell.value_user_id) === String(authUserId))"
            type="button"
            class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200"
            @click="emit('worker-complete', { cellId: cell.id, assignmentId: cell.assignment_id, rowId, colKey: colDef.key })"
          >完了にする</button>
        </template>
        <!-- 担当者選択済み・未登録 -->
        <template v-else-if="cell.value_user_id || cell.value_subcontractor_id">
          <span class="text-xs text-gray-400">┄ 未登録 ┄</span>
          <button
            v-if="authUserId"
            type="button"
            class="rounded border border-dashed border-indigo-300 bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 hover:bg-indigo-100"
            @click="emit('worker-job-register', { rowId, colKey: colDef.key, userId: cell.value_user_id, subcontractorId: cell.value_subcontractor_id })"
          >＋ 登録</button>
        </template>
        <!-- 未設定 -->
        <template v-else>
          <button
            v-if="authUserId"
            type="button"
            class="rounded border border-dashed border-indigo-300 bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 hover:bg-indigo-100"
            @click="emit('worker-job-register', { rowId, colKey: colDef.key, userId: null, subcontractorId: null })"
          >＋ 登録</button>
          <span v-else class="text-xs text-gray-300">未設定</span>
        </template>
      </div>
    </div>
    <!-- メモ行 -->
    <div v-if="cell.cell_note || canEdit" class="border-t border-gray-100 px-2 py-0.5">
      <template v-if="!showNoteEdit">
        <div class="relative">
          <button
            type="button"
            class="flex w-full items-center gap-1 text-left text-xs"
            :class="cell.cell_note ? 'text-blue-500 hover:text-blue-700' : 'text-gray-300 hover:text-gray-400'"
            @mouseenter="cell.cell_note && (showNotePopup = true)"
            @mouseleave="showNotePopup = false"
            @click="canEdit && startNoteEdit()"
          >
            <span>📝</span>
            <span v-if="cell.cell_note" class="min-w-0 truncate"><span v-if="cell.cell_note_user_name" :class="roleColorClass(cell.cell_note_user_role)" class="font-semibold">{{ cell.cell_note_user_name }}：</span>{{ noteFirstLine }}</span>
            <span v-else class="text-xs text-gray-400">メモ</span>
          </button>
          <!-- ホバーポップアップ -->
          <div
            v-if="showNotePopup && cell.cell_note"
            class="absolute bottom-full left-0 z-50 mb-1 w-64 rounded-lg border border-gray-200 bg-white p-2 shadow-lg"
            @mouseenter="showNotePopup = true"
            @mouseleave="showNotePopup = false"
          >
            <p class="whitespace-pre-wrap text-xs text-gray-700">{{ cell.cell_note }}</p>
            <div v-if="cell.cell_note_user_name" class="mt-1 flex items-center gap-1 border-t border-gray-100 pt-1">
              <span class="text-xs font-semibold" :class="roleColorClass(cell.cell_note_user_role)">{{ cell.cell_note_user_name }}</span>
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="flex items-center gap-1">
          <input
            v-model="editingNote"
            :ref="el => { if (el) el.focus() }"
            type="text"
            class="min-w-0 flex-1 rounded border border-gray-300 px-1 py-0.5 text-xs focus:border-indigo-400 focus:outline-none"
            placeholder="メモを入力..."
            @keydown.enter.prevent="saveNote"
            @keydown.escape.prevent="cancelNoteEdit"
          />
          <button type="button" class="shrink-0 rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200" @click="saveNote">保存</button>
          <button type="button" class="shrink-0 rounded bg-gray-100 px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-200" @click="cancelNoteEdit">✕</button>
        </div>
      </template>
    </div>
  </td>

  <!-- schedlink型（予定連携セル） -->
  <td
    v-else-if="colDef.type === 'schedlink'"
    class="border border-gray-200 px-0 py-0 align-middle min-w-[180px] transition-colors"
    :class="schedlinkCellBg"
    :style="schedlinkCellBorder"
  >
    <div class="flex min-h-[52px]">
      <!-- 左70%: スケジュール名 + 締切/完了 -->
      <div class="flex-1 px-2 py-1 flex flex-col justify-center gap-0.5" style="min-width:0">
        <!-- 完了済み -->
        <template v-if="cell.completed_at">
          <div class="flex items-center gap-1">
            <span class="text-xs text-green-600">✓</span>
            <span class="text-sm font-medium text-gray-700 truncate">{{ cell.schedule_name ?? '(未選択)' }}</span>
          </div>
          <span class="text-xs text-green-600">完了: {{ formatDate(cell.completed_at) }}</span>
        </template>
        <!-- 選択済み・未完了 -->
        <template v-else-if="cell.schedule_id">
          <span class="text-sm font-medium text-gray-700 truncate">{{ cell.schedule_name ?? String(cell.schedule_id) }}</span>
          <span v-if="schedlinkDeadline" class="text-xs" :class="schedlinkDeadlineColor">
            締切: {{ formatShortDate(schedlinkDeadline) }}
          </span>
        </template>
        <!-- 未選択 -->
        <template v-else-if="canEdit">
          <select
            :value="cell.schedule_id ?? ''"
            class="w-full rounded border border-gray-300 px-1 py-0.5 text-sm focus:border-indigo-400 focus:outline-none"
            @change="onSchedlinkSelect($event.target.value)"
          >
            <option value="">— スケジュール選択 —</option>
            <option v-for="s in projectSchedules" :key="s.id" :value="s.id">
              {{ s.name }}{{ s.end_date ? ` (〜${formatShortDate(s.end_date)})` : '' }}
            </option>
          </select>
        </template>
        <template v-else>
          <span class="text-sm text-gray-400">未選択</span>
        </template>
      </div>

      <!-- 右30%: 操作ボタン -->
      <div class="flex flex-col items-center justify-center gap-1 border-l border-gray-200 px-1.5 py-1" style="min-width:70px;max-width:80px">
        <!-- 完了済み -->
        <template v-if="cell.completed_at">
          <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">✓ 完了</span>
        </template>
        <!-- 選択済み・未完了 -->
        <template v-else-if="cell.schedule_id">
          <button
            v-if="canEdit"
            type="button"
            class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200"
            @click="emit('schedlink-complete', { cellId: cell.id, rowId, colKey: colDef.key })"
          >完了にする</button>
          <button
            v-if="canEdit"
            type="button"
            class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-200"
            @click="onSchedlinkSelect('')"
          >変更</button>
        </template>
        <!-- 未選択 -->
        <template v-else>
          <span class="text-xs text-gray-300">未設定</span>
        </template>
      </div>
    </div>
    <!-- メモ行 -->
    <div v-if="cell.cell_note || canEdit" class="border-t border-gray-100 px-2 py-0.5">
      <template v-if="!showNoteEdit">
        <div class="relative">
          <button
            type="button"
            class="flex w-full items-center gap-1 text-left text-xs"
            :class="cell.cell_note ? 'text-blue-500 hover:text-blue-700' : 'text-gray-300 hover:text-gray-400'"
            @mouseenter="cell.cell_note && (showNotePopup = true)"
            @mouseleave="showNotePopup = false"
            @click="canEdit && startNoteEdit()"
          >
            <span>📝</span>
            <span v-if="cell.cell_note" class="min-w-0 truncate"><span v-if="cell.cell_note_user_name" :class="roleColorClass(cell.cell_note_user_role)" class="font-semibold">{{ cell.cell_note_user_name }}：</span>{{ noteFirstLine }}</span>
            <span v-else class="text-xs text-gray-400">メモ</span>
          </button>
          <!-- ホバーポップアップ -->
          <div
            v-if="showNotePopup && cell.cell_note"
            class="absolute bottom-full left-0 z-50 mb-1 w-64 rounded-lg border border-gray-200 bg-white p-2 shadow-lg"
            @mouseenter="showNotePopup = true"
            @mouseleave="showNotePopup = false"
          >
            <p class="whitespace-pre-wrap text-xs text-gray-700">{{ cell.cell_note }}</p>
            <div v-if="cell.cell_note_user_name" class="mt-1 flex items-center gap-1 border-t border-gray-100 pt-1">
              <span class="text-xs font-semibold" :class="roleColorClass(cell.cell_note_user_role)">{{ cell.cell_note_user_name }}</span>
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="flex items-center gap-1">
          <input
            v-model="editingNote"
            :ref="el => { if (el) el.focus() }"
            type="text"
            class="min-w-0 flex-1 rounded border border-gray-300 px-1 py-0.5 text-xs focus:border-indigo-400 focus:outline-none"
            placeholder="メモを入力..."
            @keydown.enter.prevent="saveNote"
            @keydown.escape.prevent="cancelNoteEdit"
          />
          <button type="button" class="shrink-0 rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200" @click="saveNote">保存</button>
          <button type="button" class="shrink-0 rounded bg-gray-100 px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-200" @click="cancelNoteEdit">✕</button>
        </div>
      </template>
    </div>
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
    <!-- メモ行 -->
    <div v-if="cell.cell_note || canEdit" class="mt-1 border-t border-gray-100 pt-0.5">
      <template v-if="!showNoteEdit">
        <div class="relative">
          <button
            type="button"
            class="flex w-full items-center gap-1 text-left text-xs"
            :class="cell.cell_note ? 'text-blue-500 hover:text-blue-700' : 'text-gray-300 hover:text-gray-400'"
            @mouseenter="cell.cell_note && (showNotePopup = true)"
            @mouseleave="showNotePopup = false"
            @click="canEdit && startNoteEdit()"
          >
            <span>📝</span>
            <span v-if="cell.cell_note" class="min-w-0 truncate"><span v-if="cell.cell_note_user_name" :class="roleColorClass(cell.cell_note_user_role)" class="font-semibold">{{ cell.cell_note_user_name }}：</span>{{ noteFirstLine }}</span>
            <span v-else class="text-xs text-gray-400">メモ</span>
          </button>
          <!-- ホバーポップアップ -->
          <div
            v-if="showNotePopup && cell.cell_note"
            class="absolute bottom-full left-0 z-50 mb-1 w-64 rounded-lg border border-gray-200 bg-white p-2 shadow-lg"
            @mouseenter="showNotePopup = true"
            @mouseleave="showNotePopup = false"
          >
            <p class="whitespace-pre-wrap text-xs text-gray-700">{{ cell.cell_note }}</p>
            <div v-if="cell.cell_note_user_name" class="mt-1 flex items-center gap-1 border-t border-gray-100 pt-1">
              <span class="text-xs font-semibold" :class="roleColorClass(cell.cell_note_user_role)">{{ cell.cell_note_user_name }}</span>
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="flex items-center gap-1">
          <input
            v-model="editingNote"
            :ref="el => { if (el) el.focus() }"
            type="text"
            class="min-w-0 flex-1 rounded border border-gray-300 px-1 py-0.5 text-xs focus:border-indigo-400 focus:outline-none"
            placeholder="メモを入力..."
            @keydown.enter.prevent="saveNote"
            @keydown.escape.prevent="cancelNoteEdit"
          />
          <button type="button" class="shrink-0 rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700 hover:bg-indigo-200" @click="saveNote">保存</button>
          <button type="button" class="shrink-0 rounded bg-gray-100 px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-200" @click="cancelNoteEdit">✕</button>
        </div>
      </template>
    </div>
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
import { computed, ref } from 'vue';

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
  projectSchedules: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update', 'job-link-open', 'job-link-detail', 'complete-assignment', 'proof-request-open', 'proof-direct-complete', 'worker-complete', 'worker-job-register', 'worker-job-detail', 'schedlink-complete', 'note-save']);

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
  } else if (val.startsWith('s_')) {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'subcontractor', value: Number(val.slice(2)) });
  } else {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'user', value: null });
  }
}

function onProofUserSimpleChange(val) {
  if (val.startsWith('u_')) {
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

// ── worker型ヘルパー ──────────────────────────────────────
const workerAssigneeName = computed(() => {
  if (props.cell.value_subcontractor_id) {
    const sub = props.subcontractors.find((s) => String(s.id) === String(props.cell.value_subcontractor_id));
    return sub ? `[外注] ${sub.name}` : props.cell.value_subcontractor_name ?? '外注先';
  }
  if (props.cell.value_user_id) {
    const u = props.users.find((u) => String(u.id) === String(props.cell.value_user_id));
    return u?.name ?? props.cell.value_user_name ?? String(props.cell.value_user_id);
  }
  return props.cell.value_text ?? '';
});

const workerDeadline = computed(() => {
  // 優先順: cell_deadline > schedule.end_date > assignment.desired_end_date
  if (props.cell.cell_deadline) return props.cell.cell_deadline;
  if (props.cell.schedule_end_date) return props.cell.schedule_end_date;
  if (props.cell.assignment_end_date) return props.cell.assignment_end_date;
  return null;
});

const workerDeadlineColor = computed(() => {
  const d = workerDeadline.value;
  if (!d || props.cell.completed_at) return 'text-gray-500';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'text-red-600 font-medium';
  if (diff <= 3) return 'text-yellow-600 font-medium';
  return 'text-gray-500';
});

const workerCellBg = computed(() => {
  if (props.cell.completed_at) return 'bg-green-50';
  const d = workerDeadline.value;
  if (!d) return '';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'bg-red-50';
  if (diff <= 3) return 'bg-yellow-50';
  return '';
});

const workerCellBorder = computed(() => {
  if (props.cell.completed_at) return 'border-left: 3px solid #16a34a';
  const d = workerDeadline.value;
  if (!d) return '';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'border-left: 3px solid #dc2626';
  if (diff <= 3) return 'border-left: 3px solid #ca8a04';
  return '';
});

function formatDate(dt) {
  if (!dt) return '';
  return dt.slice(0, 10);
}

function formatWorkMinutes(mins) {
  if (!mins) return '';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? `${h}h${m > 0 ? m + 'm' : ''}` : `${m}m`;
}

function formatShortDate(d) {
  if (!d) return '';
  const parts = d.slice(0, 10).split('-');
  if (parts.length === 3) return `${parts[0].slice(2)}/${parts[1]}/${parts[2]}`;
  return d;
}

function onWorkerAssigneeChange(val) {
  if (!val) {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'worker', value: null });
  } else if (val.startsWith('s_')) {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'worker', value: null, subcontractor_id: Number(val.slice(2)) });
  } else {
    emit('update', { row_id: props.rowId, col_key: props.colDef.key, value_type: 'worker', value: Number(val.slice(2)) });
  }
}

// ── proof_user型ヘルパー ─────────────────────────────────
const proofCellCompleted = computed(() => {
  return !!(
    props.cell.completed_at ||
    props.cell.assignment_completed ||
    props.cell.assignment_proof_completed ||
    props.cell.proof_assignment_completed
  );
});

const proofAssigneeName = computed(() => {
  if (props.cell.proof_assignment_id && !props.cell.value_user_id && !props.cell.value_subcontractor_id) {
    return '📋 校正管理経由';
  }
  if (props.cell.value_subcontractor_id) {
    const sub = props.subcontractors.find((s) => String(s.id) === String(props.cell.value_subcontractor_id));
    return sub ? `[外注] ${sub.name}` : props.cell.value_subcontractor_name ?? '外注先';
  }
  if (props.cell.value_user_id) {
    const u = props.users.find((u) => String(u.id) === String(props.cell.value_user_id));
    return u?.name ?? props.cell.value_user_name ?? String(props.cell.value_user_id);
  }
  return '';
});

const proofDeadline = computed(() => {
  if (props.cell.cell_deadline) return props.cell.cell_deadline;
  if (props.cell.schedule_end_date) return props.cell.schedule_end_date;
  if (props.cell.assignment_end_date) return props.cell.assignment_end_date;
  return null;
});

const proofDeadlineColor = computed(() => {
  const d = proofDeadline.value;
  if (!d || proofCellCompleted.value) return 'text-gray-500';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'text-red-600 font-medium';
  if (diff <= 3) return 'text-yellow-600 font-medium';
  return 'text-gray-500';
});

const proofCellBg = computed(() => {
  if (proofCellCompleted.value) return 'bg-green-50';
  const d = proofDeadline.value;
  if (!d) return '';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'bg-red-50';
  if (diff <= 3) return 'bg-yellow-50';
  return '';
});

const proofCellBorder = computed(() => {
  if (proofCellCompleted.value) return 'border-left: 3px solid #16a34a';
  const d = proofDeadline.value;
  if (!d) return '';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'border-left: 3px solid #dc2626';
  if (diff <= 3) return 'border-left: 3px solid #ca8a04';
  return '';
});

// ── schedlink型ヘルパー ──────────────────────────────────
const schedlinkDeadline = computed(() => {
  if (props.cell.cell_deadline) return props.cell.cell_deadline;
  if (props.cell.schedule_end_date) return props.cell.schedule_end_date;
  return null;
});

const schedlinkDeadlineColor = computed(() => {
  const d = schedlinkDeadline.value;
  if (!d || props.cell.completed_at) return 'text-gray-500';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'text-red-600 font-medium';
  if (diff <= 3) return 'text-yellow-600 font-medium';
  return 'text-gray-500';
});

const schedlinkCellBg = computed(() => {
  if (props.cell.completed_at) return 'bg-green-50';
  const d = schedlinkDeadline.value;
  if (!d) return '';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'bg-red-50';
  if (diff <= 3) return 'bg-yellow-50';
  return '';
});

const schedlinkCellBorder = computed(() => {
  if (props.cell.completed_at) return 'border-left: 3px solid #16a34a';
  const d = schedlinkDeadline.value;
  if (!d) return '';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const deadline = new Date(d);
  const diff = (deadline - today) / (1000 * 60 * 60 * 24);
  if (diff < 0) return 'border-left: 3px solid #dc2626';
  if (diff <= 3) return 'border-left: 3px solid #ca8a04';
  return '';
});

function onSchedlinkSelect(val) {
  emit('update', {
    row_id: props.rowId,
    col_key: props.colDef.key,
    value_type: 'schedlink',
    value: val ? Number(val) : null,
  });
}

// ── メモ機能 ──────────────────────────────────────────
const showNoteEdit = ref(false);
const showNotePopup = ref(false);
const editingNote = ref('');

const noteFirstLine = computed(() => {
  if (!props.cell.cell_note) return '';
  return props.cell.cell_note.split('\n')[0];
});

// ── ロールカラー（ProjectWeekPlannerと同じ定義） ────────────────
const ROLE_COLOR = {
  superadmin: 'text-yellow-600',
  admin:      'text-red-600',
  leader:     'text-orange-600',
  coordinator:'text-green-600',
  clerk:      'text-purple-600',
  user:       'text-blue-600',
};
const ROLE_LABEL = {
  superadmin: 'SAdmin',
  admin:      'Admin',
  leader:     'Leader',
  coordinator:'Co',
  clerk:      'Clerk',
  user:       'User',
};
function roleColorClass(userRole) {
  return ROLE_COLOR[(userRole || '').toLowerCase()] || 'text-gray-700';
}
function roleLabel(userRole) {
  return ROLE_LABEL[(userRole || '').toLowerCase()] || '';
}

function startNoteEdit() {
  editingNote.value = props.cell.cell_note ?? '';
  showNoteEdit.value = true;
}

function cancelNoteEdit() {
  showNoteEdit.value = false;
}

function saveNote() {
  showNoteEdit.value = false;
  emit('note-save', { cellId: props.cell.id, rowId: props.rowId, colKey: props.colDef.key, note: editingNote.value || null });
}

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
