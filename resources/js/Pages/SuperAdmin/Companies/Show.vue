<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout title="会社詳細">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">会社詳細</h2>
                <Link
                    :href="route('superadmin.companies.edit', company.id)"
                    class="rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-700"
                >
                    編集
                </Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- 会社名 -->
            <div class="mb-6">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">会社名</div>
                <div class="mt-1 text-2xl font-bold text-blue-800">{{ company.name }}</div>
            </div>

            <!-- 代表者 -->
            <div class="mb-6">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">代表者</div>
                <div class="mt-1 text-lg">
                    <span v-if="company.representative" class="font-semibold text-blue-700">
                        {{ company.representative.name }}
                        <span class="ml-2 text-sm font-normal text-gray-500">{{ company.representative.email }}</span>
                    </span>
                    <span v-else class="text-gray-400">未設定</span>
                </div>
            </div>

            <!-- 部署・担当 -->
            <div class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">部署 / 担当</div>
            <div
                v-for="department in [...company.departments].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))"
                :key="department.id"
                class="mb-4 rounded border-l-4 border-blue-300 bg-blue-50 p-4"
            >
                <div class="mb-2 text-lg font-semibold text-blue-900">{{ department.name }}</div>
                <div class="ml-4 text-blue-700">
                    <span>担当：</span>
                    <span v-for="(assignment, idx) in department.assignments" :key="assignment.id">
                        {{ assignment.name }}<span v-if="idx < department.assignments.length - 1">，</span>
                    </span>
                    <span v-if="!department.assignments?.length" class="text-gray-400">なし</span>
                </div>
            </div>
            <div v-if="!company.departments?.length" class="text-gray-400">部署が登録されていません。</div>

            <div class="mt-6">
                <Link
                    :href="route('superadmin.companies.index')"
                    class="text-sm text-gray-600 hover:underline"
                >
                    ← 一覧へ戻る
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
