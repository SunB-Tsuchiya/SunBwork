<!--
 プロジェクトジョブ詳細ページ（リデザイン版）
 - タイトル行にアクションボタンをまとめる
 - スケジュール / メンバー をセクション形式で表示
-->

<template>
    <AppLayout title="案件詳細">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('coordinator.project_jobs.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← 案件一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
                </h2>
            </div>
        </template>

        <!-- ── スティッキーヘッダー ──────────────────────────── -->
        <div class="sticky top-0 z-20 rounded-t bg-white px-4 sm:px-6 pt-4 sm:pt-6 pb-0 shadow-md">

            <!-- ── タイトル行 ──────────────────────────────────── -->
            <div class="mb-4">
                <!-- クライアント / 案件名 / サブ情報 -->
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
                        サブリーダー: {{ subCoordinators.map((c) => c.name).join('、') }}
                    </p>
                </div>

                <!-- アクションボタン群（サブリーダーの下） -->
                <div class="mt-3 flex flex-wrap items-center gap-1.5 sm:gap-2">
                    <!-- ── 常時表示ボタン ────────────────────────── -->
                    <button
                        type="button"
                        :class="job.completed
                            ? 'rounded bg-gray-300 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-gray-400 cursor-not-allowed'
                            : 'rounded bg-yellow-600 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-white hover:bg-yellow-700'"
                        :disabled="job.completed"
                        @click="goEdit"
                    >編集</button>

                    <!-- 共有済バッジ or 共有ボタン -->
                    <div class="relative" ref="shareButtonRef">
                        <button
                            v-if="sharedJobs.length > 0"
                            type="button"
                            class="rounded border border-emerald-500 bg-emerald-50 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-emerald-700 hover:bg-emerald-100"
                            @click.stop="toggleSharedPopup"
                        >✓ 共有済 ({{ sharedJobs.length }})</button>
                        <button
                            v-else
                            type="button"
                            class="rounded bg-emerald-600 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-white hover:bg-emerald-700"
                            @click="openShareModal"
                        >共有</button>

                        <!-- 共有先一覧ポップアップ -->
                        <div
                            v-if="showSharedPopup"
                            class="absolute left-0 top-full z-30 mt-1 min-w-[260px] rounded-lg border border-emerald-200 bg-white shadow-lg"
                            @click.stop
                        >
                            <div class="flex items-center justify-between border-b px-4 py-2">
                                <span class="text-sm font-semibold text-gray-700">共有済の部署・リーダー</span>
                                <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="showSharedPopup = false">✕</button>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                <li v-for="sj in sharedJobs" :key="sj.id" class="px-4 py-2 text-sm">
                                    <p class="font-medium text-gray-800">{{ sj.department_name || '部署未設定' }}</p>
                                    <p class="text-xs text-gray-500">{{ sj.user_name || '—' }}</p>
                                </li>
                            </ul>
                            <div class="border-t px-4 py-2">
                                <button
                                    type="button"
                                    class="text-xs text-emerald-700 hover:underline"
                                    @click="showSharedPopup = false; openShareModal()"
                                >さらに別の部署へ共有する</button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        :class="job.completed
                            ? 'rounded bg-gray-300 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-gray-400 cursor-not-allowed'
                            : 'rounded bg-indigo-600 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-white hover:bg-indigo-700'"
                        :disabled="job.completed"
                        @click="goJobAssign"
                    >ジョブ割り当て</button>

                    <!-- 完了 / 未完了 -->
                    <button
                        v-if="!job.completed"
                        type="button"
                        class="rounded bg-green-600 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-white hover:bg-green-700"
                        @click="completeJob"
                    >完了にする</button>
                    <template v-else>
                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 sm:px-3 sm:py-1 text-xs sm:text-sm font-medium text-yellow-800">完了済み</span>
                        <button
                            type="button"
                            class="rounded bg-orange-500 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-medium text-white hover:bg-orange-600"
                            @click="uncompleteJob"
                        >未完了に戻す</button>
                    </template>

                    <!-- ── SM以上のみ表示ボタン ─────────────────── -->
                    <button
                        type="button"
                        class="hidden sm:inline-flex rounded border border-blue-400 px-4 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50"
                        @click="cloneJob"
                    >案件複製</button>
                    <button
                        type="button"
                        class="hidden sm:inline-flex rounded bg-cyan-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-cyan-700"
                        @click="goMemberSchedule"
                    >メンバー予定表</button>
                    <button
                        type="button"
                        class="hidden sm:inline-flex rounded border border-indigo-300 bg-white px-4 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                        @click="goAssignmentList"
                    >割り当て一覧</button>
                    <button
                        type="button"
                        class="hidden sm:inline-flex rounded bg-teal-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-700"
                        @click="goAnalysis"
                    >作業分析</button>
                    <button
                        type="button"
                        class="hidden sm:inline-flex rounded bg-red-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                        @click="destroyJob"
                    >削除</button>

                    <!-- ── モバイル用「その他」ドロップダウン ─── -->
                    <div class="relative sm:hidden" ref="moreMenuRef">
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                            @click.stop="showMoreMenu = !showMoreMenu"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01" /></svg>
                            その他
                        </button>
                        <div
                            v-if="showMoreMenu"
                            class="absolute left-0 top-full z-30 mt-1 w-40 rounded-lg border border-gray-200 bg-white shadow-lg"
                            @click.stop
                        >
                            <button
                                type="button"
                                class="block w-full px-4 py-2.5 text-left text-sm text-blue-700 hover:bg-blue-50"
                                @click="showMoreMenu = false; cloneJob()"
                            >案件複製</button>
                            <button
                                type="button"
                                class="block w-full px-4 py-2.5 text-left text-sm text-cyan-700 hover:bg-cyan-50"
                                @click="showMoreMenu = false; goMemberSchedule()"
                            >メンバー予定表</button>
                            <button
                                type="button"
                                class="block w-full px-4 py-2.5 text-left text-sm text-indigo-600 hover:bg-indigo-50"
                                @click="showMoreMenu = false; goAssignmentList()"
                            >割り当て一覧</button>
                            <button
                                type="button"
                                class="block w-full px-4 py-2.5 text-left text-sm text-teal-700 hover:bg-teal-50"
                                @click="showMoreMenu = false; goAnalysis()"
                            >作業分析</button>
                            <div class="border-t border-gray-100" />
                            <button
                                type="button"
                                class="block w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"
                                @click="showMoreMenu = false; destroyJob()"
                            >削除</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── タブバー ──────────────────────────────────────── -->
            <!-- モバイル: セレクター -->
            <div class="mt-2 sm:hidden border-b border-gray-200 pb-2">
                <select
                    v-model="activeTab"
                    class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                >
                    <option v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</option>
                </select>
            </div>
            <!-- SM以上: タブボタン -->
            <div class="mt-2 hidden sm:flex gap-1 border-b border-gray-200 overflow-x-auto">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="[
                        'shrink-0 px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap',
                        activeTab === tab.key
                            ? 'border-indigo-500 text-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    ]"
                    @click="activeTab = tab.key"
                >{{ tab.label }}</button>
            </div>
        </div><!-- /sticky header -->

        <!-- ── タブコンテンツ ─────────────────────────────────── -->
        <div class="rounded-b bg-white px-4 sm:px-6 pb-6 shadow-md">

            <!-- 詳細メモ（概要タブのみ） -->
            <div
                v-if="activeTab === 'overview' && job.detail"
                class="mt-4 whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
            >{{ typeof job.detail === 'string' ? job.detail : JSON.stringify(job.detail) }}</div>

            <div class="divide-y divide-gray-100">

                <!-- ── スケジュールセクション ──────────────────── -->
                <section v-show="activeTab === 'schedule'" class="py-5">
                    <ProjectCalendar
                        ref="projectCalendarRef"
                        :schedules="schedules"
                        :events="scheduleEvents"
                        :comments="[]"
                        :memos="[]"
                        :project="{ id: job.id, name: job.title, jobcode: job.jobcode ?? null }"
                        :weekPostsUrl="scheduleWeekPostsUrl"
                        :showMemoButton="false"
                        :uniformColors="true"
                        :stayInPlace="true"
                    />
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
                                <tr v-for="ps in progressSheets" :key="ps.id"
                                    class="cursor-pointer hover:bg-indigo-50"
                                    @click="router.get(route('coordinator.progress_sheets.show', { sheet: ps.id }))">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ ps.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ ps.created_at }}</td>
                                    <td class="px-4 py-2">
                                        <Link
                                            :href="route('coordinator.progress_sheets.show', { sheet: ps.id })"
                                            class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                                            @click.stop
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

                <!-- ── 項目リストセクション ──────────────────── -->
                <section v-show="activeTab === 'item_list'" class="py-5">
                    <div class="mb-3 flex items-center gap-4">
                        <h3 class="font-semibold text-gray-800">項目リスト</h3>
                        <button
                            v-if="!editingItemList"
                            type="button"
                            class="rounded border border-indigo-300 px-3 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                            @click="startEditItemList"
                        >編集</button>
                        <template v-else>
                            <button
                                type="button"
                                class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                                @click="saveItemList"
                                :disabled="itemListSaving"
                            >保存</button>
                            <button
                                type="button"
                                class="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                @click="cancelEditItemList"
                            >キャンセル</button>
                        </template>
                    </div>

                    <!-- 表示モード -->
                    <div v-if="!editingItemList">
                        <div v-if="itemEntries.length > 0">
                            <ul class="divide-y divide-gray-100 rounded border border-gray-200 bg-white">
                                <li
                                    v-for="(entry, idx) in itemEntries"
                                    :key="entry.id ?? idx"
                                    class="px-4 py-2 text-sm text-gray-800"
                                >{{ entry.name }}</li>
                            </ul>
                            <p class="mt-1 text-xs text-gray-400">{{ itemEntries.length }} 件</p>
                        </div>
                        <p v-else class="text-sm text-gray-400">項目リスト未登録。「編集」ボタンから入力してください。</p>
                    </div>

                    <!-- 編集モード -->
                    <div v-else>
                        <p class="mb-2 text-xs text-gray-500">1行に1項目を入力してください。空行は無視されます。</p>
                        <textarea
                            v-model="itemListText"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm leading-relaxed focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                            rows="12"
                            placeholder="序章初校作成&#10;第一章レイアウトデザイン&#10;第一章初校..."
                        ></textarea>
                    </div>
                </section>

                <!-- ── 工程シートセクション ───────────────────── -->
                <section v-show="activeTab === 'workflow'" class="py-5">
                    <div class="mb-3 flex items-center gap-4">
                        <h3 class="font-semibold text-gray-800">工程シート</h3>
                        <button
                            type="button"
                            class="rounded border border-indigo-300 px-3 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                            @click="showCreateWorkflowModal = true"
                        >新規作成</button>
                        <Link
                            :href="route('coordinator.workflow_templates.index')"
                            class="text-xs text-gray-500 hover:underline"
                        >テンプレート管理</Link>
                    </div>

                    <div v-if="workflowSheets.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">シート名</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">作成日</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="ws in workflowSheets"
                                    :key="ws.id"
                                    class="cursor-pointer hover:bg-indigo-50"
                                    @click="router.get(route('coordinator.workflow_sheets.show', { sheet: ws.id }))"
                                >
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ ws.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ ws.created_at }}</td>
                                    <td class="px-4 py-2">
                                        <Link
                                            :href="route('coordinator.workflow_sheets.show', { sheet: ws.id })"
                                            class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                                            @click.stop
                                        >開く</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400">工程シートなし</p>
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

                <!-- ── 伝票情報タブ ──────────────────────────────── -->
                <section v-show="activeTab === 'voucher'" class="py-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">伝票情報</h3>
                        <!-- 画像がない場合のアップロードボタン -->
                        <button
                            v-if="!job.image_url && !job.completed"
                            type="button"
                            class="rounded-lg border border-green-700 px-4 py-1.5 text-sm font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
                            :disabled="isVoucherOcrLoading"
                            @click="openVoucherOcrModal"
                        >
                            <span v-if="isVoucherOcrLoading">OCR解析中...</span>
                            <span v-else>📎 画像をアップロード</span>
                        </button>
                    </div>

                    <!-- 画像あり -->
                    <div v-if="job.image_url">
                        <div class="relative inline-block">
                            <img
                                :src="job.image_url"
                                :alt="job.original_filename ?? '伝票画像'"
                                class="h-48 w-auto cursor-pointer rounded-lg border border-gray-200 object-contain shadow-sm"
                                @click="showVoucherLightbox = true"
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="max-w-xs truncate text-xs text-gray-500">{{ job.original_filename }}</span>
                            <button
                                type="button"
                                class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                @click="showVoucherLightbox = true"
                            >🔍 拡大</button>
                            <!-- 差し替えボタン（OCRモーダル経由） -->
                            <button
                                v-if="!job.completed"
                                type="button"
                                class="rounded border border-blue-400 px-2 py-0.5 text-xs text-blue-600 hover:bg-blue-50 disabled:opacity-50"
                                :disabled="isVoucherOcrLoading"
                                @click="openVoucherOcrModal"
                            >
                                <span v-if="isVoucherOcrLoading">OCR解析中...</span>
                                <span v-else>📁 差し替え</span>
                            </button>
                            <!-- 削除ボタン -->
                            <button
                                v-if="!job.completed"
                                type="button"
                                class="rounded border border-red-400 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50 disabled:opacity-50"
                                :disabled="isVoucherOcrLoading"
                                @click="confirmDeleteVoucherImage"
                            >✕ 削除</button>
                        </div>
                    </div>

                    <!-- 画像なし -->
                    <p v-else class="text-sm text-gray-400">伝票画像なし</p>
                </section>

                <!-- ── 連携設定タブ ──────────────────────────────── -->
                <section v-show="activeTab === 'items'" class="py-5">
                    <ProjectJobItemsTab :progress-sheets="progressSheets" :job-id="job.id" />
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
                    :class="newSheetMode === 'v2' ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                    @click="newSheetMode = 'v2'"
                >
                    <input type="radio" :checked="newSheetMode === 'v2'" class="h-4 w-4 text-indigo-600" />
                    <div>
                        <div class="font-medium text-gray-700">組版・校正セット方式で作成</div>
                        <div class="text-xs text-gray-400">組版担当+校正担当のペアを校ごとに自動生成</div>
                    </div>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm"
                    :class="newSheetMode === 'template' ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                    @click="newSheetMode = 'template'"
                >
                    <input type="radio" :checked="newSheetMode === 'template'" class="h-4 w-4 text-indigo-600" />
                    <div>
                        <div class="font-medium text-gray-700">テンプレートから作成</div>
                        <div class="text-xs text-gray-400">保存済みテンプレートまたは空のシートで作成</div>
                    </div>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm"
                    :class="newSheetMode === 'calendar' ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                    @click="newSheetMode = 'calendar'"
                >
                    <input type="radio" :checked="newSheetMode === 'calendar'" class="h-4 w-4 text-indigo-600" />
                    <div>
                        <div class="font-medium text-gray-700">カレンダー（スケジュール）から作成</div>
                        <div class="text-xs text-gray-400">カレンダーの予定を行として読み込み、開始日・終了日を設定</div>
                    </div>
                </label>
            </div>

            <!-- テンプレートから作成の場合 -->
            <template v-if="newSheetMode === 'template'">
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
            <template v-else-if="newSheetMode === 'v2'">
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
                <p class="mt-2 text-xs text-gray-400">各校に「組版担当（worker型）＋ 校正担当（proof_v2型）」の2セルが自動生成されます。</p>
            </template>

            <!-- カレンダーから作成の場合 -->
            <template v-else-if="newSheetMode === 'calendar'">
                <div class="mt-3">
                    <div v-if="calendarSheetRows.length === 0" class="rounded border border-dashed border-gray-300 py-4 text-center text-sm text-gray-400">
                        このプロジェクトにカレンダーの予定がありません
                    </div>
                    <template v-else>
                        <div class="mb-1 grid grid-cols-[auto_1fr_120px_120px] gap-1 px-1 text-xs font-medium text-gray-500">
                            <span></span>
                            <span>項目名</span>
                            <span>開始日</span>
                            <span>終了日</span>
                        </div>
                        <div class="max-h-52 overflow-y-auto space-y-1">
                            <div
                                v-for="(row, idx) in calendarSheetRows"
                                :key="idx"
                                class="grid grid-cols-[auto_1fr_120px_120px] items-center gap-1"
                            >
                                <input type="checkbox" v-model="row.selected" class="h-4 w-4 rounded text-indigo-600" />
                                <input
                                    v-model="row.name"
                                    type="text"
                                    :disabled="!row.selected"
                                    class="rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none disabled:bg-gray-50 disabled:text-gray-400"
                                />
                                <input
                                    v-model="row.start_date"
                                    type="date"
                                    :disabled="!row.selected"
                                    @change="row.end_date = row.end_date || row.start_date"
                                    class="rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none disabled:bg-gray-50"
                                />
                                <input
                                    v-model="row.end_date"
                                    type="date"
                                    :disabled="!row.selected"
                                    class="rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none disabled:bg-gray-50"
                                />
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">チェックした項目が行になります。終了日は締め切りとして設定されます。</p>
                    </template>
                </div>
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

    <!-- ── 工程シート 新規作成モーダル ──────────────────────────── -->
    <div
        v-if="showCreateWorkflowModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="showCreateWorkflowModal = false"
    >
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">工程シートを新規作成</h3>
            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">シート名 <span class="text-red-500">*</span></label>
                <input
                    v-model="newWorkflowName"
                    type="text"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                    placeholder="例：書籍A 組版工程"
                    @keyup.enter="submitCreateWorkflow"
                />
            </div>
            <div class="mb-5">
                <label class="mb-1 block text-sm font-medium text-gray-700">テンプレート（任意）</label>
                <select
                    v-model="newWorkflowTemplateId"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                >
                    <option :value="null">── テンプレートなし（デフォルト4工程）</option>
                    <option v-for="t in workflowTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    @click="showCreateWorkflowModal = false"
                >キャンセル</button>
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="!newWorkflowName.trim() || newWorkflowCreating"
                    @click="submitCreateWorkflow"
                >作成</button>
            </div>
        </div>
    </div>

    <!-- ── 共有モーダル ────────────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="showShareModal"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 pt-10 pb-10"
            @click.self="closeShareModal"
        >
            <div class="mx-auto w-full max-w-lg rounded-lg bg-white shadow-xl">
                <!-- ヘッダー -->
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <h2 class="text-base font-semibold text-gray-800">他部署に案件を共有</h2>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeShareModal">✕</button>
                </div>

                <!-- 共有内容 -->
                <div class="border-b bg-gray-50 px-5 py-3 text-sm text-gray-600">
                    <p class="font-medium text-gray-700">共有する案件</p>
                    <p class="mt-1">{{ job.jobcode ? '伝票番号: ' + job.jobcode + '　' : '' }}{{ job.title }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">クライアント・サイズ・総ページ数・詳細を含めてコピーします（変更は連動しません）</p>
                </div>

                <div class="px-5 py-4 space-y-4">
                    <!-- 部署選択 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">部署を選択</label>
                        <select
                            v-model="shareSelectedDeptId"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            @change="shareSelectedUserId = null"
                        >
                            <option :value="null">-- 部署を選択してください --</option>
                            <option v-for="dept in shareDepartments" :key="dept.id" :value="dept.id">
                                {{ dept.name }}
                            </option>
                        </select>
                    </div>

                    <!-- リーダー/Co選択 -->
                    <div v-if="shareSelectedDeptId">
                        <label class="block text-sm font-medium text-gray-700 mb-1">リーダー / コーディネーターを指名</label>
                        <div v-if="shareUsersInDept.length === 0" class="text-sm text-gray-400">この部署にリーダー・コーディネーターがいません</div>
                        <div v-else class="space-y-1">
                            <label
                                v-for="u in shareUsersInDept"
                                :key="u.id"
                                class="flex cursor-pointer items-center gap-3 rounded border px-3 py-2 text-sm hover:bg-gray-50"
                                :class="shareSelectedUserId === u.id ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200'"
                            >
                                <input type="radio" :value="u.id" v-model="shareSelectedUserId" class="accent-emerald-600" />
                                <span class="font-medium text-gray-800">{{ u.name }}</span>
                                <span class="ml-auto text-xs text-gray-400">{{ roleLabel(u.user_role) }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- フッター -->
                <div class="flex items-center justify-end gap-3 border-t px-5 py-4">
                    <button type="button" class="rounded border px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50" @click="closeShareModal">キャンセル</button>
                    <button
                        type="button"
                        class="rounded bg-emerald-600 px-5 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="!shareSelectedUserId || shareSubmitting"
                        @click="submitShare"
                    >{{ shareSubmitting ? '共有中...' : '共有する' }}</button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- 伝票画像ライトボックス -->
    <Teleport to="body">
        <div
            v-if="showVoucherLightbox && job.image_url"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
            @click.self="showVoucherLightbox = false"
        >
            <div class="relative max-h-[90vh] max-w-[90vw]">
                <img
                    :src="job.image_url"
                    :alt="job.original_filename ?? '伝票画像'"
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

    <!-- 伝票OCR ファイル選択モーダル -->
    <Teleport to="body">
        <div
            v-if="showVoucherOcrModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="closeVoucherOcrModal"
        >
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">伝票画像のアップロード</h3>

                <!-- ドロップゾーン -->
                <div
                    class="rounded-lg border-2 border-dashed transition-colors"
                    :class="isDragOverVoucher
                        ? 'border-blue-500 bg-blue-50'
                        : 'border-gray-300 bg-gray-50 hover:border-gray-400'"
                    @dragenter.prevent="isDragOverVoucher = true"
                    @dragleave.prevent="isDragOverVoucher = false"
                    @dragover.prevent
                    @drop.prevent="onVoucherOcrDrop"
                >
                    <div class="py-10 text-center">
                        <!-- OCR解析中 -->
                        <div v-if="isVoucherOcrLoading" class="text-blue-600">
                            <svg class="mx-auto mb-3 h-9 w-9 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            <p class="text-sm font-medium">OCR解析中...</p>
                            <p class="mt-1 text-xs text-gray-400">しばらくお待ちください</p>
                        </div>
                        <!-- 待機中 -->
                        <div v-else>
                            <p class="text-3xl mb-3">📥</p>
                            <p class="text-sm font-medium text-gray-600">PDFや画像をここにドロップ</p>
                            <p class="mt-1 text-xs text-gray-400">または</p>
                            <label class="mt-3 inline-block cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                ファイルを選択
                                <input
                                    type="file"
                                    class="hidden"
                                    accept="image/*,.pdf"
                                    @change="onVoucherOcrFileChange"
                                />
                            </label>
                            <p class="mt-3 text-xs text-gray-400">対応形式: PDF, JPG, PNG, GIF, WebP</p>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                    @click="closeVoucherOcrModal"
                >キャンセル</button>
            </div>
        </div>
    </Teleport>

    <!-- OCR結果モーダル -->
    <OcrModal
        :show="showVoucherOcrResult"
        :ocrResult="voucherOcrResult"
        @apply="onVoucherOcrApply"
        @close="showVoucherOcrResult = false"
    />

</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import OcrModal from '@/Components/Prepress/OcrModal.vue';
import ProjectJobItemsTab from '@/Components/ProjectJobItemsTab.vue';
import ProjectCalendar from '@/Components/ProjectCalendar.vue';
import { scheduleStatusColor } from '@/Helpers/scheduleColor.js';
import { Link, router, usePage, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const page = usePage();
const job  = page.props.job || {};
const schedules = computed(() => Array.isArray(page.props.schedules) ? page.props.schedules : []);

// FullCalendar 用イベント（スケジュールタブに埋め込む ProjectCalendar 向け）
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
                endForCalendar = `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`;
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
    job.id ? route('coordinator.project_jobs.week_posts.index', { projectJob: job.id }) : null
);

// ── スケジュール ソート（閲覧モード） ───────────────────────────────────────
const scheduleSortKey = ref('start_date');
const scheduleSortDir = ref('asc');

const sortedSchedules = computed(() => {
    const key = scheduleSortKey.value;
    const dir = scheduleSortDir.value === 'asc' ? 1 : -1;
    return [...schedules.value].sort((a, b) => {
        const av = a[key] ?? '';
        const bv = b[key] ?? '';
        if (av < bv) return -1 * dir;
        if (av > bv) return  1 * dir;
        return 0;
    });
});

function toggleScheduleSort(key) {
    if (scheduleSortKey.value === key) {
        scheduleSortDir.value = scheduleSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        scheduleSortKey.value = key;
        scheduleSortDir.value = 'asc';
    }
}

function scheduleSortIcon(key) {
    if (scheduleSortKey.value !== key) return '↕';
    return scheduleSortDir.value === 'asc' ? '▲' : '▼';
}

// ── スケジュール 編集モード ─────────────────────────────────────────────────
const scheduleEditMode = ref(false);
const scheduleEditRows = ref([]);
const scheduleSaving   = ref(false);
let   _scheduleKeySeq  = 0;

function toggleScheduleEditMode() {
    if (scheduleEditMode.value) {
        cancelScheduleEditMode();
    } else {
        scheduleEditRows.value = schedules.value.map(s => ({
            _key        : s.id,
            id          : s.id,
            start_date  : s.start_date ? String(s.start_date).split('T')[0] : '',
            end_date    : s.end_date   ? String(s.end_date).split('T')[0]   : '',
            name        : s.name        ?? '',
            description : s.description ?? '',
        }));
        scheduleEditMode.value = true;
    }
}

function cancelScheduleEditMode() {
    scheduleEditMode.value = false;
    scheduleEditRows.value = [];
}

function addScheduleEditRow() {
    scheduleEditRows.value.push({
        _key        : 'new_' + (++_scheduleKeySeq),
        id          : null,
        start_date  : '',
        end_date    : '',
        name        : '',
        description : '',
    });
}

function removeScheduleEditRow(idx) {
    scheduleEditRows.value.splice(idx, 1);
}

async function saveScheduleEdits() {
    if (scheduleSaving.value) return;
    scheduleSaving.value = true;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const originalIds = new Set(schedules.value.map(s => s.id));
    const editIds     = new Set(scheduleEditRows.value.filter(r => r.id).map(r => r.id));
    const deletedIds  = [...originalIds].filter(id => !editIds.has(id));

    try {
        // 削除
        for (const id of deletedIds) {
            await fetch(route('coordinator.project_schedules.destroy', { project_schedule: id }), {
                method : 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
        }
        // 更新・新規
        for (const row of scheduleEditRows.value) {
            if (!row.name.trim()) continue; // タイトル空は無視
            const body = JSON.stringify({
                project_job_id: job.id,
                name       : row.name,
                description: row.description,
                start_date : row.start_date || null,
                end_date   : row.end_date   || null,
            });
            const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
            if (row.id) {
                await fetch(route('coordinator.project_schedules.update', { project_schedule: row.id }), {
                    method: 'PATCH', headers, body,
                });
            } else {
                await fetch(route('coordinator.project_schedules.store'), {
                    method: 'POST', headers, body,
                });
            }
        }
        scheduleEditMode.value = false;
        scheduleEditRows.value = [];
        router.reload({ only: ['schedules'] });
    } finally {
        scheduleSaving.value = false;
    }
}

// ── スケジュール CSV ────────────────────────────────────────────────────────
function handleCsvExport() {
    const id = job.id;
    if (!id) { alert('案件IDが取得できません'); return; }
    window.location.href = route('coordinator.project_schedules.csv_export', { project_job_id: id });
}

const showCsvImportModal = ref(false);
const csvImportFile      = ref(null);
const csvImportErrors    = ref([]);
const csvImportLoading   = ref(false);

function openCsvImportModal() {
    csvImportFile.value   = null;
    csvImportErrors.value = [];
    showCsvImportModal.value = true;
}

function onCsvFileChange(event) {
    csvImportFile.value   = event.target.files[0] ?? null;
    csvImportErrors.value = [];
}

async function submitCsvImport() {
    if (!csvImportFile.value) { alert('CSVファイルを選択してください'); return; }
    const id = job.id;
    if (!id) { alert('案件IDが取得できません'); return; }
    csvImportLoading.value = true;
    csvImportErrors.value  = [];
    try {
        const formData = new FormData();
        formData.append('project_job_id', id);
        formData.append('file', csvImportFile.value);
        const resp = await axios.post(route('coordinator.project_schedules.csv_import'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const created = resp.data.created ?? 0;
        showCsvImportModal.value = false;
        alert(`${created}件の予定をインポートしました`);
        router.reload({ only: ['schedules'] });
    } catch (e) {
        if (e.response?.data?.errors) {
            csvImportErrors.value = e.response.data.errors;
        } else {
            alert('インポートに失敗しました');
        }
    } finally {
        csvImportLoading.value = false;
    }
}

// ── タブ定義 ─────────────────────────────────────────────────────────────
const tabs = [
    { key: 'overview',     label: '概要・メンバー' },
    { key: 'progress',     label: '進行管理表' },
    { key: 'item_list',    label: '項目リスト' },
    { key: 'workflow',     label: '管理シート' },
    { key: 'schedule',     label: 'スケジュール' },
    { key: 'voucher',      label: '伝票情報' },
    { key: 'items',        label: '連携設定' },
    { key: 'history',      label: 'ジョブ履歴' },
];
const activeTab = ref((() => {
    const fromUrl = new URLSearchParams(window.location.search).get('tab');
    if (fromUrl) return fromUrl;
    try { return localStorage.getItem(`project_job_tab_${job.id}`) || 'overview'; } catch (e) { return 'overview'; }
})());

// スケジュールタブの ProjectCalendar ref
// 初回表示時に FullCalendar のサイズを再計算（v-showで隠された状態で mount されるため）
const projectCalendarRef = ref(null);
watch(activeTab, (tab) => {
    try { localStorage.setItem(`project_job_tab_${job.id}`, tab); } catch (e) {}
    if (tab === 'schedule') {
        nextTick(() => projectCalendarRef.value?.updateCalendarSize?.());
    }
});

// ── 伝票画像 ─────────────────────────────────────────────────────────────────
const showVoucherLightbox = ref(false);
const voucherForm = useForm({ image: null });

// ── 伝票OCR ──────────────────────────────────────────────────────────────────
const showVoucherOcrModal    = ref(false); // ファイル選択モーダル
const isVoucherOcrLoading    = ref(false);
const isDragOverVoucher      = ref(false);
const showVoucherOcrResult   = ref(false); // OcrModal
const voucherOcrResult       = ref({});
const voucherOcrFileInput    = ref(null);

function openVoucherOcrModal() {
    showVoucherOcrModal.value = true;
}

function closeVoucherOcrModal() {
    showVoucherOcrModal.value = false;
    isVoucherOcrLoading.value = false;
    isDragOverVoucher.value   = false;
}

async function triggerVoucherOcr(file) {
    // モーダルはそのまま表示（ドロップゾーン内にスピナーを出す）
    isVoucherOcrLoading.value = true;
    try {
        const fd = new FormData();
        fd.append('image', file);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await axios.post(route('coordinator.project_jobs.ocr.analyze'), fd, {
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'multipart/form-data' },
        });
        voucherOcrResult.value     = res.data;
        showVoucherOcrModal.value  = false; // 結果モーダルを出す前に閉じる
        showVoucherOcrResult.value = true;
    } catch {
        showVoucherOcrModal.value = false;
        alert('OCR解析に失敗しました。ファイルを確認して再試行してください。');
    } finally {
        isVoucherOcrLoading.value = false;
    }
}

function onVoucherOcrDrop(e) {
    isDragOverVoucher.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) triggerVoucherOcr(file);
}

async function onVoucherOcrFileChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    await triggerVoucherOcr(file);
}

async function onVoucherOcrApply(result) {
    showVoucherOcrResult.value = false;

    // 更新が必要なフィールドを確認
    const currentJobcode  = job.jobcode  ?? '';
    const currentTitle    = job.title    ?? '';
    const currentClientId = String(job.client_id ?? '');
    const newJobcode  = result.jobcode     ?? '';
    const newTitle    = result.title       ?? '';
    const newClientId = String(result.client_id ?? '');

    const hasFieldDiff = (newJobcode && newJobcode !== currentJobcode)
        || (newTitle && newTitle !== currentTitle)
        || (newClientId && newClientId !== currentClientId);

    let updateFields = false;
    if (hasFieldDiff) {
        const changes = [];
        if (newJobcode && newJobcode !== currentJobcode)       changes.push(`伝票番号: 「${currentJobcode || '未設定'}」→「${newJobcode}」`);
        if (newTitle   && newTitle   !== currentTitle)         changes.push(`案件名: 「${currentTitle}」→「${newTitle}」`);
        if (newClientId && newClientId !== currentClientId)    changes.push(`クライアント: 「${job.client?.name ?? '未設定'}」→「${result.client_name ?? ''}」`);
        updateFields = confirm(
            `以下の差分を案件詳細に反映しますか？\n\n` +
            changes.join('\n') +
            `\n\n「OK」で反映、「キャンセル」で画像のみ保存します。`
        );
    }

    // applyOcrResult エンドポイントを呼ぶ
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const payload = {
            tmp_image_path:    result.tmp_image_path,
            original_filename: result.original_filename ?? '',
            update_fields:     updateFields,
        };
        if (updateFields) {
            if (newJobcode)  payload.jobcode   = newJobcode;
            if (newTitle)    payload.title     = newTitle;
            if (newClientId) payload.client_id = parseInt(newClientId, 10);
        }
        await axios.patch(
            route('coordinator.project_jobs.ocr.apply', { projectJob: job.id }),
            payload,
            { headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } }
        );
        router.reload({ preserveScroll: true });
    } catch {
        alert('画像の保存に失敗しました。');
    }
}

function onVoucherFileChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    voucherForm.image = file;
    voucherForm.post(route('coordinator.project_jobs.image.store', { projectJob: job.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            voucherForm.reset();
            router.reload({ only: ['job'], preserveScroll: true });
        },
        onError: () => { alert('画像のアップロードに失敗しました。'); },
    });
}

