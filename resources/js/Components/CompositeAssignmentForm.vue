<template>
    <form @submit.prevent="submit">
        <!-- クライアント・プロジェクト（読み取り専用） -->
        <div class="mb-3">
            <label class="mb-1 block font-semibold">クライアント</label>
            <div class="w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                {{ projectJob?.client?.name ?? '(未設定)' }}
            </div>
        </div>
        <div class="mb-3">
            <label class="mb-1 block font-semibold">プロジェクト名</label>
            <div class="w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                {{ projectJob?.title ?? projectJob?.name ?? '(未設定)' }}
            </div>
        </div>

        <!-- ジョブ名 -->
        <div class="mb-3">
            <label class="mb-1 block font-semibold">ジョブ名</label>
            <input v-model="form.title_suffix" type="text" class="w-full rounded border px-3 py-2" placeholder="例：画像加工・PDF校正" />
        </div>

        <!-- 概要 -->
        <div class="mb-4">
            <label class="mb-1 block font-semibold">概要</label>
            <textarea v-model="form.detail" class="w-full rounded border px-3 py-2" rows="3"></textarea>
        </div>

        <!-- ▼ 作業ファイル情報 -->
        <div class="mb-4 rounded border border-blue-200 bg-blue-50 p-4">
            <h3 class="mb-3 font-semibold text-blue-800">作業ファイル情報</h3>

            <!-- 対応形式の案内 -->
            <details class="mb-2 text-xs text-gray-500">
                <summary class="cursor-pointer hover:text-gray-700">対応ファイル形式を確認</summary>
                <div class="mt-2 rounded border bg-white p-3">
                    <div class="mb-1 font-semibold text-green-700">自動取得できる形式</div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5">
                        <div v-for="s in SUPPORTED_TYPES" :key="s.ext">
                            <span class="font-mono font-semibold">{{ s.ext }}</span>
                            <span class="text-gray-500"> — {{ s.info }}</span>
                        </div>
                    </div>
                    <div class="mt-2 border-t pt-2">
                        <div class="mb-1 font-semibold text-red-600">自動除外される形式（対応外）</div>
                        <div v-for="u in UNSUPPORTED_NOTICE" :key="u.ext" class="text-red-600">
                            <span class="font-mono font-semibold">{{ u.ext }}</span>
                            <span class="text-gray-500"> — {{ u.reason }}</span>
                        </div>
                    </div>
                </div>
            </details>

            <!-- ドロップゾーン -->
            <div
                class="flex min-h-[100px] cursor-pointer flex-col items-center justify-center rounded border-2 border-dashed border-blue-300 bg-white px-4 py-6 text-center transition hover:border-blue-500 hover:bg-blue-50"
                @dragover.prevent
                @drop.prevent="onDrop"
                @click="triggerFileInput"
            >
                <p class="mb-2 text-sm text-gray-600">ここにファイル・フォルダをドラッグ＆ドロップ</p>
                <div class="flex gap-2">
                    <button type="button" class="rounded border border-gray-300 bg-gray-50 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100" @click.stop="triggerFolderInput">
                        フォルダを選択
                    </button>
                    <button type="button" class="rounded border border-gray-300 bg-gray-50 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100" @click.stop="triggerFileInput">
                        ファイルを選択
                    </button>
                </div>
            </div>
            <!-- 隠しinput -->
            <input ref="fileInputRef" type="file" multiple class="hidden" @change="onFileInputChange" />
            <input ref="folderInputRef" type="file" multiple webkitdirectory class="hidden" @change="onFileInputChange" />

            <!-- 対応外ファイル一覧 -->
            <div v-if="rejectedFiles.length > 0" class="mt-3 rounded border border-red-200 bg-red-50 p-3">
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-xs font-semibold text-red-700">自動除外されたファイル（{{ rejectedFiles.length }}件）— ページ数・サイズ取得不可</span>
                    <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="clearRejected">閉じる</button>
                </div>
                <ul class="max-h-40 overflow-y-auto text-xs text-red-700">
                    <li v-for="(f, i) in rejectedFiles" :key="i" class="border-b border-red-100 py-0.5 last:border-0">
                        <span class="font-medium">{{ f.name }}</span>
                        <span class="ml-2 text-red-500">— {{ f.reason }}</span>
                    </li>
                </ul>
            </div>

            <!-- 解析中インジケーター -->
            <div v-if="analyzing" class="mt-3 flex items-center gap-2 text-sm text-blue-600">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                解析中...
            </div>

            <!-- ファイルが1件以上あれば表 -->
            <template v-if="results.length > 0">
                <!-- クリアボタン -->
                <div class="mt-3 flex justify-end">
                    <button type="button" class="text-xs text-red-500 hover:underline" @click="clearFiles">すべて削除</button>
                </div>

                <!-- 種別ごとのテーブル -->
                <div v-for="group in grouped" :key="group.type" class="mt-3">
                    <div class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <span>── {{ group.label }}</span>
                        <span class="text-gray-500">（{{ group.files.length }}ファイル
                            <template v-if="group.totalPages"> / {{ group.totalPages }}ページ</template>
                            / {{ formatSize(group.totalSize) }}）
                        </span>
                    </div>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-100 text-left">
                                <th class="px-2 py-1">ファイル名</th>
                                <template v-if="group.columns === 'page'">
                                    <th class="px-2 py-1">ページ数</th>
                                    <th class="px-2 py-1">ドキュメントサイズ</th>
                                </template>
                                <template v-else-if="group.columns === 'image'">
                                    <th class="px-2 py-1">幅×高さ(px)</th>
                                    <th class="px-2 py-1">カラー</th>
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
                                </template>
                                <template v-else-if="group.columns === 'image'">
                                    <td class="px-2 py-1">{{ file.width && file.height ? `${file.width}×${file.height}` : '-' }}</td>
                                    <td class="px-2 py-1">{{ file.extra ?? '-' }}</td>
                                </template>
                                <td class="px-2 py-1 text-right">
                                    <button type="button" class="text-red-400 hover:text-red-600" @click="removeByGroupIndex(group.type, fi)">✕</button>
                                </td>
                            </tr>
                            <!-- 合計行 -->
                            <tr class="bg-gray-50 font-semibold">
                                <td class="px-2 py-1">合計</td>
                                <template v-if="group.columns === 'page'">
                                    <td class="px-2 py-1">{{ group.totalPages ? group.totalPages + 'p' : '-' }}</td>
                                    <td class="px-2 py-1 text-gray-400">{{ formatSize(group.totalSize) }}</td>
                                </template>
                                <template v-else-if="group.columns === 'image'">
                                    <td class="px-2 py-1">{{ group.files.length }}ファイル</td>
                                    <td class="px-2 py-1 text-gray-400">{{ formatSize(group.totalSize) }}</td>
                                </template>
                                <td class="px-2 py-1"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 全体合計 -->
                <div class="mt-3 rounded border border-blue-200 bg-white px-3 py-2 text-sm font-semibold text-blue-900">
                    合計: {{ summary.totalFiles }}ファイル
                    <template v-if="summary.totalPages"> / {{ summary.totalPages }}ページ</template>
                    / {{ summary.totalSizeLabel }}
                </div>
            </template>
        </div>

        <!-- 割当ユーザー -->
        <div class="mb-3">
            <label class="mb-1 block font-semibold">割当ユーザー</label>
            <select v-model="form.user_id" class="w-full rounded border px-3 py-2">
                <option value="">未指定（自己割当）</option>
                <option v-for="m in members" :key="m.id" :value="m.id">
                    {{ m.is_ghost ? '[テスト] ' : '' }}{{ m.name }}{{ m.assignment_name ? '（' + m.assignment_name + '）' : '' }}
                    {{ m.employment_type === 'proof_dispatcher' ? '【単発派遣】' : ['dispatch','outsource','contract'].includes(m.employment_type) ? '【' + m.employment_type_label + '】' : '' }}
                </option>
            </select>
            <div v-if="selectedMemberBadge" class="mt-1.5 flex items-center gap-1.5 rounded border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs">
                <span class="rounded-full px-2 py-0.5 font-medium" :class="selectedMemberBadge.cls">
                    {{ selectedMemberBadge.label }}
                </span>
                <span class="text-orange-700">このユーザーは{{ selectedMemberBadge.label }}です。</span>
            </div>
        </div>

        <!-- 作業種別・ステージ -->
        <div class="mb-3 grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600">作業種別</label>
                <select v-model="form.work_item_type_id" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">-- 選択 --</option>
                    <option v-for="t in types" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">ステージ（校数）</label>
                <select v-model="form.stage_id" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">-- 選択 --</option>
                    <option v-for="s in stages" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                </select>
            </div>
        </div>

        <!-- 難易度 -->
        <div class="mb-3">
            <label class="mb-1 block font-semibold">難易度</label>
            <select v-model="form.difficulty_id" class="w-full rounded border px-3 py-2">
                <option :value="null">-- 選択 --</option>
                <option v-for="d in difficulties" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
        </div>

        <!-- 締め切り -->
        <div class="mb-3">
            <label class="mb-1 block font-semibold">締め切り</label>
            <div class="flex items-center gap-3">
                <input v-model="form.desired_end_date" type="date" :min="today" class="rounded border px-3 py-2" />
                <select v-model="form.desired_time_hour" class="w-20 rounded border px-3 py-2">
                    <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                </select>
                <select v-model="form.desired_time_min" class="w-20 rounded border px-3 py-2">
                    <option v-for="m in mins" :key="m" :value="m">{{ m }}</option>
                </select>
            </div>
        </div>

        <!-- 見積時間 -->
        <div class="mb-4">
            <label class="mb-1 block font-semibold">見積時間</label>
            <div class="flex items-center gap-2">
                <select v-model="form.estimated_hours" class="w-40 rounded border px-3 py-2">
                    <option value="">未指定</option>
                    <option v-for="opt in estimatedOptions" :key="opt" :value="opt">
                        {{ String(opt).replace('.0', '') }}h
                    </option>
                </select>
                <span class="text-sm text-gray-500">(0.25刻み)</span>
            </div>
        </div>

        <!-- 送信 -->
        <div class="flex gap-2">
            <button type="submit" :disabled="saving" class="rounded bg-green-600 px-5 py-2 text-white hover:bg-green-700 disabled:opacity-50">
                {{ saving ? '送信中...' : '保存して送信' }}
            </button>
            <Link :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })" class="rounded bg-gray-200 px-4 py-2">
                戻る
            </Link>
        </div>
    </form>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useFileAnalyzer, SUPPORTED_TYPES, UNSUPPORTED_NOTICE } from '@/composables/useFileAnalyzer'

