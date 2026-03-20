<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    groups:        { type: Array,   default: () => [] },
    meta:          { type: Object,  default: null },
    filters:       { type: Object,  default: () => ({}) },
    routePrefix:   { type: String,  default: 'admin' },
    is_superadmin: { type: Boolean, default: false },
});

const viewMode      = ref(props.filters?.group === 'month' ? 'month' : 'day');
const selectedDays  = ref(props.filters?.days ? Number(props.filters.days) : 30);
const sortOvertime  = ref(false);

function indexRoute() {
    return `${props.routePrefix}.work_records.index`;
}

function applyFilters(page = 1) {
    const params = { days: selectedDays.value, perPage: props.filters?.perPage ?? 50, page };
    if (viewMode.value === 'month') params.group = 'month';
    router.get(route(indexRoute()), params);
}

function formatMinutes(min) {
    if (!min) return '—';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h${m > 0 ? m + 'm' : ''}` : `${m}m`;
}

// 全レコードをフラット化して日付・月でグループ化
const allRecords = computed(() => {
    const list = [];
    for (const group of props.groups ?? []) {
        for (const dept of group.departments ?? []) {
            for (const rec of dept.records ?? []) {
                list.push({ ...rec, company_name: group.company_name, department_name: dept.department_name });
            }
        }
    }
    return list;
});

const groupedByDate = computed(() => {
    const map = {};
    for (const rec of allRecords.value) {
        const key = viewMode.value === 'month' ? (rec.date ?? '').slice(0, 7) : (rec.date ?? '不明');
        if (!map[key]) map[key] = [];
        map[key].push(rec);
    }
    // 日付キーを降順ソート
    const sorted = Object.entries(map).sort(([a], [b]) => (a < b ? 1 : a > b ? -1 : 0));
    // 残業時間ソートが有効なら各グループ内を overtime_minutes 降順に並べ替え
    if (sortOvertime.value) {
        for (const entry of sorted) {
            entry[1].sort((a, b) => (b.overtime_minutes ?? 0) - (a.overtime_minutes ?? 0));
        }
    }
    return Object.fromEntries(sorted);
});

function formatDateLabel(key) {
    if (!key) return '不明';
    if (viewMode.value === 'month') {
        const [y, m] = key.split('-');
        return `${y}年${parseInt(m)}月`;
    }
    const [y, m, d] = key.split('-');
    return `${y}/${m}/${d}`;
}

const currentPage = computed(() => props.meta?.current_page ?? 1);
const lastPage    = computed(() => props.meta?.last_page ?? 1);
</script>

<template>
    <AppLayout title="勤務時間管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">勤務時間管理</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- フィルタ -->
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <label class="text-sm">表示:</label>
                <select v-model="viewMode" class="w-36 rounded border px-2 py-1 text-sm">
                    <option value="day">日別表示</option>
                    <option value="month">月別に表示</option>
                </select>

                <label class="text-sm">期間:</label>
                <select v-model.number="selectedDays" class="w-40 rounded border px-2 py-1 text-sm">
                    <option :value="7">7日分を表示</option>
                    <option :value="30">30日分を表示</option>
                    <option :value="90">90日分を表示</option>
                </select>

                <button
                    @click.prevent="applyFilters(1)"
                    class="rounded bg-blue-600 px-4 py-1 text-sm text-white hover:bg-blue-700"
                >
                    適用
                </button>

                <button
                    @click.prevent="sortOvertime = !sortOvertime"
                    :class="[
                        'rounded border px-4 py-1 text-sm transition-colors',
                        sortOvertime
                            ? 'border-red-500 bg-red-50 text-red-700 font-medium'
                            : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50',
                    ]"
                >
                    残業時間順
                    <span v-if="sortOvertime" class="ml-1 text-xs">▼ ON</span>
                </button>
            </div>

            <!-- データなし -->
            <div v-if="!allRecords.length" class="py-12 text-center text-gray-500">
                対象の勤務記録がありません。
            </div>

            <!-- 日付/月グループ -->
            <div v-for="(records, dateKey) in groupedByDate" :key="dateKey" class="mb-8">
                <h3 class="mb-2 text-base font-bold text-gray-800 border-b pb-1">
                    {{ formatDateLabel(dateKey) }}
                </h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-if="viewMode === 'month'" class="px-3 py-2 text-left font-medium text-gray-700">日付</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">氏名</th>
                                <th v-if="is_superadmin" class="px-3 py-2 text-left font-medium text-gray-700">会社</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">部署</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">勤務形態</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">始業</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">終業</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">規定始業</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">規定終業</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">残業</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">早退</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="rec in records" :key="rec.id" class="hover:bg-gray-50">
                                <td v-if="viewMode === 'month'" class="whitespace-nowrap px-3 py-2 text-gray-800">{{ rec.date }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-800">{{ rec.user_name }}</td>
                                <td v-if="is_superadmin" class="whitespace-nowrap px-3 py-2 text-gray-600">{{ rec.company_name }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ rec.department_name }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ rec.worktype_name }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ rec.start_time || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ rec.end_time || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ rec.scheduled_start || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ rec.scheduled_end || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span v-if="rec.overtime_minutes" class="text-red-600">{{ formatMinutes(rec.overtime_minutes) }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span v-if="rec.early_leave_minutes" class="text-orange-600">{{ formatMinutes(rec.early_leave_minutes) }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ページネーション -->
            <div v-if="meta && meta.last_page > 1" class="mt-6 flex items-center justify-between">
                <button
                    class="rounded border px-3 py-1 text-sm disabled:opacity-40"
                    :disabled="currentPage <= 1"
                    @click.prevent="applyFilters(currentPage - 1)"
                >
                    前
                </button>
                <span class="text-sm text-gray-600">
                    {{ currentPage }} / {{ lastPage }} ページ
                    <span class="ml-2 text-gray-400">(全 {{ meta.total }} 件)</span>
                </span>
                <button
                    class="rounded border px-3 py-1 text-sm disabled:opacity-40"
                    :disabled="currentPage >= lastPage"
                    @click.prevent="applyFilters(currentPage + 1)"
                >
                    次
                </button>
            </div>
        </div>
    </AppLayout>
</template>
