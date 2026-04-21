<!--
 プロジェクトジョブ詳細ページ（リデザイン版）
 - タイトル行にアクションボタンをまとめる
 - スケジュール / メンバー をセクション形式で表示
-->

<template>
    <AppLayout title="案件詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <!-- ── スティッキーヘッダー ──────────────────────────── -->
        <div class="sticky top-0 z-20 rounded-t bg-white px-6 pt-6 pb-0 shadow-md">

            <!-- ── タイトル行 ──────────────────────────────────── -->
            <div class="mb-4 flex flex-wrap items-start gap-5">
                <!-- 左：クライアント / 案件名 / サブ情報 -->
                <div>
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
                        サブCo: {{ subCoordinators.map((c) => c.name).join('、') }}
                    </p>
                </div>

                <!-- タイトル横：アクションボタン群 -->
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button
                        type="button"
                        :class="job.completed
                            ? 'rounded bg-gray-300 px-4 py-1.5 text-sm font-medium text-gray-400 cursor-not-allowed'
                            : 'rounded bg-yellow-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-yellow-700'"
                        :disabled="job.completed"
                        @click="goEdit"
                    >編集</button>
                    <button
                        type="button"
                        class="rounded border border-blue-400 px-4 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50"
                        @click="cloneJob"
                    >この案件を複製する</button>
                    <button
                        type="button"
                        class="rounded bg-cyan-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-cyan-700"
                        @click="goMemberSchedule"
                    >メンバー予定表</button>
                    <button
                        type="button"
                        :class="job.completed
                            ? 'rounded bg-gray-300 px-4 py-1.5 text-sm font-medium text-gray-400 cursor-not-allowed'
                            : 'rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700'"
                        :disabled="job.completed"
                        @click="goJobAssign"
                    >ジョブ割り当て</button>
                    <button
                        type="button"
                        class="rounded border border-indigo-300 bg-white px-4 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                        @click="goAssignmentList"
                    >割り当て一覧</button>
                    <button
                        type="button"
                        class="rounded bg-teal-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-700"
                        @click="goAnalysis"
                    >ジョブ詳細</button>
                    <!-- 完了 / 未完了 -->
                    <button
                        v-if="!job.completed"
                        type="button"
                        class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                        @click="completeJob"
                    >完了にする</button>
                    <template v-else>
                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800">完了済み</span>
                        <button
                            type="button"
                            class="rounded bg-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-orange-600"
                            @click="uncompleteJob"
                        >未完了に戻す</button>
                    </template>
                    <!-- 削除 -->
                    <button
                        type="button"
                        class="rounded bg-red-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                        @click="destroyJob"
                    >削除</button>
                    <button
                        type="button"
                        class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                        @click="backToIndex"
                    >一覧に戻る</button>
                </div>
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

                <!-- ── スケジュールセクション ──────────────────── -->
                <section v-show="activeTab === 'overview'" class="py-5">
                    <div class="mb-3 flex items-center gap-4">
                        <h3 class="font-semibold text-gray-800">スケジュール</h3>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded border border-blue-300 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50"
                                @click="goScheduleCalendar"
                            >{{ hasScheduleFlag ? 'カレンダーで編集' : 'カレンダーで登録' }}</button>
                        </div>
                    </div>

                    <div v-if="schedules.length > 0" class="overflow-x-auto">
                        <table class="min-w-full border text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">開始日</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">終了日</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                    <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">内容</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="s in schedules"
                                    :key="s.id"
                                    class="cursor-pointer hover:bg-blue-50"
                                    @click="goScheduleCalendar"
                                >
                                    <td class="border px-3 py-2 text-gray-700">{{ formatDate(s.start_date) }}</td>
                                    <td class="border px-3 py-2 text-gray-700">{{ formatDate(s.end_date) }}</td>
                                    <td class="border px-3 py-2 font-medium text-gray-900">{{ s.name || '-' }}</td>
                                    <td class="border px-3 py-2 text-gray-600">{{ truncate(s.description, 40) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400">スケジュール未登録</p>
                </section>

                <!-- ── メンバーセクション ──────────────────────── -->
                <section v-show="activeTab === 'overview'" class="py-5">
                    <div class="mb-3 flex items-center gap-4">
                        <h3 class="font-semibold text-gray-800">メンバー</h3>
                        <button
                            type="button"
                            class="rounded border border-green-300 px-3 py-1 text-xs font-medium text-green-600 hover:bg-green-50"
                            @click="hasMembers ? editMembers() : goProjectTeammember()"
                        >{{ hasMembers ? '編集' : '登録' }}</button>
                    </div>

                    <div class="space-y-2">
                        <!-- リーダー -->
                        <div v-if="job.user" class="flex items-center gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-yellow-700">リーダー</span>
                            <span class="flex items-center gap-1.5 rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm font-medium text-gray-800">
                                <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                                {{ job.user.name }}
                            </span>
                        </div>

                        <!-- サブリーダー（副Coordinator） -->
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

                        <!-- Coordinator メンバー -->
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

                        <!-- User メンバー -->
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
                </section>

                <!-- ── 進行管理表セクション ──────────────────── -->
                <section v-show="activeTab === 'progress'" class="py-5">
                    <div class="mb-3 flex items-center gap-4">
                        <h3 class="font-semibold text-gray-800">進行管理表</h3>
                        <button
                            type="button"
                            class="rounded border border-indigo-300 px-3 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                            @click="showCreateSheetModal = true"
                        >
                            新規作成
                        </button>
                        <button
                            v-if="progressSheets.length > 1"
                            type="button"
                            class="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                            @click="openReorderModal"
                        >
                            順序を変更
                        </button>
                        <Link
                            :href="route('coordinator.progress_templates.index')"
                            class="text-xs text-gray-500 hover:underline"
                        >
                            テンプレート管理
                        </Link>
                    </div>

                    <div v-if="progressSheets.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">シート名</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">作成日</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="ps in progressSheets" :key="ps.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ ps.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ ps.created_at }}</td>
                                    <td class="px-4 py-2">
                                        <Link
                                            :href="route('coordinator.progress_sheets.show', { sheet: ps.id })"
                                            class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                                        >
                                            開く
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400">進行管理表なし</p>
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

                        <!-- ── 続きジョブ シリーズパネル ── -->
                        <div v-if="false" class="mb-5 space-y-3">
                            <div v-for="(chain, ci) in historyChainGroups" :key="ci"
                                 class="overflow-hidden rounded-lg border border-orange-200 bg-orange-50 shadow-sm">
                                <div class="border-b border-orange-200 bg-orange-100 px-4 py-2">
                                    <span class="text-sm font-semibold text-orange-800">↩ 続きジョブ シリーズ（{{ chain.length }}件）</span>
                                </div>
                                <div class="divide-y divide-orange-100 px-4 py-1">
                                    <div v-for="(m, idx) in chain" :key="m.project_job_assignment?.id"
                                         class="flex items-start gap-3 py-2.5">
                                        <!-- 番号 -->
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-orange-200 text-xs font-bold text-orange-700">
                                            {{ idx + 1 }}
                                        </span>
                                        <!-- タイトルとイベント -->
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <button @click="historyRowClick(m, $event)"
                                                        class="text-left text-sm font-medium text-blue-700 underline hover:text-blue-900">
                                                    {{ m.subject || m.project_job_assignment?.title }}
                                                </button>
                                                <span class="text-xs text-gray-500">{{ historyGetRecipients(m) }}</span>
                                                <span v-if="m.project_job_assignment?.completed"
                                                      class="rounded-full bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-800">完了</span>
                                            </div>
                                            <!-- イベント一覧 -->
                                            <div v-if="m.all_events && m.all_events.length" class="mt-1 space-y-0.5">
                                                <div v-for="ev in m.all_events" :key="ev.id" class="text-xs text-gray-500">
                                                    {{ historyFormatEvDate(ev.date) }}
                                                    {{ ev.start }}〜{{ ev.end }}
                                                    <span class="ml-1 font-medium text-gray-700">{{ historyFormatMins(ev.minutes) }}</span>
                                                </div>
                                            </div>
                                            <div v-else class="mt-0.5 text-xs text-gray-400">（予定未セット）</div>
                                        </div>
                                        <!-- 合計時間 -->
                                        <div class="shrink-0 text-right">
                                            <span class="text-sm font-bold"
                                                  :class="m.total_minutes > 0 ? 'text-indigo-700' : 'text-gray-300'">
                                                {{ m.total_minutes > 0 ? historyFormatMins(m.total_minutes) : '-' }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- チェーン合計 -->
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-sm font-semibold text-orange-800">シリーズ合計</span>
                                        <span class="text-base font-bold text-orange-800">
                                            {{ historyFormatMins(chain.reduce((s, m) => s + (m.total_minutes || 0), 0)) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── 進行表に関連するジョブ ── -->
                        <div class="mb-4">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded bg-indigo-50 px-4 py-2 text-left text-sm font-semibold text-indigo-800 hover:bg-indigo-100"
                                @click="historyLinkedOpen = !historyLinkedOpen"
                            >
                                <span>{{ historyLinkedOpen ? '▼' : '▶' }}</span>
                                <span>進行表に関連するジョブ</span>
                                <span class="ml-1 text-xs font-normal text-indigo-500">
                                    {{ historyLinkedCount }} 件
                                </span>
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
                                                <col style="width: 88px">
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
                                                    :key="m.id"
                                                    :class="['cursor-pointer hover:bg-indigo-50', m.__chain_depth > 0 ? 'bg-orange-50' : '']"
                                                    @click.prevent="historyRowClick(m, $event)"
                                                    role="button"
                                                >
                                                    <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetSender(m) }}</td>
                                                    <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetRecipients(m) }}</td>
                                                    <td class="break-words whitespace-pre-line border px-3 py-2 text-sm text-gray-600">{{ historyGetWorkDate(m) }}</td>
                                                    <td class="break-words border px-3 py-2 text-sm">
                                                        <span v-if="m.__chain_depth > 0"
                                                              class="mr-1 inline-block text-orange-300"
                                                              :style="{ paddingLeft: (m.__chain_depth * 12) + 'px' }">└</span>
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
                                <span class="ml-1 text-xs font-normal text-gray-500">
                                    {{ historyOtherCount }} 件
                                </span>
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
                                                <col style="width: 88px">
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
                                                    :key="m.id"
                                                    :class="['cursor-pointer hover:bg-gray-100', m.__chain_depth > 0 ? 'bg-orange-50' : '']"
                                                    @click.prevent="historyRowClick(m, $event)"
                                                    role="button"
                                                >
                                                    <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetSender(m) }}</td>
                                                    <td class="break-words border px-3 py-2 text-sm text-gray-700">{{ historyGetRecipients(m) }}</td>
                                                    <td class="break-words whitespace-pre-line border px-3 py-2 text-sm text-gray-600">{{ historyGetWorkDate(m) }}</td>
                                                    <td class="break-words border px-3 py-2 text-sm">
                                                        <span v-if="m.__chain_depth > 0"
                                                              class="mr-1 inline-block text-orange-300"
                                                              :style="{ paddingLeft: (m.__chain_depth * 12) + 'px' }">└</span>
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

                <!-- 校正依頼履歴 -->
                <section v-show="activeTab === 'history'" v-if="(page.props.proofHistory || []).length > 0" class="py-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">校正依頼履歴</h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">校正者</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">締め切り</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ステータス</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="pr in (page.props.proofHistory || [])" :key="pr.id" class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-900">{{ pr.title }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ pr.proofreader_name ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-500 whitespace-nowrap">
                                    {{ pr.deadline ? new Date(pr.deadline).toLocaleDateString('ja-JP', { timeZone: 'Asia/Tokyo', year: 'numeric', month: 'numeric', day: 'numeric' }) : '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="{
                                        'bg-gray-100 text-gray-700':     pr.status === 'pending',
                                        'bg-blue-100 text-blue-800':     pr.status === 'assigned',
                                        'bg-pink-100 text-pink-800':     pr.status === 'in_progress',
                                        'bg-yellow-100 text-yellow-800': pr.status === 'completed',
                                    }" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                                        {{ { pending: '受理待ち', assigned: '割り当て済み', in_progress: '校正中', completed: '完了' }[pr.status] ?? pr.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

            </div><!-- /divide-y -->
        </div><!-- /tab content -->
    </AppLayout>

    <!-- ── 進行管理表 新規作成モーダル ──── -->
    <div
        v-if="showCreateSheetModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="showCreateSheetModal = false"
    >
        <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">進行管理表を作成</h3>

            <label class="block text-sm font-medium text-gray-700">シート名 <span class="text-red-500">*</span></label>
            <input
                v-model="newSheetName"
                type="text"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                placeholder="例: 本文用"
            />

            <!-- 作成方式の選択 -->
            <div class="mt-4 space-y-2">
                <label class="flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm"
                    :class="newSheetUseV2 ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                    @click="newSheetUseV2 = true"
                >
                    <input type="radio" :checked="newSheetUseV2" class="h-4 w-4 text-indigo-600" />
                    <div>
                        <div class="font-medium text-gray-700">組版・校正セット方式で作成</div>
                        <div class="text-xs text-gray-400">組版担当+登録欄・校正担当+登録欄のペアを校ごとに自動生成</div>
                    </div>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm"
                    :class="!newSheetUseV2 ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                    @click="newSheetUseV2 = false"
                >
                    <input type="radio" :checked="!newSheetUseV2" class="h-4 w-4 text-indigo-600" />
                    <div>
                        <div class="font-medium text-gray-700">テンプレートから作成</div>
                        <div class="text-xs text-gray-400">保存済みテンプレートまたは空のシートで作成</div>
                    </div>
                </label>
            </div>

            <!-- テンプレートから作成の場合 -->
            <template v-if="!newSheetUseV2">
                <label class="mt-3 block text-sm font-medium text-gray-700">テンプレート（任意）</label>
                <select
                    v-model="newSheetTemplateId"
                    class="mt-1 w-full rounded border border-gray-300 px-2 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                >
                    <option :value="null">— 使用しない —</option>
                    <option v-for="t in sheetTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </template>

            <!-- セット方式の場合：校の入力 -->
            <template v-else>
                <div class="mt-3 space-y-2">
                    <div v-for="(round, idx) in newSheetRounds" :key="idx" class="flex items-center gap-2">
                        <span class="w-12 flex-shrink-0 text-xs text-gray-500">第{{ idx + 1 }}校</span>
                        <select
                            v-model="round.stage_id"
                            class="flex-1 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                            @change="round.stage_name = stageNameById(round.stage_id)"
                        >
                            <option :value="null">— ステージを選択 —</option>
                            <option v-for="s in availableStages" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <button
                            v-if="newSheetRounds.length > 1"
                            type="button"
                            class="text-xs text-red-400 hover:text-red-600"
                            @click="newSheetRounds.splice(idx, 1)"
                        >✕</button>
                    </div>
                    <button
                        type="button"
                        class="text-xs font-medium text-indigo-600 hover:underline"
                        @click="addNextRound"
                    >＋ 校を追加</button>
                </div>
                <p class="mt-2 text-xs text-gray-400">各校に「組版担当・登録欄 + 校正担当・登録欄」が自動生成されます。</p>
            </template>

            <div class="mt-5 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    @click="showCreateSheetModal = false"
                >
                    キャンセル
                </button>
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="createSheet"
                >
                    作成
                </button>
            </div>
        </div>
    </div>

    <!-- ── 進行管理表 並び順モーダル ──── -->
    <div
        v-if="showReorderModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="showReorderModal = false"
    >
        <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">進行管理表の順序を変更</h3>
            <ul class="mb-5 space-y-2">
                <li
                    v-for="(sheet, idx) in reorderList"
                    :key="sheet.id"
                    class="flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
                >
                    <span class="flex-1 font-medium text-gray-800">{{ sheet.name }}</span>
                    <button
                        type="button"
                        :disabled="idx === 0"
                        class="rounded p-1 text-gray-500 hover:bg-gray-200 disabled:opacity-30"
                        @click="moveSheet(idx, -1)"
                        title="上へ"
                    >▲</button>
                    <button
                        type="button"
                        :disabled="idx === reorderList.length - 1"
                        class="rounded p-1 text-gray-500 hover:bg-gray-200 disabled:opacity-30"
                        @click="moveSheet(idx, 1)"
                        title="下へ"
                    >▼</button>
                </li>
            </ul>
            <div class="flex justify-end gap-3">
                <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="showReorderModal = false">キャンセル</button>
                <button type="button" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="saveSheetOrder">保存</button>
            </div>
        </div>
    </div>

</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const page = usePage();
const job  = page.props.job || {};
const schedules = computed(() => Array.isArray(page.props.schedules) ? page.props.schedules : []);

// ── タブ定義 ─────────────────────────────────────────────────────────────
const tabs = [
    { key: 'overview',  label: '概要・メンバー' },
    { key: 'progress',  label: '進行管理表' },
    { key: 'history',   label: 'ジョブ履歴' },
];
const activeTab = ref('overview');

// hasSchedule flag (server-side or derived)
const serverHasSchedule = page.props.hasSchedule;
const computedHasSchedule = computed(() => {
    const s = job.schedule;
    if (!s) return false;
    if (typeof s === 'boolean') return s === true;
    if (Array.isArray(s)) return s.length > 0;
    if (typeof s === 'object') return Object.keys(s).length > 0;
    return Boolean(s);
});
const hasScheduleFlag = computed(() =>
    typeof serverHasSchedule !== 'undefined' ? Boolean(serverHasSchedule) : computedHasSchedule.value
);

const members   = page.props.members || [];
const hasMembers = computed(() => Array.isArray(members) && members.length > 0);
const subCoordinators = computed(() => page.props.subCoordinators || []);

// リーダー・サブリーダーを除いた通常メンバー
const regularMembers = computed(() => {
    const leaderUserId   = job.user_id ?? null;
    const subCoIds       = new Set(subCoordinators.value.map(c => c.id));
    return members.filter(m => {
        if (!m.user) return true;
        if (leaderUserId && m.user.id === leaderUserId) return false;
        if (subCoIds.has(m.user.id)) return false;
        return true;
    });
});

// coordinator ロールのメンバー（リーダー・サブリーダーを除く）
const coordinatorMembers = computed(() =>
    regularMembers.value.filter(m => m.user?.user_role === 'coordinator' || m.user?.user_role === 'clerk')
);

// user ロールのメンバー（coordinator以外）
const userMembers = computed(() =>
    regularMembers.value.filter(m => m.user?.user_role !== 'coordinator' && m.user?.user_role !== 'clerk')
);

// Confirm prompt after initial creation
onMounted(() => {
    const flags        = page.props.registerFlags || [];
    const createdJobId = page.props.jobid || null;
    if (flags.length && createdJobId) {
        if (flags.includes('teammember')) {
            if (confirm('プロジェクトを登録しました。続いてメンバーを登録しますか？')) {
                router.visit(route('coordinator.project_team_members.create') + '?project_job_id=' + createdJobId);
            }
        }
    }
});

// ── Navigation helpers ────────────────────────────────────────────────────
function goMemberSchedule() {
    const authUser = page.props.auth?.user;
    const isLeader = authUser && (job.user_id === authUser.id);
    const isSubCo  = authUser && subCoordinators.value.some(c => c.id === authUser.id);
    if (!isLeader && !isSubCo) {
        alert('このページはリーダーと副リーダーのみ閲覧できます。');
        return;
    }
    router.visit(route('coordinator.project_jobs.member_schedule', { projectJob: job.id }));
}

function goSchedule() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.schedule', { projectJob: id }));
}

