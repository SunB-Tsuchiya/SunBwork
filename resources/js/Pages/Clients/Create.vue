<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const DEPT_COLOR_PALETTE = [
    'bg-blue-100 text-blue-700',
    'bg-green-100 text-green-700',
    'bg-purple-100 text-purple-700',
    'bg-orange-100 text-orange-700',
    'bg-pink-100 text-pink-700',
    'bg-yellow-100 text-yellow-700',
];
function deptColor(dept) {
    return DEPT_COLOR_PALETTE[(dept.id - 1) % DEPT_COLOR_PALETTE.length];
}

const props = defineProps({
    departments: { type: Array, default: () => [] },
});

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    if (['admin', 'superadmin'].includes(role)) return 'admin';
    if (['coordinator', 'clerk'].includes(role)) return 'coordinator';
    return 'leader';
});
const form = useForm({
    name:           '',
    client_code:    '',
    detail:         '',
    department_ids: [],
});

// ===== 重複チェック =====
// type: 'no_code_same_name' | 'diff_code_same_name' | 'same_code_diff_name' | null
const duplicateModalType    = ref(null);
const duplicateModalClients = ref([]);
const isCheckingDuplicate   = ref(false);

// ===== 他社共有確認モーダル =====
const shareModalClient = ref(null); // { id, name, client_code, companies: [{id, name}] }
const isSharingToCompany = ref(false);
const shareDeptIds = ref([]);

async function submit() {
    if (!form.name.trim()) return;

    isCheckingDuplicate.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(route(`${routePrefix.value}.clients.check_duplicate`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name: form.name, client_code: form.client_code || null }),
        });
        if (res.status === 419) {
            // CSRF タイムアウト → ページリロードして新鮮なトークンを取得
            window.location.reload();
            return;
        }
        if (res.ok) {
            const data = await res.json();

            // 最優先: 他社に同コードが存在 → 共有確認モーダル
            if (data.other_company_match?.length > 0) {
                shareModalClient.value = data.other_company_match[0];
                return;
            }
            // 優先順位: same_code_diff_name → no_code_same_name → diff_code_same_name
            if (data.same_code_diff_name?.length > 0) {
                duplicateModalType.value    = 'same_code_diff_name';
                duplicateModalClients.value = data.same_code_diff_name;
                return;
            }
            if (data.no_code_same_name?.length > 0) {
                duplicateModalType.value    = 'no_code_same_name';
                duplicateModalClients.value = data.no_code_same_name;
                return;
            }
            if (data.diff_code_same_name?.length > 0) {
                duplicateModalType.value    = 'diff_code_same_name';
                duplicateModalClients.value = data.diff_code_same_name;
                return;
            }
        }
    } catch {
        // チェック失敗時はそのまま保存続行
    } finally {
        isCheckingDuplicate.value = false;
    }

    doSubmit();
}

function doSubmit() {
    duplicateModalType.value = null;
    form.post(route(`${routePrefix.value}.clients.store`));
}

function closeModal() {
    duplicateModalType.value = null;
}

// 他社クライアントを自社に共有登録する（選択した部署も紐付け）
function shareToMyCompany() {
    if (!shareModalClient.value) return;
    isSharingToCompany.value = true;
    router.post(
        route(`${routePrefix.value}.clients.share_to_my_company`, { client: shareModalClient.value.id }),
        { department_ids: shareDeptIds.value },
        { onFinish: () => { isSharingToCompany.value = false; } },
    );
}

function closeShareModal() {
    form.client_code = '';
    shareModalClient.value = null;
    shareDeptIds.value = [];
}
</script>

