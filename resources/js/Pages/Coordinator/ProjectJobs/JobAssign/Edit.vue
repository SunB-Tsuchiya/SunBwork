<template>
    <AppLayout :title="`割当編集 - ${projectJob ? projectJob.title : ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">割当編集</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-bold">割当を編集</h1>
                <button
                    type="button"
                    @click="openModal"
                    class="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                >過去データから流用</button>
            </div>

            <AssignmentForm :key="formKey" :projectJob="projectJob" :members="members" :assignments="formAssignments" :editMode="true" />

            <div class="mt-4">
                <Link
                    :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob ? projectJob.id : '' })"
                    class="rounded bg-gray-200 px-4 py-2"
                    >戻る</Link
                >
            </div>
        </div>
    </AppLayout>

    <!-- 過去データから流用 モーダル -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="closeModal">
        <div class="relative mx-4 flex max-h-[90vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-xl">
            <!-- ヘッダー -->
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-800">過去データから流用</h2>
                <button @click="closeModal" class="text-gray-500 hover:text-gray-700">✕</button>
            </div>

            <!-- フィルター -->
            <div class="border-b px-6 py-3 space-y-3">
                <div class="flex items-center gap-4">
                    <!-- 完了を表示しない -->
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                        <input type="checkbox" v-model="modalHideCompleted" class="h-4 w-4 rounded border-gray-300" />
                        完了を表示しない
                    </label>
                </div>

                <!-- 検索モード -->
                <div class="flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                    <button
                        @click="modalMode = 'date'"
                        :class="modalMode === 'date' ? 'bg-white text-blue-700 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        class="rounded px-4 py-1.5 text-sm transition-all"
                    >日付から検索</button>
                    <button
                        @click="modalMode = 'project'"
                        :class="modalMode === 'project' ? 'bg-white text-blue-700 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        class="rounded px-4 py-1.5 text-sm transition-all"
                    >案件から検索</button>
                </div>

                <!-- 日付モード -->
                <div v-if="modalMode === 'date'" class="flex gap-2">
                    <button
                        v-for="opt in dateRangeOptions"
                        :key="opt.value"
                        @click="modalDateRange = opt.value"
                        :class="modalDateRange === opt.value ? 'bg-blue-600 text-white' : 'border text-gray-700 hover:bg-gray-100'"
                        class="rounded px-3 py-1.5 text-sm"
                    >{{ opt.label }}</button>
                </div>

                <!-- 案件モード -->
                <div v-if="modalMode === 'project'" class="flex flex-wrap gap-3">
                    <select v-model="modalClientId" @change="modalProjectJobId = ''" class="rounded border px-3 py-2 text-sm">
                        <option value="">-- クライアント --</option>
                        <option v-for="c in modalClients" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="modalProjectJobId" class="rounded border px-3 py-2 text-sm">
                        <option value="">-- 案件 --</option>
                        <option v-for="p in filteredProjects" :key="p.id" :value="p.id">{{ p.title }}</option>
                    </select>
                </div>

                <button
                    @click="fetchModalData"
                    :disabled="modalLoading"
                    class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-60"
                >{{ modalLoading ? '検索中...' : '検索' }}</button>
            </div>

            <!-- 結果テーブル -->
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div v-if="modalError" class="mb-3 text-sm text-red-600">{{ modalError }}</div>
                <div v-if="!modalLoading && modalRecords.length === 0" class="py-8 text-center text-sm text-gray-400">
                    データがありません。
                </div>
                <table v-if="modalRecords.length > 0" class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">日付</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">クライアント</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">案件</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">タイトル</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">見積時間</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="rec in modalRecords"
                            :key="rec.id"
                            @click="selectRecord(rec)"
                            class="cursor-pointer hover:bg-blue-50"
                        >
                            <td class="px-3 py-2 text-gray-600">{{ rec.created_at || '-' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ rec.client_name || '-' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ rec.project_job_name || '-' }}</td>
                            <td class="px-3 py-2">{{ rec.title || '-' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ rec.estimated_hours != null ? `${rec.estimated_hours}h` : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- フッター -->
            <div class="border-t px-6 py-3 text-right">
                <button @click="closeModal" class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">閉じる</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ projectJob: Object, members: Array, assignments: Array });
const projectJob = props.projectJob;
const members = props.members || [];

const formAssignments = ref(props.assignments || []);
const formKey = ref(0);

// ===== モーダル状態 =====
const showModal = ref(false);
const modalMode = ref('date');
const modalDateRange = ref('yesterday');
const modalHideCompleted = ref(true);
const modalClientId = ref('');
const modalProjectJobId = ref('');
const modalRecords = ref([]);
const modalClients = ref([]);
const modalProjects = ref([]);
const modalLoading = ref(false);
const modalError = ref('');

const dateRangeOptions = [
    { value: 'yesterday', label: '前日' },
    { value: 'week', label: '過去7日' },
    { value: 'month', label: '過去30日' },
];

const filteredProjects = computed(() => {
    if (!modalClientId.value) return modalProjects.value;
    return modalProjects.value.filter((p) => String(p.client_id) === String(modalClientId.value));
});

// モーダルを開くとき初期データ取得
function openModal() {
    showModal.value = true;
    if (modalClients.value.length === 0) {
        fetchModalData();
    }
}

function closeModal() {
    showModal.value = false;
}

async function fetchModalData() {
    modalLoading.value = true;
    modalError.value = '';
    try {
        const params = new URLSearchParams({
            mode: modalMode.value,
            hide_completed: modalHideCompleted.value ? '1' : '0',
        });
        if (modalMode.value === 'date') {
            params.set('date_range', modalDateRange.value);
        } else {
            if (modalClientId.value) params.set('client_id', modalClientId.value);
            if (modalProjectJobId.value) params.set('project_job_id', modalProjectJobId.value);
        }
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const xsrfMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const xsrf = xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : null;
        const url = (typeof route === 'function' ? route('coordinator.project_jobs.past_assignments') : '/coordinator/project_jobs/past-assignments') + '?' + params.toString();
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token,
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        if (!res.ok) throw new Error('取得失敗');
        const data = await res.json();
        modalRecords.value = data.records || [];
        if (data.clients) modalClients.value = data.clients;
        if (data.projects) modalProjects.value = data.projects;
    } catch (e) {
        modalError.value = 'データの取得に失敗しました。';
    } finally {
        modalLoading.value = false;
    }
}

// 日付・完了フラグ変更で自動再検索
watch([modalDateRange, modalHideCompleted], () => {
    if (showModal.value && modalMode.value === 'date') fetchModalData();
});

function selectRecord(rec) {
    formAssignments.value = [
        {
            id: null, // 新規として扱う
            project_job_id: rec.project_job_id,
            title_suffix: rec.title ?? '',
            detail: rec.detail ?? '',
            work_item_type_id: rec.work_item_type_id ?? null,
            size_id: rec.size_id ?? null,
            stage_id: rec.stage_id ?? null,
            difficulty_id: rec.difficulty_id ?? null,
            desired_end_date: rec.desired_end_date ?? '',
            desired_time: rec.desired_time ?? null,
            estimated_hours: rec.estimated_hours ?? null,
            amounts: null, // 意図的に空にする
            amounts_unit: rec.amounts_unit ?? 'page',
            status_id: 1,
        },
    ];
    formKey.value += 1;
    closeModal();
}
</script>