function goScheduleCalendar() {
    const id = job.id;
    if (!id) return;
    router.visit(route('coordinator.project_schedules.calendar') + '?project_job_id=' + encodeURIComponent(id));
}

function goProjectTeammember() {
    const id = job.id;
    const url = route('coordinator.project_team_members.create') + (id ? '?project_job_id=' + id : '');
    router.visit(url);
}

function editMembers() {
    const id          = job.id;
    const selectedIds = members.filter(m => m.user).map(m => m.user.id);
    let url = route('coordinator.project_team_members.create');
    const params = [];
    if (id)                params.push('project_job_id='    + encodeURIComponent(id));
    if (selectedIds.length) params.push('selected_user_ids=' + encodeURIComponent(selectedIds.join(',')));
    if (params.length) url += '?' + params.join('&');
    router.visit(url);
}

function cloneJob() {
    const id = job.id;
    if (!id) return;
    if (!confirm('この案件をもとに新規案件を作成します。\nチームメンバーも引き継がれます。\nよいですか？')) return;
    router.post(route('coordinator.project_jobs.clone', { projectJob: id }));
}

function goEdit() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.edit', { projectJob: id }));
}

function backToIndex() {
    router.visit(route('coordinator.project_jobs.index'));
}

async function completeJob() {
    if (!confirm('この案件を完了としてマークしますか？')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('coordinator.project_jobs.complete', { projectJob: job.id }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            router.visit(route('coordinator.project_jobs.index'));
        } else {
            alert('完了処理に失敗しました。');
        }
    } catch {
        alert('完了処理に失敗しました。');
    }
}

