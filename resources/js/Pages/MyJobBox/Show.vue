<template>
    <AppLayout :title="`ジョブ割り当て - ${assignment?.title || ''}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="routeBack()" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300">← マイジョブBOXに戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て — 詳細</h2>
            </div>
        </template>

        <div class="space-y-4">
            <!-- ジョブ割り当て詳細カード -->
            <AssignmentDetailCard :assignment="assignment" />

            <!-- 校正依頼情報（proof型ジョブの場合のみ表示） -->
            <div v-if="proofRequestInfo" class="overflow-hidden rounded-lg border border-pink-200 bg-pink-50 shadow-sm">
                <div class="border-b border-pink-200 bg-pink-100 px-5 py-3 flex items-center gap-2">
                    <span class="rounded-full bg-pink-600 px-2 py-0.5 text-xs font-bold text-white">校正依頼</span>
                    <h3 class="text-sm font-semibold text-pink-800">{{ proofRequestInfo.title }}</h3>
                    <span class="ml-auto rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-yellow-100 text-yellow-800': proofRequestInfo.status === 'assigned',
                            'bg-blue-100 text-blue-800': proofRequestInfo.status === 'in_progress',
                            'bg-green-100 text-green-800': proofRequestInfo.status === 'completed',
                        }">
                        {{ proofRequestInfo.status === 'assigned' ? '校正待ち'
                         : proofRequestInfo.status === 'in_progress' ? '校正中'
                         : proofRequestInfo.status === 'completed' ? '校正完了'
                         : proofRequestInfo.status }}
                    </span>
                </div>
                <div class="px-5 py-3 grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                    <div v-if="proofRequestInfo.requester_name">
                        <span class="text-xs text-pink-600 font-medium">依頼者</span>
                        <p class="text-gray-800">{{ proofRequestInfo.requester_name }}</p>
                    </div>
                    <div v-if="proofRequestInfo.coordinator_name">
                        <span class="text-xs text-pink-600 font-medium">校正管理者</span>
                        <p class="text-gray-800">{{ proofRequestInfo.coordinator_name }}</p>
                    </div>
                    <div v-if="proofRequestInfo.deadline">
                        <span class="text-xs text-pink-600 font-medium">締切</span>
                        <p class="text-gray-800">{{ new Date(proofRequestInfo.deadline).toLocaleDateString('ja-JP') }}</p>
                    </div>
                    <div v-if="proofRequestInfo.note" class="col-span-2">
                        <span class="text-xs text-pink-600 font-medium">備考</span>
                        <p class="whitespace-pre-wrap text-gray-800">{{ proofRequestInfo.note }}</p>
                    </div>
                </div>
            </div>

            <!-- ファイル一覧（file_info があれば常に表示） -->
            <div v-if="assignment.file_info" class="mt-2">
                <FileInfoDisplay :fileInfo="assignment.file_info" />
            </div>

            <!-- セットされた予定セクション -->
            <div v-if="showScheduledSection" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b bg-gray-50 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-700">セットされた予定</h3>
                </div>
                <div class="px-5 py-4">
                    <div v-if="formattedEvents.length === 0" class="text-sm text-gray-500">予定が見つかりません。</div>
                    <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-gray-500">
                                <th class="pb-2 text-left font-medium">作業日</th>
                                <th class="pb-2 text-left font-medium">開始</th>
                                <th class="pb-2 text-left font-medium">終了</th>
                                <th class="pb-2 text-left font-medium">作業時間合計</th>
                                <th v-if="hasDeductions" class="pb-2 text-left font-medium text-orange-600">控除時間</th>
                                <th v-if="hasDeductions" class="pb-2 text-left font-medium text-blue-700">実作業時間</th>
                                <th v-if="isAssignee" class="pb-2 text-left font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="ev in formattedEvents" :key="ev.id">
                                <td class="py-2 text-gray-900">{{ ev.dateStr }}</td>
                                <td class="py-2 text-gray-900">{{ ev.startTime }}</td>
                                <td class="py-2 text-gray-900">{{ ev.endTime }}</td>
                                <td class="py-2" :class="(ev.interruptionMinutes > 0 || ev.lunchMinutes > 0) ? 'text-gray-400 line-through' : 'text-gray-900'">
                                    {{ formatDurationFromMinutes(ev.minutes) }}
                                </td>
                                <td v-if="hasDeductions" class="py-2 text-orange-600">
                                    <template v-if="ev.interruptionMinutes > 0 || ev.lunchMinutes > 0">
                                        −{{ formatDurationFromMinutes(ev.interruptionMinutes + ev.lunchMinutes) }}
                                        <span v-if="ev.lunchMinutes > 0 && ev.interruptionMinutes > 0" class="ml-1 text-xs text-gray-400">（中断＋休憩）</span>
                                        <span v-else-if="ev.lunchMinutes > 0" class="ml-1 text-xs text-amber-600">（休憩）</span>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td v-if="hasDeductions" class="py-2 font-bold text-blue-700">
                                    {{ formatDurationFromMinutes(ev.actualMinutes) }}
                                </td>
                                <td v-if="isAssignee" class="py-2 pl-2">
                                    <Link
                                        :href="eventEditHref(ev)"
                                        class="rounded bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                    >編集</Link>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="hasDeductions">
                            <tr class="border-t bg-gray-50">
                                <td colspan="3" class="py-2 pr-2 text-right text-xs text-gray-500">合計実作業時間：</td>
                                <td class="py-2 text-xs text-gray-400 line-through">{{ formatDurationFromMinutes(totalMinutes) }}</td>
                                <td class="py-2 text-xs text-orange-600">−{{ formatDurationFromMinutes(totalInterruptionMinutes + totalLunchMinutes) }}</td>
                                <td class="py-2 text-sm font-bold text-blue-700">{{ formatDurationFromMinutes(totalActualMinutes) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    <p v-if="hasInterruptions" class="mt-2 text-xs text-gray-500">
                        ※ 差し込み作業が発生したため、中断時間を実作業時間から除外しています。
                    </p>
                    <p v-if="hasLunch" class="mt-1 text-xs text-gray-500">
                        ※ 休憩時間が作業時間と重複しているため、該当分を実作業時間から除外しています。
                    </p>
                </div>
            </div>

            <!-- チェーン（続きジョブ）パネル -->
            <div v-if="hasChain" class="overflow-hidden rounded-lg border border-orange-200 bg-orange-50 shadow-sm">
                <div class="border-b border-orange-200 bg-orange-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-orange-800">↩ 続きジョブ シリーズ（{{ chainItems.length }}件）</h3>
                </div>
                <div class="divide-y divide-orange-100 px-5 py-2">
                    <div v-for="(item, idx) in chainItems" :key="item.id"
                         class="flex items-start gap-3 py-2.5"
                         :class="item.id === assignment?.id ? '-mx-5 bg-orange-100 px-5' : ''">
                        <!-- 番号 -->
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="item.id === assignment?.id ? 'bg-orange-600 text-white' : 'bg-orange-200 text-orange-700'">
                            {{ idx + 1 }}
                        </span>
                        <!-- タイトルと時間 -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button v-if="item.id !== assignment?.id"
                                        @click="goChainItem(item)"
                                        class="text-left text-sm text-blue-700 underline hover:text-blue-900">
                                    {{ item.title }}
                                </button>
                                <span v-else class="text-sm font-medium text-gray-900">{{ item.title }}</span>
                                <span v-if="item.id === assignment?.id"
                                      class="rounded-full bg-orange-600 px-1.5 py-0.5 text-xs text-white">現在</span>
                                <span v-if="item.completed"
                                      class="rounded-full bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-800">完了</span>
                            </div>
                            <!-- 現在のアサインメントのイベント -->
                            <div v-if="item.id === assignment?.id">
                                <div v-if="formattedEvents.length" class="mt-1 space-y-0.5">
                                    <div v-for="ev in formattedEvents" :key="ev.id" class="text-xs text-gray-500">
                                        {{ ev.dateStr }} {{ ev.startTime }}〜{{ ev.endTime }}
                                        <span class="ml-1 font-medium text-gray-700">{{ formatDurationFromMinutes(ev.actualMinutes) }}</span>
                                    </div>
                                </div>
                                <div v-else class="mt-0.5 text-xs text-gray-400">（予定未セット）</div>
                            </div>
                            <!-- 他のアサインメントのイベント -->
                            <div v-else>
                                <div v-if="eventsForPja(item.id).length" class="mt-1 space-y-0.5">
                                    <div v-for="ev in eventsForPja(item.id)" :key="ev.id" class="text-xs text-gray-500">
                                        {{ ev.dateStr }} {{ ev.startTime }}〜{{ ev.endTime }}
                                        <span class="ml-1 font-medium text-gray-700">{{ formatDurationFromMinutes(ev.actualMinutes) }}</span>
                                    </div>
                                </div>
                                <div v-else class="mt-0.5 text-xs text-gray-400">（予定未セット）</div>
                            </div>
                        </div>
                        <!-- 合計時間 -->
                        <div class="shrink-0 text-right">
                            <span class="text-sm font-bold"
                                  :class="(item.id === assignment?.id ? totalActualMinutes : seriesMinutesForPja(item.id)) > 0 ? 'text-indigo-700' : 'text-gray-300'">
                                {{ item.id === assignment?.id
                                    ? (totalActualMinutes > 0 ? formatDurationFromMinutes(totalActualMinutes) : '-')
                                    : (seriesMinutesForPja(item.id) > 0 ? formatDurationFromMinutes(seriesMinutesForPja(item.id)) : '-') }}
                            </span>
                        </div>
                    </div>
                    <!-- シリーズ合計 -->
                    <div v-if="seriesTotalMinutes > 0" class="flex items-center justify-between py-2.5">
                        <span class="text-sm font-semibold text-orange-800">シリーズ合計</span>
                        <span class="text-base font-bold text-orange-800">{{ formatDurationFromMinutes(seriesTotalMinutes) }}</span>
                    </div>
                </div>
            </div>

            <!-- アクションボタン -->
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white px-5 py-3 shadow-sm">
                <button
                    v-if="canDelete"
                    @click="deleteAssignment"
                    class="ml-auto inline-flex items-center rounded bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200"
                >
                    削除
                </button>

                <template v-if="isAssignee">
                    <Link
                        v-if="eventsLoaded && (assignment.scheduled || assignment.scheduled_at) && formattedEvents.length > 0"
                        :href="editHref"
                        class="inline-flex items-center rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        予定を編集
                    </Link>

                    <div v-if="(assignment.scheduled || assignment.scheduled_at) && formattedEvents.length > 0">
                        <button
                            @click="submitComplete"
                            :disabled="isAssignmentCompleted || isSubmittingComplete"
                            :class="
                                isAssignmentCompleted || isSubmittingComplete
                                    ? 'cursor-not-allowed rounded bg-yellow-800 px-3 py-1.5 text-sm font-medium text-white opacity-70'
                                    : 'rounded bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700'
                            "
                        >
                            {{ isAssignmentCompleted ? '完了済み' : '完了にする' }}
                        </button>
                    </div>

                    <Link
                        v-else-if="!(assignment.scheduled || assignment.scheduled_at)"
                        :href="typeof route === 'function' ? route('events.create_job', { job: assignment.id }) : `/events/create-job?job=${assignment.id}`"
                        class="inline-flex items-center rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        予定をセット
                    </Link>
                </template>

                <div v-else-if="assignment.scheduled || assignment.scheduled_at">
                    <span class="text-sm font-semibold text-green-600">セット済</span>
                </div>

                <div v-if="assignment.linked_assignment_id && projectJob?.id" class="ml-auto">
                    <Link
                        :href="
                            route('coordinator.project_jobs.assignments.show', {
                                projectJob: projectJob.id,
                                assignment: assignment.linked_assignment_id,
                            })
                        "
                        class="text-sm text-blue-600 hover:underline"
                    >
                        割当を見る (#{{ assignment.linked_assignment_id }})
                    </Link>
                </div>

                <!-- 校正依頼ボタン（情報出版部署のみ表示・完了済みは非表示） -->
                <template v-if="$page.props.auth.featureFlags.proofRequest">
                    <button
                        v-if="!isAssignmentCompleted && !proofRequested"
                        @click="showProofModal = true"
                        class="rounded border border-pink-300 bg-pink-50 px-3 py-1.5 text-sm font-medium text-pink-700 hover:bg-pink-100"
                    >
                        校正依頼
                    </button>
                    <span
                        v-else-if="!isAssignmentCompleted && proofRequested"
                        class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-400 cursor-not-allowed"
                    >
                        校正依頼済み
                    </span>
                </template>
            </div>
        </div>

        <ProofRequestModal
            :show="showProofModal"
            :initial-title="assignment?.title || ''"
            :project-job-assignment-id="assignment?.id || null"
            :project-job-id="projectJob?.id || null"
            @close="showProofModal = false"
        />
    </AppLayout>
</template>

<script setup>
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import FileInfoDisplay from '@/Components/FileInfoDisplay.vue';
import ProofRequestModal from '@/Components/ProofRequestModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const { projectJob, assignment, canDelete, linkedProgressCellCount, proofRequested, proofRequestInfo } = defineProps({
    projectJob: Object,
    assignment: Object,
    canDelete: { type: Boolean, default: false },
    linkedProgressCellCount: { type: Number, default: 0 },
    proofRequested: { type: Boolean, default: false },
    proofRequestInfo: { type: Object, default: null },
});
const page = usePage();

const showProofModal = ref(false);

// coordinator / admin のみ紐づけボタンを表示
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
    const savedUrl = sessionStorage.getItem('myjobbox_index_url');
    if (savedUrl) return savedUrl;
    try {
        return typeof route === 'function' ? route('user.myjobbox.index') : '/myjobbox';
    } catch (e) {
        return '/myjobbox';
    }
}

