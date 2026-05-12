<template>
    <AppLayout title="ジョブ作成">
        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-bold">
                    <template v-if="hasSourceJob">依頼されたジョブをマイジョブBOXとして登録</template>
                    <template v-else>マイジョブBOX</template>
                </h1>
                <button v-if="!hasSourceJob" @click="openModal" class="rounded border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                    過去データから流用
                </button>
                <button v-if="!hasSourceJob" @click="openRequestModal" class="rounded border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-700 hover:bg-indigo-100">
                    依頼ジョブとして登録
                </button>
            </div>

            <div>
                <AssignmentFormUser
                    mode="user"
                    :key="formKey"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="formAssignments"
                    :editMode="true"
                    :hide-status="true"
                    :default-status-id="1"
                    :defaultUserId="defaultUserId"
                    :user-clients="userClients"
                    :user-projects="userProjects"
                />
            </div>

            <!-- 依頼ジョブ由来: ファイル一覧（file_info があれば常に表示） -->
            <div v-if="sourceFileInfo" class="mt-5">
                <div class="mb-2 rounded border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-700">
                    コーディネーターから送られたジョブのファイル情報です。ページ数・ファイル数は自動的に反映されています。
                </div>
                <FileInfoDisplay :fileInfo="sourceFileInfo" />
            </div>
        </div>

        <!-- 過去データから流用モーダル -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 pt-16" @click.self="closeModal">
                <div class="mx-4 w-full max-w-3xl rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h2 class="text-lg font-semibold">過去データから流用</h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-700">✕</button>
                    </div>
                    <div class="p-5">
                        <!-- 完了を表示しない toggle -->
                        <div class="mb-4 flex items-center gap-2">
                            <button
                                @click="hideCompleted = !hideCompleted"
                                :class="hideCompleted ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                                class="rounded px-3 py-1 text-sm font-medium transition-colors"
                            >
                                {{ hideCompleted ? '完了を非表示中' : '完了を表示しない' }}
                            </button>
                        </div>
                        <!-- 検索モード -->
                        <div class="mb-4 flex w-fit gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1">
                            <button
                                v-for="m in [
                                    { key: 'date', label: '日付から検索' },
                                    { key: 'project', label: '案件から検索' },
                                ]"
                                :key="m.key"
                                @click="modalMode = m.key"
                                :class="modalMode === m.key ? 'bg-white font-semibold text-blue-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                class="rounded px-4 py-1.5 text-sm transition-all"
                            >
                                {{ m.label }}
                            </button>
                        </div>
                        <!-- 日付モード -->
                        <div v-if="modalMode === 'date'" class="mb-4">
                            <select v-model="dateRange" class="rounded border border-gray-300 px-3 py-1.5 text-sm">
                                <option v-for="o in dateRangeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </div>
                        <!-- 案件モード -->
                        <div v-else class="mb-4 flex flex-wrap gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-gray-600">クライアント</label>
                                <select v-model="selectedClientId" class="min-w-[12rem] rounded border border-gray-300 px-3 py-1.5 text-sm">
                                    <option value="">すべて</option>
                                    <option v-for="c in modalClients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                                </select>
                            </div>
                            <div v-if="selectedClientId">
                                <label class="mb-1 block text-xs text-gray-600">案件</label>
                                <select v-model="selectedProjectId" class="min-w-[12rem] rounded border border-gray-300 px-3 py-1.5 text-sm">
                                    <option value="">すべて</option>
                                    <option v-for="p in modalProjects" :key="p.id" :value="String(p.id)">{{ p.title }}</option>
                                </select>
                            </div>
                        </div>
                        <!-- 結果テーブル -->
                        <div class="max-h-80 overflow-y-auto rounded border border-gray-200">
                            <div v-if="modalLoading" class="py-8 text-center text-sm text-gray-500">読み込み中...</div>
                            <div v-else-if="records.length === 0" class="py-8 text-center text-sm text-gray-500">該当データがありません</div>
                            <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="sticky top-0 bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">日付</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">クライアント</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">案件</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">タイトル</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">種別</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">見積</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr
                                        v-for="rec in records"
                                        :key="rec.id"
                                        @click="selectRecord(rec)"
                                        class="cursor-pointer hover:bg-blue-50"
                                        :class="rec.completed ? 'text-gray-400' : ''"
                                    >
                                        <td class="whitespace-nowrap px-3 py-2">{{ rec.created_at ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ rec.client_name ?? '-' }}</td>
                                        <td class="max-w-[8rem] truncate px-3 py-2">{{ rec.project_job_name ?? '-' }}</td>
                                        <td class="max-w-[10rem] truncate px-3 py-2">{{ rec.title ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ rec.work_item_type ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            {{ rec.estimated_hours != null ? rec.estimated_hours + 'h' : '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- 続き確認モーダル -->
        <Teleport to="body">
            <div
                v-if="showContinueModal"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
                @click.self="showContinueModal = false"
            >
                <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h2 class="mb-2 text-lg font-semibold text-gray-800">引用方法を選択</h2>
                    <p class="mb-1 text-sm text-gray-600">
                        選択したジョブ：<span class="font-medium text-gray-900">{{ pendingRecord?.title }}</span>
                    </p>
                    <p class="mb-5 text-sm text-gray-500">このジョブをどのように使いますか？</p>
                    <div class="flex flex-col gap-3">
                        <button
                            @click="applyContinuation"
                            class="w-full rounded-lg bg-orange-500 px-4 py-3 text-left text-sm font-medium text-white hover:bg-orange-600"
                        >
                            <div class="font-semibold">↩ 続きとして設定</div>
                            <div class="mt-0.5 text-xs opacity-90">元ジョブと連動。作業時間を合算し、完了時に元ジョブも完了します。</div>
                        </button>
                        <button
                            @click="applyAsNew"
                            class="w-full rounded-lg bg-blue-500 px-4 py-3 text-left text-sm font-medium text-white hover:bg-blue-600"
                        >
                            <div class="font-semibold">新規として引用</div>
                            <div class="mt-0.5 text-xs opacity-90">内容を引き継いで独立した新しいジョブとして作成します。</div>
                        </button>
                        <button
                            @click="showContinueModal = false"
                            class="w-full rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                        >
                            キャンセル
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- 依頼ジョブ選択モーダル -->
        <Teleport to="body">
            <div v-if="showRequestModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 pt-16" @click.self="showRequestModal = false">
                <div class="mx-4 w-full max-w-2xl rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h2 class="text-lg font-semibold text-indigo-800">依頼ジョブを選択</h2>
                        <button @click="showRequestModal = false" class="text-gray-400 hover:text-gray-700">✕</button>
                    </div>
                    <div class="p-5">
                        <p class="mb-4 text-sm text-gray-600">自分宛に依頼されたジョブを選択すると、このマイジョブが「依頼ジョブの対応済み登録」として扱われます。依頼ジョブは案件詳細から非表示になります。</p>
                        <div v-if="requestLoading" class="py-8 text-center text-sm text-gray-500">読み込み中...</div>
                        <div v-else-if="requestRecords.length === 0" class="py-8 text-center text-sm text-gray-500">未対応の依頼ジョブはありません</div>
                        <div v-else class="max-h-80 overflow-y-auto rounded border border-gray-200">
                            <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="sticky top-0 bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">依頼者</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">案件</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">タイトル</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">締め切り</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="rec in requestRecords" :key="rec.id" @click="applyRequestJob(rec)" class="cursor-pointer hover:bg-indigo-50">
                                        <td class="px-3 py-2">{{ rec.sender_name }}</td>
                                        <td class="max-w-[8rem] truncate px-3 py-2">{{ rec.project_job_name }}</td>
                                        <td class="max-w-[10rem] truncate px-3 py-2 font-medium">{{ rec.title }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ rec.desired_end_date ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentFormUser from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import FileInfoDisplay from '@/Components/FileInfoDisplay.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const projectJob = ref(page.props.projectJob || null);
const members = page.props.members || [];
const userClients = page.props.userClients || [];
const userProjects = page.props.userProjects || [];
const defaultUserId = page.props.auth && page.props.auth.user ? page.props.auth.user.id : null;

// 依頼ジョブ由来かどうか（source_file_info または source_job_type があれば）
const hasSourceJob = !!(page.props.source_file_info || page.props.source_job_type);
const sourceFileInfo = page.props.source_file_info ?? null;

const formAssignments = ref(page.props.assignments || []);
const formKey = ref(0);

// ── 過去データモーダル ──────────────────────────────────────────────────
const showModal = ref(false);
const modalMode = ref('date');
const dateRange = ref('yesterday');
const hideCompleted = ref(false);
const selectedClientId = ref('');
const selectedProjectId = ref('');
const modalLoading = ref(false);
const records = ref([]);
const modalClients = ref([]);
const modalProjects = ref([]);

const dateRangeOptions = [
    { value: 'yesterday', label: '前日' },
    { value: '7days', label: '過去7日' },
    { value: '30days', label: '過去30日' },
];

const filteredProjects = computed(() => {
    if (!selectedClientId.value) return modalProjects.value;
    return modalProjects.value.filter((p) => String(p.client_id) === String(selectedClientId.value));
});

function openModal() {
    showModal.value = true;
    fetchData();
}

function closeModal() {
    showModal.value = false;
}

function buildParams() {
    const p = new URLSearchParams();
    p.set('mode', modalMode.value);
    p.set('hide_completed', hideCompleted.value ? '1' : '0');
    if (modalMode.value === 'date') {
        p.set('date_range', dateRange.value);
    } else {
        if (selectedClientId.value) p.set('client_id', selectedClientId.value);
        if (selectedProjectId.value) p.set('project_job_id', selectedProjectId.value);
    }
    return p.toString();
}

async function fetchData() {
    modalLoading.value = true;
    try {
        const url = route('user.myjobbox.past_data') + '?' + buildParams();
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            records.value = data.records ?? [];
            if (data.clients?.length) modalClients.value = data.clients;
            if (modalMode.value === 'project' && data.projects) modalProjects.value = data.projects;
        }
    } catch (e) {
        // ignore
    } finally {
        modalLoading.value = false;
    }
}

watch([modalMode, dateRange, hideCompleted, selectedProjectId], () => {
    if (showModal.value) fetchData();
});

watch(selectedClientId, () => {
    selectedProjectId.value = '';
    if (showModal.value) fetchData();
});

// ── 続き確認モーダル ──────────────────────────────────────────────────
const showContinueModal = ref(false);
const pendingRecord = ref(null);

function selectRecord(rec) {
    pendingRecord.value = rec;
    showContinueModal.value = true;
    closeModal();
}

function applyContinuation() {
    const rec = pendingRecord.value;
    if (!rec) return;
    formAssignments.value = [
        {
            project_job_id: rec.project_job_id,
            _client_id: rec.client_id ?? '',
            title_suffix: rec.title ?? '',
            detail: rec.detail ?? '',
            work_item_type_id: rec.work_item_type_id ?? null,
            size_id: rec.size_id ?? null,
            stage_id: rec.stage_id ?? null,
            difficulty_id: rec.difficulty_id ?? null,
            desired_end_date: rec.desired_end_date ?? '',
            desired_time: rec.desired_time ?? null,
            estimated_hours: rec.estimated_hours ?? null,
            amounts: null,
            status_id: null,
            source_assignment_id: rec.id,
        },
    ];
    formKey.value += 1;
    showContinueModal.value = false;
    pendingRecord.value = null;
}

function applyAsNew() {
    const rec = pendingRecord.value;
    if (!rec) return;
    formAssignments.value = [
        {
            project_job_id: rec.project_job_id,
            _client_id: rec.client_id ?? '',
            title_suffix: rec.title ?? '',
            detail: rec.detail ?? '',
            work_item_type_id: rec.work_item_type_id ?? null,
            size_id: rec.size_id ?? null,
            stage_id: rec.stage_id ?? null,
            difficulty_id: rec.difficulty_id ?? null,
            desired_end_date: rec.desired_end_date ?? '',
            desired_time: rec.desired_time ?? null,
            estimated_hours: rec.estimated_hours ?? null,
            amounts: null,
            status_id: null,
        },
    ];
    formKey.value += 1;
    showContinueModal.value = false;
    pendingRecord.value = null;
}

// ── 依頼ジョブ選択モーダル（supersede）────────────────────────────────
const showRequestModal = ref(false);
const requestRecords = ref([]);
const requestLoading = ref(false);

function openRequestModal() {
    showRequestModal.value = true;
    fetchPendingRequests();
}

async function fetchPendingRequests() {
    requestLoading.value = true;
    try {
        const currentProjectId = formAssignments.value[0]?.project_job_id ?? null;
        const params = new URLSearchParams();
        if (currentProjectId) params.set('project_job_id', currentProjectId);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(route('user.myjobbox.pending_requests') + (params.toString() ? '?' + params.toString() : ''), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            requestRecords.value = data.records ?? [];
        }
    } catch (e) {
        // ignore
    } finally {
        requestLoading.value = false;
    }
}

function applyRequestJob(rec) {
    formAssignments.value = [
        {
            project_job_id: rec.project_job_id,
            title_suffix: rec.title ?? '',
            desired_end_date: rec.desired_end_date ?? '',
            supersedes_assignment_id: rec.id,
        },
    ];
    formKey.value += 1;
    showRequestModal.value = false;
}
</script>

<style scoped></style>
