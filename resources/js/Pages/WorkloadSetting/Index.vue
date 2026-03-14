<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
    stages: Array,
    work_item_types: Array,
    sizes: Array,
    statuses: Array,
    difficulties: Array,
});

const page = usePage();

const sections = [
    { type: 'stages',          label: 'Stages',          items: props.stages },
    { type: 'work_item_types', label: 'Work Item Types',  items: props.work_item_types },
    { type: 'sizes',           label: 'Sizes',            items: props.sizes },
    { type: 'statuses',        label: 'Statuses',         items: props.statuses },
    { type: 'difficulties',    label: 'Difficulties',     items: props.difficulties },
];
</script>

<template>
    <AppLayout title="作業項目設定">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                作業項目設定
            </h2>
        </template>

        <Head title="作業項目設定" />

        <div class="rounded bg-white p-6 shadow">
            <div class="grid gap-6 md:grid-cols-2">
                <div
                    v-for="section in sections"
                    :key="section.type"
                    class="rounded border bg-white p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ section.label }}</h2>
                        <a
                            :href="route('workload_setting.edit', { type: section.type })"
                            class="inline-flex items-center rounded bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700"
                        >
                            編集
                        </a>
                    </div>
                    <ul class="space-y-1 text-sm">
                        <li
                            v-for="item in section.items"
                            :key="item.id"
                            class="border-b py-1 text-gray-700"
                        >
                            {{ item.name }}
                            <span v-if="item.label" class="ml-2 text-gray-400">— {{ item.label }}</span>
                        </li>
                        <li v-if="!section.items || section.items.length === 0" class="text-gray-400">
                            登録がありません
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
