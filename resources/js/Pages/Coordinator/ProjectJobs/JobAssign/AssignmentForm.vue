<template>
    <form @submit.prevent="save">
        <div v-for="(block, idx) in assignments" :key="idx" class="mb-4 rounded border p-4">
            <!-- クライアント -->
            <label class="mb-1 block font-semibold">クライアント</label>
            <div v-if="props.mode === 'coordinator'" class="w-full rounded border bg-gray-50 px-3 py-2">
                {{ clientName(block) }}
            </div>
            <div v-else class="w-full">
                <div v-if="block._locked_client" class="flex items-center rounded border bg-gray-100 px-3 py-2 text-sm text-gray-600">
                    <span>{{ userClients.find(c => String(c.id) === String(block._client_id))?.name ?? block._client_id }}</span>
                    <span class="ml-auto text-xs text-gray-400">🔒 進行表から</span>
                </div>
                <select v-else v-model="block._client_id" :disabled="!editMode" class="w-full rounded border px-3 py-2" @change="onClientChange(idx)">
                    <option value="">-- 選択 --</option>
                    <option v-for="c in userClients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>
            </div>

            <!-- プロジェクト名 -->
            <label class="mb-1 mt-2 block font-semibold">プロジェクト名</label>
            <div v-if="props.mode === 'coordinator'" class="w-full rounded border bg-gray-50 px-3 py-2">
                {{ projectName(block) }}
            </div>
            <div v-else>
                <div v-if="block._locked_project" class="flex items-center rounded border bg-gray-100 px-3 py-2 text-sm text-gray-600">
                    <span>{{ (props.userProjects || []).find(p => String(p.id) === String(block.project_job_id))?.title ?? block.project_job_id }}</span>
                    <span class="ml-auto text-xs text-gray-400">🔒 進行表から</span>
                </div>
                <select v-else v-model="block.project_job_id" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                    <option value="">-- 選択 --</option>
                    <option v-for="p in projectsForBlock(block)" :key="p.id" :value="p.id">{{ p.title || p.name }}</option>
                </select>
            </div>

            <label class="mb-1 mt-2 block font-semibold">ジョブ名</label>
            <div>
                <input v-model="block.title_suffix" :disabled="!editMode" type="text" 
                       :class="['w-full rounded border px-3 py-2', 
                               getFieldError('assignments.0.title') || getFieldError('title') ? 'border-red-500 bg-red-50' : '']" />
                <div v-if="getFieldError('assignments.0.title') || getFieldError('title')" 
                     class="mt-1 text-sm text-red-600">
                    {{ getFieldError('assignments.0.title') || getFieldError('title') }}
                </div>
            </div>

            <label class="mb-1 mt-2 block font-semibold">概要</label>
            <div>
                <textarea v-model="block.detail" :disabled="!editMode" 
                         :class="['w-full rounded border px-3 py-2',
                                 getFieldError('assignments.0.detail') || getFieldError('detail') ? 'border-red-500 bg-red-50' : '']"
                         rows="3"></textarea>
                <div v-if="getFieldError('assignments.0.detail') || getFieldError('detail')" 
                     class="mt-1 text-sm text-red-600">
                    {{ getFieldError('assignments.0.detail') || getFieldError('detail') }}
                </div>
            </div>

            <!-- 作業ファイル情報 -->
            <template v-if="props.mode === 'coordinator' || props.mode === 'user'">
                <div class="mt-3 rounded border border-blue-200 bg-blue-50 p-3">
                    <h3 class="mb-2 text-sm font-semibold text-blue-800">作業ファイル情報</h3>

                    <!-- 既存 file_info の表示（閲覧時） -->
                    <div v-if="!editMode && block.file_info" class="text-sm text-gray-700">
                        合計: {{ block.file_info.total_files }}ファイル
                        <template v-if="block.file_info.total_pages"> / {{ block.file_info.total_pages }}ページ</template>
                        <span class="ml-2 text-xs text-gray-400">（詳細はショー画面で確認できます）</span>
                    </div>
                    <div v-else-if="!editMode && !block.file_info" class="text-xs text-gray-400">ファイル情報なし</div>

                    <!-- 編集時のファイルアップロードUI -->
                    <template v-if="editMode">
                        <!-- 既存データがある場合の案内 -->
                        <div v-if="block.file_info && fa(idx).results.value.length === 0" class="mb-2 rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                            現在: {{ block.file_info.total_files }}ファイル
                            <template v-if="block.file_info.total_pages"> / {{ block.file_info.total_pages }}ページ</template>
                            — 再アップロードすると更新されます
                        </div>

                        <!-- 対応形式 -->
                        <details class="mb-2 text-xs text-gray-500">
                            <summary class="cursor-pointer hover:text-gray-700">対応ファイル形式</summary>
                            <div class="mt-1 rounded border bg-white p-2">
                                <div class="mb-1 font-semibold text-green-700">自動取得できる形式</div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-0.5">
                                    <div v-for="s in SUPPORTED_TYPES" :key="s.ext">
                                        <span class="font-mono font-semibold">{{ s.ext }}</span>
                                        <span class="text-gray-500"> — {{ s.info }}</span>
                                    </div>
                                </div>
                                <div class="mt-1 border-t pt-1">
                                    <div class="mb-0.5 font-semibold text-red-600">自動除外される形式</div>
                                    <div v-for="u in UNSUPPORTED_NOTICE" :key="u.ext" class="text-red-600">
                                        <span class="font-mono font-semibold">{{ u.ext }}</span>
                                        <span class="text-gray-500"> — {{ u.reason }}</span>
                                    </div>
                                </div>
                            </div>
                        </details>

                        <!-- ドロップゾーン -->
                        <div
                            class="flex min-h-[80px] cursor-pointer flex-col items-center justify-center rounded border-2 border-dashed border-blue-300 bg-white px-4 py-4 text-center transition hover:border-blue-500 hover:bg-blue-50"
                            @dragover.prevent
                            @drop.prevent="onFileDrop($event, idx)"
                            @click="triggerFileInput(idx)"
                        >
                            <p class="mb-2 text-xs text-gray-600">ここにファイル・フォルダをドラッグ＆ドロップ</p>
                            <div class="flex gap-2">
                                <button type="button" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100" @click.stop="triggerFolderInput(idx)">
                                    フォルダを選択
                                </button>
                                <button type="button" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100" @click.stop="triggerFileInput(idx)">
                                    ファイルを選択
                                </button>
                            </div>
                        </div>
                        <input :ref="el => { if(el) fileInputRefs[idx] = el }" type="file" multiple class="hidden" @change="onFileInputChange($event, idx)" />
                        <input :ref="el => { if(el) folderInputRefs[idx] = el }" type="file" multiple webkitdirectory class="hidden" @change="onFileInputChange($event, idx)" />

                        <!-- 対応外ファイル -->
                        <div v-if="fa(idx).rejectedFiles.value.length > 0" class="mt-2 rounded border border-red-200 bg-red-50 p-2">
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-xs font-semibold text-red-700">除外ファイル（{{ fa(idx).rejectedFiles.value.length }}件）</span>
                                <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="fa(idx).clearRejected()">閉じる</button>
                            </div>
                            <ul class="max-h-24 overflow-y-auto text-xs text-red-700">
                                <li v-for="(f, fi) in fa(idx).rejectedFiles.value" :key="fi" class="border-b border-red-100 py-0.5 last:border-0">
                                    <span class="font-medium">{{ f.name }}</span> — {{ f.reason }}
                                </li>
                            </ul>
                        </div>

                        <!-- 解析中 -->
                        <div v-if="fa(idx).analyzing.value" class="mt-2 flex items-center gap-2 text-xs text-blue-600">
                            <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            解析中...
                        </div>

                        <!-- ファイル一覧 -->
                        <template v-if="fa(idx).results.value.length > 0">
                            <div class="mt-2 flex justify-end">
                                <button type="button" class="text-xs text-red-500 hover:underline" @click="fa(idx).clearFiles(); block.file_info = null;">すべて削除</button>
                            </div>
                            <div v-for="group in fa(idx).grouped.value" :key="group.type" class="mt-2">
                                <div class="mb-1 text-xs font-semibold text-gray-700">
                                    {{ group.label }}（{{ group.files.length }}ファイル
                                    <template v-if="group.totalPages"> / {{ group.totalPages }}ページ</template>）
                                </div>
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="bg-gray-100 text-left">
                                            <th class="px-2 py-1">ファイル名</th>
                                            <template v-if="group.columns === 'page'">
                                                <th class="px-2 py-1">ページ数</th>
                                                <th class="px-2 py-1">用紙サイズ</th>
                                                <th class="px-2 py-1 text-right">容量</th>
                                            </template>
                                            <template v-else-if="group.columns === 'image'">
                                                <th class="px-2 py-1">幅×高さ</th>
                                                <th class="px-2 py-1">カラー</th>
                                                <th class="px-2 py-1 text-right">容量</th>
                                            </template>
                                            <template v-else-if="group.columns === 'size'">
                                                <th class="px-2 py-1">容量</th>
                                            </template>
                                            <th class="px-2 py-1 text-right">削除</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(file, fi) in group.files" :key="fi" class="border-b border-gray-100">
                                            <td class="px-2 py-1">
                                                <span v-if="file.analyzing" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-blue-400 border-t-transparent"></span>
                                                <span v-else>{{ file.name }}</span>
                                            </td>
                                            <template v-if="group.columns === 'page'">
                                                <td class="px-2 py-1">{{ file.pages != null ? file.pages + 'p' : '-' }}</td>
                                                <td class="px-2 py-1">{{ file.doc_size ?? '-' }}</td>
                                                <td class="px-2 py-1 text-right text-gray-500">{{ fa(idx).formatSize(file.size) }}</td>
                                            </template>
                                            <template v-else-if="group.columns === 'image'">
                                                <td class="px-2 py-1">{{ file.width && file.height ? `${file.width}×${file.height}` : '-' }}</td>
                                                <td class="px-2 py-1">{{ file.extra ?? '-' }}</td>
                                                <td class="px-2 py-1 text-right text-gray-500">{{ fa(idx).formatSize(file.size) }}</td>
                                            </template>
                                            <template v-else-if="group.columns === 'size'">
                                                <td class="px-2 py-1">{{ fa(idx).formatSize(file.size) }}</td>
                                            </template>
                                            <td class="px-2 py-1 text-right">
                                                <button type="button" class="text-red-400 hover:text-red-600" @click="fa(idx).removeByGroupIndex(group.type, fi)">✕</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- 合計 -->
                            <div class="mt-2 rounded border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-900">
                                合計: {{ fa(idx).summary.value.totalFiles }}ファイル
                                <template v-if="fa(idx).summary.value.totalPages"> / {{ fa(idx).summary.value.totalPages }}ページ</template>
                                / {{ fa(idx).summary.value.totalSizeLabel }}
                            </div>
                        </template>
                    </template>
                </div>
            </template>

            <!-- 割当ユーザー（概要直下に移動） -->
            <label class="mb-1 mt-3 block font-semibold">割当ユーザー</label>
            <div v-if="!editMode" class="mt-1 flex items-center gap-2 rounded border bg-gray-50 px-3 py-2 text-sm">
                <span>{{ memberName(block.user_id) }}</span>
                <span
                    v-if="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type]"
                    class="rounded-full px-2 py-0 text-xs font-medium"
                    :class="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].cls"
                >
                    {{ EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].label }}
                </span>
            </div>
            <div v-else-if="block._locked_user" class="mt-1 flex items-center gap-2 rounded border bg-gray-100 px-3 py-2 text-sm text-gray-600">
                <span>{{ memberName(block.user_id) }}</span>
                <span class="ml-2 text-xs text-gray-400">🔒 進行表から</span>
                <span
                    v-if="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type]"
                    class="rounded-full px-2 py-0 text-xs font-medium"
                    :class="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].cls"
                >
                    {{ EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].label }}
                </span>
            </div>
            <div v-else>
                <select v-model="block.user_id" class="w-full rounded border px-3 py-2" @change="onUserChange(block)">
                    <option value="">未指定</option>
                    <option v-for="m in props.members || members" :key="m.id" :value="m.id">
                        {{ m.name }}{{ m.assignment_name ? '（' + m.assignment_name + '）' : '' }}
                        {{ m.employment_type === 'proof_dispatcher' ? '【単発派遣】' : ['dispatch','outsource','contract'].includes(m.employment_type) ? '【' + m.employment_type_label + '】' : '' }}
                    </option>
                </select>
                <!-- 選択後の雇用形態バッジ（派遣・業務委託のみ表示） -->
                <div
                    v-if="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type]"
                    class="mt-1.5 flex items-center gap-1.5 rounded border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs"
                >
                    <span
                        class="rounded-full px-2 py-0.5 font-medium"
                        :class="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].cls"
                    >
                        {{ EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].label }}
                    </span>
                    <span class="text-orange-700">
                        このユーザーは{{ EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].label }}です。
                    </span>
                </div>
            </div>

            <!-- 作業詳細ヘッダー＋フィルター -->
            <div class="mb-1 mt-4 flex flex-wrap items-center gap-2">
                <span class="font-semibold">作業詳細</span>
                <template v-if="editMode">
                    <select v-model="block._type_filter" class="rounded border px-2 py-1 text-xs text-gray-700">
                        <option value="">業種：全部表示</option>
                        <option value="dtp">組版・DTP</option>
                        <option value="design">デザイン・制作</option>
                        <option value="proof">校正・確認</option>
                        <option value="mgmt">進行管理・事務</option>
                        <option value="sales">営業</option>
                        <option value="common">共通</option>
                    </select>
                    <select v-model="block._medium_filter" class="rounded border px-2 py-1 text-xs text-gray-700">
                        <option value="paper">紙媒体</option>
                        <option value="digital">デジタル</option>
                        <option value="">媒体：全表示</option>
                    </select>
                </template>
            </div>
            <div>
                <input type="hidden" v-model="block.company_id" />
                <input type="hidden" v-model="block.department_id" />
            </div>

            <!-- 作業種別（Type）＋ ステージ（Stage）を1行に -->
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">作業種別</label>
                    <div v-if="!editMode || block._locked_work_item_type" class="mt-1 w-full rounded border bg-gray-100 px-3 py-2 text-sm text-gray-500">
                        {{ itemName('types', block.work_item_type_id) }}
                        <span v-if="block._locked_work_item_type" class="ml-2 text-xs text-gray-400">🔒 進行表から</span>
                    </div>
                    <select
                        v-else
                        v-model="block.work_item_type_id"
                        :disabled="props.mode === 'coordinator' ? (!hasDepartment(block) || !editMode) : !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <template v-for="grp in typesGrouped(block.company_id, block.department_id, block._type_filter || '')" :key="grp.group">
                            <optgroup :label="grp.label">
                                <option v-for="t in grp.items" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                            </optgroup>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">ステージ（校数）</label>
                    <div v-if="!editMode || block._locked_stage" class="mt-1 w-full rounded border bg-gray-100 px-3 py-2 text-sm text-gray-500">
                        {{ itemName('stages', block.stage_id) }}
                        <span v-if="block._locked_stage" class="ml-2 text-xs text-gray-400">🔒 進行表から</span>
                    </div>
                    <select
                        v-else
                        v-model="block.stage_id"
                        :disabled="props.mode === 'coordinator' ? (!hasDepartment(block) || !editMode) : !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="st in stagesForSelect(block.company_id, block.department_id)" :key="st.id" :value="String(st.id)">
                            {{ st.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- サイズ（Size）＋ ステータス（user モードのみ）を1行に -->
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">サイズ</label>
                    <div v-if="!editMode || projectJobSizeId || block._locked_size" class="mt-1 w-full rounded border bg-gray-100 px-3 py-2 text-sm text-gray-500">
                        {{ itemName('sizes', block.size_id) || '(未設定)' }}
                        <span v-if="projectJobSizeId" class="ml-2 text-xs text-gray-400">🔒 案件で固定</span>
                        <span v-else-if="block._locked_size" class="ml-2 text-xs text-gray-400">🔒 進行表から</span>
                    </div>
                    <select
                        v-else
                        v-model="block.size_id"
                        :disabled="props.mode === 'coordinator' ? (!hasDepartment(block) || !editMode) : !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <template v-for="grp in sizesGrouped(block.company_id, block.department_id, block._medium_filter ?? 'paper')" :key="grp.group">
                            <optgroup :label="grp.label">
                                <option v-for="s in grp.items" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </optgroup>
                        </template>
                    </select>
                </div>
                <!-- Status: coordinator では非表示、user では表示 -->
                <div v-if="props.mode === 'user'">
                    <label class="block text-xs font-medium text-gray-600">
                        ステータス
                        <span v-if="!block.id && props.defaultStatusId" class="ml-1 font-normal text-gray-400">（新規固定）</span>
                    </label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ itemName('statuses', block.status_id) }}
                    </div>
                    <select
                        v-else
                        v-model="block.status_id"
                        :disabled="!editMode || (!block.id && props.defaultStatusId !== null && props.defaultStatusId !== undefined)"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="st in statusesForSelect(block.company_id, block.department_id)" :key="st.id" :value="String(st.id)">
                            {{ st.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- 数量（ページ数を数値入力に変更） -->
            <div class="mt-3 flex items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">数量</label>
                    <div v-if="!editMode" class="mt-1 rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ block.amounts != null ? block.amounts : '-' }}
                        {{ block.amounts_unit === 'page' ? 'ページ' : block.amounts_unit === 'file' ? 'ファイル' : '' }}
                    </div>
                    <input
                        v-else
                        type="number"
                        v-model.number="block.amounts"
                        min="0"
                        max="9999"
                        step="1"
                        :disabled="!editMode"
                        class="mt-1 w-24 rounded border px-3 py-1.5 text-sm"
                        @change="onInlineSelectionChange(idx)"
                    />
                </div>
                <div v-if="editMode">
                    <label class="block text-xs font-medium text-gray-600">単位</label>
                    <select v-model="block.amounts_unit" :disabled="!editMode" class="mt-1 w-28 rounded border px-2 py-1.5 text-sm">
                        <option value="page">ページ</option>
                        <option value="file">ファイル</option>
                    </select>
                </div>
                <div v-else class="pb-2 text-sm text-gray-500">
                    {{ block.amounts_unit === 'page' ? 'ページ' : block.amounts_unit === 'file' ? 'ファイル' : '' }}
                </div>
            </div>

            <label class="mb-1 mt-4 block font-semibold">難易度</label>
            <select v-model="block.difficulty_id" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                <option :value="null">-- 選択 --</option>
                <option v-for="d in ($page.props.difficulties || [])" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>

            <!-- 締め切り: coordinator = 常に編集可, user = 既存レコードのみ読み取り表示 -->
            <div class="mt-2" v-if="props.mode === 'coordinator'">
                <div class="mt-2">
                    <label class="mb-1 block font-semibold">締め切り</label>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="block.desired_end_date"
                            :min="minEndDate(idx)"
                            type="date"
                            :class="['rounded border px-3 py-2',
                                    getFieldError('assignments.0.desired_end_date') || getFieldError('desired_end_date') ? 'border-red-500 bg-red-50' : '']"
                            @change="onEndDateChange(idx)"
                            :disabled="!editMode"
                        />
                        <select
                            v-model="block.desired_time_hour"
                            :disabled="!editMode"
                            :class="['w-20 rounded border px-3 py-2',
                                    getFieldError('assignments.0.desired_time') || getFieldError('desired_time') ? 'border-red-500 bg-red-50' : '']"
                            @change="onHourChange(idx)"
                        >
                            <option v-for="h in availableHours(idx)" :key="h" :value="h">{{ h }}</option>
                        </select>
                        <select v-model="block.desired_time_min" :disabled="!editMode" 
                               :class="['w-20 rounded border px-3 py-2',
                                       getFieldError('assignments.0.desired_time') || getFieldError('desired_time') ? 'border-red-500 bg-red-50' : '']">
                            <option v-for="m in availableMins(idx, block.desired_time_hour)" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </div>
                    <div v-if="getFieldError('assignments.0.desired_end_date') || getFieldError('desired_end_date')" 
                         class="mt-1 text-sm text-red-600">
                        {{ getFieldError('assignments.0.desired_end_date') || getFieldError('desired_end_date') }}
                    </div>
                    <div v-if="getFieldError('assignments.0.desired_time') || getFieldError('desired_time')" 
                         class="mt-1 text-sm text-red-600">
                        {{ getFieldError('assignments.0.desired_time') || getFieldError('desired_time') }}
                    </div>
                </div>
            </div>
            <div
                v-else-if="block.id && (block.desired_start_date || block.desired_end_date || block.desired_time_hour || block.desired_time_min)"
                class="mt-2 flex gap-4"
            >
                <div class="flex-1">
                    <label class="mb-1 block font-semibold">締め切り</label>
                    <div v-if="block.desired_end_date || block.desired_time_hour" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ formatEnd(block) }}
                    </div>
                </div>
            </div>

            <!-- 見積時間: coordinator = 常に編集可, user = 既存レコードのみ読み取り表示 -->
            <label class="mb-1 mt-2 block font-semibold">見積時間</label>
            <div class="flex items-center gap-2">
                <template v-if="props.mode === 'coordinator'">
                    <select v-model="block.estimated_hours" :disabled="!editMode" class="w-40 rounded border px-3 py-2">
                        <option value="">未指定</option>
                        <option v-for="opt in estimatedOptions" :key="opt" :value="opt">{{ String(opt).replace('.0', '') }}h</option>
                    </select>
                    <span class="text-sm text-gray-500">(0.25刻み、例: 1.5 = 1時間30分)</span>
                </template>
                <template v-else>
                    <div v-if="block.id && block.estimated_hours" class="mt-1 w-40 rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ formatEstimated(block) }}
                    </div>
                </template>
            </div>

            <div v-if="props.mode === 'coordinator'" class="mt-2 text-right">
                <template v-if="block.linked_assignment_id">
                    <a
                        :href="
                            route('coordinator.project_jobs.assignments.show', {
                                projectJob: block.project_job && block.project_job.id ? block.project_job.id : projectJob ? projectJob.id : '',
                                assignment: block.linked_assignment_id,
                            })
                        "
                        class="ml-3 text-sm text-blue-600"
                        >割当を見る (#{{ block.linked_assignment_id }})</a
                    >
                </template>
            </div>
        </div>

        <!-- インラインイベント日時エディタ (user モードのみ) -->
        <div v-if="props.mode === 'user' && editMode" class="mb-4 rounded border p-4">
            <label class="block text-sm font-medium text-gray-700">作業日</label>
            <div class="mt-1 flex items-center gap-2">
                <input type="date" v-model="workDate" class="rounded border px-3 py-2" />
            </div>
            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700">時間</label>
                <div class="mt-1 flex items-end gap-4">
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600">開始</label>
                        <div class="flex items-center gap-2">
                            <select v-model="startTimeHour" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <select v-model="startTimeMin" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="m in minsOptions(startTimeMin)" :key="m" :value="m">{{ m }}</option>
                            </select>
                            <button type="button" @click="setCurrentTime('start')" :disabled="!editMode" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 disabled:opacity-50">現在時刻</button>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600">終了</label>
                        <div class="flex items-center gap-2">
                            <select v-model="endTimeHour" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <select v-model="endTimeMin" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="m in minsOptions(endTimeMin)" :key="m" :value="m">{{ m }}</option>
                            </select>
                            <button type="button" @click="setCurrentTime('end')" :disabled="!editMode" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 disabled:opacity-50">現在時刻</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 作業日・時間スロット -->
        <div v-if="props.showWorkSlots" class="mt-6 rounded border border-pink-100 bg-pink-50 p-4">
            <div class="mb-2">
                <h4 class="text-sm font-semibold text-pink-700">作業日・時間</h4>
            </div>
            <div class="mb-4 flex items-center gap-x-5">
                <button
                    type="button"
                    @click="$emit('open-calendar')"
                    class="rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                >
                    カレンダーで作業時間を選択
                </button>
                <button
                    type="button"
                    @click="addWorkSlot"
                    class="rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                >
                    ＋ 追加
                </button>
            </div>
            <div v-if="workSlots.length === 0" class="text-xs text-gray-400">
                「＋ 追加」で作業日・時間を登録できます。
            </div>
            <div class="space-y-2">
                <div
                    v-for="(slot, idx) in workSlots"
                    :key="idx"
                    class="flex flex-wrap items-end gap-3 rounded border border-pink-200 bg-white p-3"
                >
                    <div>
                        <label class="block text-xs text-gray-500">日付</label>
                        <input
                            v-model="slot.date"
                            type="date"
                            class="mt-1 rounded border-gray-300 text-sm shadow-sm focus:border-pink-500 focus:ring-pink-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">開始</label>
                        <div class="mt-1 flex items-center gap-1">
                            <select v-model="slot.startHour" class="rounded border-gray-300 text-sm">
                                <option v-for="h in SLOT_HOURS" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <span class="text-gray-400">:</span>
                            <select v-model="slot.startMinute" class="rounded border-gray-300 text-sm">
                                <option v-for="m in SLOT_MINUTES" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">終了</label>
                        <div class="mt-1 flex items-center gap-1">
                            <select v-model="slot.endHour" class="rounded border-gray-300 text-sm">
                                <option v-for="h in SLOT_HOURS" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <span class="text-gray-400">:</span>
                            <select v-model="slot.endMinute" class="rounded border-gray-300 text-sm">
                                <option v-for="m in SLOT_MINUTES" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500">{{ formatSlotDuration(slot) }}</div>
                    <button
                        type="button"
                        @click="removeWorkSlot(idx)"
                        class="ml-auto rounded bg-red-50 px-2 py-1 text-xs text-red-500 hover:bg-red-100"
                    >
                        削除
                    </button>
                </div>
            </div>
        </div>

        <div class="flex gap-2" v-if="editMode">
            <template v-if="props.mode === 'coordinator'">
                <!-- 新規作成のみ「ブロック追加」を表示 -->
                <button v-if="!isEditMode" type="button" class="rounded bg-blue-600 px-4 py-2 text-white" @click="addBlock">ジョブブロックを追加</button>
                <!-- saveOnly モード: 保存ボタンのみ（送信なし） -->
                <template v-if="props.saveOnly">
                    <button type="button" class="rounded bg-pink-600 px-4 py-2 text-white" :disabled="saving" @click.prevent="save(false)">保存</button>
                </template>
                <!-- 通常モード: 新規: 保存して送信 / 編集: 保存して再送信 + 送信せず保存 -->
                <template v-else-if="isEditMode">
                    <button type="button" class="rounded bg-green-600 px-4 py-2 text-white" :disabled="saving" @click.prevent="save(true)">保存して再送信</button>
                    <button type="button" class="rounded bg-gray-600 px-4 py-2 text-white" :disabled="saving" @click.prevent="save(false)">送信せず保存</button>
                </template>
                <template v-else>
                    <button type="submit" class="rounded bg-green-600 px-4 py-2 text-white" :disabled="saving">保存して送信</button>
                </template>
                <Link
                    :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob ? projectJob.id : '' })"
                    class="rounded bg-gray-200 px-4 py-2"
                    >戻る</Link
                >
            </template>
            <template v-else>
                <button type="submit" @click.prevent="save" :disabled="saving" class="rounded bg-green-600 px-4 py-2 text-white">保存する</button>
            </template>
        </div>
    </form>

    <SelectionModal
        v-if="props.mode === 'coordinator' && showSelector"
        :show="showSelector"
        :companies="$page.props.companies || []"
        :types="$page.props.types || []"
        :sizes="$page.props.sizes || []"
        :stages="$page.props.stages || []"
        :statuses="$page.props.statuses || []"
        :user-role="$page.props.auth.user.user_role || null"
        :current-company-id="$page.props.company ? $page.props.company.id : $page.props.auth.user.company_id || ''"
        :current-department-id="$page.props.department ? $page.props.department.id : $page.props.auth.user.department_id || ''"
        @close="showSelector = false"
        @selected="onSelected"
    />

    <!-- Event overlap warning modal -->
    <div v-if="showOverlapModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="mx-4 w-full max-w-md rounded-lg bg-white shadow-xl">
            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-red-700">予定が重複しています</h2>
            </div>
            <div class="p-6">
                <p class="mb-4 text-sm text-gray-700">
                    選択した日時に以下の予定が重複しています。差し込み作業として記録されます。
                </p>
                <div class="mb-4 max-h-40 overflow-y-auto rounded border border-red-200 bg-red-50">
                    <div v-for="(e, idx) in overlappingEvents" :key="idx" class="border-b px-3 py-2 text-sm last:border-b-0">
                        <div class="font-medium text-gray-800">{{ e.title || e.name || '(無題)' }}</div>
                        <div class="text-xs text-gray-600">
                            {{ new Date(e.start).toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) }} -
                            {{ new Date(e.end).toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <button
                    @click="closeOverlapModal"
                    class="rounded bg-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-300"
                >
                    キャンセル
                </button>
                <button
                    @click="proceedAnyway"
                    class="rounded bg-orange-600 px-4 py-2 text-white hover:bg-orange-700"
                >
                    それでも作成する
                </button>
            </div>
        </div>
    </div>

    <!-- Toast container -->
    <div class="fixed bottom-4 right-4 z-50 space-y-2">
        <div v-for="t in toasts" :key="t.id" :class="toastClass(t.type)" class="max-w-sm">
            <div class="flex items-center justify-between">
                <div>{{ t.message }}</div>
                <button @click="dismissToast(t.id)" class="ml-3 text-xs text-white">✕</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import SelectionModal from '@/Components/SelectionModal.vue';
import useToasts from '@/Composables/useToasts';
import { useFileAnalyzer, SUPPORTED_TYPES, UNSUPPORTED_NOTICE } from '@/composables/useFileAnalyzer';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, inject, onMounted, ref, watch } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'coordinator' },
    projectJob: Object,
    members: Array,
    assignments: Array,
    editMode: { type: Boolean, default: false },
    defaultUserId: { type: [Number, String], default: null },
    hideStatus: { type: Boolean, default: false },
    defaultStatusId: { type: [Number, String], default: null },
    currentCompanyId: { type: [Number, String], default: null },
    currentDepartmentId: { type: [Number, String], default: null },
    userClients: { type: Array, default: () => [] },
    userProjects: { type: Array, default: () => [] },
    otherClientId: { type: [Number, String], default: null },
    otherProjectId: { type: [Number, String], default: null },
    event: { type: Object, default: null },
    // 校正コーディネーターなど、coordinatorモードで別ルートへ投稿したいときに指定
    storeOverrideUrl: { type: String, default: null },
    // coordinatorモードで編集時に別ルート（PUT）へ送りたいときに指定
    updateOverrideUrl: { type: String, default: null },
    // 送信概念なしで「保存」ボタンのみ表示（校正管理者など）
    saveOnly: { type: Boolean, default: false },
    // 作業日・時間スロット表示
    showWorkSlots:    { type: Boolean, default: false },
    initialWorkSlots: { type: Array,   default: () => [] },
});
const page = usePage();

