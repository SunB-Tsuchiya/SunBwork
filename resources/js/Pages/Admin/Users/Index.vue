<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import UserTable from '@/Components/UserTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const DEPT_ORDER = ['情報出版', '製版', 'オンデマンド'];
function sortDepts(depts) {
    return [...depts].sort((a, b) => {
        const ai = DEPT_ORDER.indexOf(a.name);
        const bi = DEPT_ORDER.indexOf(b.name);
        if (ai !== -1 && bi !== -1) return ai - bi;
        if (ai !== -1) return -1;
        if (bi !== -1) return 1;
        return a.name.localeCompare(b.name, 'ja');
    });
}

// 部署フィルターボタン用（superadmin は全部署、admin は自社部署）
const buttonDepartments = computed(() => {
    const base = filteredDepartments.value.length > 0 ? filteredDepartments.value : props.departments;
    return sortDepts(base);
});

function selectDept(deptId) {
    selectedDepartmentId.value = deptId;
    selectedAssignmentId.value = '';
}

// 検索結果用usersフィルタ
const filteredUsers = computed(() => {
    let result = props.users;
    if (selectedDepartmentId.value) {
        result = result.filter((u) => String(u.department_id) === String(selectedDepartmentId.value));
    }
    if (selectedAssignmentId.value) {
        result = result.filter((u) => String(u.assignment_id) === String(selectedAssignmentId.value));
    }
    return result;
});

// ソート状態
const sortKey = ref('id');
const sortDesc = ref(false);

