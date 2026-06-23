<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

const page = usePage();
const props = page.props;
const team = props.team || {};
const unit = props.unit || null;

const companies   = ref(props.companies || []);
const departments = ref(props.departments || []);
const assignments = ref(props.assignments || []);
const users       = ref(props.users || []);
const leaders     = ref(props.leaders || []);

// team_user を正とした初期メンバー ID（文字列）
const initMemberIds = (
    props.current_member_ids?.length
        ? props.current_member_ids
        : (unit?.members?.map((m) => String(m.id)) || [])
).map(String);

// メンバー選択状態は独立した ref で管理
const selectedIds = ref([...initMemberIds]);

const form = useForm({
    name:           team.name || '',
    company_id:     unit?.company_id ?? team.company_id ?? '',
    department_id:  unit?.department_id ?? team.department_id ?? '',
    description:    unit?.description ?? team.description ?? '',
    leader_id:      unit?.leader_id ? String(unit.leader_id) : null,
    member_ids:     [...initMemberIds],
    can_read_diary: team.can_read_diary !== false,
});

const availableDepartments = computed(() => {
    if (!form.company_id) return [];
    return departments.value.filter((d) => d.company_id === Number(form.company_id));
});

onMounted(() => {
    if (companies.value.length === 1 && !form.company_id) form.company_id = companies.value[0].id;
});

// ── メンバー選択モーダル ──
const showMemberModal     = ref(false);
const modalFilterDeptId   = ref('');
const modalFilterAssignId = ref('');
const modalTempIds        = ref([]);

const modalFilterableAssignments = computed(() => {
    if (!modalFilterDeptId.value) return [];
    return assignments.value.filter((a) => String(a.department_id) === String(modalFilterDeptId.value));
});

const filteredModalMembers = computed(() => {
    const list = users.value;
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
    modalTempIds.value = [...selectedIds.value];
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
    selectedIds.value = [...modalTempIds.value];
    closeMemberModal();
}

function isSelected(id) {
    const sid = String(id);
    return selectedIds.value.includes(sid)
        || (form.leader_id && String(form.leader_id) === sid);
}

const selectedMembers = computed(() =>
    users.value.filter((u) => isSelected(u.id)),
);

function removeFromSelected(id) {
    selectedIds.value = selectedIds.value.filter((m) => m !== String(id));
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
        ...selectedIds.value,
        ...(form.leader_id ? [String(form.leader_id)] : []),
    ])];
    form.put(route('leader.teams.update', { team: team.id }));
};
</script>

<template>
    <div class="mx-auto max-w-4xl rounded bg-white px-4 py-6 sm:p-6 shadow">
        <form @submit.prevent="submit" class="space-y-4">

            <div>
                <label class="block text-sm font-medium text-gray-700">会社</label>
                <select v-model="form.company_id" class="input mt-1 w-full">
                    <option value="">-- 選択 --</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">部署</label>
                <select v-model="form.department_id" class="input mt-1 w-full">
                    <option value="">-- 選択 --</option>
                    <option v-for="d in availableDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>

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
                    <option v-for="u in leaders" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.user_role }})</option>
                </select>
            </div>

            <!-- ── メンバー選択 ── -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    メンバー（複数選択可・部署横断）
                </label>

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
                    {{ form.processing ? '更新中...' : '更新' }}
                </button>
            </div>
        </form>
    </div>
</template>
