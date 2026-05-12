<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import FileInfoDisplay from '@/Components/FileInfoDisplay.vue';
import ProofRequestModal from '@/Components/ProofRequestModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    event: Object,
    jst_start: { type: String, default: null },
    jst_end: { type: String, default: null },
    hide_edit: { type: Boolean, default: false },
    view_as_coordinator: { type: Boolean, default: false },
    coordinator_assignment: { type: Object, default: null },
    lunch_start: { type: String, default: null },
    lunch_end: { type: String, default: null },
    lunch_overlap_minutes: { type: Number, default: 0 },
    proof_requested: { type: Boolean, default: false },
    chain_series: { type: Object, default: null },
    overlapping_events: { type: Array, default: () => [] },
    dynamic_interruption_minutes: { type: Number, default: 0 },
});

const showProofModal = ref(false);

const assignment = computed(() => props.event?.project_job_assignment ?? null);
const chainSeries = computed(() => props.chain_series ?? null);

function isEventCompleted() {
    try {
        if (!props.event) return false;
        if (props.event.title && String(props.event.title).indexOf('【完了】') === 0) return true;
        if (assignment.value?.completed) return true;
        const s = assignment.value?.status_model ?? assignment.value?.statusModel;
        if (s?.key === 'completed' || String(s?.name || '').indexOf('完了') !== -1) return true;
        return false;
    } catch (e) {
        return false;
    }
}

function formatJstDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (!isNaN(d.getTime())) {
        const fmt = new Intl.DateTimeFormat('ja-JP', {
            timeZone: 'Asia/Tokyo',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: false,
        });
        const p = Object.fromEntries(fmt.formatToParts(d).map(({ type, value }) => [type, value]));
        return `${p.year}-${p.month}-${p.day} ${p.hour}:${p.minute}`;
    }
    // フォールバック: タイムゾーン情報のない文字列はそのまま切り出す
    const s = String(dateStr);
    const m = s.match(/(\d{4}-\d{2}-\d{2})[T ]?(\d{2}:\d{2})/);
    return m ? `${m[1]} ${m[2]}` : s.replace('T', ' ').substring(0, 16);
}

