<template>
    <AppLayout title="案件編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('coordinator.project_jobs.index')"
                      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← 案件一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">案件編集</h2>
            </div>
        </template>
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">伝票番号</label>
                    <input v-model="form.jobcode" type="text" class="w-full rounded border px-3 py-2" />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">案件タイトル</label>
                    <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2" required />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">リーダー（代表Coordinator）</label>
                    <select v-model="form.user_id" class="w-full rounded border px-3 py-2" required>
                        <option value="" disabled>選択してください</option>
                        <option v-for="c in props.coordinatorCandidates" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">サブCoordinator（複数可）</label>
                    <div class="rounded border px-3 py-2 max-h-40 overflow-y-auto space-y-1">
                        <template v-for="c in subCandidates" :key="c.id">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" :value="c.id" v-model="form.sub_coordinator_ids" class="rounded" />
                                <span>{{ c.name }}</span>
                            </label>
                        </template>
                        <p v-if="subCandidates.length === 0" class="text-sm text-gray-400">候補なし</p>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">クライアント</label>
                    <div class="flex items-center gap-2">
                        ID:<input v-model="form.client_id" type="number" class="w-16 rounded border bg-gray-100 px-3 py-2" readonly />
                        <input v-model="form.client_name" type="text" class="w-60 rounded border bg-gray-100 px-3 py-2" readonly />
                    </div>
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
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-3 py-2" rows="3"></textarea>
                </div>

                <!-- 伝票画像 -->
                <div class="mb-6">
                    <label class="mb-2 block font-semibold">作業ファイル情報（伝票画像）</label>

                    <!-- 既存画像あり -->
                    <div v-if="currentImageUrl">
                        <div class="relative inline-block">
                            <img
                                :src="currentImageUrl"
                                :alt="props.job.original_filename ?? '伝票画像'"
                                class="h-40 w-auto cursor-pointer rounded-lg border border-gray-200 object-contain shadow-sm"
                                @click="showLightbox = true"
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="max-w-xs truncate text-xs text-gray-500">{{ props.job.original_filename }}</span>
                            <button type="button" class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50" @click="showLightbox = true">🔍 拡大</button>
                            <label class="cursor-pointer rounded border border-blue-400 px-2 py-0.5 text-xs text-blue-600 hover:bg-blue-50">
                                📁 差し替え
                                <input type="file" accept="image/*,.pdf" class="hidden" @change="onVoucherFileChange" />
                            </label>
                            <button type="button" class="rounded border border-red-400 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50" @click="confirmDeleteVoucherImage">✕ 削除</button>
                        </div>
                        <p v-if="voucherError" class="mt-1 text-xs text-red-600">{{ voucherError }}</p>
                    </div>

                    <!-- 新規プレビュー（ローカル選択後・まだ未保存） -->
                    <div v-else-if="previewUrl">
                        <div class="relative inline-block">
                            <img :src="previewUrl" :alt="previewName" class="h-40 w-auto cursor-pointer rounded-lg border border-gray-200 object-contain shadow-sm" @click="showLightbox = true" />
                            <button type="button" class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs text-white shadow hover:bg-red-600" @click="removePreview">✕</button>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="max-w-xs truncate text-xs text-gray-500">{{ previewName }}</span>
                            <button type="button" class="rounded border border-green-600 px-3 py-1 text-xs text-green-700 hover:bg-green-50" :disabled="voucherUploading" @click="uploadPendingFile">
                                {{ voucherUploading ? 'アップロード中...' : '💾 保存する' }}
                            </button>
                            <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="showLightbox = true">🔍 拡大</button>
                        </div>
                        <p v-if="voucherError" class="mt-1 text-xs text-red-600">{{ voucherError }}</p>
                    </div>

                    <!-- 画像なし -->
                    <div v-else>
                        <div
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
                        <div class="mt-3 flex flex-wrap gap-2">
                            <label class="cursor-pointer rounded-lg border border-green-700 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">
                                📁 フォルダから選ぶ
                                <input type="file" accept="image/*,.pdf" class="hidden" @change="onFileInputChange" />
                            </label>
                            <label v-if="isMobile" class="cursor-pointer rounded-lg border border-gray-400 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                📷 カメラ画像を取り込む
                                <input type="file" accept="image/*" capture="environment" class="hidden" @change="onFileInputChange" />
                            </label>
                        </div>
                        <p v-if="voucherError" class="mt-1 text-xs text-red-600">{{ voucherError }}</p>
                    </div>
                </div>

                <!-- スケジュール設定 -->
                <div class="mb-4">
                    <h3 class="mb-1 font-semibold">スケジュール設定</h3>
                    <div class="flex items-center gap-4">
                        <div
                            :class="[
                                'status-box w-32 rounded px-4 py-2',
                                form.schedule ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500',
                            ]"
                        >
                            {{ form.schedule ? '決定済み' : '未設定' }}
                        </div>
                        <button type="button" class="rounded bg-blue-100 px-4 py-2 text-blue-700" @click="goSchedule">スケジュール設定</button>
                    </div>
                </div>
                <!-- メンバー選定 -->
                <div class="mb-4">
                    <h3 class="mb-1 font-semibold">メンバー選定</h3>
                    <div class="flex items-center gap-4">
                        <div
                            class="status-box w-32 rounded px-4 py-2"
                            :class="form.teammember ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ form.teammember ? '決定済み' : '未設定' }}
                        </div>
                        <button type="button" class="rounded bg-green-100 px-4 py-2 text-green-700" @click="goProjectTeammember">
                            チームメンバー設定
                        </button>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <Link :href="route('coordinator.project_jobs.index')"
                          class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                    >キャンセル</Link>
                    <button type="submit" class="rounded bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700">更新</button>
                </div>
                <div v-if="props.job.completed" class="mt-3 flex justify-end gap-3">
                    <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800">完了済み</span>
                    <button
                        type="button"
                        class="rounded bg-orange-500 px-4 py-2 text-sm text-white hover:bg-orange-600"
                        :disabled="uncompleting"
                        @click="uncomplete"
                    >
                        {{ uncompleting ? '処理中...' : '未完了にする' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- 画像拡大ライトボックス -->
        <Teleport to="body">
            <div
                v-if="showLightbox && (currentImageUrl || previewUrl)"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
                @click.self="showLightbox = false"
            >
                <div class="relative max-h-[90vh] max-w-[90vw]">
                    <img :src="currentImageUrl || previewUrl" :alt="previewName || props.job.original_filename || '伝票画像'" class="max-h-[85vh] max-w-[88vw] rounded-lg object-contain" />
                    <button type="button" class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-800 shadow-md hover:bg-gray-100" @click="showLightbox = false">✕</button>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const isMobile = computed(() => {
    if (typeof navigator === 'undefined') return false;
    return /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
});

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
const props = defineProps({
    job: Object,
    coordinatorCandidates: { type: Array, default: () => [] },
    sizes: { type: Array, default: () => [] },
});

