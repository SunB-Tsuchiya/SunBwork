<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.leaderPermissions ?? null);
const can = (key) => permissions.value === null || permissions.value[key] === true;
const isDepartmentLeader = computed(() => page.props.auth?.user?.isDepartmentLeader === true);
const isAdminOrAbove = computed(() => ['admin', 'superadmin'].includes(page.props.auth?.user?.user_role));

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

// Keep original routes and visibility rules. One list drives desktop and mobile.
const entries = computed(() => [
    { key: 'dashboard', group: null, label: 'ダッシュボード', route: 'leader.dashboard', matches: ['leader.dashboard'] },
    { key: 'diaries', group: null, label: '日報閲覧', route: 'leader.diaryinteractions.index', matches: ['leader.diaryinteractions.'], visible: can('diary_management') },

    { key: 'user_management', group: 'people', label: 'ユーザー管理', route: 'leader.user_management.index', matches: ['leader.user_management.'], visible: isDepartmentLeader.value },
    { key: 'teams', group: 'people', label: 'チーム管理', route: 'leader.teams.index', matches: ['leader.teams.'] },
    { key: 'clients', group: 'people', label: 'クライアント管理', route: 'leader.clients.index', matches: ['leader.clients.'], visible: can('client_management') },
    { key: 'dispatch', group: 'people', label: '派遣管理', route: 'leader.dispatch_management.index', matches: ['leader.dispatch_management.'], visible: can('dispatch_management') },
    { key: 'sales_reps', group: 'people', label: '営業担当管理', route: 'leader.sales_reps.index', matches: ['leader.sales_reps.'] },

    { key: 'broad_projects', group: 'records', label: '大まかな案件', route: 'leader.broad_projects.index', matches: ['leader.broad_projects.'], visible: Boolean(page.props.auth?.canManageBroadProjects) },
    { key: 'project_jobs', group: 'records', label: '案件総覧', route: 'leader.project_jobs.index', matches: ['leader.project_jobs.'], visible: (isDepartmentLeader.value || isAdminOrAbove.value) && can('project_job_overview') },
    { key: 'work_records', group: 'records', label: '勤務時間閲覧', route: 'leader.work_records.index', matches: ['leader.work_records.'], visible: can('work_record_management') },
    { key: 'workload', group: 'records', label: '作業量分析', route: 'leader.workload_analyzer.index', matches: ['leader.workload_analyzer.'], visible: can('workload_analysis') },

    { key: 'leader_permissions', group: 'permissions', label: 'Leader権限', route: 'leader.leader_permissions.index', matches: ['leader.leader_permissions.'] },

    { key: 'workload_setting', group: 'settings', label: '作業項目', route: 'workload_setting.index', matches: ['workload_setting.', 'leader.workload_setting.', 'leader.department_field_config.'], visible: can('workload_setting') },
    { key: 'meeting_definitions', group: 'settings', label: '会議', route: 'leader.meeting_definitions.index', matches: ['leader.meeting_definitions.'] },
    { key: 'presence_board_settings', group: 'settings', label: '在席ボード', route: 'leader.presence.board_settings', matches: ['leader.presence.'] },

    { key: 'announcements', group: null, label: 'お知らせ通知', route: 'leader.announcements.index', matches: ['leader.announcements.'] },
].filter((entry) => entry.visible !== false)
    .map((entry) => ({ ...entry, href: tryRoute(entry.route) }))
    .filter((entry) => entry.href));

const groups = [
    { key: 'people', label: '人・組織' },
    { key: 'records', label: '記録・分析' },
    { key: 'permissions', label: '権限管理' },
    { key: 'settings', label: '各種設定' },
];

const topTabs = computed(() => [
    ...entries.value.filter((entry) => entry.group === null && entry.key !== 'announcements'),
    ...groups.map((group) => {
        const first = entries.value.find((entry) => entry.group === group.key);
        return first ? { key: group.key, label: group.label, href: first.href } : null;
    }).filter(Boolean),
    ...entries.value.filter((entry) => entry.key === 'announcements'),
]);

const currentEntry = computed(() => {
    let current = '';
    try { current = route().current() ?? ''; } catch { /* Use explicit page key. */ }
    return entries.value.find((entry) => entry.matches.some((prefix) => current === prefix || current.startsWith(prefix)))
        ?? entries.value.find((entry) => entry.key === props.active)
        ?? null;
});
const activeTop = computed(() => currentEntry.value?.group ?? currentEntry.value?.key ?? '');
const activeChildren = computed(() => entries.value.filter((entry) => entry.group === activeTop.value));

const topClass = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    activeTop.value === key
        ? 'bg-orange-100 text-orange-700'
        : 'border border-orange-200 text-orange-600 hover:bg-orange-50 hover:text-orange-800',
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
                class="w-full rounded-md border border-orange-300 bg-white px-3 py-2 text-sm text-orange-700 shadow-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
                aria-label="Leader画面のメニュー">
                <option value="">— ページを選択 —</option>
                <option v-for="item in topTabs" :key="item.key" :value="item.href">{{ item.label }}</option>
            </select>
        </div>

        <nav class="hidden flex-wrap gap-2 sm:flex" aria-label="Leader画面のメニュー">
            <Link v-for="item in topTabs" :key="item.key" :href="item.href" :class="topClass(item.key)"
                :aria-current="activeTop === item.key ? 'page' : undefined">{{ item.label }}</Link>
        </nav>

        <nav v-if="activeChildren.length" class="mt-3 flex gap-1 overflow-x-auto rounded bg-white px-3 shadow"
            :aria-label="`${topTabs.find((item) => item.key === activeTop)?.label ?? ''}のメニュー`">
            <Link v-for="entry in activeChildren" :key="entry.key" :href="entry.href"
                :aria-current="currentEntry?.key === entry.key ? 'page' : undefined"
                class="shrink-0 border-b-2 px-3 py-3 text-sm"
                :class="currentEntry?.key === entry.key
                    ? 'border-orange-600 font-semibold text-orange-700'
                    : 'border-transparent text-gray-600 hover:border-orange-200 hover:text-orange-700'">
                {{ entry.label }}
            </Link>
        </nav>
    </div>
</template>
