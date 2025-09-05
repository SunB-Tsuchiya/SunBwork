<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    assignments: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    showActions: {
        type: Boolean,
        default: true,
    },
    // When true, show leftmost selectable checkbox column
    selectable: {
        type: Boolean,
        default: false,
    },
    // array of selected ids (strings or numbers). Parent can bind and listen to update:selected
    selected: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:selected']);

// normalize selected ids to strings for stable comparison
const normalizedSelected = computed(() => (Array.isArray(props.selected) ? props.selected.map((s) => String(s)) : []));

const allIds = computed(() => (Array.isArray(props.users) ? props.users.map((u) => String(u.id)) : []));

const allSelected = computed(() => allIds.value.length > 0 && allIds.value.every((id) => normalizedSelected.value.includes(id)));

const isSelected = (id) => normalizedSelected.value.includes(String(id));

const toggleSelection = (id) => {
    const set = new Set(normalizedSelected.value);
    const sid = String(id);
    if (set.has(sid)) set.delete(sid);
    else set.add(sid);
    emit('update:selected', Array.from(set));
};

const toggleAll = () => {
    if (allSelected.value) emit('update:selected', []);
    else emit('update:selected', allIds.value.slice());
};

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

const getDepartmentName = (department_id) => {
    if (!props.departments) return '';
    const department = props.departments.find((d) => d.id === department_id);
    return department ? department.name : '';
};

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

function deleteUser(id) {
    if (confirm('本当にこのユーザーを削除しますか？')) {
        router.delete(route('admin.users.destroy', id), {
            onSuccess: () => {
                // reload current page
                router.reload();
            },
        });
    }
}

const membersList = computed(() => (Array.isArray(props.users) ? [...props.users] : []));

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
</script>

<template>
    <div class="mt-2 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th v-if="props.selectable" class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <input type="checkbox" :checked="allSelected" @change.prevent="toggleAll" />
                    </th>
                    <th
                        @click.prevent="changeSort('id')"
                        class="cursor-pointer px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        ID
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
                        <span class="ml-2 inline-block w-4 text-center text-xs" aria-hidden="true">
                            <template v-if="sortKey === 'name'">
                                <span v-if="!sortDesc">▲</span>
                                <span v-else>▼</span>
                            </template>
                        </span>
                    </th>
                    <th
                        @click.prevent="changeSort('department_id')"
                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        部署
                        <span class="ml-2 inline-block w-4 text-center text-xs" aria-hidden="true">
                            <template v-if="sortKey === 'department_id'">
                                <span v-if="!sortDesc">▲</span>
                                <span v-else>▼</span>
                            </template>
                        </span>
                    </th>
                    <th
                        @click.prevent="changeSort('assignment_id')"
                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        担当
                        <span class="ml-2 inline-block w-4 text-center text-xs" aria-hidden="true">
                            <template v-if="sortKey === 'assignment_id'">
                                <span v-if="!sortDesc">▲</span>
                                <span v-else>▼</span>
                            </template>
                        </span>
                    </th>
                    <th
                        @click.prevent="changeSort('user_role')"
                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        権限レベル
                        <span class="ml-2 inline-block w-4 text-center text-xs" aria-hidden="true">
                            <template v-if="sortKey === 'user_role'">
                                <span v-if="!sortDesc">▲</span>
                                <span v-else>▼</span>
                            </template>
                        </span>
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="sortedUsers.length === 0">
                    <td colspan="6" class="px-6 py-4 text-sm text-gray-500">メンバーが登録されていません</td>
                </tr>
                <tr v-for="user in sortedUsers" :key="user.id" class="hover:bg-gray-50">
                    <td v-if="props.selectable" class="whitespace-nowrap px-2 py-4 text-sm text-gray-500">
                        <input type="checkbox" :checked="isSelected(user.id)" @change.prevent="toggleSelection(user.id)" />
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ user.id }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ user.name }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ getDepartmentName(user.department_id) }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ getAssignmentName(user.assignment_id) }}</td>
                    <td class="whitespace-nowrap px-6 py-4">
                        <span :class="getAssignmentBadgeClass(user.user_role)" class="inline-flex rounded-full px-2 py-1 text-xs font-semibold">{{
                            getAssignmentText(user.user_role)
                        }}</span>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                        <div class="flex justify-end space-x-2">
                            <Link :href="route('admin.users.show', user.id)" class="text-blue-600 hover:text-blue-900">詳細</Link>
                            <span v-if="showActions">
                                <Link :href="route('admin.users.edit', user.id)" class="text-yellow-600 hover:text-yellow-900">編集</Link>
                                <button @click="deleteUser(user.id)" class="text-red-600 hover:text-red-900">削除</button>
                            </span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
