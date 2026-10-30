<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/layouts/AppLayout.vue';
import useWorkloadAnalysisContext from '@/Composables/useWorkloadAnalysisContext';

const props = defineProps({
    title: { type: String, required: true },
    active: { type: String, required: true },
    date: { type: String, required: true },
    period: { type: String, default: 'day' },
    scoreVersion: { type: String, default: 'trial_time_v3' },
});
const { context, analysisRoute } = useWorkloadAnalysisContext();
const selectedDepartments = computed(() => context.value.departments.filter((d) => Number(d.company_id) === Number(context.value.companyId)));
const navigation = computed(() => ({ date: props.date, period: props.period, score_version: props.scoreVersion }));
const resultNavigation = computed(() => ({ ...navigation.value, period: props.period === 'quarter' ? 'month' : props.period }));
const colors = computed(() => ({
    superadmin: { active: 'border-yellow-500 bg-yellow-50 text-yellow-900', guide: 'border-yellow-200 bg-yellow-50 text-yellow-900' },
    admin: { active: 'border-red-500 bg-red-50 text-red-900', guide: 'border-red-200 bg-red-50 text-red-900' },
    leader: { active: 'border-orange-500 bg-orange-50 text-orange-900', guide: 'border-orange-200 bg-orange-50 text-orange-900' },
}[context.value.role] || { active: 'border-gray-500 bg-gray-50 text-gray-900', guide: 'border-gray-200 bg-gray-50 text-gray-900' }));
const tabClass = (key) => ['rounded border px-4 py-2 text-sm font-semibold', props.active === key ? colors.value.active : 'border-transparent text-gray-600 hover:bg-gray-50'];
function changeScope(key, value) {
    const params = { ...navigation.value, company_id: context.value.companyId, department_id: context.value.departmentId, [key]: value };
    if (key === 'company_id') delete params.department_id;
    const action = ['details', 'guide'].includes(props.active) ? props.active : 'results';
    router.get(route(`${context.value.routePrefix}.${action}`, params));
}
</script>

<template>
    <AppLayout :title="title" :fluid="true">
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route(context.dashboardRoute)" class="whitespace-nowrap rounded bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200">← ダッシュボードへ</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
            </div>
        </template>
        <template #headerExtras>
            <Link :href="analysisRoute('guide', navigation)" class="rounded border px-3 py-1.5 text-sm font-medium" :class="colors.guide" :aria-current="active === 'guide' ? 'page' : undefined">この分析のガイド</Link>
        </template>
        <div class="mb-3 rounded-lg border bg-white p-3 shadow-sm">
            <nav class="flex flex-wrap gap-2" aria-label="作業分析の画面">
                <Link :href="analysisRoute('results', resultNavigation)" :class="tabClass('results')">作業実績の内訳・簡易順位</Link>
                <Link :href="analysisRoute('details', navigation)" :class="tabClass('details')">詳しい作業量分析</Link>
                <a :href="analysisRoute('archive', { ym: date.slice(0, 7) })" target="_blank" rel="noopener noreferrer" :class="tabClass('archive')">旧方式アーカイブ ↗</a>
            </nav>
            <div class="mt-3 flex flex-wrap items-center gap-3 border-t pt-3 text-sm">
                <label v-if="context.companies.length">会社
                    <select :value="context.companyId" class="ml-1 rounded border-gray-300 py-1 text-sm" @change="changeScope('company_id', $event.target.value)">
                        <option v-for="company in context.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                    </select>
                </label>
                <label v-if="selectedDepartments.length">部署
                    <select :value="context.departmentId" class="ml-1 rounded border-gray-300 py-1 text-sm" @change="changeScope('department_id', $event.target.value)">
                        <option v-for="department in selectedDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </label>
                <span class="text-xs text-gray-600">{{ context.scopeLabel }}で集計します。</span>
                <span v-if="!context.companies.length || !selectedDepartments.length" class="text-xs text-gray-500">閲覧対象がありません。</span>
            </div>
        </div>
        <slot />
    </AppLayout>
</template>
