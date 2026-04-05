<template>
    <AppLayout :title="sheet.name + ' - 進行管理表'">
        <template #header>
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">進行管理表：{{ sheet.name }}</h2>
                </div>
                <!-- 案件情報バー -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-sm text-gray-600">
                    <span v-if="projectJob.client_name" class="font-medium text-gray-700">{{ projectJob.client_name }}</span>
                    <span v-if="projectJob.client_name && projectJob.title" class="text-gray-400">/</span>
                    <span class="font-medium text-indigo-700">{{ projectJob.title }}</span>
                    <span v-if="projectJob.size_name" class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">サイズ: {{ projectJob.size_name }}</span>
                    <span v-if="projectJob.page_count" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">総{{ projectJob.page_count }}ページ</span>
                </div>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- ツールバー -->
            <div class="mb-4 flex items-center gap-3">
                <button
                    type="button"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    @click="backToJob"
                >
                    ← 案件詳細に戻る
                </button>
            </div>

            <!-- 進行管理表 -->
            <div v-if="!localColumnConfig.length" class="py-8 text-center text-gray-400">列が定義されていません。</div>
            <ProgressTable
                v-else
                :rows="localRows"
                :column-config="localColumnConfig"
                :cells="localCells"
                :users="[]"
                :stages="[]"
                :sizes="[]"
                :assignments="[]"
                :work-item-types="[]"
                :can-edit="false"
                :edit-mode="false"
                :job-link-only="true"
                :auth-user-id="page.props.auth?.user?.id ?? null"
                @job-link-open="openJobLink"
                @job-link-detail="openJobLinkDetail"
                @complete-assignment="onCompleteAssignment"
            />
        </div>

        <!-- ジョブリンク詳細モーダル -->
        <div
            v-if="detailModal.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click.self="detailModal.open = false"
        >
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">登録済みジョブ</h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">タイトル</dt>
                        <dd class="text-gray-800">{{ detailModal.title }}</dd>
                    </div>
                    <div v-if="detailModal.endDate">
                        <dt class="text-xs font-medium text-gray-500">期限</dt>
                        <dd class="text-gray-800">{{ detailModal.endDate }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">状態</dt>
                        <dd :class="detailModal.completed ? 'text-yellow-700' : 'text-blue-700'">
                            {{ detailModal.completed ? '完了' : '未完了' }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-5 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        @click="detailModal.open = false"
                    >
                        閉じる
                    </button>
                    <button
                        v-if="detailModal.assignmentId && !detailModal.completed"
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        :disabled="detailModal.completing"
                        @click="completeFromModal"
                    >
                        {{ detailModal.completing ? '処理中…' : '完了にする' }}
                    </button>
                    <button
                        v-if="detailModal.assignmentId"
                        type="button"
                        class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                        @click="goToMyJob"
                    >
                        マイジョブを開く
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import ProgressTable from '@/Components/ProgressTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    sheet: Object,
    projectJob: Object,
});

const page = usePage();

const localColumnConfig = ref(JSON.parse(JSON.stringify(props.sheet.column_config ?? [])));
const localRows = ref((props.sheet.rows ?? []).map((r) => ({ ...r })));
const localCells = ref((props.sheet.cells ?? []).map((c) => ({ ...c })));

// props が更新されたときに同期
watch(
    () => props.sheet,
    (fresh) => {
        if (fresh) {
            localColumnConfig.value = JSON.parse(JSON.stringify(fresh.column_config ?? []));
            localRows.value = (fresh.rows ?? []).map((r) => ({ ...r }));
            localCells.value = (fresh.cells ?? []).map((c) => ({ ...c }));
        }
    },
);

// ── ナビゲーション ──────────────────────────────────────────────────

function backToJob() {
    try {
        router.visit(route('user.project_jobs.show', { projectJob: props.projectJob.id }));
    } catch {
        window.location.href = route('user.project_jobs.show', { projectJob: props.projectJob.id });
    }
}

// ── ジョブリンク「＋ 登録」 ───────────────────────────────────────

function openJobLink({ rowId, colKey }) {
    const row = localRows.value.find((r) => r.id === rowId);
    const title = row?.label ?? '';
    const params = {
        title,
        project_job_id: props.projectJob.id,
        progress_sheet_id: props.sheet.id,
        row_id: rowId,
        col_key: colKey,
    };
    if (props.projectJob.client_id) params.client_id = props.projectJob.client_id;
    try {
        router.visit(route('events.create_job', params));
    } catch {
        window.location.href = route('events.create_job', params);
    }
}

// ── ジョブリンク「詳細」 ──────────────────────────────────────────

const detailModal = ref({
    open: false,
    title: '',
    endDate: null,
    completed: false,
    assignmentId: null,
    completing: false,
});

function openJobLinkDetail({ assignmentId, assignmentTitle, endDate, completed }) {
    detailModal.value = {
        open: true,
        title: assignmentTitle ?? '(タイトルなし)',
        endDate: endDate ?? null,
        completed: !!completed,
        assignmentId: assignmentId ?? null,
        completing: false,
    };
}

async function onCompleteAssignment({ assignmentId }) {
    if (!assignmentId) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('myjobbox.assignments.complete', { assignment: assignmentId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            const cellIdx = localCells.value.findIndex((c) => c.assignment_id === assignmentId);
            if (cellIdx >= 0) {
                localCells.value.splice(cellIdx, 1, { ...localCells.value[cellIdx], assignment_completed: true });
            }
        }
    } catch {
        /* ignore */
    }
}

async function completeFromModal() {
    const assignmentId = detailModal.value.assignmentId;
    if (!assignmentId || detailModal.value.completed) return;
    detailModal.value.completing = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('myjobbox.assignments.complete', { assignment: assignmentId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            detailModal.value.completed = true;
            const cellIdx = localCells.value.findIndex((c) => c.assignment_id === assignmentId);
            if (cellIdx >= 0) {
                localCells.value.splice(cellIdx, 1, { ...localCells.value[cellIdx], assignment_completed: true });
            }
        }
    } catch {
        /* ignore */
    } finally {
        detailModal.value.completing = false;
    }
}

function goToMyJob() {
    const id = detailModal.value.assignmentId;
    if (!id) return;
    try {
        router.visit(route('user.myjobbox.show', { assignment: id }));
    } catch {
        window.location.href = route('user.myjobbox.show', { assignment: id });
    }
}
</script>