// ── 伝票画像 ──────────────────────────────────────────────────────────────────
// サーバーから渡された既存の画像 URL（router.reload で更新される）
const currentImageUrl = ref(props.job.image_url ?? null);

// ローカル選択中のファイル（まだ未保存）
const previewUrl      = ref(null);
const previewName     = ref('');
const pendingFile     = ref(null);
const isDragging      = ref(false);
const showLightbox    = ref(false);
const voucherUploading = ref(false);
const voucherError    = ref('');

// router.reload で props が更新されたとき currentImageUrl を同期
watch(() => props.job.image_url, (url) => {
    currentImageUrl.value = url ?? null;
    // アップロード完了後はプレビューをクリア
    previewUrl.value  = null;
    previewName.value = '';
    pendingFile.value = null;
});

function selectFile(file) {
    if (!file) return;
    pendingFile.value = file;
    previewName.value = file.name;
    voucherError.value = '';
    const reader = new FileReader();
    reader.onload = (e) => { previewUrl.value = e.target.result; };
    reader.readAsDataURL(file);
}

function onFileInputChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (file) selectFile(file);
}

function onVoucherFileChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    // 既存画像の差し替え：即アップロード
    uploadFile(file);
}

function onDropZoneDrop(e) {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) selectFile(file);
}

function removePreview() {
    previewUrl.value  = null;
    previewName.value = '';
    pendingFile.value = null;
    voucherError.value = '';
}

function uploadPendingFile() {
    if (!pendingFile.value) return;
    uploadFile(pendingFile.value);
}

function uploadFile(file) {
    voucherUploading.value = true;
    voucherError.value = '';
    const fd = new FormData();
    fd.append('image', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    fetch(route('coordinator.project_jobs.image.store', { projectJob: props.job.id }), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: fd,
    }).then((res) => {
        if (!res.ok) throw new Error('upload failed');
        router.reload({ preserveScroll: true });
    }).catch(() => {
        voucherError.value = '画像のアップロードに失敗しました。';
    }).finally(() => {
        voucherUploading.value = false;
    });
}

