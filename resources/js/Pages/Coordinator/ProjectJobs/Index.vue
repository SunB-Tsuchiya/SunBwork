<template>
    <AppLayout title="案件一覧">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">案件一覧</h2>
        </template>

        <template #headerExtras>
            <div class="flex items-center gap-2">
                <Link :href="route('coordinator.project_jobs.bulk_create.index')"
                      class="rounded border border-green-600 px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                    テンプレートから一括作成
                </Link>
                <button
                    type="button"
                    class="rounded border border-blue-600 px-4 py-2 text-sm text-blue-700 hover:bg-blue-50"
                    @click="openCsvModal">
                    CSV読み込み
                </button>
                <Link :href="route('coordinator.project_jobs.create')"
                      class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    新規作成
                </Link>
            </div>
        </template>


        <SuperAdminGlobalGuard :show="isGlobalMode">
        <div class="space-y-4">

            <!-- ★ お気に入り -->
            <div class="rounded bg-white shadow">
                <div class="flex items-center gap-2 border-b border-yellow-300 bg-yellow-200 px-4 py-3">
                    <span class="text-yellow-600 text-lg">★</span>
                    <span class="text-sm font-semibold text-yellow-900">お気に入り</span>
                    <span class="text-xs text-yellow-700">（完了案件含む・フィルター対象外）</span>
                </div>

                <div v-if="localFavoriteJobs.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                    お気に入りの案件はありません。
                </div>

                <table v-else class="w-full table-fixed border">
                    <colgroup>
                        <col v-if="showCreatedAt" class="w-28" />
                        <col v-if="showJobcode" class="w-28" />
                        <col class="w-36" />
                        <col v-if="showClientName" class="w-44" />
                        <col v-if="showStatus" class="w-24" />
                        <col class="w-12" />
                    </colgroup>
                    <thead>
                        <tr class="bg-yellow-100">
                            <th v-if="showCreatedAt" class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">登録日</th>
                            <th v-if="showJobcode" class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">伝票番号</th>
                            <th class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">案件名</th>
                            <th v-if="showClientName" class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">クライアント名</th>
                            <th v-if="showStatus" class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">ステータス</th>
                            <th class="border px-3 py-1.5 text-center text-xs font-medium text-yellow-900">★</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="job in localFavoriteJobs" :key="job.id" class="cursor-pointer hover:bg-yellow-50" @click="rowClick($event, job)">
                            <td v-if="showCreatedAt" class="border px-3 py-2 text-sm text-gray-600">{{ formatDate(job.created_at) }}</td>
                            <td v-if="showJobcode" class="border px-3 py-2 text-sm text-gray-500">{{ job.jobcode || '' }}</td>
                            <td class="border px-3 py-2 text-sm font-medium text-gray-800 max-w-0 truncate" :title="job.title || job.name">{{ job.title || job.name }}</td>
                            <td v-if="showClientName" class="border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                            <td v-if="showStatus" class="border px-3 py-2">
                                <span
                                    :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                >{{ job.completed ? '完了' : '進行中' }}</span>
                            </td>
                            <td class="border px-3 py-2 text-center" @click.stop>
                                <button
                                    @click="toggleFavorite(job, true)"
                                    class="text-lg leading-none transition-colors text-yellow-400 hover:text-yellow-300"
                                    title="お気に入り解除"
                                >★</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 検索・一覧 -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="案件名/クライアントで検索"
                        class="w-full sm:w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
            </div>

            <!-- 月セレクター + 完了非表示チェック + 表示設定 -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select
                        v-model="page.props.period_model"
                        @change="search"
                        class="rounded border px-3 py-2 text-sm"
                        style="width: 9.5em"
                    >
                        <option value="all">全期間</option>
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </option>
                    </select>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                    <input type="checkbox" v-model="hideCompleted" class="h-4 w-4 rounded border-gray-300" />
                    完了を表示しない
                </label>
                <div class="relative ml-auto">
                    <button
                        @click="showColumnSettings = !showColumnSettings"
                        class="rounded border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    >表示設定 ▾</button>
                    <div v-if="showColumnSettings"
                         class="absolute right-0 top-9 z-20 w-44 rounded border bg-white p-3 shadow-lg">
                        <p class="mb-2 text-xs font-semibold text-gray-500">表示カラム</p>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                            <input type="checkbox" v-model="showCreatedAt" class="h-4 w-4 rounded border-gray-300" />
                            登録日
                        </label>
                        <label class="mt-1.5 flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                            <input type="checkbox" v-model="showJobcode" class="h-4 w-4 rounded border-gray-300" />
                            伝票番号
                        </label>
                        <label class="mt-1.5 flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                            <input type="checkbox" v-model="showClientName" class="h-4 w-4 rounded border-gray-300" />
                            クライアント名
                        </label>
                        <label class="mt-1.5 flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                            <input type="checkbox" v-model="showStatus" class="h-4 w-4 rounded border-gray-300" />
                            ステータス
                        </label>
                    </div>
                </div>
            </div>

            <!-- ビューモード切替ボタン -->
            <div class="mt-4 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                <button
                    v-for="mode in viewModes"
                    :key="mode.key"
                    @click="viewMode = mode.key"
                    :class="viewMode === mode.key
                        ? 'bg-white text-indigo-700 font-semibold shadow-sm'
                        : 'text-gray-600 hover:text-gray-900'"
                    class="rounded px-4 py-1.5 text-sm transition-all"
                >{{ mode.label }}</button>
            </div>

            <!-- グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    表示するデータがありません。
                </div>

                <template v-for="group in displayGroups" :key="group.key">
                    <!-- 月ヘッダー -->
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full table-fixed border">
                        <colgroup>
                            <col v-if="showCreatedAt" class="w-28" />
                            <col v-if="showJobcode" class="w-28" />
                            <col class="w-36" />
                            <col v-if="showClientName" class="w-44" />
                            <col v-if="showStatus" class="w-24" />
                            <col class="w-12" />
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th v-if="showCreatedAt" class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('created_at')">
                                        登録日<span class="text-gray-400">{{ sortIndicator('created_at') }}</span>
                                    </button>
                                </th>
                                <th v-if="showJobcode" class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">伝票番号</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件名</th>
                                <th v-if="showClientName" class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('client')">
                                        クライアント名<span class="text-gray-400">{{ sortIndicator('client') }}</span>
                                    </button>
                                </th>
                                <th v-if="showStatus" class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('status')">
                                        ステータス<span class="text-gray-400">{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="border px-3 py-1.5 text-center text-xs font-medium text-gray-500">★</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="job in group.items" :key="job.id" class="cursor-pointer hover:bg-blue-50" @click="rowClick($event, job)">
                                <td v-if="showCreatedAt" class="border px-3 py-2 text-sm text-gray-600">{{ formatDate(job.created_at) }}</td>
                                <td v-if="showJobcode" class="border px-3 py-2 text-sm text-gray-500">{{ job.jobcode || '' }}</td>
                                <td class="border px-3 py-2 text-sm max-w-0 truncate" :title="job.title || job.name">{{ job.title || job.name }}</td>
                                <td v-if="showClientName" class="border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                                <td v-if="showStatus" class="border px-3 py-2">
                                    <span
                                        :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >{{ job.completed ? '完了' : '進行中' }}</span>
                                </td>
                                <td class="border px-3 py-2 text-center" @click.stop>
                                    <button
                                        @click="toggleFavorite(job)"
                                        class="text-lg leading-none transition-colors"
                                        :class="job.is_favorite ? 'text-yellow-400 hover:text-yellow-300' : 'text-gray-300 hover:text-yellow-400'"
                                        :title="job.is_favorite ? 'お気に入り解除' : 'お気に入りに追加'"
                                    >★</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 text-sm text-gray-600">
                表示中 {{ totalDisplayCount }} 件
                <span v-if="hideCompleted && hiddenCompletedCount > 0" class="ml-2 text-xs text-gray-400">（完了 {{ hiddenCompletedCount }} 件を非表示）</span>
            </div>
            </div><!-- /検索・一覧 -->
        </div>
        </SuperAdminGlobalGuard>
    </AppLayout>

    <!-- CSV 一括登録モーダル -->
    <Teleport to="body">
        <div
            v-if="showCsvModal"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 px-2 py-6"
            @click.self="closeCsvModal"
        >
            <div class="w-full max-w-7xl rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">CSV一括登録</h3>
                    <div class="flex items-center gap-3">
                        <a :href="route('coordinator.project_jobs.csv.sample')"
                           class="rounded border border-gray-400 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50">
                            サンプルCSVをダウンロード
                        </a>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeCsvModal">✕</button>
                    </div>
                </div>

                <!-- ファイル選択（解析前） -->
                <div v-if="csvAnalysisRows.length === 0 && !csvAnalyzing" class="px-6 py-6">
                    <p class="mb-3 text-sm text-gray-600">
                        CSV形式: <code class="rounded bg-gray-100 px-1 text-xs">受注No., 得意先, 品名, 営業担当</code>（No列は省略可・CP932 / UTF-8 対応）
                    </p>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-6 py-4 hover:border-blue-500 hover:bg-blue-50">
                        <span class="text-2xl">📊</span>
                        <span class="text-sm font-medium text-gray-600">CSVファイルを選択</span>
                        <input type="file" accept=".csv,text/csv" class="hidden" @change="onCsvFileSelect" />
                    </label>
                </div>

                <!-- 解析中 -->
                <div v-if="csvAnalyzing" class="flex items-center justify-center py-12 text-blue-600">
                    <svg class="mr-2 h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    解析中...
                </div>

                <!-- 解析結果テーブル -->
                <div v-if="csvAnalysisRows.length > 0 && !csvAnalyzing" class="px-6 pb-4">
                    <div class="mb-2 mt-4 flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700">{{ csvAnalysisRows.length }}件</span>
                        <span v-if="csvUnresolvedCount > 0" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                            クライアント未解決 {{ csvUnresolvedCount }}件
                        </span>
                        <span v-if="csvDupSkipCount > 0" class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-700">
                            受注番号重複 {{ csvDupSkipCount }}件スキップ
                        </span>
                    </div>
                    <div class="overflow-x-auto rounded border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">受注No.</th>
                                    <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">品名</th>
                                    <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">得意先(CSV)</th>
                                    <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">得意先(解決)</th>
                                    <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">担当営業(CSV)</th>
                                    <th class="border-b px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">担当営業(解決)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, idx) in csvAnalysisRows"
                                    :key="idx"
                                    :class="row.jobcode_dup && row.jobcode_dup !== 'none' ? 'bg-orange-50' : ''"
                                    class="border-b last:border-0"
                                >
                                    <!-- 受注No. -->
                                    <td class="border-b px-3 py-2 whitespace-nowrap font-mono text-xs">
                                        {{ row.jobcode || '—' }}
                                        <span v-if="row.jobcode_dup === 'db'" class="ml-1 rounded bg-red-100 px-1 py-0.5 text-xs font-semibold text-red-700">DB重複</span>
                                        <span v-else-if="row.jobcode_dup === 'csv'" class="ml-1 rounded bg-yellow-100 px-1 py-0.5 text-xs font-semibold text-yellow-700">CSV重複</span>
                                    </td>
                                    <!-- 品名 -->
                                    <td class="border-b px-3 py-2 text-xs">{{ row.title }}</td>
                                    <!-- 得意先(CSV) -->
                                    <td class="border-b px-3 py-2 whitespace-nowrap text-xs text-gray-500">{{ row.raw_client_name || '—' }}</td>
                                    <!-- 得意先(解決) -->
                                    <td class="border-b px-3 py-2 text-xs">
                                        <div v-if="row.status === 'matched'" class="flex items-center gap-1 text-green-700">
                                            <span>✅</span>
                                            <span class="truncate max-w-[160px]">{{ row.resolved_client_name || row.raw_client_name }}</span>
                                        </div>
                                        <div v-else-if="row.status === 'candidates'" class="space-y-1">
                                            <p class="text-xs text-yellow-700 font-medium">候補を選択:</p>
                                            <div
                                                v-for="c in row.candidates" :key="c.id"
                                                class="cursor-pointer rounded border border-yellow-300 bg-white px-2 py-0.5 text-xs hover:bg-yellow-100"
                                                @click="csvSelectCandidate(idx, c)"
                                            >{{ c.name }}</div>
                                            <div v-if="!row.showSearch" class="flex flex-wrap gap-1">
                                                <button type="button" class="text-xs text-blue-600 underline"
                                                    @click="row.showSearch = true; csvRowClientSearch[idx] = ''">一覧から選択</button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" class="text-xs text-purple-600 underline"
                                                    @click="openInlineClientModal(idx)">新規登録</button>
                                            </div>
                                            <div v-else class="mt-1">
                                                <input v-model="csvRowClientSearch[idx]" type="text"
                                                    placeholder="クライアント名で検索"
                                                    class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                    @input="onCsvRowSearchInput(idx)" />
                                                <div v-for="c in (row.searchResults ?? [])" :key="c.id"
                                                    class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                    @click="csvSelectFromSearch(idx, c)">{{ c.name }}</div>
                                            </div>
                                        </div>
                                        <div v-else class="space-y-1">
                                            <p class="text-xs text-red-600 font-medium">未マッチ</p>
                                            <div v-if="!row.showSearch" class="flex flex-wrap gap-1">
                                                <button type="button" class="text-xs text-blue-600 underline"
                                                    @click="row.showSearch = true; csvRowClientSearch[idx] = ''">一覧から選択</button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" class="text-xs text-gray-500 underline"
                                                    @click="row.status = 'matched'; row.resolved_client_name = row.raw_client_name; row.resolved_client_id = null">名前のまま</button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" class="text-xs text-purple-600 underline"
                                                    @click="openInlineClientModal(idx)">新規登録</button>
                                            </div>
                                            <div v-else class="mt-1">
                                                <input v-model="csvRowClientSearch[idx]" type="text"
                                                    placeholder="クライアント名で検索"
                                                    class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                    @input="onCsvRowSearchInput(idx)" />
                                                <div v-for="c in (row.searchResults ?? [])" :key="c.id"
                                                    class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                    @click="csvSelectFromSearch(idx, c)">{{ c.name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- 担当営業(CSV) -->
                                    <td class="border-b px-3 py-2 whitespace-nowrap">{{ row.sales_rep || '—' }}</td>
                                    <!-- 担当営業(解決) -->
                                    <td class="border-b px-3 py-2">
                                        <div v-if="!row.sales_rep" class="text-gray-400 text-xs">—</div>
                                        <div v-else-if="row.sales_rep_status === 'matched'" class="flex items-center gap-1 text-green-700">
                                            <span>✅</span>
                                            <span>{{ row.resolved_sales_rep_name }}</span>
                                        </div>
                                        <div v-else-if="row.sales_rep_status === 'candidates'" class="space-y-1">
                                            <p class="text-xs text-yellow-700 font-medium">候補を選択:</p>
                                            <div
                                                v-for="r in row.sales_rep_candidates" :key="r.id"
                                                class="cursor-pointer rounded border border-yellow-300 bg-white px-2 py-0.5 text-xs hover:bg-yellow-100"
                                                @click="csvSelectSalesRepCandidate(idx, r)"
                                            >{{ r.name }}</div>
                                            <div v-if="!row.showSalesRepSearch" class="flex flex-wrap gap-1">
                                                <button type="button" class="text-xs text-blue-600 underline"
                                                    @click="row.showSalesRepSearch = true; csvRowSalesRepSearch[idx] = ''">一覧から選択</button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" class="text-xs text-gray-500 underline"
                                                    @click="row.sales_rep_status = 'matched'; row.resolved_sales_rep_name = row.sales_rep; row.resolved_sales_rep_id = null">テキストのまま</button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" class="text-xs text-purple-600 underline"
                                                    @click="openInlineSalesRepModal(idx)">新規登録</button>
                                            </div>
                                            <div v-else class="mt-1">
                                                <input v-model="csvRowSalesRepSearch[idx]" type="text" placeholder="氏名で検索"
                                                    class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                    @input="onCsvRowSalesRepSearchInput(idx)" />
                                                <div v-for="r in (row.salesRepSearchResults ?? [])" :key="r.id"
                                                    class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                    @click="csvSelectSalesRepFromSearch(idx, r)">{{ r.name }}</div>
                                            </div>
                                        </div>
                                        <div v-else class="space-y-1">
                                            <p class="text-xs text-orange-600 font-medium">未マッチ</p>
                                            <div v-if="!row.showSalesRepSearch" class="flex flex-wrap gap-1">
                                                <button type="button" class="text-xs text-blue-600 underline"
                                                    @click="row.showSalesRepSearch = true; csvRowSalesRepSearch[idx] = ''">一覧から選択</button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" class="text-xs text-gray-500 underline"
                                                    @click="row.sales_rep_status = 'matched'; row.resolved_sales_rep_name = row.sales_rep; row.resolved_sales_rep_id = null">テキストのまま</button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" class="text-xs text-purple-600 underline"
                                                    @click="openInlineSalesRepModal(idx)">新規登録</button>
                                            </div>
                                            <div v-else class="mt-1">
                                                <input v-model="csvRowSalesRepSearch[idx]" type="text" placeholder="氏名で検索"
                                                    class="w-full rounded border border-gray-300 px-2 py-0.5 text-xs"
                                                    @input="onCsvRowSalesRepSearchInput(idx)" />
                                                <div v-for="r in (row.salesRepSearchResults ?? [])" :key="r.id"
                                                    class="cursor-pointer border-b px-2 py-0.5 text-xs hover:bg-blue-50"
                                                    @click="csvSelectSalesRepFromSearch(idx, r)">{{ r.name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-3">
                        <button type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                            @click="closeCsvModal">キャンセル</button>
                        <button type="button"
                            class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="csvUnresolvedCount > 0 || csvImporting"
                            @click="doCsvImport"
                        >{{ csvImporting ? '保存中...' : csvDupSkipCount > 0 ? `一括保存 (${csvImportableCount}件) ※${csvDupSkipCount}件スキップ` : `一括保存 (${csvAnalysisRows.length}件)` }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- インライン クライアント新規登録モーダル -->
        <div
            v-if="showInlineClientModal"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
            @click.self="showInlineClientModal = false"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <h4 class="mb-4 text-base font-semibold text-gray-800">クライアント新規登録</h4>
                <div v-if="inlineClientNote" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    {{ inlineClientNote }}
                </div>
                <template v-if="!inlineClientNote">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">クライアント名 <span class="text-red-500">*</span></label>
                            <input v-model="inlineClientForm.name" type="text"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Client ID（任意）</label>
                            <input v-model="inlineClientForm.client_code" type="text"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
                        </div>
                    </div>
                </template>
                <div class="mt-4 flex gap-2 justify-end">
                    <template v-if="inlineClientNote">
                        <button type="button" @click="showInlineClientModal = false"
                            class="rounded bg-blue-600 px-4 py-1.5 text-sm text-white hover:bg-blue-700">OK</button>
                    </template>
                    <template v-else>
                        <button type="button" @click="showInlineClientModal = false"
                            class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">キャンセル</button>
                        <button type="button" @click="saveInlineClient" :disabled="!inlineClientForm.name || inlineClientSaving"
                            class="rounded bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50">
                            {{ inlineClientSaving ? '登録中...' : '登録' }}
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- インライン 営業担当新規登録モーダル -->
        <div
            v-if="showInlineSalesRepModal"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
            @click.self="showInlineSalesRepModal = false"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <h4 class="mb-4 text-base font-semibold text-gray-800">営業担当新規登録</h4>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">氏名 <span class="text-red-500">*</span></label>
                        <input v-model="inlineSalesRepForm.name" type="text"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">会社（任意）</label>
                        <input v-model="inlineSalesRepForm.company" type="text"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            placeholder="株式会社〇〇 など" />
                    </div>
                </div>
                <div class="mt-4 flex gap-2 justify-end">
                    <button type="button" @click="showInlineSalesRepModal = false"
                        class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">キャンセル</button>
                    <button type="button" @click="saveInlineSalesRep" :disabled="!inlineSalesRepForm.name || inlineSalesRepSaving"
                        class="rounded bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50">
                        {{ inlineSalesRepSaving ? '登録中...' : '登録' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminGlobalGuard from '@/Components/SuperAdminGlobalGuard.vue';
import { useUIState } from '@/Composables/useUIState';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({ jobs: Array, favoriteJobs: { type: Array, default: () => [] }, registerFlags: Array, jobid: [Number, String], monthOptions: Array, q: String, period: String, isGlobalMode: { type: Boolean, default: false } });
const page = usePage();
page.props.q_model = props.q || '';
page.props.period_model = props.period || 'all';

const monthOptions = computed(() => (Array.isArray(props.monthOptions) ? props.monthOptions : []));
const hideCompleted = useUIState('sbw_coord_pj_hide_completed', true);
const showCreatedAt  = useUIState('coord_pj_col_created_at', true);
const showJobcode    = useUIState('coord_pj_col_jobcode', true);
const showClientName = useUIState('coord_pj_col_client', true);
const showStatus     = useUIState('coord_pj_col_status', true);
const showColumnSettings = ref(false);

// ローカルコピー（完了ボタンで即時更新するため）
const localJobs = ref((props.jobs || []).map((j) => ({ ...j })));
const localFavoriteJobs = ref((props.favoriteJobs || []).map((j) => ({ ...j })));

async function toggleFavorite(job, isFavSection = false) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const res = await axios.post(
            route('coordinator.project_jobs.favorite', { projectJob: job.id }),
            {},
            { headers: { 'X-CSRF-TOKEN': csrf } },
        );
        const nowFav = res.data.is_favorite;

        const idx = localJobs.value.findIndex(j => j.id === job.id);
        if (idx !== -1) localJobs.value[idx].is_favorite = nowFav;

        if (nowFav) {
            if (!localFavoriteJobs.value.find(j => j.id === job.id)) {
                localFavoriteJobs.value.unshift({ ...job, is_favorite: true });
            }
        } else {
            localFavoriteJobs.value = localFavoriteJobs.value.filter(j => j.id !== job.id);
        }
    } catch (e) {
        console.error('お気に入り更新エラー', e);
    }
}

// ===== ビューモード =====

const viewModes = [
    { key: 'date', label: '日付ごと' },
    { key: 'client', label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];
const viewMode = useUIState('pj_index_view_mode', 'date');

// ===== ソート =====

const sortKey = useUIState('sbw_coord_pj_sort_key', 'created_at');
const sortDir = useUIState('sbw_coord_pj_sort_dir', 'desc');

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'created_at' ? 'desc' : 'asc';
    }
}

function sortIndicator(key) {
    if (sortKey.value !== key) return ' ↕';
    return sortDir.value === 'asc' ? ' ↑' : ' ↓';
}

function sortJobs(jobs) {
    return [...jobs].sort((a, b) => {
        let va, vb;
        if (sortKey.value === 'created_at') {
            va = a.created_at || '';
            vb = b.created_at || '';
        } else if (sortKey.value === 'client') {
            va = a.client?.name || '';
            vb = b.client?.name || '';
        } else if (sortKey.value === 'status') {
            va = a.completed ? 1 : 0;
            vb = b.completed ? 1 : 0;
        }
        if (va < vb) return sortDir.value === 'asc' ? -1 : 1;
        if (va > vb) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
}

// ===== 月グループ =====

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        return String(dateStr).split('T')[0].split(' ')[0];
    } catch {
        return String(dateStr);
    }
}

function getMonthKey(job) {
    return job.created_at ? String(job.created_at).slice(0, 7) : '';
}

function formatMonthLabel(monthStr) {
    if (!monthStr) return '日付なし';
    const [y, m] = monthStr.split('-');
    return `${y}年${parseInt(m)}月`;
}

const displayGroups = computed(() => {
    let jobs = Array.isArray(localJobs.value) ? localJobs.value : [];

    if (hideCompleted.value) {
        jobs = jobs.filter((j) => !j.completed);
    }

    jobs = sortJobs(jobs);

    if (viewMode.value === 'client') {
        const grouped = new Map();
        for (const j of jobs) {
            const key = j.client?.name || '（クライアントなし）';
            if (!grouped.has(key)) grouped.set(key, []);
            grouped.get(key).push(j);
        }
        const sortedKeys = Array.from(grouped.keys()).sort((a, b) => a.localeCompare(b, 'ja'));
        return sortedKeys.map((k) => ({ key: k, label: k, items: grouped.get(k) }));
    }

    if (viewMode.value === 'project') {
        const sorted = [...jobs].sort((a, b) => (a.title || a.name || '').localeCompare(b.title || b.name || '', 'ja'));
        return [{ key: 'all', label: '全案件', items: sorted }];
    }

    // date モード（デフォルト）: 月グループ
    const grouped = new Map();
    for (const j of jobs) {
        const mk = getMonthKey(j);
        if (!grouped.has(mk)) grouped.set(mk, []);
        grouped.get(mk).push(j);
    }

    const sortedKeys = Array.from(grouped.keys()).sort((a, b) => {
        if (!a) return 1;
        if (!b) return -1;
        return b.localeCompare(a);
    });

    return sortedKeys.map((mk) => ({
        key: mk,
        label: formatMonthLabel(mk),
        items: grouped.get(mk),
    }));
});

const totalDisplayCount = computed(() => displayGroups.value.reduce((sum, g) => sum + g.items.length, 0));

const hiddenCompletedCount = computed(() => {
    if (!hideCompleted.value) return 0;
    return (Array.isArray(localJobs.value) ? localJobs.value : []).filter((j) => j.completed).length;
});

// ===== 行クリック =====

function rowClick(event, job) {
    if (event.target.closest('a, button')) return;
    router.visit(route('coordinator.project_jobs.show', { projectJob: job.id }));
}

// ===== 検索 =====

function search() {
    router.get(route('coordinator.project_jobs.index'), { q: page.props.q_model, period: page.props.period_model }, { preserveState: false });
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

// ===== 登録後ナビゲーション =====

const registerFlags = props.registerFlags || [];
const latestJobId = props.jobid || (props.jobs?.length ? props.jobs[props.jobs.length - 1].id : null);

onMounted(() => {
    if (page.props.reload) {
        location.reload();
        return;
    }
    if (registerFlags.length && latestJobId) {
        if (registerFlags.includes('teammember') && registerFlags.includes('schedule')) {
            if (confirm('プロジェクト登録が完了しました。続いてメンバーを登録しますか？')) {
                router.visit(route('coordinator.project_team_members.create'));
            }
        } else if (registerFlags.includes('schedule')) {
            if (confirm('メンバー登録が完了しました。続いてスケジュールを登録しますか？')) {
                router.visit(route('coordinator.project_jobs.show', { projectJob: latestJobId }));
            }
        }
    }
});

// ── CSV 一括登録 ──────────────────────────────────────────────────────────────
const showCsvModal     = ref(false);
const csvAnalyzing     = ref(false);
const csvImporting     = ref(false);
const csvAnalysisRows  = ref([]);
const csvFile          = ref(null);

function openCsvModal() {
    showCsvModal.value    = true;
    csvFile.value         = null;
    csvAnalyzing.value    = false;
    csvImporting.value    = false;
    csvAnalysisRows.value = [];
}

function closeCsvModal() {
    showCsvModal.value    = false;
    csvFile.value         = null;
    csvAnalyzing.value    = false;
    csvImporting.value    = false;
    csvAnalysisRows.value = [];
}

async function onCsvFileSelect(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    csvFile.value = file;
    csvAnalyzing.value = true;
    csvAnalysisRows.value = [];
    const fd   = new FormData();
    fd.append('csv', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(route('coordinator.project_jobs.csv.analyze'), fd, {
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'multipart/form-data' },
        });
        csvAnalysisRows.value = res.data.rows ?? [];
    } catch {
        alert('CSV解析に失敗しました。ファイルを確認してください。');
    } finally {
        csvAnalyzing.value = false;
        e.target.value = '';
    }
}

function csvSelectCandidate(rowIndex, client) {
    const row = csvAnalysisRows.value[rowIndex];
    if (!row) return;
    row.resolved_client_id   = client.id;
    row.resolved_client_name = client.name;
    row.status               = 'matched';
}

function csvSelectFromSearch(rowIndex, client) {
    csvSelectCandidate(rowIndex, client);
    csvAnalysisRows.value[rowIndex].showSearch = false;
}

function csvSelectSalesRepCandidate(rowIndex, rep) {
    const row = csvAnalysisRows.value[rowIndex];
    if (!row) return;
    row.resolved_sales_rep_id   = rep.id;
    row.resolved_sales_rep_name = rep.name;
    row.sales_rep_status        = 'matched';
}

function csvSelectSalesRepFromSearch(rowIndex, rep) {
    csvSelectSalesRepCandidate(rowIndex, rep);
    csvAnalysisRows.value[rowIndex].showSalesRepSearch = false;
}

const csvUnresolvedCount = computed(() =>
    csvAnalysisRows.value.filter(r => r.status !== 'matched').length
);
const csvDupSkipCount = computed(() =>
    csvAnalysisRows.value.filter(r => r.jobcode_dup && r.jobcode_dup !== 'none').length
);
const csvImportableCount = computed(() =>
    csvAnalysisRows.value.length - csvDupSkipCount.value
);

const csvRowClientSearch   = ref({});
const csvRowSalesRepSearch = ref({});
let csvSearchTimers = {};

function onCsvRowSearchInput(rowIndex) {
    clearTimeout(csvSearchTimers[`c_${rowIndex}`]);
    const q = csvRowClientSearch.value[rowIndex] ?? '';
    if (!q.trim()) { csvAnalysisRows.value[rowIndex].searchResults = []; return; }
    csvSearchTimers[`c_${rowIndex}`] = setTimeout(async () => {
        const res = await axios.get(route('coordinator.clients.json'), { params: { name: q } });
        if (csvAnalysisRows.value[rowIndex]) csvAnalysisRows.value[rowIndex].searchResults = res.data;
    }, 250);
}

function onCsvRowSalesRepSearchInput(rowIndex) {
    clearTimeout(csvSearchTimers[`s_${rowIndex}`]);
    const q = csvRowSalesRepSearch.value[rowIndex] ?? '';
    if (!q.trim()) { csvAnalysisRows.value[rowIndex].salesRepSearchResults = []; return; }
    csvSearchTimers[`s_${rowIndex}`] = setTimeout(async () => {
        const res = await axios.get(route('coordinator.sales_reps.api.list'));
        if (csvAnalysisRows.value[rowIndex]) {
            csvAnalysisRows.value[rowIndex].salesRepSearchResults = res.data.filter(r =>
                r.name.includes(q)
            );
        }
    }, 250);
}

// ── インライン クライアント新規登録 ──────────────────────────────────────────
const showInlineClientModal = ref(false);
const inlineCsvRowIndex     = ref(null);
const inlineClientForm      = ref({ name: '', client_code: '' });
const inlineClientSaving    = ref(false);
const inlineClientNote      = ref('');

function openInlineClientModal(rowIndex) {
    inlineCsvRowIndex.value  = rowIndex;
    const row = csvAnalysisRows.value[rowIndex];
    inlineClientForm.value   = { name: row?.raw_client_name ?? '', client_code: '' };
    inlineClientNote.value   = '';
    inlineClientSaving.value = false;
    showInlineClientModal.value = true;
}

async function saveInlineClient() {
    if (!inlineClientForm.value.name) return;
    inlineClientSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(
            route('coordinator.project_jobs.csv.client_create'),
            inlineClientForm.value,
            { headers: { 'X-CSRF-TOKEN': csrf } },
        );
        const client = res.data.client;
        const idx = inlineCsvRowIndex.value;
        if (idx !== null && csvAnalysisRows.value[idx]) {
            csvAnalysisRows.value[idx].resolved_client_id   = client.id;
            csvAnalysisRows.value[idx].resolved_client_name = client.name;
            csvAnalysisRows.value[idx].status               = 'matched';
        }
        if (res.data.was_existing) {
            inlineClientNote.value = `「${client.name}」は既に登録されています。該当クライアントを使用します。`;
        } else {
            showInlineClientModal.value = false;
        }
    } catch {
        alert('クライアント登録に失敗しました。');
    } finally {
        inlineClientSaving.value = false;
    }
}

// ── インライン 営業担当新規登録 ──────────────────────────────────────────────
const showInlineSalesRepModal = ref(false);
const inlineSalesRepRowIndex  = ref(null);
const inlineSalesRepForm      = ref({ name: '', company: '' });
const inlineSalesRepSaving    = ref(false);

function openInlineSalesRepModal(rowIndex) {
    inlineSalesRepRowIndex.value  = rowIndex;
    const row = csvAnalysisRows.value[rowIndex];
    inlineSalesRepForm.value      = { name: row?.sales_rep ?? '', company: '' };
    inlineSalesRepSaving.value    = false;
    showInlineSalesRepModal.value = true;
}

async function saveInlineSalesRep() {
    if (!inlineSalesRepForm.value.name) return;
    inlineSalesRepSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(
            route('coordinator.sales_reps.api.create'),
            inlineSalesRepForm.value,
            { headers: { 'X-CSRF-TOKEN': csrf } },
        );
        const rep = res.data.rep;
        const idx = inlineSalesRepRowIndex.value;
        if (idx !== null && csvAnalysisRows.value[idx]) {
            csvAnalysisRows.value[idx].resolved_sales_rep_id   = rep.id;
            csvAnalysisRows.value[idx].resolved_sales_rep_name = rep.name;
            csvAnalysisRows.value[idx].sales_rep_status        = 'matched';
        }
        showInlineSalesRepModal.value = false;
    } catch (err) {
        const msg = err.response?.data?.error ?? '営業担当登録に失敗しました。';
        alert(msg);
    } finally {
        inlineSalesRepSaving.value = false;
    }
}

async function doCsvImport() {
    if (csvUnresolvedCount.value > 0) return;
    csvImporting.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const rows = csvAnalysisRows.value
            .filter(r => !r.jobcode_dup || r.jobcode_dup === 'none')
            .map(r => ({
                jobcode:      r.jobcode || null,
                title:        r.title,
                client_id:    r.resolved_client_id    || null,
                client_name:  r.resolved_client_name  || r.raw_client_name || null,
                sales_rep:    r.sales_rep              || null,
                sales_rep_id: r.resolved_sales_rep_id || null,
            }));
        const res = await axios.post(route('coordinator.project_jobs.csv.import'), { rows }, {
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        alert(`${res.data.imported}件を登録しました。` + (res.data.skipped_dup > 0 ? `（重複${res.data.skipped_dup}件スキップ）` : ''));
        closeCsvModal();
        router.reload({ only: [] });
    } catch {
        alert('登録に失敗しました。もう一度お試しください。');
    } finally {
        csvImporting.value = false;
    }
}
</script>
