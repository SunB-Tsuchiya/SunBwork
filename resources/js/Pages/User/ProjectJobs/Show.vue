<template>
    <AppLayout title="案件詳細">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('user.project_jobs.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 案件一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">案件詳細</h2>
            </div>
        </template>

        <!-- ── スティッキーヘッダー ──────────────────────────── -->
        <div class="sticky top-0 z-20 rounded-t bg-white px-6 pt-6 pb-0 shadow-md">

            <!-- ── タイトル行 ──────────────────────────────────── -->
            <div class="mb-4">
                <p class="text-sm font-medium text-gray-400">
                    {{ job.client?.name || 'クライアント未設定' }}
                </p>
                <h1 class="mt-0.5 text-2xl font-bold text-gray-900">
                    {{ job.title || job.name || '（案件名なし）' }}
                </h1>
                <p class="mt-1 text-xs text-gray-500">
                    <span v-if="job.jobcode">伝票番号: {{ job.jobcode }}　</span>
                    <span v-if="job.user?.name">リーダー: {{ job.user.name }}</span>
                </p>
                <p class="mt-0.5 text-xs text-gray-500">
                    <span v-if="job.size?.name">版型: <span class="font-medium text-gray-700">{{ job.size.name }}</span>　</span>
                    <span v-if="job.page_count">総ページ数: <span class="font-medium text-gray-700">{{ job.page_count }} ページ</span></span>
                </p>
                <p v-if="subCoordinators.length > 0" class="mt-0.5 text-xs text-gray-400">
                    サブリーダー: {{ subCoordinators.map((c) => c.name).join('、') }}
                </p>
                <span
                    v-if="job.completed"
                    class="mt-2 inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800"
                >完了</span>
            </div>

            <!-- ── タブバー ──────────────────────────────────────── -->
            <div class="mt-2 flex gap-1 border-b border-gray-200">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="[
                        'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                        activeTab === tab.key
                            ? 'border-indigo-500 text-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    ]"
                    @click="activeTab = tab.key"
                >{{ tab.label }}</button>
            </div>
        </div><!-- /sticky header -->

        <!-- ── タブコンテンツ ─────────────────────────────────── -->
        <div class="rounded-b bg-white px-6 pb-6 shadow-md">

            <!-- 詳細メモ（概要タブのみ） -->
            <div
                v-if="activeTab === 'overview' && job.detail"
                class="mt-4 whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
            >{{ typeof job.detail === 'string' ? job.detail : JSON.stringify(job.detail) }}</div>

            <div class="divide-y divide-gray-100">

                <!-- ── メンバーセクション ──────────────────────── -->
                <section v-show="activeTab === 'overview'" class="py-5">
                    <h3 class="mb-3 font-semibold text-gray-800">メンバー</h3>
                    <div class="space-y-2">
                        <div v-if="job.user" class="flex items-center gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-yellow-700">リーダー</span>
                            <span class="flex items-center gap-1.5 rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm font-medium text-gray-800">
                                <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                                {{ job.user.name }}
                            </span>
                        </div>
                        <div v-if="subCoordinators.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-orange-700">サブリーダー</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="c in subCoordinators"
                                    :key="c.id"
                                    class="flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-sm font-medium text-gray-800"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-orange-400"></span>
                                    {{ c.name }}
                                </span>
                            </div>
                        </div>
                        <div v-if="coordinatorMembers.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-indigo-700">Coordinator</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="m in coordinatorMembers"
                                    :key="m.id"
                                    class="flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-sm font-medium text-gray-800"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-indigo-400"></span>
                                    {{ m.user ? m.user.name : '（ユーザー情報なし）' }}
                                </span>
                            </div>
                        </div>
                        <div v-if="userMembers.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-green-700">User</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="m in userMembers"
                                    :key="m.id"
                                    class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-sm font-medium text-gray-800"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-green-400"></span>
                                    {{ m.user ? m.user.name : '（ユーザー情報なし）' }}
                                </span>
                            </div>
                        </div>
                        <p v-if="!job.user && !subCoordinators.length && !coordinatorMembers.length && !userMembers.length" class="text-sm text-gray-400">メンバー未登録</p>
                    </div>

                    <!-- 校正依頼（情報出版部署のみ表示） -->
                    <div v-if="$page.props.auth.featureFlags.proofRequest" class="mt-5 border-t pt-4">
                        <div class="mb-2 flex items-center gap-3">
                            <h3 class="font-semibold text-gray-800">校正依頼</h3>
                            <button
                                v-if="!job.completed"
                                type="button"
                                class="rounded border border-pink-300 bg-pink-50 px-3 py-1.5 text-sm font-medium text-pink-700 hover:bg-pink-100"
                                @click="openProofModal(null)"
                            >+ 校正依頼を送る</button>
                        </div>
                        <p class="text-sm text-gray-400">
                            この案件に関する校正は「校正状況」タブから確認できます。
                        </p>
                    </div>
                </section>

                <!-- ── 進行管理表セクション ──────────────────── -->
                <section v-show="activeTab === 'progress'" class="py-5">
                    <h3 class="mb-3 font-semibold text-gray-800">進行管理表</h3>
                    <div v-if="progressSheets.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">シート名</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="sheet in progressSheets" :key="sheet.id"
                                    class="cursor-pointer hover:bg-blue-50"
                                    @click="openSheet(sheet)">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ sheet.name }}</td>
                                    <td class="px-4 py-2">
                                        <button
                                            type="button"
                                            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                            @click.stop="openSheet(sheet)"
                                        >開く</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400">進行管理表なし</p>
                </section>

                <!-- ── 管理シートセクション ──────────────────── -->
                <section v-show="activeTab === 'workflow'" class="py-5">
                    <h3 class="mb-3 font-semibold text-gray-800">管理シート</h3>
                    <div v-if="workflowSheets.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">シート名</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="ws in workflowSheets" :key="ws.id"
                                    class="cursor-pointer hover:bg-blue-50"
                                    @click="openWorkflowSheet(ws)">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ ws.name }}</td>
                                    <td class="px-4 py-2">
                                        <button
                                            type="button"
                                            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                            @click.stop="openWorkflowSheet(ws)"
                                        >開く</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400">管理シートなし</p>
                </section>

                <!-- ── スケジュールセクション ──────────────────── -->
                <section v-show="activeTab === 'schedule'" class="py-5">
                    <ProjectCalendar
                        :schedules="schedules"
                        :events="scheduleEvents"
                        :comments="[]"
                        :memos="[]"
                        :project="{ id: job.id, name: job.title, jobcode: job.jobcode ?? null }"
                        :weekPostsUrl="scheduleWeekPostsUrl"
                        :showMemoButton="false"
                        :uniformColors="true"
                        :stayInPlace="true"
                        :readonly="true"
                    />
                </section>

                <!-- ── 伝票情報タブ ──────────────────────────────── -->
                <section v-show="activeTab === 'voucher'" class="py-5">
                    <h3 class="mb-3 font-semibold text-gray-800">伝票情報</h3>
                    <div v-if="jobImageUrl">
                        <div class="relative inline-block">
                            <img
                                :src="jobImageUrl"
                                :alt="jobOriginalFilename ?? '伝票画像'"
                                class="h-48 w-auto cursor-pointer rounded-lg border border-gray-200 object-contain shadow-sm"
                                @click="showVoucherLightbox = true"
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="max-w-xs truncate text-xs text-gray-500">{{ jobOriginalFilename }}</span>
                            <button
                                type="button"
                                class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                @click="showVoucherLightbox = true"
                            >🔍 拡大</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">伝票画像なし</p>
                </section>

                <!-- ── ジョブ履歴セクション ───────────────────── -->
                <section v-show="activeTab === 'history'" class="py-5">
                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            class="flex items-center gap-1 font-semibold text-gray-800 hover:text-gray-600"
                            @click="historyOpen = !historyOpen"
                        >
                            <span>{{ historyOpen ? '▼' : '▶' }}</span>
                            <span>ジョブ履歴</span>
                            <span class="ml-1 text-xs font-normal text-gray-400">{{ historyDisplayCount }}件</span>
                        </button>
                        <label v-if="historyOpen" class="flex cursor-pointer items-center gap-1.5 text-sm text-gray-600 select-none">
                            <input type="checkbox" v-model="hideHistoryCompleted" class="h-4 w-4 rounded border-gray-300" />
                            完了を表示しない
                        </label>
                    </div>

                    <template v-if="historyOpen">
                        <div v-if="historyDisplayCount === 0 && historyHiddenCount === 0" class="text-sm text-gray-400">
                            {{ (page.props.jobHistory || []).length === 0 ? 'ジョブ履歴なし' : '表示するデータがありません。' }}
                        </div>
                        <template v-else>

                            <!-- ── 進行表に関連するジョブ ── -->
                            <div class="mb-4">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded bg-indigo-50 px-4 py-2 text-left text-sm font-semibold text-indigo-800 hover:bg-indigo-100"
                                    @click="historyLinkedOpen = !historyLinkedOpen"
                                >
                                    <span>{{ historyLinkedOpen ? '▼' : '▶' }}</span>
                                    <span>進行表に関連するジョブ</span>
                                    <span class="ml-1 text-xs font-normal text-indigo-500">{{ historyLinkedCount }} 件</span>
                                </button>
                                <template v-if="historyLinkedOpen">
                                    <div v-if="historyGroupsLinked.length === 0" class="mt-2 px-4 text-sm text-gray-400">
                                        {{ historyLinkedCount === 0 ? '該当なし' : '表示するデータがありません。' }}
                                    </div>
                                    <div v-else class="overflow-x-auto">
                                        <template v-for="group in historyGroupsLinked" :key="group.key">
                                            <div class="mt-3 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-2">
                                                {{ group.label }}
                                                <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                                            </div>
                                            <table class="w-full table-fixed border" style="min-width: 760px;">
                                                <colgroup>
                                                    <col style="width: 100px">
                                                    <col style="width: 100px">
                                                    <col style="width: 140px">
                                                    <col>
                                                    <col style="width: 100px">
                                                </colgroup>
                                                <thead>
                                                    <tr class="bg-gray-50">
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">発信者</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">受信者</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作業日/作成日</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="m in group.items"
                                                        :key="m.id ?? m.project_job_assignment?.id"
                                                        :class="['cursor-pointer hover:bg-indigo-50', m.__chain_depth > 0 ? 'bg-orange-50' : '']"
                                                        @click.prevent="historyRowClick(m, $event)"
                                                        role="button"
                                                    >
                                                        <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetSender(m) }}</td>
                                                        <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetRecipients(m) }}</td>
                                                        <td class="break-words whitespace-pre-line border px-3 py-2 text-sm text-gray-600">{{ historyGetWorkDate(m) }}</td>
                                                        <td class="break-words border px-3 py-2 text-sm">
                                                            <span v-if="m.__chain_depth > 0" class="mr-1 inline-block text-orange-300" :style="{ paddingLeft: (m.__chain_depth * 12) + 'px' }">└</span>
                                                            {{ m.subject || (m.body && m.body.slice(0, 60)) }}
                                                        </td>
                                                        <td class="border px-3 py-2">
                                                            <span :class="statusBadgeClass(historyGetStatus(m))" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">{{ historyGetStatus(m) }}</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- ── 進行表に関連しないジョブ ── -->
                            <div>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded bg-gray-100 px-4 py-2 text-left text-sm font-semibold text-gray-700 hover:bg-gray-200"
                                    @click="historyOtherOpen = !historyOtherOpen"
                                >
                                    <span>{{ historyOtherOpen ? '▼' : '▶' }}</span>
                                    <span>進行表に関連しないジョブ</span>
                                    <span class="ml-1 text-xs font-normal text-gray-500">{{ historyOtherCount }} 件</span>
                                </button>
                                <template v-if="historyOtherOpen">
                                    <div v-if="historyGroupsOther.length === 0" class="mt-2 px-4 text-sm text-gray-400">
                                        {{ historyOtherCount === 0 ? '該当なし' : '表示するデータがありません。' }}
                                    </div>
                                    <div v-else class="overflow-x-auto">
                                        <template v-for="group in historyGroupsOther" :key="group.key">
                                            <div class="mt-3 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-2">
                                                {{ group.label }}
                                                <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                                            </div>
                                            <table class="w-full table-fixed border" style="min-width: 760px;">
                                                <colgroup>
                                                    <col style="width: 100px">
                                                    <col style="width: 100px">
                                                    <col style="width: 140px">
                                                    <col>
                                                    <col style="width: 100px">
                                                </colgroup>
                                                <thead>
                                                    <tr class="bg-gray-50">
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">発信者</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">受信者</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作業日/作成日</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                                        <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="m in group.items"
                                                        :key="m.id ?? m.project_job_assignment?.id"
                                                        :class="['cursor-pointer hover:bg-gray-100', m.__chain_depth > 0 ? 'bg-orange-50' : '']"
                                                        @click.prevent="historyRowClick(m, $event)"
                                                        role="button"
                                                    >
                                                        <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetSender(m) }}</td>
                                                        <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetRecipients(m) }}</td>
                                                        <td class="break-words whitespace-pre-line border px-3 py-2 text-sm text-gray-600">{{ historyGetWorkDate(m) }}</td>
                                                        <td class="break-words border px-3 py-2 text-sm">
                                                            <span v-if="m.__chain_depth > 0" class="mr-1 inline-block text-orange-300" :style="{ paddingLeft: (m.__chain_depth * 12) + 'px' }">└</span>
                                                            {{ m.subject || (m.body && m.body.slice(0, 60)) }}
                                                        </td>
                                                        <td class="border px-3 py-2">
                                                            <span :class="statusBadgeClass(historyGetStatus(m))" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">{{ historyGetStatus(m) }}</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </template>
                                    </div>
                                </template>
                            </div>

                        </template>

                        <div class="mt-3 text-sm text-gray-600">
                            表示中 {{ historyDisplayCount }} 件
                            <span v-if="hideHistoryCompleted && historyHiddenCount > 0" class="ml-2 text-xs text-gray-400">（完了 {{ historyHiddenCount }} 件を非表示）</span>
                        </div>
                    </template><!-- /historyOpen -->
                </section>

            </div><!-- /divide-y -->
        </div><!-- /tab content -->
    </AppLayout>

    <!-- 伝票画像ライトボックス -->
    <Teleport to="body">
        <div
            v-if="showVoucherLightbox && jobImageUrl"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
            @click.self="showVoucherLightbox = false"
        >
            <div class="relative max-h-[90vh] max-w-[90vw]">
                <img
                    :src="jobImageUrl"
                    :alt="jobOriginalFilename ?? '伝票画像'"
                    class="max-h-[85vh] max-w-[88vw] rounded-lg object-contain"
                />
                <button
                    type="button"
                    class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-800 shadow-md hover:bg-gray-100"
                    @click="showVoucherLightbox = false"
                >✕</button>
            </div>
        </div>
    </Teleport>

    <ProofRequestModal
        :show="showProofModal"
        :initial-title="proofTargetAssignment?.title || job.title || ''"
        :project-job-assignment-id="proofTargetAssignment?.id || null"
        :project-job-id="job.id || null"
        @close="showProofModal = false"
    />
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProjectCalendar from '@/Components/ProjectCalendar.vue';
import ProofRequestModal from '@/Components/ProofRequestModal.vue';
import { scheduleStatusColor } from '@/Helpers/scheduleColor.js';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const page = usePage();
const job  = page.props.job || {};

