<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// props定義
const props = defineProps({
    members: Array,
    departments: Array,
    assignments: Array,
    // accept user via prop or via shared page props under auth.user
    user: Object,
    auth: Object,
});
const page = usePage();
const currentUser = page.props?.auth?.user ?? page.props?.user ?? props.auth?.user ?? props.user ?? null;
const roomType = ref('private');
const name = ref('');
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');
const selectedMemberIds = ref(currentUser ? [currentUser.id] : []);
const errors = ref({});

watch(roomType, (val) => {
    name.value = '';
    selectedMemberIds.value = currentUser ? [currentUser.id] : [];
});

// パーソナル時、相手選択で自動でnameにセット
watch([roomType, selectedMemberIds], ([type, ids]) => {
    if (type === 'private') {
        if (!currentUser || !Array.isArray(ids)) {
            name.value = '';
            return;
        }
        // 自分以外のID（型が string/number 混在する可能性があるため文字列化して比較する）
        const otherIds = ids.filter((id) => String(id) !== String(currentUser.id));
        const otherId = otherIds.length > 0 ? otherIds[0] : null;
        const other = props.members.find((m) => String(m.id) === String(otherId));
        name.value = other ? other.name : '';
    } else {
        // グループに切り替えた場合は name を空にする（ユーザが入力可能）
        // ただし既に入力されている場合は消さない方が安全。ここでは初期化は行わない。
    }
});

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
    // 自分を先頭に、その後他のメンバー
    const self = result.find((m) => m.id === currentUser.id);
    const others = result.filter((m) => m.id !== currentUser.id);
    return self ? [self, ...others] : others;
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
    // prevent toggling current user
    if (currentUser && id === currentUser.id) return;
    // if private room, allow only one other participant besides currentUser
    if (roomType.value === 'private') {
        // toggle: if already selected (other), remove it (leave only currentUser), otherwise set as the sole other
        const exists = selectedMemberIds.value.some((mid) => String(mid) === String(id));
        if (exists) {
            selectedMemberIds.value = currentUser ? [currentUser.id] : [];
        } else {
            selectedMemberIds.value = currentUser ? [currentUser.id, id] : [id];
        }
        return;
    }
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
        selectedMemberIds.value = currentUser ? [currentUser.id] : [];
    } else {
        if (roomType.value === 'private') {
            // pick first other only
            const firstOther = filteredMembers.value.find((m) => m.id !== currentUser.id);
            selectedMemberIds.value = currentUser
                ? firstOther
                    ? [currentUser.id, firstOther.id]
                    : [currentUser.id]
                : firstOther
                  ? [firstOther.id]
                  : [];
        } else {
            selectedMemberIds.value = [currentUser.id, ...filteredMembers.value.filter((m) => m.id !== currentUser.id).map((m) => m.id)];
        }
    }
};

// normalize selectedMemberIds when it changes: ensure currentUser included and private has at most one other
watch(
    selectedMemberIds,
    (ids) => {
        if (!currentUser) return;
        const currIdStr = String(currentUser.id);
        // ensure current user present
        let normalized = Array.isArray(ids) ? [...ids] : [];
        if (!normalized.some((id) => String(id) === currIdStr)) {
            normalized.unshift(currentUser.id);
        }
        // if private room, keep at most one other
        if (roomType.value === 'private') {
            const others = normalized.filter((id) => String(id) !== currIdStr).map((id) => id);
            normalized = [currentUser.id];
            if (others.length > 0) normalized.push(others[0]);
        }
        // if changed, update
        if (JSON.stringify(normalized) !== JSON.stringify(ids)) {
            selectedMemberIds.value = normalized;
        }
    },
    { deep: true },
);
const selectedMembers = computed(() => {
    // 自分を先頭、その後に他の選択メンバー
    const self = props.members.find((m) => m.id === currentUser.id);
    const others = props.members.filter((m) => selectedMemberIds.value.includes(m.id) && m.id !== currentUser.id);
    return self ? [self, ...others] : others;
});
function removeSelectedMember(id) {
    // cannot remove current user
    if (currentUser && id === currentUser.id) return;
    selectedMemberIds.value = selectedMemberIds.value.filter((mid) => mid !== id);
}
function validate() {
    errors.value = {};
    if (roomType.value === 'private') {
        if (selectedMemberIds.value.length < 2) errors.value.members = '相手を選択してください';
        if (!name.value.trim()) errors.value.name = '相手の名前を入力してください';
    } else {
        if (!name.value.trim()) errors.value.name = 'グループ名を入力してください';
        if (selectedMemberIds.value.length < 3) errors.value.members = '2人以上選択してください（自分含む）';
    }
    return Object.keys(errors.value).length === 0;
}
function createRoom() {
    if (!validate()) return;
    router.post(
        '/chat/rooms',
        {
            name: name.value,
            type: roomType.value,
            user_ids: selectedMemberIds.value,
        },
        {
            onSuccess: () => router.visit('/chat/rooms'),
            onError: (e) => {
                errors.value = e;
            },
        },
    );
}
</script>
<template>
    <AppLayout title="チャットルーム作成">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チャットルーム作成</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
                <div class="mb-4">
                    <label class="mr-4 font-bold">ルーム種別:</label>
                    <label class="mr-4"><input type="radio" value="private" v-model="roomType" /> パーソナル</label>
                    <label><input type="radio" value="group" v-model="roomType" /> グループ</label>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-bold">{{ roomType === 'private' ? '相手の名前' : 'グループ名' }}</label>
                    <input
                        v-model="name"
                        class="w-full rounded border px-3 py-2"
                        :placeholder="roomType === 'private' ? '相手の名前' : 'グループ名'"
                        :readonly="roomType === 'private'"
                        :style="roomType === 'private' ? 'background:#f3f4f6;' : ''"
                    />
                    <div v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-bold">選択中のメンバー</label>
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
                            <tr
                                v-for="member in selectedMembers"
                                :key="member.id"
                                :class="member.id === currentUser.id ? 'bg-gray-200' : 'hover:bg-gray-50'"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ member.id }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ member.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ getDepartmentName(member.department_id) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    <span
                                        :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                    >
                                        {{ getAssignmentName(member.assignment_id) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <template v-if="member.id === currentUser.id">
                                        <span class="rounded bg-blue-100 px-2 py-1 text-blue-800">ユーザー</span>
                                    </template>
                                    <template v-else>
                                        <button @click="removeSelectedMember(member.id)" class="text-red-600 hover:text-red-900">削除</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mb-4 mt-6 flex justify-end">
                    <button class="rounded bg-blue-600 px-6 py-2 text-white" @click="createRoom">作成する</button>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-bold">メンバー選択</label>
                    <div class="mb-2 flex gap-4">
                        <select v-model="selectedDepartmentId" class="rounded border px-2 py-1">
                            <option value="">-- 部署を選択 --</option>
                            <option v-for="department in props.departments" :key="department.id" :value="String(department.id)">
                                {{ department.name }}
                            </option>
                        </select>
                        <select v-model="selectedAssignmentId" class="rounded border px-2 py-1" :disabled="!selectedDepartmentId">
                            <option value="">-- 担当を選択 --</option>
                            <option v-for="assignment in filteredAssignments" :key="assignment.id" :value="String(assignment.id)">
                                {{ assignment.name }}
                            </option>
                        </select>
                    </div>
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
                                    <input type="checkbox" :value="member.id" v-model="selectedMemberIds" :disabled="member.id === currentUser?.id" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ member.id }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ member.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ getDepartmentName(member.department_id) }}</td>
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
                    <div v-if="errors.members" class="mt-1 text-sm text-red-600">{{ errors.members }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
