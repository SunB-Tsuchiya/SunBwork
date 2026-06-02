<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    diaryTeams: { type: Array, default: () => [] },
});

const deleteTarget = ref(null);

function confirmDelete(team) {
    if (confirm(`「${team.name}」を削除しますか？`)) {
        router.delete(route('admin.diary_teams.destroy', team.id));
    }
}

const roleLabel = {
    clerk:             '事務',
    coordinator:       'コーディネーター',
    proof_coordinator: '校正Co',
};
</script>

<template>
    <AppLayout title="日報権限管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">日報権限管理</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <AdminNavigationTabs active="diary_teams" />

            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-700">日報権限チーム一覧</h3>
                <Link
                    :href="route('admin.diary_teams.create')"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                >
                    + 新規作成
                </Link>
            </div>

            <div v-if="diaryTeams.length === 0" class="py-8 text-center text-gray-400">
                日報権限チームがまだ登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">チーム名</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">日報マネージャー</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-600">メンバー数</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="team in diaryTeams" :key="team.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ team.name }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                <span
                                    v-for="leader in team.leaders"
                                    :key="leader.id"
                                    class="mr-1 inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700"
                                >
                                    {{ leader.name }}
                                    <span class="text-blue-400">({{ roleLabel[leader.user_role] ?? leader.user_role }})</span>
                                </span>
                                <span v-if="team.leaders.length === 0" class="text-gray-400">未設定</span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ team.member_count }} 名</td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('admin.diary_teams.edit', team.id)"
                                    class="mr-2 text-blue-600 hover:underline"
                                >
                                    編集
                                </Link>
                                <button
                                    @click="confirmDelete(team)"
                                    class="text-red-500 hover:underline"
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
