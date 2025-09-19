<script setup>
const todayJst = (() => {
    const d = new Date();
    d.setHours(d.getHours() + 9);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
})();
function formatJstDate(dateStr) {
    const d = new Date(dateStr);
    d.setHours(d.getHours() + 9);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    diary: Object,
});

const editHref = computed(() => {
    try {
        // attach a return_to parameter so Edit.vue can navigate back correctly
        const current = window.location.pathname + window.location.search + window.location.hash;
        return route('diaries.edit', props.diary.id) + `?return_to=${encodeURIComponent(current)}`;
    } catch (e) {
        return route('diaries.edit', props.diary.id);
    }
});

const deleteDiary = () => {
    if (confirm('この日報を削除してよろしいですか？')) {
        router.delete(route('diaries.destroy', props.diary.id));
    }
};

import {} from 'vue';

const back = () => {
    try {
        // if browser history has entries, go back using Inertia
        if (window.history && window.history.length > 1) {
            // use native browser back to replicate exact browser behavior
            window.history.back();
            return;
        }
    } catch (e) {
        // ignore
    }
    // fallback to diaries index
    router.get(route('diaries.index'));
};
</script>

<template>
    <AppLayout title="日報表示">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">日報 {{ formatJstDate(props.diary.date) }}</h1>
            <div class="prose mb-6">
                <p v-html="props.diary.content"></p>
            </div>
            <div class="flex space-x-4">
                <!-- 今日の日報表示時は新規作成ボタンを非表示 -->
                <!-- <Link v-if="formatJstDate(props.diary.date) !== todayJst" :href="route('diaries.create')" class="px-4 py-2 bg-green-600 text-white rounded">新しく日報を書く</Link> -->
                <Link :href="editHref" class="rounded bg-blue-600 px-4 py-2 text-white">編集</Link>
                <button @click="deleteDiary" class="rounded bg-red-600 px-4 py-2 text-white">削除</button>
                <button @click="back" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</button>
            </div>
        </div>
    </AppLayout>
</template>
