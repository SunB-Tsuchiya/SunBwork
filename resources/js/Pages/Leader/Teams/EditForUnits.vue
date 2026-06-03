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

// メンバー選択状態は useForm の配列と切り離し、独立した ref で管理
// → Inertia reactive proxy と v-model の相性問題を回避
const selectedIds = ref([...initMemberIds]);

const form = useForm({
    name:           team.name || '',
    company_id:     unit?.company_id ?? team.company_id ?? '',
    department_id:  unit?.department_id ?? team.department_id ?? '',
    description:    unit?.description ?? team.description ?? '',
    leader_id:      unit?.leader_id ? String(unit.leader_id) : null,
    sub_leader_ids: (props.sub_leader_ids || []).map(String),
    member_ids:     [...initMemberIds],
});

const availableDepartments = computed(() => {
    if (!form.company_id) return [];
    return departments.value.filter((d) => d.company_id === Number(form.company_id));
});

onMounted(() => {
    if (companies.value.length === 1 && !form.company_id) form.company_id = companies.value[0].id;
});

// ── フィルターモーダル ──
const showFilterModal = ref(false);
const filterDeptId   = ref('');
const filterAssignId = ref('');

const filterableAssignments = computed(() => {
    if (!filterDeptId.value) return [];
    return assignments.value.filter((a) => String(a.department_id) === String(filterDeptId.value));
});

// admin/leader/superadmin は部署フィルター対象外（常に表示）
const ALWAYS_VISIBLE_ROLES = ['superadmin', 'admin', 'leader'];

const filteredDisplayMembers = computed(() => {
    const list = users.value;
    if (!filterDeptId.value && !filterAssignId.value) return list;

    return list.filter((u) => {
        if (selectedIds.value.includes(String(u.id))) return true;
        if (ALWAYS_VISIBLE_ROLES.includes(u.user_role)) return true;
        if (filterDeptId.value && String(u.department_id) !== String(filterDeptId.value)) return false;
        if (filterAssignId.value && String(u.assignment_id) !== String(filterAssignId.value)) return false;
        return true;
    });
});

const allFilteredChecked = computed(() =>
    filteredDisplayMembers.value.length > 0 &&
    filteredDisplayMembers.value.every((u) => selectedIds.value.includes(String(u.id))),
);

function toggleAll() {
    const ids = filteredDisplayMembers.value.map((u) => String(u.id));
    if (allFilteredChecked.value) {
        selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id));
    } else {
        selectedIds.value = [...new Set([...selectedIds.value, ...ids])];
    }
}

function toggleMember(id) {
    const sid = String(id);
    if (selectedIds.value.includes(sid)) {
        selectedIds.value = selectedIds.value.filter((m) => m !== sid);
    } else {
        selectedIds.value = [...selectedIds.value, sid];
    }
}

