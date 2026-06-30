<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    noCompanySelected:  { type: Boolean, default: false },
    meetingDefinitions: { type: Array, default: () => [] },
});

const recurrenceLabel = { weekly: '毎週', biweekly: '隔週', monthly: '毎月', custom_dates: 'カレンダー指定' };
const dayLabel = ['日', '月', '火', '水', '木', '金', '土'];

function destroy(id) {
    if (!confirm('この会議定義を削除してもよいですか？')) return;
    router.delete(route('leader.meeting_definitions.destroy', { meeting_definition: id }));
}
</script>

<template>
    <AppLayout title="会議設定">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">会議設定</h2>
        </template>
        <template #headerExtras>
            <Link
                :href="route('leader.meeting_definitions.create')"
                class="rounded bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700"
            >
                会議を追加
            </Link>
        </template>

        <!-- SuperAdmin グローバルモード警告 -->
        <div v-if="props.noCompanySelected" class="rounded border border-yellow-300 bg-yellow-50 p-6 shadow">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="font-semibold text-yellow-800">会社が選択されていません</p>
                    <p class="mt-1 text-sm text-yellow-700">右上の会社コンテキスト切り替えで表示したい会社を選択してから、このページを開いてください。</p>
                </div>
            </div>
        </div>

        <div v-else class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <p v-if="meetingDefinitions.length === 0" class="text-sm text-gray-500">
                登録済みの会議はありません。
            </p>
            <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-600">
                        <th class="py-2 pr-4">タイトル</th>
                        <th class="py-2 pr-4">繰り返し</th>
                        <th class="py-2 pr-4">週</th>
                        <th class="py-2 pr-4">曜日</th>
                        <th class="py-2 pr-4">時間</th>
                        <th class="py-2 pr-4">メンバー数</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="def in meetingDefinitions"
                        :key="def.id"
                        class="border-b hover:bg-gray-50"
                    >
                        <td class="py-2 pr-4 font-medium">{{ def.title }}</td>
                        <td class="py-2 pr-4">{{ recurrenceLabel[def.recurrence] }}</td>
                        <td class="py-2 pr-4">{{ def.recurrence === 'monthly' && def.week_of_month ? `第${def.week_of_month}週` : (def.recurrence === 'custom_dates' ? `${def.custom_dates?.length ?? 0}日選択` : '—') }}</td>
                        <td class="py-2 pr-4">{{ def.recurrence === 'custom_dates' ? '選択日' : `${dayLabel[def.day_of_week]}曜` }}</td>
                        <td class="py-2 pr-4">{{ def.start_time.slice(0,5) }}〜{{ def.end_time.slice(0,5) }}</td>
                        <td class="py-2 pr-4">{{ def.members?.length ?? 0 }}名</td>
                        <td class="py-2 flex gap-2">
                            <Link
                                :href="route('leader.meeting_definitions.edit', { meeting_definition: def.id })"
                                class="rounded border border-orange-400 px-3 py-1 text-xs text-orange-600 hover:bg-orange-50"
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
        </div>
    </AppLayout>
</template>
