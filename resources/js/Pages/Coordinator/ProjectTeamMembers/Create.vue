<template>
    <AppLayout title="チームメンバー管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">チームメンバー管理</h2>
            </div>
        </template>
        <div class="rounded bg-white p-6 shadow">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">登録チームメンバー一覧</h3>
                                <div class="flex items-center space-x-2">
                                    <button @click="openSearchModal" class="rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-700">
                                        絞り込み
                                    </button>
                                    <button @click="clearSearch" class="rounded bg-gray-300 px-4 py-2 font-bold text-gray-800 hover:bg-gray-400">
                                        クリア
                                    </button>
                                </div>
                            </div>
                            <DialogModal :show="showSearchModal" @close="closeSearchModal">
                                <template #title>メンバー検索</template>
                                <template #content>
                                    <div class="mb-4">
                                        <label class="mb-1 block font-semibold">部署</label>
                                        <select v-model="selectedDepartmentId" class="w-full rounded border px-3 py-2">
                                            <option value="">-- 部署を選択してください --</option>
                                            <option v-for="department in departments" :key="department.id" :value="String(department.id)">
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
                                    <button class="rounded bg-blue-600 px-4 py-2 text-white" @click="doSearch">絞り込み</button>
                                </template>
                            </DialogModal>
                            <div class="overflow-x-auto">
                                <div class="mb-2 border-b pb-1 text-lg font-bold">メンバー一覧</div>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                <input type="checkbox" :checked="allChecked" @change="toggleAllMembers" />
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <tr
                                            v-for="member in filteredMembers"
                                            :key="member.id"
                                            class="hover:bg-gray-50"
                                            @click="toggleMember(member.id)"
                                            :class="{ 'bg-blue-50': selectedMemberIds.includes(member.id), 'cursor-pointer': true }"
                                        >
                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-500" @click.stop>
                                                <input type="checkbox" :value="member.id" v-model="selectedMemberIds" />
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ member.id }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ member.name }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {{ getDepartmentName(member.department_id) }}
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                <span
                                                    :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))"
                                                    class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                                >
                                                    {{ getAssignmentName(member.assignment_id) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-8 overflow-x-auto">
                                <div class="mb-2 border-b pb-1 text-lg font-bold">選択中のチームメンバー</div>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <tr v-for="member in selectedMembers" :key="member.id" class="hover:bg-gray-50">
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ member.id }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ member.name }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {{ getDepartmentName(member.department_id) }}
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                <span
                                                    :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))"
                                                    class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                                >
                                                    {{ getAssignmentName(member.assignment_id) }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                                <button @click="removeSelectedMember(member.id)" class="text-red-600 hover:text-red-900">削除</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="mt-4 flex justify-end">
                                    <button class="rounded bg-blue-600 px-6 py-2 font-bold text-white hover:bg-blue-700" @click="registerMembers">
                                        メンバー登録
                                    </button>
                                </div>
                            </div>
        </div>
    </AppLayout>
</template>
<script setup>
// 外部ライブラリ
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
// 自作コンポーネント
import DialogModal from '@/Components/DialogModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
// props定義
const props = defineProps({
    members: Array,
    departments: Array,
    assignments: Array,
    user: Object,
});
const showSearchModal = ref(false);
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');
// ログインユーザーをデフォルト選択
const selectedMemberIds = ref(props.user ? [props.user.id] : []);
// If selected_user_ids is passed via querystring (when editing from Show), parse and apply
try {
    const qp = new URLSearchParams(window.location.search);
    const selected = qp.get('selected_user_ids') || (page.props && page.props.selected_user_ids);
    if (selected) {
        const arr = String(selected)
            .split(',')
            .map((s) => Number(s))
            .filter(Boolean);
        if (arr.length) selectedMemberIds.value = Array.from(new Set([...selectedMemberIds.value, ...arr]));
    }
} catch (e) {
    // ignore in SSR or when URL unavailable
}
// project_job_id may be passed via props (from server) or via query string
const page = usePage();
function resolveProjectJobId() {
    // 1) explicit prop passed from server-side Inertia props
    if (props.project_job_id) return props.project_job_id;
    // 2) Inertia page props (common patterns)
    if (page && page.props && page.props.project_job_id) return page.props.project_job_id;
    if (page && page.props && page.props.job && page.props.job.id) return page.props.job.id;
    // 3) Ziggy route params (projectJob or project_job_id)
    try {
        if (typeof route === 'function' && route().params) {
            return route().params.projectJob || route().params.project_job_id || null;
        }
    } catch (e) {
        // ignore
    }
    // 4) fallback: URL query string ?project_job_id=...
    try {
        const qp = new URLSearchParams(window.location.search);
        const q = qp.get('project_job_id');
        if (q) return q;
    } catch (e) {
        // ignore (SSR or other)
    }
    return null;
}
const projectJobId = resolveProjectJobId();
function openSearchModal() {
    showSearchModal.value = true;
}
function closeSearchModal() {
    showSearchModal.value = false;
}
function clearSearch() {
    selectedDepartmentId.value = '';
    selectedAssignmentId.value = '';
    showSearchModal.value = false;
}
function doSearch() {
    showSearchModal.value = false;
}
const filteredAssignments = computed(() => {
    if (!selectedDepartmentId.value) return [];
    return props.assignments.filter((a) => String(a.department_id) === String(selectedDepartmentId.value));
});
const filteredMembers = computed(() => {
    let result = props.members;
    if (selectedDepartmentId.value) {
        result = result.filter((m) => String(m.department_id) === String(selectedDepartmentId.value));
    }
    if (selectedAssignmentId.value) {
        result = result.filter((m) => String(m.assignment_id) === String(selectedAssignmentId.value));
    }
    return result;
});
const getDepartmentName = (department_id) => {
    const department = props.departments.find((d) => d.id === department_id);
    return department ? department.name : '';
};
const getAssignmentName = (assignment_id) => {
    const assignment = props.assignments.find((a) => a.id === assignment_id);
    return assignment ? assignment.name : '';
};

// Admin/Users/Index.vueと同じバッジ色分け関数
const getAssignmentBadgeClass = (assignment) => {
    switch (assignment) {
        case '管理者':
            return 'bg-red-100 text-red-800';
        case 'リーダー':
            return 'bg-orange-100 text-orange-800';
        case '進行管理':
            return 'bg-green-100 text-blue-800';
        case 'ユーザー':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
function toggleMember(id) {
    const idx = selectedMemberIds.value.indexOf(id);
    if (idx === -1) {
        selectedMemberIds.value.push(id);
    } else {
        selectedMemberIds.value.splice(idx, 1);
    }
}
const allChecked = computed(() => filteredMembers.value.length > 0 && filteredMembers.value.every((m) => selectedMemberIds.value.includes(m.id)));
const toggleAllMembers = () => {
    if (allChecked.value) {
        selectedMemberIds.value = [];
    } else {
        selectedMemberIds.value = filteredMembers.value.map((m) => m.id);
    }
};
const selectedMembers = computed(() => {
    return props.members.filter((m) => selectedMemberIds.value.includes(m.id));
});
function removeSelectedMember(id) {
    selectedMemberIds.value = selectedMemberIds.value.filter((mid) => mid !== id);
}
function registerMembers() {
    const pid = projectJobId;
    if (!pid) {
        alert('プロジェクトIDが取得できません。');
        return;
    }
    router.post(
        route('coordinator.project_team_members.store'),
        {
            project_job_id: pid,
            user_ids: selectedMemberIds.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                if (errors && Object.keys(errors).length > 0) {
                    let msg = '登録に失敗しました。\n';
                    for (const key in errors) {
                        msg += `・${errors[key]}\n`;
                    }
                    alert(msg);
                }
            },
        },
    );
}
</script>