// projectJobにサイズが設定されている場合、割当ブロックのサイズを固定する
const projectJobSizeId = computed(() => props.projectJob?.size_id ? String(props.projectJob.size_id) : null);

const injectedAuthUser = inject('authUser', null);
const injectedUser = inject('user', null);

// バリデーションエラー状態管理
const validationErrors = ref({});

function effectiveAuthUser() {
    return (
        injectedAuthUser ||
        (page.props && page.props.auth && page.props.auth.user ? page.props.auth.user : null) ||
        (page.props && page.props.user ? page.props.user : null) ||
        null
    );
}


// Inline event editor state (user mode)
const _today = new Date();
const workDate = ref(`${_today.getFullYear()}-${String(_today.getMonth() + 1).padStart(2, '0')}-${String(_today.getDate()).padStart(2, '0')}`); // 今日の日付をデフォルトに (YYYY-MM-DD)
const startTimeHour = ref('17');
const startTimeMin = ref('30');
const endTimeHour = ref('10');
const endTimeMin = ref('00');

// Event overlap checking state
const overlappingEvents = ref([]);
const showOverlapModal = ref(false);
const proceedWithOverlap = ref(false);

function normalizeToDateTimePartsLocal(dt) {
    if (!dt) return { date: '', time: '' };
    const s = String(dt);
    const m = s.match(/(\d{4}-\d{2}-\d{2})[T ]?(\d{2}:\d{2})/);
    if (m) return { date: m[1], time: m[2] };
    const parts = s.replace('T', ' ').split(' ');
    return { date: parts[0] || '', time: (parts[1] || '').slice(0, 5) };
}

