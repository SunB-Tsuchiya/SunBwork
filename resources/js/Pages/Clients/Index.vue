<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, reactive, watch } from 'vue';

// 通常バッジカラー（非編集モード / 非SA編集モード）: dept.id 順で循環
const DEPT_COLOR_PALETTE = [
    'bg-blue-100 text-blue-700',
    'bg-green-100 text-green-700',
    'bg-purple-100 text-purple-700',
    'bg-orange-100 text-orange-700',
    'bg-pink-100 text-pink-700',
    'bg-yellow-100 text-yellow-700',
];
function deptColor(dept) {
    return DEPT_COLOR_PALETTE[(dept.id - 1) % DEPT_COLOR_PALETTE.length];
}

// SuperAdmin 編集モード: 会社ごとの色パレット
const COMPANY_PALETTES = [
    { active: 'bg-blue-500 text-white border-blue-600',   inactive: 'bg-blue-50 text-blue-600 border-blue-200 hover:bg-blue-100',   legend: 'bg-blue-100 text-blue-700 border-blue-200' },
    { active: 'bg-red-500 text-white border-red-600',     inactive: 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100',       legend: 'bg-red-100 text-red-700 border-red-200' },
    { active: 'bg-green-500 text-white border-green-600', inactive: 'bg-green-50 text-green-600 border-green-200 hover:bg-green-100', legend: 'bg-green-100 text-green-700 border-green-200' },
    { active: 'bg-orange-500 text-white border-orange-600', inactive: 'bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100', legend: 'bg-orange-100 text-orange-700 border-orange-200' },
];

const props = defineProps({
    clients:             Array,
    unregisteredClients: { type: Array, default: null },
    showDormant:         { type: Boolean, default: false },
    departments:         { type: Array, default: () => [] },
    allDepts:            { type: Array, default: () => [] },
});

const page = usePage();
const routePrefix = computed(() => {
    try {
        const r = route().current() ?? '';
        if (r.startsWith('admin.')) return 'admin';
        if (r.startsWith('leader.')) return 'leader';
        if (r.startsWith('coordinator.')) return 'coordinator';
    } catch {}
    const role = page.props.auth?.user?.user_role ?? 'leader';
    if (['admin', 'superadmin'].includes(role)) return 'admin';
    if (['coordinator', 'clerk'].includes(role)) return 'coordinator';
    return 'leader';
});

const isLeaderView = computed(() => props.unregisteredClients !== null);

const searchQuery = ref('');
const selectedDeptId = ref('');

function matchesQuery(client, q) {
    if (!q) return true;
    const lower = q.toLowerCase();
    return (client.client_code ?? '').toLowerCase().includes(lower)
        || (client.name ?? '').toLowerCase().includes(lower);
}

function matchesDept(client) {
    if (!selectedDeptId.value) return true;
    return (client.departments ?? []).some(d => String(d.id) === selectedDeptId.value);
}

// ===== 編集モード =====
const editMode = ref(false);
const isSuperAdmin = computed(() => page.props.auth?.user?.user_role === 'superadmin');

// クライアントのローカルコピー（楽観的更新用）
const clientsState = reactive(
    Object.fromEntries((props.clients ?? []).map(c => [
        c.id,
        { departmentIds: (c.departments ?? []).map(d => d.id) },
    ]))
);

// props.clients が Inertia reload で更新された時に clientsState を同期
watch(() => props.clients, (newClients) => {
    for (const c of (newClients ?? [])) {
        clientsState[c.id] = { departmentIds: (c.departments ?? []).map(d => d.id) };
    }
});

// 編集モードOFF → リロードして変更を反映
function toggleEditMode() {
    if (editMode.value) {
        editMode.value = false;
        router.reload({ only: ['clients'] });
    } else {
        editMode.value = true;
    }
}

function hasDept(client, deptId) {
    return (clientsState[client.id]?.departmentIds ?? []).includes(deptId);
}

async function toggleDept(client, deptId) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const ids = clientsState[client.id].departmentIds;
    const idx = ids.indexOf(deptId);
    if (idx >= 0) ids.splice(idx, 1); else ids.push(deptId);

    try {
        await fetch(route(`${routePrefix.value}.clients.toggle_dept`, { client: client.id }), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ department_id: deptId }),
        });
    } catch {
        if (idx >= 0) ids.push(deptId); else ids.splice(ids.indexOf(deptId), 1);
    }
}

// SuperAdmin 用: 会社ごとの色パレットマッピング（allDepts の company_id 出現順）
const companyColorMap = computed(() => {
    const map = {};
    let idx = 0;
    for (const dept of props.allDepts) {
        if (!(dept.company_id in map)) {
            map[dept.company_id] = {
                palette:      COMPANY_PALETTES[idx % COMPANY_PALETTES.length],
                company_name: dept.company_name,
            };
            idx++;
        }
    }
    return map;
});

