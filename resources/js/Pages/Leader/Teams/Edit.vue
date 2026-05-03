<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import EditForUnits from '@/Pages/Leader/Teams/EditForUnits.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const team = page.props.team;
</script>

<template>
    <AppLayout title="チーム編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('leader.teams.index')" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← チーム一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム編集</h2>
            </div>
        </template>

        <EditForUnits v-if="team.team_type === 'unit'" />
        <div v-else class="mx-auto max-w-2xl rounded bg-white p-6 shadow text-gray-500">このチームは編集できません。</div>
    </AppLayout>
</template>
