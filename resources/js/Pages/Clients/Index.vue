<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const DEPT_COLORS = {
    '情報出版': 'bg-blue-100 text-blue-700',
    '製版':     'bg-green-100 text-green-700',
    'オンデマンド': 'bg-purple-100 text-purple-700',
};

const props = defineProps({
    clients:             Array,
    unregisteredClients: { type: Array, default: null },
    showDormant:         { type: Boolean, default: false },
});

const page = usePage();
const routePrefix = computed(() => {
    try {
        const r = route().current() ?? '';
        if (r.startsWith('admin.')) return 'admin';
        if (r.startsWith('leader.')) return 'leader';
        if (r.startsWith('coordinator.')) return 'coordinator';
    } catch {}
    // fallback: user_role ベース
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

const DEPT_ORDER = ['情報出版', '製版', 'オンデマンド'];
function sortDepts(depts) {
    return [...depts].sort((a, b) => {
        const ai = DEPT_ORDER.indexOf(a.name);
        const bi = DEPT_ORDER.indexOf(b.name);
        if (ai !== -1 && bi !== -1) return ai - bi;
        if (ai !== -1) return -1;
        if (bi !== -1) return 1;
        return a.name.localeCompare(b.name, 'ja');
    });
}

// clients + unregisteredClients 両方から重複なし部署一覧を抽出
const allDepartments = computed(() => {
    const map = new Map();
    [...(props.clients ?? []), ...(props.unregisteredClients ?? [])].forEach(c =>
        (c.departments ?? []).forEach(d => { if (!map.has(d.id)) map.set(d.id, d); })
    );
    return sortDepts([...map.values()]);
});

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

        <!-- 検索ボックス -->
        <div class="mb-3">
            <input
                v-model="searchQuery"
                type="text"
                class="w-full max-w-sm rounded border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-300"
                placeholder="Client ID または名前で検索…"
            />
        </div>

        <!-- 部署フィルターボタン -->
        <div v-if="allDepartments.length > 0" class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                @click="selectedDeptId = ''"
                :class="selectedDeptId === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
            >全部署</button>
            <button
                v-for="dept in allDepartments"
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
                                        :class="DEPT_COLORS[dept.name] ?? 'bg-gray-100 text-gray-600'"
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
                                    <span
                                        v-for="dept in (client.departments ?? [])"
                                        :key="dept.id"
                                        class="mr-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="DEPT_COLORS[dept.name] ?? 'bg-gray-100 text-gray-600'"
                                    >{{ dept.name }}</span>
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
