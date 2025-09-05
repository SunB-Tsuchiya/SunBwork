<script setup>
import UserTable from '@/Components/UserTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentTeam = ref(props.team || {});

// Debug: log incoming props and resolved team/users to browser console
console.log('[Admin/Teams/Show] page.props:', page.props);
console.log('[Admin/Teams/Show] prop team:', props.team);
console.log('[Admin/Teams/Show] currentTeam.users (initial):', currentTeam.value.users);

const goBack = () => {
    router.visit(route('admin.teams.index'));
};

const goEdit = () => {
    router.visit(route('admin.teams.edit', { team: currentTeam.value.id }));
};

// delete user action (same as admin users index)
function deleteUser(id) {
    if (confirm('本当にこのユーザーを削除しますか？')) {
        router.delete(route('admin.users.destroy', id), {
            onSuccess: () => {
                router.visit(route('admin.teams.show', currentTeam.value.id));
            },
        });
    }
}

// table helpers
const departments = computed(() => page.props.departments || []);
const assignments = computed(() => page.props.assignments || []);

const getDepartmentName = (department_id) => {
    if (!departments.value) return '';
    const department = departments.value.find((d) => d.id === department_id);
    return department ? department.name : '';
};

const getAssignmentName = (assignment_id) => {
    const assignment = assignments.value.find((r) => r.id === assignment_id);
    return assignment ? assignment.name : '';
};

const getAssignmentBadgeClass = (assignment) => {
    switch (assignment) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'leader':
            return 'bg-orange-100 text-orange-800';
        case 'coordinator':
            return 'bg-blue-100 text-blue-800';
        case 'user':
            return 'bg-green-100 text-blue-800';
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
            return 'ユーザー';
        default:
            return '不明';
    }
};

// sorting
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

const membersList = computed(() => (Array.isArray(currentTeam.value.users) ? [...currentTeam.value.users] : []));

// 追加：ISO文字列を "YYYY年MM月DD日 HH時mm分ss秒" に整形するヘルパー
const formatDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return iso;
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}年${pad(d.getMonth() + 1)}月${pad(d.getDate())}日 ${pad(d.getHours())}時${pad(d.getMinutes())}分${pad(d.getSeconds())}秒`;
};
const sortedUsers = computed(() => {
    const list = membersList.value;
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

// compute leader display name from team.leader_id or leader_user relation if present
const leaderName = computed(() => {
    const teamObj = currentTeam.value || {};
    const lid = teamObj.leader_id || teamObj.leader_user_id || null;
    if (!lid) return '未設定';

    // if users are loaded, try to find leader by id
    const users = Array.isArray(teamObj.users) ? teamObj.users : [];
    const found = users.find((u) => String(u.id) === String(lid));
    if (found) return found.name || found.display_name || found.email || `ID:${found.id}`;

    // if there's an embedded leader object
    if (teamObj.leader_user && (teamObj.leader_user.name || teamObj.leader_user.display_name)) {
        return teamObj.leader_user.name || teamObj.leader_user.display_name;
    }

    // handle sentinel value
    if (String(lid) === 'superadmin') return 'Super Admin (全権限)';

    return '未設定';
});
</script>

<template>
    <AppLayout :title="`チーム：${currentTeam.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム詳細</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-sm text-gray-500">ID</div>
                            <div class="text-lg font-medium">{{ currentTeam.id }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">チーム名</div>
                            <div class="text-lg font-medium">{{ currentTeam.name }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">会社</div>
                            <div class="text-lg">{{ currentTeam.company?.name || '未設定' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">部署</div>
                            <div class="text-lg">{{ currentTeam.department?.name || '未設定' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">種別</div>
                            <div class="text-lg">{{ currentTeam.team_type || '' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">リーダー</div>
                            <div class="text-lg">{{ leaderName }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">作成日</div>
                            <div class="text-lg">{{ formatDate(currentTeam.created_at) }}</div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-medium text-gray-700">メンバー</h3>
                        <UserTable :users="currentTeam.users || []" :departments="departments" :assignments="assignments" :show-actions="false" />
                    </div>

                    <div class="mt-6 flex gap-2">
                        <button @click="goBack" class="rounded border px-4 py-2 text-sm">一覧へ戻る</button>
                        <button
                            v-if="currentTeam.team_type !== 'department'"
                            @click="goEdit"
                            class="rounded bg-blue-500 px-4 py-2 text-sm text-white hover:bg-blue-600"
                        >
                            編集
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
