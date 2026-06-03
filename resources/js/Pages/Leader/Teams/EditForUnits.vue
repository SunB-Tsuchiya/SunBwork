<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const props = page.props;
const team = props.team || {};
const unit = props.unit || null;

const companies   = ref(props.companies || []);
const departments = ref(props.departments || []);
const assignments = ref(props.assignments || []);
const users       = ref(props.users || []);
const leaders     = ref(props.leaders || []);

// team_user を正とした初期メンバー（なければ unit.members にフォールバック）
const initMemberIds = (
    props.current_member_ids?.length
        ? props.current_member_ids
        : (unit?.members?.map((m) => String(m.id)) || [])
).map(String);

const form = useForm({
    name:           team.name || '',
    company_id:     unit?.company_id ?? team.company_id ?? '',
    department_id:  unit?.department_id ?? team.department_id ?? '',
    description:    unit?.description ?? team.description ?? '',
    leader_id:      unit?.leader_id ? String(unit.leader_id) : null,
    sub_leader_ids: (props.sub_leader_ids || []).map(String),
    member_ids:     initMemberIds,
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

const filteredDisplayMembers = computed(() => {
    let list = users.value.filter((u) => !u.is_ghost);
    if (filterDeptId.value)
        list = list.filter((u) => String(u.department_id) === String(filterDeptId.value));
    if (filterAssignId.value)
        list = list.filter((u) => String(u.assignment_id) === String(filterAssignId.value));
    return list;
});

const allChecked = computed(() =>
    filteredDisplayMembers.value.length > 0 &&
    filteredDisplayMembers.value.every((u) => form.member_ids.includes(String(u.id))),
);

function toggleAll() {
    const ids = filteredDisplayMembers.value.map((u) => String(u.id));
    if (allChecked.value) {
        form.member_ids = form.member_ids.filter((id) => !ids.includes(id));
    } else {
        form.member_ids = [...new Set([...form.member_ids, ...ids])];
    }
}

function toggleMember(id) {
    const sid = String(id);
    const idx = form.member_ids.indexOf(sid);
    if (idx === -1) form.member_ids.push(sid);
    else            form.member_ids.splice(idx, 1);
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

function doFilter() { showFilterModal.value = false; }
function clearFilter() { filterDeptId.value = ''; filterAssignId.value = ''; }

const submit = () => {
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

            <!-- メンバー選択（部署横断） -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">メンバー（複数選択可・部署横断）</label>
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
                    <div class="border-b bg-gray-50 px-3 py-2 text-xs font-bold text-gray-600">メンバー一覧</div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-8 px-3 py-2 text-left">
                                    <input type="checkbox" :checked="allChecked" @change="toggleAll" />
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
                                :class="{ 'bg-blue-50': form.member_ids.includes(String(u.id)) }"
                                @click="toggleMember(u.id)"
                            >
                                <td class="px-3 py-2" @click.stop>
                                    <input type="checkbox" :value="String(u.id)" v-model="form.member_ids" />
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ u.name }}</td>
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
                                <td class="px-3 py-2 font-medium text-gray-900">{{ m.name }}</td>
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
