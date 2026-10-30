<script setup>
// 宛先（全員 / 社員のみ / 個別選択）とユーザー選択モーダル。お知らせとリマインダーで共用する。
import { computed, ref, watch } from 'vue';

const props = defineProps({
    targetType: { type: String, required: true },
    userIds: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    companies: { type: Array, default: null },
    showCompany: { type: Boolean, default: false },
    accent: { type: String, default: 'purple' },
    // 対象範囲ブロック（サン・ブレーンのみ。全社 / 本社 / 小台）
    showBlocks: { type: Boolean, default: false },
    block: { type: String, default: 'all' },
    error: { type: String, default: '' },
    userError: { type: String, default: '' },
});
const emit = defineEmits(['update:targetType', 'update:userIds', 'update:block']);

// Tailwind は動的クラスを検出できないため、全文字列を明示する
const ACCENTS = {
    purple: { radio: 'text-purple-600', btn: 'border-purple-400 text-purple-700 hover:bg-purple-50', row: 'bg-purple-50', hover: 'hover:bg-purple-50', main: 'bg-purple-600 hover:bg-purple-700', link: 'text-purple-600' },
    orange: { radio: 'text-orange-600', btn: 'border-orange-400 text-orange-700 hover:bg-orange-50', row: 'bg-orange-50', hover: 'hover:bg-orange-50', main: 'bg-orange-600 hover:bg-orange-700', link: 'text-orange-600' },
};
const cls = computed(() => ACCENTS[props.accent] ?? ACCENTS.purple);

const showModal = ref(false);
const employmentFilter = ref('all');
const sortKey = ref('name');
const sortAsc = ref(true);

const employmentLabel = (type) => ({ regular: '正社員', contract: '契約社員', dispatch: '派遣', outsource: '業務委託' }[type] ?? type);
const companyName = (id) => props.companies?.find((c) => c.id === id)?.name ?? '';

function toggleSort(key) {
    if (sortKey.value === key) sortAsc.value = !sortAsc.value;
    else { sortKey.value = key; sortAsc.value = true; }
}

const BLOCKS = [
    { value: 'all', label: '全社' },
    { value: 'honsha', label: '本社', hint: '情報出版' },
    { value: 'odai', label: '小台', hint: '製版・オンデマンド' },
];

const filteredUsers = computed(() => {
    let base = props.showBlocks && props.block !== 'all'
        ? props.users.filter((u) => (u.blocks ?? []).includes(props.block))
        : props.users;
    if (employmentFilter.value === 'employees') base = base.filter((u) => ['regular', 'contract'].includes(u.employment_type));
    else if (employmentFilter.value === 'dispatch') base = base.filter((u) => ['dispatch', 'outsource'].includes(u.employment_type));
    return [...base].sort((a, b) => {
        const va = sortKey.value === 'company' ? companyName(a.company_id) : a.name;
        const vb = sortKey.value === 'company' ? companyName(b.company_id) : b.name;
        return sortAsc.value ? va.localeCompare(vb, 'ja') : vb.localeCompare(va, 'ja');
    });
});

// 個別選択以外に切り替えたら選択をクリア
// ブロックを切り替えたら個別選択をクリア（範囲外の人が残らないように）
watch(() => props.block, () => emit('update:userIds', []));
watch(() => props.targetType, (v) => { if (v !== 'individual') emit('update:userIds', []); });

function toggleUser(id) {
    const next = props.userIds.includes(id) ? props.userIds.filter((x) => x !== id) : [...props.userIds, id];
    emit('update:userIds', next);
}
const selectAll = () => emit('update:userIds', filteredUsers.value.map((u) => u.id));
const clearAll = () => emit('update:userIds', []);
</script>

