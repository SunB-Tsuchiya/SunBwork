<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

// Admin カラー: red
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-red-100 text-red-700'
        : 'border border-red-200 text-red-600 hover:bg-red-50 hover:text-red-800',
];

const page = usePage();
const perm = computed(() => page.props.auth?.adminPermissions ?? null);
const can = (key) => perm.value === null || perm.value[key] === true;
const isRepresentative = computed(() => page.props.auth?.user?.isRepresentative ?? false);

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'project_jobs', href: tryRoute('admin.project_jobs.index'), label: '案件総覧' },
    {
        key: 'companies',
        href: tryRoute('admin.companies.index'),
        label: '会社管理',
        condition: can('company_management') && typeof route === 'function' && route().has('admin.companies.index'),
    },
    { key: 'users', href: tryRoute('admin.users.index'), label: 'ユーザー管理', condition: can('user_management') },
    { key: 'teams', href: tryRoute('admin.teams.index'), label: '部署管理', condition: can('team_management') },
    { key: 'diaries', href: tryRoute('admin.diaryinteractions.index'), label: '日報管理', condition: can('diary_management') },
    { key: 'clients', href: tryRoute('admin.clients.index'), label: 'クライアント管理', condition: can('client_management') },
    { key: 'workload', href: tryRoute('admin.workload_analyzer.index'), label: '作業量分析', condition: can('workload_analysis') },
    { key: 'worktypes', href: tryRoute('admin.worktypes.index'), label: '勤務形態設定', condition: can('worktype_setting') },
    { key: 'work_records', href: tryRoute('admin.work_records.index'), label: '勤務時間管理', condition: can('work_record_management') },
    { key: 'admin_permissions', href: tryRoute('admin.admin_permissions.index'), label: 'Admin権限管理', condition: isRepresentative.value },
    { key: 'leader_permissions', href: tryRoute('admin.leader_permissions.index'), label: 'Leader権限管理' },
    { key: 'meeting_definitions', href: tryRoute('admin.meeting_definitions.index'), label: '会議設定' },
].filter(t => t.condition !== false && t.href));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-6">
        <!-- モバイル: ドロップダウン -->
        <div class="sm:hidden">
            <select
                @change="onMobileSelect"
                class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
            >
                <option value="">— ページを選択 —</option>
                <option
                    v-for="t in tabs"
                    :key="t.key"
                    :value="t.href"
                    :selected="active === t.key"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- デスクトップ: タブ -->
        <nav class="hidden sm:flex flex-wrap gap-2" aria-label="Tabs">
            <Link
                :href="route('admin.project_jobs.index')"
                :class="tab('project_jobs')"
            >
                案件総覧
            </Link>
            <Link
                v-if="can('company_management') && typeof route === 'function' && route().has('admin.companies.index')"
                :href="route('admin.companies.index')"
                :class="tab('companies')"
            >
                会社管理
            </Link>
            <Link
                v-if="can('user_management')"
                :href="route('admin.users.index')"
                :class="tab('users')"
            >
                ユーザー管理
            </Link>
            <Link
                v-if="can('team_management')"
                :href="route('admin.teams.index')"
                :class="tab('teams')"
            >
                部署管理
            </Link>
            <Link
                v-if="can('diary_management')"
                :href="route('admin.diaryinteractions.index')"
                :class="tab('diaries')"
            >
                日報管理
            </Link>
            <Link
                v-if="can('client_management')"
                :href="route('admin.clients.index')"
                :class="tab('clients')"
            >
                クライアント管理
            </Link>
            <Link
                v-if="can('workload_analysis')"
                :href="route('admin.workload_analyzer.index')"
                :class="tab('workload')"
            >
                作業量分析
            </Link>
            <Link
                v-if="can('worktype_setting')"
                :href="route('admin.worktypes.index')"
                :class="tab('worktypes')"
            >
                勤務形態設定
            </Link>
            <Link
                v-if="can('work_record_management')"
                :href="route('admin.work_records.index')"
                :class="tab('work_records')"
            >
                勤務時間管理
            </Link>
            <Link
                v-if="isRepresentative"
                :href="route('admin.admin_permissions.index')"
                :class="tab('admin_permissions')"
            >
                Admin権限管理
            </Link>
            <Link
                :href="route('admin.leader_permissions.index')"
                :class="tab('leader_permissions')"
            >
                Leader権限管理
            </Link>
            <Link
                :href="route('admin.meeting_definitions.index')"
                :class="tab('meeting_definitions')"
            >
                会議設定
            </Link>
        </nav>
    </div>
</template>