const showProofModal = ref(false);
const proofTargetAssignment = ref(null);
function openProofModal(assignment = null) {
    proofTargetAssignment.value = assignment;
    showProofModal.value = true;
}

const schedules     = computed(() => Array.isArray(page.props.schedules) ? page.props.schedules : []);
const members       = page.props.members || [];
const subCoordinators = computed(() => page.props.subCoordinators || []);
const progressSheets  = computed(() => page.props.progressSheets || []);
const workflowSheets  = computed(() => page.props.workflowSheets  || []);

const jobImageUrl         = ref(job.image_url ?? null);
const jobOriginalFilename = ref(job.original_filename ?? null);
const showVoucherLightbox = ref(false);

// ── メンバー分類 ───────────────────────────────────────────────────────────
const regularMembers = computed(() => {
    const leaderUserId = job.user_id ?? null;
    const subCoIds     = new Set(subCoordinators.value.map((c) => c.id));
    return members.filter((m) => {
        if (!m.user) return true;
        if (leaderUserId && m.user.id === leaderUserId) return false;
        if (subCoIds.has(m.user.id)) return false;
        return true;
    });
});

const coordinatorMembers = computed(() =>
    regularMembers.value.filter((m) => m.user?.user_role === 'coordinator' || m.user?.user_role === 'clerk')
);

