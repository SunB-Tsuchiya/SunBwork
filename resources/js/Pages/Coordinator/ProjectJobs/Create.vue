<!--
 この画面は「プロジェクトジョブ登録フロー」の詳細登録用（step1）です。
 1. 伝票番号・案件タイトル・担当ユーザーID・クライアントID・詳細のみを登録。
 2. 登録後はshow画面へ遷移し、確認・案内を出す。
 3. confirmダイアログで「続いてメンバーを登録しますか？」を表示し、OKならProjectTeamMember/indexへ遷移。
 4. teammember/scheduleはnullで送信し、あとで登録。
-->

<template>
    <AppLayout title="プロジェクトジョブ作成">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('coordinator.project_jobs.index')"
                      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 案件一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">案件作成</h2>
            </div>
        </template>
        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit">
                <!-- クライアント選択（一番最初） -->
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">クライアント</label>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-1">
                            <label class="text-sm">ID:</label>
                            <input
                                v-model="form.client_id"
                                type="number"
                                class="w-20 rounded border px-3 py-2"
                                placeholder="ID"
                                @input="onClientIdChange"
                                :disabled="isLoadingClientById" />
                            <div v-if="isLoadingClientById" class="text-xs text-blue-600">読込中...</div>
                        </div>

                        <div class="relative flex-1">
                            <input
                                v-model="form.client_name"
                                type="text"
                                class="w-full rounded border px-3 py-2"
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
                                class="w-full rounded bg-blue-100 px-3 py-2 text-blue-700 hover:bg-blue-200 sm:w-auto"
                                @click="openClientModal">詳細検索</button>
                    </div>
                    <div v-if="form.errors.client_id" class="mt-1 text-sm text-red-600">{{ form.errors.client_id }}</div>
                </div>

                <!-- クライアントプリセットバナー -->
                <div v-if="showPresetBanner && lastJobConfig"
                     class="mb-4 rounded border border-blue-300 bg-blue-50 px-4 py-3 text-sm">
                    <p class="font-semibold text-blue-800">前回の設定を引き継ぎますか？</p>
                    <p class="mt-1 text-blue-700">
                        （{{ lastJobConfig.job_created_at }}「{{ lastJobConfig.job_title }}」より）
                        リーダー: {{ lastJobConfig.user_name }}、
                        サイズ: {{ lastJobConfig.size_name || 'なし' }}、
                        メンバー: {{ lastJobConfig.team_members?.length || 0 }} 名
                    </p>
                    <div class="mt-2 flex gap-2">
                        <button type="button"
                                class="rounded bg-indigo-600 px-3 py-1 text-xs text-white hover:bg-indigo-700"
                                @click="applyPreset">引き継ぐ</button>
                        <button type="button"
                                class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50"
                                @click="showPresetBanner = false">今回は引き継がない</button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="mb-1 block font-semibold">伝票番号</label>
                    <input
                        v-model="form.jobcode"
                        type="text"
                        class="w-full rounded border px-3 py-2"
                        inputmode="text"
                        title="数字とハイフンのみ入力できます"
                        @input="validateJobcode"
                    />
                    <div v-if="jobcodeError" class="mt-1 text-sm text-red-600">{{ jobcodeError }}</div>
                    <div v-if="form.errors.jobcode" class="mt-1 text-sm text-red-600">{{ form.errors.jobcode }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">案件タイトル</label>
                    <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2" required />
                    <div v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">リーダー（代表Coordinator）</label>
                    <select v-model="form.user_id" class="w-full rounded border px-3 py-2" required>
                        <option value="" disabled>選択してください</option>
                        <option v-for="c in props.coordinatorCandidates" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <div v-if="form.errors.user_id" class="mt-1 text-sm text-red-600">{{ form.errors.user_id }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">サブリーダー（複数可）</label>
                    <div class="rounded border px-3 py-2 max-h-40 overflow-y-auto space-y-1">
                        <template v-for="c in subCandidates" :key="c.id">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" :value="c.id" v-model="form.sub_coordinator_ids" class="rounded" />
                                <span>{{ c.name }}</span>
                            </label>
                        </template>
                        <p v-if="subCandidates.length === 0" class="text-sm text-gray-400">候補なし</p>
                    </div>
                    <div v-if="form.errors.sub_coordinator_ids" class="mt-1 text-sm text-red-600">{{ form.errors.sub_coordinator_ids }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">サイズ</label>
                    <!-- 媒体グループ切り替え -->
                    <div class="mb-2 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                        <button
                            v-for="opt in mediumOptions"
                            :key="opt.value"
                            type="button"
                            :class="sizeFilter === opt.value ? 'bg-white text-indigo-700 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                            class="rounded px-4 py-1.5 text-sm transition-all"
                            @click="sizeFilter = opt.value"
                        >{{ opt.label }}</button>
                    </div>
                    <select v-model="form.size_id" class="w-full rounded border px-3 py-2">
                        <option value="">-- 選択しない --</option>
                        <template v-for="grp in filteredSizeGroups" :key="grp.group">
                            <optgroup :label="grp.label">
                                <option v-for="s in grp.items" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </optgroup>
                        </template>
                    </select>
                    <div v-if="form.errors.size_id" class="mt-1 text-sm text-red-600">{{ form.errors.size_id }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">総ページ数</label>
                    <input
                        v-model.number="form.page_count"
                        type="number"
                        min="1"
                        max="99999"
                        step="1"
                        class="w-40 rounded border px-3 py-2"
                        placeholder="例: 128"
                        @input="validatePageCount"
                    />
                    <span class="ml-2 text-sm text-gray-500">ページ</span>
                    <div v-if="pageCountError" class="mt-1 text-sm text-red-600">{{ pageCountError }}</div>
                    <div v-if="form.errors.page_count" class="mt-1 text-sm text-red-600">{{ form.errors.page_count }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-3 py-2" rows="3"></textarea>
                    <div v-if="form.errors.detail" class="mt-1 text-sm text-red-600">{{ form.errors.detail }}</div>
                </div>

                <!-- 伝票画像 -->
                <div class="mb-6">
                    <label class="mb-2 block font-semibold">作業ファイル情報（伝票画像）</label>

                    <!-- サムネイル表示 -->
                    <div v-if="previewUrl" class="mb-3">
                        <div class="relative inline-block">
                            <img
                                :src="previewUrl"
                                :alt="previewName"
                                class="h-40 w-auto rounded-lg border border-gray-200 object-contain shadow-sm cursor-pointer"
                                @click="showLightbox = true"
                            />
                            <button
                                type="button"
                                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs text-white shadow hover:bg-red-600"
                                @click="removeImage"
                            >✕</button>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="max-w-xs truncate text-xs text-gray-500">{{ previewName }}</span>
                            <button
                                type="button"
                                class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                @click="showLightbox = true"
                            >🔍 拡大</button>
                        </div>
                    </div>

                    <!-- ドロップゾーン -->
                    <div
                        v-if="!previewUrl && !isOcrLoading"
                        class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-8 transition-colors"
                        :class="isDragging ? 'border-green-500 bg-green-50' : 'border-gray-300 bg-gray-50 hover:border-green-400'"
                        @dragover.prevent="isDragging = true"
                        @dragleave="isDragging = false"
                        @drop.prevent="onDropZoneDrop"
                    >
                        <div class="mb-2 text-3xl text-gray-400">📎</div>
                        <p class="text-sm text-gray-600">ここに画像をドロップ</p>
                        <p class="mt-1 text-xs text-gray-400">JPG / PNG / WEBP / HEIC / GIF / PDF 対応（最大 20MB）</p>
                    </div>

                    <!-- OCR ローディング -->
                    <div v-if="isOcrLoading" class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-green-400 bg-green-50 px-6 py-8">
                        <p class="text-sm font-medium text-green-700">OCR解析中...</p>
                        <p class="mt-1 text-xs text-gray-400">しばらくお待ちください</p>
                    </div>

                    <!-- ファイル選択ボタン -->
                    <div v-if="!isOcrLoading" class="mt-3 flex flex-wrap gap-2">
                        <label class="cursor-pointer rounded-lg border border-green-700 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">
                            📁 ファイルを読み込む（OCR自動入力）
                            <input type="file" accept="image/*,.pdf" class="hidden" @change="onFileInputChange" />
                        </label>
                        <label v-if="isMobile" class="cursor-pointer rounded-lg border border-gray-400 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            📷 カメラ画像を取り込む
                            <input type="file" accept="image/*" capture="environment" class="hidden" @change="onFileInputChange" />
                        </label>
                    </div>

                    <div v-if="form.errors.image" class="mt-1 text-sm text-red-600">{{ form.errors.image }}</div>
                </div>

                <!-- チームメンバー選択 -->
                <div class="mb-6">
                    <h3 class="mb-3 font-semibold text-gray-700">チームメンバー</h3>
                    <div class="mb-3 flex flex-wrap gap-2">
                        <span
                            v-for="(m, i) in form.team_members"
                            :key="m.user_id"
                            class="flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700"
                        >
                            {{ m.user_name }}
                            <button type="button" class="ml-1 text-blue-400 hover:text-red-500" @click="removeMember(i)">×</button>
                        </span>
                        <span v-if="form.team_members.length === 0" class="text-sm text-gray-400">メンバー未設定</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                                @click="openMemberModal">
                            メンバーを選択
                        </button>
                        <span class="text-sm text-gray-500">{{ form.team_members.length }}人選択中</span>
                    </div>
                    <div v-if="form.errors.team_members" class="mt-1 text-sm text-red-600">{{ form.errors.team_members }}</div>
                </div>

                <!-- メンバー・スケジュール登録は後続ステップで実装 -->
                <div class="mt-6 flex justify-end gap-3">
                    <Link :href="route('coordinator.project_jobs.index')"
                          class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                    >キャンセル</Link>
                    <button v-if="projectJobId" type="button" class="rounded bg-blue-100 px-4 py-2 text-sm text-blue-700 hover:bg-blue-200" @click="goSchedule">
                        スケジュール設定
                    </button>
                    <button type="submit" class="rounded bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700">作成</button>
                </div>
            </form>

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
                    <!-- clientSearchModeが'list'のときはボタンを表示せず、一覧を自動で出す -->
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
                    <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>会社名</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="client in clientList" :key="client.id" @click="selectClient(client)" class="cursor-pointer hover:bg-blue-100">
                                <td>{{ client.id }}</td>
                                <td>{{ client.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <div v-if="clientList.length === 0" class="py-4 text-gray-500">クライアントがありません</div>
                </template>
                <template #footer>
                    <button class="rounded bg-gray-300 px-4 py-2" @click="closeClientListModal">閉じる</button>
                </template>
            </DialogModal>
        </div>

        <!-- 案件名重複チェック警告モーダル -->
        <DialogModal :show="showDuplicateModal" @close="closeDuplicateModal">
            <template #title>
                <span class="flex items-center gap-2 text-yellow-700">
                    <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    類似案件が見つかりました
                </span>
            </template>
            <template #content>
                <div class="space-y-3">
                    <p class="text-sm text-gray-700">同一クライアントに似た名前の案件がすでに登録されています。</p>
                    <ul class="divide-y divide-yellow-100 rounded border border-yellow-200 bg-yellow-50">
                        <li v-for="job in duplicateJobs" :key="job.id" class="flex items-center justify-between px-3 py-2 text-sm">
                            <span class="font-medium text-gray-800">{{ job.title }}</span>
                            <span class="ml-3 whitespace-nowrap text-xs text-gray-500">{{ job.created_at }}</span>
                        </li>
                    </ul>
                    <p class="text-sm text-gray-600">タイトルを変えるか、そのまま登録するか選択してください。</p>
                </div>
            </template>
            <template #footer>
                <div class="flex w-full justify-between">
                    <button
                        class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 whitespace-nowrap hover:bg-gray-300"
                        @click="closeDuplicateModal"
                    >
                        閉じる（タイトルを変更する）
                    </button>
                    <button
                        class="rounded bg-orange-500 px-4 py-2 text-sm text-white hover:bg-orange-600"
                        @click="forceSubmit"
                    >
                        それでも登録する
                    </button>
                </div>
            </template>
        </DialogModal>

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
                    <div class="overflow-x-auto">
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
                    </div>
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

        <!-- 画像拡大ライトボックス -->
        <Teleport to="body">
            <div
                v-if="showLightbox && previewUrl"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
                @click.self="showLightbox = false"
            >
                <div class="relative max-h-[90vh] max-w-[90vw]">
                    <img :src="previewUrl" :alt="previewName" class="max-h-[85vh] max-w-[88vw] rounded-lg object-contain" />
                    <button
                        type="button"
                        class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-800 shadow-md hover:bg-gray-100"
                        @click="showLightbox = false"
                    >✕</button>
                </div>
            </div>
        </Teleport>
    </AppLayout>

    <!-- OCR ローディングオーバーレイ -->
    <Teleport to="body">
        <div v-if="isOcrLoading" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/50">
            <div class="rounded-xl bg-white px-8 py-6 shadow-xl">
                <p class="text-sm font-medium text-gray-700">OCR解析中...</p>
                <p class="mt-1 text-xs text-gray-400">しばらくお待ちください</p>
            </div>
        </div>
    </Teleport>

    <!-- OCR結果モーダル -->
    <OcrModal
        :show="showOcrModal"
        :ocrResult="ocrResult"
        @apply="onOcrApply"
        @close="showOcrModal = false"
    />
</template>

<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import OcrModal from '@/Components/Prepress/OcrModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    coordinatorCandidates: { type: Array, default: () => [] },
    sizes: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
});

const page = usePage();
const form = useForm({
    jobcode: '',
    title: '',
    user_id: page.props.auth.user.id,
    sub_coordinator_ids: [],
    client_id: '',
    client_name: '',
    size_id: '',
    page_count: '',
    detail: '',
    team_members: [],
    image: null,
    tmp_ocr_image_path: '',
});

// ── 伝票画像 ────────────────────────────────────────────────────────────────
const previewUrl   = ref(null);
const previewName  = ref('');
const isDragging   = ref(false);
const showLightbox = ref(false);

// ── OCR ─────────────────────────────────────────────────────────────────────
const isOcrLoading  = ref(false);
const showOcrModal  = ref(false);
const ocrResult     = ref({});

const isMobile = computed(() => {
    if (typeof navigator === 'undefined') return false;
    return /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
});

async function handleVoucherFile(file) {
    if (!file) return;
    isOcrLoading.value = true;
    showOcrModal.value = false;
    const fd = new FormData();
    fd.append('image', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(route('coordinator.project_jobs.ocr.analyze'), fd, {
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'multipart/form-data' },
        });
        ocrResult.value    = res.data;
        showOcrModal.value = true;
    } catch {
        // OCR 失敗時はそのままプレビュー表示
        form.image    = file;
        previewName.value = file.name;
        const reader = new FileReader();
        reader.onload = (e) => { previewUrl.value = e.target.result; };
        reader.readAsDataURL(file);
    } finally {
        isOcrLoading.value = false;
    }
}

function onOcrApply(result) {
    form.jobcode            = result.jobcode     || form.jobcode;
    form.title              = result.title       || form.title;
    form.client_id          = result.client_id   || '';
    form.client_name        = result.client_name || form.client_name;
    form.tmp_ocr_image_path = result.tmp_image_path || '';
    form.image              = null;
    if (result.tmp_image_path) {
        previewUrl.value  = result.image_url || previewUrl.value;
        previewName.value = result.original_filename || previewName.value;
    }
    showOcrModal.value = false;
}

function onDropZoneDrop(e) {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) handleVoucherFile(file);
}

function onFileInputChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (file) handleVoucherFile(file);
}

function removeImage() {
    form.image = null;
    form.tmp_ocr_image_path = '';
    previewUrl.value  = null;
    previewName.value = '';
}

// リーダーとして選択中のユーザーを除いたサブCo候補
const subCandidates = computed(() =>
    props.coordinatorCandidates.filter((c) => c.id !== form.user_id),
);

// ── サイズフィルター ──────────────────────────────────────
const sizeFilter = ref('paper');
const mediumOptions = [
    { value: 'paper', label: '紙媒体' },
    { value: 'digital', label: 'デジタル' },
    { value: '', label: '全て' },
];

const GROUP_LABELS = {
    paper: '紙媒体', digital: 'デジタル', web: 'Web', other: 'その他',
};

const filteredSizeGroups = computed(() => {
    const list = props.sizes ?? [];
    const filtered = sizeFilter.value ? list.filter((s) => s.group === sizeFilter.value) : list;
    const map = new Map();
    for (const s of filtered) {
        const g = s.group || 'other';
        if (!map.has(g)) map.set(g, []);
        map.get(g).push(s);
    }
    return [...map.entries()].map(([group, items]) => ({
        group,
        label: GROUP_LABELS[group] ?? group,
        items,
    }));
});

// ── ページ数バリデーション ───────────────────────────────
const pageCountError = ref('');
function validatePageCount() {
    const val = form.page_count;
    if (val === '' || val === null || val === undefined) {
        pageCountError.value = '';
        return;
    }
    const n = Number(val);
    if (!Number.isInteger(n) || n < 1 || n > 99999) {
        pageCountError.value = '1〜99999の整数を入力してください';
    } else {
        pageCountError.value = '';
    }
}

const jobcodeError = ref('');
function validateJobcode(e) {
    const val = e.target.value;
    if (/^[0-9\-]*$/.test(val)) {
        jobcodeError.value = '';
    } else {
        jobcodeError.value = '数字とハイフンのみ入力できます';
    }
}

// クライアント検索用
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

// チームメンバー選択モーダル用
const showMemberModal = ref(false);
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');
const selectedMemberIds = ref([]);

// clientSearchModeが'list'になったら自動で一覧モーダルを開く
watch(clientSearchMode, (val) => {
    if (val === 'list') {
        openClientListModal();
    }
});

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
const showPresetBanner = ref(false);
const lastJobConfig = ref(null);

async function selectClient(client) {
    form.client_id = client.id;
    form.client_name = client.name;
    closeClientModal();
    closeClientListModal();

    // 共通のプリセット取得関数を使用
    await loadClientPreset(client);
}

function applyPreset() {
    const c = lastJobConfig.value;
    if (!c) return;
    
    // 案件タイトルに「ーコピー」を追加
    if (c.job_title) form.title = c.job_title + 'ーコピー';
    
    // 伝票番号は空欄（ユニーク性のため）
    form.jobcode = '';
    
    // その他は全部引き継ぐ
    if (c.user_id)             form.user_id = c.user_id;
    if (c.sub_coordinator_ids) form.sub_coordinator_ids = [...c.sub_coordinator_ids];
    if (c.size_id)             form.size_id = c.size_id;
    if (c.page_count)          form.page_count = c.page_count;
    if (c.detail)              form.detail = c.detail;
    
    // チームメンバーも引き継ぐ
    if (c.team_members && Array.isArray(c.team_members)) {
        form.team_members = [...c.team_members];
    }
    
    // プリセット適用後に、リーダー・サブリーダーがチームメンバーに含まれているか確認し、追加
    if (form.user_id) {
        addUserToTeamMembers(form.user_id);
    }
    if (form.sub_coordinator_ids && Array.isArray(form.sub_coordinator_ids)) {
        form.sub_coordinator_ids.forEach(userId => {
            addUserToTeamMembers(userId);
        });
    }
    
    showPresetBanner.value = false;
}

// ID入力時の名前自動取得
async function onClientIdChange() {
    const clientId = form.client_id;
    if (!clientId || clientId === '') {
        form.client_name = '';
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
                form.client_name = client.name;
                // プリセット取得も実行
                await loadClientPreset(client);
            } else {
                form.client_name = '';
            }
        } else {
            form.client_name = '';
        }
    } catch (error) {
        console.error('クライアント取得エラー:', error);
        form.client_name = '';
    } finally {
        isLoadingClientById.value = false;
    }
}

