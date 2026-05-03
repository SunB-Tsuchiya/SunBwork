<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const DEPT_COLORS = {
    '情報出版': 'bg-blue-100 text-blue-700',
    '製版':     'bg-green-100 text-green-700',
    'オンデマンド': 'bg-purple-100 text-purple-700',
};

const props = defineProps({
    client:      Object,
    departments: { type: Array, default: () => [] },
});

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    if (['admin', 'superadmin'].includes(role)) return 'admin';
    if (role === 'coordinator') return 'coordinator';
    return 'leader';
});
const isAdmin    = computed(() => ['admin', 'superadmin'].includes(page.props.auth?.user?.user_role));
const isLeader   = computed(() => page.props.auth?.user?.user_role === 'leader');
const userDeptId = computed(() => page.props.auth?.user?.department_id);
const ownDept    = computed(() => props.departments.find(d => d.id === userDeptId.value));

const form = useForm({
    name:           props.client.name,
    detail:         props.client.notes,
    department_ids: (props.client.departments ?? []).map(d => d.id),
});

function submit() {
    form.put(route(`${routePrefix.value}.clients.update`, props.client.id));
}

// 休眠操作
const isDormantLoading = ref(false);
function toggleDormant(makeDormant) {
    if (!confirm(makeDormant
        ? `「${props.client.name}」を休眠状態にします。\n一覧には表示されなくなります。よろしいですか？`
        : `「${props.client.name}」の休眠を解除します。\n通常一覧に表示されるようになります。よろしいですか？`)
    ) return;
    isDormantLoading.value = true;
    router.post(
        route(`${routePrefix.value}.clients.dormant`, props.client.id),
        { dormant: makeDormant },
        { onFinish: () => { isDormantLoading.value = false; } },
    );
}

// ===== 削除・統合モーダル =====
const showModal = ref(false);
// 'error'=削除ブロック表示  'select'=統合先選択
const modalStep = ref('error');
const deleteError = ref(null);

// 統合先選択ステップ用
const clientList = ref([]);
const clientNameFilter = ref('');
const mergeTarget = ref(null);
const isFetchingClients = ref(false);
const isMerging = ref(false);

// サーバーから返ってきた削除エラーをウォッチ
watch(
    () => page.props.clientDeleteError,
    (val) => {
        if (val) {
            deleteError.value = val;
            modalStep.value = 'error';
            showModal.value = true;
        }
    },
    { immediate: true },
);

function closeModal() {
    showModal.value = false;
    modalStep.value = 'error';
    mergeTarget.value = null;
    clientNameFilter.value = '';
}

// 案件なしクライアントの直接削除確認
function confirmDelete() {
    if (!confirm(`「${props.client.name}」を削除してもよいですか？\nこの操作は取り消せません。`)) return;
    router.delete(route(`${routePrefix.value}.clients.destroy`, props.client.id));
}

// ===== 統合先選択ステップ =====
const filteredClients = computed(() => {
    const q = clientNameFilter.value.trim().toLowerCase();
    return clientList.value.filter(
        (c) => c.id !== props.client.id && (!q || c.name.toLowerCase().includes(q)),
    );
});

