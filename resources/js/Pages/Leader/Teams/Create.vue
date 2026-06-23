<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const props = page.props;

const users       = ref(props.users || []);
const leaders     = ref(props.leaders || []);
const departments = ref(props.departments || []);
const assignments = ref(props.assignments || []);

const form = useForm({
    company_id:     props.auth_company_id || '',
    department_id:  props.auth_department_id || '',
    name:           '',
    description:    '',
    leader_id:      '',
    member_ids:     [],
    can_read_diary: false,
});

// リーダー変更時、そのユーザーをメンバーから除外
watch(
    () => form.leader_id,
    (newLeader) => {
        if (!newLeader || !Array.isArray(form.member_ids)) return;
        form.member_ids = form.member_ids.filter((id) => String(id) !== String(newLeader));
    },
);

// ── メンバー選択モーダル ──
const showMemberModal  = ref(false);
const modalFilterDeptId   = ref('');
const modalFilterAssignId = ref('');
const modalTempIds    = ref([]);

const modalFilterableAssignments = computed(() => {
    if (!modalFilterDeptId.value) return [];
    return assignments.value.filter((a) => String(a.department_id) === String(modalFilterDeptId.value));
});

const filteredModalMembers = computed(() => {
    const list = users.value.filter((u) => String(u.id) !== String(form.leader_id));
    if (!modalFilterDeptId.value && !modalFilterAssignId.value) return list;
    return list.filter((u) => {
        if (modalFilterDeptId.value && String(u.department_id) !== String(modalFilterDeptId.value)) return false;
        if (modalFilterAssignId.value && String(u.assignment_id) !== String(modalFilterAssignId.value)) return false;
        return true;
    });
});

const allModalChecked = computed(() =>
    filteredModalMembers.value.length > 0 &&
    filteredModalMembers.value.every((u) => modalTempIds.value.includes(String(u.id))),
);

function openMemberModal() {
    modalTempIds.value = [...form.member_ids];
    showMemberModal.value = true;
}

function closeMemberModal() {
    showMemberModal.value = false;
    modalFilterDeptId.value   = '';
    modalFilterAssignId.value = '';
}

function clearModalFilters() {
    modalFilterDeptId.value   = '';
    modalFilterAssignId.value = '';
}

function toggleAllModal() {
    const ids = filteredModalMembers.value.map((u) => String(u.id));
    if (allModalChecked.value) {
        modalTempIds.value = modalTempIds.value.filter((id) => !ids.includes(id));
    } else {
        modalTempIds.value = [...new Set([...modalTempIds.value, ...ids])];
    }
}

function toggleMember(id) {
    const sid = String(id);
    if (modalTempIds.value.includes(sid)) {
        modalTempIds.value = modalTempIds.value.filter((m) => m !== sid);
    } else {
        modalTempIds.value = [...modalTempIds.value, sid];
    }
}

function confirmMemberSelection() {
    form.member_ids = [...modalTempIds.value];
    closeMemberModal();
}

const selectedMembers = computed(() =>
    users.value.filter((u) => form.member_ids.includes(String(u.id))),
);

function removeFromSelected(id) {
    form.member_ids = form.member_ids.filter((m) => m !== String(id));
}

function getDeptName(deptId) {
    const d = departments.value.find((d) => String(d.id) === String(deptId));
    return d ? d.name : '';
}

function getAssignName(assignId) {
    const a = assignments.value.find((a) => String(a.id) === String(assignId));
    return a ? a.name : '';
}

const ROLE_BADGE = {
    superadmin:  { text: 'SA',        cls: 'bg-yellow-100 text-yellow-800' },
    admin:       { text: 'admin',     cls: 'bg-red-100 text-red-700' },
    leader:      { text: 'リーダー', cls: 'bg-orange-100 text-orange-700' },
    coordinator: { text: '進行',      cls: 'bg-green-100 text-green-700' },
    clerk:       { text: '事務',      cls: 'bg-purple-100 text-purple-700' },
};

function roleBadge(role) {
    return ROLE_BADGE[role] || null;
}

const submit = () => {
    form.member_ids = [...new Set([
        ...form.member_ids,
        ...(form.leader_id ? [String(form.leader_id)] : []),
    ])];
    form.post(route('leader.units.store'));
};
</script>

