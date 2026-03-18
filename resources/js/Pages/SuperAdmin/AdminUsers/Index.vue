<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
// 管理者アカウント削除処理
function deleteUser(id) {
    if (confirm('本当にこの管理者アカウントを削除しますか？')) {
        router.delete(route('superadmin.adminusers.destroy', id), {
            onSuccess: () => {
                router.visit(route('superadmin.adminusers.index'));
            },
        });
    }
}

// 検索用モーダル状態
const showSearchModal = ref(false);
// Inertiaのグローバルpropsからuser情報を取得
const page = usePage();
const myuser = computed(() => page.props.user);
const userCompanyId = computed(() => {
    return myuser.value && myuser.value.company_id ? String(myuser.value.company_id) : '';
});
// 部署・担当の選択状態
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');

// company_idで絞った部署リスト
const filteredDepartments = computed(() => {
    return props.departments.filter((dep) => String(dep.company_id) === userCompanyId.value);
});
// 部署IDで絞った担当リスト
const filteredAssignments = computed(() => {
    if (!selectedDepartmentId.value) return [];
    return props.assignments.filter((a) => String(a.department_id) === String(selectedDepartmentId.value));
});

// 部署選択時に担当をリセット
function onDepartmentChange() {
    selectedAssignmentId.value = '';
}

// 検索結果用usersフィルタ（表示は admin のみ）
const filteredUsers = computed(() => {
    // まず admin ロールのみを対象にする
    let result = (props.users || []).filter((u) => String(u.user_role) === 'admin');
    if (selectedDepartmentId.value) {
        result = result.filter((u) => String(u.department_id) === String(selectedDepartmentId.value));
    }
    if (selectedAssignmentId.value) {
        result = result.filter((u) => String(u.assignment_id) === String(selectedAssignmentId.value));
    }
    return result;
});

// ソート用の状態
const sortKey = ref('id');
const sortDesc = ref(false);

const changeSort = (key) => {
    if (sortKey.value === key) {
        sortDesc.value = !sortDesc.value;
    } else {
        sortKey.value = key;
        sortDesc.value = false;
    }
};

// フィルタ済みのユーザーをソートして返す
const sortedUsers = computed(() => {
    const list = Array.isArray(filteredUsers.value) ? [...filteredUsers.value] : [];
    if (!sortKey.value) return list;
    list.sort((a, b) => {
        let va = a[sortKey.value];
        let vb = b[sortKey.value];
        if (va === null || va === undefined) va = '';
        if (vb === null || vb === undefined) vb = '';
        // 日付や数値の判定は粗めに行う
        const numA = Number(va);
        const numB = Number(vb);
        if (!isNaN(numA) && !isNaN(numB)) {
            return sortDesc.value ? numB - numA : numA - numB;
        }
        va = String(va).toLowerCase();
        vb = String(vb).toLowerCase();
        if (va < vb) return sortDesc.value ? 1 : -1;
        if (va > vb) return sortDesc.value ? -1 : 1;
        return 0;
    });
    return list;
});

function openSearchModal() {
    showSearchModal.value = true;
}
function closeSearchModal() {
    showSearchModal.value = false;
}
function resetSearch() {
    selectedDepartmentId.value = '';
    selectedAssignmentId.value = '';
}

function clearSearch() {
    resetSearch();
    showSearchModal.value = false;
}

function doSearch() {
    showSearchModal.value = false;
}

const props = defineProps({
    users: {
        type: Array,
        required: true,
    },
    assignments: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    companies: {
        type: Array,
        default: () => [],
    },
});

// company_idから部署名を取得
// Debug logging removed
const getCompanyName = (company_id) => {
    if (!props.companies || !Array.isArray(props.companies)) return '';
    const company = props.companies.find((c) => String(c.id) === String(company_id));
    return company ? company.name || company.company_name || `会社 #${company.id}` : '';
};

// department_idから部署名を取得
const getDepartmentName = (department_id) => {
    if (!props.departments) return '';
    const department = props.departments.find((d) => d.id === department_id);
    return department ? department.name : '';
};

// assignment_idから役職名を取得
const getAssignmentName = (assignment_id) => {
    const assignment = props.assignments.find((r) => r.id === assignment_id);
    return assignment ? assignment.name : '';
};