// フィルタ済みのユーザーをソートして返す
const sortedUsers = computed(() => {
    const list = Array.isArray(filteredUsers.value) ? [...filteredUsers.value] : [];
    if (!sortKey.value) return list;
    list.sort((a, b) => {
        let va;
        let vb;
        const key = sortKey.value;
        if (key === 'department_id') {
            va = getDepartmentName(a.department_id);
            vb = getDepartmentName(b.department_id);
        } else if (key === 'assignment_id') {
            va = getAssignmentName(a.assignment_id);
            vb = getAssignmentName(b.assignment_id);
        } else if (key === 'user_role') {
            va = getAssignmentText(a.user_role);
            vb = getAssignmentText(b.user_role);
        } else {
            va = a[key];
            vb = b[key];
        }

        if (va === null || va === undefined) va = '';
        if (vb === null || vb === undefined) vb = '';
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

// ---- 並び替えモード ----
const reorderMode = ref(false);
const allReorderList = ref([]);   // グローバルマスターリスト
const reorderDeptId = ref('');    // 並び替えモード内の部署タブ
const reorderProcessing = ref(false);

// 部署タブで絞り込んだ表示リスト
const reorderDisplayList = computed(() =>
    reorderDeptId.value
        ? allReorderList.value.filter(u => String(u.department_id) === reorderDeptId.value)
        : allReorderList.value
);

const getEmploymentTypeLabel = (type) => {
    switch (type) {
        case 'regular':   return '正社員';
        case 'contract':  return '契約社員';
        case 'dispatch':  return '派遣社員';
        case 'outsource': return '業務委託';
        default:          return '正社員';
    }
};

function enterReorderMode() {
    allReorderList.value = [...props.users].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
    reorderDeptId.value = selectedDepartmentId.value;
    reorderMode.value = true;
}

function switchReorderDept(deptId) {
    reorderDeptId.value = deptId;
}

function cancelReorder() {
    reorderMode.value = false;
    allReorderList.value = [];
    reorderDeptId.value = '';
}

// 表示リスト上の idx を受け取り、マスターリスト内で swap
function moveUp(idx) {
    if (idx <= 0) return;
    const userA = reorderDisplayList.value[idx - 1];
    const userB = reorderDisplayList.value[idx];
    const iA = allReorderList.value.indexOf(userA);
    const iB = allReorderList.value.indexOf(userB);
    [allReorderList.value[iA], allReorderList.value[iB]] = [allReorderList.value[iB], allReorderList.value[iA]];
}

function moveDown(idx) {
    const displayed = reorderDisplayList.value;
    if (idx >= displayed.length - 1) return;
    const userA = displayed[idx];
    const userB = displayed[idx + 1];
    const iA = allReorderList.value.indexOf(userA);
    const iB = allReorderList.value.indexOf(userB);
    [allReorderList.value[iA], allReorderList.value[iB]] = [allReorderList.value[iB], allReorderList.value[iA]];
}

const EMPLOYMENT_RANK = { regular: 1, contract: 2, dispatch: 3, outsource: 4 };

// 表示中ユーザーを役職→雇用形態→名前で自動整列し、マスターリストの元スロットに挿入
function quickSort() {
    const displayed = reorderDisplayList.value;
    const sorted = [...displayed].sort((a, b) => {
        const pa = a.position_title?.sort_order ?? 999;
        const pb = b.position_title?.sort_order ?? 999;
        if (pa !== pb) return pa - pb;
        const ea = EMPLOYMENT_RANK[a.employment_type ?? 'regular'] ?? 5;
        const eb = EMPLOYMENT_RANK[b.employment_type ?? 'regular'] ?? 5;
        if (ea !== eb) return ea - eb;
        return (a.name ?? '').localeCompare(b.name ?? '', 'ja');
    });
    // 元の位置（マスター内のインデックス）を昇順に取得し、sorted を順番に埋める
    const positions = displayed
        .map(u => allReorderList.value.indexOf(u))
        .sort((a, b) => a - b);
    const newList = [...allReorderList.value];
    positions.forEach((pos, i) => { newList[pos] = sorted[i]; });
    allReorderList.value = newList;
}

function submitReorder() {
    reorderProcessing.value = true;
    router.put(route('admin.users.reorder'), {
        ordered_ids: allReorderList.value.map(u => u.id),
    }, {
        onSuccess: () => { reorderMode.value = false; allReorderList.value = []; reorderDeptId.value = ''; },
        onFinish:  () => { reorderProcessing.value = false; },
    });
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
});

// department_idから部署名を取得
// Debug logging removed
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

const getAssignmentText = (assignment) => {
    switch (assignment) {
        case 'superadmin':
            return 'サイト管理者';
        case 'admin':
            return '管理者';
        case 'leader':
            return 'リーダー';
        case 'coordinator':
            return '進行管理';
        case 'proof_coordinator':
            return '校正コーディネーター';
        case 'clerk':
            return '事務・経理';
        case 'user':
            return 'ユーザー';
        default:
            return '不明';
    }
};
</script>

<template>
    <AppLayout title="ユーザー管理">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ユーザー管理</h2>
        </template>
        <template #headerExtras>
            <div class="flex items-center gap-2">
                <button
                    v-if="!reorderMode && (myuser?.user_role === 'superadmin' || myuser?.user_role === 'admin')"
                    type="button"
                    @click="enterReorderMode"
                    class="rounded bg-gray-600 px-4 py-2 font-bold text-white hover:bg-gray-700"
                >
                    並び替え
                </button>
                <Link
                    v-if="!reorderMode && (myuser?.user_role === 'superadmin' || myuser?.user_role === 'admin')"
                    :href="route('admin.users.create')"
                    class="rounded bg-red-600 px-4 py-2 font-bold text-white hover:bg-red-700"
                >
                    新規ユーザー登録
                </Link>
            </div>
        </template>
        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- 並び替えモード -->
            <template v-if="reorderMode">
                <!-- ヘッダー行 -->
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-medium text-gray-900">並び替え</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="quickSort"
                            class="rounded border border-blue-300 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100"
                        >
                            簡易ソート（役職→雇用形態→名前）
                        </button>
                        <span class="text-sm text-gray-400">▲▼ で微調整 → 保存</span>
                    </div>
                </div>

                <!-- 部署タブ -->
                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="switchReorderDept('')"
                        :class="reorderDeptId === '' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                    >全員 ({{ allReorderList.length }})</button>
                    <button
                        v-for="dept in buttonDepartments"
                        :key="dept.id"
                        type="button"
                        @click="switchReorderDept(String(dept.id))"
                        :class="reorderDeptId === String(dept.id) ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                    >{{ dept.name }} ({{ allReorderList.filter(u => String(u.department_id) === String(dept.id)).length }})</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-20 px-3 py-2 text-left font-medium text-gray-600">順序</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">名前</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">部署</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">雇用形態</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">役職</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(user, idx) in reorderDisplayList"
                                :key="user.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1">
                                        <span class="w-6 text-right text-xs text-gray-400">
                                            {{ allReorderList.indexOf(user) + 1 }}
                                        </span>
                                        <div class="flex flex-col">
                                            <button
                                                type="button"
                                                :disabled="idx === 0"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveUp(idx)"
                                            >▲</button>
                                            <button
                                                type="button"
                                                :disabled="idx === reorderDisplayList.length - 1"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveDown(idx)"
                                            >▼</button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ user.name }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ getDepartmentName(user.department_id) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ getEmploymentTypeLabel(user.employment_type) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ user.position_title?.name ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                    <button
                        type="button"
                        @click="cancelReorder"
                        class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        キャンセル
                    </button>
                    <button
                        type="button"
                        @click="submitReorder"
                        :disabled="reorderProcessing"
                        class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        保存（全{{ allReorderList.length }}件）
                    </button>
                </div>
            </template>

            <!-- 通常表示モード -->
            <template v-else>
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">登録ユーザー一覧</h3>
                    <div class="text-sm text-gray-500">総数: {{ filteredUsers.length }}人</div>
                    <div class="flex items-center space-x-2">
                        <button @click="openSearchModal" class="rounded bg-indigo-600 px-4 py-2 font-bold text-white hover:bg-indigo-700">
                            絞り込み
                        </button>
                        <button @click="clearSearch" class="rounded bg-gray-300 px-4 py-2 font-bold text-gray-800 hover:bg-gray-400">
                            クリア
                        </button>
                    </div>
                </div>

                <!-- 部署フィルターボタン -->
                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="selectDept('')"
                        :class="selectedDepartmentId === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                    >全員</button>
                    <button
                        v-for="dept in buttonDepartments"
                        :key="dept.id"
                        type="button"
                        @click="selectDept(String(dept.id))"
                        :class="selectedDepartmentId === String(dept.id) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                    >{{ dept.name }}</button>
                </div>

                <div class="overflow-x-auto">
                    <!-- 検索モーダル -->
                    <DialogModal :show="showSearchModal" @close="closeSearchModal">
                        <template #title>ユーザー検索</template>
                        <template #content>
                            <div class="mb-4">
                                <label class="mb-1 block font-semibold">部署</label>
                                <select
                                    v-model="selectedDepartmentId"
                                    @change="onDepartmentChange"
                                    class="w-full rounded border px-3 py-2"
                                    :disabled="!userCompanyId"
                                >
                                    <option value="">-- 部署を選択してください --</option>
                                    <option v-for="department in filteredDepartments" :key="department.id" :value="String(department.id)">
                                        {{ department.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="mb-1 block font-semibold">担当</label>
                                <select
                                    v-model="selectedAssignmentId"
                                    class="w-full rounded border px-3 py-2"
                                    :disabled="!selectedDepartmentId"
                                >
                                    <option value="">-- 担当を選択してください --</option>
                                    <option v-for="assignment in filteredAssignments" :key="assignment.id" :value="String(assignment.id)">
                                        {{ assignment.name }}
                                    </option>
                                </select>
                            </div>
                        </template>
                        <template #footer>
                            <button class="mr-2 rounded bg-gray-300 px-4 py-2" @click="closeSearchModal">閉じる</button>
                            <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" @click="doSearch">絞り込み</button>
                        </template>
                    </DialogModal>
                    <UserTable :users="sortedUsers" :departments="props.departments" :assignments="props.assignments" />
                </div>
            </template>

        </div>
    </AppLayout>
</template>