// 名前入力時のオートコンプリート
function onClientNameInput() {
    const searchTerm = form.client_name;
    
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
    form.client_id = client.id;
    form.client_name = client.name;
    showNameSuggestions.value = false;
    selectedSuggestionIndex.value = -1;
    
    // プリセット取得
    await loadClientPreset(client);
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

// フォーカスアウト時に候補を非表示
function onClientNameBlur() {
    // 少し遅延させて、候補クリック時間を確保
    setTimeout(() => {
        showNameSuggestions.value = false;
        selectedSuggestionIndex.value = -1;
    }, 200);
}

// エラー項目の日本語ラベル
const errorLabels = {
    jobcode: '伝票番号',
    title: '案件タイトル',
    user_id: 'リーダー',
    sub_coordinator_ids: 'サブCoordinator',
    client_id: 'クライアントID',
    client_name: 'クライアント名',
    size_id: 'サイズ',
    page_count: '総ページ数',
    detail: '詳細',
};

// ===== 案件名重複チェック =====
const showDuplicateModal = ref(false);
const duplicateJobs = ref([]);
const isCheckingDuplicate = ref(false);

function closeDuplicateModal() {
    showDuplicateModal.value = false;
}

// ===== リーダー・サブリーダー自動追加機能 =====
// チームメンバーに自動追加するヘルパー関数
function addUserToTeamMembers(userId) {
    if (!userId) return;
    
    // 既に存在するかチェック
    const exists = form.team_members.some(member => member.user_id === userId);
    if (exists) return;
    
    // ユーザー情報を取得して追加
    const user = props.members.find(m => m.id === userId);
    if (user) {
        form.team_members.push({
            user_id: user.id,
            user_name: user.name
        });
    }
}

function removeUserFromTeamMembers(userId) {
    if (!userId) return;
    const index = form.team_members.findIndex(member => member.user_id === userId);
    if (index > -1) {
        form.team_members.splice(index, 1);
    }
}

// リーダー変更を監視
watch(() => form.user_id, (newUserId, oldUserId) => {
    // 古いリーダーをチームメンバーから削除（サブリーダーでない場合）
    if (oldUserId && !form.sub_coordinator_ids.includes(oldUserId)) {
        removeUserFromTeamMembers(oldUserId);
    }
    
    // 新しいリーダーをチームメンバーに追加
    if (newUserId) {
        addUserToTeamMembers(newUserId);
    }
});

// サブリーダー変更を監視
watch(() => form.sub_coordinator_ids, (newSubIds, oldSubIds) => {
    const oldIds = oldSubIds || [];
    const newIds = newSubIds || [];
    
    // 削除されたサブリーダーをチームメンバーから削除（リーダーでない場合）
    oldIds.forEach(userId => {
        if (!newIds.includes(userId) && userId !== form.user_id) {
            removeUserFromTeamMembers(userId);
        }
    });
    
    // 追加されたサブリーダーをチームメンバーに追加
    newIds.forEach(userId => {
        addUserToTeamMembers(userId);
    });
}, { deep: true });

// ===== チームメンバー選択機能 =====
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
    if (form.user_id) {
        initialIds.push(form.user_id);
    }
    
    // サブリーダー（sub_coordinator_ids）を追加
    if (Array.isArray(form.sub_coordinator_ids)) {
        initialIds.push(...form.sub_coordinator_ids);
    }
    
    // 既存のチームメンバーも選択状態にする
    form.team_members.forEach(member => {
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
    form.team_members = [];
    selectedMemberIds.value.forEach(memberId => {
        const member = props.members.find(m => m.id === memberId);
        if (member) {
            form.team_members.push({
                user_id: member.id,
                user_name: member.name
            });
        }
    });
    closeMemberModal();
}

function removeMember(index) {
    form.team_members.splice(index, 1);
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

async function submit() {
    // jobcode は未入力を許可。入力がある場合は数字とハイフンのみ許可する。
    if (form.jobcode && !/^[0-9\-]+$/.test(form.jobcode)) {
        jobcodeError.value = '数字とハイフンのみ入力できます';
        alert('伝票番号は数字とハイフンのみ入力できます');
        return;
    }

    // ページ数バリデーション
    validatePageCount();
    if (pageCountError.value) {
        alert(pageCountError.value);
        return;
    }

    // クライアントと案件タイトルが揃っている場合のみ重複チェック
    if (form.client_id && form.title) {
        isCheckingDuplicate.value = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(route('coordinator.project_jobs.check_duplicate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ title: form.title, client_id: form.client_id }),
            });
            if (res.ok) {
                const data = await res.json();
                if (data.duplicates && data.duplicates.length > 0) {
                    duplicateJobs.value = data.duplicates;
                    showDuplicateModal.value = true;
                    isCheckingDuplicate.value = false;
                    return; // ユーザーの選択待ち
                }
            }
        } catch {
            // チェック失敗時はそのまま保存続行
        } finally {
            isCheckingDuplicate.value = false;
        }
    }

    doSubmit();
}

function doSubmit() {
    // teammember/scheduleはnullで送信
    form.teammember = null;
    form.schedule = null;
    // submit to server; server redirects to index and Index.vue handles follow-up prompts
    form.post(route('coordinator.project_jobs.store'), {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => {
            // 重大なバリデーションエラー時はalertも出す
            if (errors && Object.keys(errors).length > 0) {
                let msg = '入力内容に誤りがあります。\n';
                for (const key in errors) {
                    const label = errorLabels[key] || key;
                    msg += `・${label}: ${errors[key]}\n`;
                }
                alert(msg);
            }
        },
    });
}

function forceSubmit() {
    closeDuplicateModal();
    doSubmit();
}

// If this page was opened with ?project_job_id=123, show a quick link to the calendar PoC
const projectJobId = (() => {
    try {
        const params = new URLSearchParams(window.location.search);
        return params.get('project_job_id');
    } catch (e) {
        return null;
    }
})();

function goSchedule() {
    if (!projectJobId) return;
    router.visit(route('coordinator.project_jobs.schedule', { projectJob: projectJobId }));
}

// スケジュール・メンバー登録は後続ステップで実装
</script>

<style scoped>
/* 必要に応じてスタイル追加 */
</style>