<template>
    <AppLayout title="クライアント新規作成">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">クライアント新規作成</h2>
                <Link :href="route(`${routePrefix}.clients.index`)" class="text-gray-600 hover:text-gray-900">← 一覧に戻る</Link>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- CSV一括登録 -->
            <div class="mb-6">
                <h3 class="mb-2 text-base font-medium text-orange-800">CSV一括登録</h3>
                <p class="mb-3 text-sm text-orange-700">CSVファイルを使用して複数のクライアントを一度に登録できます。</p>
                <Link
                    :href="route(`${routePrefix}.clients.csv.upload`)"
                    class="inline-flex items-center rounded-md border border-transparent bg-orange-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-orange-700 focus:outline-none"
                >
                    📄 CSVファイルをアップロード
                </Link>
            </div>

            <div class="relative mb-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-white px-2 text-gray-500">または個別に登録</span>
                </div>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block">名前</label>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-2 py-1" />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Client ID
                        <span class="ml-1 text-xs font-normal text-gray-400">（任意・ユニークコード）</span>
                    </label>
                    <input
                        v-model="form.client_code"
                        type="text"
                        maxlength="64"
                        placeholder="例: ABC-001"
                        class="w-full rounded border px-2 py-1 font-mono text-sm"
                    />
                    <p v-if="form.errors.client_code" class="mt-1 text-xs text-red-600">{{ form.errors.client_code }}</p>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-2 py-1"></textarea>
                </div>

                <!-- 部署（全ロール共通：全部署から複数選択） -->
                <div class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700">部署 <span class="text-xs text-gray-400">（複数選択可）</span></label>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="dept in props.departments"
                            :key="dept.id"
                            class="flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors"
                            :class="form.department_ids.includes(dept.id)
                                ? `${deptColor(dept)} border-transparent font-medium`
                                : 'border-gray-300 text-gray-500 hover:border-gray-400'"
                        >
                            <input type="checkbox" :value="dept.id" v-model="form.department_ids" class="hidden" />
                            {{ dept.name }}
                        </label>
                    </div>
                    <p v-if="form.errors.department_ids" class="mt-1 text-xs text-red-600">{{ form.errors.department_ids }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="isCheckingDuplicate || form.processing"
                    class="flex items-center gap-2 rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700 disabled:opacity-60"
                >
                    <svg v-if="isCheckingDuplicate" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    登録
                </button>
            </form>
        </div>
    </AppLayout>

    <!-- 他社共有確認モーダル -->
    <Teleport to="body">
        <div v-if="shareModalClient" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="closeShareModal" />
            <div class="relative z-10 w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="p-6">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.935-2.186 2.25 2.25 0 0 0-3.935 2.186Z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">他社に登録済みのクライアントです</h3>
                    </div>
                    <div class="mb-5 space-y-3">
                        <p class="text-sm text-gray-700">
                            Client ID <strong class="font-mono text-gray-900">「{{ shareModalClient.client_code }}」</strong> は
                            <strong class="text-gray-900">{{ shareModalClient.name }}</strong> として
                            <span class="font-medium text-green-700">{{ shareModalClient.companies.map(c => c.name).join('・') }}</span>
                            に登録されています。
                        </p>
                        <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">
                            新規作成せず、このクライアントを自社にも共有して使用しますか？
                        </div>
                        <!-- 部署選択（任意） -->
                        <div v-if="props.departments.length > 0">
                            <p class="mb-2 text-xs font-medium text-gray-600">紐付ける部署を選択（任意）</p>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="dept in props.departments"
                                    :key="dept.id"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors"
                                    :class="shareDeptIds.includes(dept.id)
                                        ? `${deptColor(dept)} border-transparent font-medium`
                                        : 'border-gray-300 text-gray-500 hover:border-gray-400'"
                                >
                                    <input type="checkbox" :value="dept.id" v-model="shareDeptIds" class="hidden" />
                                    {{ shareDeptIds.includes(dept.id) ? '●' : '○' }} {{ dept.name }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <button
                            type="button"
                            :disabled="isSharingToCompany"
                            class="rounded bg-green-600 px-4 py-2 font-bold text-white hover:bg-green-700 disabled:opacity-60"
                            @click="shareToMyCompany"
                        >
                            {{ isSharingToCompany ? '処理中…' : '共有する' }}
                        </button>
                        <button
                            type="button"
                            class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300"
                            @click="closeShareModal"
                        >
                            キャンセル（Client ID を変更する）
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- 重複チェックモーダル -->
    <Teleport to="body">
        <div v-if="duplicateModalType" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="closeModal" />

            <div class="relative z-10 w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="p-6">

                    <!-- ===== パターン1: same_code_diff_name ===== -->
                    <!-- 同じ Client ID で名前が違う → アラート（ブロック） -->
                    <template v-if="duplicateModalType === 'same_code_diff_name'">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">同じ Client ID が使用中です</h3>
                        </div>
                        <div class="mb-5 space-y-3">
                            <p class="text-sm text-gray-700">
                                入力した Client ID <strong class="font-mono text-gray-900">「{{ form.client_code }}」</strong> はすでに別のクライアントに登録されています。
                            </p>
                            <div class="rounded-md bg-red-50 p-3">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wider text-red-700">同じ Client ID を持つクライアント</p>
                                <ul class="space-y-1">
                                    <li v-for="c in duplicateModalClients" :key="c.id" class="flex items-center gap-2 text-sm text-gray-800">
                                        <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-red-500" />
                                        <span class="font-medium">{{ c.name }}</span>
                                        <span class="font-mono text-xs text-gray-400">{{ c.client_code }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p class="text-sm font-medium text-red-600">Client ID を変更してから再度登録してください。</p>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300" @click="closeModal">
                                閉じる（Client ID を変更する）
                            </button>
                        </div>
                    </template>

                    <!-- ===== パターン2: no_code_same_name ===== -->
                    <!-- Client ID 未設定で名前が一致 → 警告（ブロック） -->
                    <template v-else-if="duplicateModalType === 'no_code_same_name'">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">類似クライアントが見つかりました</h3>
                        </div>
                        <div class="mb-5 space-y-3">
                            <p class="text-sm text-gray-700">
                                入力した名前 <strong class="text-gray-900">「{{ form.name }}」</strong> と似たクライアントが既に登録されています。
                                Client ID が設定されていないため、同一クライアントの可能性があります。
                            </p>
                            <div class="rounded-md bg-yellow-50 p-3">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wider text-yellow-700">既存の類似クライアント</p>
                                <ul class="space-y-1">
                                    <li v-for="c in duplicateModalClients" :key="c.id" class="flex items-center gap-2 text-sm text-gray-800">
                                        <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-yellow-500" />
                                        <span class="font-medium">{{ c.name }}</span>
                                        <span v-if="c.client_code" class="font-mono text-xs text-gray-400">{{ c.client_code }}</span>
                                        <span v-else class="text-xs text-gray-400">（ID未設定）</span>
                                    </li>
                                </ul>
                            </div>
                            <p class="text-sm font-medium text-red-600">
                                別クライアントの場合は Client ID を設定した上で再度登録してください。
                            </p>
                            <details class="text-xs text-gray-400">
                                <summary class="cursor-pointer hover:text-gray-600">類似判定の基準とは？</summary>
                                <p class="mt-1 leading-relaxed">
                                    株式会社・有限会社などの法人格を除いた社名、全角/半角の違い、スペースの有無を統一した上で比較しています。
                                </p>
                            </details>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300" @click="closeModal">
                                閉じる（名前または Client ID を変更する）
                            </button>
                        </div>
                    </template>

                    <!-- ===== パターン3: diff_code_same_name ===== -->
                    <!-- 両方 Client ID あり・異なる・名前が一致 → 確認（通過可能） -->
                    <template v-else-if="duplicateModalType === 'diff_code_same_name'">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">同名のクライアントが存在します</h3>
                        </div>
                        <div class="mb-5 space-y-3">
                            <p class="text-sm text-gray-700">
                                名前 <strong class="text-gray-900">「{{ form.name }}」</strong> と同名のクライアントが存在しますが、
                                Client ID が異なるため別のクライアントとして登録できます。
                            </p>
                            <div class="rounded-md bg-blue-50 p-3">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wider text-blue-700">既存の同名クライアント（Client ID が異なる）</p>
                                <ul class="space-y-1">
                                    <li v-for="c in duplicateModalClients" :key="c.id" class="flex items-center gap-2 text-sm text-gray-800">
                                        <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-blue-500" />
                                        <span class="font-medium">{{ c.name }}</span>
                                        <span class="font-mono text-xs text-gray-500">{{ c.client_code }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p class="text-sm text-gray-600">Client ID が異なる別クライアントとして登録しますか？</p>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <button
                                type="button"
                                class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700"
                                @click="doSubmit"
                            >
                                このまま登録する
                            </button>
                            <button type="button" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300" @click="closeModal">
                                キャンセル
                            </button>
                        </div>
                    </template>

                </div>
            </div>
        </div>
    </Teleport>
</template>
