<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    pages: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

function statusLabel(p) {
    if (!p.is_active) return { text: '無効', cls: 'bg-gray-100 text-gray-500' };
    if (p.is_expired)  return { text: '期限切れ', cls: 'bg-red-100 text-red-600' };
    return { text: '公開中', cls: 'bg-green-100 text-green-700' };
}
</script>

<template>
    <AppLayout title="デモページ管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">デモページ管理</h2>
        </template>
        <template #tabs>
            <SuperAdminNavigationTabs active="demo_pages" />
        </template>

        <div class="space-y-4">

            <div v-if="flash.success" class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center gap-2">
                <span>✔</span>
                <span>{{ flash.success }}</span>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <div v-if="pages.length === 0" class="py-10 text-center text-sm text-gray-400">
                    デモページが登録されていません。
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">ページ名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">スラッグ</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">状態</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">公開期限</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">許可メール</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">作成者</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="p in pages" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ p.name }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ p.slug }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-block rounded-full px-2 py-0.5 text-xs font-semibold', statusLabel(p).cls]">
                                        {{ statusLabel(p).text }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ p.expires_at ?? '無期限' }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ p.emails_count }} 件</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ p.creator_name }}</td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="route('superadmin.demo_pages.show', { demoPage: p.id })"
                                        class="text-xs font-medium text-yellow-600 hover:text-yellow-800"
                                    >詳細・編集</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