function confirmDeleteVoucherImage() {
    if (!confirm('伝票画像を削除しますか？')) return;
    router.delete(route('coordinator.project_jobs.image.destroy', { projectJob: props.job.id }), {
        preserveScroll: true,
    });
}
function decodeField(val, fallback = '') {
    if (val === null || val === undefined) return fallback;
    // If it's already an object (from model casting), return it
    if (typeof val === 'object') return val;
    // If it's a string, it might be JSON or plain text
    if (typeof val === 'string') {
        // Try parsing JSON; if it parses to an object, return it
        try {
            const parsed = JSON.parse(val);
            if (parsed && typeof parsed === 'object') return parsed;
        } catch (e) {
            // not JSON, return the raw string
            return val;
        }
        // If parse succeeded but wasn't an object, fall back to raw string
        return val;
    }
    return fallback;
}
// prepare decoded fields
const decodedDetail = decodeField(props.job.detail, '');
const decodedTeammember = decodeField(props.job.teammember, null);
const decodedSchedule = decodeField(props.job.schedule, null);
const form = useForm({
    jobcode: props.job.jobcode || '',
    title: props.job.title || props.job.name || '',
    user_id: props.job.user_id || '',
    sub_coordinator_ids: props.job.sub_coordinator_ids || [],
    client_id: props.job.client_id || '',
    client_name: props.job.client?.name || '',
    size_id: props.job.size_id || '',
    page_count: props.job.page_count || '',
    // support both { "text": "..." } JSON and plain text
    detail:
        decodedDetail && typeof decodedDetail === 'object' && 'text' in decodedDetail
            ? decodedDetail.text
            : typeof decodedDetail === 'string'
              ? decodedDetail
              : '',
    teammember: decodedTeammember || null,
    schedule: decodedSchedule || null,
});
// リーダーとして選択中のユーザーを除いたサブCo候補
const subCandidates = computed(() =>
    props.coordinatorCandidates.filter((c) => c.id !== form.user_id),
);

function submit() {
    form.put(route('coordinator.project_jobs.update', { projectJob: props.job.id }));
}

const uncompleting = ref(false);
async function uncomplete() {
    if (!confirm('この案件を未完了に戻しますか？')) return;
    uncompleting.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const url = route('coordinator.project_jobs.uncomplete', { projectJob: props.job.id });
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            router.visit(route('coordinator.project_jobs.edit', { projectJob: props.job.id }), { preserveState: false });
        } else {
            alert('未完了への変更に失敗しました。');
        }
    } catch {
        alert('未完了への変更に失敗しました。');
    } finally {
        uncompleting.value = false;
    }
}
function goSchedule() {
    // プロジェクトIDの存在確認とエラーハンドリングを追加
    if (!props.job?.id) {
        alert('プロジェクトIDが取得できません');
        return;
    }
    
    try {
        router.visit(route('coordinator.project_schedules.calendar', { project_job_id: props.job.id }));
    } catch (error) {
        console.error('スケジュール画面への遷移でエラーが発生しました:', error);
        alert('スケジュール画面への遷移でエラーが発生しました');
    }
}
function goProjectTeammember() {
    // If this job already has teammember info, pass selected_user_ids so the Create page
    // pre-selects existing members. Support several shapes for form.teammember.
    try {
        const selected = form.teammember;
        const pid = props.job.id;
        const base = route('coordinator.project_team_members.create');
        let ids = [];
        if (Array.isArray(selected)) {
            ids = selected
                .map((s) => {
                    if (!s) return null;
                    if (typeof s === 'object') return s.user ? s.user.id || s.id || null : s.id || null;
                    return s;
                })
                .filter(Boolean);
        } else if (selected && typeof selected === 'object') {
            // common shapes: { users: [{ user: { id } }, ... ] } or { user: { id } }
            if (Array.isArray(selected.users)) {
                ids = selected.users.map((u) => (u && u.user ? u.user.id || u.id || null : u.id || null)).filter(Boolean);
            } else if (selected.user && selected.user.id) {
                ids = [selected.user.id];
            }
        }

        if (ids.length) {
            const url = `${base}?project_job_id=${encodeURIComponent(pid)}&selected_user_ids=${encodeURIComponent(ids.join(','))}`;
            router.visit(url);
            return;
        }
    } catch (e) {
        // fall through to default navigation
    }

    router.visit(route('coordinator.project_team_members.create', { projectJob: props.job.id }));
}
</script>

<style scoped></style>