function confirmDeleteVoucherImage() {
    if (!confirm('伝票画像を削除しますか？')) return;
    router.delete(route('coordinator.project_jobs.image.destroy', { projectJob: job.id }), {
        preserveScroll: true,
        onSuccess: () => { router.reload({ only: ['job'], preserveScroll: true }); },
    });
}

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

    // 進行表一覧の「新規作成」から遷移してきた場合、進行表作成モーダルを自動オープン
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create_sheet') === '1') {
        showCreateSheetModal.value = true;
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

// ── 共有モーダル ─────────────────────────────────────────────────────────
const shareDepartments = computed(() => {
    const d = page.props.departmentCandidates;
    return Array.isArray(d) ? d : [];
});

// 既共有一覧（サーバーから渡されたもの + 今セッションで追加したもの）
const sharedJobsFromServer = computed(() => {
    const s = page.props.sharedJobs;
    return Array.isArray(s) ? s : [];
});
const sharedJobsLocal = ref([]);
const sharedJobs = computed(() => [...sharedJobsFromServer.value, ...sharedJobsLocal.value]);

// 共有済ポップアップ
const showSharedPopup = ref(false);
function toggleSharedPopup() {
    showSharedPopup.value = !showSharedPopup.value;
}
// その他メニュー（モバイル用）
const showMoreMenu = ref(false);
// ポップアップ・メニュー外クリックで閉じる
if (typeof window !== 'undefined') {
    window.addEventListener('click', () => {
        showSharedPopup.value = false;
        showMoreMenu.value    = false;
    });
}

