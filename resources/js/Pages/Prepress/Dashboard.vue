<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    departments: { type: Array, default: () => [] },
});

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props.user;
</script>

<template>
    <AppLayout title="Prepress Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                【製版】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <div class="space-y-4">
            <!-- クイックリンク -->
            <div class="flex flex-wrap gap-3">
                <Link :href="route('prepress.board')" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                    📋 伝票ボード
                </Link>
                <Link :href="route('prepress.tickets.index')" class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600">
                    📄 伝票一覧
                </Link>
            </div>

            <!-- イルカ在席ボード -->
            <IrukaBoard :departments="$page.props.departments" />
        </div>
    </AppLayout>
</template>