const getAssignmentBadgeClass = (assignment) => {
    switch (assignment) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'leader':
            return 'bg-orange-100 text-orange-800';
        case 'user':
            return 'bg-green-100 text-blue-800';
        case 'user':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getAssignmentText = (assignment) => {
    switch (assignment) {
        case 'admin':
            return '管理者';
        case 'leader':
            return 'リーダー';
        case 'coordinator':
            return '進行管理';
        case 'user':
            return '管理者アカウント';
        default:
            return '不明';
    }
};
</script>

<template>
    <AppLayout title="管理者アカウント管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">管理者アカウント管理</h2>
                <template v-if="myuser?.user_role === 'superadmin'">
                    <Link :href="route('superadmin.adminusers.create')" class="rounded bg-red-600 px-4 py-2 font-bold text-white hover:bg-red-700">
                        新規管理者アカウント登録
                    </Link>
                </template>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">登録アカウント一覧</h3>
                            <div class="text-sm text-gray-500">総数: {{ filteredUsers.length }}人</div>
                            <div class="flex items-center space-x-2">
                                <button @click="openSearchModal" class="rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-700">
                                    絞り込み
                                </button>
                                <button @click="clearSearch" class="rounded bg-gray-300 px-4 py-2 font-bold text-gray-800 hover:bg-gray-400">
                                    クリア
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <!-- 検索モーダル -->
                            <DialogModal :show="showSearchModal" @close="closeSearchModal">
                                <template #title>管理者アカウント検索</template>
                                <template #content>
                                    <div class="mb-4">
                                        <label class="mb-1 block font-semibold">会社</label>
                                        <select class="w-full rounded border px-3 py-2" disabled>
                                            <option value="">-- 会社を選択 --</option>
                                            <option
                                                v-for="company in props.companies"
                                                :key="company.id"
                                                :value="String(company.id)"
                                                :selected="String(company.id) === userCompanyId"
                                            >
                                                {{ company.name || company.company_name || '会社 #' + company.id }}
                                            </option>
                                        </select>
                                    </div>
                                    <!-- <div class="mb-4">
                        <label class="block mb-1 font-semibold">部署</label>
                        <select v-model="selectedDepartmentId" @change="onDepartmentChange" class="w-full border rounded px-3 py-2" :disabled="!userCompanyId">
                            <option value="">-- 部署を選択してください --</option>
                            <option v-for="department in filteredDepartments" :key="department.id" :value="String(department.id)">
                                {{ department.name }}
                            </option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">担当</label>
                        <select v-model="selectedAssignmentId" class="w-full border rounded px-3 py-2" :disabled="!selectedDepartmentId">
                            <option value="">-- 担当を選択してください --</option>
                            <option v-for="assignment in filteredAssignments" :key="assignment.id" :value="String(assignment.id)">
                                {{ assignment.name }}
                            </option>
                        </select>
                    </div> -->
                                </template>
                                <template #footer>
                                    <button class="mr-2 rounded bg-gray-300 px-4 py-2" @click="closeSearchModal">閉じる</button>
                                    <button class="rounded bg-blue-600 px-4 py-2 text-white" @click="doSearch">絞り込み</button>
                                </template>
                            </DialogModal>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            @click.prevent="changeSort('id')"
                                            class="cursor-pointer px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                        >
                                            ID
                                            <!-- 固定幅プレースホルダで幅を確保してソート時のずれを防止 -->
                                            <span class="ml-1 inline-block w-4 text-center text-xs" aria-hidden="true">
                                                <template v-if="sortKey === 'id'">
                                                    <span v-if="!sortDesc">▲</span>
                                                    <span v-else>▼</span>
                                                </template>
                                            </span>
                                        </th>
                                        <th
                                            @click.prevent="changeSort('name')"
                                            class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                        >
                                            名前
                                            <!-- 常に固定幅のインジケータをレンダリングして境界ずれを防ぐ -->
                                            <span class="ml-2 inline-block w-4 text-center text-xs" aria-hidden="true">
                                                <template v-if="sortKey === 'name'">
                                                    <span v-if="!sortDesc">▲</span>
                                                    <span v-else>▼</span>
                                                </template>
                                            </span>
                                        </th>
                                        <th
                                            @click.prevent="changeSort('company_id')"
                                            class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                        >
                                            会社
                                            <span class="ml-2 inline-block w-4 text-center text-xs" aria-hidden="true">
                                                <template v-if="sortKey === 'company_id'">
                                                    <span v-if="!sortDesc">▲</span>
                                                    <span v-else>▼</span>
                                                </template>
                                            </span>
                                        </th>
                                        <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            部署
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            担当
                                        </th> -->
                                        <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            権限レベル
                                        </th> -->
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="user in sortedUsers" :key="user.id" class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {{ user.id }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                            {{ user.name }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {{ getCompanyName(user.company_id) }}
                                        </td>
                                        <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ getDepartmentName(user.department_id) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ getAssignmentName(user.assignment_id) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getAssignmentBadgeClass(user.user_role)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                                {{ getAssignmentText(user.user_role) }}
                                            </span>
                                        </td> -->
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <div class="flex justify-end space-x-2">
                                                <Link :href="route('superadmin.adminusers.show', user.id)" class="text-blue-600 hover:text-blue-900">
                                                    詳細
                                                </Link>
                                                <Link
                                                    :href="route('superadmin.adminusers.edit', user.id)"
                                                    class="text-yellow-600 hover:text-yellow-900"
                                                >
                                                    編集
                                                </Link>
                                                <button @click="deleteUser(user.id)" class="text-red-600 hover:text-red-900">削除</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
        </div>
    </AppLayout>
</template>
