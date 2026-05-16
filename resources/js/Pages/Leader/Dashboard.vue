<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';

defineProps({
    departments: { type: Array, default: () => [] },
});

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props.user;
</script>

<template>
    <AppLayout title="Leader Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">【リーダー】{{ user?.name || 'ユーザー' }}さんのページ</h2>
        </template>
        <IrukaBoard :departments="$page.props.departments" />
    </AppLayout>
</template>
