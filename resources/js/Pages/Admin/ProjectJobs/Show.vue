<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    job:              { type: Object, required: true },
    assignmentEvents: { type: Array,  default: () => [] },
    roleConfig:       { type: Array,  default: () => [] },
});

const activeTab = ref('overview');
const tabs = [
    { key: 'overview',  label: '概要・メンバー' },
    { key: 'analysis',  label: '作業分析' },
];

const subCoordinators = computed(() => props.job.coordinators ?? []);

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try { return String(dateStr).split('T')[0].split(' ')[0]; } catch { return String(dateStr); }
}

const VIEW_MODES = [
    { key: 'date',  label: '日付順'   },
    { key: 'stage', label: 'ステージ順' },
];

const groupBy = reactive(
    Object.fromEntries((props.roleConfig ?? []).map((r) => [r.key, 'date']))
);

const ROLE_STYLES = {
    coordinator:  { card: 'border-indigo-200 bg-indigo-50',  label: 'text-indigo-600', total: 'text-indigo-800', sub: 'text-indigo-400', badge: 'bg-indigo-100 text-indigo-700' },
    production:   { card: 'border-blue-200 bg-blue-50',      label: 'text-blue-600',   total: 'text-blue-800',   sub: 'text-blue-400',   badge: 'bg-blue-100 text-blue-700'   },
    proofreading: { card: 'border-amber-200 bg-amber-50',    label: 'text-amber-600',  total: 'text-amber-800',  sub: 'text-amber-400',  badge: 'bg-amber-100 text-amber-700' },
    other:        { card: 'border-gray-200 bg-gray-50',      label: 'text-gray-500',   total: 'text-gray-700',   sub: 'text-gray-400',   badge: 'bg-gray-100 text-gray-600'   },
};

function roleStyle(key) { return ROLE_STYLES[key] ?? ROLE_STYLES.other; }

const activeRoles = computed(() =>
    (props.roleConfig ?? []).filter((r) => r.key !== 'other' || eventsByRole(r.key).length > 0)
);

function eventsByRole(roleKey) {
    return (props.assignmentEvents ?? []).filter((e) => e.role_category === roleKey);
}

function totalMinutesByRole(roleKey) {
    return eventsByRole(roleKey).reduce((sum, e) => sum + actualMinutes(e), 0);
}

// actual_minutes（昼休憩・重複中断除算済み）を優先。なければ生計算
function actualMinutes(e) {
    if (typeof e.actual_minutes === 'number') return e.actual_minutes;
    return eventMinutes(e);
}

function eventMinutes(e) {
    if (!e.start || !e.end) return 0;
    try {
        const diff = Math.round((new Date(e.end) - new Date(e.start)) / 60000);
        return diff > 0 ? diff : 0;
    } catch { return 0; }
}

function groupedEvents(roleKey) {
    const events = eventsByRole(roleKey);
    const mode   = groupBy[roleKey] ?? 'date';
    return mode === 'stage' ? groupByStage(events) : groupByDate(events);
}