// hours computed: coordinator = 06-22, user = 07-23
const hours = computed(() => {
    if (props.mode === 'coordinator') {
        return Array.from({ length: 17 }, (_, i) => String(6 + i).padStart(2, '0'));
    }
    return Array.from({ length: 17 }, (_, i) => String(7 + i).padStart(2, '0'));
});
const mins = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
const estimatedOptions = Array.from({ length: 32 }, (_, i) => Number(((i + 1) * 0.25).toFixed(2)));

function makeLabel(kind, id) {
    if (!id) return null;
    const list = { types: page.props.types, sizes: page.props.sizes, statuses: page.props.statuses, stages: page.props.stages }[kind];
    if (!Array.isArray(list)) return null;
    const found = list.find((x) => String(x.id) === String(id));
    return found ? `${kind.replace(/s$/, '')}: ${found.name}` : null;
}

// エラーハンドル関数：バリデーションエラーを詳細表示
function handleSaveError(errors) {
    console.error('保存エラー:', errors);
    
    // 前のエラーをクリア
    validationErrors.value = {};
    
    // エラーオブジェクトが存在する場合、詳細を表示
    if (errors && typeof errors === 'object') {
        const errorMessages = [];
        
        // エラー状態を更新
        validationErrors.value = errors;
        
        // すべてのエラーフィールドを確認
        Object.keys(errors).forEach(field => {
            const fieldErrors = Array.isArray(errors[field]) ? errors[field] : [errors[field]];
            fieldErrors.forEach(message => {
                // フィールド名を日本語に変換
                const fieldLabel = getFieldLabel(field);
                errorMessages.push(`${fieldLabel}: ${message}`);
            });
        });
        
        if (errorMessages.length > 0) {
            alert(`保存に失敗しました:\n\n${errorMessages.join('\n')}`);
            return;
        }
    }
    
    // フォールバック：詳細なエラー情報がない場合
    alert('保存に失敗しました。入力内容を確認してください。');
}

// フィールド名を日本語ラベルに変換
function getFieldLabel(fieldName) {
    const labels = {
        'assignments.0.title': 'タイトル',
        'assignments.0.user_id': '担当者',
        'assignments.0.desired_end_date': '締め切り日',
        'assignments.0.desired_time': '締め切り時間',
        'assignments.0.estimated_hours': '見積もり時間',
        'assignments.0.work_item_type_id': '作業種別',
        'assignments.0.size_id': 'サイズ',
        'assignments.0.stage_id': '工程',
        'assignments.0.difficulty': '難易度',
        'assignments.0.detail': '詳細',
        'title': 'タイトル',
        'user_id': '担当者',
        'desired_end_date': '締め切り日',
        'desired_time': '締め切り時間',
        'estimated_hours': '見積もり時間',
        'work_item_type_id': '作業種別',
        'size_id': 'サイズ',
        'stage_id': '工程',
        'difficulty': '難易度',
        'detail': '詳細',
    };
    return labels[fieldName] || fieldName;
}

// フィールドエラー取得関数
function getFieldError(fieldName) {
    return validationErrors.value[fieldName] ? validationErrors.value[fieldName][0] : null;
}

// エラークリア関数
function clearValidationErrors() {
    validationErrors.value = {};
}

// Event overlap checking function
async function checkEventOverlaps() {
    if (!workDate.value || !startTimeHour.value || !endTimeHour.value) {
        return [];
    }

    try {
        const start = `${workDate.value} ${String(startTimeHour.value).padStart(2, '0')}:${String(startTimeMin.value || '00').padStart(2, '0')}:00`;
        const end = `${workDate.value} ${String(endTimeHour.value).padStart(2, '0')}:${String(endTimeMin.value || '00').padStart(2, '0')}:00`;

        // Construct the Ziggy route properly
        const url = route('events.index') + `?date=${encodeURIComponent(workDate.value)}`;

        const res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });

        if (res.ok) {
            const events = await res.json();
            if (!Array.isArray(events)) return [];

            const startTime = new Date(start).getTime();
            const endTime = new Date(end).getTime();

            // Filter events that overlap with the new event
            // 編集中のイベント自身（同一 ID）は除外する
            const currentEventId = props.event ? Number(props.event.id) : null;
            const overlaps = events.filter((e) => {
                if (currentEventId !== null && Number(e.id) === currentEventId) return false;
                const eStart = new Date(e.start).getTime();
                const eEnd = new Date(e.end).getTime();
                // Check if there's any overlap
                return startTime < eEnd && endTime > eStart;
            });

            return overlaps;
        }
    } catch (err) {
        console.warn('Failed to check event overlaps:', err);
    }

    return [];
}