const showShareModal    = ref(false);
const shareSelectedDeptId = ref(null);
const shareSelectedUserId = ref(null);
const shareSubmitting   = ref(false);

const shareUsersInDept = computed(() => {
    if (!shareSelectedDeptId.value) return [];
    const dept = shareDepartments.value.find(d => d.id === shareSelectedDeptId.value);
    return dept ? (dept.users ?? []) : [];
});

function roleLabel(role) {
    const map = { leader: 'リーダー', coordinator: 'コーディネーター', clerk: '事務' };
    return map[role] ?? role;
}

function openShareModal() {
    shareSelectedDeptId.value = null;
    shareSelectedUserId.value = null;
    showShareModal.value = true;
}

function closeShareModal() {
    showShareModal.value = false;
}

function submitShare() {
    if (!shareSelectedUserId.value || shareSubmitting.value) return;
    shareSubmitting.value = true;
    // 選択中ユーザー情報を手元でも保持（リロード前に即時反映）
    const selectedUser = shareUsersInDept.value.find(u => u.id === shareSelectedUserId.value);
    const selectedDept = shareDepartments.value.find(d => d.id === shareSelectedDeptId.value);
    router.post(
        route('coordinator.project_jobs.share', { projectJob: job.id }),
        { target_user_id: shareSelectedUserId.value },
        {
            onFinish: () => { shareSubmitting.value = false; },
            onSuccess: () => {
                // ローカルリストに追加（ページリロード後はサーバーから来る）
                if (selectedUser && selectedDept) {
                    sharedJobsLocal.value.push({
                        id: null,
                        user_name: selectedUser.name,
                        department_name: selectedDept.name,
                    });
                }
                closeShareModal();
            },
        }
    );
}

