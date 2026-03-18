<script setup>
// copied from Admin/Teams/Index.vue with route names adjusted to superadmin
import AppLayout from '@/layouts/AppLayout.vue';

import { computed, ref } from 'vue';

const props = defineProps({
    teams: {
        type: Array,
        required: true,
    },
});

// showType は表示モード。'department' または 'unit' を使う。
// 注意: 判定は team.personal_team ではなく team.team_type を参照して行う。
// 以前AIが personal_team を誤って参照していたため、ここでも team_type ベースで判定します。
const showType = ref('department'); // 'department' or 'unit'

const filteredTeams = computed(() => {
    // department view: department 切り替え時は team_type が 'personal' と 'unit' のチームは除外する
    if (showType.value === 'department') {
        return props.teams.filter((team) => team.team_type !== 'personal' && team.team_type !== 'unit');
    }

    // unit view: team_type が 'unit' のものだけ表示する
    return props.teams.filter((team) => team.team_type === 'unit');
});

const handleEdit = (teamId) => {
    window.location.href = route('superadmin.teams.edit', { team: teamId });
};

const handleDelete = async (teamId) => {
    if (!confirm('チームを削除します。よろしいですか？')) return;
    if (!confirm('本当に削除してよいですか？')) return;
    await window.Inertia.delete(route('superadmin.teams.destroy', { team: teamId }));
};
</script>

<template>
    <AppLayout title="チーム一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム一覧</h2>
        </template>
        <div class="rounded bg-white p-6 shadow">
                    <div class="mb-4 flex gap-4">
                        <button
                            class="rounded border px-4 py-2 text-sm font-medium"
                            :class="showType === 'department' ? 'border-blue-600 bg-blue-600 text-white' : 'border-blue-600 bg-white text-blue-600'"
                            @click="showType = 'department'"
                        >
                            部署チーム
                        </button>
                        <button
                            class="rounded border px-4 py-2 text-sm font-medium"
                            :class="showType === 'unit' ? 'border-blue-600 bg-blue-600 text-white' : 'border-blue-600 bg-white text-blue-600'"
                            @click="showType = 'unit'"
                        >
                            ユニットチーム
                        </button>
                    </div>
                    <div class="mb-6 overflow-hidden bg-white p-6 shadow-xl sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">チーム名</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">会社</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="team in filteredTeams" :key="team.id">
                                    <td class="whitespace-nowrap px-6 py-4">{{ team.id }}</td>
                                    <td class="whitespace-nowrap px-6 py-4">{{ team.name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4">{{ team.company?.name || '未設定' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4">{{ team.department?.name || '' }}</td>
                                    <td class="flex flex-col gap-2 whitespace-nowrap px-6 py-4 sm:flex-row sm:justify-end">
                                        <button
                                            @click="() => $inertia.visit(route('superadmin.teams.edit', { team: team.id }))"
                                            class="rounded bg-blue-500 px-3 py-1 text-xs text-white hover:bg-blue-600"
                                        >
                                            編集
                                        </button>
                                        <button
                                            v-if="team.team_type !== 'personal'"
                                            @click="() => handleDelete(team.id)"
                                            class="rounded bg-red-500 px-3 py-1 text-xs text-white hover:bg-red-600"
                                        >
                                            削除
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
        </div>
    </AppLayout>
</template>