// 現在時刻（JST）を取得して開始または終了のセレクターにセット（分は丸めない）
function setCurrentTime(target) {
    const now = new Date();
    // Intl.DateTimeFormat で確実にJST時刻を取得（ブラウザのタイムゾーン設定に関わらず正確）
    const jstParts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Tokyo',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(now);
    const rawH = Number(jstParts.find((p) => p.type === 'hour').value);
    const rawM = Number(jstParts.find((p) => p.type === 'minute').value);
    const m = String(rawM).padStart(2, '0');
    // hours配列の範囲内に収める
    const hourNums = hours.value.map(Number);
    const clampedH = Math.min(Math.max(rawH, hourNums[0]), hourNums[hourNums.length - 1]);
    const h = String(clampedH).padStart(2, '0');
    if (target === 'start') {
        startTimeHour.value = h;
        startTimeMin.value = m;
    } else {
        endTimeHour.value = h;
        endTimeMin.value = m;
    }
}

// 分のセレクターオプション：5分刈みベースだが、現在値が5分刈みにない場合は動的に追加
function minsOptions(currentVal) {
    if (!currentVal || mins.includes(currentVal)) return mins;
    return [...mins, currentVal].sort((a, b) => Number(a) - Number(b));
}

function resolveDifficultySlug(id) {
    if (id === undefined || id === null) return 'normal';
    const list = page.props.difficulties || null;
    if (Array.isArray(list)) {
        const found = list.find((d) => String(d.id) === String(id));
        if (found) return found.slug || found.key || 'normal';
    }
    // フォールバック: hardcoded ID→slug
    const map = { 1: 'light', 2: 'normal', 3: 'heavy', 4: 'serious' };
    return map[Number(id)] || 'normal';
}

function resolveDifficultyId(val) {
    if (val === undefined || val === null || val === '') return null;
    const num = Number(val);
    if (!Number.isNaN(num) && String(val).trim() !== '') return num;
    const list = window?.page?.props?.difficulties || page.props.difficulties || null;
    if (Array.isArray(list)) {
        const lower = String(val).toLowerCase();
        const found = list.find((d) => {
            try {
                if (!d) return false;
                const parts = [d.name, d.slug, d.key, d.label].filter(Boolean);
                return parts.some((p) => String(p).toLowerCase() === lower);
            } catch (e) {
                return false;
            }
        });
        if (found) return found.id;
    }
    try {
        const map = { light: 'light', normal: 'normal', heavy: 'heavy' };
        const k = String(val).toLowerCase();
        if (map[k]) {
            if (Array.isArray(window?.page?.props?.difficulties)) {
                const f = window.page.props.difficulties.find((d) => String(d.slug || d.key || d.name).toLowerCase() === k);
                if (f) return f.id;
            }
        }
    } catch (e) {}
    try {
        const k2 = String(val).toLowerCase();
        const fallbackIds = { light: 1, normal: 2, heavy: 3 };
        if (Object.prototype.hasOwnProperty.call(fallbackIds, k2)) return fallbackIds[k2];
    } catch (e) {}
    return null;
}

function normalizeAssignment(a) {
    if (props.mode === 'coordinator') {
        return {
            id: a.id || null,
            title_prefix: `${props.projectJob?.title || ''}：`,
            title_suffix: (() => {
                const raw = a.title || '';
                if (!raw) return '';
                const pj = props.projectJob?.title || '';
                const candidates = [];
                if (pj) {
                    candidates.push(`「${pj}：`);
                    candidates.push(`${pj}：`);
                    candidates.push(`${pj}:`);
                }
                for (const pref of candidates) {
                    if (raw.startsWith(pref)) return raw.slice(pref.length).trim();
                }
                if (raw.includes('：'))
                    return raw
                        .replace(/^.*？：/, '')
                        .replace(/^.*：/, '')
                        .trim();
                if (raw.includes(':')) return raw.replace(/^.*?:/, '').trim();
                return raw;
            })(),
            detail: a.detail || '',
            difficulty: a.difficulty || resolveDifficultySlug(a.difficulty_id),
            difficulty_id: a.difficulty_id != null ? Number(a.difficulty_id) : resolveDifficultyId(a.difficulty),
            desired_start_date: a.desired_start_date || a.desired_date || '',
            desired_end_date: a.desired_end_date || new Date().toISOString().split('T')[0],
            desired_time_hour: a.desired_time ? a.desired_time.split(':')[0] || '17' : a.desired_time_hour || '17',
            desired_time_min: a.desired_time ? a.desired_time.split(':')[1] || '30' : a.desired_time_min || '30',
            estimated_hours: a.estimated_hours !== undefined && a.estimated_hours !== null ? a.estimated_hours : '',
            user_id: a.user_id || (a.user ? a.user.id : '') || '',
            work_item_type_id: a.work_item_type_id != null ? String(a.work_item_type_id) : null,
            size_id: a.size_id != null ? String(a.size_id) : (props.projectJob?.size_id ? String(props.projectJob.size_id) : null),
            stage_id: a.stage_id != null ? String(a.stage_id) : null,
            status_id: 1,
            company_id: a.company_id || null,
            department_id: a.department_id || null,
            type_label: a.type_label || makeLabel('types', a.work_item_type_id),
            size_label: a.size_label || makeLabel('sizes', a.size_id),
            stage_label: a.stage_label || makeLabel('stages', a.stage_id),
            status_label: a.status_label || makeLabel('statuses', a.status_id),
            amounts: a.amounts !== undefined && a.amounts !== null ? a.amounts : a.amounts || 0,
            amounts_unit: a.amounts_unit || 'page',
            project_job:
                a.project_job ||
                (props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title, client: props.projectJob.client || null } : null),
            _locked_stage: a._locked_stage || false,
            _locked_size: a._locked_size || false,
            _locked_work_item_type: a._locked_work_item_type || false,
            _locked_user: a._locked_user || false,
            _progress_sheet_id: a._progress_sheet_id ?? null,
            _row_id: a._row_id ?? null,
            _col_key: a._col_key ?? null,
            file_info: a.file_info ?? null,
        };
    } else {
        return {
            id: a.id || null,
            project_job: a.project_job || null,
            project_job_id: a.project_job_id || (a.project_job && a.project_job.id) || null,
            _client_id:
                a._client_id ||
                (a.project_job && (a.project_job.client?.id || a.project_job.client_id)) ||
                // その他案件の場合: project_job_id が otherProjectId と一致すれば otherClientId を補完
                (props.otherProjectId !== null &&
                    (a.project_job_id || (a.project_job && a.project_job.id)) &&
                    String(a.project_job_id || (a.project_job && a.project_job.id)) === String(props.otherProjectId)
                    ? String(props.otherClientId)
                    : '') ||
                '',
            title_suffix: a.title ? a.title.replace(/^.*：/, '').trim() : a.title_suffix || '',
            detail: a.detail || '',
            user_id: a.user_id || (effectiveAuthUser() ? effectiveAuthUser().id : null),
            difficulty: a.difficulty || resolveDifficultySlug(a.difficulty_id),
            difficulty_id: a.difficulty_id != null ? Number(a.difficulty_id) : resolveDifficultyId(a.difficulty),
            desired_start_date: a.desired_start_date || a.desired_date || '',
            desired_end_date: a.desired_end_date || new Date().toISOString().split('T')[0],
            desired_time_hour: a.desired_time ? a.desired_time.split(':')[0] || '17' : a.desired_time_hour || '17',
            desired_time_min: a.desired_time ? a.desired_time.split(':')[1] || '30' : a.desired_time_min || '30',
            start_time_hour: a.start_time
                ? a.start_time.split(':')[0] || '17'
                : a.start_time_hour || (a.desired_time ? a.desired_time.split(':')[0] : '17'),
            start_time_min: a.start_time
                ? a.start_time.split(':')[1] || '30'
                : a.start_time_min || (a.desired_time ? a.desired_time.split(':')[1] : '30'),
            estimated_hours: a.estimated_hours !== undefined && a.estimated_hours !== null ? a.estimated_hours : '',
            work_item_type_id: a.work_item_type_id != null ? String(a.work_item_type_id) : null,
            size_id: a.size_id != null ? String(a.size_id) : (props.projectJob?.size_id ? String(props.projectJob.size_id) : null),
            stage_id: a.stage_id != null ? String(a.stage_id) : null,
            status_id: a.status_id != null ? String(a.status_id) : null,
            amounts: a.amounts !== undefined && a.amounts !== null ? a.amounts : a.amounts_unit ? 0 : undefined,
            amounts_unit: a.amounts_unit ?? 'page',
            _medium_filter: a._medium_filter ?? '',
            _type_filter: a._type_filter ?? '',
            source_assignment_id: a.source_assignment_id || null,
            supersedes_assignment_id: a.supersedes_assignment_id || null,
            file_info: a.file_info ?? null,
            _locked_client: a._locked_client || false,
            _locked_project: a._locked_project || false,
            _locked_stage: a._locked_stage || false,
            _locked_size: a._locked_size || false,
            _locked_work_item_type: a._locked_work_item_type || false,
            _locked_user: a._locked_user || false,
            _progress_sheet_id: a._progress_sheet_id ?? null,
            _row_id: a._row_id ?? null,
            _col_key: a._col_key ?? null,
        };
    }
}

const assignments = ref(props.assignments && props.assignments.length ? props.assignments.map(normalizeAssignment) : [normalizeAssignment({})]);

