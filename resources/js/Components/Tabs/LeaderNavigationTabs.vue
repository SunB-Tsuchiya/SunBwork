<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

// Leader カラー: orange
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-orange-100 text-orange-700'
        : 'border border-orange-200 text-orange-600 hover:bg-orange-50 hover:text-orange-800',
];

const page = usePage();
const perm = computed(() => page.props.auth?.leaderPermissions ?? null);
const can = (key) => perm.value === null || perm.value[key] === true;
const isDepartmentLeader = computed(() => page.props.auth?.user?.isDepartmentLeader === true);
const isAdminOrAbove = computed(() => ['admin', 'superadmin'].includes(page.props.auth?.user?.user_role));

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'user_management', href: tryRoute('leader.user_management.index'), label: 'ユーザー管理', condition: isDepartmentLeader.value },
    {
        key: 'project_jobs',
        href: tryRoute('leader.project_jobs.index'),
        label: '案件総覧',
        condition: (isDepartmentLeader.value || isAdminOrAbove.value) && can('project_job_overview'),
    },
    { key: 'teams', href: tryRoute('leader.teams.index'), label: 'チーム管理' },
    { key: 'clients', href: tryRoute('leader.clients.index'), label: 'クライアント管理', condition: can('client_management') },
    { key: 'diaries', href: tryRoute('leader.diaryinteractions.index'), label: '日報管理', condition: can('diary_management') },
    { key: 'workload', href: tryRoute('leader.workload_analyzer.index'), label: '作業量分析', condition: can('workload_analysis') },
    { key: 'workload_setting', href: tryRoute('workload_setting.index'), label: '作業項目設定', condition: can('workload_setting') },
    { key: 'work_records', href: tryRoute('leader.work_records.index'), label: '勤務時間管理', condition: can('work_record_management') },
    { key: 'dispatch', href: tryRoute('leader.dispatch_management.index'), label: '派遣管理', condition: can('dispatch_management') },
    { key: 'leader_permissions', href: tryRoute('leader.leader_permissions.index'), label: 'Leader権限管理' },
    { key: 'meeting_definitions', href: tryRoute('leader.meeting_definitions.index'), label: '会議設定' },
    { key: 'presence_board_settings', href: tryRoute('presence.board_settings'), label: '在席ボード管理' },
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
                class="w-full rounded-md border border-orange-300 bg-white px-3 py-2 text-sm text-orange-700 shadow-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
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
                v-if="isDepartmentLeader"
                :href="route('leader.user_management.index')"
                :class="tab('user_management')"
            >
                ユーザー管理
            </Link>
            <Link
                v-if="(isDepartmentLeader || isAdminOrAbove) && can('project_job_overview')"
                :href="route('leader.project_jobs.index')"
                :class="tab('project_jobs')"
            >
                案件総覧
            </Link>
            <Link
                :href="route('leader.teams.index')"
                :class="tab('teams')"
            >
                チーム管理
            </Link>
            <Link
                v-if="can('client_management')"
                :href="route('leader.clients.index')"
                :class="tab('clients')"
            >
                クライアント管理
            </Link>
            <Link
                v-if="can('diary_management')"
                :href="route('leader.diaryinteractions.index')"
                :class="tab('diaries')"
            >
                日報管理
            </Link>
            <Link
                v-if="can('workload_analysis')"
                :href="route('leader.workload_analyzer.index')"
                :class="tab('workload')"
            >
                作業量分析
            </Link>
            <Link
                v-if="can('workload_setting')"
                :href="route('workload_setting.index')"
                :class="tab('workload_setting')"
            >
                作業項目設定
            </Link>
            <Link
                v-if="can('work_record_management')"
                :href="route('leader.work_records.index')"
                :class="tab('work_records')"
            >
                勤務時間管理
            </Link>
            <Link
                v-if="can('dispatch_management')"
                :href="route('leader.dispatch_management.index')"
                :class="tab('dispatch')"
            >
                派遣管理
            </Link>
            <Link
                :href="route('leader.leader_permissions.index')"
                :class="tab('leader_permissions')"
            >
                Leader権限管理
            </Link>
            <Link
                :href="route('leader.meeting_definitions.index')"
                :class="tab('meeting_definitions')"
            >
                会議設定
            </Link>
            <Link
                :href="route('presence.board_settings')"
                :class="tab('presence_board_settings')"
            >
                在席ボード管理
            </Link>
        </nav>
    </div>
</template>