<template>
    <div>
        <div v-if="showBlocks" class="mb-4">
            <label class="mb-1 block text-sm font-medium text-gray-700">対象範囲</label>
            <div class="flex flex-wrap gap-4">
                <label v-for="b in BLOCKS" :key="b.value" class="flex cursor-pointer items-center gap-1.5">
                    <input type="radio" :checked="block === b.value" :value="b.value" :class="cls.radio" @change="emit('update:block', b.value)" />
                    <span class="text-sm">{{ b.label }}<span v-if="b.hint" class="ml-1 text-xs text-gray-500">（{{ b.hint }}）</span></span>
                </label>
            </div>
            <p class="mt-1 text-xs text-gray-500">管理者はどの範囲にも含まれます。</p>
        </div>
        <label class="mb-1 block text-sm font-medium text-gray-700">宛先</label>
        <div class="flex gap-4">
            <label class="flex cursor-pointer items-center gap-1.5">
                <input type="radio" :checked="targetType === 'all'" value="all" :class="cls.radio" @change="emit('update:targetType', 'all')" />
                <span class="text-sm">全員</span>
            </label>
            <label class="flex cursor-pointer items-center gap-1.5">
                <input type="radio" :checked="targetType === 'employees_only'" value="employees_only" :class="cls.radio" @change="emit('update:targetType', 'employees_only')" />
                <span class="text-sm">社員のみ</span>
            </label>
            <label class="flex cursor-pointer items-center gap-1.5">
                <input type="radio" :checked="targetType === 'individual'" value="individual" :class="cls.radio" @change="emit('update:targetType', 'individual')" />
                <span class="text-sm">個別選択</span>
            </label>
        </div>
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
        <div v-if="targetType === 'individual'" class="mt-3">
            <button type="button" class="rounded border px-3 py-1.5 text-sm" :class="cls.btn" @click="showModal = true">
                ユーザーを選択する
            </button>
            <span v-if="userIds.length > 0" class="ml-2 text-sm text-gray-600">{{ userIds.length }}人選択中</span>
            <p v-if="userError" class="mt-1 text-xs text-red-500">{{ userError }}</p>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-800">送信先ユーザーを選択</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="showModal = false">✕</button>
                    </div>
                    <div class="flex items-center gap-3 border-b bg-gray-50 px-6 py-3">
                        <span class="text-sm text-gray-600">絞り込み:</span>
                        <label class="flex cursor-pointer items-center gap-1 text-sm"><input v-model="employmentFilter" type="radio" value="all" :class="cls.radio" /> 全員</label>
                        <label class="flex cursor-pointer items-center gap-1 text-sm"><input v-model="employmentFilter" type="radio" value="employees" :class="cls.radio" /> 正社員・契約社員</label>
                        <label class="flex cursor-pointer items-center gap-1 text-sm"><input v-model="employmentFilter" type="radio" value="dispatch" :class="cls.radio" /> 派遣・業務委託</label>
                        <div class="ml-auto flex gap-2">
                            <button type="button" class="text-xs hover:underline" :class="cls.link" @click="selectAll">全選択</button>
                            <button type="button" class="text-xs text-gray-500 hover:underline" @click="clearAll">解除</button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="w-10 px-4 py-2"></th>
                                    <th class="cursor-pointer select-none px-4 py-2 text-left text-xs font-medium text-gray-500 hover:text-gray-700" @click="toggleSort('name')">
                                        名前 <span v-if="sortKey === 'name'">{{ sortAsc ? '▲' : '▼' }}</span>
                                    </th>
                                    <th v-if="showCompany" class="cursor-pointer select-none px-4 py-2 text-left text-xs font-medium text-gray-500 hover:text-gray-700" @click="toggleSort('company')">
                                        会社 <span v-if="sortKey === 'company'">{{ sortAsc ? '▲' : '▼' }}</span>
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">担当</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">雇用形態</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="u in filteredUsers"
                                    :key="u.id"
                                    class="cursor-pointer"
                                    :class="[cls.hover, userIds.includes(u.id) ? cls.row : '']"
                                    @click="toggleUser(u.id)"
                                >
                                    <td class="px-4 py-2 text-center">
                                        <input type="checkbox" :checked="userIds.includes(u.id)" class="rounded" :class="cls.radio" @click.stop="toggleUser(u.id)" />
                                    </td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ u.name }}</td>
                                    <td v-if="showCompany" class="px-4 py-2 text-xs text-gray-500">{{ companyName(u.company_id) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ u.assignment_name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ employmentLabel(u.employment_type) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="filteredUsers.length === 0" class="py-6 text-center text-sm text-gray-500">
                            {{ showCompany && users.length === 0 ? '送信先の会社を選択してください' : '該当するユーザーがいません' }}
                        </p>
                    </div>
                    <div class="flex items-center justify-between border-t px-6 py-4">
                        <span class="text-sm text-gray-600">{{ userIds.length }}人選択中</span>
                        <button type="button" class="rounded px-5 py-2 text-sm font-medium text-white" :class="cls.main" @click="showModal = false">確定</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
