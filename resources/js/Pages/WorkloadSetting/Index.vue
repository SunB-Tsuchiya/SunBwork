<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    noCompanySelected: { type: Boolean, default: false },
    stages: Array,
    work_item_types: Array,
    sizes: Array,
    statuses: Array,
    difficulties: Array,
});

// グループ設定（group カラムを持つタイプのみ）
const groupConfigByType = {
    work_item_types: {
        groups: ['dtp', 'design', 'proof', 'mgmt', 'sales', 'common', null],
        labels: {
            dtp: 'DTP',
            design: 'デザイン',
            proof: '校正',
            mgmt: '管理・進行',
            sales: '営業・受発注',
            common: '共通',
            null: 'グループなし',
        },
    },
    sizes: {
        groups: ['paper', 'digital'],
        labels: {
            paper: '紙媒体',
            digital: 'デジタル・Web',
        },
    },
};

function computeGroupedSections(type, items) {
    const config = groupConfigByType[type];
    if (!config || !items) return null;
    // ハードコード済みグループに加え、DB に存在するカスタムグループも末尾追加
    const configKeys = config.groups.map((k) => k ?? null);
    const extraKeys = [...new Set(items.map((i) => i.group ?? null))].filter(
        (k) => !configKeys.includes(k),
    );
    const allGroups = [...config.groups, ...extraKeys];
    const sections = allGroups
        .map((key) => ({
            key,
            label: config.labels[key] ?? (key !== null ? String(key) : 'グループなし'),
            items: items.filter((i) => (i.group ?? null) === (key ?? null)),
        }))
        .filter((s) => s.items.length > 0);
    return sections.length > 0 ? sections : null;
}

const sections = [
    { type: 'stages', label: 'Stages', items: props.stages },
    { type: 'work_item_types', label: 'Work Item Types', items: props.work_item_types },
    { type: 'sizes', label: 'Sizes', items: props.sizes },
    { type: 'statuses', label: 'Statuses', items: props.statuses },
    { type: 'difficulties', label: 'Difficulties', items: props.difficulties },
];
</script>

<template>
    <AppLayout title="作業項目設定">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">作業項目設定</h2>
        </template>

        <Head title="作業項目設定" />

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
            <div class="grid gap-6 md:grid-cols-2">
                <div
                    v-for="section in sections"
                    :key="section.type"
                    class="rounded border bg-white p-4"
                    :class="{ 'md:col-span-2': !!computeGroupedSections(section.type, section.items) }"
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
                    <!-- グループなし：通常リスト -->
                    <template v-if="!computeGroupedSections(section.type, section.items)">
                        <ul class="space-y-1 text-sm">
                            <li v-for="item in section.items" :key="item.id" class="border-b py-1 text-gray-700">
                                {{ item.name }}
                                <span v-if="item.label" class="ml-2 text-gray-400">— {{ item.label }}</span>
                            </li>
                            <li v-if="!section.items || section.items.length === 0" class="text-gray-400">登録がありません</li>
                        </ul>
                    </template>

                    <!-- グループあり：グループ別表示 -->
                    <template v-else>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="grp in computeGroupedSections(section.type, section.items)" :key="grp.key ?? 'null'">
                                <div class="mb-1 border-b border-gray-200 pb-0.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ grp.label }}
                                </div>
                                <ul class="space-y-1 text-sm">
                                    <li v-for="item in grp.items" :key="item.id" class="border-b py-1 text-gray-700">
                                        {{ item.name }}
                                        <span v-if="item.label" class="ml-2 text-gray-400">— {{ item.label }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
