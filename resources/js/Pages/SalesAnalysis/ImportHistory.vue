<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

const props = defineProps({
    routePrefix: { type: String, required: true },
    imports: { type: Array, default: () => [] },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const sourcePeriod = (item) => (item.source_month ? `${item.source_year}年${item.source_month}月` : `${item.source_year}年（年次）`);

const statusLabels = {
    validating: '検証中',
    failed: '失敗',
    completed: '完了',
};
</script>

<template>
    <AppLayout title="売上取込履歴">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← ダッシュボードに戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">売上取込履歴</h2>
            </div>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="import_history" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div v-if="imports.length === 0" class="py-8 text-center text-gray-500">
                取込履歴がありません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">対象期間</th>
                            <th class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">版</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ファイル名</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ハッシュ</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当者</th>
                            <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">受注/明細</th>
                            <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">合計金額</th>
                            <th class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">状態</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">日時</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in imports" :key="item.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-700">{{ item.department_label }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-700">{{ sourcePeriod(item) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-center text-sm">
                                <span
                                    class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="item.active_month_count === item.total_month_count ? 'bg-green-100 text-green-800' : item.is_active ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500'"
                                    :title="item.active_month_count > 0 && item.active_month_count < item.total_month_count ? '一部の月だけ別版に差し替わっています' : ''"
                                >
                                    v{{ item.version }} ({{ item.active_month_count }}/{{ item.total_month_count }}有効)
                                </span>
                            </td>
                            <td class="max-w-[16rem] truncate px-3 py-3 text-sm text-gray-700" :title="item.original_filename">{{ item.original_filename }}</td>
                            <td class="whitespace-nowrap px-3 py-3 font-mono text-xs text-gray-500">{{ item.file_sha256_short }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-700">{{ item.imported_by_name }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-gray-700">{{ item.order_count }} / {{ item.detail_count }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-gray-700">¥{{ Number(item.total_amount).toLocaleString() }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-700">{{ statusLabels[item.status] ?? item.status }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-500">{{ item.imported_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="lastPage > 1" class="mt-4 text-center text-xs text-gray-500">{{ currentPage }} / {{ lastPage }} ページ</p>
        </div>
    </AppLayout>
</template>
