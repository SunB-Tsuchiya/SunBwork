<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    clients: Array,
    showDormant: { type: Boolean, default: false },
});

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    if (['admin', 'superadmin'].includes(role)) return 'admin';
    if (role === 'coordinator') return 'coordinator';
    return 'leader';
});

function toggleDormantView() {
    router.get(
        route(`${routePrefix.value}.clients.index`),
        props.showDormant ? {} : { dormant: 1 },
        { preserveState: false },
    );
}
</script>

<template>
    <AppLayout title="クライアント一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                クライアント管理
                <span v-if="props.showDormant" class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-sm font-normal text-gray-600">休眠一覧</span>
            </h2>
        </template>
        <template #headerExtras>
            <button
                type="button"
                class="rounded border px-4 py-2 font-bold"
                :class="props.showDormant
                    ? 'border-blue-400 bg-blue-50 text-blue-700 hover:bg-blue-100'
                    : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                @click="toggleDormantView"
            >
                {{ props.showDormant ? '通常一覧を表示' : '休眠を表示' }}
            </button>
            <Link
                v-if="!props.showDormant"
                :href="route(`${routePrefix}.clients.create`)"
                class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700"
            >新規作成</Link>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <template v-if="props.clients.length === 0">
                <p class="py-8 text-gray-500">
                    {{ props.showDormant ? '休眠クライアントはありません' : 'クライアントはまだ登録されていません' }}
                </p>
            </template>

            <template v-else>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">詳細</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="client in props.clients" :key="client.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ client.id }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ client.name }}
                                    <span v-if="client.is_dormant" class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-500">休眠</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ client.detail || client.notes || '' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <Link :href="route(`${routePrefix}.clients.edit`, client.id)" class="text-blue-600 hover:text-blue-900">編集</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