async function uncompleteJob() {
    if (!confirm('完了を取り消しますか？')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('coordinator.project_jobs.uncomplete', { projectJob: job.id }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            router.reload({ preserveScroll: true });
        } else {
            alert('未完了への変更に失敗しました。');
        }
    } catch {
        alert('未完了への変更に失敗しました。');
    }
}

async function destroyJob() {
    if (!confirm('この案件を削除しますか？\n関連するジョブ・進行表がある場合は削除できません。')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('coordinator.project_jobs.destroy', { projectJob: job.id }), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            router.visit(route('coordinator.project_jobs.index'));
        } else {
            alert(data.error || '削除できませんでした。');
        }
    } catch {
        alert('削除処理に失敗しました。');
    }
}

function goJobAssign() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.assignments.create', { projectJob: id }));
}

function goAssignmentList() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.assignments.index', { projectJob: id }));
}

function goAnalysis() {
    const id = job.id;
    if (id) router.visit(route('coordinator.project_jobs.analysis', { projectJob: id }));
}

// ── Formatters ────────────────────────────────────────────────────────────
function formatDate(v) {
    if (!v) return '-';
    try { return String(v).split('T')[0]; } catch { return String(v); }
}

function truncate(text, len) {
    if (!text) return '-';
    const s = String(text);
    return s.length > len ? s.slice(0, len) + '…' : s;
}

