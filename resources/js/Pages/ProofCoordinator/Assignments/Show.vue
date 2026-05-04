<script setup>
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    proofRequest:        { type: Object, required: true },
    assignment:          { type: Object, default: null },
    proofreaderSchedule: { type: Object, default: null },
});

// ---- ステータス ----
const statusLabel = {
    pending:     '受理待ち',
    assigned:    '割り当て済み',
    in_progress: '校正中',
    completed:   '完了',
};
const statusBadge = {
    pending:     'bg-gray-100 text-gray-700',
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-indigo-100 text-indigo-800',
    completed:   'bg-yellow-100 text-yellow-800',
};

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

const isDeadlinePast = computed(() => {
    if (!props.proofRequest.deadline) return false;
    return new Date(props.proofRequest.deadline) < new Date();
});

// ---- アクション ----
function start() {
    router.put(route('proof_coordinator.assignments.start', { proofRequest: props.proofRequest.id }), {}, {
        preserveScroll: true,
    });
}

function complete() {
    if (!confirm('この校正を完了にしますか？依頼者に通知されます。')) return;
    router.put(route('proof_coordinator.assignments.complete', { proofRequest: props.proofRequest.id }), {}, {
        onSuccess: () => router.get(route('proof_coordinator.jobs')),
    });
}

function uncomplete() {
    if (!confirm('この校正を未完了（校正中）に戻しますか？')) return;
    router.put(route('proof_coordinator.assignments.uncomplete', { proofRequest: props.proofRequest.id }), {}, {
        preserveScroll: true,
    });
}

// ---- 予定（events）関連 ----
const events = ref([]);

onMounted(async () => {
    if (!props.assignment) return;
    const isScheduled = Boolean(props.assignment.scheduled || props.assignment.scheduled_at);
    const assigneeId = props.assignment.user?.id || props.assignment.user_id;
    if (!assigneeId || !isScheduled) return;

    try {
        const url =
            route('events.index') +
            '?user_id=' + encodeURIComponent(assigneeId) +
            '&job=' + encodeURIComponent(props.assignment.id);
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            const payload = await res.json();
            events.value = Array.isArray(payload)
                ? payload.filter((e) => String(e.project_job_assignment_id) === String(props.assignment.id))
                : [];
        }
    } catch (_) { /* ignore */ }
});

