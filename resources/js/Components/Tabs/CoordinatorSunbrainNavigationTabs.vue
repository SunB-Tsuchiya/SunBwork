<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
    projectJob: { type: Object, default: null },
});

const page = usePage();

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

// Existing destinations and visibility rules drive both screen sizes.
const entries = computed(() => [
    { key: 'dashboard', group: null, label: 'ダッシュボード', route: 'coordinator.dashboard' },
    { key: 'diaries', group: null, label: '日報閲覧', route: 'diary_manager.diaryinteractions.index', visible: page.props.auth?.isDiaryManager === true },

    { key: 'projects', group: 'projects', label: '案件一覧', route: 'coordinator.project_jobs.index', prefix: 'coordinator.project_jobs.' },
    { key: 'broad_projects', group: 'projects', label: '大まかな案件の整理', route: 'coordinator.broad_projects.index', prefix: 'coordinator.broad_projects.', visible: page.props.auth?.canManageCoordinatorBroadProjects === true },
    { key: 'jobs', group: 'projects', label: 'ジョブ一覧', route: 'coordinator.jobbox' },
    { key: 'calendar', group: 'projects', label: '案件カレンダー', route: 'coordinator.project_jobs.calendar' },
    { key: 'progress_sheet_list', group: 'projects', label: '進行表一覧', route: 'coordinator.progress_sheet_list.index', prefix: 'coordinator.progress_sheet_list.' },
    { key: 'workflow_sheet_list', group: 'projects', label: '管理シート一覧', route: 'coordinator.workflow_sheet_list.index', prefix: 'coordinator.workflow_sheet_list.' },
    { key: 'mginbon', group: 'projects', label: '銀本進行', route: 'coordinator.mginbon.index', prefix: 'coordinator.mginbon.', newTab: true },

    { key: 'clients', group: 'partners', label: 'クライアント管理', route: 'coordinator.clients.index', prefix: 'coordinator.clients.' },
    { key: 'subcontractors', group: 'partners', label: '外注先管理', route: 'coordinator.subcontractors.index', prefix: 'coordinator.subcontractors.' },
    { key: 'sales_reps', group: 'partners', label: '営業担当管理', route: 'coordinator.sales_reps.index', prefix: 'coordinator.sales_reps.' },

    { key: 'settings', group: 'settings', label: '設定', route: 'coordinator.settings.index', prefix: 'coordinator.settings.' },
    { key: 'ghost_users', group: 'settings', label: 'テストユーザー', route: 'coordinator.ghost_users.index', prefix: 'coordinator.ghost_users.' },
].filter((entry) => entry.visible !== false)
    .map((entry) => ({ ...entry, href: tryRoute(entry.route) }))
    .filter((entry) => entry.href));

const groups = [
    { key: 'projects', label: '案件・進行' },
    { key: 'partners', label: '取引先・担当者' },
    { key: 'settings', label: '管理・設定' },
];

const topTabs = computed(() => [
    ...entries.value.filter((entry) => entry.group === null),
    ...groups.map((group) => {
        const first = entries.value.find((entry) => entry.group === group.key);
        return first ? { key: group.key, label: group.label, href: first.href } : null;
    }).filter(Boolean),
]);

const currentEntry = computed(() => {
    const byActive = entries.value.find((entry) => entry.key === props.active);
    if (byActive) return byActive;

    let current = '';
    try { current = route().current() ?? ''; } catch { /* The page may not have a named route. */ }
    return entries.value.find((entry) => entry.route === current)
        ?? entries.value.find((entry) => entry.prefix && current.startsWith(entry.prefix))
        ?? null;
});

const activeTop = computed(() => {
    if (currentEntry.value) return currentEntry.value.group ?? currentEntry.value.key;
    // Retained direct URLs show their group without restoring a menu link.
    if (['operator_calendar', 'progress_report'].includes(props.active)) return 'projects';
    return '';
});
const activeChildren = computed(() => entries.value.filter((entry) => entry.group === activeTop.value));

const topClass = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    activeTop.value === key
        ? 'bg-green-100 text-green-700'
        : 'border border-green-200 text-green-600 hover:bg-green-50 hover:text-green-800',
];

function onMobileSelect(event) {
    if (event.target.value) router.get(event.target.value);
}
</script>

<template>
    <div class="mb-4">
        <div class="sm:hidden">
            <select :value="topTabs.find((item) => item.key === activeTop)?.href ?? ''"
                @change="onMobileSelect"
                class="w-full rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                aria-label="進行管理画面のメニュー">
                <option value="">— ページを選択 —</option>
                <option v-for="item in topTabs" :key="item.key" :value="item.href">{{ item.label }}</option>
            </select>
        </div>

        <nav class="hidden flex-wrap gap-2 sm:flex" aria-label="進行管理画面のメニュー">
            <Link v-for="item in topTabs" :key="item.key" :href="item.href" :class="topClass(item.key)"
                :aria-current="activeTop === item.key ? 'page' : undefined">{{ item.label }}</Link>
        </nav>

        <nav v-if="activeChildren.length" class="mt-3 flex gap-1 overflow-x-auto rounded bg-white px-3 shadow"
            :aria-label="`${topTabs.find((item) => item.key === activeTop)?.label ?? ''}のメニュー`">
            <template v-for="entry in activeChildren" :key="entry.key">
                <a v-if="entry.newTab" :href="entry.href" target="_blank" rel="noopener"
                    class="shrink-0 border-b-2 border-transparent px-3 py-3 text-sm text-gray-600 hover:border-green-200 hover:text-green-700">
                    {{ entry.label }}
                </a>
                <Link v-else :href="entry.href"
                    :aria-current="currentEntry?.key === entry.key ? 'page' : undefined"
                    class="shrink-0 border-b-2 px-3 py-3 text-sm"
                    :class="currentEntry?.key === entry.key
                        ? 'border-green-600 font-semibold text-green-700'
                        : 'border-transparent text-gray-600 hover:border-green-200 hover:text-green-700'">
                    {{ entry.label }}
                </Link>
            </template>
        </nav>
    </div>
</template>