<template>
    <AppLayout title="ユニットチーム作成">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('leader.teams.index')" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300">← チーム一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">新規ユニットチーム作成</h2>
            </div>
        </template>

        <div class="mx-auto max-w-4xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit" class="space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700">ユニット名</label>
                    <input v-model="form.name" class="input mt-1 w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">説明</label>
                    <textarea v-model="form.description" class="textarea mt-1 w-full"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">リーダー（代表者）</label>
                    <select v-model="form.leader_id" class="input mt-1 w-full">
                        <option value="">-- 選択 --</option>
                        <option v-for="u in leaders" :key="u.id" :value="u.id">{{ u.name }} ({{ u.user_role }})</option>
                    </select>
                </div>

                <!-- ── メンバー選択 ── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">メンバー（複数選択可・部署横断）</label>

                    <button type="button" @click="openMemberModal"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        メンバーを選択
                    </button>

                    <!-- 選択済みメンバー chips -->
                    <div v-if="selectedMembers.length > 0" class="mt-3 rounded border border-gray-200 bg-gray-50 px-3 py-2">
                        <div class="mb-1 text-xs font-bold text-gray-600">選択中のメンバー（{{ selectedMembers.length }}名）</div>
                        <div class="flex flex-wrap gap-1">
                            <span v-for="m in selectedMembers" :key="m.id"
                                class="inline-flex items-center gap-1 rounded bg-blue-100 px-2 py-1 text-xs text-blue-800">
                                <span v-if="roleBadge(m.user_role)"
                                    :class="['inline-block rounded px-1 text-xs font-semibold', roleBadge(m.user_role).cls]">
                                    {{ roleBadge(m.user_role).text }}
                                </span>
                                {{ m.name }}
                                <button type="button" @click="removeFromSelected(m.id)"
                                    class="ml-0.5 text-blue-400 hover:text-red-600 font-bold leading-none">×</button>
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-gray-400">メンバーが選択されていません</p>
                </div>

                <!-- チームメンバー選択モーダル -->
                <DialogModal :show="showMemberModal" @close="closeMemberModal">
                    <template #title>チームメンバー選択</template>
                    <template #content>
                        <!-- フィルター -->
                        <div class="mb-4 flex items-end gap-3">
                            <div class="flex-1">
                                <label class="mb-1 block text-sm font-medium">部署</label>
                                <select v-model="modalFilterDeptId" class="w-full rounded border px-3 py-2 text-sm"
                                    @change="modalFilterAssignId = ''">
                                    <option value="">-- 全部署 --</option>
                                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="mb-1 block text-sm font-medium">担当</label>
                                <select v-model="modalFilterAssignId" class="w-full rounded border px-3 py-2 text-sm"
                                    :disabled="!modalFilterDeptId">
                                    <option value="">-- 全担当 --</option>
                                    <option v-for="a in modalFilterableAssignments" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
                                </select>
                            </div>
                            <div>
                                <button type="button"
                                    class="rounded bg-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-400"
                                    @click="clearModalFilters">クリア</button>
                            </div>
                        </div>

                        <!-- メンバー一覧 -->
                        <div class="max-h-96 overflow-y-auto rounded border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="sticky top-0 bg-gray-50">
                                    <tr>
                                        <th class="w-8 px-3 py-2">
                                            <input type="checkbox" :checked="allModalChecked" @change="toggleAllModal" />
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500">名前</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500">部署</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500">担当</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="u in filteredModalMembers" :key="u.id"
                                        class="cursor-pointer hover:bg-gray-50"
                                        :class="{ 'bg-blue-50': modalTempIds.includes(String(u.id)) }"
                                        @click="toggleMember(u.id)">
                                        <td class="px-3 py-2" @click.stop>
                                            <input type="checkbox" :value="String(u.id)" v-model="modalTempIds" />
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900">
                                            <span v-if="roleBadge(u.user_role)"
                                                :class="['mr-1 inline-block rounded px-1 py-0.5 text-xs font-semibold', roleBadge(u.user_role).cls]">
                                                {{ roleBadge(u.user_role).text }}
                                            </span>
                                            {{ u.name }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ getDeptName(u.department_id) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ getAssignName(u.assignment_id) }}</td>
                                    </tr>
                                    <tr v-if="filteredModalMembers.length === 0">
                                        <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-400">該当するメンバーがいません</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 選択中 -->
                        <div v-if="modalTempIds.length > 0" class="mt-3 rounded bg-blue-50 p-3">
                            <div class="text-sm font-medium text-blue-700">{{ modalTempIds.length }}人選択中</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span v-for="sid in modalTempIds" :key="sid"
                                    class="inline-flex rounded bg-blue-100 px-2 py-1 text-xs text-blue-700">
                                    {{ users.find(u => String(u.id) === sid)?.name }}
                                </span>
                            </div>
                        </div>
                    </template>
                    <template #footer>
                        <button type="button"
                            class="mr-3 rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="closeMemberModal">キャンセル</button>
                        <button type="button"
                            class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                            @click="confirmMemberSelection">
                            追加（{{ modalTempIds.length }}人）
                        </button>
                    </template>
                </DialogModal>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.can_read_diary" class="rounded border-gray-300 text-orange-600" />
                        <span class="text-sm font-medium text-gray-700">チームリーダーがメンバーの日報を閲覧できる</span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing"
                        class="rounded bg-orange-600 px-4 py-2 text-white hover:bg-orange-700 disabled:opacity-60">
                        {{ form.processing ? '作成中...' : '作成' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