// ── 進行管理表 ───────────────────────────────────────────────────────────

const progressSheets = computed(() => Array.isArray(page.props.progressSheets) ? page.props.progressSheets : []);
const sheetTemplates = computed(() => Array.isArray(page.props.sheetTemplates) ? page.props.sheetTemplates : []);
const showCreateSheetModal = ref(false);
const newSheetName = ref('');
const newSheetTemplateId = ref(null);
const newSheetUseV2 = ref(true);

// stages（進行表セット方式モーダル用）
const availableStages = computed(() => {
    const s = page.props.stages;
    return Array.isArray(s) ? s : [];
});

function stageNameById(stageId) {
    if (!stageId) return '';
    const s = availableStages.value.find((x) => x.id === stageId || String(x.id) === String(stageId));
    return s ? s.name : '';
}

// 初期値: sort_order 最小（初校）
const firstStage = computed(() => availableStages.value[0] ?? null);
const newSheetRounds = ref([{ stage_id: null, stage_name: '' }]);

// モーダルが開いた時点で stages が揃っていれば初期値をセット
watch(showCreateSheetModal, (open) => {
    if (open && firstStage.value && newSheetRounds.value.length === 1 && !newSheetRounds.value[0].stage_id) {
        newSheetRounds.value[0].stage_id = firstStage.value.id;
        newSheetRounds.value[0].stage_name = firstStage.value.name;
    }
});

