<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';

defineProps({
    available_teams:  { type: Array,  default: () => [] },
    departments:      { type: Array,  default: () => [] },
    userDepartmentId: { type: Number, default: null },
});

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props.user;
</script>

<template>
    <AppLayout title="SuperAdmin Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">【スーパ管理者】{{ user?.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <IrukaBoard
            v-if="$page.props.departments.length > 0"
            :departments="$page.props.departments"
            :default-dept-id="$page.props.userDepartmentId"
        />
        <div v-else class="rounded bg-white p-6 shadow text-sm text-gray-400 text-center">
            在籍ボードを表示するには、ヘッダーから会社を選択してください。
        </div>
    </AppLayout>
</template>
