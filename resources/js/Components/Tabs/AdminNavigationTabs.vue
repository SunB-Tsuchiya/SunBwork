<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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

// admin_permissions が null（未設定）の場合は全権限オン扱い
const perm = computed(() => page.props.auth?.adminPermissions ?? null);
const can = (key) => perm.value === null || perm.value[key] === true;
</script>

<template>
    <div class="mb-6">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
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
                チーム管理
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
        </nav>
    </div>
</template>