/** 次の sort_order を持つステージを追加する */
function addNextRound() {
    const stages = availableStages.value;
    if (!stages.length) {
        newSheetRounds.value.push({ stage_id: null, stage_name: '' });
        return;
    }
    // 最後に選ばれているステージの sort_order を取得
    const lastRound = newSheetRounds.value[newSheetRounds.value.length - 1];
    const lastStage = stages.find((s) => s.id === lastRound.stage_id || String(s.id) === String(lastRound.stage_id));
    const lastOrder = lastStage ? (lastStage.sort_order ?? 0) : -1;
    // 次の sort_order を持つステージを探す
    const nextStage = stages.find((s) => (s.sort_order ?? 0) > lastOrder);
    newSheetRounds.value.push({
        stage_id: nextStage ? nextStage.id : null,
        stage_name: nextStage ? nextStage.name : '',
    });
}

function buildV2ColumnConfig(rounds) {
    return rounds
        .filter((r) => r.stage_name || r.stage_id)
        .map((round, idx) => {
            const label = round.stage_name || stageNameById(round.stage_id) || ('第' + (idx + 1) + '校');
            const key = 'round' + (idx + 1);
            return {
                key,
                label,
                type: 'text',
                children: [
                    {
                        key: key + '_kumihan',
                        label: '組版',
                        type: 'text',
                        children: [
                            { key: key + '_kumihan_tanto',  label: '担当',   type: 'user' },
                            { key: key + '_kumihan_toroku', label: '登録欄', type: 'joblink' },
                        ],
                    },
                    {
                        key: key + '_kosei',
                        label: '校正',
                        type: 'text',
                        children: [
                            { key: key + '_kosei_tanto',  label: '担当',   type: 'proof_user' },
                            { key: key + '_kosei_toroku', label: '登録欄', type: 'joblink' },
                        ],
                    },
                ],
            };
        });
}

