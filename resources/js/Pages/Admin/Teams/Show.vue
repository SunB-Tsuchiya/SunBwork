<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const team = page.props.team || {};
</script>

<template>
    <AppLayout title="チーム詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム詳細</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">{{ team.name }}</h3>
                    <p class="mt-2 text-sm text-gray-600">会社: {{ team.company?.name || '未設定' }}</p>
                    <p class="mt-1 text-sm text-gray-600">部署: {{ team.department?.name || '未設定' }}</p>

                    <div class="mt-4">
                        <a :href="route('admin.teams.index')" class="text-sm text-blue-600 hover:underline">一覧へ戻る</a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
