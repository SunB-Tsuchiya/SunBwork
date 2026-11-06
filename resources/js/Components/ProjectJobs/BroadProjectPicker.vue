<script setup>
import { computed, ref } from 'vue';
import BroadProjectClients from './BroadProjectClients.vue';
const props = defineProps({
    modelValue: { type: Number, default: null },
    options: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);
const query = ref('');
const normalize = value => value.normalize('NFKC').toLocaleLowerCase();
const filtered = computed(() => {
    const words = normalize(query.value).trim().split(/\s+/).filter(Boolean);
    return props.options.filter(option => option.id === props.modelValue || words.every(word => normalize(`${option.label} ${(option.clients || []).map(client => client.name).join(' ')}`).includes(word)));
});
const selected = computed(() => props.options.find(option => option.id === props.modelValue));
</script>

<template>
    <div class="mb-4 rounded border border-gray-200 p-3">
        <label for="project-broad-classification" class="mb-1 block font-semibold">大まかな案件（任意）</label>
        <p class="mb-2 text-xs text-gray-500">案件名とは別に、大グループまたは中グループを選択します。未選択でも保存できます。</p>
        <input v-model="query" type="search" maxlength="100" placeholder="グループ名・クライアント名・年度で絞り込み" aria-label="大まかな案件の候補を検索" class="mb-2 w-full rounded border px-3 py-2 text-sm" />
        <select id="project-broad-classification" :value="modelValue" class="w-full rounded border px-3 py-2" @change="emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)">
            <option value="">紐付けない</option>
            <optgroup v-if="filtered.some(option => option.is_favorite)" label="お気に入り">
                <option v-for="option in filtered.filter(option => option.is_favorite)" :key="option.id" :value="option.id">{{ option.parent_id ? '中：' : '大：' }}{{ option.label }}{{ option.active ? '' : '［休止中・既存の選択］' }}</option>
            </optgroup>
            <optgroup :label="filtered.some(option => option.is_favorite) ? 'その他の分類' : 'すべての分類'">
                <option v-for="option in filtered.filter(option => !option.is_favorite)" :key="option.id" :value="option.id">{{ option.parent_id ? '中：' : '大：' }}{{ option.label }}{{ option.active ? '' : '［休止中・既存の選択］' }}</option>
            </optgroup>
        </select>
        <p v-if="query.trim() && !filtered.length" class="mt-1 text-xs text-gray-500">一致する分類がありません。検索語を変えてください。</p>
        <p v-if="selected" class="mt-2 text-sm text-gray-600">選択中：{{ selected.label }}</p>
        <BroadProjectClients v-if="selected" :clients="selected.clients || []" />
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
        <div v-if="suggestions.length" class="mt-3 border-t pt-3">
            <p class="text-sm font-medium">保存済みの案件名からの分類候補</p>
            <p class="mt-1 text-xs text-gray-500">未確定の候補です。内容を確認し、選択して案件を更新すると紐付けられます。</p>
            <ul class="mt-2 space-y-2">
                <li v-for="candidate in suggestions" :key="candidate.id" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div><span>{{ candidate.label }}</span><p class="text-xs text-gray-500">{{ candidate.reason }}</p></div>
                    <button type="button" class="text-green-700 underline" @click="emit('update:modelValue', candidate.id)">この候補を選ぶ</button>
                </li>
            </ul>
        </div>
    </div>
</template>
