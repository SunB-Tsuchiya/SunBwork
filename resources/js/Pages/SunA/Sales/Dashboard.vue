<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';

// 営業のダッシュボード（経理・総務のクラークのダッシュボードと同じ作り）
defineProps({
    user: { type: Object, default: null },
    departments: { type: Array, default: () => [] },
    userDepartmentId: { type: Number, default: null },
});
</script>

<template>
    <AppLayout title="営業ダッシュボード" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                【営業】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <IrukaBoard :departments="departments" :default-dept-id="userDepartmentId" />
    </AppLayout>
</template>