if (props.mode === 'coordinator') {
    assignments.value.forEach((a) => {
        if (a.saving === undefined) a.saving = false;
        if (a.linked_assignment_id === undefined) a.linked_assignment_id = null;
        if (a.title_prefix === undefined) a.title_prefix = `「${props.projectJob?.title || ''}：`;
        if (a.title_suffix === undefined) a.title_suffix = '';
        if (a.showInlineSelector === undefined) a.showInlineSelector = false;
        const authForDefaults = effectiveAuthUser();
        const defaultCompany =
            authForDefaults && authForDefaults.company_id ? authForDefaults.company_id : page.props.company ? page.props.company.id : null;
        const defaultDepartment =
            authForDefaults && authForDefaults.department_id ? authForDefaults.department_id : page.props.department ? page.props.department.id : null;
        if (a.company_id === undefined || a.company_id === null || a.company_id === '') a.company_id = defaultCompany;
        if (a.department_id === undefined || a.department_id === null || a.department_id === '') a.department_id = defaultDepartment;
        if (a.work_item_type_id === undefined) a.work_item_type_id = a.work_item_type_id || null;
        if (a.size_id === undefined) a.size_id = a.size_id || null;
        if (a.stage_id === undefined) a.stage_id = a.stage_id || null;
        if (a.status_id === undefined) a.status_id = 1;
        if ((a.difficulty_id === undefined || a.difficulty_id === null) && a.difficulty) {
            try {
                const resolved = resolveDifficultyId(a.difficulty);
                if (resolved !== null) a.difficulty_id = resolved;
            } catch (e) {}
        }
        const amt = a.amounts !== undefined && a.amounts !== null && a.amounts !== '' ? Number(a.amounts) : null;
        if (amt !== null && !Number.isNaN(amt)) {
            const abs = Math.max(0, Math.floor(Math.abs(amt)) % 10000);
            const d0 = Math.floor(abs / 1000) % 10;
            const d1 = Math.floor(abs / 100) % 10;
            const d2 = Math.floor(abs / 10) % 10;
            const d3 = Math.floor(abs % 10);
            if (a.amount_digit_0 === undefined) a.amount_digit_0 = String(d0);
            if (a.amount_digit_1 === undefined) a.amount_digit_1 = String(d1);
            if (a.amount_digit_2 === undefined) a.amount_digit_2 = String(d2);
            if (a.amount_digit_3 === undefined) a.amount_digit_3 = String(d3);
        } else {
            if (a.amount_digit_0 === undefined) a.amount_digit_0 = '0';
            if (a.amount_digit_1 === undefined) a.amount_digit_1 = '0';
            if (a.amount_digit_2 === undefined) a.amount_digit_2 = '0';
            if (a.amount_digit_3 === undefined) a.amount_digit_3 = '0';
        }
        if (a.amounts === undefined) a.amounts = a.amounts || 0;
        if (a.amounts_unit === undefined) a.amounts_unit = a.amounts_unit || 'page';
        if (a._type_filter === undefined) a._type_filter = '';
        if (a._medium_filter === undefined) a._medium_filter = 'paper';
    });

    assignments.value.forEach((a) => {
        try {
            if (a.project_job) {
                if (!a.project_job.title && !a.project_job.name) {
                    if (props.projectJob && (props.projectJob.title || props.projectJob.name)) {
                        a.project_job.title = props.projectJob.title || props.projectJob.name || '';
                    } else if (Array.isArray(page.props.userProjects)) {
                        const pid = a.project_job.id || a.project_job.project_job_id || null;
                        if (pid) {
                            const found = page.props.userProjects.find((p) => String(p.id) === String(pid));
                            if (found) a.project_job.title = found.title || found.name || found.project_name || '';
                        }
                    }
                }
            }
        } catch (e) {}
    });

    if (!props.editMode) {
        const memberNameLocal = (userId) => {
            if (!userId) return '';
            const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
            if (m && (m.name || m.full_name)) return m.name || m.full_name;
            if (typeof userId === 'object' && userId !== null) return userId.name || userId.id || '';
            return String(userId || '');
        };

        assignments.value = assignments.value.map((b) => {
            const copy = { ...b };
            try {
                copy.company_id = companyName(b.company_id) || copy.company_id;
                copy.department_id = departmentName(b.department_id) || copy.department_id;
                copy.work_item_type_id = itemName('types', b.work_item_type_id) || copy.work_item_type_id;
                copy.size_id = itemName('sizes', b.size_id) || copy.size_id;
                copy.stage_id = itemName('stages', b.stage_id) || copy.stage_id;
                copy.status_id = itemName('statuses', b.status_id) || copy.status_id;
                copy.user_id = memberNameLocal(b.user_id) || (b.user && (b.user.name || b.user.id)) || copy.user_id;
            } catch (e) {}
            return copy;
        });
    }
} else {
    assignments.value.forEach((a) => {
        if (a.company_id === undefined || a.company_id === null || a.company_id === '') {
            const auth = effectiveAuthUser();
            const defaultCompany = page.props.company ? page.props.company.id : auth && auth.company_id ? auth.company_id : null;
            a.company_id = defaultCompany;
        }
        if (a.department_id === undefined || a.department_id === null || a.department_id === '') {
            const auth = effectiveAuthUser();
            const defaultDepartment = page.props.department ? page.props.department.id : auth && auth.department_id ? auth.department_id : null;
            a.department_id = defaultDepartment;
        }
        if (a.work_item_type_id === undefined) a.work_item_type_id = a.work_item_type_id || null;
        if (a.size_id === undefined) a.size_id = a.size_id || null;
        if (a.stage_id === undefined) a.stage_id = a.stage_id || null;
        if (a.status_id === undefined || a.status_id === null) {
            if (!a.id && props.defaultStatusId !== null && props.defaultStatusId !== undefined) {
                a.status_id = String(props.defaultStatusId);
            } else {
                a.status_id = a.status_id || null;
            }
        }
        if (a.difficulty === undefined) a.difficulty = a.difficulty || 'normal';
        if (a.difficulty_id === undefined || a.difficulty_id === null) {
            try {
                a.difficulty_id = resolveDifficultyId(a.difficulty);
            } catch (e) {
                a.difficulty_id = null;
            }
        }
        if (a.desired_start_date === undefined) a.desired_start_date = a.desired_start_date || '';
        if (a.desired_end_date === undefined) a.desired_end_date = a.desired_end_date || new Date().toISOString().split('T')[0];
        if (a.desired_time_hour === undefined) a.desired_time_hour = a.desired_time_hour || '17';
        if (a.desired_time_min === undefined) a.desired_time_min = a.desired_time_min || '30';
        if (a.start_time_hour === undefined) a.start_time_hour = a.start_time_hour || a.desired_time_hour || '17';
        if (a.start_time_min === undefined) a.start_time_min = a.start_time_min || a.desired_time_min || '30';
        if (a.estimated_hours === undefined) a.estimated_hours = a.estimated_hours || '';
        if (a.amount_digit_0 === undefined) a.amount_digit_0 = a.amounts ? String(Math.floor(a.amounts / 1000) % 10) : '0';
        if (a.amount_digit_1 === undefined) a.amount_digit_1 = a.amounts ? String(Math.floor(a.amounts / 100) % 10) : '0';
        if (a.amount_digit_2 === undefined) a.amount_digit_2 = a.amounts ? String(Math.floor(a.amounts / 10) % 10) : '0';
        if (a.amount_digit_3 === undefined) a.amount_digit_3 = a.amounts ? String(a.amounts % 10) : '0';
        if (a.amounts === undefined) a.amounts = a.amounts || 0;
        if (a.amounts_unit === undefined) a.amounts_unit = a.amounts_unit || 'page';
    });

    try {
        const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
        if (role !== 'superadmin') {
            const auth2 = effectiveAuthUser();
            const forcedCompany = page.props.company ? page.props.company.id : auth2 && auth2.company_id ? auth2.company_id : null;
            const forcedDepartment = page.props.department ? page.props.department.id : auth2 && auth2.department_id ? auth2.department_id : null;
            assignments.value.forEach((a) => {
                if (!a.company_id && forcedCompany) a.company_id = forcedCompany;
                if (!a.department_id && forcedDepartment) a.department_id = forcedDepartment;
            });
        }
    } catch (e) {}
}

const showSelector = ref(false);
const selectorTargetIndex = ref(null);
const { toasts, dismissToast, toastClass } = useToasts();

onMounted(() => {
    if (props.mode === 'coordinator') {
        try {
            const list = window?.page?.props?.difficulties || page.props.difficulties || null;
            if (Array.isArray(list)) {
                // difficulties loaded
            }
        } catch (e) {}
    } else {
        // user mode: init inline event editor
        const ev = props.event || (assignments.value && assignments.value[0] ? assignments.value[0] : null);
        if (ev) {
            const s = normalizeToDateTimePartsLocal(ev.start || ev.desired_start_date || ev.start_time || '');
            const e = normalizeToDateTimePartsLocal(ev.end || ev.desired_end_date || ev.desired_time || '');
            workDate.value = s.date || (assignments.value[0] ? assignments.value[0].desired_start_date || '' : '');
            if (s.time) {
                const [sh, sm] = String(s.time).split(':');
                startTimeHour.value = sh || '17';
                startTimeMin.value = sm || '30';
            } else if (assignments.value[0]) {
                startTimeHour.value = assignments.value[0].start_time_hour || '17';
                startTimeMin.value = assignments.value[0].start_time_min || '30';
            }
            if (e.time) {
                const [eh, em] = String(e.time).split(':');
                endTimeHour.value = eh || startTimeHour.value || '10';
                endTimeMin.value = em || startTimeMin.value || '00';
            } else if (assignments.value[0]) {
                endTimeHour.value = assignments.value[0].desired_time_hour || startTimeHour.value || '17';
                endTimeMin.value = assignments.value[0].desired_time_min || startTimeMin.value || '30';
            }
        }
    }
});

// Watch for user mode prop changes
watch(
    () => ({
        injectedAuthUser: injectedAuthUser,
        injectedUser: injectedUser,
        effective: effectiveAuthUser(),
        pageAuthUser: page.props && page.props.auth ? page.props.auth.user : null,
        pageUser: page.props ? page.props.user : null,
    }),
    (_val) => {},
    { deep: true },
);

watch(assignments, () => {}, { deep: true });

// assignment_name → _type_filter の自動マッピング
const ASSIGNMENT_TYPE_MAP = {
    '組版': 'dtp',
    'オペレーター': 'dtp',
    'DTP': 'dtp',
    'dtp': 'dtp',
    'デザイナー': 'design',
    'デザイン': 'design',
    '制作': 'design',
    '校正': 'proof',
    '進行管理': 'mgmt',
    '営業': 'sales',
};

function assignmentNameToTypeFilter(assignmentName) {
    if (!assignmentName) return '';
    for (const [key, val] of Object.entries(ASSIGNMENT_TYPE_MAP)) {
        if (assignmentName.includes(key)) return val;
    }
    return '';
}

function onUserChange(block) {
    const membersList = props.members || members.value || [];
    const found = membersList.find((m) => String(m.id) === String(block.user_id));
    if (found && found.assignment_name) {
        block._type_filter = assignmentNameToTypeFilter(found.assignment_name);
    }
}

function clientName(block) {
    try {
        if (block && block.project_job && block.project_job.client) {
            const c = block.project_job.client;
            if (c.name) return c.name;
            if (c.client_name) return c.client_name;
            if (c.name_en) return c.name_en;
        }
        if (block && block.project_job && block.project_job.client_name) return block.project_job.client_name;
        if (props.projectJob && props.projectJob.client) {
            const pc = props.projectJob.client;
            if (pc.name) return pc.name;
            if (pc.client_name) return pc.client_name;
        }
        if (props.projectJob && props.projectJob.client_name) return props.projectJob.client_name;
        const clientId =
            (block && block.project_job && (block.project_job.client?.id || block.project_job.client_id)) ||
            (props.projectJob && (props.projectJob.client?.id || props.projectJob.client_id));
        if (clientId && Array.isArray(page.props.clients)) {
            const found = page.props.clients.find((x) => String(x.id) === String(clientId));
            if (found && found.name) return found.name;
        }
    } catch (e) {}
    return '-';
}

function projectName(block) {
    try {
        if (block && block.project_job) {
            const pj = block.project_job;
            if (pj.title) return pj.title;
            if (pj.name) return pj.name;
            if (pj.project_name) return pj.project_name;
        }
        if (props.projectJob && (props.projectJob.title || props.projectJob.name)) return props.projectJob.title || props.projectJob.name;
        const pid = block && block.project_job && (block.project_job.id || block.project_job.project_job_id);
        if (pid && Array.isArray(page.props.userProjects)) {
            const found = page.props.userProjects.find((p) => String(p.id) === String(pid));
            if (found) return found.name || found.title || found.project_name || '';
        }
        if (block && block.project_job_id) {
            const found = (props.userProjects || []).find((p) => String(p.id) === String(block.project_job_id));
            if (found) return found.title || found.name;
        }
    } catch (e) {}
    return '-';
}

function onSelected(payload) {
    const idx = selectorTargetIndex.value;
    const makeLabel2 = (kind, id) => {
        if (!id) return null;
        const list = { types: page.props.types, sizes: page.props.sizes, statuses: page.props.statuses, stages: page.props.stages }[kind];
        if (!Array.isArray(list)) return null;
        const found = list.find((x) => String(x.id) === String(id));
        return found ? `${kind.replace(/s$/, '')}: ${found.name}` : null;
    };

    if (idx === null || idx === undefined) {
        assignments.value.push({
            title_prefix: `「${props.projectJob?.title || ''}：`,
            title_suffix: payload.size_id ? `サイズ: ${payload.size_id}` : '',
            detail: '',
            difficulty: 'normal',
            desired_date: new Date().toISOString().split('T')[0],
            desired_time_hour: '17',
            desired_time_min: '30',
            estimated_hours: '',
            user_id: '',
            work_item_type_id: payload.work_item_type_id,
            size_id: payload.size_id,
            stage_id: payload.stage_id || null,
            status_id: 1,
            company_id: payload.company_id,
            department_id: payload.department_id,
            saving: false,
            linked_assignment_id: null,
            type_label: makeLabel2('types', payload.work_item_type_id),
            size_label: makeLabel2('sizes', payload.size_id),
            stage_label: makeLabel2('stages', payload.stage_id),
            status_label: makeLabel2('statuses', payload.status_id),
        });
    } else {
        const b = assignments.value[idx];
        b.work_item_type_id = payload.work_item_type_id;
        b.size_id = payload.size_id;
        b.stage_id = payload.stage_id || null;
        b.status_id = 1;
        b.company_id = payload.company_id;
        b.department_id = payload.department_id;
        b.type_label = makeLabel2('types', payload.work_item_type_id);
        b.size_label = makeLabel2('sizes', payload.size_id);
        b.stage_label = makeLabel2('stages', payload.stage_id);
        b.status_label = makeLabel2('statuses', payload.status_id);
    }
    selectorTargetIndex.value = null;
}

