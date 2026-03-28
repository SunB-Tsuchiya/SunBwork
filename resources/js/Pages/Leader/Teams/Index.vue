<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    teams: {
        type: Array,
        required: true,
    },
});

const handleDelete = async (teamId) => {
    if (!confirm('チームを削除します。よろしいですか？')) return;
    if (!confirm('本当に削除してよいですか？')) return;
    try {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const getCookie = (name) => {
            const match = document.cookie.match(new RegExp('(^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
            return match ? match[2] : null;
        };
        let csrf = tokenMeta ? tokenMeta.getAttribute('content') : null;
        if (!csrf) {
            const raw = getCookie('XSRF-TOKEN');
            if (raw) csrf = decodeURIComponent(raw);
        }
        const res = await fetch(`/leader/teams/${teamId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf || '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        if (res.ok) {
            window.location.href = route('leader.teams.index');
            return;
        }
        const data = await res.json().catch(() => ({}));
        alert(data.message || `削除に失敗しました (HTTP ${res.status})`);
    } catch (err) {
        alert('削除に失敗しました。コンソールを確認してください。');
    }
};
</script>

<template>
    <AppLayout title="チーム管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム管理</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex justify-end">
                <button
                    @click="router.visit(route('leader.teams.create'))"
                    class="rounded bg-orange-600 px-4 py-2 text-sm font-bold text-white hover:bg-orange-700"
                >
                    新規作成
                </button>
            </div>

            <div v-if="teams.length === 0" class="py-8 text-center text-gray-500">
                ユニットチームが登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">チーム名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="team in teams" :key="team.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ team.name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ team.department?.name || '未設定' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <button
                                    @click="router.visit(route('leader.teams.show', { team: team.id }))"
                                    class="mr-1 rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                >
                                    詳細
                                </button>
                                <button
                                    @click="router.visit(route('leader.teams.edit', { team: team.id }))"
                                    class="mr-1 rounded bg-orange-600 px-3 py-1 text-xs font-bold text-white hover:bg-orange-700"
                                >
                                    編集
                                </button>
                                <button
                                    @click="handleDelete(team.id)"
                                    class="rounded bg-red-600 px-3 py-1 text-xs font-bold text-white hover:bg-red-700"
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