function formatDurationFromMinutes(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

const showScheduledSection = computed(() => {
    if (!props.assignment) return false;
    const label = props.assignment.status_label || '';
    const statusOk =
        String(label).includes('セット') ||
        String(label).includes('完了') ||
        String(label).toLowerCase() === 'completed' ||
        String(label).toLowerCase() === 'scheduled';
    return (props.assignment.scheduled || props.assignment.scheduled_at) && statusOk;
});

function jstDateStr(isoStr) {
    if (!isoStr) return '';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日`;
}

function jstTimeStr(isoStr) {
    if (!isoStr) return '';
    return new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        hour: '2-digit', minute: '2-digit', hour12: false,
    }).format(new Date(isoStr));
}

const formattedEvents = computed(() => events.value.map((e) => {
    const startsIso = e.starts_at || e.start;
    const endsIso   = e.ends_at   || e.end;
    const start = startsIso ? new Date(startsIso) : null;
    const end   = endsIso   ? new Date(endsIso)   : null;
    const dateStr   = startsIso ? jstDateStr(startsIso) : '';
    const startTime = startsIso ? jstTimeStr(startsIso) : '';
    const endTime   = endsIso   ? jstTimeStr(endsIso)   : '';
    const minutes           = start && end ? Math.max(0, Math.round((end - start) / 60000)) : 0;
    const interruptionMinutes = e.interruption_minutes ?? 0;
    const actualMinutes     = Math.max(0, minutes - interruptionMinutes);
    return { ...e, dateStr, startTime, endTime, minutes, interruptionMinutes, actualMinutes };
}));

const hasInterruptions    = computed(() => formattedEvents.value.some((e) => e.interruptionMinutes > 0));
const totalMinutes        = computed(() => formattedEvents.value.reduce((s, e) => s + e.minutes, 0));
const totalInterruption   = computed(() => formattedEvents.value.reduce((s, e) => s + e.interruptionMinutes, 0));
const totalActual         = computed(() => formattedEvents.value.reduce((s, e) => s + e.actualMinutes, 0));
</script>

<template>
    <AppLayout :title="`校正詳細 - ${proofRequest.title}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('proof_coordinator.jobs')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← ジョブ管理に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">校正依頼 — 詳細</h2>
            </div>
        </template>

        <template #headerExtras>
            <div class="flex items-center gap-2">
                <!-- 進行中のみ編集ボタンを表示 -->
                <Link
                    v-if="proofRequest.status !== 'completed'"
                    :href="route('proof_coordinator.assignments.edit', { proofRequest: proofRequest.id })"
                    class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700"
                >編集</Link>
                <!-- 進行中のみ完了ボタンを表示 -->
                <button
                    v-if="proofRequest.status !== 'completed'"
                    @click="complete"
                    class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                >完了にする</button>
                <!-- 完了済み：未完了に戻すボタン -->
                <template v-if="proofRequest.status === 'completed'">
                    <span class="rounded bg-yellow-100 px-3 py-1.5 text-sm font-medium text-yellow-800">完了済み</span>
                    <button
                        @click="uncomplete"
                        class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >未完了に戻す</button>
                </template>
            </div>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="jobs" />
        </template>

        <div class="mx-auto max-w-3xl space-y-4">

            <!-- 校正依頼情報カード -->
            <div class="overflow-hidden rounded-lg border border-pink-200 bg-white shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-pink-100 bg-pink-50 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-base font-bold text-gray-900">{{ proofRequest.title }}</h3>
                        <p v-if="proofRequest.project_job" class="mt-0.5 text-sm text-gray-500">
                            {{ proofRequest.project_job.title }}
                        </p>
                    </div>
                    <span :class="['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold', statusBadge[proofRequest.status]]">
                        {{ statusLabel[proofRequest.status] ?? proofRequest.status }}
                    </span>
                </div>

                <dl class="divide-y divide-gray-100">
                    <div class="grid grid-cols-2 gap-x-6 px-5 py-3">
                        <div>
                            <dt class="text-xs text-gray-500">依頼者</dt>
                            <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ proofRequest.requester?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">担当校正員</dt>
                            <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ proofRequest.proofreader?.name ?? '未割り当て' }}</dd>
                        </div>
                    </div>
                    <div class="px-5 py-3">
                        <dt class="text-xs text-gray-500">校正締め切り</dt>
                        <dd class="mt-0.5 text-sm font-medium" :class="isDeadlinePast && proofRequest.status !== 'completed' ? 'text-red-600 font-bold' : 'text-gray-900'">
                            {{ fmtDeadline(proofRequest.deadline) }}
                        </dd>
                    </div>
                    <!-- 校正員が設定した実作業時間 -->
                    <div v-if="proofreaderSchedule" class="px-5 py-3 bg-blue-50 border-t border-blue-100">
                        <dt class="text-xs text-blue-500 mb-1">作業時間（校正員設定 / {{ proofreaderSchedule.scheduled_at }}）</dt>
                        <dd v-if="proofreaderSchedule.work_slots.length > 0">
                            <div
                                v-for="(slot, i) in proofreaderSchedule.work_slots"
                                :key="i"
                                class="text-sm font-medium text-gray-900"
                            >
                                {{ slot.date }}　{{ slot.start }} 〜 {{ slot.end }}
                            </div>
                        </dd>
                        <dd v-else class="text-sm text-gray-400">作業時間未登録</dd>
                    </div>
                    <div v-if="proofRequest.note" class="px-5 py-3">
                        <dt class="text-xs text-gray-500">メモ</dt>
                        <dd class="mt-0.5 whitespace-pre-wrap text-sm text-gray-700">{{ proofRequest.note }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 割り当てジョブ詳細 -->
            <AssignmentDetailCard v-if="assignment" :assignment="assignment" />
            <div v-else class="rounded-lg border border-dashed border-gray-300 px-5 py-6 text-center text-sm text-gray-400">
                校正員への割り当てジョブがまだ作成されていません。
            </div>

            <!-- セットされた予定 -->
            <div v-if="showScheduledSection" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b bg-gray-50 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-700">セットされた予定</h3>
                </div>
                <div class="px-5 py-4">
                    <div v-if="formattedEvents.length === 0" class="text-sm text-gray-500">予定が見つかりません。</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-gray-500">
                                <th class="pb-2 text-left font-medium">作業日</th>
                                <th class="pb-2 text-left font-medium">開始</th>
                                <th class="pb-2 text-left font-medium">終了</th>
                                <th class="pb-2 text-left font-medium">作業時間</th>
                                <th v-if="hasInterruptions" class="pb-2 text-left font-medium text-orange-600">中断</th>
                                <th v-if="hasInterruptions" class="pb-2 text-left font-medium text-blue-700">実作業</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="ev in formattedEvents" :key="ev.id">
                                <td class="py-2 text-gray-900">{{ ev.dateStr }}</td>
                                <td class="py-2 text-gray-900">{{ ev.startTime }}</td>
                                <td class="py-2 text-gray-900">{{ ev.endTime }}</td>
                                <td class="py-2" :class="ev.interruptionMinutes > 0 ? 'text-gray-400 line-through' : 'text-gray-900'">
                                    {{ formatDurationFromMinutes(ev.minutes) }}
                                </td>
                                <td v-if="hasInterruptions" class="py-2 text-orange-600">
                                    {{ ev.interruptionMinutes > 0 ? '−' + formatDurationFromMinutes(ev.interruptionMinutes) : '—' }}
                                </td>
                                <td v-if="hasInterruptions" class="py-2 font-bold text-blue-700">
                                    {{ formatDurationFromMinutes(ev.actualMinutes) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="hasInterruptions">
                            <tr class="border-t bg-gray-50">
                                <td colspan="3" class="py-2 pr-2 text-right text-xs text-gray-500">合計実作業時間：</td>
                                <td class="py-2 text-xs text-gray-400 line-through">{{ formatDurationFromMinutes(totalMinutes) }}</td>
                                <td class="py-2 text-xs text-orange-600">−{{ formatDurationFromMinutes(totalInterruption) }}</td>
                                <td class="py-2 text-sm font-bold text-blue-700">{{ formatDurationFromMinutes(totalActual) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
