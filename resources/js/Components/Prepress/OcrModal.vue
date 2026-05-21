<script setup>
/**
 * OcrModal.vue
 * 伝票OCR読み取り結果モーダル
 *
 * props:
 *   show         Boolean  - 表示/非表示
 *   ocrResult    Object   - OCR解析結果 { jobcode, client_name, title, matched_clients, image_url, tmp_image_path, original_filename }
 *
 * emits:
 *   apply  ({ jobcode, client_name, client_id, title, tmp_image_path, original_filename })  - フォームへ反映
 *   close  ()  - モーダルを閉じる
 */
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    show:      { type: Boolean, default: false },
    ocrResult: { type: Object,  default: () => ({}) },
});

const emit = defineEmits(['apply', 'close']);

// ── 編集可能フィールド ──────────────────────────────────────
const jobcode    = ref('');
const clientName = ref('');
const title      = ref('');
const clientId   = ref('');

// ── クライアント照合 ────────────────────────────────────────
const matchedClients    = ref([]);
const selectedClientIdx = ref(-1); // matchedClients の選択インデックス

// ── クライアント検索（「既存に紐づけ」モード） ──────────────
const searchMode     = ref('none'); // 'none' | 'link' | 'new'
const searchId       = ref('');
const searchName     = ref('');
const searchResults  = ref([]);
const isSearching    = ref(false);
let   searchTimer    = null;

// ── 新規クライアント登録 ────────────────────────────────────
const newClientName  = ref('');
const isCreating     = ref(false);
const createError    = ref('');
const createSuccess  = ref(false);

// ── 部署未登録の確認 ────────────────────────────────────────
const selectedClientInDept = ref(true); // 選択クライアントが自部署に登録済みか
const isAttaching          = ref(false);
const attachError          = ref('');
const attachDismissed      = ref(false); // バナーを「スキップ」で閉じた

// ── サムネイル拡大 ──────────────────────────────────────────
const showLightbox = ref(false);

// ── propsが変わったら各フィールドをリセット ─────────────────
watch(() => props.ocrResult, (val) => {
    jobcode.value    = val.jobcode ?? '';
    title.value      = val.title   ?? '';
    clientId.value   = '';
    matchedClients.value    = val.matched_clients ?? [];
    selectedClientIdx.value = matchedClients.value.length === 1 ? 0 : -1;

    // 1件だけ一致した場合は自動で clientId とDB名をセット
    if (matchedClients.value.length === 1) {
        clientId.value              = matchedClients.value[0].id;
        clientName.value            = matchedClients.value[0].name; // OCR名ではなくDB名
        selectedClientInDept.value  = matchedClients.value[0].in_department ?? true;
    } else {
        clientName.value = val.client_name ?? ''; // マッチなし時のみOCR名を初期表示
        selectedClientInDept.value = true;
    }

    searchMode.value    = 'none';
    searchId.value      = '';
    searchName.value    = '';
    searchResults.value = [];
    newClientName.value = '';
    createError.value   = '';
    createSuccess.value = false;
    attachError.value   = '';
    attachDismissed.value = false;
}, { deep: true, immediate: true });

// ── クライアント候補から選択 ────────────────────────────────
function selectMatched(idx) {
    selectedClientIdx.value = idx;
    const c = matchedClients.value[idx];
    clientId.value              = c.id;
    clientName.value            = c.name; // 常にDB名を使う
    selectedClientInDept.value  = c.in_department ?? true;
    searchMode.value            = 'none';
    attachError.value           = '';
    attachDismissed.value       = false;
}

