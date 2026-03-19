<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ client: Object });

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    return ['admin', 'superadmin'].includes(role) ? 'admin' : 'leader';
});
</script>

<template>
    <AppLayout title="クライアント詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアント詳細</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4"><strong>ID:</strong> {{ client.id }}</div>
            <div class="mb-4"><strong>会社名:</strong> {{ client.name }}</div>
            <div class="mb-4"><strong>詳細:</strong> {{ client.notes }}</div>
            <div class="mt-6 flex gap-4">
                <Link :href="route(`${routePrefix}.clients.edit`, client.id)" class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700">編集</Link>
                <Link :href="route(`${routePrefix}.clients.index`)" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300">一覧へ戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>
