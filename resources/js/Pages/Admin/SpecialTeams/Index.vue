<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    teams: { type: Array, required: true },
});

const handleDelete = async (id) => {
    if (!confirm('この特別チームを削除します。よろしいですか？')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(route('admin.special_teams.destroy', { special_team: id }), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });
    if (res.ok) {
        router.get(route('admin.special_teams.index'));
    } else {
        alert('削除に失敗しました');
    }
};
</script>

<template>
    <AppLayout title="特別チーム管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">特別チーム管理</h2>
                <Link :href="route('admin.special_teams.create')"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    ＋ 新規作成
                </Link>
            </div>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <p class="mb-4 text-sm text-gray-500">
                会社をまたいだメンバーで構成できる特別チームです。
            </p>

            <div v-if="teams.length === 0" class="py-8 text-center text-gray-500">
                特別チームが登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">チーム名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">リーダー</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">メンバー数</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">説明</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="team in teams" :key="team.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ team.name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ team.leader?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ team.member_count }}名</td>
                            <td class="px-4 py-3 text-gray-500 max-w-xs truncate">{{ team.description ?? '—' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link :href="route('admin.special_teams.edit', { special_team: team.id })"
                                    class="mr-3 text-sm text-blue-600 hover:text-blue-800">編集</Link>
                                <button @click="handleDelete(team.id)"
                                    class="text-sm text-red-600 hover:text-red-800">削除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
