<script setup>
/**
 * AssignmentDetailCard.vue
 * ジョブ割り当て（ProjectJobAssignment）の詳細をリッチに表示する再利用コンポーネント。
 * Events/Show, MyJobBox/Show などから利用される。
 *
 * Props:
 *   assignment - ProjectJobAssignment オブジェクト（関連モデルをロード済み）
 *   showHeader - タイトルヘッダーを表示するか（default: true）
 */
import { computed } from 'vue';

const props = defineProps({
    assignment: { type: Object, required: true },
    showHeader: { type: Boolean, default: true },
});

// ステータスバッジのスタイル
const statusBadgeClass = computed(() => {
    const a = props.assignment;
    if (a.completed) return 'bg-yellow-100 text-yellow-800';
    const key = a.status_model?.key ?? a.statusModel?.key ?? '';
    const name = a.status_model?.name ?? a.statusModel?.name ?? a.status_label ?? '';
    if (key === 'scheduled' || String(name).includes('セット')) return 'bg-blue-100 text-blue-800';
    if (key === 'confirmed' || String(name).includes('確認')) return 'bg-green-100 text-green-800';
    if (key === 'in_progress' || String(name).includes('進行') || String(name).includes('受信')) return 'bg-indigo-100 text-indigo-800';
    return 'bg-gray-100 text-gray-700';
});

const statusLabel = computed(() => {
    const a = props.assignment;
    if (a.completed) return '完了';
    return a.status_model?.name ?? a.statusModel?.name ?? a.status_label ?? '未設定';
});

const clientName = computed(() => {
    const a = props.assignment;
    return a.project_job?.client?.name ?? a.client?.name ?? a.client_name ?? '—';
});

const projectJobTitle = computed(() => {
    const a = props.assignment;
    return a.project_job?.title ?? a.project_job?.name ?? a.project_job_name ?? '—';
});

const workItemTypeName = computed(() => {
    const a = props.assignment;
    return a.work_item_type?.name ?? a.workItemType?.name ?? a.type_label ?? '—';
});

const sizeName = computed(() => {
    const a = props.assignment;
    return a.size?.name ?? a.size?.label ?? a.size_label ?? '—';
});

const stageName = computed(() => {
    const a = props.assignment;
    return a.stage?.name ?? a.stage_label ?? '—';
});

const difficultyName = computed(() => {
    const a = props.assignment;
    return a.difficulty_model?.name ?? a.difficultyModel?.name ?? a.difficulty_label ?? '—';
});

const assigneeName = computed(() => {
    const a = props.assignment;
    return a.user?.name ?? '—';
});

const senderName = computed(() => {
    const a = props.assignment;
    if (!a.sender) return null;
    if (a.sender_id === a.user_id) return null; // 自己割当なので表示不要
    return a.sender?.name ?? null;
});

const estimatedHours = computed(() => {
    const h = props.assignment.estimated_hours;
    if (h === null || h === undefined || h === '') return '—';
    const n = Number(h);
    if (Number.isNaN(n)) return '—';
    const hours = Math.floor(n);
    const mins = Math.round((n - hours) * 60);
    if (mins > 0) return `${hours}時間${mins}分`;
    return `${hours}時間`;
});

const desiredEndDate = computed(() => props.assignment.desired_end_date ?? '—');