const props = defineProps({
    projectJob: Object,
    members:    { type: Array, default: () => [] },
})

const page = usePage()
const types       = computed(() => page.props.types       || [])
const stages      = computed(() => page.props.stages      || [])
const difficulties = computed(() => page.props.difficulties || [])

// ---- フォーム状態 ----
const saving = ref(false)
const form = ref({
    title_suffix:       '',
    detail:             '',
    user_id:            '',
    work_item_type_id:  '',
    stage_id:           '',
    difficulty_id:      null,
    desired_end_date:   '',
    desired_time_hour:  '17',
    desired_time_min:   '00',
    estimated_hours:    '',
})

const today = new Date().toISOString().split('T')[0]
const hours = Array.from({ length: 17 }, (_, i) => String(6 + i).padStart(2, '0'))
const mins  = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55']
const estimatedOptions = Array.from({ length: 32 }, (_, i) => Number(((i + 1) * 0.25).toFixed(2)))

// ---- 雇用形態バッジ ----
const EMPLOYMENT_BADGE = {
    dispatch:  { label: '派遣社員',  cls: 'bg-orange-100 text-orange-700' },
    outsource: { label: '業務委託',  cls: 'bg-purple-100 text-purple-700' },
    contract:  { label: '契約社員',  cls: 'bg-green-100 text-green-700' },
    proof_dispatcher: { label: '単発派遣', cls: 'bg-pink-100 text-pink-700' },
}
const selectedMemberBadge = computed(() => {
    if (!form.value.user_id) return null
    const m = props.members.find(m => String(m.id) === String(form.value.user_id))
    return m ? EMPLOYMENT_BADGE[m.employment_type] ?? null : null
})

