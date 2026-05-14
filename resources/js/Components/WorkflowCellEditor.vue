<template>
    <div
        class="flex min-h-[56px] transition-colors"
        :class="cellBg"
        :style="cellBorderStyle"
    >
        <!-- 左: 担当者 + 情報 -->
        <div class="flex flex-1 flex-col justify-center gap-0.5 px-2 py-1.5" style="min-width: 0">

            <!-- 完了済み -->
            <template v-if="isCompleted">
                <div class="flex items-center gap-1">
                    <span class="text-xs text-green-600">✓</span>
                    <span class="truncate text-sm font-medium text-gray-700">{{ assigneeName }}</span>
                </div>
                <span class="text-xs text-green-600">完了: {{ formatDate(cell.completed_at) }}</span>
                <span v-if="cell.work_minutes" class="text-xs text-gray-500">作業: {{ formatMinutes(cell.work_minutes) }}</span>
            </template>

            <!-- 登録済み・未完了 -->
            <template v-else-if="isRegistered">
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-400">🔒</span>
                    <span class="truncate text-sm font-medium text-gray-700">{{ assigneeName }}</span>
                </div>
                <span v-if="cell?.work_minutes" class="text-xs text-gray-500">作業: {{ formatMinutes(cell.work_minutes) }}</span>
                <span v-if="cell?.cell_note" class="max-w-[160px] truncate text-xs italic text-gray-400" :title="cell.cell_note">{{ cell.cell_note }}</span>
            </template>

            <!-- joblink型: worker未登録 -->
            <template v-else-if="isJoblink && !linkedAssignmentId">
                <span class="text-xs text-gray-300">（worker未登録）</span>
            </template>

            <!-- joblink型: worker登録済み・joblink未登録 -->
            <template v-else-if="isJoblink && linkedAssignmentId">
                <span class="truncate text-sm text-gray-600">{{ linkedUserName }}</span>
                <span class="text-xs text-gray-400">未登録</span>
            </template>

            <!-- worker型: 担当者ローカル選択中・未登録 -->
            <template v-else-if="selectedUserId">
                <span class="truncate text-sm text-gray-600">{{ selectedUserName }}</span>
                <span class="text-xs text-gray-400">未登録</span>
            </template>

            <!-- worker型: 未選択 / 閲覧のみ -->
            <template v-else>
                <template v-if="canEdit && !isJoblink">
                    <select
                        class="w-full rounded border border-gray-200 px-1.5 py-1 text-xs focus:border-indigo-400 focus:outline-none"
                        value=""
                        @change="onSelectUser"
                    >
                        <option value="">— 担当者 —</option>
                        <option v-for="u in workerUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </template>
                <template v-else>
                    <span class="text-xs text-gray-300">—</span>
                </template>
            </template>
        </div>

        <!-- 右: ボタン群 -->
        <div
            class="flex flex-col items-center justify-center gap-1 border-l border-gray-100 px-1.5 py-1"
            style="min-width: 68px"
        >
            <!-- joblink型: worker登録済み・joblink未登録 → ＋登録 -->
            <template v-if="isJoblink && linkedAssignmentId && !isRegistered && !isCompleted && isCoordinator">
                <button
                    type="button"
                    class="whitespace-nowrap rounded border border-orange-300 px-2 py-0.5 text-xs font-medium text-orange-600 hover:bg-orange-50"
                    @click="$emit('register', { user_id: linkedUserId })"
                >＋ 登録</button>
            </template>

            <!-- worker型: 担当選択済み・未登録 → 登録 / 取消 -->
            <template v-else-if="selectedUserId && !isRegistered && !isCompleted && isCoordinator">
                <button
                    type="button"
                    class="whitespace-nowrap rounded border border-indigo-300 px-2 py-0.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                    @click="$emit('register', { user_id: selectedUserId })"
                >＋ 登録</button>
                <button
                    type="button"
                    class="text-xs text-gray-400 hover:text-gray-600"
                    @click="selectedUserId = null; selectedUserName = ''"
                >取消</button>
            </template>

            <!-- 登録済み・未完了 -->
            <template v-else-if="isRegistered && !isCompleted">
                <button
                    v-if="canEdit"
                    type="button"
                    class="whitespace-nowrap rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 hover:bg-green-200"
                    @click="$emit('complete', { cell_id: cell?.id })"
                >完了にする</button>
                <button
                    v-if="isCoordinator"
                    type="button"
                    class="whitespace-nowrap text-xs text-red-400 hover:text-red-600"
                    @click="$emit('unregister', { cell_id: cell?.id })"
                >解除</button>
            </template>

            <!-- 完了済み -->
            <template v-else-if="isCompleted">
                <span class="whitespace-nowrap text-xs font-medium text-green-600">✓ 完了</span>
                <button
                    v-if="canEdit"
                    type="button"
                    class="whitespace-nowrap text-xs text-gray-400 hover:text-gray-600"
                    @click="$emit('complete', { cell_id: cell?.id })"
                >取り消す</button>
            </template>

            <!-- 未選択 -->
            <template v-else>
                <span class="text-xs text-gray-300">┄ 未登録 ┄</span>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    cell:          { type: Object,  default: null },
    stage:         { type: Object,  required: true },
    workerUsers:   { type: Array,   default: () => [] },
    canEdit:       { type: Boolean, default: false },
    isCoordinator: { type: Boolean, default: false },
    linkedCell:    { type: Object,  default: null },
});

defineEmits(['register', 'complete', 'unregister']);

const selectedUserId   = ref(null);
const selectedUserName = ref('');

const isJoblink    = computed(() => props.stage.type === 'joblink');
const isRegistered = computed(() => !!(props.cell?.assignment_id));
const isCompleted  = computed(() => !!(props.cell?.completed_at));
const assigneeName = computed(() => props.cell?.assigned_user_name ?? '');

const linkedAssignmentId = computed(() => props.linkedCell?.assignment_id ?? null);
const linkedUserId       = computed(() => props.linkedCell?.assigned_user_id ?? null);
const linkedUserName     = computed(() => props.linkedCell?.assigned_user_name ?? '');

const cellBg = computed(() => {
    if (isCompleted.value) return 'bg-green-50';
    return '';
});
const cellBorderStyle = computed(() => {
    if (isCompleted.value) return 'border-left: 3px solid #4ade80;';
    return '';
});

function onSelectUser(e) {
    const id   = e.target.value ? Number(e.target.value) : null;
    const name = id ? (e.target.options[e.target.selectedIndex]?.text ?? '') : '';
    selectedUserId.value   = id;
    selectedUserName.value = name;
}

function formatDate(dt) {
    if (!dt) return '';
    return String(dt).substring(0, 10);
}

function formatMinutes(mins) {
    if (!mins) return '';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return h > 0 ? `${h}h${m > 0 ? m + 'm' : ''}` : `${m}m`;
}
</script>
