<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';

const props = defineProps({
    companies:   { type: Array, required: true },
    departments: { type: Array, required: true },
    users:       { type: Array, required: true },
    scripts:     { type: Array, required: true },
    assignments: { type: Object, default: () => ({}) },
});

// ─── 会社・部署選択 ─────────────────────────────────────────────
const selectedCompanyId  = ref(null);
const selectedDeptIds    = ref([]);

const filteredDepartments = computed(() =>
    selectedCompanyId.value
        ? props.departments.filter(d => d.company_id === selectedCompanyId.value)
        : []
);

watch(selectedCompanyId, () => {
    selectedDeptIds.value = [];
    selectionMode.value   = 'individual';
    selectedUserIds.value = [];
    selectedScriptIds.value = [];
});

watch(selectedDeptIds, () => {
    selectedUserIds.value = [];
    selectedScriptIds.value = [];
});

function toggleDept(id) {
    const idx = selectedDeptIds.value.indexOf(id);
    if (idx === -1) selectedDeptIds.value.push(id);
    else selectedDeptIds.value.splice(idx, 1);
}

// ─── ユーザー選択 ─────────────────────────────────────────────
const selectionMode = ref('individual'); // 'individual' | 'all'

const usersInSelectedDepts = computed(() => {
    if (!selectedDeptIds.value.length) return [];
    return props.users.filter(u => selectedDeptIds.value.includes(u.department_id));
});

// selectionMode が all になったら全ユーザーを選択状態に
watch(selectionMode, (mode) => {
    if (mode === 'all') {
        selectedUserIds.value = usersInSelectedDepts.value.map(u => u.id);
        refreshScriptIds();
    }
});

watch(usersInSelectedDepts, () => {
    if (selectionMode.value === 'all') {
        selectedUserIds.value = usersInSelectedDepts.value.map(u => u.id);
        refreshScriptIds();
    }
});

const selectedUserIds = ref([]);

function toggleUser(id) {
    const idx = selectedUserIds.value.indexOf(id);
    if (idx === -1) selectedUserIds.value.push(id);
    else selectedUserIds.value.splice(idx, 1);
    refreshScriptIds();
}

// ─── スクリプト選択（選択ユーザーの現在の割り当てを反映） ──────
const selectedScriptIds = ref([]);

function refreshScriptIds() {
    if (!selectedUserIds.value.length) {
        selectedScriptIds.value = [];
        return;
    }
    // 選択ユーザー全員に割り当て済みのスクリプトを初期値に
    const firstUser = selectedUserIds.value[0];
    const firstSet  = new Set(props.assignments[firstUser] ?? []);
    for (const uid of selectedUserIds.value.slice(1)) {
        const set = new Set(props.assignments[uid] ?? []);
        for (const sid of [...firstSet]) {
            if (!set.has(sid)) firstSet.delete(sid);
        }
    }
    selectedScriptIds.value = [...firstSet];
}

function toggleScript(id) {
    const idx = selectedScriptIds.value.indexOf(id);
    if (idx === -1) selectedScriptIds.value.push(id);
    else selectedScriptIds.value.splice(idx, 1);
}

// ─── 保存 ─────────────────────────────────────────────────────
const saving  = ref(false);
const flash   = ref('');

function save() {
    if (!selectedUserIds.value.length) return;
    saving.value = true;
    router.post(route('superadmin.scripts.assign'), {
        user_ids:   selectedUserIds.value,
        script_ids: selectedScriptIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { flash.value = '保存しました。'; setTimeout(() => { flash.value = ''; }, 3000); },
        onFinish:  () => { saving.value = false; },
    });
}

// ─── ソート ───────────────────────────────────────────────────
const sortKey = ref('name');
const sortDir = ref('asc');

function setSort(key) {
    if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else { sortKey.value = key; sortDir.value = 'asc'; }
}

const sortedUsers = computed(() => {
    const deptMap = Object.fromEntries(props.departments.map(d => [d.id, d.name]));
    return [...usersInSelectedDepts.value].sort((a, b) => {
        const va = sortKey.value === 'dept'
            ? (deptMap[a.department_id] ?? '')
            : a.name;
        const vb = sortKey.value === 'dept'
            ? (deptMap[b.department_id] ?? '')
            : b.name;
        const cmp = va.localeCompare(vb, 'ja');
        return sortDir.value === 'asc' ? cmp : -cmp;
    });
});

// ─── ヘルパー ─────────────────────────────────────────────────
const deptName  = (id) => props.departments.find(d => d.id === id)?.name ?? '―';
const roleLabel = (role) => ({
    admin: 'Admin', leader: 'Leader', coordinator: 'Co', clerk: 'Clerk', user: 'User',
})[role] ?? role;
</script>

