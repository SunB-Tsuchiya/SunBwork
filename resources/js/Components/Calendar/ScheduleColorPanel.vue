<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { CLERK_EVENT_COLORS } from '@/Components/Clerk/clerkEventColors';

// チームルーム・案件のカレンダーの色設定の窓（Clerk の ClerkCalendarColorPanel と同じ見た目・操作）。
// 取得・更新・並び替えの URL と、変更できるかを props で受ける。
const props = defineProps({
    open:       { type: Boolean, default: false },
    colorsUrl:  { type: String, required: true },       // GET: { colors: [{ color_key, label, sort_order }], last_changed: { name, at } | null }
    updateUrl:  { type: Function, required: true },     // (colorKey) => PATCH の URL
    reorderUrl: { type: String, required: true },       // POST の URL
    canEdit:    { type: Boolean, default: false },      // 変更できる人だけ true（閲覧のみは、見るだけ）
});

const emit = defineEmits(['update:open', 'colors-updated']);

const colors = ref([]);
const lastChanged = ref(null);
const serverCanEdit = ref(null);   // サーバーが返す「変更できるか」（返さないときは、props.canEdit）
const editable = computed(() => serverCanEdit.value ?? props.canEdit);
const loading = ref(false);
const dragSrcIdx = ref(null);
const dragOverIdx = ref(null);

async function fetchColors() {
    loading.value = true;
    try {
        const res = await axios.get(props.colorsUrl);
        colors.value = res.data.colors;
        lastChanged.value = res.data.last_changed ?? null;
        if (typeof res.data.can_edit === 'boolean') serverCanEdit.value = res.data.can_edit;
        emit('colors-updated', colors.value);
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
}

watch(() => props.open, (val) => { if (val) fetchColors(); });

const labelSaveTimers = {};
function onLabelInput(item) {
    clearTimeout(labelSaveTimers[item.color_key]);
    labelSaveTimers[item.color_key] = setTimeout(() => saveLabel(item), 500);
}

async function saveLabel(item) {
    try {
        const res = await axios.patch(props.updateUrl(item.color_key), { label: item.label || null });
        lastChanged.value = res.data.last_changed ?? lastChanged.value;
        emit('colors-updated', colors.value);
    } catch {
        alert('保存に失敗しました');
    }
}

function onDragStart(idx) { if (editable.value) dragSrcIdx.value = idx; }
function onDragOver(idx)  { if (editable.value) dragOverIdx.value = idx; }
function onDragEnd()      { dragSrcIdx.value = null; dragOverIdx.value = null; }

async function onDrop(targetIdx) {
    const srcIdx = dragSrcIdx.value;
    dragSrcIdx.value = null;
    dragOverIdx.value = null;
    if (!editable.value || srcIdx === null || srcIdx === targetIdx) return;

    const arr = [...colors.value];
    const [moved] = arr.splice(srcIdx, 1);
    arr.splice(targetIdx, 0, moved);
    arr.forEach((c, i) => { c.sort_order = i; });
    colors.value = arr;
    emit('colors-updated', colors.value);

    try {
        const res = await axios.post(props.reorderUrl, { orders: arr.map((c, i) => ({ color_key: c.color_key, sort_order: i })) });
        lastChanged.value = res.data.last_changed ?? lastChanged.value;
    } catch {
        alert('並び替えの保存に失敗しました');
    }
}

function close() { emit('update:open', false); }
</script>

<template>
    <div v-if="open" class="mb-4 rounded border border-gray-200 bg-gray-50 p-4" @click.stop>
        <div class="mb-2 text-xs font-semibold text-gray-600">
            色の設定<template v-if="editable">（ドラッグで並び替え可・ラベルは自由記入）</template><template v-else>（閲覧のみ）</template>
        </div>
        <div v-if="loading" class="text-xs text-gray-400">読込中...</div>
        <div v-else class="flex flex-wrap gap-x-2 gap-y-1">
            <div
                v-for="(item, idx) in colors"
                :key="item.color_key"
                :draggable="editable"
                class="flex select-none items-center gap-1.5 rounded border px-2 py-1"
                :class="[editable ? 'cursor-grab' : '', dragOverIdx === idx ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:bg-gray-50']"
                @dragstart="onDragStart(idx)"
                @dragover.prevent="onDragOver(idx)"
                @drop="onDrop(idx)"
                @dragend="onDragEnd"
            >
                <span v-if="editable" class="select-none text-xs text-gray-300">⠿</span>
                <span :class="[CLERK_EVENT_COLORS[item.color_key]?.swatch, 'h-3.5 w-3.5 shrink-0 rounded-full']"></span>
                <input
                    type="text"
                    v-model="item.label"
                    maxlength="6"
                    :disabled="!editable"
                    :placeholder="editable ? 'ラベル未設定' : ''"
                    class="w-24 rounded border border-gray-200 px-1.5 py-0.5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none disabled:bg-gray-50"
                    @input="onLabelInput(item)"
                />
            </div>
        </div>
        <p v-if="lastChanged" class="mt-2 text-xs text-gray-500">最後に変更: {{ lastChanged.name }}さん（{{ lastChanged.at }}）</p>
        <button
            type="button"
            class="mt-2 rounded border border-gray-300 px-3 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
            @click="close"
        >閉じる</button>
    </div>
</template>