const userMembers = computed(() =>
    regularMembers.value.filter((m) => m.user?.user_role !== 'coordinator' && m.user?.user_role !== 'clerk')
);

// ── タブ定義 ─────────────────────────────────────────────────────────────
const tabs = [
    { key: 'overview', label: '概要・メンバー' },
    { key: 'progress', label: '進行管理表' },
    { key: 'workflow', label: '管理シート' },
    { key: 'schedule', label: 'スケジュール' },
    { key: 'voucher',  label: '伝票情報' },
    { key: 'history',  label: 'ジョブ履歴' },
];
const activeTab = ref(new URLSearchParams(window.location.search).get('tab') || 'overview');

// ── 進行管理表を開く ──────────────────────────────────────────────────────
function openSheet(sheet) {
    router.visit(route('user.progress_sheets.show', { sheet: sheet.id }) + '?back_tab=progress');
}

function openWorkflowSheet(ws) {
    router.visit(route('user.workflow_sheets.show', { sheet: ws.id }) + '?back_tab=workflow');
}

// ── スケジュール（カレンダー用） ──────────────────────────────────────────
const scheduleEvents = computed(() =>
    schedules.value.map((s) => {
        const startDateOnly = s.start_date ? String(s.start_date).split('T')[0] : null;
        const endDateOnly   = s.end_date   ? String(s.end_date).split('T')[0]   : null;
        let endForCalendar = endDateOnly;
        if (endDateOnly) {
            try {
                const [y, m, d] = endDateOnly.split('-').map(Number);
                const dt = new Date(y, m - 1, d);
                dt.setDate(dt.getDate() + 1);
                endForCalendar = `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
            } catch { /* ignore */ }
        }
        const isCompleted = !!s.completed_at || (s.progress ?? 0) >= 100;
        const uc = scheduleStatusColor(endDateOnly, isCompleted);
        return {
            id: s.id, title: s.name ?? '', start: startDateOnly, end: endForCalendar,
            allDay: true, backgroundColor: uc.bg, borderColor: uc.border, textColor: uc.text,
            extendedProps: { schedule_id: s.id, progress: s.progress ?? 0, completed_at: s.completed_at ?? null },
        };
    })
);

const scheduleWeekPostsUrl = computed(() =>
    job.id ? route('user.project_jobs.week_posts.index', { projectJob: job.id }) : null
);

// ── ジョブ履歴 ────────────────────────────────────────────────────────────
const hideHistoryCompleted = ref(false);
const historyOpen       = ref(true);
const historyLinkedOpen = ref(true);
const historyOtherOpen  = ref(true);

const sheetLinkedSet = computed(() =>
    new Set((page.props.sheetLinkedAssignmentIds || []).map(String))
);

function isSheetLinked(m) {
    const aid = String(m.project_job_assignment_id || m.project_job_assignment?.id || '');
    return aid !== '' && sheetLinkedSet.value.has(aid);
}

function historyDeduplicate(arr) {
    const byAssign = new Map();
    for (const m of arr) {
        const assignId = m.project_job_assignment?.id ?? m.project_job_assignment_id;
        const aid = assignId ? `assign-${assignId}` : `noassign-${m.id ?? Math.random()}`;
        if (!byAssign.has(aid)) { byAssign.set(aid, m); continue; }
        const existing = byAssign.get(aid);
        const eCreated = existing?.created_at ? new Date(existing.created_at) : null;
        const mCreated = m?.created_at ? new Date(m.created_at) : null;
        if ((!eCreated && mCreated) || (eCreated && mCreated && mCreated > eCreated)) {
            byAssign.set(aid, m);
        }
    }
    return Array.from(byAssign.values());
}

function historyGetDateKey(m) {
    if (m.event_starts_at) return String(m.event_starts_at).replace(' ', 'T').split('T')[0];
    return (m.created_at ? String(m.created_at).split('T')[0] : null) || '';
}

function historyGetTimeKey(m) {
    if (m.event_starts_at) {
        try {
            const d = new Date(String(m.event_starts_at).replace(' ', 'T'));
            return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
        } catch { /* fallthrough */ }
    }
    return m.project_job_assignment?.start_time || m.project_job_assignment?.desired_time || '00:00';
}

function historyFormatDateLabel(dateStr) {
    if (!dateStr) return '日付なし';
    try {
        const d = new Date(dateStr + 'T00:00:00');
        const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${dow}）`;
    } catch { return dateStr; }
}

function historyGetStatus(m) {
    try {
        const assignment = m.project_job_assignment || {};
        if (Boolean(m.completed) || Boolean(assignment.completed)) return '完了';
        if (Boolean(m.accepted) || Boolean(assignment.accepted) ||
            Boolean(m.scheduled) || Boolean(assignment.scheduled) || Boolean(assignment.scheduled_at)) return 'セット済み';
        const readAt = m.read_at || assignment.read_at || null;
        if (readAt) return '確認済み';
        return '未読';
    } catch { return '未読'; }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':      return 'bg-yellow-100 text-yellow-800';
        case 'セット済み': return 'bg-blue-100 text-blue-800';
        case '確認済み':  return 'bg-green-100 text-green-800';
        case '未読':      return 'bg-red-100 text-red-800';
        default:          return 'bg-gray-100 text-gray-700';
    }
}

function historyGetSender(m) {
    try { return m.sender?.name || m.message?.fromUser?.name || '-'; } catch { return '-'; }
}

function historyGetRecipients(m) {
    try {
        const recs = m.message && Array.isArray(m.message.recipients) ? m.message.recipients : [];
        if (recs.length) {
            const names = recs.map((r) => r.user?.name || r.name || null).filter(Boolean);
            if (names.length) return names.join(', ');
        }
        if (m.project_job_assignment?.user?.name) return m.project_job_assignment.user.name;
        return '-';
    } catch { return '-'; }
}

function historyGetWorkDate(m) {
    try {
        if (m.event_starts_at) {
            const norm    = String(m.event_starts_at).replace(' ', 'T');
            const dateStr = norm.split('T')[0];
            const parts   = dateStr.split('-');
            if (parts.length === 3) {
                const formatted = `${parts[0]}/${parts[1]}/${parts[2]}`;
                const d = new Date(norm);
                const startTime = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
                if (m.event_ends_at) {
                    const e = new Date(String(m.event_ends_at).replace(' ', 'T'));
                    const endTime = `${String(e.getHours()).padStart(2, '0')}:${String(e.getMinutes()).padStart(2, '0')}`;
                    return `${formatted}\n${startTime}〜${endTime}`;
                }
                return `${formatted}\n${startTime}〜`;
            }
        }
        const date = m.project_job_assignment?.desired_end_date || null;
        if (!date) return '-';
        const parts = String(date).split('T')[0].split('-');
        if (parts.length !== 3) return String(date).split('T')[0];
        return `${parts[0]}/${parts[1]}/${parts[2]}`;
    } catch { return '-'; }
}

function sortWithChainHistoryMsg(items) {
    function msgId(m)  { return String(m.project_job_assignment?.id ?? m.project_job_assignment_id ?? ''); }
    function msgSrc(m) { return String(m.project_job_assignment?.source_assignment_id ?? ''); }
    const byId  = new Map(items.map((m) => [msgId(m), m]));
    const roots = items.filter((m) => !msgSrc(m) || !byId.has(msgSrc(m)));
    const result  = [];
    const visited = new Set();
    function appendWithChildren(item, depth) {
        const id = msgId(item);
        if (visited.has(id)) return;
        visited.add(id);
        const finalDepth = (depth === 0 && (item.__chain_depth ?? 0) > 0) ? item.__chain_depth : depth;
        result.push({ ...item, __chain_depth: finalDepth });
        items.filter((m) => msgSrc(m) === id).forEach((child) => appendWithChildren(child, finalDepth + 1));
    }
    roots.forEach((root) => appendWithChildren(root, 0));
    items.filter((m) => !visited.has(msgId(m))).forEach((m) => result.push({ ...m, __chain_depth: m.__chain_depth ?? 0 }));
    return result;
}

function buildHistoryGroups(messages) {
    const globalByPjaId = new Map();
    for (const m of messages) {
        const id = String(m.project_job_assignment?.id ?? m.project_job_assignment_id ?? '');
        if (id) globalByPjaId.set(id, m);
    }
    const withGlobalDepth = messages.map((m) => {
        const srcId = String(m.project_job_assignment?.source_assignment_id ?? '');
        return { ...m, __chain_depth: (srcId && globalByPjaId.has(srcId)) ? 1 : 0 };
    });
    const grouped = new Map();
    for (const m of withGlobalDepth) {
        const key = historyGetDateKey(m);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(m);
    }
    for (const [key, items] of grouped.entries()) {
        items.sort((a, b) => historyGetTimeKey(a).localeCompare(historyGetTimeKey(b)));
        grouped.set(key, sortWithChainHistoryMsg(items));
    }
    const sortedKeys = Array.from(grouped.keys()).sort((a, b) => {
        if (!a) return 1; if (!b) return -1;
        return b.localeCompare(a);
    });
    return sortedKeys.map((key) => ({ key, label: historyFormatDateLabel(key), items: grouped.get(key) }));
}

const historyGroupsLinked = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    let messages = historyDeduplicate(raw).filter(isSheetLinked);
    if (hideHistoryCompleted.value) messages = messages.filter((m) => historyGetStatus(m) !== '完了');
    return buildHistoryGroups(messages);
});