function createSheet() {
    const name = newSheetName.value.trim();
    if (!name) {
        alert('シート名を入力してください。');
        return;
    }
    if (newSheetUseV2.value) {
        const config = buildV2ColumnConfig(newSheetRounds.value);
        if (config.length === 0) {
            alert('少なくとも1つのステージを選択してください。');
            return;
        }
        router.post(
            route('coordinator.project_jobs.progress_sheets.store', { projectJob: job.id }),
            { name, column_config: config },
            {
                onSuccess: () => {
                    showCreateSheetModal.value = false;
                    newSheetName.value = '';
                    newSheetUseV2.value = false;
                    newSheetRounds.value = [{ stage_id: firstStage.value?.id ?? null, stage_name: firstStage.value?.name ?? '' }];
                },
            },
        );
    } else {
        router.post(
            route('coordinator.project_jobs.progress_sheets.store', { projectJob: job.id }),
            { name, template_id: newSheetTemplateId.value ?? null },
            { onSuccess: () => { showCreateSheetModal.value = false; newSheetName.value = ''; newSheetTemplateId.value = null; } },
        );
    }
}

// ── 進行管理表 並び順モーダル ──────────────────────────────────────────────
const showReorderModal = ref(false);
const reorderList = ref([]);

function openReorderModal() {
    reorderList.value = [...progressSheets.value].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
    showReorderModal.value = true;
}

function moveSheet(index, dir) {
    const list = reorderList.value;
    const target = index + dir;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
}

function saveSheetOrder() {
    const ids = reorderList.value.map((s) => s.id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    fetch(route('coordinator.project_jobs.progress_sheets.reorder', { projectJob: job.id }), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ ids }),
    }).then(() => {
        showReorderModal.value = false;
        router.reload({ only: ['progressSheets'] });
    });
}
// ── ジョブ履歴 ────────────────────────────────────────────────────────────