function deleteAssignment() {
    const msg = linkedProgressCellCount > 0
        ? `このジョブ割り当てを削除しますか？\n\n⚠ このジョブは進行管理表（${linkedProgressCellCount}件）に登録されています。\n削除すると管理表の登録情報も同時にクリアされます。\n\nこの操作は取り消せません。`
        : 'このジョブ割り当てを本当に削除しますか？この操作は取り消せません。';
    if (!confirm(msg)) return;
    router.delete(typeof route === 'function' ? route('user.myjobbox.destroy', { assignment: assignment?.id }) : `/myjobbox/${assignment?.id}`, {
        onError: () => alert('削除に失敗しました。'),
    });
}

// ---- 予定（events）関連 ----
const events = ref([]);
const eventsLoaded = ref(false);
const isSubmittingComplete = ref(false);

// ---- チェーン（続きジョブ）関連 ----
const chainItems = ref([]);
const seriesEvents = ref([]); // 自分以外のチェーンメンバーのイベント

onMounted(async () => {
    try {
        const assigneeId = assignment && (assignment.user?.id || assignment.user_id || null);
        const isScheduled = Boolean(assignment && (assignment.scheduled || assignment.scheduled_at));
        if (assigneeId && isScheduled) {
            const url =
                (typeof route === 'function' ? route('events.index') : '/events') +
                '?user_id=' +
                encodeURIComponent(assigneeId) +
                '&job=' +
                encodeURIComponent(assignment.id);
            const res = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (res.ok) {
                const payload = await res.json();
                const evs = Array.isArray(payload) ? payload.filter((e) => String(e.project_job_assignment_id) === String(assignment.id)) : [];
                events.value = evs;
            }
        }
        eventsLoaded.value = true;

        // チェーン全体を取得
        if (assignment?.id) {
            try {
                const chainUrl =
                    typeof route === 'function'
                        ? route('user.myjobbox.assignments.chain', { assignment: assignment.id })
                        : `/myjobbox/assignments/${assignment.id}/chain`;
                const chainRes = await fetch(chainUrl, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                if (chainRes.ok) {
                    const chainData = await chainRes.json();
                    chainItems.value = chainData.chain || [];

                    // 他のチェーンメンバーのイベントも取得
                    const othersIds = (chainData.chain || []).map((c) => c.id).filter((id) => id !== assignment.id);
                    const assigneeId2 = assignment.user?.id || assignment.user_id || null;
                    if (assigneeId2 && othersIds.length > 0) {
                        const allOtherEvs = [];
                        for (const otherId of othersIds) {
                            try {
                                const evUrl =
                                    (typeof route === 'function' ? route('events.index') : '/events') +
                                    '?user_id=' +
                                    encodeURIComponent(assigneeId2) +
                                    '&job=' +
                                    encodeURIComponent(otherId);
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
                            } catch (_) {
                                /* ignore */
                            }
                        }
                        seriesEvents.value = allOtherEvs;
                    }
                }
            } catch (_) {
                /* ignore chain fetch errors */
            }
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
        const dateStr = e.date || (start ? start.toLocaleDateString('sv-SE') : '');
        const startTime = start ? start.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
        const endTime = end ? end.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
        const minutes = start && end ? Math.max(0, Math.round((end - start) / 60000)) : 0;
        const interruptionMinutes = e.interruption_minutes ?? 0;
        const lunchMinutes = e.lunch_overlap_minutes ?? 0;
        const actualMinutes = Math.max(0, minutes - interruptionMinutes - lunchMinutes);
        return { ...e, dateStr, startTime, endTime, minutes, interruptionMinutes, lunchMinutes, actualMinutes };
    });
});

const hasInterruptions = computed(() => formattedEvents.value.some((e) => e.interruptionMinutes > 0));
const hasLunch = computed(() => formattedEvents.value.some((e) => e.lunchMinutes > 0));
const hasDeductions = computed(() => hasInterruptions.value || hasLunch.value);
const totalMinutes = computed(() => formattedEvents.value.reduce((sum, e) => sum + e.minutes, 0));
const totalInterruptionMinutes = computed(() => formattedEvents.value.reduce((sum, e) => sum + e.interruptionMinutes, 0));
const totalLunchMinutes = computed(() => formattedEvents.value.reduce((sum, e) => sum + e.lunchMinutes, 0));
const totalActualMinutes = computed(() => formattedEvents.value.reduce((sum, e) => sum + e.actualMinutes, 0));

// ---- チェーン関連 computed ----
const chainOtherItems = computed(() => chainItems.value.filter((c) => c.id !== assignment?.id));
const sourceItem = computed(() => chainItems.value.find((c) => String(c.id) === String(assignment?.source_assignment_id)));
const continuationItems = computed(() => chainItems.value.filter((c) => String(c.source_assignment_id) === String(assignment?.id)));
const hasChain = computed(() => chainItems.value.length > 1);

function formatSeriesEvent(e) {
    const rawStart = e.start ?? e.starts_at ?? null;
    const rawEnd = e.end ?? e.ends_at ?? null;
    const start = rawStart ? new Date(rawStart) : null;
    const end = rawEnd ? new Date(rawEnd) : null;
    const minutes = start && end ? Math.max(0, Math.round((end - start) / 60000)) : 0;
    const interruptionMinutes = e.interruption_minutes ?? 0;
    const lunchMinutes = e.lunch_overlap_minutes ?? 0;
    const actualMinutes = Math.max(0, minutes - interruptionMinutes - lunchMinutes);
    return { minutes, interruptionMinutes, lunchMinutes, actualMinutes };
}
const seriesTotalMinutes = computed(() => {
    const ownTotal = totalActualMinutes.value || totalMinutes.value;
    const otherTotal = seriesEvents.value.reduce((sum, e) => {
        const f = formatSeriesEvent(e);
        return sum + (f.actualMinutes || f.minutes);
    }, 0);
    return ownTotal + otherTotal;
});

function eventsForPja(pjaId) {
    return seriesEvents.value
        .filter((e) => String(e.project_job_assignment_id) === String(pjaId))
        .map((e) => {
            const rawStart = e.start ?? e.starts_at ?? null;
            const rawEnd = e.end ?? e.ends_at ?? null;
            const start = rawStart ? new Date(rawStart) : null;
            const end = rawEnd ? new Date(rawEnd) : null;
            const dateStr = e.date || (start ? start.toLocaleDateString('sv-SE') : '');
            const startTime = start ? start.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
            const endTime = end ? end.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
            const minutes = start && end ? Math.max(0, Math.round((end - start) / 60000)) : 0;
            const interruptionMinutes = e.interruption_minutes ?? 0;
            const lunchMinutes = e.lunch_overlap_minutes ?? 0;
            const actualMinutes = Math.max(0, minutes - interruptionMinutes - lunchMinutes);
            return { id: e.id, dateStr, startTime, endTime, minutes, actualMinutes };
        });
}

function seriesMinutesForPja(pjaId) {
    return eventsForPja(pjaId).reduce((sum, ev) => sum + (ev.actualMinutes || ev.minutes), 0);
}

function goChainItem(item) {
    try {
        const url = typeof route === 'function' ? route('user.myjobbox.show', { assignment: item.id }) : null;
        if (url) { window.location.href = url; } else { router && router.visit && router.visit(route('user.myjobbox.show', { assignment: item.id })); }
    } catch (_) {
        window.location.href = route('user.myjobbox.show', { assignment: item.id });
    }
}

// ---- 完了処理 ----
async function submitComplete() {
    try {
        if (!confirm('このジョブを完了としてマークしますか？')) return;
        if (!assignment || !assignment.id) {
            alert('割当情報が見つかりません。');
            return;
        }
        isSubmittingComplete.value = true;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const url =
            typeof route === 'function'
                ? route('myjobbox.assignments.complete', { assignment: assignment.id })
                : `/myjobbox/assignments/${assignment.id}/complete`;
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            const savedUrl = sessionStorage.getItem('myjobbox_index_url');
            const indexUrl = savedUrl || (typeof route === 'function' ? route('user.myjobbox.index') : '/myjobbox');
            window.location.href = indexUrl;
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
function eventEditHref(ev) {
    try {
        const returnTo = typeof route === 'function'
            ? route('user.myjobbox.show', { assignment: assignment?.id })
            : `/myjobbox/${assignment?.id}`;
        return route('events.edit', { event: ev.id }) + '?return_to=' + encodeURIComponent(returnTo);
    } catch (_) {
        return '#';
    }
}

const editHref = computed(() => {
    const firstEvent = Array.isArray(events.value) && events.value.length > 0 ? events.value[0] : null;
    if (firstEvent && firstEvent.id) {
        try {
            const returnTo = typeof route === 'function'
                ? route('user.myjobbox.show', { assignment: assignment?.id })
                : `/myjobbox/${assignment?.id}`;
            return route('events.edit', { event: firstEvent.id }) + '?return_to=' + encodeURIComponent(returnTo);
        } catch (_) {
            // fallback below
        }
    }
    const base = typeof route === 'function' ? route('calendar.index') : '/calendar';
    return base;
});
</script>
