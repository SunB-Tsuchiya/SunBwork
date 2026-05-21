<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';

defineProps({
    departments:      { type: Array,  default: () => [] },
    userDepartmentId: { type: Number, default: null },
});

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props.user;
</script>

<template>
    <AppLayout title="Clerk Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                【クラーク】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <IrukaBoard :departments="$page.props.departments" :default-dept-id="$page.props.userDepartmentId" />
    </AppLayout>
</template>