const desiredTime = computed(() => {
    const t = props.assignment.desired_time;
    if (!t) return '—';
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`;
    return t;
});

const amounts = computed(() => {
    const a = props.assignment;
    if (!a.amounts) return null;
    const unit = a.amounts_unit ?? 'ページ';
    return `${a.amounts} ${unit}`;
});

const completedBadge = computed(() => props.assignment.completed);
const scheduledBadge = computed(() => {
    if (props.assignment.completed) return false;
    return props.assignment.scheduled || props.assignment.scheduled_at;
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <!-- ヘッダー -->
        <div v-if="showHeader" class="flex items-start justify-between gap-3 border-b bg-gray-50 px-5 py-4">
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-base font-bold text-gray-900">{{ assignment.title || '—' }}</h3>
                <p v-if="clientName !== '—'" class="mt-0.5 truncate text-sm text-gray-500">{{ clientName }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', statusBadgeClass]">
                    {{ statusLabel }}
                </span>
                <span v-if="completedBadge" class="inline-flex items-center gap-1 text-xs text-yellow-700">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    完了済み
                </span>
            </div>
        </div>

        <div class="divide-y divide-gray-100">
            <!-- 案件情報セクション -->
            <div class="px-5 py-4">
                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">案件情報</h4>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                    <div class="flex items-start gap-2">
                        <dt class="w-20 shrink-0 text-sm text-gray-500">クライアント</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ clientName }}</dd>
                    </div>
                    <div class="flex items-start gap-2">
                        <dt class="w-20 shrink-0 text-sm text-gray-500">案件名</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ projectJobTitle }}</dd>
                    </div>
                    <div v-if="senderName" class="flex items-start gap-2">
                        <dt class="w-20 shrink-0 text-sm text-gray-500">割当者</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ senderName }}</dd>
                    </div>
                    <div class="flex items-start gap-2">
                        <dt class="w-20 shrink-0 text-sm text-gray-500">担当者</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ assigneeName }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 作業詳細セクション -->
            <div class="px-5 py-4">
                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">作業詳細</h4>
                <div class="flex flex-wrap gap-2">
                    <!-- 種別 -->
                    <div v-if="workItemTypeName !== '—'" class="flex items-center gap-1.5 rounded-md bg-blue-50 px-3 py-1.5">
                        <span class="text-xs text-blue-500">種別</span>
                        <span class="text-sm font-semibold text-blue-800">{{ workItemTypeName }}</span>
                    </div>
                    <!-- サイズ -->
                    <div v-if="sizeName !== '—'" class="flex items-center gap-1.5 rounded-md bg-purple-50 px-3 py-1.5">
                        <span class="text-xs text-purple-500">サイズ</span>
                        <span class="text-sm font-semibold text-purple-800">{{ sizeName }}</span>
                    </div>
                    <!-- ステージ -->
                    <div v-if="stageName !== '—'" class="flex items-center gap-1.5 rounded-md bg-indigo-50 px-3 py-1.5">
                        <span class="text-xs text-indigo-500">ステージ</span>
                        <span class="text-sm font-semibold text-indigo-800">{{ stageName }}</span>
                    </div>
                    <!-- 難易度 -->
                    <div v-if="difficultyName !== '—'" class="flex items-center gap-1.5 rounded-md bg-orange-50 px-3 py-1.5">
                        <span class="text-xs text-orange-500">難易度</span>
                        <span class="text-sm font-semibold text-orange-800">{{ difficultyName }}</span>
                    </div>
                    <!-- 量 -->
                    <div v-if="amounts" class="flex items-center gap-1.5 rounded-md bg-teal-50 px-3 py-1.5">
                        <span class="text-xs text-teal-500">量</span>
                        <span class="text-sm font-semibold text-teal-800">{{ amounts }}</span>
                    </div>
                </div>
                <!-- 何も情報がない場合 -->
                <p v-if="workItemTypeName === '—' && sizeName === '—' && stageName === '—' && difficultyName === '—' && !amounts"
                   class="text-sm text-gray-400">作業詳細情報なし</p>
            </div>

            <!-- スケジュールセクション -->
            <div class="px-5 py-4">
                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">スケジュール</h4>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-gray-500">見積時間</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ estimatedHours }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">希望終了日</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ desiredEndDate }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">希望時間</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ desiredTime }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 詳細テキストセクション -->
            <div v-if="assignment.detail" class="px-5 py-4">
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">概要・詳細</h4>
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{{ assignment.detail }}</p>
            </div>
        </div>
    </div>
</template>