const hideHistoryCompleted = ref(false);

function historyFormatMins(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

function historyFormatEvDate(dateStr) {
    const match = String(dateStr || '').match(/\d{4}-(\d{2})-(\d{2})/);
    return match ? `${parseInt(match[1])}/${parseInt(match[2])}` : (dateStr || '');
}

// 続きジョブ チェーングループ（2件以上のチェーンのみ）
const historyChainGroups = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    const deduped = historyDeduplicate(raw);

    // assignment_id -> entry
    const byId = new Map();
    for (const m of deduped) {
        const aid = String(m.project_job_assignment?.id ?? m.project_job_assignment_id ?? '');
        if (aid) byId.set(aid, m);
    }

    const chains = [];
    const visited = new Set();

    for (const m of deduped) {
        const aid = String(m.project_job_assignment?.id ?? m.project_job_assignment_id ?? '');
        if (!aid || visited.has(aid)) continue;

        // ルートをたどる
        let rootEntry = m;
        for (let i = 0; i < 20; i++) {
            const srcId = String(rootEntry.project_job_assignment?.source_assignment_id ?? '');
            if (!srcId || !byId.has(srcId)) break;
            rootEntry = byId.get(srcId);
        }
        const rootId = String(rootEntry.project_job_assignment?.id ?? rootEntry.project_job_assignment_id ?? '');
        if (visited.has(rootId)) continue;

        // BFS で子孫を収集
        const items = [];
        const queue = [rootId];
        while (queue.length > 0) {
            const cur = queue.shift();
            if (visited.has(cur)) continue;
            visited.add(cur);
            const entry = byId.get(cur);
            if (entry) {
                items.push(entry);
                for (const m2 of deduped) {
                    const m2id  = String(m2.project_job_assignment?.id ?? m2.project_job_assignment_id ?? '');
                    const m2src = String(m2.project_job_assignment?.source_assignment_id ?? '');
                    if (m2src === cur && !visited.has(m2id)) queue.push(m2id);
                }
            }
        }

        if (items.length > 1) chains.push(items);
    }

    return chains;
});

function historyGetDateKey(m) {
    if (m.event_starts_at) return String(m.event_starts_at).replace(' ', 'T').split('T')[0];
    // イベントなしの場合は作成日でグルーピング
    return (
        (m.created_at ? String(m.created_at).split('T')[0] : null) ||
        ''
    );
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
        const y = d.getFullYear();
        const mo = d.getMonth() + 1;
        const day = d.getDate();
        const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return `${y}年${mo}月${day}日（${dow}）`;
    } catch {
        return dateStr;
    }
}

function historyGetStatus(m) {
    try {
        const assignment = m.project_job_assignment || {};
        const jam = m || {};
        const statusKey = assignment.status?.key || jam.status?.key || null;
        if (statusKey) {
            switch (statusKey) {
                case 'completed':  return '完了';
                case 'scheduled':  return '進行中';
                case 'confirmed':  return '確認済';
                case 'received':
                case 'order':
                case 'in_progress': return '受信済';
                default: break;
            }
        }
        if (Boolean(jam.completed) || Boolean(assignment.completed)) return '完了';
        if (Boolean(jam.scheduled) || Boolean(assignment.scheduled) || Boolean(assignment.scheduled_at)) return '進行中';
        const readAt = jam.read_at || assignment.read_at || null;
        if (readAt) return Boolean(jam.accepted) || Boolean(assignment.accepted) ? '確認済' : '既読済';
        if (Boolean(jam.accepted) || Boolean(assignment.accepted)) return '受信済';
        return '-';
    } catch {
        return '-';
    }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':    return 'bg-yellow-100 text-yellow-800';
        case '進行中': return 'bg-blue-100 text-blue-800';
        case '確認済':  return 'bg-green-100 text-green-800';
        case '受信済':  return 'bg-indigo-100 text-indigo-800';
        case '既読済':  return 'bg-gray-100 text-gray-700';
        default:        return 'bg-gray-100 text-gray-700';
    }
}

// 割当IDで重複排除（最新メッセージを採用）
function historyDeduplicate(arr) {
    const byAssign = new Map();
    for (const m of arr) {
        // synth エントリは m.id=null だが project_job_assignment.id で一意に識別
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

const historyGroups = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    let messages = historyDeduplicate(raw);
    if (hideHistoryCompleted.value) {
        messages = messages.filter((m) => historyGetStatus(m) !== '完了');
    }
    const grouped = new Map();
    for (const m of messages) {
        const key = historyGetDateKey(m);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(m);
    }
    for (const items of grouped.values()) {
        items.sort((a, b) => historyGetTimeKey(a).localeCompare(historyGetTimeKey(b)));
    }
    const sortedKeys = Array.from(grouped.keys()).sort((a, b) => {
        if (!a) return 1;
        if (!b) return -1;
        return b.localeCompare(a); // 日付降順
    });
    return sortedKeys.map((key) => ({
        key,
        label: historyFormatDateLabel(key),
        items: grouped.get(key),
    }));
});

