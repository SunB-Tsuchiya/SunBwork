<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
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
    <AppLayout title="Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                <span v-if="user?.user_role === 'admin'"> 【管理者→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ </span>
                <span v-else-if="user?.user_role === 'leader'"> 【リーダー→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ </span>
                <span v-else-if="user?.user_role === 'coordinator'">【進行管理→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ</span>
                <span v-else> {{ user?.name || 'ユーザー' }}さんのページ </span>
            </h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="dashboard" />
        </template>

        <IrukaBoard :departments="$page.props.departments" :default-dept-id="$page.props.userDepartmentId" />
    </AppLayout>
</template>
