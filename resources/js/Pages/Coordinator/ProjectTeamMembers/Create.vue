<template>
    <AppLayout title="チームメンバー管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">チームメンバー管理</h2>
            </div>
        </template>
        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4">
                <h3 class="mb-3 text-lg font-medium text-gray-900">登録チームメンバー一覧</h3>

                <!-- インライン絞り込みフィルター -->
                <div class="mb-4 flex items-end gap-3">
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-700">部署</label>
                        <select v-model="selectedDepartmentId" class="w-full rounded border px-3 py-2 text-sm"
                            @change="selectedAssignmentId = ''">
                            <option value="">-- 全部署 --</option>
                            <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                                {{ department.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-700">担当</label>
                        <select v-model="selectedAssignmentId" class="w-full rounded border px-3 py-2 text-sm"
                            :disabled="!selectedDepartmentId">
                            <option value="">-- 全担当 --</option>
                            <option v-for="assignment in filteredAssignments" :key="assignment.id" :value="String(assignment.id)">
                                {{ assignment.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <button @click="clearSearch"
                            class="rounded bg-gray-300 px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-400">
                            クリア
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="mb-2 border-b pb-1 text-sm font-bold text-gray-600">
                    メンバー一覧（{{ filteredMembers.length }}件）
                </div>
                <div class="max-h-96 overflow-y-auto rounded border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <input type="checkbox" :checked="allChecked" @change="toggleAllMembers" />
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="member in filteredMembers"
                                :key="member.id"
                                class="cursor-pointer hover:bg-gray-50"
                                @click="toggleMember(member.id)"
                                :class="{ 'bg-blue-50': selectedMemberIds.includes(member.id) }"
                            >
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500" @click.stop>
                                    <input type="checkbox" :value="member.id" v-model="selectedMemberIds" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                    <span v-if="member.is_ghost" class="mr-1 rounded bg-amber-100 px-1 py-0.5 text-xs font-semibold text-amber-800">[テスト]</span>
                                    {{ member.name }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                    {{ getDepartmentName(member.department_id) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                    <span
                                        :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                    >
                                        {{ getAssignmentName(member.assignment_id) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="filteredMembers.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">該当するメンバーがいません</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 選択中メンバー chips -->
            <div v-if="selectedMembers.length > 0" class="mt-3 rounded bg-blue-50 p-3">
                <div class="mb-1 text-sm font-medium text-blue-700">{{ selectedMembers.length }}人選択中</div>
                <div class="flex flex-wrap gap-1">
                    <span v-for="member in selectedMembers" :key="member.id"
                        class="inline-flex items-center gap-1 rounded bg-blue-100 px-2 py-1 text-xs text-blue-700">
                        <span v-if="member.is_ghost" class="rounded bg-amber-100 px-1 text-xs text-amber-800">[テスト]</span>
                        {{ member.name }}
                        <button type="button" @click="removeSelectedMember(member.id)"
                            class="ml-0.5 font-bold text-blue-400 hover:text-red-600 leading-none">×</button>
                    </span>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button class="rounded bg-indigo-600 px-6 py-2 font-bold text-white hover:bg-indigo-700" @click="registerMembers">
                    メンバー登録
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    members: Array,
    departments: Array,
    assignments: Array,
    user: Object,
    pre_checked_ids: { type: Array, default: () => [] },
    leader_department_id: { type: [Number, String, null], default: null },
});

const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');

// リーダー・サブCoordinatorをデフォルト選択
const selectedMemberIds = ref(
    props.pre_checked_ids && props.pre_checked_ids.length > 0
        ? [...props.pre_checked_ids]
        : props.user ? [props.user.id] : []
);

// selected_user_ids がクエリストリングで渡された場合に適用
try {
    const qp = new URLSearchParams(window.location.search);
    const selected = qp.get('selected_user_ids');
    if (selected) {
        const arr = String(selected).split(',').map((s) => Number(s)).filter(Boolean);
        if (arr.length) selectedMemberIds.value = Array.from(new Set([...selectedMemberIds.value, ...arr]));
    }
} catch (e) {
    // ignore
}

const page = usePage();
function resolveProjectJobId() {
    if (props.project_job_id) return props.project_job_id;
    if (page?.props?.project_job_id) return page.props.project_job_id;
    try {
        const qp = new URLSearchParams(window.location.search);
        const q = qp.get('project_job_id');
        if (q) return q;
    } catch (e) { /* ignore */ }
    return null;
}
const projectJobId = resolveProjectJobId();

function clearSearch() {
    selectedDepartmentId.value = '';
    selectedAssignmentId.value = '';
}

const filteredAssignments = computed(() => {
    if (!selectedDepartmentId.value) return [];
    return props.assignments.filter((a) => String(a.department_id) === String(selectedDepartmentId.value));
});

const filteredMembers = computed(() => {
    const ghosts = props.members.filter((m) => m.is_ghost);
    let result = props.members.filter((m) => !m.is_ghost);
    if (selectedDepartmentId.value) {
        result = result.filter((m) => String(m.department_id) === String(selectedDepartmentId.value));
    }
    if (selectedAssignmentId.value) {
        result = result.filter((m) => String(m.assignment_id) === String(selectedAssignmentId.value));
    }
    return [...result, ...ghosts];
});

const getDepartmentName = (department_id) => {
    const department = props.departments.find((d) => d.id === department_id);
    return department ? department.name : '';
};

const getAssignmentName = (assignment_id) => {
    const assignment = props.assignments.find((a) => a.id === assignment_id);
    return assignment ? assignment.name : '';
};

const getAssignmentBadgeClass = (assignment) => {
    switch (assignment) {
        case '管理者':    return 'bg-red-100 text-red-800';
        case 'リーダー': return 'bg-orange-100 text-orange-800';
        case '進行管理': return 'bg-green-100 text-blue-800';
        case 'ユーザー': return 'bg-blue-100 text-blue-800';
        default:          return 'bg-gray-100 text-gray-800';
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

const allChecked = computed(() =>
    filteredMembers.value.length > 0 &&
    filteredMembers.value.every((m) => selectedMemberIds.value.includes(m.id)),
);

const toggleAllMembers = () => {
    if (allChecked.value) {
        const ids = filteredMembers.value.map((m) => m.id);
        selectedMemberIds.value = selectedMemberIds.value.filter((id) => !ids.includes(id));
    } else {
        const ids = filteredMembers.value.map((m) => m.id);
        selectedMemberIds.value = Array.from(new Set([...selectedMemberIds.value, ...ids]));
    }
};

const selectedMembers = computed(() =>
    props.members.filter((m) => selectedMemberIds.value.includes(m.id)),
);

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
                    for (const key in errors) { msg += `・${errors[key]}\n`; }
                    alert(msg);
                }
            },
        },
    );
}
</script>
