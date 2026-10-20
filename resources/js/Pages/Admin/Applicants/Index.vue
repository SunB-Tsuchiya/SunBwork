<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    features:          { type: Array, default: () => [] },
    noCompanySelected: { type: Boolean, default: false },
});
</script>

<template>
    <AppLayout title="申請者管理">
        <template #header>
            <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">申請者管理</h2>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="applicants" />
        </template>

        <!-- SuperAdmin グローバルモード警告（在席ボード管理と同じ） -->
        <div v-if="noCompanySelected" class="mx-auto max-w-2xl rounded border border-yellow-300 bg-yellow-50 p-6 shadow">
            <p class="font-semibold text-yellow-800">会社が選択されていません</p>
            <p class="mt-1 text-sm text-yellow-700">右上の会社コンテキスト切り替えで、設定したい会社を選択してから、このページを開いてください。</p>
        </div>

        <div v-else class="rounded bg-white p-6 shadow">
            <h3 class="mb-1 text-lg font-semibold text-gray-800">申請の種類</h3>
            <p class="mb-5 text-sm text-gray-500">申請の種類を選んで、その申請を使う人を、ユーザーごとのスイッチで決めます。スイッチを ON にした人にだけ、ユーザー用のタブ「申請」が出ます（派遣・契約の方など、申請が必要な人にだけ出すための設定です）。</p>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="f in features" :key="f.key" :href="route('admin.applicants.show', { feature: f.key })"
                    class="block rounded-lg border border-red-200 bg-red-50 p-4 transition hover:bg-red-100">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="font-semibold text-red-900">{{ f.name }}</h4>
                        <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-200">
                            使う人 {{ f.enabled_count }} / {{ f.total_count }}人
                        </span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600">{{ f.description }}</p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
