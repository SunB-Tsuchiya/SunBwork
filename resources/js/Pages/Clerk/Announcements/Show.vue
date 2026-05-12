<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    announcement: Object,
    recipients: Array,
});

const targetLabel = (type) => ({
    all: '全員',
    employees_only: '社員のみ',
    individual: '個別選択',
}[type] ?? type);

const employmentLabel = (type) => ({
    regular: '正社員',
    contract: '契約社員',
    dispatch: '派遣',
    outsource: '業務委託',
}[type] ?? type);

const readRate = computed(() => {
    if (!props.announcement.recipients_count) return 0;
    return Math.round(props.announcement.read_count / props.announcement.recipients_count * 100);
});
</script>

<template>
    <AppLayout title="お知らせ詳細（送信側）">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">お知らせ詳細</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <ClerkNavigationTabs active="announcements" />

            <div class="mb-4">
                <Link :href="route('clerk.announcements.index')" class="text-sm text-gray-500 hover:text-gray-700">
                    ← 送信済み一覧に戻る
                </Link>
            </div>

            <!-- お知らせ内容 -->
            <div class="mb-6 rounded-lg border border-purple-100 bg-purple-50 p-5">
                <div class="mb-1 flex flex-wrap items-center gap-3">
                    <span class="rounded bg-purple-200 px-2 py-0.5 text-xs font-medium text-purple-800">
                        {{ targetLabel(announcement.target_type) }}
                    </span>
                    <span class="text-xs text-gray-500">{{ announcement.created_at }}</span>
                </div>
                <h3 class="mb-3 text-lg font-bold text-gray-900">{{ announcement.title }}</h3>
                <p class="whitespace-pre-wrap text-sm text-gray-700 leading-relaxed">{{ announcement.content }}</p>
            </div>

            <!-- 既読進捗 -->
            <div class="mb-4 flex items-center gap-4">
                <div class="text-sm text-gray-700">
                    既読: <span class="font-bold text-green-600">{{ announcement.read_count }}</span>
                    / {{ announcement.recipients_count }}人
                </div>
                <div class="flex-1 max-w-xs">
                    <div class="h-2 w-full rounded-full bg-gray-200">
                        <div
                            class="h-2 rounded-full bg-green-500 transition-all"
                            :style="{ width: readRate + '%' }"
                        ></div>
                    </div>
                </div>
                <span class="text-sm font-medium text-gray-600">{{ readRate }}%</span>
            </div>

            <!-- 受信者一覧 -->
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">名前</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">担当</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">雇用形態</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">既読状況</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">既読日時</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="r in recipients" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ r.assignment_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ employmentLabel(r.employment_type) }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="r.is_read ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                <span class="inline-block h-1.5 w-1.5 rounded-full" :class="r.is_read ? 'bg-green-500' : 'bg-gray-400'"></span>
                                {{ r.is_read ? '既読' : '未読' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ r.read_at ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </AppLayout>
</template>