// ── クライアントを自部署に追加 ─────────────────────────────
async function attachToDepartment() {
    if (!clientId.value) return;
    isAttaching.value = true;
    attachError.value = '';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(route('prepress.ocr.attach_department', { client: clientId.value }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
        if (res.ok) {
            selectedClientInDept.value = true;
        } else {
            const err = await res.json();
            attachError.value = err.error ?? '登録に失敗しました。';
        }
    } catch {
        attachError.value = '通信エラーが発生しました。';
    } finally {
        isAttaching.value = false;
    }
}

// ── 既存に紐づけ: ID直接入力 ───────────────────────────────
async function onSearchIdChange() {
    clientId.value   = '';
    clientName.value = '';
    if (!searchId.value) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        isSearching.value = true;
        try {
            const res = await axios.get(route('prepress.api.clients'), { params: { q: '' } });
            const found = res.data.find(c => String(c.id) === String(searchId.value));
            if (found) {
                clientId.value   = found.id;
                clientName.value = found.name;
                searchResults.value = [found];
            } else {
                searchResults.value = [];
            }
        } finally {
            isSearching.value = false;
        }
    }, 400);
}

// ── 既存に紐づけ: 名前オートコンプリート ────────────────────
function onSearchNameInput() {
    clientId.value = '';
    clearTimeout(searchTimer);
    if (!searchName.value.trim()) {
        searchResults.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        isSearching.value = true;
        try {
            const res = await axios.get(route('prepress.api.clients'), { params: { q: searchName.value } });
            searchResults.value = res.data;
        } finally {
            isSearching.value = false;
        }
    }, 250);
}

function selectSearchResult(client) {
    clientId.value   = client.id;
    clientName.value = client.name;
    searchName.value = client.name;
    searchId.value   = String(client.id);
    searchResults.value = [];
}

// ── 新規クライアント登録 ────────────────────────────────────
async function createNewClient() {
    if (!newClientName.value.trim()) return;
    isCreating.value = true;
    createError.value = '';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(route('leader.clients.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name: newClientName.value.trim(), department_ids: [] }),
        });
        if (res.ok) {
            const data = await res.json();
            if (data.id) {
                clientId.value      = data.id;
                clientName.value    = data.name;
                createSuccess.value = true;
                searchMode.value    = 'none';
            } else {
                createError.value = '登録に失敗しました。';
            }
        } else {
            const err = await res.json();
            createError.value = err.message ?? '登録に失敗しました。';
        }
    } catch {
        createError.value = '通信エラーが発生しました。';
    } finally {
        isCreating.value = false;
    }
}

// ── 反映 ────────────────────────────────────────────────────
function apply() {
    emit('apply', {
        jobcode:          jobcode.value,
        client_name:      clientName.value,
        client_id:        clientId.value,
        title:            title.value,
        tmp_image_path:   props.ocrResult.tmp_image_path   ?? '',
        original_filename: props.ocrResult.original_filename ?? '',
        image_url:        props.ocrResult.image_url ?? '',
    });
}

