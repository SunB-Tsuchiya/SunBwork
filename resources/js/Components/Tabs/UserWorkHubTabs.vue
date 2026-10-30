<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ active: { type: String, required: true } });
const page = usePage();
const count = computed(() => Math.max(0, Number(page.props.userWorkHub?.unregisteredRequests) || 0));
const tabs = computed(() => [
    { key: 'myjob', name: 'user.myjobbox.index', label: 'マイジョブ' },
    { key: 'jobbox', name: 'user.jobbox.index', label: '依頼されたジョブ' },
    { key: 'broad_projects', name: 'user.work.broad_projects.index', label: '大まかな案件' },
    { key: 'project_jobs', name: 'user.project_jobs.index', label: '案件確認' },
    { key: 'proof_status', name: 'user.proof.status', label: '校正状況', visible: page.props.auth?.companyType === 'sunbrain' },
].filter(tab => tab.visible !== false));
</script>

<template>
    <nav aria-label="仕事・案件" class="flex gap-1 overflow-x-auto rounded bg-white px-3 shadow">
        <Link v-for="tab in tabs" :key="tab.key" :href="route(tab.name)" :aria-current="active === tab.key ? 'page' : undefined"
            class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm"
            :class="active === tab.key ? 'border-blue-600 font-semibold text-blue-700' : 'border-transparent text-gray-600 hover:border-blue-200 hover:text-blue-700'">
            {{ tab.label }}
            <span v-if="tab.key === 'jobbox' && count > 0" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800" :aria-label="`未登録の依頼 ${count}件`" title="未登録の依頼（全期間・完了済みを除く）">{{ count }}</span>
        </Link>
    </nav>
</template>