// ---- ファイル解析 ----
const { analyzing, results, rejectedFiles, grouped, summary, analyzeFiles, removeByGroupIndex, clearFiles, clearRejected, buildFileInfo, formatSize } = useFileAnalyzer()

const fileInputRef   = ref(null)
const folderInputRef = ref(null)

function triggerFileInput()   { fileInputRef.value?.click() }
function triggerFolderInput() { folderInputRef.value?.click() }
function onFileInputChange(e) { analyzeFiles(e.target.files); e.target.value = '' }
function onDrop(e)            { analyzeFiles(e.dataTransfer.files) }

// ファイル解析完了後に数量を自動セット
watch(summary, (s) => {
    if (s.totalFiles === 0) return
    form.value.amounts      = s.totalPages > 0 ? s.totalPages : s.totalFiles
    form.value.amounts_unit = s.totalPages > 0 ? 'page' : 'file'
})

// ---- 送信 ----
function submit() {
    saving.value = true

    const fileInfo = buildFileInfo()
    const desired_time = form.value.desired_end_date
        ? `${form.value.desired_time_hour}:${form.value.desired_time_min}`
        : null

    router.post(
        route('coordinator.project_jobs.assignments.composite.store', { projectJob: props.projectJob.id }),
        {
            user_id:           form.value.user_id || null,
            title_suffix:      form.value.title_suffix,
            detail:            form.value.detail,
            work_item_type_id: form.value.work_item_type_id || null,
            stage_id:          form.value.stage_id || null,
            difficulty_id:     form.value.difficulty_id || null,
            desired_end_date:  form.value.desired_end_date || null,
            desired_time:      desired_time,
            estimated_hours:   form.value.estimated_hours || null,
            amounts:           form.value.amounts ?? null,
            amounts_unit:      form.value.amounts_unit ?? null,
            file_info:         fileInfo ? JSON.stringify(fileInfo) : null,
        },
        {
            onError: () => { saving.value = false },
            onFinish: () => { saving.value = false },
        }
    )
}
</script>
