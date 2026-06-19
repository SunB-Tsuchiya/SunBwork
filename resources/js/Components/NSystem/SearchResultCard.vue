<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    subjectLabels: { type: Object, required: true },
});

const badgeClass = computed(() => {
    const classes = {
        共学: 'bg-emerald-100 text-emerald-800',
        男子: 'bg-blue-100 text-blue-800',
        女子: 'bg-pink-100 text-pink-800',
        地方: 'bg-gray-100 text-gray-700',
    };
    return classes[props.item.school.category] ?? classes.地方;
});
</script>

<template>
    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md sm:p-5">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <a :href="item.url" class="font-bold text-[#1a3a6b] hover:underline">{{ item.school.name }}</a>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badgeClass">{{ item.school.category }}</span>
            <span class="text-xs text-gray-500">
                {{ item.school.year }}年度・{{ subjectLabels[item.subject] ?? item.subject }}・大問{{ item.daimon_index }}
            </span>
        </div>

        <a :href="item.url" class="block rounded bg-gray-50 px-3 py-2.5 text-sm leading-7 text-gray-700 hover:bg-blue-50">
            <span v-if="item.snippet.leading_ellipsis">…</span><span>{{ item.snippet.before }}</span><mark
                v-if="item.snippet.match"
                class="rounded bg-yellow-200 px-0.5 text-gray-900"
            >{{ item.snippet.match }}</mark><span>{{ item.snippet.after }}</span><span v-if="item.snippet.trailing_ellipsis">…</span>
        </a>
    </article>
</template>
