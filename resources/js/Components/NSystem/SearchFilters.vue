<script setup>
defineProps({
    filters: { type: Object, required: true },
    schools: { type: Array, required: true },
    subjectLabels: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const emit = defineEmits(['update-filter']);

const update = (key, value) => emit('update-filter', { key, value: value || null });
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" aria-labelledby="search-options-heading">
        <div class="flex flex-col gap-4">
            <div>
                <h2 id="search-options-heading" class="mb-2 text-sm font-bold text-[#1a3a6b]">検索方法</h2>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="option in [
                            { value: 'exact', label: 'そのまま含む', description: '入力した文字列が連続して存在' },
                            { value: 'all', label: 'すべての語', description: '空白区切りした全語が存在' },
                            { value: 'any', label: 'いずれかの語', description: '空白区切りしたどれかが存在' },
                        ]"
                        :key="option.value"
                        class="cursor-pointer rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="filters.mode === option.value ? 'border-[#1a3a6b] bg-blue-50 text-[#1a3a6b]' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input
                            type="radio"
                            name="search-mode"
                            :value="option.value"
                            :checked="filters.mode === option.value"
                            class="mr-1.5 text-[#1a3a6b] focus:ring-[#1a3a6b]"
                            @change="update('mode', option.value)"
                        />
                        <span class="font-semibold">{{ option.label }}</span>
                        <span class="ml-1 hidden text-xs text-gray-500 lg:inline">{{ option.description }}</span>
                    </label>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-sm font-medium text-gray-700">
                    科目
                    <select
                        :value="filters.subject ?? ''"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-[#1a3a6b] focus:ring-[#1a3a6b]"
                        @change="update('subject', $event.target.value)"
                    >
                        <option value="">すべての科目</option>
                        <option v-for="(label, code) in subjectLabels" :key="code" :value="code">{{ label }}</option>
                    </select>
                </label>

                <label class="text-sm font-medium text-gray-700">
                    学校
                    <select
                        :value="filters.school_id ?? ''"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-[#1a3a6b] focus:ring-[#1a3a6b]"
                        @change="update('school_id', $event.target.value ? Number($event.target.value) : null)"
                    >
                        <option value="">すべての学校</option>
                        <option v-for="school in schools" :key="school.id" :value="school.id">
                            {{ school.name }}（{{ school.year }}年度）
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-gray-700">
                    カテゴリ
                    <select
                        :value="filters.category ?? ''"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-[#1a3a6b] focus:ring-[#1a3a6b]"
                        @change="update('category', $event.target.value)"
                    >
                        <option value="">すべてのカテゴリ</option>
                        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                    </select>
                </label>
            </div>
        </div>
    </section>
</template>
