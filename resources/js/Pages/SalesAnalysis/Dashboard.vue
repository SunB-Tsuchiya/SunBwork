<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="売上分析">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">売上分析</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">集計ダッシュボードは後続のPhaseで実装します。</p>
            <div class="mt-4">
                <Link
                    :href="route('sales_analysis.import.create')"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700"
                >
                    Excel取込へ
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
