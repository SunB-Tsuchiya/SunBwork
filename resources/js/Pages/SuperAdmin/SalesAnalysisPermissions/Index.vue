<script setup>
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import useToasts from '@/Composables/useToasts';

const props = defineProps({
    candidates: { type: Array, default: () => [] },
});

const { addToast } = useToasts();
const savingId = ref(null);

const roleLabels = {
    admin: 'Admin',
    clerk: 'Clerk',
};

const roleBadgeClass = {
    admin: 'bg-red-100 text-red-800',
    clerk: 'bg-purple-100 text-purple-800',
};

const toggle = (candidate) => {
    savingId.value = candidate.id;
    const nextEnabled = !candidate.enabled;

    router.put(
        route('superadmin.sales_analysis_permissions.update', { user: candidate.id }),
        { enabled: nextEnabled },
        {
            preserveScroll: true,
            onSuccess: () => {
                candidate.enabled = nextEnabled;
                addToast(nextEnabled ? '利用を許可しました' : '利用許可を解除しました', 'success');
            },
            onError: () => {
                addToast('保存に失敗しました', 'error');
            },
            onFinish: () => {
                savingId.value = null;
            },
        }
    );
};
</script>

<template>
    <AppLayout title="売上分析 利用許可設定">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">売上分析 利用許可設定</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <p class="mb-4 text-sm text-gray-500">
                対象は Admin / Clerk のみです（Leader は対象外）。許可したユーザーは閲覧・取込・会社統合設定・Excel出力のすべてを利用できます。
            </p>

            <div v-if="candidates.length === 0" class="py-8 text-center text-gray-500">
                対象ユーザーが登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">ロール</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">利用許可</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="candidate in candidates" :key="candidate.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ candidate.name }}</div>
                                <div class="text-xs text-gray-500">{{ candidate.email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <span
                                    :class="roleBadgeClass[candidate.user_role]"
                                    class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                >
                                    {{ roleLabels[candidate.user_role] }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <button
                                    type="button"
                                    :disabled="savingId === candidate.id"
                                    @click="toggle(candidate)"
                                    :class="candidate.enabled
                                        ? 'bg-green-600 hover:bg-green-700'
                                        : 'bg-gray-300 hover:bg-gray-400'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors disabled:opacity-50"
                                >
                                    <span
                                        :class="candidate.enabled ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
