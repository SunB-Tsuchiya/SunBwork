<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import QuickWorkEntryModal from '@/Components/Calendar/QuickWorkEntryModal.vue';
import WorkJobSeries from '@/Components/WorkJobSeries.vue';
defineProps({ data: { type: Object, required: true } });
const show = ref(false);
function saved(date, id) {
    show.value = false;
    router.visit(route('user.myjobbox.entries.show', { entry: id }));
}
</script>

<template>
    <div class="space-y-4">
        <WorkJobSeries :series="data.series" />
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-5 py-3 shadow-sm">
            <p class="text-xs text-gray-500">この仕事の続きを、簡易入力で登録します。元の予定・数量は変わりません。</p>
            <button type="button" class="rounded border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700" @click="show = true">続きのマイジョブを作成</button>
        </div>
        <QuickWorkEntryModal :show="show" :entry="data.prefill" :date="new Date().toLocaleDateString('sv-SE')" :continuation-source="data.source" entry-point="myjob" @close="show = false" @saved="saved" />
    </div>
</template>
