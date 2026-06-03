<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    teams: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout title="チームルーム">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チームルーム</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div v-if="teams.length === 0" class="py-12 text-center text-gray-400">
                所属しているチームがありません
            </div>

            <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">チーム名</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">部署</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">リーダー</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">メンバー</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr
                        v-for="team in teams"
                        :key="team.id"
                        class="cursor-pointer hover:bg-gray-50"
                        @click="router.get(route('team-rooms.show', { team: team.id }))"
                    >
                        <td class="px-4 py-3 font-medium text-gray-900">{{ team.name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ team.department?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            <span v-if="team.leader_name" class="flex items-center gap-1">
                                <span class="inline-block h-2 w-2 rounded-full bg-orange-400"></span>
                                {{ team.leader_name }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600" style="max-width: 200px;">
                            <span v-if="team.member_names && team.member_names.length > 0" class="block truncate text-xs">
                                {{ team.member_names.slice(0, 6).join('、') }}{{ team.member_names.length > 6 ? `　他${team.member_names.length - 6}名` : '' }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Link
                                :href="route('team-rooms.show', { team: team.id })"
                                class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                            >ルームへ</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
