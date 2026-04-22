<script setup>
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    proofRequest: { type: Object, required: true },
    pja100:       { type: Object, default: null },
    assignment:   { type: Object, default: null },   // pja101
    projectJob:   { type: Object, default: null },
    events:       { type: Array,  default: () => [] },
});

// ---- 状態 ----
const isSubmitting = ref(false);

// ---- 締め切りフォーマット ----
function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日 ${p.hour}時${p.minute}分`;
}

// ---- イベント表示 ----
const formattedEvents = computed(() => {
    return props.events.map((e) => {
        const rawStart = e.start ?? e.starts_at ?? null;
        const rawEnd   = e.end   ?? e.ends_at   ?? null;
        // start/end はローカル文字列（JST）、starts_at/ends_at は UTC ISO
        // start が "YYYY-MM-DD HH:mm:ss" 形式ならそのまま使う
        const startDate = rawStart
            ? (rawStart.includes('T') ? new Date(rawStart) : new Date(rawStart.replace(' ', 'T') + '+09:00'))
            : null;
        const endDate = rawEnd
            ? (rawEnd.includes('T') ? new Date(rawEnd) : new Date(rawEnd.replace(' ', 'T') + '+09:00'))
            : null;
        const dateStr  = e.date || (startDate ? startDate.toLocaleDateString('ja-JP', { timeZone: 'Asia/Tokyo' }).replace(/\//g, '-') : '');
        const startTime = startDate
            ? startDate.toLocaleTimeString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' })
            : '';
        const endTime = endDate
            ? endDate.toLocaleTimeString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' })
            : '';
        const minutes = startDate && endDate ? Math.max(0, Math.round((endDate - startDate) / 60000)) : 0;
        return { ...e, dateStr, startTime, endTime, minutes };
    });
});

const totalMinutes = computed(() => formattedEvents.value.reduce((s, e) => s + e.minutes, 0));

function formatDuration(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

// ---- ボタン表示条件 ----
const isCompleted = computed(() => props.proofRequest.is_completed);
const hasEvents   = computed(() => formattedEvents.value.length > 0);
const isScheduled = computed(() => props.assignment && (props.assignment.scheduled || props.assignment.scheduled_at));

const setHref = computed(() => {
    try {
        return route('user.proof_jobs.set_page', { proofRequest: props.proofRequest.id });
    } catch (_) {
        return `/user/proof-jobs/${props.proofRequest.id}/set`;
    }
});

// ---- 完了処理 ----
function submitComplete() {
    if (!confirm('校正を完了としてマークします。\n依頼者に完了通知が送られます。よろしいですか？')) return;
    isSubmitting.value = true;
    router.post(
        route('user.proof_jobs.complete', { proofRequest: props.proofRequest.id }),
        {},
        {
            onFinish: () => { isSubmitting.value = false; },
        }
    );
}

// ---- ステータスバッジ ----
const statusLabel = {
    pending:     '受理待ち',
    assigned:    '割り当て済み',
    in_progress: '校正中',
    completed:   '完了',
};
const statusBadge = {
    pending:     'bg-gray-100 text-gray-700',
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-pink-100 text-pink-800',
    completed:   'bg-yellow-100 text-yellow-800',
};
</script>

<template>
    <AppLayout :title="`校正ジョブ - ${proofRequest.title}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('user.proof_jobs.index')" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 校正ジョブ一覧に戻る</Link>
                <h2 class="text-xl font-semibold text-gray-800">校正ジョブ — 詳細</h2>
            </div>
        </template>
        <template #tabs>
            <UserNavigationTabs active="proof_jobs" />
        </template>

        <div class="mx-auto max-w-3xl space-y-4">

            <!-- 校正依頼情報カード -->
            <div class="overflow-hidden rounded-lg border border-pink-200 bg-white shadow-sm">
                <div class="border-b border-pink-100 bg-pink-50 px-5 py-3 flex items-center gap-3">
                    <h3 class="text-sm font-semibold text-pink-800">校正依頼情報</h3>
                    <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', statusBadge[proofRequest.status] ?? 'bg-gray-100 text-gray-700']">
                        {{ statusLabel[proofRequest.status] ?? proofRequest.status }}
                    </span>
                </div>
                <dl class="divide-y divide-gray-100 px-5 py-3 text-sm">
                    <div class="flex gap-4 py-2">
                        <dt class="w-28 shrink-0 font-medium text-gray-500">タイトル</dt>
                        <dd class="text-gray-900">{{ proofRequest.title }}</dd>
                    </div>
                    <div class="flex gap-4 py-2">
                        <dt class="w-28 shrink-0 font-medium text-gray-500">案件</dt>
                        <dd class="text-gray-900">{{ proofRequest.job_title ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-4 py-2">
                        <dt class="w-28 shrink-0 font-medium text-gray-500">校正締め切り</dt>
                        <dd class="text-gray-900">{{ fmtDeadline(proofRequest.deadline) }}</dd>
                    </div>
                    <div class="flex gap-4 py-2">
                        <dt class="w-28 shrink-0 font-medium text-gray-500">依頼者</dt>
                        <dd class="text-gray-900">{{ proofRequest.requester_name ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-4 py-2">
                        <dt class="w-28 shrink-0 font-medium text-gray-500">校正管理者</dt>
                        <dd class="text-gray-900">{{ proofRequest.coordinator_name ?? '—' }}</dd>
                    </div>
                    <div v-if="proofRequest.note" class="flex gap-4 py-2">
                        <dt class="w-28 shrink-0 font-medium text-gray-500">備考</dt>
                        <dd class="text-gray-700 whitespace-pre-wrap">{{ proofRequest.note }}</dd>
                    </div>
                </dl>
            </div>

            <!-- pja101 詳細カード（セット済みの場合） -->
            <AssignmentDetailCard v-if="assignment" :assignment="assignment" />

            <!-- pja101 未存在の場合の案内 -->
            <div v-else class="rounded-lg border border-gray-200 bg-white px-5 py-6 text-center text-sm text-gray-400 shadow-sm">
                まだ作業予定がセットされていません。「予定をセット」から登録してください。
            </div>

            <!-- 作業時間テーブル -->
            <div v-if="hasEvents" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b bg-gray-50 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-700">セットされた予定</h3>
                </div>
                <div class="px-5 py-4">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-gray-500">
                                <th class="pb-2 text-left font-medium">作業日</th>
                                <th class="pb-2 text-left font-medium">開始</th>
                                <th class="pb-2 text-left font-medium">終了</th>
                                <th class="pb-2 text-left font-medium">時間</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="ev in formattedEvents" :key="ev.id">
                                <td class="py-2 text-gray-900">{{ ev.dateStr }}</td>
                                <td class="py-2 text-gray-900">{{ ev.startTime }}</td>
                                <td class="py-2 text-gray-900">{{ ev.endTime }}</td>
                                <td class="py-2 text-gray-600">{{ formatDuration(ev.minutes) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="formattedEvents.length > 1">
                            <tr class="border-t bg-gray-50">
                                <td colspan="3" class="py-2 pr-2 text-right text-xs text-gray-500">合計：</td>
                                <td class="py-2 text-sm font-bold text-gray-700">{{ formatDuration(totalMinutes) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- アクションボタン -->
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white px-5 py-3 shadow-sm">
                <template v-if="!isCompleted">
                    <!-- 予定をセット / 予定を編集 -->
                    <Link
                        :href="setHref"
                        class="inline-flex items-center rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                    >
                        {{ isScheduled ? '予定を編集' : '予定をセット' }}
                    </Link>

                    <!-- 完了にする（イベントが存在するときのみ） -->
                    <button
                        v-if="hasEvents"
                        @click="submitComplete"
                        :disabled="isSubmitting"
                        :class="isSubmitting
                            ? 'cursor-not-allowed rounded bg-yellow-800 px-3 py-1.5 text-sm font-medium text-white opacity-70'
                            : 'rounded bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700'"
                    >
                        {{ isSubmitting ? '送信中…' : '完了にする' }}
                    </button>
                </template>

                <span v-else class="rounded border border-yellow-300 bg-yellow-50 px-3 py-1.5 text-sm font-medium text-yellow-700">
                    完了済み
                </span>
            </div>

        </div>
    </AppLayout>
</template>