async function openSelectStep() {
    isFetchingClients.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(
            route(`${routePrefix.value}.clients.json`) + '?include_dormant=1',
            {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        clientList.value = await res.json();
    } catch {
        alert('クライアント一覧の取得に失敗しました。ページを再読み込みしてください。');
        return;
    } finally {
        isFetchingClients.value = false;
    }
    mergeTarget.value = null;
    clientNameFilter.value = '';
    modalStep.value = 'select';
}

function selectMergeTarget(client) {
    mergeTarget.value = client;
}

function executeMerge() {
    if (!mergeTarget.value) return;
    const count = deleteError.value?.projectJobCount ?? '';
    const msg =
        `「${props.client.name}」の案件 ${count} 件をすべて\n` +
        `「${mergeTarget.value.name}」に移し、クライアントを削除します。\n\n` +
        `この操作は取り消せません。よろしいですか？`;
    if (!confirm(msg)) return;

    isMerging.value = true;
    router.post(
        route(`${routePrefix.value}.clients.merge`, props.client.id),
        { merge_into_id: mergeTarget.value.id },
        { onFinish: () => { isMerging.value = false; } },
    );
}
</script>

<template>
    <AppLayout title="クライアント編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアント編集</h2>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
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

                <!-- Leader: 自部署のみオン/オフ -->
                <div v-else-if="isLeader && ownDept" class="mb-4">
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

                <!-- 休眠バッジ -->
                <div v-if="props.client.is_dormant" class="mb-4 flex items-center gap-2 rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-600">
                    <svg class="h-4 w-4 flex-shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                    このクライアントは現在<strong class="ml-1">休眠状態</strong>です。通常の一覧には表示されません。
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit" class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700">更新</button>
                    <Link :href="route(`${routePrefix}.clients.index`)" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300">一覧へ戻る</Link>

                    <!-- 休眠 / 解除ボタン -->
                    <button
                        v-if="!props.client.is_dormant"
                        type="button"
                        :disabled="isDormantLoading"
                        class="rounded bg-gray-500 px-4 py-2 font-bold text-white hover:bg-gray-600 disabled:opacity-60"
                        @click="toggleDormant(true)"
                    >
                        休眠にする
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="isDormantLoading"
                        class="rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                        @click="toggleDormant(false)"
                    >
                        休眠を解除
                    </button>

                    <button
                        type="button"
                        class="ml-auto rounded bg-red-600 px-4 py-2 font-bold text-white hover:bg-red-700"
                        @click="confirmDelete"
                    >
                        削除
                    </button>
                </div>
            </form>
        </div>

        <!-- 削除・統合モーダル -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <!-- オーバーレイ -->
                <div class="absolute inset-0 bg-black/50" @click="closeModal" />

                <!-- モーダル本体 -->
                <div class="relative z-10 w-full max-w-lg rounded-lg bg-white shadow-xl">

                    <!-- ===== Step: error ===== -->
                    <template v-if="modalStep === 'error'">
                        <div class="p-6">
                            <!-- ヘッダー -->
                            <div class="mb-4 flex items-center gap-3">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">そのままでは削除できません</h3>
                            </div>

                            <!-- 本文 -->
                            <div v-if="deleteError" class="mb-6 space-y-3">
                                <p class="text-sm text-gray-700">
                                    クライアント <strong class="text-gray-900">「{{ deleteError.clientName }}」</strong> には
                                    現在 <strong class="text-red-600">{{ deleteError.projectJobCount }} 件</strong> の案件が紐付いているため削除できません。
                                </p>

                                <!-- 案件一覧（最大5件） -->
                                <div class="rounded-md bg-gray-50 p-3">
                                    <p class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-500">紐付いている案件（一部）</p>
                                    <ul class="space-y-1">
                                        <li v-for="(title, i) in deleteError.projectJobTitles" :key="i" class="flex items-center gap-2 text-sm text-gray-700">
                                            <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400" />
                                            {{ title }}
                                        </li>
                                    </ul>
                                    <p v-if="deleteError.projectJobCount > deleteError.projectJobTitles.length" class="mt-2 text-xs text-gray-500">
                                        ほか {{ deleteError.projectJobCount - deleteError.projectJobTitles.length }} 件…
                                    </p>
                                </div>

                                <p class="text-sm text-gray-500">
                                    別のクライアントへ統合することで削除できます。統合すると紐付いている案件の客先がすべて移行されます。
                                </p>
                            </div>

                            <!-- フッター -->
                            <div class="flex items-center justify-between gap-3">
                                <button
                                    type="button"
                                    :disabled="isFetchingClients"
                                    class="flex items-center gap-2 rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700 disabled:opacity-60"
                                    @click="openSelectStep"
                                >
                                    <svg v-if="isFetchingClients" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    統合先クライアントを選ぶ
                                </button>
                                <button
                                    type="button"
                                    class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300"
                                    @click="closeModal"
                                >
                                    閉じる
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- ===== Step: select ===== -->
                    <template v-if="modalStep === 'select'">
                        <div class="p-6">
                            <!-- ヘッダー -->
                            <div class="mb-4 flex items-center gap-3">
                                <button type="button" class="flex-shrink-0 rounded p-1 text-gray-500 hover:bg-gray-100" @click="modalStep = 'error'">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                    </svg>
                                </button>
                                <h3 class="text-lg font-semibold text-gray-900">統合先クライアントを選択</h3>
                            </div>

                            <!-- 移行元の表示 -->
                            <div class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                <span class="font-medium">削除：</span>{{ deleteError?.clientName }}
                                （案件 {{ deleteError?.projectJobCount }} 件）
                            </div>

                            <!-- 名前フィルター -->
                            <div class="mb-3">
                                <input
                                    v-model="clientNameFilter"
                                    type="text"
                                    placeholder="クライアント名で絞り込み…"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                                />
                            </div>

                            <!-- クライアント一覧 -->
                            <div class="mb-4 max-h-60 overflow-y-auto rounded border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="sticky top-0 bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">クライアント名</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr
                                            v-for="c in filteredClients"
                                            :key="c.id"
                                            class="cursor-pointer transition-colors"
                                            :class="mergeTarget?.id === c.id ? 'bg-blue-100' : 'hover:bg-blue-50'"
                                            @click="selectMergeTarget(c)"
                                        >
                                            <td class="px-3 py-2 text-gray-500">{{ c.id }}</td>
                                            <td class="px-3 py-2 font-medium text-gray-900">
                                                <div class="flex items-center gap-2">
                                                    <span v-if="mergeTarget?.id === c.id" class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-blue-500">
                                                        <svg class="h-2.5 w-2.5 text-white" fill="currentColor" viewBox="0 0 12 12">
                                                            <path d="M3.707 5.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4a1 1 0 00-1.414-1.414L5 6.586 3.707 5.293z"/>
                                                        </svg>
                                                    </span>
                                                    {{ c.name }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="filteredClients.length === 0">
                                            <td colspan="2" class="px-3 py-6 text-center text-gray-400">該当するクライアントがありません</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 選択中クライアントの確認表示 -->
                            <div v-if="mergeTarget" class="mb-4 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm">
                                <span class="text-blue-600">統合先：</span>
                                <strong class="text-blue-900">{{ mergeTarget.name }}</strong>
                            </div>

                            <!-- フッター -->
                            <div class="flex items-center justify-between gap-3">
                                <button
                                    type="button"
                                    :disabled="!mergeTarget || isMerging"
                                    class="flex items-center gap-2 rounded bg-red-600 px-4 py-2 font-bold text-white hover:bg-red-700 disabled:opacity-40"
                                    @click="executeMerge"
                                >
                                    <svg v-if="isMerging" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    この客先に統合して削除
                                </button>
                                <button
                                    type="button"
                                    class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300"
                                    @click="closeModal"
                                >
                                    キャンセル
                                </button>
                            </div>
                        </div>
                    </template>

                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