function groupByDate(events) {
    const map = new Map();
    for (const e of events) {
        const key = e.date || 'nodate';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(e);
    }
    const sorted = [...map.entries()].sort(([a], [b]) => {
        if (a === 'nodate') return 1;
        if (b === 'nodate') return -1;
        return b.localeCompare(a);
    });
    return sorted.map(([key, items]) => {
        items.sort((a, b) => (a.start || '').localeCompare(b.start || ''));
        return { key, label: formatDateLabel(key), items, totalMinutes: items.reduce((s, e) => s + actualMinutes(e), 0) };
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
    const sorted = [...map.entries()].sort(([, a], [, b]) => a.sort - b.sort);
    return sorted.map(([key, g]) => {
        g.items.sort((a, b) => (a.start || '').localeCompare(b.start || ''));
        return { key, label: g.name, items: g.items, totalMinutes: g.items.reduce((s, e) => s + actualMinutes(e), 0) };
    });
}

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
        const m = String(v).match(/(\d{2}:\d{2})/);
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
</script>

<template>
    <AppLayout :title="job.title || '案件詳細'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.project_jobs.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← 案件総覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">案件詳細</h2>
            </div>
        </template>

        <template #tabs>
            <AdminNavigationTabs active="project_jobs" />
        </template>

        <!-- スティッキーヘッダー -->
        <div class="sticky top-0 z-20 rounded-t bg-white px-6 pt-6 pb-0 shadow-md">

            <div class="mb-4">
                <p class="text-sm font-medium text-gray-400">
                    {{ job.client?.name || 'クライアント未設定' }}
                </p>
                <h1 class="mt-0.5 text-2xl font-bold text-gray-900">
                    {{ job.title || job.name || '（案件名なし）' }}
                </h1>
                <p class="mt-1 text-xs text-gray-500">
                    <span v-if="job.jobcode">伝票番号: {{ job.jobcode }}　</span>
                    <span v-if="job.user?.name">リーダー: {{ job.user.name }}</span>
                </p>
                <p v-if="subCoordinators.length > 0" class="mt-0.5 text-xs text-gray-400">
                    サブリーダー: {{ subCoordinators.map((c) => c.name).join('、') }}
                </p>
                <p class="mt-1">
                    <span
                        :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    >{{ job.completed ? '完了' : '進行中' }}</span>
                </p>
            </div>

            <!-- タブバー -->
            <div class="mt-2 flex gap-1 border-b border-gray-200">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="[
                        'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                        activeTab === tab.key
                            ? 'border-red-500 text-red-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    ]"
                    @click="activeTab = tab.key"
                >{{ tab.label }}</button>
            </div>
        </div>

        <!-- タブコンテンツ -->
        <div class="rounded-b bg-white px-6 pb-6 shadow-md">

            <!-- 概要・メンバータブ -->
            <section v-show="activeTab === 'overview'" class="py-5 space-y-5">

                <div
                    v-if="job.detail"
                    class="whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
                >{{ job.detail }}</div>

                <div>
                    <h3 class="mb-2 font-semibold text-gray-800">基本情報</h3>
                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">登録日</dt>
                            <dd class="mt-0.5 text-gray-900">{{ formatDate(job.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">担当 Coordinator</dt>
                            <dd class="mt-0.5 text-gray-900">{{ job.user?.name || '-' }}</dd>
                        </div>
                        <div v-if="job.size?.name">
                            <dt class="text-xs font-medium text-gray-500">版型</dt>
                            <dd class="mt-0.5 text-gray-900">{{ job.size.name }}</dd>
                        </div>
                        <div v-if="job.page_count">
                            <dt class="text-xs font-medium text-gray-500">総ページ数</dt>
                            <dd class="mt-0.5 text-gray-900">{{ job.page_count }} ページ</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="mb-3 font-semibold text-gray-800">メンバー</h3>
                    <div class="space-y-2">
                        <div v-if="job.user" class="flex items-center gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-yellow-700">リーダー</span>
                            <span class="flex items-center gap-1.5 rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm font-medium text-gray-800">
                                <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                                {{ job.user.name }}
                            </span>
                        </div>
                        <div v-if="subCoordinators.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-orange-700">サブリーダー</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="c in subCoordinators"
                                    :key="c.id"
                                    class="flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-sm font-medium text-gray-800"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-orange-400"></span>
                                    {{ c.name }}
                                </span>
                            </div>
                        </div>
                        <div v-if="job.team_members && job.team_members.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-green-700">User</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="tm in job.team_members"
                                    :key="tm.id"
                                    class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-sm font-medium text-gray-800"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-green-400"></span>
                                    {{ tm.user?.name || '-' }}
                                </span>
                            </div>
                        </div>
                        <p v-if="!job.user && !subCoordinators.length && !(job.team_members?.length)" class="text-sm text-gray-400">メンバー未登録</p>
                    </div>
                </div>
            </section>

            <!-- 作業分析タブ -->
            <section v-show="activeTab === 'analysis'" class="py-5">

                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div
                        v-for="role in activeRoles"
                        :key="role.key"
                        :class="roleStyle(role.key).card"
                        class="rounded-lg border px-4 py-3"
                    >
                        <p :class="roleStyle(role.key).label" class="text-xs font-semibold uppercase tracking-wide">{{ role.label }}</p>
                        <p :class="roleStyle(role.key).total" class="mt-1 text-2xl font-bold">{{ formatMin(totalMinutesByRole(role.key)) }}</p>
                        <p :class="roleStyle(role.key).sub" class="mt-0.5 text-xs">{{ eventsByRole(role.key).length }} 件</p>
                    </div>
                </div>

                <p v-if="activeRoles.length === 0" class="text-sm text-gray-400">作業記録はまだありません。</p>

                <div class="divide-y divide-gray-100">
                    <div v-for="role in activeRoles" :key="role.key" class="py-6">
                        <div class="mb-3 flex flex-wrap items-center gap-3">
                            <span :class="roleStyle(role.key).badge" class="rounded-full px-3 py-1 text-sm font-semibold">{{ role.label }}</span>
                            <span class="text-sm font-medium text-gray-600">
                                合計: <span class="font-bold text-gray-900">{{ formatMin(totalMinutesByRole(role.key)) }}</span>
                            </span>
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

                        <p v-if="eventsByRole(role.key).length === 0" class="text-sm text-gray-400">この役割の作業記録はありません。</p>

                        <div v-else class="space-y-4 overflow-x-auto">
                            <div v-for="group in groupedEvents(role.key)" :key="group.key">
                                <div class="flex items-center justify-between rounded bg-gray-50 px-3 py-1.5">
                                    <span class="text-sm font-semibold text-gray-700">
                                        {{ group.label }}
                                        <span class="ml-1.5 text-xs font-normal text-gray-400">{{ group.items.length }} 件</span>
                                    </span>
                                    <span class="flex items-center gap-3 text-xs font-medium text-gray-500">
                                        <span v-if="group.items.reduce((s, e) => s + (e.lunch_minutes ?? 0), 0) > 0" class="text-orange-500">
                                            昼休憩: −{{ formatMin(group.items.reduce((s, e) => s + (e.lunch_minutes ?? 0), 0)) }}
                                        </span>
                                        <span v-if="group.items.reduce((s, e) => s + (e.interruption_minutes ?? 0), 0) > 0" class="text-red-400">
                                            重複・中断: −{{ formatMin(group.items.reduce((s, e) => s + (e.interruption_minutes ?? 0), 0)) }}
                                        </span>
                                        小計: <span class="font-bold text-gray-700">{{ formatMin(group.totalMinutes) }}</span>
                                    </span>
                                </div>
                                <table class="min-w-full divide-y divide-gray-100 border text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <template v-if="groupBy[role.key] === 'date'">
                                                <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">担当者</th>
                                                <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">ステージ</th>
                                            </template>
                                            <template v-else>
                                                <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">日付</th>
                                                <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">担当者</th>
                                            </template>
                                            <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">開始</th>
                                            <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">終了</th>
                                            <th class="border-b px-3 py-2 text-right text-xs font-medium text-gray-500">生時間</th>
                                            <th class="border-b px-3 py-2 text-right text-xs font-medium text-orange-500">昼休憩</th>
                                            <th class="border-b px-3 py-2 text-right text-xs font-medium text-red-400">重複・中断</th>
                                            <th class="border-b px-3 py-2 text-right text-xs font-medium text-gray-700">実作業時間</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr v-for="(ev, idx) in group.items" :key="`${group.key}-${idx}`" class="hover:bg-gray-50">
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
                                            <td class="px-3 py-2 text-right text-gray-500">{{ calcDuration(ev.start, ev.end) }}</td>
                                            <td class="px-3 py-2 text-right" :class="(ev.lunch_minutes ?? 0) > 0 ? 'text-orange-500 font-medium' : 'text-gray-300'">
                                                {{ (ev.lunch_minutes ?? 0) > 0 ? '−' + formatMin(ev.lunch_minutes) : '−' }}
                                            </td>
                                            <td class="px-3 py-2 text-right" :class="(ev.interruption_minutes ?? 0) > 0 ? 'text-red-400 font-medium' : 'text-gray-300'">
                                                {{ (ev.interruption_minutes ?? 0) > 0 ? '−' + formatMin(ev.interruption_minutes) : '−' }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium text-gray-800">{{ formatMin(actualMinutes(ev)) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </AppLayout>
</template>