function cloneJob() {
    const id = job.id;
    if (!id) return;
    if (!confirm('この案件をもとに新規案件を作成します。\nスケジュール（日付は空）・進行管理表の行構造（担当者は未選択）・チームメンバーも引き継がれます。\nよいですか？')) return;
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
const newSheetMode = ref('v2'); // 'v2' | 'template' | 'calendar'
const calendarSheetRows = ref([]);

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
    if (!open) return;
    if (firstStage.value && newSheetRounds.value.length === 1 && !newSheetRounds.value[0].stage_id) {
        newSheetRounds.value[0].stage_id = firstStage.value.id;
        newSheetRounds.value[0].stage_name = firstStage.value.name;
    }
    // カレンダー行をスケジュール一覧から初期化
    calendarSheetRows.value = schedules.value.map((s) => ({
        selected: true,
        name: s.name ?? '',
        start_date: s.start_date ?? '',
        end_date: s.end_date ?? '',
        schedule_id: s.id,
    }));
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
                type: 'stage',
                children: [
                    { key: key + '_kumihan', label: '組版', type: 'worker' },
                    { key: key + '_kosei',   label: '校正', type: 'proof_v2' },
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

    const resetModal = () => {
        showCreateSheetModal.value = false;
        newSheetName.value = '';
        newSheetMode.value = 'v2';
        newSheetTemplateId.value = null;
        newSheetRounds.value = [{ stage_id: firstStage.value?.id ?? null, stage_name: firstStage.value?.name ?? '' }];
        calendarSheetRows.value = [];
    };

    if (newSheetMode.value === 'v2') {
        const config = buildV2ColumnConfig(newSheetRounds.value);
        if (config.length === 0) {
            alert('少なくとも1つのステージを選択してください。');
            return;
        }
        router.post(
            route('coordinator.project_jobs.progress_sheets.store', { projectJob: job.id }),
            { name, column_config: config },
            { onSuccess: resetModal },
        );
    } else if (newSheetMode.value === 'calendar') {
        const selectedRows = calendarSheetRows.value.filter((r) => r.selected && r.name.trim());
        if (selectedRows.length === 0) {
            alert('少なくとも1つの項目を選択してください。');
            return;
        }
        const columnConfig = [
            { key: 'start_date', label: '開始日', type: 'date' },
            { key: 'end_date',   label: '終了日', type: 'date' },
        ];
        const initialRows = selectedRows.map((r) => ({
            label:      r.name.trim(),
            start_date: r.start_date || null,
            end_date:   r.end_date   || null,
        }));
        router.post(
            route('coordinator.project_jobs.progress_sheets.store', { projectJob: job.id }),
            { name, column_config: columnConfig, initial_rows: initialRows },
            { onSuccess: resetModal },
        );
    } else {
        router.post(
            route('coordinator.project_jobs.progress_sheets.store', { projectJob: job.id }),
            { name, template_id: newSheetTemplateId.value ?? null },
            { onSuccess: resetModal },
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
// ── 項目リスト ────────────────────────────────────────────────────────────

const itemEntries    = computed(() => Array.isArray(page.props.itemEntries) ? page.props.itemEntries : []);
const editingItemList = ref(false);
const itemListText   = ref('');
const itemListSaving = ref(false);

function startEditItemList() {
    itemListText.value = itemEntries.value.map((e) => e.name).join('\n');
    editingItemList.value = true;
}

function cancelEditItemList() {
    editingItemList.value = false;
    itemListText.value = '';
}

async function saveItemList() {
    itemListSaving.value = true;
    const names = itemListText.value
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);
    const entries = names.map((name, idx) => ({ name, sort_order: idx }));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        await fetch(route('coordinator.item_entries.update', { projectJob: job.id }), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ entries }),
        });
        editingItemList.value = false;
        router.reload({ only: ['itemEntries'] });
    } catch (e) {
        alert('保存に失敗しました');
    } finally {
        itemListSaving.value = false;
    }
}