<template>
    <AppLayout title="スクリプト管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">スクリプト管理</h2>
        </template>
        <template #tabs>
            <SuperAdminNavigationTabs active="scripts" />
        </template>

        <div class="space-y-4">

            <!-- ─── 会社選択 ─── -->
            <div class="rounded bg-white p-5 shadow">
                <label class="mb-1 block text-sm font-medium text-gray-700">会社を選択</label>
                <select
                    v-model="selectedCompanyId"
                    class="rounded border border-gray-300 px-3 py-2 text-sm focus:border-yellow-400 focus:outline-none"
                >
                    <option :value="null">-- 会社を選択してください --</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <template v-if="selectedCompanyId">

                <!-- ─── 部署選択 ─── -->
                <div class="rounded bg-white p-5 shadow">
                    <p class="mb-2 text-sm font-medium text-gray-700">部署を選択（複数可）</p>
                    <div v-if="filteredDepartments.length" class="flex flex-wrap gap-3">
                        <label
                            v-for="d in filteredDepartments" :key="d.id"
                            class="flex cursor-pointer items-center gap-2 rounded border px-3 py-1.5 text-sm"
                            :class="selectedDeptIds.includes(d.id)
                                ? 'border-yellow-400 bg-yellow-50 text-yellow-800'
                                : 'border-gray-200 text-gray-700 hover:border-gray-300'"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedDeptIds.includes(d.id)"
                                @change="toggleDept(d.id)"
                                class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-400"
                            />
                            {{ d.name }}
                        </label>
                    </div>
                    <p v-else class="text-sm text-gray-400">この会社に部署がありません。</p>
                </div>

                <template v-if="selectedDeptIds.length">

                    <!-- ─── ユーザー選択 ─── -->
                    <div class="rounded bg-white p-5 shadow">
                        <!-- ラジオ: 個人 / 部署全員 -->
                        <div class="mb-3 flex items-center gap-4">
                            <p class="text-sm font-medium text-gray-700">対象を選択</p>
                            <label class="flex items-center gap-1.5 text-sm">
                                <input type="radio" v-model="selectionMode" value="individual"
                                    class="border-gray-300 text-yellow-600 focus:ring-yellow-400" />
                                個人
                            </label>
                            <label class="flex items-center gap-1.5 text-sm">
                                <input type="radio" v-model="selectionMode" value="all"
                                    class="border-gray-300 text-yellow-600 focus:ring-yellow-400" />
                                部署全員
                            </label>
                        </div>

                        <div v-if="usersInSelectedDepts.length" class="overflow-hidden rounded border border-gray-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-xs text-gray-600">
                                        <th class="w-8 px-3 py-2"></th>
                                        <th
                                            class="cursor-pointer select-none px-3 py-2 text-left hover:text-gray-900"
                                            @click="setSort('name')"
                                        >
                                            名前
                                            <span v-if="sortKey === 'name'" class="ml-0.5">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                        </th>
                                        <th
                                            class="cursor-pointer select-none px-3 py-2 text-left hover:text-gray-900"
                                            @click="setSort('dept')"
                                        >
                                            部署
                                            <span v-if="sortKey === 'dept'" class="ml-0.5">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                        </th>
                                        <th class="px-3 py-2 text-left">ロール</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="u in sortedUsers" :key="u.id"
                                        class="cursor-pointer hover:bg-gray-50"
                                        :class="selectedUserIds.includes(u.id) ? 'bg-yellow-50' : ''"
                                        @click="toggleUser(u.id)"
                                    >
                                        <td class="px-3 py-2.5 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="selectedUserIds.includes(u.id)"
                                                @click.stop
                                                @change="toggleUser(u.id)"
                                                class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-400"
                                            />
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-gray-800">{{ u.name }}</td>
                                        <td class="px-3 py-2.5 text-gray-600">{{ deptName(u.department_id) }}</td>
                                        <td class="px-3 py-2.5">
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                                                {{ roleLabel(u.user_role) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-sm text-gray-400">選択した部署にユーザーがいません。</p>
                    </div>

                    <!-- ─── スクリプト割り当て ─── -->
                    <div v-if="selectedUserIds.length" class="rounded bg-white p-5 shadow">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-700">
                                割り当てるスクリプト
                                <span class="ml-1 text-xs text-gray-400">（{{ selectedUserIds.length }}名に適用）</span>
                            </p>
                        </div>

                        <div v-if="scripts.length" class="space-y-2">
                            <label
                                v-for="s in scripts" :key="s.id"
                                class="flex cursor-pointer items-start gap-3 rounded border px-4 py-3"
                                :class="selectedScriptIds.includes(s.id)
                                    ? 'border-yellow-400 bg-yellow-50'
                                    : 'border-gray-200 hover:border-gray-300'"
                            >
                                <input
                                    type="checkbox"
                                    :checked="selectedScriptIds.includes(s.id)"
                                    @change="toggleScript(s.id)"
                                    class="mt-0.5 rounded border-gray-300 text-yellow-600 focus:ring-yellow-400"
                                />
                                <div>
                                    <p class="font-medium text-gray-800">{{ s.name }}</p>
                                    <p v-if="s.description" class="mt-0.5 text-xs text-gray-500">{{ s.description }}</p>
                                </div>
                            </label>
                        </div>
                        <p v-else class="text-sm text-gray-400">アクティブなスクリプトがありません。</p>

                        <div class="mt-4 flex items-center gap-3">
                            <button
                                type="button"
                                @click="save"
                                :disabled="saving"
                                class="rounded bg-yellow-500 px-5 py-2 text-sm font-medium text-white hover:bg-yellow-600 disabled:opacity-40"
                            >
                                {{ saving ? '保存中...' : '保存' }}
                            </button>
                            <span v-if="flash" class="text-sm font-medium text-green-600">{{ flash }}</span>
                        </div>
                    </div>

                </template>
            </template>

        </div>
    </AppLayout>
</template>