// 凡例用: 会社 → パレット一覧
const legendCompanies = computed(() =>
    Object.entries(companyColorMap.value).map(([cid, info]) => ({
        company_id:   parseInt(cid),
        company_name: info.company_name,
        palette:      info.palette,
    }))
);

function getSaPalette(dept) {
    return companyColorMap.value[dept.company_id]?.palette ?? COMPANY_PALETTES[0];
}

const filteredClients = computed(() =>
    props.clients.filter(c => matchesQuery(c, searchQuery.value) && matchesDept(c)),
);

const filteredUnregisteredClients = computed(() =>
    (props.unregisteredClients ?? []).filter(c => matchesQuery(c, searchQuery.value) && matchesDept(c)),
);

function toggleDormantView() {
    router.get(
        route(`${routePrefix.value}.clients.index`),
        props.showDormant ? {} : { dormant: 1 },
        { preserveState: false },
    );
}

function toggleDepartment(clientId) {
    router.post(
        route(`${routePrefix.value}.clients.toggle_department`, clientId),
        {},
        { preserveScroll: true },
    );
}

function goToEdit(clientId) {
    router.visit(route(`${routePrefix.value}.clients.edit`, { client: clientId }));
}
</script>

<template>
    <AppLayout title="クライアント一覧">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                クライアント管理
                <span v-if="props.showDormant" class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-sm font-normal text-gray-600">休眠一覧</span>
            </h2>
        </template>
        <template #headerExtras>
            <div class="flex items-center gap-4">
                <!-- 編集モードボタン（SuperAdmin: 会社間共有 / その他: 部署間共有） -->
                <button
                    v-if="!props.showDormant"
                    type="button"
                    class="rounded px-4 py-2 text-sm font-medium transition-colors"
                    :class="editMode
                        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                        : 'bg-white border border-indigo-400 text-indigo-600 hover:bg-indigo-50'"
                    @click="toggleEditMode"
                >
                    {{ editMode ? '編集モード ON' : '編集モード OFF' }}
                </button>
                <button
                    type="button"
                    class="rounded px-4 py-2 text-sm font-medium text-white"
                    :class="props.showDormant
                        ? 'bg-blue-600 hover:bg-blue-700'
                        : 'bg-gray-500 hover:bg-gray-600'"
                    @click="toggleDormantView"
                >
                    {{ props.showDormant ? '通常一覧を表示' : '休眠を表示' }}
                </button>
                <Link
                    v-if="!props.showDormant"
                    :href="route(`${routePrefix}.clients.duplicate_check`)"
                    class="rounded bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700"
                >重複チェック</Link>
                <Link
                    v-if="!props.showDormant"
                    :href="route(`${routePrefix}.clients.create`)"
                    class="rounded bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700"
                >新規作成</Link>
            </div>
        </template>

        <!-- SuperAdmin 編集モード時の会社色凡例 -->
        <div v-if="editMode && isSuperAdmin && legendCompanies.length > 0" class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-2 text-sm">
            <span class="mr-1 text-xs font-semibold text-indigo-500">会社凡例:</span>
            <span
                v-for="co in legendCompanies"
                :key="co.company_id"
                class="rounded-full border px-3 py-0.5 text-xs font-medium"
                :class="co.palette.legend"
            >{{ co.company_name }}</span>
            <span class="ml-2 text-xs text-indigo-400">●=紐付き済み ○=未紐付き</span>
        </div>

        <!-- 検索ボックス -->
        <div class="mb-3">
            <input
                v-model="searchQuery"
                type="text"
                class="w-full max-w-sm rounded border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-300"
                placeholder="Client ID または名前で検索…"
            />
        </div>

        <!-- 部署フィルターボタン（サーバーから自社部署のみ受け取る） -->
        <div v-if="props.departments.length > 0" class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                @click="selectedDeptId = ''"
                :class="selectedDeptId === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
            >全部署</button>
            <button
                v-for="dept in props.departments"
                :key="dept.id"
                type="button"
                @click="selectedDeptId = String(dept.id)"
                :class="selectedDeptId === String(dept.id) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
            >{{ dept.name }}</button>
        </div>

        <!-- ── Leader: 2セクション表示 ── -->
        <template v-if="isLeaderView">
            <!-- 登録済み -->
            <div class="rounded bg-white shadow overflow-hidden mb-4">
                <div class="flex items-center justify-between bg-green-50 px-6 py-3 border-b border-green-100">
                    <h3 class="font-semibold text-green-800">自部署に登録済み
                        <span class="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs font-normal text-green-700">{{ filteredClients.length }}</span>
                    </h3>
                </div>
                <template v-if="filteredClients.length === 0">
                    <p class="px-6 py-8 text-sm text-gray-400">{{ searchQuery ? '該当するクライアントはありません' : '登録済みのクライアントはありません' }}</p>
                </template>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Client ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">詳細</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="client in filteredClients"
                                :key="client.id"
                                class="hover:bg-green-50/40 cursor-pointer"
                                @click="goToEdit(client.id)"
                            >
                                <td class="whitespace-nowrap px-6 py-3 text-gray-500 text-xs">{{ client.client_code || '―' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 font-medium text-gray-900">
                                    {{ client.name }}
                                    <span v-if="client.is_dormant" class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-500">休眠</span>
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ client.notes || '' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="rounded border border-red-300 px-3 py-1 text-xs text-red-600 hover:bg-red-50"
                                        @click.stop="toggleDepartment(client.id)"
                                    >外す</button>
                                    <Link :href="route(`${routePrefix}.clients.edit`, { client: client.id })" class="text-blue-600 hover:text-blue-900 text-xs" @click.stop>編集</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 未登録 -->
            <div class="rounded bg-white shadow overflow-hidden">
                <div class="flex items-center justify-between bg-gray-50 px-6 py-3 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-600">未登録（他部署のクライアント）
                        <span class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-xs font-normal text-gray-500">{{ filteredUnregisteredClients.length }}</span>
                    </h3>
                </div>
                <template v-if="filteredUnregisteredClients.length === 0">
                    <p class="px-6 py-8 text-sm text-gray-400">{{ searchQuery ? '該当するクライアントはありません' : '未登録のクライアントはありません' }}</p>
                </template>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Client ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">詳細</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="client in filteredUnregisteredClients"
                                :key="client.id"
                                class="hover:bg-gray-50 cursor-pointer"
                                @click="goToEdit(client.id)"
                            >
                                <td class="whitespace-nowrap px-6 py-3 text-gray-500 text-xs">{{ client.client_code || '―' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-gray-700">{{ client.name }}</td>
                                <td class="px-6 py-3">
                                    <span
                                        v-for="dept in (client.departments ?? [])"
                                        :key="dept.id"
                                        class="mr-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="deptColor(dept)"
                                    >{{ dept.name }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ client.notes || '' }}</td>
                                <td class="whitespace-nowrap px-6 py-3">
                                    <button
                                        type="button"
                                        class="rounded border border-green-600 px-3 py-1 text-xs text-green-700 hover:bg-green-50"
                                        @click.stop="toggleDepartment(client.id)"
                                    >自部署に追加</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- ── Admin / Coordinator など: 通常1テーブル表示 ── -->
        <div v-else class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <template v-if="filteredClients.length === 0">
                <p class="py-8 text-gray-500">
                    {{ searchQuery ? '該当するクライアントはありません' : (props.showDormant ? '休眠クライアントはありません' : 'クライアントはまだ登録されていません') }}
                </p>
            </template>
            <template v-else>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Client ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">詳細</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="client in filteredClients"
                                :key="client.id"
                                class="hover:bg-gray-50 cursor-pointer"
                                @click="goToEdit(client.id)"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ client.client_code || '―' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ client.name }}
                                    <span v-if="client.is_dormant" class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-500">休眠</span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <!-- 通常表示: 現在紐付いている部署バッジ -->
                                    <template v-if="!editMode">
                                        <span
                                            v-for="dept in (client.departments ?? [])"
                                            :key="dept.id"
                                            class="mr-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="deptColor(dept)"
                                        >{{ dept.name }}</span>
                                    </template>
                                    <!-- 編集モード(SuperAdmin): 全社の部署を会社色でトグル -->
                                    <template v-else-if="isSuperAdmin">
                                        <button
                                            v-for="dept in props.allDepts"
                                            :key="dept.id"
                                            type="button"
                                            class="mr-1 mb-1 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium transition-colors"
                                            :class="hasDept(client, dept.id)
                                                ? getSaPalette(dept).active
                                                : getSaPalette(dept).inactive"
                                            @click.stop="toggleDept(client, dept.id)"
                                        >
                                            {{ hasDept(client, dept.id) ? '●' : '○' }} {{ dept.name }}
                                        </button>
                                    </template>
                                    <!-- 編集モード(Admin等): 自社部署トグルボタン -->
                                    <template v-else>
                                        <button
                                            v-for="dept in props.departments"
                                            :key="dept.id"
                                            type="button"
                                            class="mr-1 mb-1 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium transition-colors"
                                            :class="hasDept(client, dept.id)
                                                ? `${deptColor(dept)} border-transparent`
                                                : 'bg-white text-gray-400 border-gray-300 hover:border-gray-400'"
                                            @click.stop="toggleDept(client, dept.id)"
                                        >
                                            {{ hasDept(client, dept.id) ? '●' : '○' }} {{ dept.name }}
                                        </button>
                                    </template>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ client.detail || client.notes || '' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <Link :href="route(`${routePrefix}.clients.edit`, { client: client.id })" class="text-blue-600 hover:text-blue-900" @click.stop>編集</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
