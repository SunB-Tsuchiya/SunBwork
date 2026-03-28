<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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

// leader_permissions が null（未設定）の場合は全権限オン扱い
const perm = computed(() => page.props.auth?.leaderPermissions ?? null);
const can = (key) => perm.value === null || perm.value[key] === true;
</script>

<template>
    <div class="mb-6">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
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
                :href="route('leader.leader_permissions.index')"
                :class="tab('leader_permissions')"
            >
                Leader権限管理
            </Link>
        </nav>
    </div>
</template>
