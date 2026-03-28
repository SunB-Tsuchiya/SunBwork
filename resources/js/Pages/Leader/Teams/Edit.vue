<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import EditForUnits from '@/Pages/Leader/Teams/EditForUnits.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const team = page.props.team;
</script>

<template>
    <AppLayout title="チーム編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム編集</h2>
        </template>

        <EditForUnits v-if="team.team_type === 'unit'" />
        <div v-else class="rounded bg-white p-6 shadow text-gray-500">このチームは編集できません。</div>
    </AppLayout>
</template>
