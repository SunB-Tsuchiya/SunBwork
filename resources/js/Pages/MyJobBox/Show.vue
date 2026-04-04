<template>
    <AppLayout :title="`ジョブ割り当て - ${assignment?.title || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て — 詳細</h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-4">

            <!-- ジョブ割り当て詳細カード -->
            <AssignmentDetailCard :assignment="assignment" />

            <!-- セットされた予定セクション -->
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
                                <th class="pb-2 text-left font-medium">作業時間合計</th>
                                <th v-if="hasInterruptions" class="pb-2 text-left font-medium text-orange-600">中断時間</th>
                                <th v-if="hasInterruptions" class="pb-2 text-left font-medium text-blue-700">実作業時間</th>
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
                                <td class="py-2 text-xs text-orange-600">−{{ formatDurationFromMinutes(totalInterruptionMinutes) }}</td>
                                <td class="py-2 text-sm font-bold text-blue-700">{{ formatDurationFromMinutes(totalActualMinutes) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <p v-if="hasInterruptions" class="mt-2 text-xs text-gray-500">
                        ※ 差し込み作業が発生したため、中断時間を実作業時間から除外しています。
                    </p>
                </div>
            </div>

            <!-- チェーン（続きジョブ）パネル -->
            <div v-if="hasChain" class="overflow-hidden rounded-lg border border-orange-200 bg-orange-50 shadow-sm">
                <div class="border-b border-orange-200 bg-orange-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-orange-800">↩ 続きジョブ チェーン</h3>
                </div>
                <div class="divide-y divide-orange-100 px-5 py-3">
                    <!-- 元ジョブへのリンク -->
                    <div v-if="sourceItem" class="flex items-center gap-3 py-2">
                        <span class="text-xs text-orange-600 font-medium w-16 shrink-0">元ジョブ↑</span>
                        <button
                            @click="goChainItem(sourceItem)"
                            class="text-sm text-blue-700 underline hover:text-blue-900 text-left"
                        >{{ sourceItem.title }}</button>
                        <span v-if="sourceItem.completed" class="ml-auto rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">完了</span>
                    </div>
                    <!-- 続きジョブリスト -->
                    <div v-for="cont in continuationItems" :key="cont.id" class="flex items-center gap-3 py-2">
                        <span class="text-xs text-orange-600 font-medium w-16 shrink-0">続き↓</span>
                        <button
                            @click="goChainItem(cont)"
                            class="text-sm text-blue-700 underline hover:text-blue-900 text-left"
                        >{{ cont.title }}</button>
                        <span v-if="cont.completed" class="ml-auto rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">完了</span>
                    </div>
                    <!-- シリーズ合計時間 -->
                    <div v-if="seriesTotalMinutes > 0" class="flex items-center gap-3 py-2">
                        <span class="text-xs text-orange-600 font-medium w-16 shrink-0">合計</span>
                        <span class="text-sm font-bold text-orange-800">シリーズ全体: {{ formatDurationFromMinutes(seriesTotalMinutes) }}</span>
                    </div>
                </div>
            </div>

            <!-- アクションボタン -->
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white px-5 py-3 shadow-sm">
                <Link :href="routeBack()" class="inline-flex items-center rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    戻る
                </Link>

                <button
                    v-if="canDelete"
                    @click="deleteAssignment"
                    class="ml-auto inline-flex items-center rounded bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200"
                >
                    削除
                </button>

                <template v-if="isAssignee">
                    <Link
                        v-if="assignment.scheduled || assignment.scheduled_at"
                        :href="editHref"
                        class="inline-flex items-center rounded bg-blue-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-600"
                    >
                        予定を編集
                    </Link>

                    <div v-if="(assignment.scheduled || assignment.scheduled_at) && formattedEvents.length > 0">
                        <button
                            @click="submitComplete"
                            :disabled="isAssignmentCompleted || isSubmittingComplete"
                            :class="isAssignmentCompleted || isSubmittingComplete
                                ? 'cursor-not-allowed rounded bg-yellow-800 px-3 py-1.5 text-sm font-medium text-white opacity-70'
                                : 'rounded bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700'"
                        >
                            {{ isAssignmentCompleted ? '完了済み' : '完了にする' }}
                        </button>
                    </div>

                    <Link
                        v-else-if="!(assignment.scheduled || assignment.scheduled_at)"
                        :href="typeof route === 'function' ? route('events.create', { job: assignment.id }) : `/events/create?job=${assignment.id}`"
                        class="inline-flex items-center rounded bg-blue-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-600"
                    >
                        予定をセット
                    </Link>
                </template>

                <div v-else-if="assignment.scheduled || assignment.scheduled_at">
                    <span class="text-sm font-semibold text-green-600">セット済</span>
                </div>

                <div v-if="assignment.linked_assignment_id && projectJob?.id" class="ml-auto">
                    <Link
                        :href="route('coordinator.project_jobs.assignments.show', {
                            projectJob: projectJob.id,
                            assignment: assignment.linked_assignment_id,
                        })"
                        class="text-sm text-blue-600 hover:underline"
                    >
                        割当を見る (#{{ assignment.linked_assignment_id }})
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const { projectJob, assignment, canDelete } = defineProps({ projectJob: Object, assignment: Object, canDelete: { type: Boolean, default: false } });
const page = usePage();

const isAssignee = computed(() => {
    try {
        return page.props.auth.user && assignment && assignment.user && page.props.auth.user.id === assignment.user.id;
    } catch (e) {
        return false;
    }
});

const isAssignmentCompleted = computed(() => {
    try {
        if (!assignment) return false;
        if (assignment.completed) return true;
        const s = assignment.status_model ?? assignment.statusModel;
        if (s?.key === 'completed' || String(s?.name || '').indexOf('完了') !== -1) return true;
        if (assignment.status_label && String(assignment.status_label).indexOf('完了') !== -1) return true;
        return false;
    } catch (e) {
        return false;
    }
});

function routeBack() {
    try {
        return typeof route === 'function' ? route('user.myjobbox.index') : '/myjobbox';
    } catch (e) {
        return '/myjobbox';
    }
}

function deleteAssignment() {
    if (!confirm('このジョブ割り当てを本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(
        typeof route === 'function'
            ? route('user.myjobbox.destroy', { assignment: assignment?.id })
            : `/myjobbox/${assignment?.id}`,
        {
            onError: () => alert('削除に失敗しました。'),
        },
    );
}

// ---- 予定（events）関連 ----
const events = ref([]);
const isSubmittingComplete = ref(false);

// ---- チェーン（続きジョブ）関連 ----
const chainItems = ref([]);
const seriesEvents = ref([]); // 自分以外のチェーンメンバーのイベント

onMounted(async () => {
    try {
        const assigneeId = assignment && (assignment.user?.id || assignment.user_id || null);
        const isScheduled = Boolean(assignment && (assignment.scheduled || assignment.scheduled_at));
        if (assigneeId && isScheduled) {
            const url = (typeof route === 'function' ? route('events.index') : '/events')
                + '?user_id=' + encodeURIComponent(assigneeId)
                + '&job=' + encodeURIComponent(assignment.id);
            const res = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (res.ok) {
                const payload = await res.json();
                const evs = Array.isArray(payload)
                    ? payload.filter((e) => String(e.project_job_assignment_id) === String(assignment.id))
                    : [];
                events.value = evs;
            }
        }

        // チェーン全体を取得
        if (assignment?.id) {
            try {
                const chainUrl = (typeof route === 'function'
                    ? route('user.myjobbox.assignments.chain', { assignment: assignment.id })
                    : `/myjobbox/assignments/${assignment.id}/chain`);
                const chainRes = await fetch(chainUrl, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                if (chainRes.ok) {
                    const chainData = await chainRes.json();
                    chainItems.value = chainData.chain || [];

                    // 他のチェーンメンバーのイベントも取得
                    const othersIds = (chainData.chain || [])
                        .map((c) => c.id)
                        .filter((id) => id !== assignment.id);
                    const assigneeId2 = assignment.user?.id || assignment.user_id || null;
                    if (assigneeId2 && othersIds.length > 0) {
                        const allOtherEvs = [];
                        for (const otherId of othersIds) {
                            try {
                                const evUrl = (typeof route === 'function' ? route('events.index') : '/events')
                                    + '?user_id=' + encodeURIComponent(assigneeId2)
                                    + '&job=' + encodeURIComponent(otherId);
                                const evRes = await fetch(evUrl, {
                                    credentials: 'same-origin',
                                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                                });
                                if (evRes.ok) {
                                    const evData = await evRes.json();
                                    const filtered = Array.isArray(evData)
                                        ? evData.filter((e) => String(e.project_job_assignment_id) === String(otherId))
                                        : [];
                                    allOtherEvs.push(...filtered);
                                }
                            } catch (_) { /* ignore */ }
                        }
                        seriesEvents.value = allOtherEvs;
                    }
                }
            } catch (_) { /* ignore chain fetch errors */ }
        }
    } catch (err) {
        // ignore
    }
});

function formatDurationFromMinutes(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

const showScheduledSection = computed(() => {
    const statusLabel = assignment?.status_label || '';
    const statusOk =
        String(statusLabel).includes('セット') ||
        String(statusLabel).includes('完了') ||
        String(statusLabel).toLowerCase() === 'completed' ||
        String(statusLabel).toLowerCase() === 'scheduled';
    return assignment && (assignment.scheduled || assignment.scheduled_at) && statusOk;
});

const formattedEvents = computed(() => {
    return events.value.map((e) => {
        const rawStart = e.start ?? e.starts_at ?? null;
        const rawEnd = e.end ?? e.ends_at ?? null;
        const start = rawStart ? new Date(rawStart) : null;
        const end = rawEnd ? new Date(rawEnd) : null;
        const dateStr = e.date || (start ? start.toISOString().slice(0, 10) : '');
        const startTime = start ? start.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
        const endTime = end ? end.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
        const minutes = start && end ? Math.max(0, Math.round((end - start) / 60000)) : 0;
        const interruptionMinutes = e.interruption_minutes ?? 0;
        const actualMinutes = Math.max(0, minutes - interruptionMinutes);
        return { ...e, dateStr, startTime, endTime, minutes, interruptionMinutes, actualMinutes };
    });
});

const hasInterruptions = computed(() => formattedEvents.value.some((e) => e.interruptionMinutes > 0));
const totalMinutes = computed(() => formattedEvents.value.reduce((sum, e) => sum + e.minutes, 0));
const totalInterruptionMinutes = computed(() => formattedEvents.value.reduce((sum, e) => sum + e.interruptionMinutes, 0));
const totalActualMinutes = computed(() => formattedEvents.value.reduce((sum, e) => sum + e.actualMinutes, 0));

// ---- チェーン関連 computed ----
const chainOtherItems = computed(() =>
    chainItems.value.filter((c) => c.id !== assignment?.id)
);
const sourceItem = computed(() =>
    chainItems.value.find((c) => String(c.id) === String(assignment?.source_assignment_id))
);
const continuationItems = computed(() =>
    chainItems.value.filter((c) => String(c.source_assignment_id) === String(assignment?.id))
);
const hasChain = computed(() => chainItems.value.length > 1);

function formatSeriesEvent(e) {
    const rawStart = e.start ?? e.starts_at ?? null;
    const rawEnd = e.end ?? e.ends_at ?? null;
    const start = rawStart ? new Date(rawStart) : null;
    const end = rawEnd ? new Date(rawEnd) : null;
    const minutes = start && end ? Math.max(0, Math.round((end - start) / 60000)) : 0;
    const interruptionMinutes = e.interruption_minutes ?? 0;
    const actualMinutes = Math.max(0, minutes - interruptionMinutes);
    return { minutes, interruptionMinutes, actualMinutes };
}
const seriesTotalMinutes = computed(() => {
    const ownTotal = totalActualMinutes.value || totalMinutes.value;
    const otherTotal = seriesEvents.value.reduce((sum, e) => {
        const f = formatSeriesEvent(e);
        return sum + (f.actualMinutes || f.minutes);
    }, 0);
    return ownTotal + otherTotal;
});

function goChainItem(item) {
    try {
        const url = typeof route === 'function'
            ? route('user.myjobbox.show', { assignment: item.id })
            : `/myjobbox/${item.id}`;
        window.location.href = url;
    } catch (_) {
        window.location.href = `/myjobbox/${item.id}`;
    }
}

// ---- 完了処理 ----
async function submitComplete() {
    try {
        if (!confirm('このジョブを完了としてマークしますか？')) return;
        if (!assignment || !assignment.id) { alert('割当情報が見つかりません。'); return; }
        isSubmittingComplete.value = true;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const url = typeof route === 'function'
            ? route('myjobbox.assignments.complete', { assignment: assignment.id })
            : `/myjobbox/assignments/${assignment.id}/complete`;
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            try {
                router.get(typeof route === 'function' ? route('user.myjobbox.index') : '/myjobbox');
            } catch (e) {
                window.location.href = route('user.myjobbox.index');
            }
        } else {
            isSubmittingComplete.value = false;
            alert('完了処理に失敗しました。');
        }
    } catch (e) {
        isSubmittingComplete.value = false;
        alert('完了処理に失敗しました。');
    }
}

// ---- 予定編集リンク ----
const editDate = computed(() => {
    if (Array.isArray(events.value) && events.value.length > 0) {
        const ev = events.value[0];
        if (ev.date) return ev.date;
        if (ev.start) return new Date(ev.start).toISOString().slice(0, 10);
        if (ev.starts_at) return new Date(ev.starts_at).toISOString().slice(0, 10);
    }
    if (assignment?.scheduled_at) return new Date(assignment.scheduled_at).toISOString().slice(0, 10);
    if (assignment?.date) return assignment.date;
    return new Date().toISOString().slice(0, 10);
});

const editHref = computed(() => {
    const base = typeof route === 'function' ? route('calendar.index') : '/calendar';
    return base + '?date=' + encodeURIComponent(editDate.value) + '&user_id=' + encodeURIComponent(assignment.user?.id || '');
});
</script>