const historyDisplayCount = computed(() => historyGroups.value.reduce((sum, g) => sum + g.items.length, 0));

const historyHiddenCount = computed(() => {
    if (!hideHistoryCompleted.value) return 0;
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    return historyDeduplicate(raw).filter((m) => historyGetStatus(m) === '完了').length;
});

// ── 進行表連動ジョブ分類 ──────────────────────────────────────────────────

const sheetLinkedSet = computed(() =>
    new Set((page.props.sheetLinkedAssignmentIds || []).map(String))
);

function isSheetLinked(m) {
    const aid = String(m.project_job_assignment_id || m.project_job_assignment?.id || '');
    return aid !== '' && sheetLinkedSet.value.has(aid);
}

// チェーン順ソート: source_assignment_id を持つメッセージを親の直後に配置
function sortWithChainHistoryMsg(items) {
    function msgId(m) { return String(m.project_job_assignment?.id ?? m.project_job_assignment_id ?? ''); }
    function msgSrc(m) { return String(m.project_job_assignment?.source_assignment_id ?? ''); }
    const byId = new Map(items.map((m) => [msgId(m), m]));
    const roots = items.filter((m) => !msgSrc(m) || !byId.has(msgSrc(m)));
    const result = [];
    const visited = new Set();
    function appendWithChildren(item, depth) {
        const id = msgId(item);
        if (visited.has(id)) return;
        visited.add(id);
        // グループをまたぐ続きジョブは既存の__chain_depthを尊重（グループ内rootでも depth>0の場合あり）
        const finalDepth = (depth === 0 && (item.__chain_depth ?? 0) > 0) ? item.__chain_depth : depth;
        result.push({ ...item, __chain_depth: finalDepth });
        const children = items.filter((m) => msgSrc(m) === id);
        for (const child of children) appendWithChildren(child, finalDepth + 1);
    }
    for (const root of roots) appendWithChildren(root, 0);
    for (const m of items) {
        if (!visited.has(msgId(m))) result.push({ ...m, __chain_depth: m.__chain_depth ?? 0 });
    }
    return result;
}

function buildHistoryGroups(messages) {
    // グローバルIDマップで続きジョブのdepthを事前計算（グループをまたいでも機能）
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

const historyOpen       = ref(false);
const historyLinkedOpen = ref(true);
const historyOtherOpen  = ref(true);

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

// 件数（フィルタ前の全件、セクションヘッダー用）
const historyLinkedCount = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    return historyDeduplicate(raw).filter(isSheetLinked).length;
});
const historyOtherCount = computed(() => {
    const raw = Array.isArray(page.props.jobHistory) ? page.props.jobHistory : [];
    return historyDeduplicate(raw).filter((m) => !isSheetLinked(m)).length;
});

function historyGetWorkDate(m) {
    try {
        if (m.event_starts_at) {
            const norm = String(m.event_starts_at).replace(' ', 'T');
            const dateStr = norm.split('T')[0];
            const parts = dateStr.split('-');
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
    } catch {
        return '-';
    }
}

function historyGetSender(m) {
    try {
        return m.sender?.name || m.message?.fromUser?.name || '-';
    } catch {
        return '-';
    }
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
    } catch {
        return '-';
    }
}

function historyRowClick(m, event) {
    const tag = event.target?.tagName?.toLowerCase() || '';
    if (tag === 'a' || tag === 'button' || event.target.closest?.('a,button')) return;

    try {
        // カレンダーイベントがあれば coordinator 専用詳細ページへ（タブメニュー維持）
        if (m.event_id) {
            router.visit(route('coordinator.events.show', { event: m.event_id }), { preserveState: false });
            return;
        }
        const pjId = job.id;
        const msgId = m.id;
        if (pjId && msgId) {
            router.visit(
                route('coordinator.project_jobs.jobbox.show', { projectJob: pjId, message: msgId }) + '?from=project',
                { preserveState: false },
            );
            return;
        }
        // 進行表から登録または独自ジョブ（m.id=null）はアサイン詳細へ
        const aid = m.project_job_assignment_id || m.project_job_assignment?.id;
        if (pjId && aid) {
            router.visit(
                route('coordinator.project_jobs.assignments.show', { projectJob: pjId, assignment: aid }),
                { preserveState: false },
            );
        }
    } catch {}
}
</script>