const historyGroupsOther = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    let messages = historyDeduplicate(raw).filter((m) => !isSheetLinked(m));
    if (hideHistoryCompleted.value) messages = messages.filter((m) => historyGetStatus(m) !== '完了');
    return buildHistoryGroups(messages);
});

const historyLinkedCount = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    return historyDeduplicate(raw).filter(isSheetLinked).length;
});

const historyOtherCount = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    return historyDeduplicate(raw).filter((m) => !isSheetLinked(m)).length;
});

const historyDisplayCount = computed(() =>
    historyGroupsLinked.value.reduce((s, g) => s + g.items.length, 0) +
    historyGroupsOther.value.reduce((s, g) => s + g.items.length, 0)
);

const historyHiddenCount = computed(() => {
    if (!hideHistoryCompleted.value) return 0;
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    return historyDeduplicate(raw).filter((m) => historyGetStatus(m) === '完了').length;
});

function historyRowClick(m, event) {
    const tag = event.target?.tagName?.toLowerCase() || '';
    if (tag === 'a' || tag === 'button' || event.target.closest?.('a,button')) return;
    try {
        const pjId  = job.id;
        const msgId = m.id;
        if (pjId && msgId) {
            router.visit(
                route('user.project_jobs.jobbox.show', { projectJob: pjId, message: msgId }) + '?from=project',
                { preserveState: false }
            );
        }
    } catch {}
}
</script>
