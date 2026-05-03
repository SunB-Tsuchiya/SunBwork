<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    meetingDefinitions: { type: Array, default: () => [] },
});

const recurrenceLabel = { weekly: '毎週', biweekly: '隔週', monthly: '毎月' };
const dayLabel = ['日', '月', '火', '水', '木', '金', '土'];

function destroy(id) {
    if (!confirm('この会議定義を削除してもよいですか？')) return;
    router.delete(route('admin.meeting_definitions.destroy', { meeting_definition: id }));
}
</script>

<template>
    <AppLayout title="会議設定">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">会議設定</h2>
        </template>
        <template #headerExtras>
            <Link
                :href="route('admin.meeting_definitions.create')"
                class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            >
                会議を追加
            </Link>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p v-if="meetingDefinitions.length === 0" class="text-sm text-gray-500">
                登録済みの会議はありません。
            </p>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-600">
                        <th class="py-2 pr-4">タイトル</th>
                        <th class="py-2 pr-4">繰り返し</th>
                        <th class="py-2 pr-4">曜日</th>
                        <th class="py-2 pr-4">時間</th>
                        <th class="py-2 pr-4">メンバー数</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="def in meetingDefinitions" :key="def.id" class="border-b hover:bg-gray-50">
                        <td class="py-2 pr-4 font-medium">{{ def.title }}</td>
                        <td class="py-2 pr-4">{{ recurrenceLabel[def.recurrence] }}</td>
                        <td class="py-2 pr-4">{{ dayLabel[def.day_of_week] }}曜</td>
                        <td class="py-2 pr-4">{{ def.start_time.slice(0,5) }}〜{{ def.end_time.slice(0,5) }}</td>
                        <td class="py-2 pr-4">{{ def.members?.length ?? 0 }}名</td>
                        <td class="py-2 flex gap-2">
                            <Link
                                :href="route('admin.meeting_definitions.edit', { meeting_definition: def.id })"
                                class="rounded border border-red-400 px-3 py-1 text-xs text-red-600 hover:bg-red-50"
                            >
                                編集
                            </Link>
                            <button
                                @click="destroy(def.id)"
                                class="rounded border border-red-300 px-3 py-1 text-xs text-red-600 hover:bg-red-50"
                            >
                                削除
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
