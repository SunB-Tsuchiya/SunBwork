<template>
    <AppLayout title="進行レポート">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-green-700">進行レポート</h2>
        </template>
        <template #tabs>
            <CoordinatorNavigationTabs active="progress_report" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- フィルター -->
            <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <!-- 担当者 -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">担当者</label>
                    <select v-model="form.user_id" class="w-full rounded border px-3 py-2 text-sm">
                        <option :value="null">全員</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>

                <!-- 案件 -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">案件</label>
                    <select v-model="form.project_job_id" class="w-full rounded border px-3 py-2 text-sm">
                        <option :value="null">全案件</option>
                        <option v-for="j in projectJobs" :key="j.id" :value="j.id">
                            {{ j.title }}（{{ j.client_name }}）
                        </option>
                    </select>
                </div>

                <!-- 完了状況 -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">完了状況</label>
                    <select v-model="form.status" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="incomplete">未完了のみ</option>
                        <option value="complete">完了のみ</option>
                        <option value="all">全件</option>
                    </select>
                </div>

                <!-- 締め切り -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">締め切り（この日まで）</label>
                    <input type="date" v-model="form.deadline_to" class="w-full rounded border px-3 py-2 text-sm" />
                </div>

                <!-- 完了日 -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">完了日（この日以前）</label>
                    <input type="date" v-model="form.completed_date" class="w-full rounded border px-3 py-2 text-sm" />
                </div>

                <!-- ボタン -->
                <div class="flex items-end gap-2">
                    <button @click="search" class="rounded bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700">
                        検索
                    </button>
                    <button @click="clearFilters" class="rounded border px-4 py-2 text-sm hover:bg-gray-50">
                        クリア
                    </button>
                </div>
            </div>

            <!-- 件数 -->
            <div class="mb-3 text-sm text-gray-600">
                {{ cells.length }} 件
            </div>

            <!-- テーブル -->
            <div class="overflow-x-auto">
                <div v-if="cells.length === 0" class="py-10 text-center text-sm text-gray-400">
                    該当するセルがありません。
                </div>

                <table v-else class="w-full border text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">案件名</th>
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">進行表</th>
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">行</th>
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">列</th>
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">担当者</th>
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">締め切り</th>
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">状況</th>
                            <th class="border px-3 py-2 text-left text-xs font-medium text-gray-500">完了日</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cell in cells"
                            :key="cell.id"
                            class="cursor-pointer hover:bg-green-50"
                            :class="rowClass(cell)"
                            @click="openSheet(cell)"
                        >
                            <td class="border px-3 py-2">{{ cell.project_job_title }}</td>
                            <td class="border px-3 py-2 text-gray-600">{{ cell.sheet_name }}</td>
                            <td class="border px-3 py-2 text-gray-600">{{ cell.row_label }}</td>
                            <td class="border px-3 py-2 text-gray-600">{{ cell.col_label }}</td>
                            <td class="border px-3 py-2 font-medium">{{ cell.user_name }}</td>
                            <td class="border px-3 py-2" :class="deadlineClass(cell)">
                                {{ cell.deadline ?? '—' }}
                            </td>
                            <td class="border px-3 py-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="cell.completed_at ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ cell.completed_at ? '完了' : '未完了' }}
                                </span>
                            </td>
                            <td class="border px-3 py-2 text-xs text-gray-500">
                                {{ cell.completed_at ? cell.completed_at.slice(0, 10) : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    cells:       { type: Array, default: () => [] },
    projectJobs: { type: Array, default: () => [] },
    users:       { type: Array, default: () => [] },
    filters:     { type: Object, default: () => ({}) },
});

const form = reactive({
    user_id:        props.filters.user_id        ?? null,
    project_job_id: props.filters.project_job_id ?? null,
    status:         props.filters.status         ?? 'incomplete',
    deadline_to:    props.filters.deadline_to    ?? '',
    completed_date: props.filters.completed_date ?? '',
});

function search() {
    const params = {};
    if (form.user_id)        params.user_id        = form.user_id;
    if (form.project_job_id) params.project_job_id = form.project_job_id;
    if (form.status !== 'all') params.status       = form.status;
    if (form.deadline_to)    params.deadline_to    = form.deadline_to;
    if (form.completed_date) params.completed_date = form.completed_date;

    router.get(route('coordinator.progress_report.index'), params, { preserveState: false });
}

function clearFilters() {
    form.user_id        = null;
    form.project_job_id = null;
    form.status         = 'incomplete';
    form.deadline_to    = '';
    form.completed_date = '';
    search();
}

function openSheet(cell) {
    router.visit(route('coordinator.progress_sheets.show', { sheet: cell.sheet_id }));
}

const today = new Date().toLocaleDateString('sv-SE');
const in3days = new Date(Date.now() + 3 * 86400000).toLocaleDateString('sv-SE');

function rowClass(cell) {
    if (cell.completed_at) return 'bg-green-50';
    if (!cell.deadline)    return '';
    if (cell.deadline < today)   return 'bg-red-50';
    if (cell.deadline <= in3days) return 'bg-yellow-50';
    return '';
}

function deadlineClass(cell) {
    if (cell.completed_at || !cell.deadline) return '';
    if (cell.deadline < today)    return 'font-semibold text-red-600';
    if (cell.deadline <= in3days) return 'font-semibold text-yellow-700';
    return '';
}
</script>
