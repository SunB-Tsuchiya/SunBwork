<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';

const props = defineProps({
    billings:        { type: Array,  default: () => [] },
    month:           { type: String, required: true },
    months:          { type: Array,  required: true },
    departmentCodes: { type: Object, required: true },
});

const selectedMonth = ref(props.month);
const openIds       = ref(new Set());

const monthOptions = computed(() =>
    props.months.map(m => {
        const [y, mo] = m.split('-');
        return { value: m, label: `${y}年${parseInt(mo)}月` };
    })
);

function changeMonth() {
    router.get(route('superadmin.billing.transport.billed'), { month: selectedMonth.value }, {
        preserveState: true, preserveScroll: false,
    });
}

function toggle(id) {
    if (openIds.value.has(id)) openIds.value.delete(id);
    else openIds.value.add(id);
    openIds.value = new Set(openIds.value);
}

function deptName(code) {
    return props.departmentCodes[code] ?? code;
}
</script>

<template>
    <AppLayout title="請求済み交通費">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">請求済み交通費</h2>
        </template>

        <template #headerExtras>
            <a
                :href="route('superadmin.billing.transport.index')"
                class="rounded bg-blue-100 px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200"
            >
                ← 交通費入力
            </a>
        </template>

        <template #tabs>
            <SuperAdminNavigationTabs active="billing_transport_input" />
        </template>

        <div class="rounded bg-white px-4 py-6 shadow">

            <!-- 月セレクター -->
            <div class="mb-6 flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700">対象月:</label>
                <select v-model="selectedMonth" @change="changeMonth"
                    class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                    <option v-for="opt in monthOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>

            <!-- 請求データがない -->
            <div v-if="billings.length === 0"
                class="py-12 text-center text-sm text-gray-400">
                この月の請求済みデータはありません。
            </div>

            <!-- 請求データ一覧 -->
            <div v-else class="space-y-4">
                <div v-for="billing in billings" :key="billing.id"
                    class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">

                    <!-- ヘッダー行 -->
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-gray-50 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-4">
                            <button type="button" @click="toggle(billing.id)"
                                class="flex items-center gap-2 text-left">
                                <span class="text-gray-400 text-xs">
                                    {{ openIds.has(billing.id) ? '▲' : '▼' }}
                                </span>
                                <div>
                                    <span class="text-sm font-semibold text-gray-800">
                                        {{ billing.period_start }} 〜 {{ billing.period_end }}
                                    </span>
                                    <span class="ml-3 text-xs text-gray-500">（{{ billing.expense_count }}件）</span>
                                </div>
                            </button>
                            <span class="text-base font-bold text-gray-900">
                                {{ billing.total_amount.toLocaleString() }} 円
                            </span>
                            <span class="text-xs text-gray-400">作成: {{ billing.created_at }}</span>
                        </div>
                        <div class="flex gap-2">
                            <a :href="route('superadmin.billing.transport.billing.pdf', { billing: billing.id })"
                                target="_blank"
                                class="rounded bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                                PDF
                            </a>
                            <a :href="route('superadmin.billing.transport.billing.excel', { billing: billing.id })"
                                class="rounded bg-green-50 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-100">
                                Excel
                            </a>
                        </div>
                    </div>

                    <!-- 展開: 伝票一覧 -->
                    <div v-if="openIds.has(billing.id)" class="divide-y divide-gray-100 px-4 py-2">
                        <div v-for="exp in billing.expenses" :key="exp.id" class="py-3">

                            <!-- 伝票ヘッダー -->
                            <div class="mb-2 flex flex-wrap items-center gap-3 text-sm">
                                <span class="font-medium text-gray-700">{{ exp.billing_date }}</span>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                    {{ deptName(exp.department_code) }}
                                </span>
                                <span class="font-semibold text-gray-900">
                                    {{ exp.total_amount.toLocaleString() }} 円
                                </span>
                                <div class="flex gap-2 ml-auto">
                                    <a :href="route('superadmin.billing.transport.pdf', { expense: exp.id })"
                                        target="_blank"
                                        class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                        PDF
                                    </a>
                                    <a :href="route('superadmin.billing.transport.excel', { expense: exp.id })"
                                        class="rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 hover:bg-green-100">
                                        Excel
                                    </a>
                                </div>
                            </div>

                            <!-- 明細行 -->
                            <table class="w-full text-xs text-gray-700">
                                <thead>
                                    <tr class="border-b border-gray-200 text-gray-500">
                                        <th class="pb-1 text-left font-normal w-14">発生日</th>
                                        <th class="pb-1 text-left font-normal w-24">行先</th>
                                        <th class="pb-1 text-left font-normal">用件</th>
                                        <th class="pb-1 text-left font-normal">区間</th>
                                        <th class="pb-1 text-right font-normal w-20">金額</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in exp.items" :key="item.id"
                                        class="border-b border-gray-100 last:border-0">
                                        <td class="py-1">{{ item.occurrence_date ?? '―' }}</td>
                                        <td class="py-1">{{ item.destination ?? '―' }}</td>
                                        <td class="py-1">{{ item.purpose_label }}</td>
                                        <td class="py-1 text-gray-500">
                                            <span v-if="item.station_from || item.station_to">
                                                {{ item.station_from }} → {{ item.station_to }}
                                            </span>
                                            <span v-else>―</span>
                                        </td>
                                        <td class="py-1 text-right tabular-nums">
                                            {{ item.amount.toLocaleString() }} 円
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
