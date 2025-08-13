<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

import { ref, computed } from 'vue';

const props = defineProps({
    teams: {
        type: Array,
        required: true,
    },
});

const showType = ref('department'); // 'department' or 'personal'

const filteredTeams = computed(() => {
    return props.teams.filter(team =>
        showType.value === 'department'
            ? team.team_type !== 'personal'
            : team.team_type === 'personal'
    );
});

const handleEdit = (teamId) => {
    // 編集ページへ遷移
    window.location.href = route('admin.teams.edit', { team: teamId });
};

const handleDelete = async (teamId) => {
    if (!confirm('チームを削除します。よろしいですか？')) return;
    if (!confirm('本当に削除してよいですか？')) return;
    // InertiaでDELETEリクエスト
    await window.Inertia.delete(route('admin.teams.destroy', { team: teamId }));
};
</script>

<template>
    <AppLayout title="チーム一覧">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                チーム一覧
            </h2>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-4 flex gap-4">
                    <button
                        class="px-4 py-2 rounded border text-sm font-medium"
                        :class="showType === 'department' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-blue-600 border-blue-600'"
                        @click="showType = 'department'"
                    >部署チーム</button>
                    <button
                        class="px-4 py-2 rounded border text-sm font-medium"
                        :class="showType === 'personal' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-blue-600 border-blue-600'"
                        @click="showType = 'personal'"
                    >個人チーム</button>
                </div>
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">チーム名</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">会社</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">部署</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">種別</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="team in filteredTeams" :key="team.id">
                                <td class="px-6 py-4 whitespace-nowrap">{{ team.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ team.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ team.company?.name || '未設定' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ team.department?.name || '未設定' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ team.team_type === 'personal' ? '個人チーム' : '部署チーム' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap flex flex-col sm:flex-row gap-2 sm:justify-end">
                                    <button @click="() => $inertia.visit(route('admin.teams.edit', { team: team.id }))" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-xs">編集</button>
                                    <button
                                        v-if="!team.personal_team"
                                        @click="() => handleDelete(team.id)"
                                        class="px-3 py-1 text-white rounded text-xs bg-red-500 hover:bg-red-600"
                                    >削除</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
