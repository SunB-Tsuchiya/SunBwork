<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

const page = usePage();
const canAccessSalesAnalysis = computed(() => page.props.auth?.canAccessSalesAnalysis ?? false);
const canAccessWorkRecordTrial = computed(() => page.props.auth?.canAccessWorkRecordTrial ?? false);
const canAccessWorkHubPreview = computed(() => page.props.auth?.user?.user_role === 'superadmin'
    && Boolean(page.props.userWorkHub?.enabled) && page.props.auth?.companyType !== 'general');

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

// The original routes and access checks are shared by desktop and mobile.
const entries = computed(() => [
    { key: 'dashboard', group: null, label: 'ダッシュボード', route: 'superadmin.dashboard', matches: ['superadmin.dashboard'] },
    { key: 'work_hub_preview', group: null, label: '仕事・案件（試用）', route: 'user.myjobbox.index', matches: ['user.myjobbox.'], visible: canAccessWorkHubPreview.value },

    { key: 'companies', group: 'people', label: '会社の追加と管理', route: 'superadmin.companies.index', matches: ['superadmin.companies.'] },
    { key: 'company_groups', group: 'people', label: 'グループ会社設定', route: 'super-admin.company-groups.index', matches: ['super-admin.company-groups.'] },
    { key: 'membership', group: 'people', label: '会社・部署設定', route: 'superadmin.membership.edit', matches: ['superadmin.membership.'] },
    { key: 'all_users', group: 'people', label: 'ユーザー管理', route: 'superadmin.users.index', matches: ['superadmin.users.'] },
    { key: 'users', group: 'people', label: 'Adminユーザー管理', route: 'superadmin.adminusers.index', matches: ['superadmin.adminusers.'] },
    { key: 'position_titles', group: 'people', label: '役職称号管理', route: 'superadmin.position_titles.index', matches: ['superadmin.position_titles.'] },

    { key: 'broad_projects', group: 'records', label: '大まかな案件', route: 'superadmin.broad_projects.index', matches: ['superadmin.broad_projects.'], visible: canAccessWorkRecordTrial.value },
    { key: 'workload', group: 'records', label: '作業量分析', route: 'superadmin.workload_analyzer.index', matches: ['superadmin.workload_analyzer.'], newTab: true },
    { key: 'sales_analysis', group: 'records', label: '売上分析', route: 'superadmin.sales_analysis.dashboard', matches: ['superadmin.sales_analysis.'], visible: canAccessSalesAnalysis.value },

    { key: 'admin_permissions', group: 'permissions', label: 'Admin権限管理', route: 'superadmin.admin_permissions.index', matches: ['superadmin.admin_permissions.'] },
    { key: 'sales_analysis_permissions', group: 'permissions', label: '売上分析 利用許可設定', route: 'superadmin.sales_analysis_permissions.index', matches: ['superadmin.sales_analysis_permissions.'] },
    { key: 'applicants', group: 'permissions', label: '申請者管理', route: 'admin.applicants.index', matches: ['admin.applicants.'] },

    { key: 'ai', group: 'settings', label: 'AI設定', route: 'superadmin.ai.index', matches: ['superadmin.ai.'] },
    { key: 'scripts', group: 'settings', label: 'スクリプト管理', route: 'superadmin.scripts.index', matches: ['superadmin.scripts.'] },
    { key: 'demo_pages', group: 'settings', label: 'デモページ管理', route: 'superadmin.demo_pages.index', matches: ['superadmin.demo_pages.'] },
    { key: 'debug', group: 'settings', label: 'APIデバッグページ', route: 'debug.api', matches: ['debug.api'] },
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
    ...entries.value.filter((entry) => entry.group === null),
    ...groups.map((group) => {
        const first = entries.value.find((entry) => entry.group === group.key);
        return first ? { key: group.key, label: group.label, href: first.href, newTab: first.newTab } : null;
    }).filter(Boolean),
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
        ? 'bg-yellow-100 text-yellow-700'
        : 'border border-yellow-200 text-yellow-600 hover:bg-yellow-50 hover:text-yellow-800',
];

function onMobileSelect(event) {
    const selected = topTabs.value.find((item) => item.href === event.target.value);
    if (selected?.newTab) window.open(selected.href, '_blank', 'noopener,noreferrer');
    else if (selected?.href) router.get(selected.href);
}
</script>

<template>
    <div class="mb-4">
        <div class="sm:hidden">
            <select :value="topTabs.find((item) => item.key === activeTop)?.href ?? ''"
                @change="onMobileSelect"
                class="w-full rounded-md border border-yellow-300 bg-white px-3 py-2 text-sm text-yellow-700 shadow-sm focus:border-yellow-500 focus:outline-none focus:ring-1 focus:ring-yellow-500"
                aria-label="Super Admin画面のメニュー">
                <option value="">— ページを選択 —</option>
                <option v-for="item in topTabs" :key="item.key" :value="item.href">{{ item.label }}</option>
            </select>
        </div>

        <nav class="hidden flex-wrap gap-2 sm:flex" aria-label="Super Admin画面のメニュー">
            <template v-for="item in topTabs" :key="item.key">
                <a v-if="item.newTab" :href="item.href" target="_blank" rel="noopener noreferrer"
                    :class="topClass(item.key)" :aria-current="activeTop === item.key ? 'page' : undefined">{{ item.label }}</a>
                <Link v-else :href="item.href" :class="topClass(item.key)"
                    :aria-current="activeTop === item.key ? 'page' : undefined">{{ item.label }}</Link>
            </template>
        </nav>

        <nav v-if="activeChildren.length" class="mt-3 flex gap-1 overflow-x-auto rounded bg-white px-3 shadow"
            :aria-label="`${topTabs.find((item) => item.key === activeTop)?.label ?? ''}のメニュー`">
            <template v-for="entry in activeChildren" :key="entry.key">
                <a v-if="entry.newTab" :href="entry.href" target="_blank" rel="noopener noreferrer"
                    :aria-current="currentEntry?.key === entry.key ? 'page' : undefined"
                    class="shrink-0 border-b-2 px-3 py-3 text-sm"
                    :class="currentEntry?.key === entry.key
                        ? 'border-yellow-600 font-semibold text-yellow-700'
                        : 'border-transparent text-gray-600 hover:border-yellow-200 hover:text-yellow-700'">{{ entry.label }}</a>
                <Link v-else :href="entry.href" :aria-current="currentEntry?.key === entry.key ? 'page' : undefined"
                    class="shrink-0 border-b-2 px-3 py-3 text-sm"
                    :class="currentEntry?.key === entry.key
                        ? 'border-yellow-600 font-semibold text-yellow-700'
                        : 'border-transparent text-gray-600 hover:border-yellow-200 hover:text-yellow-700'">{{ entry.label }}</Link>
            </template>
        </nav>
    </div>
</template>