function companyNameFromId(companyId) {
    if (!companyId) return companyName(companyId);
    const fromList = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    if (fromList && fromList.name) return fromList.name;
    if (page.props && page.props.company && String(page.props.company.id) === String(companyId) && page.props.company.name) {
        return page.props.company.name;
    }
    try {
        const up = page.props.userProjects || page.props.user_projects || null;
        if (Array.isArray(up)) {
            const found = up.find((p) => {
                if (!p) return false;
                if (p.client && (String(p.client.id) === String(companyId) || String(p.client_id) === String(companyId))) return true;
                if (p.company && (String(p.company.id) === String(companyId) || String(p.company_id) === String(companyId))) return true;
                return false;
            });
            if (found) {
                if (found.client && found.client.name) return found.client.name;
                if (found.company && found.company.name) return found.company.name;
            }
        }
    } catch (e) {}
    return companyName(companyId);
}

function departmentsFlattened() {
    const out = [];
    (page.props.companies || []).forEach((c) => {
        const deps = c && c.departments ? c.departments : [];
        Array.from(deps || []).forEach((d) => {
            const id = d && (d.id ?? d['id']) ? (d.id ?? d['id']) : null;
            const name = d && (d.name ?? d['name']) ? (d.name ?? d['name']) : null;
            out.push({ id: id, name: name, company_id: c && (c.id ?? c['id']) ? (c.id ?? c['id']) : null });
        });
    });
    return out;
}

function departmentNameFromId(departmentId) {
    if (!departmentId) return departmentName(departmentId);
    const fromList = departmentsFlattened().find((d) => String(d.id) === String(departmentId));
    if (fromList && fromList.name) return fromList.name;
    if (page.props && page.props.department && String(page.props.department.id) === String(departmentId) && page.props.department.name) {
        return page.props.department.name;
    }
    return departmentName(departmentId);
}

function departmentNameFromBlock(block) {
    try {
        if (!block) return departmentNameFromId(null);
        if (block.department && (block.department.name || block.department.department_name)) {
            return block.department.name || block.department.department_name;
        }
        if (block.department_name) return block.department_name;
        if (block.project_job && block.project_job.department) {
            const d = block.project_job.department;
            if (d.name) return d.name;
            if (d.department_name) return d.department_name;
        }
    } catch (e) {}
    return departmentNameFromId(block && block.department_id ? block.department_id : null);
}

function companyName(companyId) {
    if (!companyId) return 'グローバル/未設定';
    const found = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    return found ? found.name : String(companyId);
}

function departmentName(departmentId) {
    if (!departmentId) return '指定なし';
    const all = departmentsFlattened();
    const found = all.find((d) => String(d.id) === String(departmentId));
    return found ? found.name : String(departmentId);
}

function companyById(companyId) {
    if (!companyId) return null;
    const found = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    return found || null;
}

function departmentById(departmentId) {
    if (!departmentId) return null;
    const all = departmentsFlattened();
    const found = all.find((d) => String(d.id) === String(departmentId));
    return found || null;
}

function itemName(kind, id) {
    if (!id) return '';
    const list = { types: page.props.types, sizes: page.props.sizes, statuses: page.props.statuses, stages: page.props.stages }[kind];
    if (!Array.isArray(list)) return String(id);
    const found = list.find((x) => String(x.id) === String(id));
    return found ? found.name : String(id);
}

function memberName(userId) {
    if (!userId) return '未指定';
    if (typeof userId === 'string' && isNaN(Number(userId))) return userId;
    const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
    if (m) return m.name || m.full_name || String(m.id);
    const pageUsers = page.props.users || page.props.members || [];
    const found = (Array.isArray(pageUsers) ? pageUsers : []).find((u) => String(u.id) === String(userId));
    if (found) return found.name || found.full_name || String(found.id);
    try {
        if (userId && typeof userId === 'object') return userId.name || userId.full_name || String(userId.id || '');
    } catch (e) {}
    return String(userId);
}

/** 選択中ユーザーの雇用形態情報を返す */
function memberEmploymentType(userId) {
    if (!userId) return null;
    const membersList = props.members || [];
    return membersList.find((m) => String(m.id) === String(userId)) ?? null;
}

const EMPLOYMENT_BADGE = {
    dispatch:  { label: '派遣社員', cls: 'bg-orange-100 text-orange-700' },
    outsource: { label: '業務委託', cls: 'bg-purple-100 text-purple-700' },
    contract:  { label: '契約社員', cls: 'bg-green-100 text-green-700' },
};

// ── グループ化ヘルパー ─────────────────────────────────────────────────────

const TYPE_GROUP_ORDER  = ['dtp', 'design', 'proof', 'mgmt', 'sales', 'common'];
const TYPE_GROUP_LABELS = {
    dtp:    '組版・DTP',
    design: 'デザイン・制作',
    proof:  '校正・確認',
    mgmt:   '進行管理・事務',
    sales:  '営業',
    common: '共通',
};
const SIZE_GROUP_ORDER  = ['paper', 'digital'];
const SIZE_GROUP_LABELS = { paper: '紙媒体', digital: 'デジタル' };

// typeFilter: '' = 全部表示、それ以外 = そのグループのみ表示
function typesGrouped(companyId, departmentId, typeFilter) {
    const list     = typesForSelect(companyId, departmentId);
    const filtered = typeFilter ? list.filter((t) => (t.group || 'common') === typeFilter) : list;
    if (typeFilter) {
        // 単一グループ → optgroup 不要のフラット表示用に 1グループとして返す
        return [{ group: typeFilter, label: TYPE_GROUP_LABELS[typeFilter] || typeFilter, items: filtered }];
    }
    const map = {};
    for (const t of filtered) {
        const g = t.group || 'common';
        if (!map[g]) map[g] = [];
        map[g].push(t);
    }
    // ハードコード済みグループを順序通りに並べ、未知のカスタムグループは末尾追加
    const knownGroups = TYPE_GROUP_ORDER.filter((g) => map[g]).map((g) => ({ group: g, label: TYPE_GROUP_LABELS[g] || g, items: map[g] }));
    const extraGroups = Object.keys(map)
        .filter((g) => !TYPE_GROUP_ORDER.includes(g))
        .map((g) => ({ group: g, label: g, items: map[g] }));
    return [...knownGroups, ...extraGroups];
}

// mediumFilter: '' = 全表示、'paper' = 紙媒体のみ、'digital' = デジタルのみ
function sizesGrouped(companyId, departmentId, mediumFilter) {
    const list     = sizesForSelect(companyId, departmentId);
    const filtered = mediumFilter ? list.filter((s) => (s.group || 'paper') === mediumFilter) : list;
    if (mediumFilter) {
        return [{ group: mediumFilter, label: SIZE_GROUP_LABELS[mediumFilter] || mediumFilter, items: filtered }];
    }
    const map = {};
    for (const s of filtered) {
        const g = s.group || 'paper';
        if (!map[g]) map[g] = [];
        map[g].push(s);
    }
    return SIZE_GROUP_ORDER
        .filter((g) => map[g])
        .map((g) => ({ group: g, label: SIZE_GROUP_LABELS[g] || g, items: map[g] }));
}

