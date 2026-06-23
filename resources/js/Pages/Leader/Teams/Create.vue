<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

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

// ── フィルターモーダル ──
const showFilterModal = ref(false);
const filterDeptId   = ref('');
const filterAssignId = ref('');

const filterableAssignments = computed(() => {
    if (!filterDeptId.value) return [];
    return assignments.value.filter((a) => String(a.department_id) === String(filterDeptId.value));
});

const ALWAYS_VISIBLE_ROLES = ['superadmin', 'admin'];

const filteredDisplayMembers = computed(() => {
    const list = users.value.filter((u) => String(u.id) !== String(form.leader_id));
    if (!filterDeptId.value && !filterAssignId.value) return list;

    return list.filter((u) => {
        if (form.member_ids.includes(String(u.id))) return true;
        if (ALWAYS_VISIBLE_ROLES.includes(u.user_role)) return true;
        if (filterDeptId.value && String(u.department_id) !== String(filterDeptId.value)) return false;
        if (filterAssignId.value && String(u.assignment_id) !== String(filterAssignId.value)) return false;
        return true;
    });
});

const allFilteredChecked = computed(() =>
    filteredDisplayMembers.value.length > 0 &&
    filteredDisplayMembers.value.every((u) => form.member_ids.includes(String(u.id))),
);

function toggleAll() {
    const ids = filteredDisplayMembers.value.map((u) => String(u.id));
    if (allFilteredChecked.value) {
        form.member_ids = form.member_ids.filter((id) => !ids.includes(id));
    } else {
        form.member_ids = [...new Set([...form.member_ids, ...ids])];
    }
}

function toggleMember(id) {
    const sid = String(id);
    if (form.member_ids.includes(sid)) {
        form.member_ids = form.member_ids.filter((m) => m !== sid);
    } else {
        form.member_ids = [...form.member_ids, sid];
    }
}

function isSelected(id) {
    const sid = String(id);
    return form.member_ids.includes(sid) || (form.leader_id && String(form.leader_id) === sid);
}

const selectedMembers = computed(() =>
    users.value.filter((u) => isSelected(u.id)),
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

function doFilter() { showFilterModal.value = false; }
function clearFilter() { filterDeptId.value = ''; filterAssignId.value = ''; }

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

                <!-- ── メンバー選択（部署横断） ── -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-700">
                            メンバー（複数選択可・部署横断）
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
                                    :class="{ 'bg-blue-50': form.member_ids.includes(String(u.id)) }"
                                    @click="toggleMember(u.id)"
                                >
                                    <td class="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            :value="String(u.id)"
                                            v-model="form.member_ids"
                                            @click.stop
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
