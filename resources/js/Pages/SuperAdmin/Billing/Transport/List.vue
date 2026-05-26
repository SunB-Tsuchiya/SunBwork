<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';

const props = defineProps({
    grouped:  { type: Array, default: () => [] },
    month:    { type: String, required: true },
    months:   { type: Array, required: true },
    purposes: { type: Object, required: true },
});

const selectedMonth = ref(props.month);

function changeMonth() {
    router.get(route('superadmin.billing.transport.list'), { month: selectedMonth.value });
}

// 詳細モーダル
const detailModal = ref(null); // { user_name, expenses }

function openDetail(member) {
    detailModal.value = member;
}

function closeDetail() {
    detailModal.value = null;
}

function formatMonth(ym) {
    const [y, m] = ym.split('-');
    return `${y}年${parseInt(m)}月`;
}
</script>

<template>
    <AppLayout title="交通費一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">交通費一覧</h2>
        </template>

        <template #tabs>
            <SuperAdminNavigationTabs active="billing_transport_list" />
        </template>

        <div class="space-y-6">
            <div class="rounded bg-white p-6 shadow">
                <!-- 月フィルター -->
                <div class="mb-6 flex items-center gap-3">
                    <label class="text-sm font-medium text-gray-700">対象月</label>
                    <select
                        v-model="selectedMonth"
                        @change="changeMonth"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none"
                    >
                        <option v-for="m in months" :key="m" :value="m">
                            {{ formatMonth(m) }}
                        </option>
                    </select>
                    <span class="text-sm text-gray-500">{{ formatMonth(month) }} の申請状況</span>
                </div>

                <!-- データなし -->
                <div v-if="!grouped.length" class="py-12 text-center text-gray-400">
                    {{ formatMonth(month) }} の申請はありません。
                </div>

                <!-- 部署別グループ -->
                <div v-else class="space-y-6">
                    <div
                        v-for="dept in grouped"
                        :key="dept.department_name"
                        class="rounded border border-gray-200"
                    >
                        <!-- 部署ヘッダー -->
                        <div class="flex items-center justify-between rounded-t bg-gray-50 px-4 py-2.5">
                            <h3 class="font-semibold text-gray-800">{{ dept.department_name }}</h3>
                            <span class="text-sm text-gray-600">
                                部署合計: <strong>{{ dept.dept_total.toLocaleString() }} 円</strong>
                            </span>
                        </div>

                        <!-- メンバー一覧テーブル -->
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs text-gray-500">
                                    <th class="px-4 py-2 text-left">氏名</th>
                                    <th class="px-4 py-2 text-right">合計金額</th>
                                    <th class="px-4 py-2 text-right">件数</th>
                                    <th class="px-4 py-2 text-center">詳細</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="member in dept.members"
                                    :key="member.user_id"
                                    class="border-b border-gray-100 last:border-0"
                                >
                                    <td class="px-4 py-2.5 text-gray-800">{{ member.user_name }}</td>
                                    <td class="px-4 py-2.5 text-right font-medium text-gray-900">
                                        {{ member.total_amount.toLocaleString() }} 円
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600">
                                        {{ member.expenses.length }} 件
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button
                                            type="button"
                                            @click="openDetail(member)"
                                            class="rounded bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100"
                                        >
                                            詳細
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 詳細モーダル -->
        <Teleport to="body">
            <div
                v-if="detailModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="closeDetail"
            >
                <div class="max-h-[80vh] w-full max-w-3xl overflow-auto rounded-lg bg-white p-6 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">
                            {{ detailModal.user_name }} さんの申請詳細（{{ formatMonth(month) }}）
                        </h3>
                        <button
                            type="button"
                            @click="closeDetail"
                            class="text-gray-400 hover:text-gray-600 text-xl leading-none"
                        >
                            ✕
                        </button>
                    </div>

                    <div
                        v-for="exp in detailModal.expenses"
                        :key="exp.id"
                        class="mb-4 rounded border border-gray-200"
                    >
                        <!-- 申請ヘッダー -->
                        <div class="flex items-center justify-between bg-gray-50 px-3 py-2 text-sm">
                            <span class="text-gray-700">
                                請求日: {{ exp.billing_date }}
                            </span>
                            <span
                                :class="exp.status === 'submitted' ? 'text-green-600' : 'text-yellow-600'"
                                class="text-xs font-medium"
                            >
                                {{ exp.status === 'submitted' ? '提出済' : '下書き' }}
                            </span>
                            <span class="font-medium text-gray-900">
                                合計: {{ exp.total_amount.toLocaleString() }} 円
                            </span>
                        </div>

                        <!-- 明細 -->
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-500">
                                    <th class="px-3 py-1.5 text-center">発生日</th>
                                    <th class="px-3 py-1.5 text-left">行先</th>
                                    <th class="px-3 py-1.5 text-left">用件</th>
                                    <th class="px-3 py-1.5 text-left">区間</th>
                                    <th class="px-3 py-1.5 text-center">種別</th>
                                    <th class="px-3 py-1.5 text-right">金額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in exp.items"
                                    :key="item.id"
                                    class="border-b border-gray-100 last:border-0"
                                >
                                    <td class="px-3 py-1.5 text-center text-gray-700">{{ item.occurrence_date || '―' }}</td>
                                    <td class="px-3 py-1.5 text-gray-700">{{ item.destination || '―' }}</td>
                                    <td class="px-3 py-1.5 text-gray-700">{{ item.purpose_label }}</td>
                                    <td class="px-3 py-1.5 text-gray-700">
                                        <span v-if="item.station_from || item.station_to">
                                            {{ item.station_from }} → {{ item.station_to }}
                                        </span>
                                        <span v-else class="text-gray-400">―</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span
                                            :class="item.fare_type === 'ic' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'"
                                            class="rounded px-1.5 py-0.5 text-xs"
                                        >
                                            {{ item.fare_type === 'ic' ? 'IC' : '切符' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-medium text-gray-900">
                                        {{ item.amount.toLocaleString() }} 円
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-right text-sm font-semibold text-gray-900">
                        合計: {{ detailModal.total_amount.toLocaleString() }} 円
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
