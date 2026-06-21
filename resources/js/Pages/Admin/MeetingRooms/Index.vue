<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    rooms: { type: Array, required: true },
});

function confirmDelete(id) {
    if (confirm('この会議室を削除してもよろしいですか？')) {
        router.delete(route('admin.meeting-rooms.destroy', { room: id }));
    }
}
</script>

<template>
    <AppLayout title="会議室管理">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">会議室管理</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-700">会議室一覧</h3>
                <Link
                    :href="route('admin.meeting-rooms.create')"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                >
                    + 新規登録
                </Link>
            </div>

            <div v-if="rooms.length === 0" class="py-8 text-center text-gray-500">
                会議室が登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">順序</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">会議室名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">定員</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">色</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">予約可能時間</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">状態</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="room in rooms" :key="room.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ room.sort_order }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ room.name }}</div>
                                <div v-if="room.description" class="text-xs text-gray-400">{{ room.description }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                {{ room.capacity ? `${room.capacity}名` : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span v-if="room.color"
                                    class="inline-block h-5 w-5 rounded-full border border-gray-300"
                                    :style="{ backgroundColor: room.color }"
                                    :title="room.color"
                                ></span>
                                <span v-else class="text-sm text-gray-400">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                <template v-if="room.available_from && room.available_to">
                                    {{ room.available_from.slice(0,5) }}〜{{ room.available_to.slice(0,5) }}
                                </template>
                                <span v-else class="text-gray-400">制限なし</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="room.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ room.active ? '有効' : '無効' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <Link
                                    :href="route('admin.meeting-rooms.edit', { room: room.id })"
                                    class="mr-3 text-sm text-blue-600 hover:underline"
                                >編集</Link>
                                <button
                                    type="button"
                                    class="text-sm text-red-600 hover:underline"
                                    @click="confirmDelete(room.id)"
                                >削除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
