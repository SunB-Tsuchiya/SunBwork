<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = usePage().props;

const leaders     = ref(props.leaders || []);
const companies   = ref(props.companies || []);
const departments = ref(props.departments || []);
const users       = ref(props.users || []);

const form = useForm({
    name:        '',
    description: '',
    leader_id:   '',
    member_ids:  [],
});

// ── 絞り込み ──
const filterCompanyId = ref('');
const filterDeptId    = ref('');

const filteredDepts = computed(() => {
    if (!filterCompanyId.value) return [];
    return departments.value.filter((d) => String(d.company_id) === String(filterCompanyId.value));
});

const filteredUsers = computed(() => {
    return users.value.filter((u) => {
        if (String(u.id) === String(form.leader_id)) return false;
        if (filterCompanyId.value && String(u.company_id) !== String(filterCompanyId.value)) return false;
        if (filterDeptId.value === '__all__') {
            // 会社内の全部署
            return String(u.company_id) === String(filterCompanyId.value);
        }
        if (filterDeptId.value && String(u.department_id) !== String(filterDeptId.value)) return false;
        if (!filterCompanyId.value) return true;
        return true;
    });
});

watch(() => filterCompanyId.value, () => { filterDeptId.value = ''; });
watch(() => form.leader_id, (newId) => {
    form.member_ids = form.member_ids.filter((id) => String(id) !== String(newId));
});

function getCompanyName(cid) {
    return companies.value.find((c) => String(c.id) === String(cid))?.name ?? '';
}
function getDeptName(did) {
    return departments.value.find((d) => String(d.id) === String(did))?.name ?? '';
}

const ROLE_BADGE = {
    superadmin:  { text: 'SA',        cls: 'bg-yellow-100 text-yellow-800' },
    admin:       { text: 'admin',     cls: 'bg-red-100 text-red-700' },
    leader:      { text: 'リーダー', cls: 'bg-orange-100 text-orange-700' },
    coordinator: { text: '進行',      cls: 'bg-green-100 text-green-700' },
    clerk:       { text: '事務',      cls: 'bg-purple-100 text-purple-700' },
};
function roleBadge(role) { return ROLE_BADGE[role] || null; }

const allChecked = computed(() =>
    filteredUsers.value.length > 0 &&
    filteredUsers.value.every((u) => form.member_ids.includes(String(u.id))),
);

function toggleAll() {
    const ids = filteredUsers.value.map((u) => String(u.id));
    if (allChecked.value) {
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
    return form.member_ids.includes(String(id)) || String(form.leader_id) === String(id);
}

const selectedMembers = computed(() =>
    users.value.filter((u) => isSelected(u.id)),
);

function removeFromSelected(id) {
    form.member_ids = form.member_ids.filter((m) => m !== String(id));
}

const submit = () => {
    form.member_ids = [...new Set([
        ...form.member_ids,
        ...(form.leader_id ? [String(form.leader_id)] : []),
    ])];
    form.post(route('admin.special_teams.store'));
};
</script>

<template>
    <AppLayout title="特別チーム作成">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.special_teams.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">特別チーム作成</h2>
            </div>
        </template>

        <div class="mx-auto max-w-4xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700">チーム名 <span class="text-red-500">*</span></label>
                    <input v-model="form.name" class="input mt-1 w-full" required />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">説明</label>
                    <textarea v-model="form.description" rows="2" class="textarea mt-1 w-full"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">リーダー</label>
                    <select v-model="form.leader_id" class="input mt-1 w-full">
                        <option value="">-- 選択 --</option>
                        <option v-for="u in leaders" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.user_role }})
                        </option>
                    </select>
                </div>

                <!-- ── メンバー選択 ── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">メンバー選択（会社横断）</label>

                    <!-- 絞り込みコントロール -->
                    <div class="mb-3 flex flex-wrap gap-3 rounded bg-gray-50 p-3">
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">会社で絞り込み</label>
                            <select v-model="filterCompanyId" class="w-full rounded border px-2 py-1.5 text-sm">
                                <option value="">— すべての会社 —</option>
                                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">部署で絞り込み</label>
                            <select v-model="filterDeptId" class="w-full rounded border px-2 py-1.5 text-sm"
                                :disabled="!filterCompanyId">
                                <option value="">— 部署を選択 —</option>
                                <option value="__all__">（会社内すべて）</option>
                                <option v-for="d in filteredDepts" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- メンバー一覧テーブル -->
                    <div class="overflow-x-auto rounded border border-gray-200">
                        <div class="border-b bg-gray-50 px-3 py-2 text-xs font-bold text-gray-600">
                            {{ filterCompanyId || filterDeptId ? `絞り込み結果: ${filteredUsers.length}件` : `全ユーザー: ${filteredUsers.length}件` }}
                            <span class="ml-2 font-normal text-gray-400">（会社・部署を選択して絞り込めます）</span>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-8 px-3 py-2">
                                        <input type="checkbox" :checked="allChecked" @change="toggleAll"
                                            :disabled="filteredUsers.length === 0" />
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-500">名前</th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-500">会社</th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-500">部署</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="u in filteredUsers" :key="u.id"
                                    class="cursor-pointer hover:bg-gray-50"
                                    :class="{ 'bg-blue-50': isSelected(u.id) }"
                                    @click="toggleMember(u.id)">
                                    <td class="px-3 py-2" @click.stop="toggleMember(u.id)">
                                        <input type="checkbox" :checked="isSelected(u.id)" @click.prevent />
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        <span v-if="roleBadge(u.user_role)"
                                            :class="['mr-1 inline-block rounded px-1 py-0.5 text-xs font-semibold', roleBadge(u.user_role).cls]">
                                            {{ roleBadge(u.user_role).text }}
                                        </span>
                                        {{ u.name }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-500 text-xs">{{ getCompanyName(u.company_id) }}</td>
                                    <td class="px-3 py-2 text-gray-500 text-xs">{{ getDeptName(u.department_id) }}</td>
                                </tr>
                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="4" class="px-3 py-4 text-center text-sm text-gray-400">
                                        {{ filterCompanyId ? '該当するユーザーがいません' : '会社を選択してください' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 選択済みメンバー -->
                    <div class="mt-3 overflow-x-auto rounded border border-gray-200">
                        <div class="border-b bg-gray-50 px-3 py-2 text-xs font-bold text-gray-600">
                            選択中のメンバー（{{ selectedMembers.length }}名）
                        </div>
                        <table v-if="selectedMembers.length > 0" class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs text-gray-500">名前</th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-500">会社</th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-500">部署</th>
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
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ getCompanyName(m.company_id) }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ getDeptName(m.department_id) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" @click="removeFromSelected(m.id)"
                                            class="text-xs text-red-600 hover:text-red-800">削除</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="px-3 py-3 text-sm text-gray-400">選択されていません</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing"
                        class="rounded bg-red-600 px-5 py-2 text-white hover:bg-red-700 disabled:opacity-60">
                        {{ form.processing ? '作成中...' : '作成' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
