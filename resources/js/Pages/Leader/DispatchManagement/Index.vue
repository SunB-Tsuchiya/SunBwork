<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

// 雇用形態フィルター
const filterType = ref('all');

const EMPLOYMENT_TYPE_OPTIONS = [
    { value: 'all',       label: 'すべて' },
    { value: 'regular',   label: '正社員' },
    { value: 'contract',  label: '契約社員' },
    { value: 'dispatch',  label: '派遣社員' },
    { value: 'outsource', label: '業務委託' },
];

const filtered = computed(() => {
    if (filterType.value === 'all') return props.users;
    return props.users.filter((u) => u.employment_type === filterType.value);
});

const badgeClass = (type) => {
    switch (type) {
        case 'regular':   return 'bg-blue-100 text-blue-700';
        case 'contract':  return 'bg-green-100 text-green-700';
        case 'dispatch':  return 'bg-orange-100 text-orange-700';
        case 'outsource': return 'bg-purple-100 text-purple-700';
        default:          return 'bg-gray-100 text-gray-600';
    }
};

// 契約終了日の表示スタイルと表示値
function contractEndInfo(dateStr) {
    if (!dateStr) return null;
    const end = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.ceil((end - today) / (1000 * 60 * 60 * 24));
    return {
        label: dateStr,
        diffDays,
        cls: diffDays < 0
            ? 'text-red-600 font-semibold'
            : diffDays <= 30
                ? 'text-orange-500 font-semibold'
                : 'text-gray-700',
        badge: diffDays < 0
            ? '期限切れ'
            : diffDays <= 30
                ? `残${diffDays}日`
                : null,
    };
}
</script>

<template>
    <AppLayout title="派遣・業務委託管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">派遣・業務委託管理</h2>
        </template>
        <template #tabs>
            <LeaderNavigationTabs active="dispatch" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- フィルター -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="opt in EMPLOYMENT_TYPE_OPTIONS"
                    :key="opt.value"
                    class="rounded-full border px-3 py-1 text-sm transition"
                    :class="filterType === opt.value
                        ? 'border-orange-400 bg-orange-100 text-orange-700 font-semibold'
                        : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                    @click="filterType = opt.value"
                >
                    {{ opt.label }}
                    <span class="ml-1 text-xs text-gray-400">
                        ({{ opt.value === 'all' ? users.length : users.filter(u => u.employment_type === opt.value).length }})
                    </span>
                </button>
            </div>

            <!-- テーブル -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">氏名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">雇用形態</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">派遣会社 / 委託先</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">契約終了日</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">日報</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-if="filtered.length === 0">
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-400">
                                対象ユーザーがいません
                            </td>
                        </tr>
                        <tr
                            v-for="user in filtered"
                            :key="user.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ user.name }}
                                <div class="text-xs text-gray-400">{{ user.email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ user.department_name || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ user.assignment_name || '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="badgeClass(user.employment_type)"
                                >
                                    {{ user.employment_type_label }}
                                </span>
                            </td>
                            <!-- 派遣会社 / 委託先（dispatch/outsource のみ） -->
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <span v-if="['dispatch','outsource'].includes(user.employment_type)">
                                    {{ user.agency_name || '—' }}
                                </span>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <!-- 契約終了日 -->
                            <td class="px-4 py-3 text-sm">
                                <template v-if="['dispatch','outsource'].includes(user.employment_type) && user.contract_end">
                                    <span :class="contractEndInfo(user.contract_end)?.cls">
                                        {{ contractEndInfo(user.contract_end)?.label }}
                                    </span>
                                    <span
                                        v-if="contractEndInfo(user.contract_end)?.badge"
                                        class="ml-1 inline-block rounded px-1 py-0 text-xs font-bold"
                                        :class="contractEndInfo(user.contract_end)?.diffDays < 0
                                            ? 'bg-red-100 text-red-600'
                                            : 'bg-orange-100 text-orange-600'"
                                    >{{ contractEndInfo(user.contract_end)?.badge }}</span>
                                </template>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="user.diary_required" class="text-xs text-blue-600">必須</span>
                                <span v-else class="text-xs text-gray-400">任意</span>
                                <span
                                    v-if="user.diary_required_override !== null && user.diary_required_override !== undefined"
                                    class="ml-1 text-xs text-orange-400"
                                    title="個別設定あり"
                                >★</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('leader.dispatch_management.edit', { dispatchUser: user.id })"
                                    class="text-sm text-orange-600 hover:underline"
                                >
                                    編集
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 凡例 -->
            <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-400">
                <span>★ = 雇用形態のデフォルトから個別上書きあり</span>
                <span class="text-orange-500 font-medium">残N日 = 契約終了まで30日以内</span>
                <span class="text-red-600 font-medium">期限切れ = 契約終了日が過去</span>
            </div>
        </div>
    </AppLayout>
</template>
