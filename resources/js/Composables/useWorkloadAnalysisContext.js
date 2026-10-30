import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

export default function useWorkloadAnalysisContext() {
    const page = usePage();
    const context = computed(() => page.props.analysisContext || {
        routePrefix: 'superadmin.workload_analyzer', dashboardRoute: 'superadmin.dashboard',
        role: 'superadmin', companies: [], departments: [], scopeLabel: '情報出版の対象者',
    });
    const analysisRoute = (action, params = {}) => route(`${context.value.routePrefix}.${action}`, {
        ...(context.value.companyId ? { company_id: context.value.companyId } : {}),
        ...(context.value.departmentId ? { department_id: context.value.departmentId } : {}),
        ...params,
    });

    const activeClass = computed(() => ({
        superadmin: 'border-yellow-600 bg-yellow-50 text-yellow-900',
        admin: 'border-red-600 bg-red-50 text-red-900',
        leader: 'border-orange-600 bg-orange-50 text-orange-900',
    }[context.value.role] || 'border-gray-600 bg-gray-50 text-gray-900'));
    const buttonClass = computed(() => ({
        superadmin: 'bg-yellow-700 text-white hover:bg-yellow-800',
        admin: 'bg-red-700 text-white hover:bg-red-800',
        leader: 'bg-orange-700 text-white hover:bg-orange-800',
    }[context.value.role] || 'bg-gray-700 text-white hover:bg-gray-800'));

    return { context, analysisRoute, activeClass, buttonClass };
}
