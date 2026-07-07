<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    departmentTeams: { type: Array, default: () => [] },
    specialTeams:    { type: Array, default: () => [] },
    unitTeams:       { type: Array, default: () => [] },
});

const STORAGE_KEYS = {
    department: 'team-rooms-order-dept',
    special:    'team-rooms-order-special',
    unit:       'team-rooms-order-unit',
};

function loadOrder(key) {
    try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; }
}

function saveOrder(key, teams) {
    localStorage.setItem(key, JSON.stringify(teams.map(t => t.id)));
}

function applyOrder(teams, savedIds) {
    if (!savedIds?.length) return [...teams];
    const map = Object.fromEntries(teams.map(t => [t.id, t]));
    const ordered = savedIds.filter(id => map[id]).map(id => map[id]);
    const rest = teams.filter(t => !savedIds.includes(t.id));
    return [...ordered, ...rest];
}

const deptTeams = ref([]);
const specTeams = ref([]);
const unitTeamList = ref([]);

onMounted(() => {
    deptTeams.value = applyOrder(props.departmentTeams, loadOrder(STORAGE_KEYS.department));
    specTeams.value = applyOrder(props.specialTeams,    loadOrder(STORAGE_KEYS.special));
    unitTeamList.value = applyOrder(props.unitTeams,       loadOrder(STORAGE_KEYS.unit));
});

// ─── drag & drop ────────────────────────────────────────────────
let dragFromIndex = -1;
let dragSection   = null;

function getList(section) {
    return { department: deptTeams, special: specTeams, unit: unitTeamList }[section];
}

function onDragStart(idx, section, event) {
    dragFromIndex = idx;
    dragSection   = section;
    event.dataTransfer.effectAllowed = 'move';
}

function onDragOver(e, idx, section) {
    if (section !== dragSection || idx === dragFromIndex) return;
    e.preventDefault();
    const list  = getList(section);
    const moved = list.value.splice(dragFromIndex, 1)[0];
    list.value.splice(idx, 0, moved);
    dragFromIndex = idx;
}

function onDragEnd(section) {
    if (section !== dragSection) return;
    saveOrder(STORAGE_KEYS[section], getList(section).value);
    dragFromIndex = -1;
    dragSection   = null;
}

function goToRoom(teamId) {
    router.get(route('team-rooms.show', { team: teamId }));
}
</script>

<template>
    <AppLayout title="チームルーム">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チームルーム</h2>
        </template>

        <div class="space-y-4">
            <!-- 部署チーム -->
            <div v-if="deptTeams.length > 0" class="overflow-hidden rounded border border-blue-200 bg-white shadow-sm">
                <div class="bg-blue-50 px-4 py-2">
                    <h3 class="text-sm font-semibold text-blue-700">部署チーム</h3>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-blue-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-blue-500"></th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-blue-500">チーム名</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-blue-500">部署</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-blue-500">リーダー</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-blue-500">メンバー</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr
                            v-for="(team, idx) in deptTeams"
                            :key="team.id"
                            draggable="true"
                            class="cursor-pointer hover:bg-gray-50"
                            @dragstart="onDragStart(idx, 'department', $event)"
                            @dragover="onDragOver($event, idx, 'department')"
                            @dragend="onDragEnd('department')"
                            @click="goToRoom(team.id)"
                        >
                            <td class="px-2 py-3 text-gray-300 select-none">⠿</td>
                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ team.name }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ team.department?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                <span v-if="team.leader_name" class="flex items-center gap-1">
                                    <span class="inline-block h-2 w-2 rounded-full bg-orange-400"></span>
                                    {{ team.leader_name }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600" style="max-width: 200px;">
                                <span v-if="team.member_names?.length" class="block truncate text-xs">
                                    {{ team.member_names.slice(0, 6).join('、') }}{{ team.member_names.length > 6 ? `　他${team.member_names.length - 6}名` : '' }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap" @click.stop>
                                <Link
                                    :href="route('team-rooms.show', { team: team.id })"
                                    class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                                >ルームへ</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- 特別チーム -->
            <div v-if="specTeams.length > 0" class="overflow-hidden rounded border border-green-200 bg-white shadow-sm">
                <div class="bg-green-50 px-4 py-2">
                    <h3 class="text-sm font-semibold text-green-700">特別チーム</h3>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-green-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-green-500"></th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-green-500">チーム名</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-green-500">部署</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-green-500">リーダー</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-green-500">メンバー</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr
                            v-for="(team, idx) in specTeams"
                            :key="team.id"
                            draggable="true"
                            class="cursor-pointer hover:bg-gray-50"
                            @dragstart="onDragStart(idx, 'special', $event)"
                            @dragover="onDragOver($event, idx, 'special')"
                            @dragend="onDragEnd('special')"
                            @click="goToRoom(team.id)"
                        >
                            <td class="px-2 py-3 text-gray-300 select-none">⠿</td>
                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ team.name }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ team.department?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                <span v-if="team.leader_name" class="flex items-center gap-1">
                                    <span class="inline-block h-2 w-2 rounded-full bg-orange-400"></span>
                                    {{ team.leader_name }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600" style="max-width: 200px;">
                                <span v-if="team.member_names?.length" class="block truncate text-xs">
                                    {{ team.member_names.slice(0, 6).join('、') }}{{ team.member_names.length > 6 ? `　他${team.member_names.length - 6}名` : '' }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap" @click.stop>
                                <Link
                                    :href="route('team-rooms.show', { team: team.id })"
                                    class="rounded bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700"
                                >ルームへ</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- 一般チーム -->
            <div class="rounded bg-white p-6 shadow">
                <h3 v-if="deptTeams.length > 0 || specTeams.length > 0" class="mb-3 text-sm font-semibold text-gray-500">一般チーム</h3>
                <div v-if="unitTeamList.length === 0" class="py-12 text-center text-gray-400">
                    所属しているチームがありません
                </div>
                <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500"></th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">チーム名</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">部署</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">リーダー</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">メンバー</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr
                            v-for="(team, idx) in unitTeamList"
                            :key="team.id"
                            draggable="true"
                            class="cursor-pointer hover:bg-gray-50"
                            @dragstart="onDragStart(idx, 'unit', $event)"
                            @dragover="onDragOver($event, idx, 'unit')"
                            @dragend="onDragEnd('unit')"
                            @click="goToRoom(team.id)"
                        >
                            <td class="px-2 py-3 text-gray-300 select-none">⠿</td>
                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ team.name }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ team.department?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                <span v-if="team.leader_name" class="flex items-center gap-1">
                                    <span class="inline-block h-2 w-2 rounded-full bg-orange-400"></span>
                                    {{ team.leader_name }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600" style="max-width: 200px;">
                                <span v-if="team.member_names?.length" class="block truncate text-xs">
                                    {{ team.member_names.slice(0, 6).join('、') }}{{ team.member_names.length > 6 ? `　他${team.member_names.length - 6}名` : '' }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap" @click.stop>
                                <Link
                                    :href="route('team-rooms.show', { team: team.id })"
                                    class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                                >ルームへ</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
