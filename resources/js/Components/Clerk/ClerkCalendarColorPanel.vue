<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';
import { CLERK_EVENT_COLORS } from '@/Components/Clerk/clerkEventColors';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'colors-updated']);

const colors = ref([]);
const loading = ref(false);
const dragSrcIdx = ref(null);
const dragOverIdx = ref(null);

async function fetchColors() {
    loading.value = true;
    try {
        const res = await axios.get(route('clerk.calendar.colors.index'));
        colors.value = res.data;
        emit('colors-updated', colors.value);
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
}

watch(() => props.open, (val) => {
    if (val) fetchColors();
});

let labelSaveTimers = {};
function onLabelInput(item) {
    clearTimeout(labelSaveTimers[item.color_key]);
    labelSaveTimers[item.color_key] = setTimeout(() => saveLabel(item), 500);
}

async function saveLabel(item) {
    try {
        await axios.patch(route('clerk.calendar.colors.update', { colorKey: item.color_key }), {
            label: item.label || null,
        });
        emit('colors-updated', colors.value);
    } catch {
        alert('保存に失敗しました');
    }
}

function onDragStart(idx) { dragSrcIdx.value = idx; }
function onDragOver(idx)  { dragOverIdx.value = idx; }
function onDragEnd()      { dragSrcIdx.value = null; dragOverIdx.value = null; }

async function onDrop(targetIdx) {
    const srcIdx = dragSrcIdx.value;
    dragSrcIdx.value  = null;
    dragOverIdx.value = null;
    if (srcIdx === null || srcIdx === targetIdx) return;

    const arr = [...colors.value];
    const [moved] = arr.splice(srcIdx, 1);
    arr.splice(targetIdx, 0, moved);
    arr.forEach((c, i) => { c.sort_order = i; });
    colors.value = arr;
    emit('colors-updated', colors.value);

    try {
        await axios.post(route('clerk.calendar.colors.reorder'), {
            orders: arr.map((c, i) => ({ color_key: c.color_key, sort_order: i })),
        });
    } catch {
        alert('並び替えの保存に失敗しました');
    }
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <div v-if="open" class="mb-4 rounded border border-gray-200 bg-gray-50 p-4" @click.stop>
        <div class="mb-2 text-xs font-semibold text-gray-600">色の設定（ドラッグで並び替え可・ラベルは自由記入）</div>
        <div v-if="loading" class="text-xs text-gray-400">読込中...</div>
        <div v-else class="flex flex-wrap gap-x-2 gap-y-1">
            <div
                v-for="(item, idx) in colors"
                :key="item.color_key"
                draggable="true"
                class="flex items-center gap-1.5 rounded px-2 py-1 cursor-grab select-none border"
                :class="dragOverIdx === idx ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:bg-gray-50'"
                @dragstart="onDragStart(idx)"
                @dragover.prevent="onDragOver(idx)"
                @drop="onDrop(idx)"
                @dragend="onDragEnd"
            >
                <span class="text-gray-300 text-xs select-none">⠿</span>
                <span :class="[CLERK_EVENT_COLORS[item.color_key]?.swatch, 'h-3.5 w-3.5 rounded-full shrink-0']"></span>
                <input
                    type="text"
                    v-model="item.label"
                    maxlength="6"
                    placeholder="ラベル未設定"
                    class="w-24 rounded border border-gray-200 px-1.5 py-0.5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none"
                    @input="onLabelInput(item)"
                />
            </div>
        </div>
        <button
            type="button"
            class="mt-2 rounded border border-gray-300 px-3 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
            @click="close"
        >閉じる</button>
    </div>
</template>