function isSelected(id) {
    const sid = String(id);
    return selectedIds.value.includes(sid)
        || (form.leader_id && String(form.leader_id) === sid)
        || form.sub_leader_ids.map(String).includes(sid);
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

function doFilter() { showFilterModal.value = false; }
function clearFilter() { filterDeptId.value = ''; filterAssignId.value = ''; }

const submit = () => {
    form.member_ids = [...new Set([
        ...selectedIds.value,
        ...(form.leader_id ? [String(form.leader_id)] : []),
        ...form.sub_leader_ids.map(String),
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

            <div>
                <label class="block text-sm font-medium text-gray-700">サブリーダー（副代表・複数可）</label>
                <div class="mt-2 space-y-1 rounded border border-gray-200 p-3">
                    <div v-if="leaders.length === 0" class="text-sm text-gray-400">候補ユーザーがいません</div>
                    <label
                        v-for="u in leaders.filter((l) => String(l.id) !== form.leader_id)"
                        :key="u.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            :value="String(u.id)"
                            v-model="form.sub_leader_ids"
                            class="rounded border-gray-300 text-orange-600"
                        />
                        {{ u.name }} ({{ u.user_role }})
                    </label>
                </div>
            </div>

            <!-- ── メンバー選択（部署横断） ── -->
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <label class="block text-sm font-medium text-gray-700">
                        メンバー（複数選択可・部署横断）
                        <span class="ml-1 text-xs text-gray-400">※ admin/リーダーは部署フィルターを無視して常に表示</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showFilterModal = true"
                            class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700">絞り込み</button>
                        <button type="button" @click="clearFilter"
                            class="rounded bg-gray-300 px-3 py-1.5 text-xs font-bold text-gray-800 hover:bg-gray-400">クリア</button>
                    </div>
                </div>

                <DialogModal :show="showFilterModal" @close="showFilterModal = false">
                    <template #title>メンバー絞り込み</template>
                    <template #content>
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-semibold">部署</label>
                            <select v-model="filterDeptId" class="w-full rounded border px-3 py-2 text-sm"
                                @change="filterAssignId = ''">
                                <option value="">-- 部署を選択 --</option>
                                <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold">担当</label>
                            <select v-model="filterAssignId" class="w-full rounded border px-3 py-2 text-sm"
                                :disabled="!filterDeptId">
                                <option value="">-- 担当を選択 --</option>
                                <option v-for="a in filterableAssignments" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
                            </select>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">※ admin/リーダーは部署フィルター対象外で常に表示されます</p>
                    </template>
                    <template #footer>
                        <button type="button" class="mr-2 rounded bg-gray-300 px-4 py-2 text-sm"
                            @click="showFilterModal = false">閉じる</button>
                        <button type="button" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white"
                            @click="doFilter">絞り込み</button>
                    </template>
                </DialogModal>

                <!-- メンバー一覧テーブル -->
                <div class="overflow-x-auto rounded border border-gray-200">
                    <div class="border-b bg-gray-50 px-3 py-2 text-xs font-bold text-gray-600">
                        メンバー一覧（{{ filteredDisplayMembers.length }}件表示）
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-8 px-3 py-2">
                                    <input type="checkbox" :checked="allFilteredChecked" @change="toggleAll" />
                                </th>
                                <th class="px-3 py-2 text-left text-xs text-gray-500">名前</th>
                                <th class="px-3 py-2 text-left text-xs text-gray-500">部署</th>
                                <th class="px-3 py-2 text-left text-xs text-gray-500">担当</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr
                                v-for="u in filteredDisplayMembers"
                                :key="u.id"
                                class="cursor-pointer hover:bg-gray-50"
                                :class="{ 'bg-blue-50': isSelected(u.id) }"
                                @click="toggleMember(u.id)"
                            >
                                <td class="px-3 py-2" @click.stop="toggleMember(u.id)">
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(u.id)"
                                        @click.prevent
                                    />
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900">
                                    <span v-if="roleBadge(u.user_role)"
                                        :class="['mr-1 inline-block rounded px-1 py-0.5 text-xs font-semibold', roleBadge(u.user_role).cls]">
                                        {{ roleBadge(u.user_role).text }}
                                    </span>
                                    {{ u.name }}
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ getDeptName(u.department_id) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ getAssignName(u.assignment_id) }}</td>
                            </tr>
                            <tr v-if="filteredDisplayMembers.length === 0">
                                <td colspan="4" class="px-3 py-4 text-center text-sm text-gray-400">該当するメンバーがいません</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 選択中のメンバー -->
                <div class="mt-3 overflow-x-auto rounded border border-gray-200">
                    <div class="border-b bg-gray-50 px-3 py-2 text-xs font-bold text-gray-600">
                        選択中のメンバー（{{ selectedMembers.length }}名）
                    </div>
                    <table v-if="selectedMembers.length > 0" class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs text-gray-500">名前</th>
                                <th class="px-3 py-2 text-left text-xs text-gray-500">部署</th>
                                <th class="px-3 py-2 text-left text-xs text-gray-500">担当</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="m in selectedMembers" :key="m.id" class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-900">
                                    <span v-if="roleBadge(m.user_role)"
                                        :class="['mr-1 inline-block rounded px-1 py-0.5 text-xs font-semibold', roleBadge(m.user_role).cls]">
                                        {{ roleBadge(m.user_role).text }}
                                    </span>
                                    {{ m.name }}
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ getDeptName(m.department_id) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ getAssignName(m.assignment_id) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="text-xs text-red-600 hover:text-red-800"
                                        @click="removeFromSelected(m.id)">削除</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-3 py-3 text-sm text-gray-400">選択されていません</p>
                </div>
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
