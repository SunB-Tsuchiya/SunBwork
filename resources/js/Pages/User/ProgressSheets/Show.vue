<template>
    <AppLayout :title="sheet.name + ' - 進行管理表'">
        <template #header>
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-3">
                    <Link
                        :href="backUrl"
                        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                    >← 案件詳細に戻る</Link>
                    <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">進行管理表：{{ sheet.name }}</h2>
                    <button
                        type="button"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        @click="openPrint"
                    >印刷</button>
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

        <!-- 進行管理表 -->
        <div class="mt-4">
            <div v-if="!localColumnConfig.length" class="rounded bg-white p-6 shadow py-8 text-center text-gray-400">列が定義されていません。</div>
            <div
                v-else
                ref="tableWrapRef"
                class="overflow-auto rounded bg-white shadow px-4 py-2"
                :style="{ height: tableHeight, minHeight: '200px', width: '100vw', marginLeft: 'calc(-50vw + 50%)' }"
            >
                <ProgressTable
                    :rows="localRows"
                    :column-config="localColumnConfig"
                    :cells="localCells"
                    :users="props.users"
                    :stages="[]"
                    :sizes="[]"
                    :assignments="[]"
                    :work-item-types="[]"
                    :can-edit="false"
                    :edit-mode="false"
                    :job-link-only="true"
                    :auth-user-id="page.props.auth?.user?.id ?? null"
                    @job-link-open="openJobLink"
                    @worker-job-register="onWorkerJobRegister"
                    @worker-job-detail="onWorkerJobDetail"
                    @job-link-detail="openJobLinkDetail"
                    @complete-assignment="onCompleteAssignment"
                    @worker-complete="onWorkerComplete"
                />
            </div>
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
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    sheet: Object,
    projectJob: Object,
    users: { type: Array, default: () => [] },
});

const page = usePage();

function openPrint() {
    window.open(route('user.progress_sheets.print', { sheet: props.sheet.id }), '_blank');
}

// ── テーブルコンテナの動的高さ計算 ──────────────────────────
const tableWrapRef = ref(null);
const tableHeight = ref('calc(100vh - 300px)');

function calcTableHeight() {
    if (!tableWrapRef.value) return;
    const top = tableWrapRef.value.getBoundingClientRect().top;
    tableHeight.value = `${window.innerHeight - top - 4}px`;
}

onMounted(() => {
    calcTableHeight();
    window.addEventListener('resize', calcTableHeight);
    document.body.style.overflowX = 'hidden';
});

onUnmounted(() => {
    window.removeEventListener('resize', calcTableHeight);
    document.body.style.overflowX = '';
});

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

const backUrl = computed(() => {
    const base = route('user.project_jobs.show', { projectJob: props.projectJob.id });
    const backTab = new URLSearchParams(window.location.search).get('back_tab');
    return backTab ? `${base}?tab=${backTab}` : base;
});

function backToJob() {
    try {
        router.visit(route('user.project_jobs.show', { projectJob: props.projectJob.id }));
    } catch {
        window.location.href = route('user.project_jobs.show', { projectJob: props.projectJob.id });
    }
}

// ── ジョブタイトル構築ヘルパー ────────────────────────────────────

