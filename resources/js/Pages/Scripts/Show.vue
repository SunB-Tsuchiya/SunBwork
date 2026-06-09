<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, defineAsyncComponent } from 'vue';

const props = defineProps({
    script: {
        type: Object,
        required: true,
    },
});

const componentMap = {
    ImageRenamer: defineAsyncComponent(() =>
        import('@/Components/Scripts/ImageRenamer.vue')
    ),
    LabelGenerator: defineAsyncComponent(() =>
        import('@/Components/Scripts/LabelGenerator.vue')
    ),
};

const CurrentTool = computed(() => componentMap[props.script.component_key] ?? null);
</script>

<template>
    <AppLayout :title="script.name">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('scripts.index')"
                    class="text-sm text-gray-500 hover:text-gray-700"
                >
                    ← スクリプト一覧
                </Link>
                <span class="text-gray-300">|</span>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">{{ script.name }}</h2>
            </div>
        </template>

        <!-- ツールコンポーネントが見つかった場合 -->
        <component :is="CurrentTool" v-if="CurrentTool" :script="script" />

        <!-- コンポーネントが未定義の場合 -->
        <div v-else class="rounded bg-white p-8 shadow text-center text-gray-500">
            <p class="text-lg font-medium">このツールは現在準備中です。</p>
            <p class="mt-2 text-sm">component_key: {{ script.component_key }}</p>
        </div>
    </AppLayout>
</template>
