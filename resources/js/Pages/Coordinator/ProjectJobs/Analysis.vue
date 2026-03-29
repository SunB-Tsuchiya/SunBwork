<!--
  ジョブ詳細ページ（旧: ジョブ分析）
  役割ごと（進行管理 / 組版・制作 / 校正 / その他）に
  作業履歴テーブルと合計時間を表示する。
  テーブルは「日付順」「ステージ順」で切り替え可能。
-->
<template>
    <AppLayout title="ジョブ詳細">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    ジョブ詳細 — {{ job.title || '案件' }}
                </h2>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                    @click="goShow"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    案件詳細に戻る
                </button>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">

            <!-- ── 案件ヘッダー ──────────────────────────────── -->
            <div class="mb-5 border-b border-gray-100 pb-4">
                <p class="text-xs font-medium text-gray-400">
                    {{ job.client?.name || 'クライアント未設定' }}
                </p>
                <h1 class="mt-0.5 text-xl font-bold text-gray-900">{{ job.title || '（案件名なし）' }}</h1>
                <p v-if="job.jobcode" class="mt-0.5 text-xs text-gray-400">伝票番号: {{ job.jobcode }}</p>
            </div>

            <!-- ── 合計サマリー ─────────────────────────────── -->
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="role in activeRoles"
                    :key="role.key"
                    :class="roleStyle(role.key).card"
                    class="rounded-lg border px-4 py-3"
                >
                    <p :class="roleStyle(role.key).label" class="text-xs font-semibold uppercase tracking-wide">
                        {{ role.label }}
                    </p>
                    <p :class="roleStyle(role.key).total" class="mt-1 text-2xl font-bold">
                        {{ formatMin(totalMinutesByRole(role.key)) }}
                    </p>
                    <p :class="roleStyle(role.key).sub" class="mt-0.5 text-xs">
                        {{ eventsByRole(role.key).length }} 件
                    </p>
                </div>
            </div>

            <!-- ── 役割ごとセクション ──────────────────────── -->
            <div class="divide-y divide-gray-100">
                <section
                    v-for="role in activeRoles"
                    :key="role.key"
                    class="py-6"
                >
                    <!-- セクションヘッダー -->
                    <div class="mb-3 flex flex-wrap items-center gap-3">
                        <!-- 役割名バッジ -->
                        <span :class="roleStyle(role.key).badge" class="rounded-full px-3 py-1 text-sm font-semibold">
                            {{ role.label }}
                        </span>
                        <!-- 合計時間 -->
                        <span class="text-sm font-medium text-gray-600">
                            合計: <span class="font-bold text-gray-900">{{ formatMin(totalMinutesByRole(role.key)) }}</span>
                        </span>
                        <!-- グループ切り替えトグル -->
                        <div class="ml-auto flex rounded-md border border-gray-200 bg-gray-50 p-0.5">
                            <button
                                v-for="mode in VIEW_MODES"
                                :key="mode.key"
                                type="button"
                                :class="groupBy[role.key] === mode.key
                                    ? 'bg-white text-gray-900 shadow-sm font-semibold'
                                    : 'text-gray-500 hover:text-gray-700'"
                                class="rounded px-3 py-1 text-xs transition-all"
                                @click="groupBy[role.key] = mode.key"
                            >{{ mode.label }}</button>
                        </div>
                    </div>

                    <!-- データなし -->
                    <p v-if="eventsByRole(role.key).length === 0" class="text-sm text-gray-400">
                        この役割の作業記録はありません。
                    </p>

                    <!-- グループ表示 -->
                    <div v-else class="space-y-4 overflow-x-auto">
                        <div
                            v-for="group in groupedEvents(role.key)"
                            :key="group.key"
                        >
                            <!-- グループ行 -->
                            <div class="flex items-center justify-between rounded bg-gray-50 px-3 py-1.5">
                                <span class="text-sm font-semibold text-gray-700">
                                    {{ group.label }}
                                    <span class="ml-1.5 text-xs font-normal text-gray-400">{{ group.items.length }} 件</span>
                                </span>
                                <span class="text-xs font-medium text-gray-500">
                                    小計: <span class="font-bold text-gray-700">{{ formatMin(group.totalMinutes) }}</span>
                                </span>
                            </div>

                            <!-- 作業履歴テーブル -->
                            <table class="min-w-full divide-y divide-gray-100 border text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <!-- 日付順: 担当者 / ステージ / 開始 / 終了 / 作業時間 -->
                                        <template v-if="groupBy[role.key] === 'date'">
                                            <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">担当者</th>
                                            <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">ステージ</th>
                                        </template>
                                        <!-- ステージ順: 日付 / 担当者 / 開始 / 終了 / 作業時間 -->
                                        <template v-else>
                                            <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">日付</th>
                                            <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">担当者</th>
                                        </template>
                                        <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">開始</th>
                                        <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">終了</th>
                                        <th class="border-b px-3 py-2 text-right text-xs font-medium text-gray-500">作業時間</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr
                                        v-for="(ev, idx) in group.items"
                                        :key="`${group.key}-${idx}`"
                                        class="hover:bg-gray-50"
                                    >
                                        <template v-if="groupBy[role.key] === 'date'">
                                            <td class="px-3 py-2 text-gray-800">{{ ev.user_name || '-' }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ ev.stage_name || '－' }}</td>
                                        </template>
                                        <template v-else>
                                            <td class="px-3 py-2 text-gray-600">{{ formatDateShort(ev.date) }}</td>
                                            <td class="px-3 py-2 text-gray-800">{{ ev.user_name || '-' }}</td>
                                        </template>
                                        <td class="px-3 py-2 text-gray-600">{{ formatTime(ev.start) }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ formatTime(ev.end) }}</td>
                                        <td class="px-3 py-2 text-right font-medium text-gray-800">{{ calcDuration(ev.start, ev.end) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const page = usePage();
const job              = page.props.job || {};
const assignmentEvents = computed(() => Array.isArray(page.props.assignmentEvents) ? page.props.assignmentEvents : []);
const roleConfig       = computed(() => Array.isArray(page.props.roleConfig) ? page.props.roleConfig : []);

// ── グループ表示切り替え ────────────────────────────────────────────────
const VIEW_MODES = [
    { key: 'date',  label: '日付順'  },
    { key: 'stage', label: 'ステージ順' },
];

// 役割ごとの表示モード（デフォルト: 日付順）
const groupBy = reactive(
    Object.fromEntries((page.props.roleConfig || []).map((r) => [r.key, 'date']))
);

// ── 役割スタイル ───────────────────────────────────────────────────────
const ROLE_STYLES = {
    coordinator:  { card: 'border-indigo-200 bg-indigo-50',  label: 'text-indigo-600', total: 'text-indigo-800', sub: 'text-indigo-400', badge: 'bg-indigo-100 text-indigo-700' },
    production:   { card: 'border-blue-200 bg-blue-50',      label: 'text-blue-600',   total: 'text-blue-800',   sub: 'text-blue-400',   badge: 'bg-blue-100 text-blue-700'   },
    proofreading: { card: 'border-amber-200 bg-amber-50',    label: 'text-amber-600',  total: 'text-amber-800',  sub: 'text-amber-400',  badge: 'bg-amber-100 text-amber-700' },
    other:        { card: 'border-gray-200 bg-gray-50',      label: 'text-gray-500',   total: 'text-gray-700',   sub: 'text-gray-400',   badge: 'bg-gray-100 text-gray-600'   },
};

function roleStyle(key) {
    return ROLE_STYLES[key] ?? ROLE_STYLES.other;
}

// データのある役割のみ表示（ただし other は件数 > 0 のとき）
const activeRoles = computed(() =>
    roleConfig.value.filter((r) => r.key !== 'other' || eventsByRole(r.key).length > 0)
);

// ── データ集計 ─────────────────────────────────────────────────────────

function eventsByRole(roleKey) {
    return assignmentEvents.value.filter((e) => e.role_category === roleKey);
}

function totalMinutesByRole(roleKey) {
    return eventsByRole(roleKey).reduce((sum, e) => sum + eventMinutes(e), 0);
}

function eventMinutes(e) {
    if (!e.start || !e.end) return 0;
    try {
        const diff = Math.round((new Date(e.end) - new Date(e.start)) / 60000);
        return diff > 0 ? diff : 0;
    } catch { return 0; }
}

// ── グルーピング ───────────────────────────────────────────────────────

function groupedEvents(roleKey) {
    const events = eventsByRole(roleKey);
    const mode   = groupBy[roleKey] ?? 'date';

    if (mode === 'stage') return groupByStage(events);
    return groupByDate(events);
}

function groupByDate(events) {
    const map = new Map();
    for (const e of events) {
        const key = e.date || 'nodate';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(e);
    }
    // 日付降順
    const sorted = [...map.entries()].sort(([a], [b]) => {
        if (a === 'nodate') return 1;
        if (b === 'nodate') return -1;
        return b.localeCompare(a);
    });
    return sorted.map(([key, items]) => {
        // 同日内は開始時刻昇順
        items.sort((a, b) => (a.start || '').localeCompare(b.start || ''));
        return {
            key,
            label: formatDateLabel(key),
            items,
            totalMinutes: items.reduce((s, e) => s + eventMinutes(e), 0),
        };
    });
}

function groupByStage(events) {
    const map = new Map();
    for (const e of events) {
        const key  = String(e.stage_id ?? 'none');
        const name = e.stage_name ?? '（ステージ未設定）';
        if (!map.has(key)) map.set(key, { name, sort: e.stage_sort ?? 99, items: [] });
        map.get(key).items.push(e);
    }
    // ステージの sort_order 昇順
    const sorted = [...map.entries()].sort(([, a], [, b]) => a.sort - b.sort);
    return sorted.map(([key, g]) => {
        g.items.sort((a, b) => (a.start || '').localeCompare(b.start || ''));
        return {
            key,
            label: g.name,
            items: g.items,
            totalMinutes: g.items.reduce((s, e) => s + eventMinutes(e), 0),
        };
    });
}

// ── フォーマット ───────────────────────────────────────────────────────

function formatMin(minutes) {
    const m = Math.max(0, Math.round(Number(minutes) || 0));
    if (m === 0) return '0分';
    const h  = Math.floor(m / 60);
    const mm = m % 60;
    if (h > 0 && mm > 0) return `${h}時間${mm}分`;
    if (h > 0) return `${h}時間`;
    return `${mm}分`;
}

function calcDuration(start, end) {
    if (!start || !end) return '-';
    try {
        const diff = Math.round((new Date(end) - new Date(start)) / 60000);
        if (diff <= 0) return '-';
        const h  = Math.floor(diff / 60);
        const mm = diff % 60;
        if (h > 0 && mm > 0) return `${h}時間${mm}分`;
        if (h > 0) return `${h}時間`;
        return `${mm}分`;
    } catch { return '-'; }
}

function formatTime(v) {
    if (!v) return '-';
    try {
        const s = String(v);
        // "HH:MM" 形式がすでに含まれていれば抽出
        const m = s.match(/(\d{2}:\d{2})/);
        if (m) return m[1];
        const d = new Date(v);
        if (isNaN(d.getTime())) return '-';
        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    } catch { return '-'; }
}

function formatDateLabel(dateStr) {
    if (!dateStr || dateStr === 'nodate') return '日付なし';
    try {
        const d   = new Date(dateStr + 'T00:00:00');
        const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${dow}）`;
    } catch { return dateStr; }
}

function formatDateShort(dateStr) {
    if (!dateStr || dateStr === 'nodate') return '-';
    try {
        const d   = new Date(dateStr + 'T00:00:00');
        const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return `${d.getMonth() + 1}/${d.getDate()}（${dow}）`;
    } catch { return dateStr; }
}

// ── ナビゲーション ────────────────────────────────────────────────────

function goShow() {
    if (!job?.id) return;
    router.visit(route('coordinator.project_jobs.show', { projectJob: job.id }));
}
</script>