function normalizeTitle(title) {
    return title.replace(/[ーｰ\-－—–]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
}

function findBreadcrumb(nodes, key, path = []) {
    for (const node of nodes) {
        const currentPath = [...path, node.label];
        if (node.key === key) return currentPath;
        if (node.children?.length) {
            const found = findBreadcrumb(node.children, key, currentPath);
            if (found) return found;
        }
    }
    return null;
}

function findNodeByKey(nodes, key) {
    for (const node of nodes) {
        if (node.key === key) return node;
        if (node.children?.length) {
            const found = findNodeByKey(node.children, key);
            if (found) return found;
        }
    }
    return null;
}

function findParentGroup(nodes, key) {
    for (const node of nodes) {
        if (node.children?.some((c) => c.key === key)) return node;
        if (node.children?.length) {
            const found = findParentGroup(node.children, key);
            if (found) return found;
        }
    }
    return null;
}

/** ジョブタイトルを自動構築：「親行ラベル_縦軸ラベル_横軸中見出し_列ラベル」 */
function buildJobTitle(rowId, colKey) {
    const row = localRows.value.find((r) => r.id === rowId);
    const parentRow = row?.parent_id ? localRows.value.find((r) => r.id === row.parent_id) : null;
    const breadcrumb = findBreadcrumb(localColumnConfig.value, colKey); // [top, ..., leaf]
    const parentPath = breadcrumb ? breadcrumb.slice(0, -1).filter(Boolean) : [];

    // joblink（「登録」ボタン）は自身のラベルではなく兄弟の worker/proof ラベルを使う
    const leafNode = findNodeByKey(localColumnConfig.value, colKey);
    let leafLabel = '';
    if (leafNode?.type === 'joblink') {
        const parent = findParentGroup(localColumnConfig.value, colKey);
        const sibling = parent?.children?.find((c) => c.key !== colKey && c.type !== 'joblink');
        leafLabel = sibling?.label ?? '';
    } else {
        leafLabel = leafNode?.label ?? '';
    }

    const colPart = [...parentPath, leafLabel].filter(Boolean).join('_');
    const rowPart = [parentRow?.label, row?.label].filter(Boolean).join('_');
    return normalizeTitle([rowPart, colPart].filter(Boolean).join('_'));
}

// ── worker型「＋ 登録」 ──────────────────────────────────────────
// 担当者が自分自身の場合は assign() API を直接呼ぶ（フォーム不要）
// それ以外は通常のジョブ作成フォームへ遷移

function onWorkerJobRegister({ rowId, colKey, userId }) {
    const authUserId = page.props.auth?.user?.id;
    if (userId && authUserId && String(userId) === String(authUserId)) {
        const cell = localCells.value.find((c) => c.row_id === rowId && c.col_key === colKey);
        if (cell?.id) {
            router.post(
                route('progress_sheets.cells.assign', { sheet: props.sheet.id, cell: cell.id }),
                {},
                { preserveScroll: true },
            );
            return;
        }
    }
    // 担当者未定または自分以外: ジョブ作成フォームへ
    openJobLink({ rowId, colKey });
}

// ── worker型「詳細」モーダル ──────────────────────────────────────

function onWorkerJobDetail({ assignmentId, rowId, colKey }) {
    const cell = localCells.value.find((c) => c.row_id === rowId && c.col_key === colKey);
    detailModal.value = {
        open: true,
        title: cell?.assignment_title ?? '(タイトルなし)',
        endDate: cell?.assignment_end_date ?? null,
        completed: !!(cell?.assignment_completed),
        assignmentId: assignmentId ?? null,
        completing: false,
    };
}

// ── ジョブリンク「＋ 登録」 ───────────────────────────────────────

function openJobLink({ rowId, colKey }) {
    const title = buildJobTitle(rowId, colKey);
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

async function onWorkerComplete({ cellId, assignmentId, rowId, colKey }) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        if (assignmentId) {
            // ジョブ紐づきあり: myjobbox complete (progress_cells.completed_at も更新される)
            const res = await fetch(route('myjobbox.assignments.complete', { assignment: assignmentId }), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!res.ok) return;
        } else if (cellId) {
            // ジョブなし・担当者のみ: セル単体の complete API
            const res = await fetch(route('user.progress_cells.complete', { cell: cellId }), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!res.ok) return;
        } else {
            return;
        }
        // ローカルセルを更新
        const idx = localCells.value.findIndex((c) => c.row_id === rowId && c.col_key === colKey);
        if (idx >= 0) {
            localCells.value.splice(idx, 1, {
                ...localCells.value[idx],
                completed_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                assignment_completed: assignmentId ? true : localCells.value[idx].assignment_completed,
            });
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