function formatMins(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

function formatEvDate(dateStr) {
    const m = String(dateStr).match(/\d{4}-(\d{2})-(\d{2})/);
    return m ? `${parseInt(m[1])}/${parseInt(m[2])}` : dateStr;
}

function totalRecordedMins() {
    const s = props.event?.start ?? props.event?.starts_at;
    const e = props.event?.end ?? props.event?.ends_at;
    if (!s || !e) return 0;
    return Math.max(0, Math.round((new Date(e) - new Date(s)) / 60000));
}

function durationText() {
    return formatMins(totalRecordedMins());
}

// stored interruption_minutes (旧：フォーム送信時に保存)
const storedInterruptionMins = computed(() => props.event?.interruption_minutes ?? 0);
// dynamic_interruption_minutes はサーバーが毎回リアルタイムで計算した値
// stored より dynamic を優先する（stale 問題を回避）
const interruptionMins = computed(() =>
    props.dynamic_interruption_minutes > 0
        ? props.dynamic_interruption_minutes
        : storedInterruptionMins.value,
);
const lunchMins = computed(() => props.lunch_overlap_minutes ?? 0);
const hasDeductions = computed(() => lunchMins.value > 0 || interruptionMins.value > 0);
// 自分から差し引く重複イベント（表示用）
const selfOverlapEvents = computed(() =>
    (props.overlapping_events ?? []).filter((e) => e.direction === 'self'),
);
// 相手から差し引く重複イベント（参考表示用）
const otherOverlapEvents = computed(() =>
    (props.overlapping_events ?? []).filter((e) => e.direction === 'other'),
);

function actualDurationText() {
    const actual = Math.max(0, totalRecordedMins() - lunchMins.value - interruptionMins.value);
    return formatMins(actual);
}

function goBack() {
    window.history.back();
}

function confirmDelete() {
    if (!confirm('この予定を削除しますか？')) return;
    router.delete(route('events.destroy', { event: props.event.id }));
}

function submitComplete() {
    // Coordinator割当は自動的に完了に設定されるため、直接完了処理を実行
    router.post(route('events.complete', { event: props.event.id }));
}

const eventTypeLabel = computed(() => props.event?.event_item_type?.name ?? null);

const CLIENT_SLUGS   = ['client_visit', 'customer_visit', 'outing'];
const INTERNAL_SLUGS = ['meeting_internal', 'conference', 'other'];

/** event_item_type の slug に応じた編集ルート URL */
const editHref = computed(() => {
    const slug = props.event?.event_item_type?.slug ?? null;
    if (CLIENT_SLUGS.includes(slug)) {
        return route('events.client-event.edit', { event: props.event.id });
    }
    if (INTERNAL_SLUGS.includes(slug)) {
        return route('events.internal-event.edit', { event: props.event.id });
    }
    return route('events.edit', props.event.id);
});
</script>

<template>
    <AppLayout title="イベント詳細">
        <template #header>
            <div class="flex items-center gap-3">
                <button @click="goBack"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 戻る</button>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">予定詳細</h2>
            </div>
        </template>

        <template #headerExtras>
            <div class="flex items-center gap-2">
                <template v-if="view_as_coordinator">
                    <span class="inline-flex cursor-not-allowed items-center gap-1.5 rounded bg-gray-300 px-4 py-2 text-sm font-medium text-gray-500" title="Coordinator は編集できません">
                        編集（閲覧のみ）
                    </span>
                    <span class="inline-flex cursor-not-allowed items-center gap-1.5 rounded bg-gray-300 px-4 py-2 text-sm font-medium text-gray-500" title="Coordinator は削除できません">
                        削除（閲覧のみ）
                    </span>
                </template>
                <template v-else>
                    <Link v-if="!hide_edit"
                          :href="editHref"
                          class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        編集
                    </Link>
                    <button @click="confirmDelete"
                            class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        削除
                    </button>
                </template>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-4">

            <!-- イベント情報カード -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <!-- カードヘッダー -->
                <div class="flex items-start justify-between gap-3 border-b bg-gray-50 px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span v-if="eventTypeLabel"
                                  class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-700">
                                {{ eventTypeLabel }}
                            </span>
                            <span v-if="isEventCompleted()"
                                  class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">
                                完了済み
                            </span>
                        </div>
                        <h1 class="mt-1 text-xl font-bold text-gray-900">{{ event.title }}</h1>
                    </div>
                </div>

                <!-- 日時 -->
                <div class="px-5 py-4">
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">日時</h4>
                    <div class="flex flex-wrap items-start gap-6">
                        <div>
                            <div class="text-xs text-gray-500">開始</div>
                            <div class="mt-0.5 text-sm font-medium text-gray-900">{{ jst_start ?? formatJstDateTime(event.start) }}</div>
                        </div>
                        <div class="mt-4 text-gray-300">→</div>
                        <div>
                            <div class="text-xs text-gray-500">終了</div>
                            <div class="mt-0.5 text-sm font-medium text-gray-900">{{ jst_end ?? formatJstDateTime(event.end) }}</div>
                        </div>
                        <div class="ml-auto text-right">
                            <div class="text-xs text-gray-500">作業時間</div>
                            <div class="mt-0.5 text-base font-bold text-indigo-700">{{ actualDurationText() }}</div>
                            <div v-if="hasDeductions" class="mt-1 space-y-0.5 text-xs text-gray-400">
                                <div>記録 {{ durationText() }}</div>
                                <div v-if="lunchMins > 0" class="text-amber-600">休憩 −{{ formatMins(lunchMins) }}（{{ lunch_start }}〜{{ lunch_end }}）</div>
                                <div v-if="interruptionMins > 0" class="text-orange-600">重複・中断 −{{ formatMins(interruptionMins) }}</div>
                            </div>
                        </div>
                    </div>
                    <!-- 重複イベントの詳細表示 -->
                    <div v-if="selfOverlapEvents.length > 0"
                         class="mt-3 rounded bg-orange-50 border border-orange-200 px-3 py-2 text-xs text-orange-700 space-y-1">
                        <div class="font-semibold">この予定は以下の予定と重複しているため、合計 {{ formatMins(interruptionMins) }} を実作業時間から差し引いています：</div>
                        <ul class="ml-3 list-disc space-y-0.5">
                            <li v-for="ev in selfOverlapEvents" :key="ev.id">
                                「{{ ev.title }}」― {{ formatMins(ev.overlap_mins) }} 重複
                            </li>
                        </ul>
                    </div>
                    <!-- 相手イベントへの影響（参考表示） -->
                    <div v-if="otherOverlapEvents.length > 0"
                         class="mt-2 rounded bg-sky-50 border border-sky-200 px-3 py-2 text-xs text-sky-700 space-y-1">
                        <div class="font-semibold">この予定は以下の長い予定の中断として記録されています：</div>
                        <ul class="ml-3 list-disc space-y-0.5">
                            <li v-for="ev in otherOverlapEvents" :key="ev.id">
                                「{{ ev.title }}」― {{ formatMins(ev.overlap_mins) }} 分
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- 詳細テキスト（ジョブイベントでない場合のみ表示） -->
                <div v-if="event.description && !assignment" class="border-t px-5 py-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">詳細</h4>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{{ event.description }}</p>
                </div>

                <!-- ボタン類 -->
                <div v-if="event.project_job_assignment_id" class="flex flex-wrap items-center gap-2 border-t bg-gray-50 px-5 py-3">
                    <button
                        @click="submitComplete"
                        :disabled="isEventCompleted()"
                        :class="isEventCompleted()
                            ? 'inline-flex cursor-not-allowed items-center gap-1.5 rounded bg-yellow-800 px-3 py-1.5 text-sm font-medium text-white opacity-70'
                            : 'inline-flex items-center gap-1.5 rounded bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700'"
                    >
                        {{ isEventCompleted() ? '完了済み' : '完了する' }}
                    </button>

                    <!-- 校正依頼ボタン（完了済みまたは校正ジョブは非表示） -->
                    <template v-if="assignment?.job_type !== 'proof'">
                        <button
                            v-if="!isEventCompleted() && !props.proof_requested"
                            @click="showProofModal = true"
                            class="inline-flex items-center gap-1.5 rounded border border-pink-300 bg-pink-50 px-3 py-1.5 text-sm font-medium text-pink-700 hover:bg-pink-100"
                        >
                            校正依頼
                        </button>
                        <span
                            v-else-if="!isEventCompleted() && props.proof_requested"
                            class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-400 cursor-not-allowed"
                        >
                            校正依頼済み
                        </span>
                    </template>
                </div>
            </div>

            <!-- ジョブ割り当て詳細カード -->
            <div v-if="assignment">
                <AssignmentDetailCard :assignment="assignment" />
            </div>

            <!-- 続きジョブ シリーズパネル -->
            <div v-if="chainSeries && chainSeries.items && chainSeries.items.length > 1"
                 class="overflow-hidden rounded-lg border border-orange-200 bg-orange-50 shadow-sm">
                <div class="border-b border-orange-200 bg-orange-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-orange-800">↩ 続きジョブ シリーズ（{{ chainSeries.items.length }}件）</h3>
                </div>
                <div class="divide-y divide-orange-100 px-5 py-2">
                    <div v-for="(item, idx) in chainSeries.items" :key="item.assignment_id"
                         class="flex items-start gap-3 py-2.5"
                         :class="item.is_current ? 'bg-orange-100 -mx-5 px-5' : ''">
                        <!-- 番号 -->
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="item.is_current ? 'bg-orange-600 text-white' : 'bg-orange-200 text-orange-700'">
                            {{ idx + 1 }}
                        </span>
                        <!-- タイトルと日付 -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-medium text-gray-900">{{ item.title }}</span>
                                <span v-if="item.is_current"
                                      class="rounded-full bg-orange-600 px-1.5 py-0.5 text-xs text-white">現在</span>
                                <span v-if="item.completed"
                                      class="rounded-full bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-800">完了</span>
                            </div>
                            <!-- イベント一覧 -->
                            <div v-if="item.events && item.events.length" class="mt-1 space-y-0.5">
                                <div v-for="ev in item.events" :key="ev.id"
                                     class="text-xs text-gray-500">
                                    <a :href="route('events.show', ev.id)"
                                       class="text-blue-600 hover:underline">
                                        {{ formatEvDate(ev.date) }}
                                    </a>
                                    {{ ev.start }}〜{{ ev.end }}
                                    <span class="ml-1 font-medium text-gray-700">{{ formatMins(ev.minutes) }}</span>
                                </div>
                            </div>
                            <div v-else class="mt-0.5 text-xs text-gray-400">（予定未セット）</div>
                        </div>
                        <!-- 作業時間 -->
                        <div class="shrink-0 text-right">
                            <span class="text-sm font-bold" :class="item.minutes > 0 ? 'text-indigo-700' : 'text-gray-300'">
                                {{ item.minutes > 0 ? formatMins(item.minutes) : '-' }}
                            </span>
                        </div>
                    </div>
                    <!-- シリーズ合計 -->
                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-sm font-semibold text-orange-800">シリーズ合計</span>
                        <span class="text-base font-bold text-orange-800">{{ formatMins(chainSeries.total_minutes) }}</span>
                    </div>
                </div>
            </div>

            <!-- ファイル一覧（file_info がある場合） -->
            <div v-if="assignment?.file_info" class="mt-4">
                <FileInfoDisplay :fileInfo="assignment.file_info" />
            </div>

        </div>

        <!-- 校正依頼モーダル -->
        <ProofRequestModal
            :show="showProofModal"
            :initial-title="assignment?.title || event.title || ''"
            :project-job-assignment-id="event.project_job_assignment_id || null"
            :project-job-id="assignment?.project_job_id || null"
            @close="showProofModal = false"
        />

    </AppLayout>
</template>
