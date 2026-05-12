<template>
    <AppLayout title="案件一括作成">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('coordinator.project_jobs.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 案件一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">案件一括作成</h2>
            </div>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- タブ切替 -->
            <div class="mb-6 flex gap-0 border-b border-gray-200">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="activeTab === tab.key
                        ? 'border-b-2 border-blue-600 text-blue-600 font-semibold'
                        : 'text-gray-500 hover:text-gray-700'"
                    class="px-6 py-3 text-sm"
                    @click="activeTab = tab.key"
                >{{ tab.label }}</button>
            </div>

            <!-- ══════════════════════════════════════════════
                 タブ1: テンプレート管理
            ══════════════════════════════════════════════ -->
            <div v-show="activeTab === 'template'">

                <!-- テンプレート一覧 -->
                <div class="mb-6">
                    <h2 class="mb-3 font-semibold text-gray-700">保存済みテンプレート</h2>
                    <div v-if="localTemplates.length === 0" class="text-sm text-gray-400">
                        テンプレートはまだありません。
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="t in localTemplates"
                            :key="t.id"
                            type="button"
                            :class="selectedTemplateId === t.id
                                ? 'border-blue-500 bg-blue-50 text-blue-700'
                                : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="rounded border px-3 py-1.5 text-sm"
                            @click="loadTemplate(t)"
                        >
                            {{ t.name }}
                            <span v-if="t.is_shared" class="ml-1 text-xs text-gray-400">（共有）</span>
                        </button>
                    </div>
                    <button
                        type="button"
                        class="mt-3 text-sm text-blue-600 hover:underline"
                        @click="resetForm"
                    >＋ 新しいテンプレートを作成</button>
                </div>

                <hr class="mb-6" />

                <!-- テンプレート編集フォーム -->
                <form @submit.prevent="saveTemplate">
                    <div class="mb-4">
                        <label class="mb-1 block font-semibold">テンプレート名 <span class="text-red-500">*</span></label>
                        <input v-model="tplForm.name" type="text" class="w-full rounded border px-3 py-2" required />
                    </div>
                    <div class="mb-4">
                        <label class="mb-1 block font-semibold">説明</label>
                        <input v-model="tplForm.description" type="text" class="w-full rounded border px-3 py-2" />
                    </div>
                    <label class="mb-4 flex cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" v-model="tplForm.is_shared" class="rounded" />
                        このテンプレートを全員と共有する
                    </label>

                    <!-- 固定項目 -->
                    <h3 class="mb-3 mt-6 font-semibold text-gray-700">
                        固定項目
                        <span class="ml-1 text-xs font-normal text-gray-400">（設定した項目は CSV に列が出なくなります）</span>
                    </h3>

                    <!-- クライアント選択（最上位） -->
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium">クライアント（空 = CSV で入力）</label>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1">
                                <label class="text-xs">ID:</label>
                                <input 
                                    v-model="tplForm.fixed_fields.client_id" 
                                    type="number" 
                                    class="w-20 rounded border px-2 py-1 text-sm"
                                    placeholder="ID"
                                    @input="onClientIdChange"
                                    :disabled="isLoadingClientById" />
                                <div v-if="isLoadingClientById" class="text-xs text-blue-600">読込中...</div>
                            </div>
                            
                            <div class="relative flex-1">
                                <input 
                                    v-model="clientName" 
                                    type="text" 
                                    class="w-full rounded border px-2 py-1 text-sm"
                                    placeholder="名前を入力（オートコンプリート）"
                                    @input="onClientNameInput"
                                    @keydown="onClientNameKeydown"
                                    @blur="onClientNameBlur" />
                                
                                <!-- オートコンプリート候補リスト -->
                                <div v-if="showNameSuggestions && clientNameSuggestions.length > 0"
                                     class="absolute top-full z-50 mt-1 w-full rounded border border-gray-300 bg-white shadow-lg max-h-60 overflow-y-auto">
                                    <div v-for="(client, index) in clientNameSuggestions"
                                         :key="client.id"
                                         class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                                         :class="{ 'bg-blue-100': index === selectedSuggestionIndex }"
                                         @click="selectClientFromSuggestion(client)">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">{{ client.name }}</span>
                                            <span class="text-xs text-gray-500">ID: {{ client.id }}</span>
                                        </div>
                                        <div v-if="client.is_dormant" class="text-xs text-red-500">※ 休眠中</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" 
                                    class="rounded bg-blue-100 px-3 py-2 text-sm text-blue-700 hover:bg-blue-200" 
                                    @click="openClientModal">詳細検索</button>
                        </div>
                        
                        <!-- クライアントプリセットバナー -->
                        <div v-if="showPresetBanner && lastJobConfig"
                             class="mt-2 rounded border border-blue-300 bg-blue-50 px-4 py-3 text-sm">
                            <p class="font-semibold text-blue-800">前回の設定を引き継ぎますか？</p>
                            <p class="mt-1 text-blue-700">
                                （{{ lastJobConfig.job_created_at }}「{{ lastJobConfig.job_title }}」より）
                                リーダー: {{ lastJobConfig.user_name }}、
                                サイズ: {{ lastJobConfig.size_name || 'なし' }}、
                                メンバー: {{ lastJobConfig.team_members?.length || 0 }} 名
                            </p>
                            <div class="mt-2 flex gap-2">
                                <button type="button"
                                        class="rounded bg-blue-600 px-3 py-1 text-xs text-white hover:bg-blue-700"
                                        @click="applyPreset">引き継ぐ</button>
                                <button type="button"
                                        class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50"
                                        @click="showPresetBanner = false">今回は引き継がない</button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium">リーダー（空 = CSV で入力）</label>
                        <select v-model="tplForm.fixed_fields.user_id" class="w-full rounded border px-3 py-2 text-sm">
                            <option :value="null">-- CSV で入力 --</option>
                            <option v-for="c in props.coordinatorCandidates" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium">サブリーダー（空 = なし）</label>
                        <div class="max-h-32 overflow-y-auto rounded border px-3 py-2 space-y-1">
                            <template v-for="c in subCandidates" :key="c.id">
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" :value="c.id" v-model="tplForm.fixed_fields.sub_coordinator_ids" class="rounded" />
                                    {{ c.name }}
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium">サイズ（空 = CSV で入力）</label>
                        <!-- 媒体フィルター -->
                        <div class="mb-2 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                            <button v-for="opt in mediumOptions" :key="opt.value" type="button"
                                :class="sizeFilter === opt.value ? 'bg-white text-indigo-700 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                class="rounded px-3 py-1 text-xs"
                                @click="sizeFilter = opt.value">{{ opt.label }}</button>
                        </div>
                        <select v-model="tplForm.fixed_fields.size_id" class="w-full rounded border px-3 py-2 text-sm">
                            <option :value="null">-- CSV で入力 --</option>
                            <template v-for="grp in filteredSizeGroups" :key="grp.group">
                                <optgroup :label="grp.label">
                                    <option v-for="s in grp.items" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </optgroup>
                            </template>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium">総ページ数（空 = CSV で入力）</label>
                        <input v-model.number="tplForm.fixed_fields.page_count" type="number" min="1" max="99999"
                               class="w-40 rounded border px-3 py-2 text-sm" placeholder="例: 128" />
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium">詳細（空 = CSV で入力）</label>
                        <textarea v-model="tplForm.fixed_fields.detail" class="w-full rounded border px-3 py-2 text-sm" rows="2"></textarea>
                    </div>

                    <!-- チームメンバー -->
                    <h3 class="mb-3 mt-6 font-semibold text-gray-700">チームメンバー</h3>
                    <div class="mb-2 flex flex-wrap gap-2">
                        <span
                            v-for="(m, i) in tplForm.team_members"
                            :key="m.user_id"
                            class="flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700"
                        >
                            {{ m.user_name }}
                            <button type="button" class="ml-1 text-blue-400 hover:text-red-500" @click="removeMember(i)">×</button>
                        </span>
                        <span v-if="tplForm.team_members.length === 0" class="text-sm text-gray-400">メンバー未設定</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" 
                                class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                @click="openMemberModal">
                            メンバーを選択
                        </button>
                        <span class="text-sm text-gray-500">{{ tplForm.team_members.length }}人選択中</span>
                    </div>

                    <!-- チームメンバー選択モーダル -->
                    <DialogModal :show="showMemberModal" @close="closeMemberModal">
                        <template #title>
                            チームメンバー選択
                        </template>
                        
                        <template #content>
                            <!-- フィルター -->
                            <div class="mb-4 flex items-center gap-4">
                                <div class="flex-1">
                                    <label class="mb-1 block text-sm font-medium">部署</label>
                                    <select v-model="selectedDepartmentId" 
                                            class="w-full rounded border px-3 py-2 text-sm">
                                        <option value="">-- 全部署 --</option>
                                        <option v-for="dept in departments" 
                                                :key="dept.id" 
                                                :value="String(dept.id)">
                                            {{ dept.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-1 block text-sm font-medium">担当</label>
                                    <select v-model="selectedAssignmentId" 
                                            class="w-full rounded border px-3 py-2 text-sm"
                                            :disabled="!selectedDepartmentId">
                                        <option value="">-- 全担当 --</option>
                                        <option v-for="assignment in filteredAssignments" 
                                                :key="assignment.id" 
                                                :value="String(assignment.id)">
                                            {{ assignment.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <button type="button" 
                                            class="rounded bg-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-400"
                                            @click="clearMemberFilters">
                                        クリア
                                    </button>
                                </div>
                            </div>

                            <!-- メンバー一覧テーブル -->
                            <div class="max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="sticky top-0 bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                <input type="checkbox" 
                                                       :checked="allChecked" 
                                                       @change="toggleAllMembers" />
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <tr v-for="member in filteredMembers"
                                            :key="member.id"
                                            class="hover:bg-gray-50 cursor-pointer"
                                            :class="{ 'bg-blue-50': selectedMemberIds.includes(member.id) }"
                                            @click="toggleMember(member.id)">
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500" @click.stop>
                                                <input type="checkbox" 
                                                       :value="member.id" 
                                                       v-model="selectedMemberIds" />
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                                {{ member.name }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                {{ getDepartmentName(member.department_id) }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                <span :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))"
                                                      class="inline-flex rounded-full px-2 py-1 text-xs font-semibold">
                                                    {{ getAssignmentName(member.assignment_id) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="filteredMembers.length === 0" class="py-8 text-center text-gray-500">
                                    該当するメンバーがいません
                                </div>
                            </div>

                            <div v-if="selectedMemberIds.length > 0" class="mt-4 rounded bg-blue-50 p-3">
                                <div class="text-sm font-medium text-blue-700">
                                    {{ selectedMemberIds.length }}人選択中
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span v-for="memberId in selectedMemberIds" 
                                          :key="memberId"
                                          class="inline-flex rounded bg-blue-100 px-2 py-1 text-xs text-blue-700">
                                        {{ members.find(m => m.id === memberId)?.name }}
                                    </span>
                                </div>
                            </div>
                        </template>

                        <template #footer>
                            <button type="button" 
                                    class="mr-3 rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                    @click="closeMemberModal">
                                キャンセル
                            </button>
                            <button type="button" 
                                    class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                                    :disabled="selectedMemberIds.length === 0"
                                    @click="addSelectedMembers">
                                追加 ({{ selectedMemberIds.length }}人)
                            </button>
                        </template>
                    </DialogModal>

                    <!-- 保存・削除ボタン -->
                    <div class="mt-8 flex items-center gap-3">
                        <button type="submit" :disabled="savingTemplate"
                                class="rounded bg-blue-600 px-5 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                            {{ selectedTemplateId ? 'テンプレートを更新' : 'テンプレートを保存' }}
                        </button>
                        <button v-if="selectedTemplateId" type="button"
                                class="rounded bg-red-100 px-4 py-2 text-sm text-red-700 hover:bg-red-200"
                                @click="deleteTemplate">削除</button>
                        <button v-if="selectedTemplateId" type="button"
                                class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                                @click="resetForm">新規作成に切り替え</button>
                        <span v-if="saveMessage" class="text-sm text-green-600">{{ saveMessage }}</span>
                    </div>
                </form>
            </div>

            <!-- ══════════════════════════════════════════════
                 タブ2: CSV取込
            ══════════════════════════════════════════════ -->
            <div v-show="activeTab === 'csv'">

                <!-- テンプレート選択 -->
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">使用するテンプレート</label>
                    <select v-model="csvTemplateId" class="w-80 rounded border px-3 py-2 text-sm">
                        <option :value="null">-- テンプレートなし（全項目CSVで入力） --</option>
                        <option v-for="t in localTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <!-- サンプルCSVダウンロード -->
                <div class="mb-6">
                    <a
                        :href="sampleDownloadUrl"
                        class="inline-flex items-center rounded border border-green-600 px-4 py-2 text-sm text-green-700 hover:bg-green-50"
                        download
                    >
                        サンプルCSVをダウンロード
                    </a>
                    <p class="mt-1 text-xs text-gray-400">
                        テンプレートで固定した項目は CSV 列に含まれません。
                    </p>
                </div>

                <!-- CSVアップロードフォーム -->
                <form @submit.prevent="submitPreview" class="mb-6">
                    <div class="mb-4">
                        <label class="mb-1 block font-semibold">CSVファイルを選択</label>
                        <input ref="csvFileInput" type="file" accept=".csv,text/csv" class="block" @change="onFileChange" />
                        <div v-if="csvFileError" class="mt-1 text-sm text-red-600">{{ csvFileError }}</div>
                        <div v-if="uploadError" class="mt-2 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <div class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                                <div>
                                    <div class="font-semibold">アップロードエラー</div>
                                    <div>{{ uploadError }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            対応形式: CSV (.csv) / 最大サイズ: 2MB
                        </div>
                    </div>
                    <button type="submit" :disabled="!csvFile || previewLoading"
                            class="rounded bg-blue-600 px-5 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                        <span v-if="previewLoading" class="flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            処理中...
                        </span>
                        <span v-else>プレビュー確認</span>
                    </button>
                    
                    <!-- デバッグ情報（開発環境のみ） -->
                    <div v-if="uploadError && $page.props.app?.debug" class="mt-2 text-xs text-gray-500">
                        <details>
                            <summary class="cursor-pointer hover:text-gray-700">技術詳細を表示</summary>
                            <div class="mt-1 rounded bg-gray-100 p-2 font-mono text-xs">
                                ファイル名: {{ csvFile?.name }}<br>
                                サイズ: {{ csvFile ? Math.round(csvFile.size / 1024) : 0 }} KB<br>
                                タイプ: {{ csvFile?.type }}<br>
                                最終更新: {{ csvFile ? new Date(csvFile.lastModified).toLocaleString('ja-JP') : '' }}
                            </div>
                        </details>
                    </div>
                </form>

                <!-- プレビュー結果 -->
                <div v-if="preview && preview.rows">
                    <div class="mb-3 flex items-center gap-4 text-sm">
                        <span class="text-green-600 font-medium">有効 {{ preview.validCount }} 件</span>
                        <span v-if="preview.errorCount > 0" class="text-red-600 font-medium">エラー {{ preview.errorCount }} 件</span>
                        <span class="text-gray-500">（全 {{ preview.rows.length }} 行）</span>
                    </div>

                    <div class="overflow-x-auto rounded border">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">行</th>
                                    <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">タイトル</th>
                                    <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">クライアント</th>
                                    <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">伝票番号</th>
                                    <th class="border-b px-3 py-2 text-left text-xs font-medium text-gray-500">エラー / 警告</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in preview.rows" :key="row.rowNum"
                                    :class="row.errors.length > 0 ? 'bg-red-50' : row.warnings.length > 0 ? 'bg-yellow-50' : ''">
                                    <td class="border-b px-3 py-1.5 text-gray-500">{{ row.rowNum }}</td>
                                    <td class="border-b px-3 py-1.5">{{ row.data.title || '-' }}</td>
                                    <td class="border-b px-3 py-1.5">
                                        {{ row.data.client_name || row.data.client_id || '-' }}
                                    </td>
                                    <td class="border-b px-3 py-1.5 text-gray-500">{{ row.data.jobcode || '-' }}</td>
                                    <td class="border-b px-3 py-1.5">
                                        <span v-for="e in row.errors" :key="e" class="mr-1 text-xs text-red-600">{{ e }}</span>
                                        <span v-for="w in row.warnings" :key="w" class="mr-1 text-xs text-yellow-600">{{ w }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 登録実行ボタン -->
                    <div class="mt-4">
                        <button
                            v-if="preview.errorCount === 0"
                            type="button"
                            :disabled="storing"
                            class="rounded bg-green-600 px-6 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                            @click="executeStore"
                        >
                            {{ storing ? '登録中...' : `${preview.validCount} 件を一括登録する` }}
                        </button>
                        <p v-else class="text-sm text-red-600">
                            エラーがある行があります。CSVを修正して再度アップロードしてください。
                        </p>
                    </div>
                </div>

                <!-- プレビューなし / ローディング状態 -->
                <div v-else-if="!preview && !previewLoading" class="text-center py-8 text-gray-500">
                    CSVファイルをアップロードしてプレビューを確認してください
                </div>

                <!-- デバッグ情報（開発環境） -->
                <div v-if="$page.props.app?.debug" class="mt-4 text-xs text-gray-500">
                    <details>
                        <summary class="cursor-pointer hover:text-gray-700">デバッグ情報</summary>
                        <div class="mt-1 rounded bg-gray-100 p-2 font-mono text-xs">
                            propsPreviewData: {{ props.previewData ? 'あり' : 'なし' }}<br>
                            previewValue: {{ preview ? 'あり' : 'なし' }}<br>
                            previewLoading: {{ previewLoading }}<br>
                            uploadError: {{ uploadError || 'なし' }}<br>
                            activeTab: {{ activeTab }}
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <!-- クライアント検索モーダル -->
        <DialogModal :show="showClientModal" @close="closeClientModal">
            <template #title>クライアント検索</template>
            <template #content>
                <div class="mb-2 flex gap-4">
                    <label><input type="radio" value="id" v-model="clientSearchMode" /> IDで検索</label>
                    <label><input type="radio" value="name" v-model="clientSearchMode" /> 名前で検索</label>
                    <label><input type="radio" value="list" v-model="clientSearchMode" /> 一覧から検索</label>
                </div>
                <div v-if="clientSearchMode === 'id'" class="mb-2">
                    <input v-model="clientSearch.id" type="number" placeholder="IDを入力" class="rounded border px-2 py-1" />
                    <button class="ml-2 rounded bg-blue-500 px-2 py-1 text-white" @click="searchClientById">検索</button>
                </div>
                <div v-if="clientSearchMode === 'name'" class="mb-2">
                    <input v-model="clientSearch.name" type="text" placeholder="名前を入力" class="rounded border px-2 py-1" />
                    <button class="ml-2 rounded bg-blue-500 px-2 py-1 text-white" @click="searchClientByName">検索</button>
                </div>
                <div v-if="clientSearchResult">
                    <div class="mt-2">
                        検索結果: <span class="font-bold">{{ clientSearchResult.id }} {{ clientSearchResult.name }}</span>
                        <button class="ml-2 rounded bg-green-500 px-2 py-1 text-white" @click="selectClient(clientSearchResult)">選択</button>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="rounded bg-gray-300 px-4 py-2" @click="closeClientModal">閉じる</button>
            </template>
        </DialogModal>

        <!-- クライアント一覧モーダル -->
        <DialogModal :show="showClientListModal" @close="closeClientListModal">
            <template #title>クライアント一覧</template>
            <template #content>
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">ID</th>
                            <th class="px-4 py-2">会社名</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="client in clientList" :key="client.id" @click="selectClient(client)" class="cursor-pointer hover:bg-blue-100">
                            <td class="px-4 py-2">{{ client.id }}</td>
                            <td class="px-4 py-2">{{ client.name }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="clientList.length === 0" class="py-4 text-gray-500">クライアントがありません</div>
            </template>
            <template #footer>
                <button class="rounded bg-gray-300 px-4 py-2" @click="closeClientListModal">閉じる</button>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';

const props = defineProps({
    templates:             { type: Array, default: () => [] },
    coordinatorCandidates: { type: Array, default: () => [] },
    sizes:                 { type: Array, default: () => [] },
    users:                 { type: Array, default: () => [] },
    departments:           { type: Array, default: () => [] },
    assignments:           { type: Array, default: () => [] },
    members:               { type: Array, default: () => [] },
    previewData:           { type: Object, default: null },
});

const page = usePage();

// ── タブ ────────────────────────────────────────────────────
const tabs = [
    { key: 'template', label: 'テンプレート管理' },
    { key: 'csv',      label: 'CSV取込' },
];
const activeTab = ref(props.previewData ? 'csv' : 'template');

// ── テンプレート管理 ─────────────────────────────────────────

const localTemplates = ref([...props.templates]);
const selectedTemplateId = ref(null);
const savingTemplate = ref(false);
const saveMessage = ref('');

const tplForm = ref(blankTplForm());

function blankTplForm() {
    return {
        name: '',
        description: '',
        is_shared: false,
        fixed_fields: {
            client_id: null,
            user_id: null,
            sub_coordinator_ids: [],
            size_id: null,
            page_count: null,
            detail: '',
        },
        team_members: [],
    };
}

function resetForm() {
    selectedTemplateId.value = null;
    tplForm.value = blankTplForm();
    saveMessage.value = '';
    
    // クライアント選択関連もリセット
    clientName.value = '';
    showPresetBanner.value = false;
    lastJobConfig.value = null;
}

function loadTemplate(t) {
    selectedTemplateId.value = t.id;
    tplForm.value = {
        name:        t.name,
        description: t.description || '',
        is_shared:   t.is_shared,
        fixed_fields: {
            client_id:           t.fixed_fields?.client_id           ?? null,
            user_id:             t.fixed_fields?.user_id             ?? null,
            sub_coordinator_ids: t.fixed_fields?.sub_coordinator_ids ?? [],
            size_id:             t.fixed_fields?.size_id             ?? null,
            page_count:          t.fixed_fields?.page_count          ?? null,
            detail:              t.fixed_fields?.detail              ?? '',
        },
        team_members: [...(t.team_members ?? [])],
    };
    saveMessage.value = '';
    
    // クライアントが設定されていれば名前を取得
    if (t.fixed_fields?.client_id) {
        loadClientNameById(t.fixed_fields.client_id);
    } else {
        clientName.value = '';
    }
}

const subCandidates = computed(() =>
    props.coordinatorCandidates.filter((c) => c.id !== tplForm.value.fixed_fields.user_id),
);

// サイズフィルター
const sizeFilter = ref('paper');
const mediumOptions = [
    { value: 'paper',   label: '紙媒体' },
    { value: 'digital', label: 'デジタル' },
    { value: '',        label: '全て' },
];
const GROUP_LABELS = { paper: '紙媒体', digital: 'デジタル', web: 'Web', other: 'その他' };

// ── クライアント選択機能 ─────────────────────────────────────────
const clientName = ref('');
const showClientModal = ref(false);
const showClientListModal = ref(false);
const clientSearchMode = ref('id');
const clientSearch = ref({ id: '', name: '' });
const clientSearchResult = ref(null);
const clientList = ref([]);

// オートコンプリート用
const clientNameSuggestions = ref([]);
const showNameSuggestions = ref(false);
const isLoadingClientById = ref(false);
const selectedSuggestionIndex = ref(-1);
let searchTimeout = null;

// プリセット機能用
const showPresetBanner = ref(false);
const lastJobConfig = ref(null);

// ID入力時の名前自動取得
async function onClientIdChange() {
    const clientId = tplForm.value.fixed_fields.client_id;
    if (!clientId || clientId === '') {
        clientName.value = '';
        return;
    }
    
    isLoadingClientById.value = true;
    try {
        const res = await fetch(
            route('coordinator.clients.json') + '?id=' + encodeURIComponent(clientId),
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
        );
        if (res.ok) {
            const client = await res.json();
            if (client) {
                clientName.value = client.name;
                // プリセット取得も実行
                await loadClientPreset(client);
            } else {
                clientName.value = '';
            }
        } else {
            clientName.value = '';
        }
    } catch (error) {
        console.error('クライアント取得エラー:', error);
        clientName.value = '';
    } finally {
        isLoadingClientById.value = false;
    }
}

// クライアントIDから名前を取得（テンプレート読み込み時用）
async function loadClientNameById(clientId) {
    if (!clientId) {
        clientName.value = '';
        return;
    }
    
    try {
        const res = await fetch(
            route('coordinator.clients.json') + '?id=' + encodeURIComponent(clientId),
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
        );
        if (res.ok) {
            const client = await res.json();
            if (client) {
                clientName.value = client.name;
            }
        }
    } catch (error) {
        console.error('クライアント名取得エラー:', error);
    }
}

// 名前入力時のオートコンプリート
function onClientNameInput() {
    const searchTerm = clientName.value;
    
    // 検索をクリア
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    if (!searchTerm || searchTerm.length < 1) {
        clientNameSuggestions.value = [];
        showNameSuggestions.value = false;
        return;
    }
    
    // デバウンス（300ms後に検索実行）
    searchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(
                route('coordinator.clients.json') + '?name=' + encodeURIComponent(searchTerm) + '&limit=10',
                { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
            );
            if (res.ok) {
                const clients = await res.json();
                clientNameSuggestions.value = Array.isArray(clients) ? clients : [];
                showNameSuggestions.value = clients.length > 0;
                selectedSuggestionIndex.value = -1;
            }
        } catch (error) {
            console.error('クライアント検索エラー:', error);
            clientNameSuggestions.value = [];
            showNameSuggestions.value = false;
        }
    }, 300);
}

// キーボード操作
function onClientNameKeydown(event) {
    if (!showNameSuggestions.value || clientNameSuggestions.value.length === 0) {
        return;
    }
    
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            selectedSuggestionIndex.value = Math.min(
                selectedSuggestionIndex.value + 1,
                clientNameSuggestions.value.length - 1
            );
            break;
        case 'ArrowUp':
            event.preventDefault();
            selectedSuggestionIndex.value = Math.max(
                selectedSuggestionIndex.value - 1,
                -1
            );
            break;
        case 'Enter':
            event.preventDefault();
            if (selectedSuggestionIndex.value >= 0) {
                selectClientFromSuggestion(clientNameSuggestions.value[selectedSuggestionIndex.value]);
            }
            break;
        case 'Escape':
            showNameSuggestions.value = false;
            selectedSuggestionIndex.value = -1;
            break;
    }
}

// 候補選択
async function selectClientFromSuggestion(client) {
    tplForm.value.fixed_fields.client_id = client.id;
    clientName.value = client.name;
    showNameSuggestions.value = false;
    selectedSuggestionIndex.value = -1;
    
    // プリセット取得
    await loadClientPreset(client);
}

// フォーカスアウト時に候補を非表示
function onClientNameBlur() {
    // 少し遅延させて、候補クリック時間を確保
    setTimeout(() => {
        showNameSuggestions.value = false;
        selectedSuggestionIndex.value = -1;
    }, 200);
}

// プリセット取得の共通化
async function loadClientPreset(client) {
    showPresetBanner.value = false;
    lastJobConfig.value = null;
    try {
        const res = await fetch(
            route('coordinator.clients.last_job_config', { client: client.id }),
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
        );
        if (res.ok) {
            const config = await res.json();
            if (config) {
                lastJobConfig.value = config;
                showPresetBanner.value = true;
            }
        }
    } catch { /* ignore */ }
}

// プリセット適用
function applyPreset() {
    const c = lastJobConfig.value;
    if (!c) return;
    
    // 設定を引き継ぐ（タイトル・伝票番号は除く）
    if (c.user_id)             tplForm.value.fixed_fields.user_id = c.user_id;
    if (c.sub_coordinator_ids) tplForm.value.fixed_fields.sub_coordinator_ids = [...c.sub_coordinator_ids];
    if (c.size_id)             tplForm.value.fixed_fields.size_id = c.size_id;
    if (c.page_count)          tplForm.value.fixed_fields.page_count = c.page_count;
    if (c.detail)              tplForm.value.fixed_fields.detail = c.detail;
    
    // チームメンバーも引き継ぐ
    if (c.team_members && Array.isArray(c.team_members)) {
        tplForm.value.team_members = [...c.team_members];
    }
    
    // プリセット適用後に、リーダー・サブリーダーがチームメンバーに含まれているか確認し、追加
    if (tplForm.value.fixed_fields.user_id) {
        addUserToTeamMembers(tplForm.value.fixed_fields.user_id);
    }
    if (tplForm.value.fixed_fields.sub_coordinator_ids && Array.isArray(tplForm.value.fixed_fields.sub_coordinator_ids)) {
        tplForm.value.fixed_fields.sub_coordinator_ids.forEach(userId => {
            addUserToTeamMembers(userId);
        });
    }
    
    showPresetBanner.value = false;
}

// 詳細検索モーダル
function openClientModal() {
    // クライアント一覧を即取得して空ならアラート
    fetch(route('coordinator.clients.json'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((res) => res.json())
        .then((data) => {
            if (data.length === 0) {
                alert(
                    'クライアントが登録されていません。\n進行管理の権限ではクライアント作成はできません。\nチームリーダーに作成を依頼してください。',
                );
            } else {
                showClientModal.value = true;
                clientSearchResult.value = null;
            }
        });
}

function closeClientModal() {
    showClientModal.value = false;
}

function openClientListModal() {
    // クライアント一覧取得APIを呼ぶ想定
    fetch(route('coordinator.clients.json'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((res) => res.json())
        .then((data) => {
            clientList.value = data;
            showClientListModal.value = true;
        });
}

function closeClientListModal() {
    showClientListModal.value = false;
}

function searchClientById() {
    if (!clientSearch.value.id) return;
    fetch(route('coordinator.clients.json') + '?id=' + encodeURIComponent(clientSearch.value.id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((res) => (res.ok ? res.json() : null))
        .then((data) => {
            clientSearchResult.value = data;
        });
}

function searchClientByName() {
    if (!clientSearch.value.name) return;
    fetch(route('coordinator.clients.json') + '?name=' + encodeURIComponent(clientSearch.value.name), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((res) => (res.ok ? res.json() : null))
        .then((data) => {
            clientSearchResult.value = data && data.length ? data[0] : null;
        });
}

// clientSearchModeが'list'になったら自動で一覧モーダルを開く
watch(clientSearchMode, (val) => {
    if (val === 'list') {
        openClientListModal();
    }
});

async function selectClient(client) {
    tplForm.value.fixed_fields.client_id = client.id;
    clientName.value = client.name;
    closeClientModal();
    closeClientListModal();
    
    // プリセット取得
    await loadClientPreset(client);
}
const filteredSizeGroups = computed(() => {
    const list = props.sizes ?? [];
    const filtered = sizeFilter.value ? list.filter((s) => s.group === sizeFilter.value) : list;
    const map = new Map();
    for (const s of filtered) {
        const g = s.group || 'other';
        if (!map.has(g)) map.set(g, []);
        map.get(g).push(s);
    }
    return [...map.entries()].map(([group, items]) => ({ group, label: GROUP_LABELS[group] ?? group, items }));
});

// チームメンバー
const memberToAdd = ref('');

// チームメンバー選択モーダル
const showMemberModal = ref(false);
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');
const selectedMemberIds = ref([]);
const availableUsers = computed(() => {
    const ids = new Set(tplForm.value.team_members.map((m) => m.user_id));
    return props.users.filter((u) => !ids.has(u.id));
});

// チームメンバー選択モーダル用 computed
const filteredAssignments = computed(() => {
    if (!selectedDepartmentId.value) return [];
    return props.assignments.filter(assignment => 
        props.members.some(member => 
            member.department_id == selectedDepartmentId.value && 
            member.assignment_id == assignment.id
        )
    );
});

const filteredMembers = computed(() => {
    let filtered = props.members;
    
    if (selectedDepartmentId.value) {
        filtered = filtered.filter(m => m.department_id == selectedDepartmentId.value);
    }
    
    if (selectedAssignmentId.value) {
        filtered = filtered.filter(m => m.assignment_id == selectedAssignmentId.value);
    }
    
    return filtered;
});

const allChecked = computed(() => {
    return filteredMembers.value.length > 0 && 
           filteredMembers.value.every(member => selectedMemberIds.value.includes(member.id));
});

// チームメンバー選択モーダル メソッド
function openMemberModal() {
    // リーダーとサブリーダーを初期選択状態にする
    const initialIds = [];
    
    // リーダー（user_id）を追加
    if (tplForm.value.fixed_fields.user_id) {
        initialIds.push(tplForm.value.fixed_fields.user_id);
    }
    
    // サブリーダー（sub_coordinator_ids）を追加
    if (Array.isArray(tplForm.value.fixed_fields.sub_coordinator_ids)) {
        initialIds.push(...tplForm.value.fixed_fields.sub_coordinator_ids);
    }
    
    // 既存のチームメンバーも選択状態にする
    tplForm.value.team_members.forEach(member => {
        if (!initialIds.includes(member.user_id)) {
            initialIds.push(member.user_id);
        }
    });
    
    selectedMemberIds.value = initialIds;
    showMemberModal.value = true;
}

function closeMemberModal() {
    showMemberModal.value = false;
    selectedDepartmentId.value = '';
    selectedAssignmentId.value = '';
    selectedMemberIds.value = [];
}

function addSelectedMembers() {
    selectedMemberIds.value.forEach(memberId => {
        const member = props.members.find(m => m.id === memberId);
        if (member) {
            const exists = tplForm.value.team_members.some(tm => tm.user_id === member.id);
            if (!exists) {
                tplForm.value.team_members.push({
                    user_id: member.id,
                    user_name: member.name
                });
            }
        }
    });
    closeMemberModal();
}

function toggleMember(memberId) {
    const index = selectedMemberIds.value.indexOf(memberId);
    if (index > -1) {
        selectedMemberIds.value.splice(index, 1);
    } else {
        selectedMemberIds.value.push(memberId);
    }
}

function toggleAllMembers() {
    if (allChecked.value) {
        selectedMemberIds.value = selectedMemberIds.value.filter(id => 
            !filteredMembers.value.some(member => member.id === id)
        );
    } else {
        filteredMembers.value.forEach(member => {
            if (!selectedMemberIds.value.includes(member.id)) {
                selectedMemberIds.value.push(member.id);
            }
        });
    }
}

function clearMemberFilters() {
    selectedDepartmentId.value = '';
    selectedAssignmentId.value = '';
}

function getDepartmentName(departmentId) {
    const dept = props.departments.find(d => d.id === departmentId);
    return dept ? dept.name : '';
}

function getAssignmentName(assignmentId) {
    const assignment = props.assignments.find(a => a.id === assignmentId);
    return assignment ? assignment.name : '';
}

function getAssignmentBadgeClass(assignmentName) {
    // Create.vueと同じスタイリング
    const colorMap = {
        '進行': 'bg-blue-100 text-blue-800',
        '営業': 'bg-green-100 text-green-800',
        '校正': 'bg-yellow-100 text-yellow-800',
        'DTP': 'bg-purple-100 text-purple-800',
        '製版': 'bg-red-100 text-red-800',
        '印刷': 'bg-gray-100 text-gray-800',
    };
    return colorMap[assignmentName] || 'bg-gray-100 text-gray-800';
}

// ===== リーダー・サブリーダー自動追加機能 =====
// チームメンバーに自動追加するヘルパー関数
function addUserToTeamMembers(userId) {
    if (!userId) return;
    
    // 既に存在するかチェック
    const exists = tplForm.value.team_members.some(member => member.user_id === userId);
    if (exists) return;
    
    // ユーザー情報を取得して追加
    const user = props.members.find(m => m.id === userId);
    if (user) {
        tplForm.value.team_members.push({
            user_id: user.id,
            user_name: user.name
        });
    }
}

function removeUserFromTeamMembers(userId) {
    if (!userId) return;
    const index = tplForm.value.team_members.findIndex(member => member.user_id === userId);
    if (index > -1) {
        tplForm.value.team_members.splice(index, 1);
    }
}

// リーダー変更を監視
watch(() => tplForm.value.fixed_fields.user_id, (newUserId, oldUserId) => {
    // 古いリーダーをチームメンバーから削除（サブリーダーでない場合）
    if (oldUserId && !tplForm.value.fixed_fields.sub_coordinator_ids.includes(oldUserId)) {
        removeUserFromTeamMembers(oldUserId);
    }
    
    // 新しいリーダーをチームメンバーに追加
    if (newUserId) {
        addUserToTeamMembers(newUserId);
    }
});

// サブリーダー変更を監視
watch(() => tplForm.value.fixed_fields.sub_coordinator_ids, (newSubIds, oldSubIds) => {
    const oldIds = oldSubIds || [];
    const newIds = newSubIds || [];
    
    // 削除されたサブリーダーをチームメンバーから削除（リーダーでない場合）
    oldIds.forEach(userId => {
        if (!newIds.includes(userId) && userId !== tplForm.value.fixed_fields.user_id) {
            removeUserFromTeamMembers(userId);
        }
    });
    
    // 追加されたサブリーダーをチームメンバーに追加
    newIds.forEach(userId => {
        if (!oldIds.includes(userId)) {
            addUserToTeamMembers(userId);
        }
    });
});

function addMember() {
    if (!memberToAdd.value) return;
    const user = props.users.find((u) => u.id === Number(memberToAdd.value));
    if (user) {
        tplForm.value.team_members.push({ user_id: user.id, user_name: user.name });
    }
    memberToAdd.value = '';
}
function removeMember(index) {
    tplForm.value.team_members.splice(index, 1);
}

async function saveTemplate() {
    if (!tplForm.value.name.trim()) return;
    savingTemplate.value = true;
    saveMessage.value = '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    try {
        const isUpdate = !!selectedTemplateId.value;
        const url = isUpdate
            ? route('coordinator.project_job_templates.update', { template: selectedTemplateId.value })
            : route('coordinator.project_job_templates.store');
        const method = isUpdate ? 'PUT' : 'POST';

        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name:         tplForm.value.name,
                description:  tplForm.value.description,
                is_shared:    tplForm.value.is_shared,
                fixed_fields: {
                    ...tplForm.value.fixed_fields,
                    // null/空文字を除外して送信
                    ...(tplForm.value.fixed_fields.client_id  == null ? {} : { client_id: tplForm.value.fixed_fields.client_id }),
                    ...(tplForm.value.fixed_fields.user_id    == null ? {} : { user_id: tplForm.value.fixed_fields.user_id }),
                    ...(tplForm.value.fixed_fields.size_id    == null ? {} : { size_id: tplForm.value.fixed_fields.size_id }),
                    ...(tplForm.value.fixed_fields.page_count == null ? {} : { page_count: tplForm.value.fixed_fields.page_count }),
                    ...((tplForm.value.fixed_fields.detail ?? '') === '' ? {} : { detail: tplForm.value.fixed_fields.detail }),
                    sub_coordinator_ids: tplForm.value.fixed_fields.sub_coordinator_ids ?? [],
                },
                team_members: tplForm.value.team_members,
            }),
        });

        if (res.ok) {
            const saved = await res.json();
            saveMessage.value = isUpdate ? '更新しました' : '保存しました';
            if (!isUpdate) {
                selectedTemplateId.value = saved.id;
                localTemplates.value.unshift({ ...saved });
            } else {
                const idx = localTemplates.value.findIndex((t) => t.id === saved.id);
                if (idx !== -1) localTemplates.value[idx] = { ...saved };
            }
        } else {
            const errorText = await res.text();
            console.error('Template save failed:', {
                status: res.status,
                statusText: res.statusText,
                errorText,
                csrf,
                url
            });
            saveMessage.value = `保存に失敗しました (${res.status}: ${res.statusText})`;
        }
    } catch (error) {
        console.error('Template save error:', error);
        saveMessage.value = `保存に失敗しました: ${error.message}`;
    } finally {
        savingTemplate.value = false;
    }
}

async function deleteTemplate() {
    if (!selectedTemplateId.value) return;
    if (!confirm('このテンプレートを削除しますか？')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await fetch(
            route('coordinator.project_job_templates.destroy', { template: selectedTemplateId.value }),
            {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            },
        );
        if (res.ok || res.status === 204) {
            localTemplates.value = localTemplates.value.filter((t) => t.id !== selectedTemplateId.value);
            resetForm();
        }
    } catch { /* ignore */ }
}

// ── CSV取込 ──────────────────────────────────────────────────

const csvTemplateId = ref(props.previewData?.templateId ?? null);
const csvFile = ref(null);
const csvFileError = ref('');
const csvFileInput = ref(null);
const previewLoading = ref(false);
const storing = ref(false);
const uploadError = ref('');  // サーバーエラー表示用

// サーバーからのプレビューデータ（preview エンドポイントの Inertia レスポンス）
const preview = ref(props.previewData ?? null);

// propsのpreviewDataが変更されたときにpreview.valueを更新
watch(() => props.previewData, (newPreviewData) => {
    preview.value = newPreviewData;
    console.log('プレビューデータが更新されました:', newPreviewData);
    
    // プレビューデータがあるときは自動的にCSVタブに切り替え
    if (newPreviewData) {
        activeTab.value = 'csv';
    }
});

// コンポーネントマウント時にpreviewDataがある場合は設定
onMounted(() => {
    if (props.previewData) {
        preview.value = props.previewData;
        activeTab.value = 'csv';
        console.log('マウント時にプレビューデータを設定:', props.previewData);
    }
});

const sampleDownloadUrl = computed(() => {
    const base = route('coordinator.project_jobs.bulk_create.sample');
    return csvTemplateId.value ? `${base}?template_id=${csvTemplateId.value}` : base;
});

function onFileChange(e) {
    const file = e.target.files?.[0];
    csvFileError.value = '';
    uploadError.value = '';
    preview.value = null;
    
    if (!file) {
        csvFile.value = null;
        return;
    }
    
    // ファイル検証
    const maxSize = 2 * 1024 * 1024; // 2MB
    if (file.size > maxSize) {
        csvFileError.value = 'ファイルサイズは2MB以下にしてください';
        return;
    }
    
    const allowedTypes = ['text/csv', 'application/csv', 'text/plain'];
    const fileExtension = file.name.toLowerCase().split('.').pop();
    if (!allowedTypes.includes(file.type) && fileExtension !== 'csv') {
        csvFileError.value = 'CSVファイル（.csv）を選択してください';
        return;
    }
    
    csvFile.value = file;
}

function submitPreview() {
    if (!csvFile.value) {
        csvFileError.value = 'CSVファイルを選択してください';
        return;
    }
    
    previewLoading.value = true;
    uploadError.value = '';

    const formData = new FormData();
    formData.append('csv_file', csvFile.value);
    if (csvTemplateId.value) {
        formData.append('template_id', String(csvTemplateId.value));
    }

    router.post(route('coordinator.project_jobs.bulk_create.preview'), formData, {
        onFinish: () => { 
            previewLoading.value = false; 
        },
        onError: (errors) => {
            console.error('CSV アップロード エラー:', errors);
            
            // サーバーエラーの詳細表示
            if (errors.csv_file) {
                csvFileError.value = errors.csv_file;
            } else if (errors.template_id) {
                uploadError.value = `テンプレートエラー: ${errors.template_id}`;
            } else if (typeof errors === 'string') {
                uploadError.value = errors;
            } else {
                // 一般的なエラー
                const errorMessages = Object.values(errors).flat();
                if (errorMessages.length > 0) {
                    uploadError.value = errorMessages.join(', ');
                } else {
                    uploadError.value = 'アップロードに失敗しました。ファイル形式やサイズを確認してください。';
                }
            }
        },
        onSuccess: () => {
            // 成功時は preview.value が props.previewData で更新される
            console.log('CSV アップロード成功');
            console.log('props.previewData:', props.previewData);
            console.log('preview.value:', preview.value);
            
            // アップロード成功メッセージを一時表示
            uploadError.value = '';
            
            // 強制的にpreviewを更新（watchが動作しない場合の保険）
            if (props.previewData && !preview.value) {
                preview.value = props.previewData;
                console.log('手動でpreview.valueを更新しました');
            }
        }
    });
}

function executeStore() {
    if (!preview.value || preview.value.errorCount > 0) return;
    
    storing.value = true;
    uploadError.value = '';
    
    router.post(
        route('coordinator.project_jobs.bulk_create.store'),
        {
            rows:        preview.value.rows,
            template_id: preview.value.templateId,
        },
        { 
            onFinish: () => { 
                storing.value = false; 
            },
            onError: (errors) => {
                console.error('一括登録 エラー:', errors);
                
                if (typeof errors === 'string') {
                    uploadError.value = `一括登録エラー: ${errors}`;
                } else {
                    const errorMessages = Object.values(errors).flat();
                    if (errorMessages.length > 0) {
                        uploadError.value = `一括登録エラー: ${errorMessages.join(', ')}`;
                    } else {
                        uploadError.value = '一括登録に失敗しました。しばらく経ってから再度お試しください。';
                    }
                }
            },
            onSuccess: () => {
                console.log('一括登録 成功');
                // 成功時はリダイレクトされるため、追加処理不要
            }
        },
    );
}
</script>
