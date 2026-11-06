<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import useToasts from '@/Composables/useToasts';

// ポートフォリオ 利用メンバー設定（試用期間中は SuperAdmin のみ）
// 売上分析の許可画面を踏襲し、雇用区分・ロール・部署で絞り込めるようにしている
const props = defineProps({
    routePrefix: { type: String, required: true },
    assignmentNames: { type: Array, default: () => [] },
    candidates: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

// useToasts が返すのは showToast（addToast は存在しない）
const { showToast } = useToasts();
const addToast = showToast;
const savingId = ref(null);
const bulkSaving = ref(false);

const EMPLOYMENT_LABELS = {
    regular: '正社員', contract: '契約社員', dispatch: '派遣社員', outsource: '業務委託',
};

const rows = ref(props.candidates.map(c => ({ ...c })));

const filterEmployment = ref('');
const filterAssignment = ref('');
const filterDept = ref('');
const onlyEnabled = ref(false);
const keyword = ref('');

const filtered = computed(() => rows.value.filter(r => {
    if (filterEmployment.value && r.employment_type !== filterEmployment.value) return false;
    if (filterAssignment.value && r.assignment_name !== filterAssignment.value) return false;
    if (filterDept.value && String(r.department_id) !== String(filterDept.value)) return false;
    if (onlyEnabled.value && !r.enabled) return false;
    const k = keyword.value.trim().toLowerCase();
    if (k && !`${r.name} ${r.email}`.toLowerCase().includes(k)) return false;
    return true;
}));

const enabledCount = computed(() => rows.value.filter(r => r.enabled).length);

function toggle(row) {
    savingId.value = row.id;
    const next = !row.enabled;
    router.put(
        route(`${props.routePrefix}.portfolio.permissions.update`, { user: row.id }),
        { enabled: next },
        {
            preserveScroll: true,
            onSuccess: () => {
                row.enabled = next;
                addToast(next ? '利用を許可しました' : '利用許可を解除しました', 'success');
            },
            onError: () => addToast('保存に失敗しました', 'error'),
            onFinish: () => { savingId.value = null; },
        }
    );
}

// 絞り込み結果の全員をまとめてオン/オフ
function bulk(next) {
    if (!filtered.value.length) return;
    if (!window.confirm(`表示中の${filtered.value.length}人を${next ? '許可' : '解除'}します。よろしいですか？`)) return;
    bulkSaving.value = true;
    const ids = filtered.value.map(r => r.id);
    router.put(
        route(`${props.routePrefix}.portfolio.permissions.bulk`),
        { user_ids: ids, enabled: next },
        {
            preserveScroll: true,
            onSuccess: () => {
                rows.value.forEach(r => { if (ids.includes(r.id)) r.enabled = next; });
                addToast('一括で保存しました', 'success');
            },
            onError: () => addToast('保存に失敗しました', 'error'),
            onFinish: () => { bulkSaving.value = false; },
        }
    );
}
</script>

<template>
    <AppLayout title="ポートフォリオ 利用メンバー設定">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ポートフォリオ 利用メンバー設定</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-gray-500">
                    サン・ブレーンのメンバーが対象です（SuperAdmin は常に利用できます）。許可したメンバーは自分のポートフォリオを作成・閲覧できます。
                </p>
                <Link :href="route(`${props.routePrefix}.portfolio.manage`)" class="text-sm text-blue-600 hover:underline">← ポートフォリオ管理へ戻る</Link>
            </div>

            <!-- 絞り込み -->
            <div class="mb-4 flex flex-wrap items-end gap-3 rounded border border-gray-200 bg-gray-50 p-3">
                <label class="text-xs text-gray-600">
                    雇用区分
                    <select v-model="filterEmployment" class="mt-1 block rounded border-gray-300 text-sm">
                        <option value="">すべて</option>
                        <option v-for="(label, key) in EMPLOYMENT_LABELS" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
                <label class="text-xs text-gray-600">
                    担当
                    <select v-model="filterAssignment" class="mt-1 block rounded border-gray-300 text-sm">
                        <option value="">すべて</option>
                        <option v-for="a in assignmentNames" :key="a" :value="a">{{ a }}</option>
                    </select>
                </label>
                <label class="text-xs text-gray-600">
                    部署
                    <select v-model="filterDept" class="mt-1 block rounded border-gray-300 text-sm">
                        <option value="">すべて</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </label>
                <label class="text-xs text-gray-600">
                    氏名・メール
                    <input v-model="keyword" type="search" class="mt-1 block rounded border-gray-300 text-sm" placeholder="検索" />
                </label>
                <label class="flex items-center gap-1 pb-1 text-sm text-gray-700">
                    <input v-model="onlyEnabled" type="checkbox" class="rounded border-gray-300" />
                    許可済みのみ
                </label>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600">
                <span>{{ filtered.length }}人表示 / 許可 {{ enabledCount }}人</span>
                <span class="flex gap-2">
                    <button type="button" :disabled="bulkSaving || !filtered.length" class="rounded border border-green-600 px-3 py-1 text-green-700 hover:bg-green-50 disabled:opacity-40" @click="bulk(true)">表示中を全員許可</button>
                    <button type="button" :disabled="bulkSaving || !filtered.length" class="rounded border border-gray-400 px-3 py-1 text-gray-700 hover:bg-gray-50 disabled:opacity-40" @click="bulk(false)">表示中を全員解除</button>
                </span>
            </div>

            <div v-if="filtered.length === 0" class="py-8 text-center text-gray-500">該当するメンバーがいません。</div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">雇用区分</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">利用許可</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in filtered" :key="row.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ row.name }}</div>
                                <div class="text-xs text-gray-500">{{ row.email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ row.department_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-gray-700">{{ EMPLOYMENT_LABELS[row.employment_type] ?? row.employment_type }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-gray-700">{{ row.assignment_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <button
                                    type="button"
                                    :disabled="savingId === row.id"
                                    :class="row.enabled ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-300 hover:bg-gray-400'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors disabled:opacity-50"
                                    @click="toggle(row)"
                                >
                                    <span :class="row.enabled ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