function typesForSelect(companyId, departmentId) {
    const list = page.props.types || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((t) => {
        const tComp = t.company_id === undefined || t.company_id === null ? '' : String(t.company_id);
        const tDept = t.department_id === undefined || t.department_id === null ? '' : String(t.department_id);
        const compMatch = !t.company_id || tComp === sComp || sComp === '';
        const deptMatch = !t.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

function sizesForSelect(companyId, departmentId) {
    const list = page.props.sizes || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((s) => {
        const tComp = s.company_id === undefined || s.company_id === null ? '' : String(s.company_id);
        const tDept = s.department_id === undefined || s.department_id === null ? '' : String(s.department_id);
        const compMatch = !s.company_id || tComp === sComp || sComp === '';
        const deptMatch = !s.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

function stagesForSelect(companyId, departmentId) {
    const list = page.props.stages || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((st) => {
        const tComp = st.company_id === undefined || st.company_id === null ? '' : String(st.company_id);
        const tDept = st.department_id === undefined || st.department_id === null ? '' : String(st.department_id);
        const compMatch = !st.company_id || tComp === sComp || sComp === '';
        const deptMatch = !st.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

function statusesForSelect(companyId, departmentId) {
    const list = page.props.statuses || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((st) => {
        const tComp = st.company_id === undefined || st.company_id === null ? '' : String(st.company_id);
        const tDept = st.department_id === undefined || st.department_id === null ? '' : String(st.department_id);
        const compMatch = !st.company_id || tComp === sComp || sComp === '';
        const deptMatch = !st.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

function openInlineSelector(idx) {
    const b = assignments.value[idx];
    b.showInlineSelector = true;
    if (!b.selectionForm) {
        b.selectionForm = {
            company_id: b.company_id || page.props.company ? page.props.company.id : page.props.auth.user.company_id || '',
            department_id: b.department_id || page.props.department ? page.props.department.id : page.props.auth.user.department_id || '',
            type_id: b.work_item_type_id || '',
            size_id: b.size_id || '',
            stage_id: b.stage_id || '',
            status_id: b.status_id || '',
        };
    }
}

function cancelInlineSelector(idx) {
    const b = assignments.value[idx];
    if (b) b.showInlineSelector = false;
}

function applyInlineSelected(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    const f = b.selectionForm || {};
    b.work_item_type_id = f.type_id || null;
    b.size_id = f.size_id || null;
    b.stage_id = f.stage_id || null;
    b.status_id = f.status_id || null;
    b.company_id = f.company_id || null;
    b.department_id = f.department_id || null;
    b.type_label = b.work_item_type_id ? makeLabel('types', b.work_item_type_id) : null;
    b.size_label = b.size_id ? makeLabel('sizes', b.size_id) : null;
    b.stage_label = b.stage_id ? makeLabel('stages', b.stage_id) : null;
    b.status_label = b.status_id ? makeLabel('statuses', b.status_id) : null;
    b.showInlineSelector = false;
}

function onInlineSelectionChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    b.type_label = b.work_item_type_id ? itemName('types', b.work_item_type_id) : null;
    b.size_label = b.size_id ? itemName('sizes', b.size_id) : null;
    b.stage_label = b.stage_id ? itemName('stages', b.stage_id) : null;
    b.status_label = b.status_id ? itemName('statuses', b.status_id) : null;
}

function hasDepartment(block) {
    return block && block.department_id !== undefined && block.department_id !== null && String(block.department_id) !== '';
}

function companyDisabled() {
    try {
        const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
        return role !== 'superadmin';
    } catch (e) {
        return false;
    }
}

function departmentDisabled() {
    try {
        const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
        return role !== 'superadmin';
    } catch (e) {
        return false;
    }
}

function onCompanyChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    b.department_id = '';
    b.work_item_type_id = null;
    b.size_id = null;
    b.stage_id = null;
    onInlineSelectionChange(idx);
}

function onDepartmentChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    b.work_item_type_id = null;
    b.size_id = null;
    b.stage_id = null;
    onInlineSelectionChange(idx);
}

function onAmountDigitsChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    const d0 = Number(b.amount_digit_0 || '0');
    const d1 = Number(b.amount_digit_1 || '0');
    const d2 = Number(b.amount_digit_2 || '0');
    const d3 = Number(b.amount_digit_3 || '0');
    const value = d0 * 1000 + d1 * 100 + d2 * 10 + d3;
    b.amounts = value;
}

function projectsForBlock(block) {
    if (!block || !block._client_id) return props.userProjects || [];
    if (props.otherClientId !== null && String(block._client_id) === String(props.otherClientId)) {
        return (props.userProjects || []).filter((p) => props.otherProjectId !== null && String(p.id) === String(props.otherProjectId));
    }
    return (props.userProjects || []).filter((p) => String(p.client_id) === String(block._client_id));
}

function onClientChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    if (props.otherClientId !== null && String(b._client_id) === String(props.otherClientId)) {
        b.project_job_id = props.otherProjectId !== null ? String(props.otherProjectId) : '';
    } else {
        b.project_job_id = '';
    }
}

function openSelector(idx) {
    selectorTargetIndex.value = idx;
    showSelector.value = true;
}

function addBlock() {
    const pc  = Math.max(0, Math.floor(Math.abs(props.projectJob?.page_count ?? 0)) % 10000);
    assignments.value.push({
        title_prefix: `「${props.projectJob?.title || ''}：`,
        title_suffix: '',
        detail: '',
        difficulty: 'normal',
        desired_date: new Date().toISOString().split('T')[0],
        desired_time_hour: '17',
        desired_time_min: '30',
        estimated_hours: '',
        user_id: props.defaultUserId || '',
        company_id: effectiveAuthUser() ? effectiveAuthUser().company_id : null,
        department_id: effectiveAuthUser() ? effectiveAuthUser().department_id : null,
        status_id: 1,
        saving: false,
        linked_assignment_id: null,
        amount_digit_0: String(Math.floor(pc / 1000) % 10),
        amount_digit_1: String(Math.floor(pc / 100) % 10),
        amount_digit_2: String(Math.floor(pc / 10) % 10),
        amount_digit_3: String(pc % 10),
        amounts: pc,
        amounts_unit: 'page',
        project_job: props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title } : null,
        _type_filter: '',
        _medium_filter: 'paper',
        size_id: props.projectJob?.size_id ? String(props.projectJob.size_id) : null,
        file_info: null,
    });
}

function removeBlock(i) {
    assignments.value.splice(i, 1);
}

function todayDateStr() {
    const d = new Date();
    return d.toISOString().split('T')[0];
}

function minEndDate(idx) {
    const a = assignments.value[idx];
    return a.desired_start_date || todayDateStr();
}

function availableHours(idx) {
    const a = assignments.value[idx];
    if (!a.desired_end_date) return hours.value;
    const today = todayDateStr();
    if (a.desired_end_date !== today) return hours.value;
    const now = new Date();
    const currentHour = String(now.getHours()).padStart(2, '0');
    return hours.value.filter((h) => h >= currentHour);
}

function availableMins(idx, hour) {
    const a = assignments.value[idx];
    if (!a.desired_end_date) return mins;
    const today = todayDateStr();
    if (a.desired_end_date !== today) return mins;
    const now = new Date();
    const currentHour = String(now.getHours()).padStart(2, '0');
    if (hour > currentHour) return mins;
    const curMin = now.getMinutes();
    const nextQuarter = Math.ceil(curMin / 15) * 15;
    return mins.filter((m) => Number(m) >= nextQuarter);
}

function onEndDateChange(idx) {
    const a = assignments.value[idx];
    if (a.desired_start_date && a.desired_end_date && a.desired_end_date < a.desired_start_date) {
        a.desired_end_date = a.desired_start_date;
    }
}

function onHourChange(idx) {
    const a = assignments.value[idx];
    const avail = availableMins(idx, a.desired_time_hour);
    if (!avail.includes(a.desired_time_min)) {
        a.desired_time_min = avail.length ? avail[0] : '00';
    }
}

function assembleTitle(a) {
    try {
        if (a?.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
        let t = a?.title_prefix ?? '';
        t = String(t || '').replace(/^\s+|\s+$/g, '');
        t = t.replace(/^.*?[：:]/, '');
        t = t.replace(/^\u300c|\u300d|"|'/g, '').replace(/\u300c|\u300d|"|'$/g, '');
        return t.trim();
    } catch (e) {
        return '';
    }
}

function memberById(userId) {
    if (!userId) return null;
    try {
        const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
        if (m) return m;
        const pageUsers = page.props.users || page.props.members || [];
        if (Array.isArray(pageUsers)) {
            const p = pageUsers.find((u) => String(u.id) === String(userId));
            if (p) return p;
        }
    } catch (e) {}
    return null;
}

function memberCompanyName(userId, block) {
    try {
        const m = memberById(userId);
        if (m) {
            if (m.company && (m.company.name || m.company.company_name)) return m.company.name || m.company.company_name;
            if (m.company_name) return m.company_name;
            if (m.company_id) return companyNameFromId(m.company_id);
        }
    } catch (e) {}
    try {
        if (block) {
            if (block.company && (block.company.name || block.company.company_name)) return block.company.name || block.company.company_name;
            if (block.company_name) return block.company_name;
            if (block.project_job && block.project_job.client && (block.project_job.client.name || block.project_job.client.client_name))
                return block.project_job.client.name || block.project_job.client.client_name;
            if (block.company_id) return companyNameFromId(block.company_id);
        }
    } catch (e) {}
    return companyNameFromId(null);
}

function memberDepartmentName(userId, block) {
    try {
        const m = memberById(userId);
        if (m) {
            if (m.department && (m.department.name || m.department.department_name)) return m.department.name || m.department.department_name;
            if (m.department_name) return m.department_name;
            if (m.department_id) return departmentNameFromId(m.department_id);
        }
    } catch (e) {}
    try {
        if (block) {
            if (block.department && (block.department.name || block.department.department_name))
                return block.department.name || block.department.department_name;
            if (block.department_name) return block.department_name;
            if (block.department_id) return departmentNameFromId(block.department_id);
        }
    } catch (e) {}
    return departmentNameFromId(null);
}

function formatStart(block) {
    if (!block) return '';
    const date = block.desired_start_date || '';
    return date ? String(date) : '';
}

function formatEnd(block) {
    if (!block) return '';
    const date = block.desired_end_date || '';
    const hh = block.desired_time_hour;
    const mm = block.desired_time_min || '00';
    if (date && hh) return `${date} ${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
    if (date) return date;
    if (hh) return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
    return '';
}

function formatEstimated(block) {
    if (!block || block.estimated_hours === undefined || block.estimated_hours === null || block.estimated_hours === '') return '';
    return String(block.estimated_hours).replace('.0', '') + 'h';
}

function authCompanyName() {
    try {
        const auth = effectiveAuthUser();
        if (auth) {
            if (auth.company && (auth.company.name || auth.company.company_name)) return auth.company.name || auth.company.company_name;
            if (auth.company_name) return auth.company_name;
            if (auth.company_id) return companyNameFromId(auth.company_id);
        }
    } catch (e) {}
    try {
        if (page.props && page.props.company && page.props.company.name) return page.props.company.name;
    } catch (e) {}
    return companyNameFromId(null);
}

function authDepartmentName() {
    try {
        const auth = effectiveAuthUser();
        if (auth) {
            if (auth.department && (auth.department.name || auth.department.department_name))
                return auth.department.name || auth.department.department_name;
            if (auth.department_name) return auth.department_name;
            if (auth.department_id) return departmentNameFromId(auth.department_id);
        }
    } catch (e) {}
    try {
        if (page.props && page.props.department && page.props.department.name) return page.props.department.name;
    } catch (e) {}
    return departmentNameFromId(null);
}

// ── ファイルアナライザー（ブロックごとに独立したインスタンス） ────────────────
// 最大10ブロックまで対応（通常1〜3ブロック）
const MAX_FILE_BLOCKS = 10;
const fileAnalyzers = Array.from({ length: MAX_FILE_BLOCKS }, () => useFileAnalyzer());
function fa(idx) { return fileAnalyzers[Math.min(idx, MAX_FILE_BLOCKS - 1)]; }

const fileInputRefs   = {};
const folderInputRefs = {};

function triggerFileInput(idx)   { fileInputRefs[idx]?.click(); }
function triggerFolderInput(idx) { folderInputRefs[idx]?.click(); }
function onFileInputChange(e, idx) {
    fa(idx).analyzeFiles(e.target.files);
    e.target.value = '';
}
// readEntries は最大100件ずつ返すため全件取得するまで繰り返す
async function readAllEntries(reader) {
    const all = [];
    while (true) {
        const batch = await new Promise((resolve) => reader.readEntries(resolve, () => resolve([])));
        if (batch.length === 0) break;
        all.push(...batch);
    }
    return all;
}

async function onFileDrop(e, idx) {
    const items = e.dataTransfer?.items;

    // DataTransferItems が使えない場合（古いブラウザ）は従来通り
    if (!items || items.length === 0) {
        fa(idx).analyzeFiles(e.dataTransfer.files);
        return;
    }

    // フォルダが含まれているか確認
    const entries = Array.from(items).map(item => item.webkitGetAsEntry?.()).filter(Boolean);
    const hasFolder = entries.some(entry => entry.isDirectory);

    if (!hasFolder) {
        // ファイルのみ → 従来通り
        fa(idx).analyzeFiles(e.dataTransfer.files);
        return;
    }

    // フォルダが含まれる場合：中のファイルを展開
    const collectedFiles = [];
    let hasNestedFolder = false;

    for (const entry of entries) {
        if (entry.isFile) {
            const file = await new Promise(resolve => entry.file(resolve));
            collectedFiles.push(file);
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const children = await readAllEntries(reader);
            for (const child of children) {
                if (child.isDirectory) {
                    hasNestedFolder = true;
                    break;
                }
                const file = await new Promise(resolve => child.file(resolve));
                collectedFiles.push(file);
            }
            if (hasNestedFolder) break;
        }
    }

    if (hasNestedFolder) {
        alert('フォルダ内にサブフォルダが含まれているためアップロードできません。\nフォルダ内のファイルのみを直接ドロップするか、「ファイルを選択」ボタンをご利用ください。');
        return;
    }

    if (collectedFiles.length > 0) {
        fa(idx).analyzeFiles(collectedFiles);
    }
}

// ファイル解析完了後に amounts / size_id を自動更新
fileAnalyzers.forEach((analyzer, idx) => {
    watch(analyzer.summary, (s) => {
        const block = assignments.value[idx];
        if (!block || s.totalFiles === 0) return;
        // すべてのファイルにページ数が取得できた場合のみ 'page' 単位を使う
        // xlsx/html/psd/image など pages=null のファイルが混ざった場合はファイル数に切り替える
        const allHavePages = analyzer.results.value.length > 0 &&
            analyzer.results.value.every(f => f.pages != null);
        block.amounts      = allHavePages ? s.totalPages : s.totalFiles;
        block.amounts_unit = allHavePages ? 'page' : 'file';
        block.file_info    = analyzer.buildFileInfo();

        // size_id 自動検出（案件固定・進行表ロックがない場合のみ）
        if (!projectJobSizeId.value && !block._locked_size) {
            const files = block.file_info?.files ?? [];
            const sizeCounts = {};
            files.forEach((f) => {
                if (f.doc_size) sizeCounts[f.doc_size] = (sizeCounts[f.doc_size] ?? 0) + 1;
            });
            const topSize = Object.entries(sizeCounts).sort((a, b) => b[1] - a[1])[0]?.[0];
            if (topSize) {
                const sizeList = page.props.sizes ?? [];
                const matched =
                    sizeList.find((sz) => sz.name === topSize) ??
                    sizeList.find((sz) => sz.name.startsWith(topSize) || topSize.startsWith(sz.name));
                if (matched) block.size_id = String(matched.id);
            }
        }
    });
});

const saving = ref(false);

// 編集モード判定: assignments[0] に id があれば既存レコードの編集
const isEditMode = computed(() => !!(assignments.value[0]?.id));

// ── 作業日・時間スロット ────────────────────────────────────
const workSlots = ref(
    props.initialWorkSlots.length > 0
        ? props.initialWorkSlots.map(s => ({ ...s }))
        : []
);
const SLOT_HOURS   = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const SLOT_MINUTES = ['00', '15', '30', '45'];

function addWorkSlot() {
    workSlots.value.push({ date: '', startHour: '09', startMinute: '00', endHour: '18', endMinute: '00' });
}
function removeWorkSlot(idx) {
    workSlots.value.splice(idx, 1);
}
function formatSlotDuration(slot) {
    const sh = Number(slot.startHour) * 60 + Number(slot.startMinute);
    const eh = Number(slot.endHour)   * 60 + Number(slot.endMinute);
    const mins = Math.max(0, eh - sh);
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}
function buildWorkSlotsPayload() {
    if (!props.showWorkSlots) return undefined;
    return workSlots.value
        .filter(s => s.date)
        .map(s => ({
            date:        s.date,
            startHour:   String(s.startHour).padStart(2, '0'),
            startMinute: String(s.startMinute).padStart(2, '0'),
            endHour:     String(s.endHour).padStart(2, '0'),
            endMinute:   String(s.endMinute).padStart(2, '0'),
        }));
}

async function save(sendImmediately = true) {
    if (!props.editMode) {
        return;
    }

    // バリデーションエラーをクリア
    clearValidationErrors();

    if (props.mode === 'coordinator') {
        saving.value = true;

        function assembleTitleCoord(a) {
            if (a.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
            const maybe = a.project_job && (a.project_job.title || a.project_job.name) ? a.project_job.title || a.project_job.name : null;
            if (!maybe) return '';
            if (maybe.includes('：')) return maybe.replace(/^.*：/, '').trim();
            return String(maybe).trim();
        }

        const payload = {
            send_immediately: true,
            assignments: assignments.value.map((a) => {
                const rawUserId = a.user_id;
                const isDispatcher = typeof rawUserId === 'string' && rawUserId.startsWith('dp_');
                const dispatcherId = isDispatcher ? Number(rawUserId.replace('dp_', '')) : null;
                const resolvedUserId = isDispatcher ? null : (rawUserId || (effectiveAuthUser() ? effectiveAuthUser().id : null));
                return {
                    title: assembleTitleCoord(a),
                    detail: a.detail || '',
                    user_id: resolvedUserId,
                    proof_dispatcher_id: dispatcherId,
                    sender_id: effectiveAuthUser() ? effectiveAuthUser().id : null,
                    project_job_id: a.project_job_id || null,
                    company_id: a.company_id || null,
                    department_id: a.department_id || null,
                    difficulty_id: a.difficulty_id ? Number(a.difficulty_id) : null,
                    desired_start_date: a.desired_start_date || null,
                    desired_end_date: a.desired_end_date || null,
                    start_time: String(a.start_time_hour || '00').padStart(2, '0') + ':' + String(a.start_time_min || '00').padStart(2, '0'),
                    desired_time: String(a.desired_time_hour || '00').padStart(2, '0') + ':' + String(a.desired_time_min || '00').padStart(2, '0'),
                    estimated_hours: a.estimated_hours || null,
                    work_item_type_id: a.work_item_type_id || null,
                    size_id: a.size_id || null,
                    stage_id: a.stage_id || null,
                    status_id: 1,
                    amounts: typeof a.amounts === 'number' ? a.amounts : Number(a.amounts) || 0,
                    amounts_unit: a.amounts_unit || 'page',
                    file_info: a.file_info ? JSON.stringify(a.file_info) : null,
                    _progress_sheet_id: a._progress_sheet_id ?? null,
                    _row_id: a._row_id ?? null,
                    _col_key: a._col_key ?? null,
                };
            }),
        };

        // work_slots を追加
        const workSlotsData = buildWorkSlotsPayload();
        if (workSlotsData !== undefined) payload.work_slots = workSlotsData;

        // storeOverrideUrl が指定されている場合は、そのURLへ直接 POST して終了
        if (props.storeOverrideUrl && !assignments.value[0]?.id) {
            router.post(props.storeOverrideUrl, payload, {
                onFinish: () => { saving.value = false; },
                onError: handleSaveError,
            });
            return;
        }

        const coordinatorProjectJobId =
            props.projectJob && props.projectJob.id ? props.projectJob.id : payload.assignments[0] ? payload.assignments[0].project_job_id : null;

        if (!coordinatorProjectJobId) {
            alert('プロジェクトが選択されていません。プロジェクトを選択してください。');
            saving.value = false;
            return;
        }

        const existingId = assignments.value[0]?.id ?? null;

        if (existingId) {
            // 既存アサインの更新（PUT）
            const a = assignments.value[0];
            const updatePayload = {
                title: assembleTitleCoord(a),
                detail: a.detail || '',
                user_id: a.user_id || null,
                company_id: a.company_id || null,
                department_id: a.department_id || null,
                difficulty_id: a.difficulty_id ? Number(a.difficulty_id) : null,
                desired_end_date: a.desired_end_date || null,
                desired_time: String(a.desired_time_hour || '00').padStart(2, '0') + ':' + String(a.desired_time_min || '00').padStart(2, '0'),
                estimated_hours: a.estimated_hours || null,
                work_item_type_id: a.work_item_type_id || null,
                size_id: a.size_id || null,
                stage_id: a.stage_id || null,
                status_id: a.status_id || null,
                amounts: typeof a.amounts === 'number' ? a.amounts : Number(a.amounts) || 0,
                amounts_unit: a.amounts_unit || 'page',
                file_info: a.file_info ? JSON.stringify(a.file_info) : null,
                send_immediately: sendImmediately,
            };
            const wsd = buildWorkSlotsPayload();
            if (wsd !== undefined) updatePayload.work_slots = wsd;
            const updateUrl = props.updateOverrideUrl
                ?? route('coordinator.project_jobs.assignments.update', { projectJob: coordinatorProjectJobId, assignment: existingId });
            router.put(
                updateUrl,
                updatePayload,
                {
                    onFinish: () => { saving.value = false; },
                    onError: handleSaveError,
                }
            );
        } else {
            router.post(
                route('coordinator.project_jobs.assignments.store', { projectJob: coordinatorProjectJobId }),
                payload,
                {
                    onFinish: () => { saving.value = false; },
                    onError: handleSaveError,
                }
            );
        }
        return;
    } else {
        // user mode save
        saving.value = true;

        // 開始と終了が同じ場合は保存不可
        if (props.mode === 'user') {
            const sh = String(startTimeHour.value || '').padStart(2, '0');
            const sm = String(startTimeMin.value || '00').padStart(2, '0');
            const eh = String(endTimeHour.value || '').padStart(2, '0');
            const em = String(endTimeMin.value || '00').padStart(2, '0');
            if (sh && eh && `${sh}:${sm}` === `${eh}:${em}`) {
                alert('開始時間と終了時間が同じです。終了時間を開始時間より後に設定してください。');
                saving.value = false;
                return;
            }
        }

        // Check for overlapping events
        if (!proceedWithOverlap.value && props.mode === 'user' && props.editMode) {
            const overlaps = await checkEventOverlaps();
            if (overlaps && overlaps.length > 0) {
                overlappingEvents.value = overlaps;
                showOverlapModal.value = true;
                saving.value = false;
                return;
            }
        }

        try {
            if (assignments.value && assignments.value.length) {
                const a0 = assignments.value[0];
                if (workDate.value) a0.desired_start_date = workDate.value;
                if (startTimeHour.value) {
                    a0.start_time_hour = startTimeHour.value;
                    a0.start_time_min = startTimeMin.value || '00';
                    a0.start_time = String(a0.start_time_hour).padStart(2, '0') + ':' + String(a0.start_time_min).padStart(2, '0');
                }
                if (endTimeHour.value) {
                    a0.desired_time_hour = endTimeHour.value;
                    a0.desired_time_min = endTimeMin.value || '00';
                    a0.desired_time = String(a0.desired_time_hour).padStart(2, '0') + ':' + String(a0.desired_time_min).padStart(2, '0');
                }
            }
        } catch (e) {}

        function assembleTitleUser(a) {
            if (a.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
            const maybe = a.project_job && (a.project_job.title || a.project_job.name) ? a.project_job.title || a.project_job.name : null;
            if (!maybe) return '';
            if (maybe.includes('：')) return maybe.replace(/^.*：/, '').trim();
            return String(maybe).trim();
        }

        const payload = {
            assignments: assignments.value.map((a) => ({
                id: a.id || null,
                title: assembleTitleUser(a),
                detail: a.detail || '',
                user_id: a.user_id || (effectiveAuthUser() ? effectiveAuthUser().id : null),
                sender_id: null,
                project_job_id: a.project_job_id || null,
                company_id: a.company_id || null,
                department_id: a.department_id || null,
                difficulty_id: a.difficulty_id ? Number(a.difficulty_id) : null,
                desired_start_date: a.desired_start_date || null,
                desired_end_date: a.desired_end_date || null,
                start_time: a.start_time_hour
                    ? String(a.start_time_hour).padStart(2, '0') + ':' + String(a.start_time_min || '00').padStart(2, '0')
                    : (a.start_time ?? null),
                desired_time: a.desired_time_hour
                    ? String(a.desired_time_hour).padStart(2, '0') + ':' + String(a.desired_time_min || '00').padStart(2, '0')
                    : (a.desired_time ?? null),
                estimated_hours: a.estimated_hours || null,
                work_item_type_id: a.work_item_type_id || null,
                size_id: a.size_id || null,
                stage_id: a.stage_id || null,
                status_id: props.hideStatus
                    ? props.defaultStatusId !== null && props.defaultStatusId !== undefined
                        ? Number(props.defaultStatusId)
                        : 2
                    : (a.status_id ?? null),
                amounts: typeof a.amounts === 'number' ? a.amounts : Number(a.amounts) || 0,
                amounts_unit: a.amounts_unit || 'page',
                source_assignment_id: a.source_assignment_id || null,
                supersedes_assignment_id: a.supersedes_assignment_id || null,
                file_info: a.file_info ?? null,
                _progress_sheet_id: a._progress_sheet_id ?? null,
                _row_id: a._row_id ?? null,
                _col_key: a._col_key ?? null,
            })),
        };

        try {
            const auth = effectiveAuthUser();
            const allForAuth = payload.assignments.every((x) => String(x.user_id) === String(auth ? auth.id : null));
            const computedProjectJobId =
                props.projectJob && props.projectJob.id ? props.projectJob.id : payload.assignments[0] ? payload.assignments[0].project_job_id : null;
            const firstAssignmentHasId = payload.assignments[0] && payload.assignments[0].id;
            if (firstAssignmentHasId) {
                const assignmentId = payload.assignments[0].id;
                if (allForAuth) {
                    router.patch(
                        route('user.project_jobs.assignments.update', { projectJob: computedProjectJobId, assignment: assignmentId }),
                        payload.assignments[0],
                        {
                            onFinish: () => { saving.value = false; },
                            onError: handleSaveError,
                        }
                    );
                    return;
                } else {
                    router.put(
                        route('coordinator.project_jobs.assignments.update', { projectJob: computedProjectJobId, assignment: assignmentId }),
                        payload.assignments[0],
                        {
                            onFinish: () => { saving.value = false; },
                            onError: handleSaveError,
                        }
                    );
                    return;
                }
            }

            if (allForAuth) {
                if (!computedProjectJobId) {
                    console.error('[AssignmentForm_user] missing projectJob id when attempting user-store', {
                        computedProjectJobId,
                        sampleAssignment: payload.assignments[0],
                    });
                    try {
                        alert('プロジェクトが選択されていません。プロジェクトを選択してください。');
                    } catch (e) {}
                    return;
                }
                router.post(
                    route('user.project_jobs.assignments.store', { projectJob: computedProjectJobId }),
                    payload,
                    {
                        onFinish: () => { saving.value = false; },
                        onError: handleSaveError,
                    }
                );
                return;
            }
        } catch (e) {
            console.warn('[AssignmentForm_user] allForAuth check failed, falling back to coordinator route', e);
        }

        const coordinatorProjectJobId =
            props.projectJob && props.projectJob.id ? props.projectJob.id : payload.assignments[0] ? payload.assignments[0].project_job_id : null;
        if (!coordinatorProjectJobId) {
            console.error('[AssignmentForm_user] missing projectJob id for coordinator-store', { sampleAssignment: payload.assignments[0] });
            try {
                alert('プロジェクトが選択されていません。プロジェクトを選択してください。');
            } catch (e) {}
            saving.value = false;
            return;
        }

        router.post(
            route('coordinator.project_jobs.assignments.store', { projectJob: coordinatorProjectJobId }),
            payload,
            {
                onFinish: () => { saving.value = false; },
                onError: handleSaveError,
            }
        );
    }
}

// Helper functions for overlap modal
function closeOverlapModal() {
    showOverlapModal.value = false;
    overlappingEvents.value = [];
    proceedWithOverlap.value = false;
}

function proceedAnyway() {
    proceedWithOverlap.value = true;
    showOverlapModal.value = false;
    save();
}

// 外部から呼び出し可能なメソッド（カレンダーピッカー連携用）
defineExpose({
    getSelectedUserId: () => assignments.value[0]?.user_id ?? null,
    setSelectedUser: (userId) => {
        const block = assignments.value[0];
        if (!block) return;
        block.user_id = String(userId);
        onUserChange(block);
    },
    addExternalWorkSlot: (slot) => {
        workSlots.value.push({
            date:        slot.date        || '',
            startHour:   String(slot.startHour   || '09').padStart(2, '0'),
            startMinute: String(slot.startMinute || '00').padStart(2, '0'),
            endHour:     String(slot.endHour     || '18').padStart(2, '0'),
            endMinute:   String(slot.endMinute   || '00').padStart(2, '0'),
        });
    },
});
</script>

<style scoped>
/* small styles (none) */
</style>