// ── 工程シート ────────────────────────────────────────────────────────────

const workflowSheets          = computed(() => Array.isArray(page.props.workflowSheets) ? page.props.workflowSheets : []);
const workflowTemplates       = computed(() => Array.isArray(page.props.workflowTemplates) ? page.props.workflowTemplates : []);
const showCreateWorkflowModal = ref(false);
const newWorkflowName         = ref('');
const newWorkflowTemplateId   = ref(null);
const newWorkflowCreating     = ref(false);

async function submitCreateWorkflow() {
    if (!newWorkflowName.value.trim()) return;
    newWorkflowCreating.value = true;
    router.post(
        route('coordinator.project_jobs.workflow_sheets.store', { projectJob: job.id }),
        {
            name:        newWorkflowName.value.trim(),
            template_id: newWorkflowTemplateId.value || null,
        },
        {
            onFinish: () => {
                newWorkflowCreating.value = false;
                showCreateWorkflowModal.value = false;
                newWorkflowName.value = '';
                newWorkflowTemplateId.value = null;
            },
        }
    );
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
        // 優先順位: 完了 > セット済み > 確認済み > 未読
        if (Boolean(jam.completed) || Boolean(assignment.completed)) return '完了';
        if (Boolean(jam.accepted) || Boolean(assignment.accepted) ||
            Boolean(jam.scheduled) || Boolean(assignment.scheduled) || Boolean(assignment.scheduled_at)) return 'セット済み';
        const readAt = jam.read_at || assignment.read_at || null;
        if (readAt) return '確認済み';
        return '未読';
    } catch {
        return '未読';
    }
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':     return 'bg-yellow-100 text-yellow-800';
        case 'セット済み': return 'bg-blue-100 text-blue-800';
        case '確認済み':  return 'bg-green-100 text-green-800';
        case '未読':     return 'bg-red-100 text-red-800';
        default:         return 'bg-gray-100 text-gray-700';
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

const historyOpen       = ref(true);
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
