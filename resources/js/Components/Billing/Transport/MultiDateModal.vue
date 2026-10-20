<script setup>
import { ref, computed } from 'vue';
import { parseDays } from './transportInput';
import { shortDate } from './transportNormalize';

const props = defineProps({
    month:       { type: String, required: true },
    description: { type: String, default: '' },   // 対象の行の説明（行先など）
});
const emit = defineEmits(['close', 'apply']);

const text = ref('');
const result = computed(() => parseDays(text.value, props.month));
const [y, m] = props.month.split('-').map(Number);
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('close')">
            <div class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">複数の日に追加</h3>
                    <button type="button" class="text-xl leading-none text-gray-400 hover:text-gray-600" @click="$emit('close')">✕</button>
                </div>
                <p class="mb-3 text-sm text-gray-600">この行と同じ内容（{{ description || '行先・区間・金額' }}）を、日にちだけ変えて、まとめて追加します。</p>

                <label class="mb-1 block text-sm font-medium text-gray-700">{{ y }}年{{ m }}月の日にち</label>
                <input type="text" v-model="text" @keydown.enter.prevent="result.dates.length && $emit('apply', result.dates)"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
                <p class="mt-1 text-xs text-gray-500">例：　1, 3, 6-10, 15　（日にちだけ。連続する日は 6-10 のように書きます）</p>

                <div v-if="result.dates.length" class="mt-3 rounded bg-blue-50 p-3 text-sm text-blue-900">
                    {{ result.dates.length }} 日: {{ result.dates.map(shortDate).join('、') }}
                </div>
                <p v-if="result.invalid.length" class="mt-2 text-sm text-red-600">読み取れない指定があります（この月にない日など）: {{ result.invalid.join('、') }}</p>

                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="$emit('close')">キャンセル</button>
                    <button type="button" :disabled="!result.dates.length" @click="$emit('apply', result.dates)"
                        class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40">追加する</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
