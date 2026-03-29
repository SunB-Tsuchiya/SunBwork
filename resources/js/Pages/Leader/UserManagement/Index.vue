<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    users:       { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
});

// ── 検索 ──────────────────────────────────────────────
const searchName = ref('');

// ── 一覧編集モード ────────────────────────────────────
const editMode = ref(false);

// 編集用のローカルコピー（editMode 突入時に作成）
const editData = ref([]);

function enterEditMode() {
    editData.value = props.users.map((u) => ({
        id:              u.id,
        name:            u.name,
        email:           u.email,
        assignment_id:   u.assignment_id,
        user_role:       u.user_role,
        employment_type: u.employment_type ?? 'regular',
    }));
    editMode.value = true;
    searchName.value = '';
}

function cancelEditMode() {
    editMode.value = false;
    editData.value = [];
}

// 変更があった行だけ抽出
const dirtyRows = computed(() => {
    return editData.value.filter((row) => {
        const orig = props.users.find((u) => u.id === row.id);
        if (!orig) return false;
        return (
            row.name            !== orig.name ||
            row.assignment_id   !== orig.assignment_id ||
            row.user_role       !== orig.user_role ||
            row.employment_type !== (orig.employment_type ?? 'regular')
        );
    });
});

function saveAll() {
    if (dirtyRows.value.length === 0) {
        cancelEditMode();
        return;
    }
    router.post(
        route('leader.user_management.bulk_update'),
        { users: dirtyRows.value },
        { onSuccess: () => { editMode.value = false; } }
    );
}

// ── 表示用ユーティリティ ──────────────────────────────
const filteredUsers = computed(() => {
    const list = editMode.value ? editData.value : props.users;
    if (!searchName.value) return list;
    const q = searchName.value.toLowerCase();
    return list.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
});

const getRoleBadge = (role) => {
    switch (role) {
        case 'leader':      return 'bg-orange-100 text-orange-700';
        case 'coordinator': return 'bg-green-100 text-green-700';
        case 'user':        return 'bg-blue-100 text-blue-700';
        default:            return 'bg-gray-100 text-gray-700';
    }
};

const getRoleLabel = (role) => {
    switch (role) {
        case 'leader':      return 'リーダー';
        case 'coordinator': return '進行管理';
        case 'user':        return 'ユーザー';
        default:            return role;
    }
};

const getAssignmentName = (assignmentId) => {
    const a = props.assignments.find((a) => a.id === assignmentId);
    return a ? a.name : '—';
};

const EMPLOYMENT_LABELS = {
    regular:   '正社員',
    contract:  '契約社員',
    dispatch:  '派遣社員',
    outsource: '業務委託',
};

const getEmploymentLabel = (type) => EMPLOYMENT_LABELS[type] ?? type;

const EMPLOYMENT_OPTIONS = [
    { value: 'regular',   label: '正社員' },
    { value: 'contract',  label: '契約社員' },
    { value: 'dispatch',  label: '派遣社員' },
    { value: 'outsource', label: '業務委託' },
];

const USER_ROLE_OPTIONS = [
    { value: 'leader',      label: 'リーダー' },
    { value: 'coordinator', label: '進行管理' },
    { value: 'user',        label: 'ユーザー' },
];

// 編集行のインデックスを props.users から取得するヘルパー
// filteredUsers は editData のサブセットなので id で逆引き
const editRowOf = (userId) => editData.value.find((r) => r.id === userId);

// 変更があった行かどうか
const isDirty = (userId) => dirtyRows.value.some((r) => r.id === userId);
</script>

<template>
    <AppLayout title="ユーザー管理">
        <template #tabs>
            <LeaderNavigationTabs active="user_management" />
        </template>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">ユーザー管理（部署）</h2>
                <Link
                    v-if="!editMode"
                    :href="route('leader.user_management.create')"
                    class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700"
                >
                    新規ユーザー登録
                </Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- ツールバー -->
            <div class="mb-4 flex items-center gap-3">
                <input
                    v-if="!editMode"
                    v-model="searchName"
                    type="text"
                    placeholder="名前・メールで絞り込み"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                />
                <span v-if="!editMode" class="text-sm text-gray-500">{{ filteredUsers.length }} 人</span>

                <span v-if="editMode" class="text-sm font-medium text-orange-700">
                    一覧編集モード
                    <span v-if="dirtyRows.length > 0" class="ml-2 rounded-full bg-orange-100 px-2 py-0.5 text-xs">
                        {{ dirtyRows.length }} 件変更あり
                    </span>
                </span>

                <div class="ml-auto">
                    <button
                        v-if="!editMode"
                        @click="enterEditMode"
                        class="rounded border border-orange-400 px-3 py-1.5 text-sm font-medium text-orange-600 hover:bg-orange-50"
                    >
                        一覧で編集
                    </button>
                </div>
            </div>

            <!-- テーブル -->
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">メール</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">権限</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">雇用形態</th>
                        <th v-if="!editMode" class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <!-- 通常表示モード -->
                    <template v-if="!editMode">
                        <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ user.name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ user.email }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ getAssignmentName(user.assignment_id) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="getRoleBadge(user.user_role)">
                                    {{ getRoleLabel(user.user_role) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ getEmploymentLabel(user.employment_type) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <Link
                                    :href="route('leader.user_management.edit', { user: user.id })"
                                    class="text-sm font-medium text-orange-600 hover:text-orange-800"
                                >
                                    編集
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="filteredUsers.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">ユーザーが見つかりません</td>
                        </tr>
                    </template>

                    <!-- 一覧編集モード -->
                    <template v-else>
                        <tr
                            v-for="row in filteredUsers"
                            :key="row.id"
                            :class="isDirty(row.id) ? 'bg-orange-50' : 'hover:bg-gray-50'"
                        >
                            <!-- 名前: input -->
                            <td class="px-4 py-2">
                                <input
                                    v-model="editRowOf(row.id).name"
                                    type="text"
                                    class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                />
                            </td>
                            <!-- メール: 表示のみ -->
                            <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">{{ row.email }}</td>
                            <!-- 担当: select -->
                            <td class="px-4 py-2">
                                <select
                                    v-model="editRowOf(row.id).assignment_id"
                                    class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                >
                                    <option v-for="a in assignments" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </td>
                            <!-- 権限: select -->
                            <td class="px-4 py-2">
                                <select
                                    v-model="editRowOf(row.id).user_role"
                                    class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                >
                                    <option v-for="opt in USER_ROLE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </td>
                            <!-- 雇用形態: select -->
                            <td class="px-4 py-2">
                                <select
                                    v-model="editRowOf(row.id).employment_type"
                                    class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                >
                                    <option v-for="opt in EMPLOYMENT_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </td>
                        </tr>
                        <tr v-if="filteredUsers.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">ユーザーが見つかりません</td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- 一覧編集モード: 下部ボタン -->
            <div v-if="editMode" class="mt-6 flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                <button
                    @click="cancelEditMode"
                    class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    保存せずに戻る
                </button>
                <button
                    @click="saveAll"
                    class="rounded bg-orange-600 px-4 py-2 text-sm font-bold text-white hover:bg-orange-700"
                    :class="{ 'opacity-50 cursor-not-allowed': dirtyRows.length === 0 }"
                >
                    保存する
                    <span v-if="dirtyRows.length > 0">（{{ dirtyRows.length }} 件）</span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
