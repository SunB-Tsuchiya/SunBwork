<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const DEPT_COLORS = {
    '情報出版': 'bg-blue-100 text-blue-700',
    '製版':     'bg-green-100 text-green-700',
    'オンデマンド': 'bg-purple-100 text-purple-700',
};

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
const isAdmin       = computed(() => ['admin', 'superadmin'].includes(page.props.auth?.user?.user_role));
const isLeader      = computed(() => page.props.auth?.user?.user_role === 'leader');
const isCoordinator = computed(() => ['coordinator', 'clerk'].includes(page.props.auth?.user?.user_role));
const userDeptId    = computed(() => page.props.auth?.user?.department_id);
const ownDept       = computed(() => props.departments.find(d => d.id === userDeptId.value));

const form = useForm({
    name:           '',
    detail:         '',
    department_ids: (isLeader.value || isCoordinator.value) && userDeptId.value ? [userDeptId.value] : [],
});

// ===== 重複チェック =====
const showDuplicateModal = ref(false);
const duplicateClients = ref([]);
const isCheckingDuplicate = ref(false);

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
            body: JSON.stringify({ name: form.name }),
        });
        if (res.ok) {
            const data = await res.json();
            if (data.duplicates && data.duplicates.length > 0) {
                duplicateClients.value = data.duplicates;
                showDuplicateModal.value = true;
                return; // 保存しない
            }
        }
    } catch {
        // チェック失敗時はそのまま保存続行
    } finally {
        isCheckingDuplicate.value = false;
    }

    form.post(route(`${routePrefix.value}.clients.store`));
}

function closeDuplicateModal() {
    showDuplicateModal.value = false;
}
</script>

<template>
    <AppLayout title="クライアント新規作成">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアント新規作成</h2>
                <Link :href="route(`${routePrefix}.clients.index`)" class="text-gray-600 hover:text-gray-900">← 一覧に戻る</Link>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
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
                    <label class="mb-1 block">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-2 py-1"></textarea>
                </div>

                <!-- Admin/SuperAdmin: 全部署から複数選択 -->
                <div v-if="isAdmin" class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700">部署 <span class="text-xs text-gray-400">（複数選択可）</span></label>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="dept in props.departments"
                            :key="dept.id"
                            class="flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors"
                            :class="form.department_ids.includes(dept.id)
                                ? (DEPT_COLORS[dept.name] ?? 'bg-gray-100 text-gray-700') + ' border-transparent font-medium'
                                : 'border-gray-300 text-gray-500 hover:border-gray-400'"
                        >
                            <input type="checkbox" :value="dept.id" v-model="form.department_ids" class="hidden" />
                            {{ dept.name }}
                        </label>
                    </div>
                    <p v-if="form.errors.department_ids" class="mt-1 text-xs text-red-600">{{ form.errors.department_ids }}</p>
                </div>

                <!-- Leader / Coordinator: 自部署のみオン/オフ -->
                <div v-else-if="(isLeader || isCoordinator) && ownDept" class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700">部署</label>
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors"
                        :class="form.department_ids.includes(ownDept.id)
                            ? (DEPT_COLORS[ownDept.name] ?? 'bg-gray-100 text-gray-700') + ' border-transparent font-medium'
                            : 'border-gray-300 text-gray-500 hover:border-gray-400'"
                    >
                        <input type="checkbox" :value="ownDept.id" v-model="form.department_ids" class="hidden" />
                        {{ ownDept.name }}
                    </label>
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

    <!-- 重複警告モーダル -->
    <Teleport to="body">
        <div v-if="showDuplicateModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <!-- オーバーレイ -->
            <div class="absolute inset-0 bg-black/50" @click="closeDuplicateModal" />

            <!-- モーダル本体 -->
            <div class="relative z-10 w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="p-6">
                    <!-- ヘッダー -->
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">類似クライアントが見つかりました</h3>
                    </div>

                    <!-- 本文 -->
                    <div class="mb-5 space-y-3">
                        <p class="text-sm text-gray-700">
                            入力した名前 <strong class="text-gray-900">「{{ form.name }}」</strong> と似たクライアントが既に登録されています。
                            データが重複する可能性があります。
                        </p>

                        <!-- 類似クライアント一覧 -->
                        <div class="rounded-md bg-yellow-50 p-3">
                            <p class="mb-2 text-xs font-medium uppercase tracking-wider text-yellow-700">既存の類似クライアント</p>
                            <ul class="space-y-1">
                                <li v-for="c in duplicateClients" :key="c.id" class="flex items-center gap-2 text-sm text-gray-800">
                                    <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-yellow-500" />
                                    <span class="font-medium">{{ c.name }}</span>
                                    <span class="text-xs text-gray-400">（ID: {{ c.id }}）</span>
                                </li>
                            </ul>
                        </div>

                        <p class="text-sm font-medium text-red-600">
                            追加する場合はクライアント名を変更してから再度登録してください。
                        </p>

                        <!-- 判定ロジックの説明 -->
                        <details class="text-xs text-gray-400">
                            <summary class="cursor-pointer hover:text-gray-600">類似判定の基準とは？</summary>
                            <p class="mt-1 leading-relaxed">
                                株式会社・有限会社などの法人格を除いた社名、全角/半角の違い、スペースの有無を統一した上で比較しています。
                                例：「株式会社ABC」「ＡＢＣ株式会社」「ABC 」は同一と判定されます。
                            </p>
                        </details>
                    </div>

                    <!-- フッター -->
                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300"
                            @click="closeDuplicateModal"
                        >
                            閉じる（名前を変更する）
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
