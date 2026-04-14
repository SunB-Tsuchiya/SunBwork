<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    dispatchers: { type: Array, default: () => [] },
});

function toggle(dispatcher) {
    router.put(
        route('proof_coordinator.dispatchers.toggle', { dispatcher: dispatcher.id }),
        { is_active: !dispatcher.is_active },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout title="単発派遣管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">単発派遣管理</h2>
        </template>
        <template #headerExtras>
            <Link
                :href="route('proof_coordinator.dispatchers.create')"
                class="rounded bg-pink-600 px-4 py-2 font-bold text-white hover:bg-pink-700"
            >
                新規登録
            </Link>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="dispatchers" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-4 text-xs text-gray-500">
                「アサイン表示」をオンにすると、校正ジョブのアサイン時に担当者セレクターに表示されます。
            </p>

            <template v-if="props.dispatchers.length === 0">
                <p class="py-8 text-gray-500">単発派遣はまだ登録されていません。</p>
            </template>
            <template v-else>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前 / 会社名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">メール</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">電話</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">割当数</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">アサイン表示</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="d in props.dispatchers" :key="d.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                    <Link :href="route('proof_coordinator.dispatchers.show', d.id)" class="text-pink-700 hover:underline">
                                        {{ d.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ d.email ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ d.phone ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ d.assignments_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="toggle(d)"
                                        :class="d.is_active
                                            ? 'bg-pink-600 hover:bg-pink-700'
                                            : 'bg-gray-300 hover:bg-gray-400'"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                                        :aria-checked="d.is_active"
                                        role="switch"
                                    >
                                        <span
                                            :class="d.is_active ? 'translate-x-6' : 'translate-x-1'"
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        />
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <Link :href="route('proof_coordinator.dispatchers.edit', d.id)" class="text-blue-600 hover:text-blue-900">編集</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
