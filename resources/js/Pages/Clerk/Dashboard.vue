<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';

defineProps({
    departments:      { type: Array,  default: () => [] },
    userDepartmentId: { type: Number, default: null },
});

import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props.user;
// 見出しの役割名: サンエー印刷は総務/経理（Admin は切替中の方）、サン・ブレーンは従来どおり「クラーク」
const roleLabel = computed(() => {
    if (page.props.auth?.companyType !== 'general') return 'クラーク';
    return page.props.auth?.clerkKind === 'keiri' ? '経理' : '総務';
});
</script>

<template>
    <AppLayout title="Clerk Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                【{{ roleLabel }}】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <IrukaBoard :departments="$page.props.departments" :default-dept-id="$page.props.userDepartmentId" />
    </AppLayout>
</template>
