<script setup>
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

// 派遣社員のみ表示
// 他の雇用形態も表示したい場合は以下のコメントアウトを解除して filtered を差し替えてください
// ---------------------------------------------------------------
// const filterType = ref('all');
// const EMPLOYMENT_TYPE_OPTIONS = [
//     { value: 'all',       label: 'すべて' },
//     { value: 'regular',   label: '正社員' },
//     { value: 'contract',  label: '契約社員' },
//     { value: 'dispatch',  label: '派遣社員' },
//     { value: 'outsource', label: '業務委託' },
// ];
// const filtered = computed(() => {
//     const base = filterType.value === 'all'
//         ? props.users
//         : props.users.filter((u) => u.employment_type === filterType.value);
//     return showHidden.value ? base : base.filter((u) => !u.is_hidden);
// });
// ---------------------------------------------------------------

// 非表示ユーザーを表示するか
const showHidden = ref(false);

// 派遣のみ・非表示フィルタリング
const filtered = computed(() => {
    const dispatch = props.users.filter((u) => u.employment_type === 'dispatch');
    return showHidden.value ? dispatch : dispatch.filter((u) => !u.is_hidden);
});

// 非表示ユーザー数
const hiddenCount = computed(() => props.users.filter((u) => u.employment_type === 'dispatch' && u.is_hidden).length);

// ■ 在籍中バッジ
// is_active=false → 非在籍（手動オフ）
// is_active=true + contract_end 超過 → 契約終了
// それ以外（期間未設定含む）→ 在籍中
function isActive(user) {
    if (!user.is_active) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = user.contract_end ? new Date(user.contract_end) : null;
    return !end || end >= today;
}

// 契約終了日の表示スタイルと表示値
function contractEndInfo(dateStr) {
    if (!dateStr) return null;
    const end = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.ceil((end - today) / (1000 * 60 * 60 * 24));
    return {
        label: dateStr,
        diffDays,
        cls: diffDays < 0 ? 'text-red-600 font-semibold' : diffDays <= 30 ? 'text-orange-500 font-semibold' : 'text-gray-700',
        badge: diffDays < 0 ? '期限切れ' : diffDays <= 30 ? `残${diffDays}日` : null,
    };
}

// 非表示トグル（インライン）
function toggleHidden(user) {
    router.patch(
        route('leader.dispatch_management.toggle_hidden', { dispatchUser: user.id }),
        { is_hidden: !user.is_hidden },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout title="派遣社員管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">派遣社員管理</h2>
        </template>
        <template #tabs>
            <LeaderNavigationTabs active="dispatch" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- ツールバー：非表示切り替えボタン -->
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    表示中: {{ filtered.length }} 名
                    <span v-if="hiddenCount > 0" class="ml-2 text-gray-400">（非表示: {{ hiddenCount }} 名）</span>
                </p>
                <button
                    v-if="hiddenCount > 0"
                    type="button"
                    class="rounded border px-3 py-1.5 text-sm transition"
                    :class="showHidden ? 'border-gray-400 bg-gray-100 text-gray-700' : 'border-gray-300 text-gray-500 hover:bg-gray-50'"
                    @click="showHidden = !showHidden"
                >
                    {{ showHidden ? '非表示ユーザーを隠す' : `非表示も表示 (${hiddenCount})` }}
                </button>
            </div>

            <!-- テーブル -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">氏名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">在籍</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">派遣会社</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">契約期間</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">日報</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">非表示</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-if="filtered.length === 0">
                            <td colspan="9" class="px-4 py-6 text-center text-sm text-gray-400">
                                {{
                                    hiddenCount > 0 && !showHidden ? '表示中のユーザーがいません（非表示ユーザーがいます）' : '対象ユーザーがいません'
                                }}
                            </td>
                        </tr>
                        <tr v-for="user in filtered" :key="user.id" class="hover:bg-gray-50" :class="user.is_hidden ? 'bg-gray-50 opacity-70' : ''">
                            <!-- 氏名 -->
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ user.name }}
                                <div class="text-xs text-gray-400">{{ user.email }}</div>
                            </td>
                            <!-- 部署 -->
                            <td class="px-4 py-3 text-sm text-gray-600">{{ user.department_name || '—' }}</td>
                            <!-- 担当 -->
                            <td class="px-4 py-3 text-sm text-gray-600">{{ user.assignment_name || '—' }}</td>
                            <!-- 在籍バッジ -->
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="isActive(user) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'"
                                >
                                    {{ isActive(user) ? '在籍中' : '契約終了' }}
                                </span>
                            </td>
                            <!-- 派遣会社 -->
                            <td class="px-4 py-3 text-sm text-gray-600">{{ user.agency_name || '—' }}</td>
                            <!-- 契約期間 -->
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-col gap-0.5">
                                    <span v-if="user.contract_start" class="text-xs text-gray-500"> {{ user.contract_start }} 〜 </span>
                                    <template v-if="user.contract_end">
                                        <span :class="contractEndInfo(user.contract_end)?.cls">
                                            {{ contractEndInfo(user.contract_end)?.label }}
                                        </span>
                                        <span
                                            v-if="contractEndInfo(user.contract_end)?.badge"
                                            class="inline-block self-start rounded px-1 text-xs font-bold"
                                            :class="
                                                contractEndInfo(user.contract_end)?.diffDays < 0
                                                    ? 'bg-red-100 text-red-600'
                                                    : 'bg-orange-100 text-orange-600'
                                            "
                                            >{{ contractEndInfo(user.contract_end)?.badge }}</span
                                        >
                                    </template>
                                    <span v-if="!user.contract_start && !user.contract_end" class="text-gray-300">—</span>
                                </div>
                            </td>
                            <!-- 日報 -->
                            <td class="px-4 py-3 text-center">
                                <span v-if="user.diary_required" class="text-xs text-blue-600">必須</span>
                                <span v-else class="text-xs text-gray-400">任意</span>
                                <span
                                    v-if="user.diary_required_override !== null && user.diary_required_override !== undefined"
                                    class="ml-1 text-xs text-orange-400"
                                    title="個別設定あり"
                                    >★</span
                                >
                            </td>
                            <!-- 非表示チェック -->
                            <td class="px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    :checked="user.is_hidden"
                                    class="h-4 w-4 cursor-pointer accent-gray-500"
                                    :title="user.is_hidden ? 'チェックを外すと一覧に表示されます' : 'チェックすると一覧から非表示になります'"
                                    @change="toggleHidden(user)"
                                />
                            </td>
                            <!-- 編集リンク -->
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('leader.dispatch_management.edit', { dispatchUser: user.id })"
                                    class="text-sm text-orange-600 hover:underline"
                                >
                                    編集
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 凡例 -->
            <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-400">
                <span>在籍中バッジは契約開始〜終了日で自動判定（日付は編集ページで設定）</span>
                <span>★ = 日報の個別上書きあり</span>
                <span class="font-medium text-orange-500">残N日 = 契約終了まで30日以内</span>
                <span class="font-medium text-red-600">期限切れ = 契約終了日が過去</span>
            </div>
        </div>
    </AppLayout>
</template>