const clientStatus = computed(() => {
    if (clientId.value) return 'selected';        // IDが確定
    if (matchedClients.value.length > 0) return 'candidates'; // 候補あり
    return 'not_found';                            // 未登録
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="$emit('close')"
            >
                <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-2xl">

                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h3 class="text-lg font-bold text-gray-800">🔍 伝票OCR読み取り結果</h3>
                        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                    </div>

                    <div class="p-6 space-y-5">

                        <!-- サムネイル + 基本フィールド -->
                        <div class="flex gap-4">
                            <!-- サムネイル -->
                            <div class="flex-shrink-0">
                                <img
                                    v-if="ocrResult.image_url"
                                    :src="ocrResult.image_url"
                                    alt="伝票画像"
                                    class="h-32 w-24 cursor-pointer rounded border object-cover shadow hover:opacity-80 transition"
                                    @click="showLightbox = true"
                                />
                                <div v-else class="flex h-32 w-24 items-center justify-center rounded border bg-gray-100 text-xs text-gray-400">
                                    画像なし
                                </div>
                                <p class="mt-1 text-center text-xs text-gray-400">クリックで拡大</p>
                            </div>

                            <!-- 伝票番号・品目名 -->
                            <div class="flex-1 space-y-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">受注番号</label>
                                    <input
                                        v-model="jobcode"
                                        type="text"
                                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:outline-none"
                                        placeholder="受注番号"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">品目名</label>
                                    <input
                                        v-model="title"
                                        type="text"
                                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:outline-none"
                                        placeholder="品目名"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- クライアント -->
                        <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">クライアント</p>
                            <p class="text-sm text-gray-700">
                                OCR読み取り: <span class="font-medium text-gray-900">「{{ ocrResult.client_name || '（読み取れませんでした）' }}」</span>
                            </p>

                            <!-- 確定済み表示 -->
                            <div v-if="clientId" class="flex items-center gap-2 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                                <span>✓ 選択済み: <strong>{{ clientName }}</strong>（ID: {{ clientId }}）</span>
                                <button
                                    type="button"
                                    class="ml-auto text-xs text-green-500 underline hover:text-green-700"
                                    @click="clientId = ''; selectedClientIdx = -1; selectedClientInDept = true; attachDismissed = false;"
                                >変更</button>
                            </div>

                            <!-- 部署未登録の確認バナー -->
                            <div
                                v-if="clientId && !selectedClientInDept && !attachDismissed"
                                class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 space-y-2"
                            >
                                <p>⚠ このクライアントは自部署に未登録です。部署に追加しますか？</p>
                                <p class="text-xs text-amber-600">追加すると、自部署のクライアント一覧に表示されるようになります。</p>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600 disabled:opacity-50"
                                        :disabled="isAttaching"
                                        @click="attachToDepartment"
                                    >{{ isAttaching ? '登録中...' : '追加する' }}</button>
                                    <button
                                        type="button"
                                        class="rounded border border-amber-400 px-3 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100"
                                        @click="attachDismissed = true"
                                    >スキップ</button>
                                </div>
                                <p v-if="attachError" class="text-xs text-red-600">{{ attachError }}</p>
                                <p v-if="selectedClientInDept && !isAttaching" class="text-xs text-green-600">✓ 部署に追加しました。</p>
                            </div>

                            <!-- 候補リスト -->
                            <template v-else-if="clientStatus === 'candidates'">
                                <p class="text-xs text-gray-500">DBに一致する可能性があるクライアントが見つかりました。選択してください：</p>
                                <div class="space-y-1">
                                    <button
                                        v-for="(c, idx) in matchedClients"
                                        :key="c.id"
                                        type="button"
                                        class="w-full flex items-center justify-between rounded border px-3 py-2 text-sm transition"
                                        :class="selectedClientIdx === idx
                                            ? 'border-green-500 bg-green-50 text-green-800'
                                            : 'border-gray-200 hover:border-green-400 hover:bg-green-50'"
                                        @click="selectMatched(idx)"
                                    >
                                        <span class="font-medium">{{ c.name }}</span>
                                        <span class="text-xs text-gray-400">
                                            <span v-if="c.client_code" class="font-mono mr-1">{{ c.client_code }}</span>
                                            <span v-if="c.is_dormant" class="text-red-500">休眠中</span>
                                        </span>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400">一致するものがない場合 ↓</p>
                            </template>

                            <!-- 未登録 -->
                            <template v-else-if="clientStatus === 'not_found'">
                                <p class="text-xs text-amber-600">⚠ DBにクライアントが見つかりませんでした。</p>
                            </template>

                            <!-- 操作ボタン（IDが未確定の場合） -->
                            <div v-if="!clientId" class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded border px-3 py-1.5 text-xs font-medium transition"
                                    :class="searchMode === 'link' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600 hover:border-blue-400'"
                                    @click="searchMode = searchMode === 'link' ? 'none' : 'link'"
                                >
                                    🔗 既存クライアントに紐づける
                                </button>
                                <button
                                    type="button"
                                    class="rounded border px-3 py-1.5 text-xs font-medium transition"
                                    :class="searchMode === 'new' ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-gray-300 text-gray-600 hover:border-purple-400'"
                                    @click="searchMode = searchMode === 'new' ? 'none' : 'new'"
                                >
                                    ➕ 新規クライアントを登録
                                </button>
                                <button
                                    type="button"
                                    class="rounded border border-gray-300 px-3 py-1.5 text-xs text-gray-500 hover:border-gray-400 transition"
                                    @click="clientName = ocrResult.client_name ?? ''; clientId = '';"
                                >
                                    名前のみ使用（IDなし）
                                </button>
                            </div>

                            <!-- 既存に紐づけ パネル -->
                            <div v-if="searchMode === 'link'" class="rounded border border-blue-200 bg-blue-50 p-3 space-y-2">
                                <p class="text-xs font-semibold text-blue-700">既存クライアントを検索して紐づける</p>
                                <div class="flex gap-2">
                                    <div class="flex items-center gap-1">
                                        <label class="text-xs text-gray-500 whitespace-nowrap">ID:</label>
                                        <input
                                            v-model="searchId"
                                            type="number"
                                            placeholder="ID"
                                            class="w-20 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none"
                                            @input="onSearchIdChange"
                                        />
                                    </div>
                                    <div class="relative flex-1">
                                        <input
                                            v-model="searchName"
                                            type="text"
                                            placeholder="名前を入力（オートコンプリート）"
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none"
                                            @input="onSearchNameInput"
                                        />
                                        <div
                                            v-if="searchResults.length > 0"
                                            class="absolute top-full z-50 mt-1 w-full overflow-y-auto rounded border border-gray-300 bg-white shadow-lg max-h-40"
                                        >
                                            <div
                                                v-for="c in searchResults"
                                                :key="c.id"
                                                class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                                                @mousedown.prevent="selectSearchResult(c)"
                                            >
                                                <span class="font-medium">{{ c.name }}</span>
                                                <span class="ml-2 text-xs text-gray-400">ID: {{ c.id }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="isSearching" class="text-xs text-blue-500">検索中...</p>
                                <p v-if="clientId" class="text-xs text-green-600">✓ 選択: {{ clientName }}（ID: {{ clientId }}）</p>
                            </div>

                            <!-- 新規登録 パネル -->
                            <div v-if="searchMode === 'new'" class="rounded border border-purple-200 bg-purple-50 p-3 space-y-2">
                                <p class="text-xs font-semibold text-purple-700">新規クライアントを登録する</p>
                                <div class="flex gap-2">
                                    <input
                                        v-model="newClientName"
                                        type="text"
                                        placeholder="クライアント名"
                                        class="flex-1 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-purple-400 focus:outline-none"
                                        @keydown.enter.prevent="createNewClient"
                                    />
                                    <button
                                        type="button"
                                        class="rounded bg-purple-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-purple-700 disabled:opacity-50 transition"
                                        :disabled="isCreating || !newClientName.trim()"
                                        @click="createNewClient"
                                    >
                                        {{ isCreating ? '登録中...' : '登録' }}
                                    </button>
                                </div>
                                <p v-if="createError" class="text-xs text-red-600">{{ createError }}</p>
                                <p v-if="createSuccess" class="text-xs text-green-600">✓ 登録完了: {{ clientName }}（ID: {{ clientId }}）</p>
                                <p class="text-xs text-purple-500">※ 部署の紐づけは後から「クライアント管理」で設定できます。</p>
                            </div>
                        </div>

                    </div>

                    <!-- フッター -->
                    <div class="flex justify-end gap-3 border-t px-6 py-4">
                        <button
                            type="button"
                            class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition"
                            @click="$emit('close')"
                        >
                            キャンセル
                        </button>
                        <button
                            type="button"
                            class="rounded bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700 transition"
                            @click="apply"
                        >
                            この内容でフォームに反映
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- サムネイル拡大ライトボックス -->
        <Transition name="modal-fade">
            <div
                v-if="showLightbox && ocrResult.image_url"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 cursor-zoom-out"
                @click="showLightbox = false"
            >
                <img
                    :src="ocrResult.image_url"
                    alt="伝票画像（拡大）"
                    class="max-h-[90vh] max-w-full rounded shadow-2xl"
                    @click.stop
                />
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
</style>
