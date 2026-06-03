<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';

const props = defineProps({
    team:  { type: Object, required: true },
    board: { type: Object, required: true },
});

const emit = defineEmits(['updated', 'cancel']);

const colorOptions = [
    { value: 'yellow', label: '黄' },
    { value: 'blue',   label: '青' },
    { value: 'green',  label: '緑' },
    { value: 'red',    label: '赤' },
    { value: 'purple', label: '紫' },
    { value: 'orange', label: 'オレンジ' },
    { value: 'gray',   label: 'グレー' },
];

const colorPreview = {
    yellow: 'bg-yellow-400',
    blue:   'bg-blue-400',
    green:  'bg-green-400',
    red:    'bg-red-400',
    purple: 'bg-purple-400',
    orange: 'bg-orange-400',
    gray:   'bg-gray-400',
};

// ディープコピーして編集用に保持
const columns = ref(
    (props.board.columns || []).map(c => ({
        id:         c.id,
        name:       c.name,
        color:      c.color,
        sort_order: c.sort_order,
    }))
);

const saving = ref(false);

function addColumn() {
    columns.value.push({ id: null, name: '新しいカラム', color: 'blue', sort_order: columns.value.length });
}

function removeColumn(idx) {
    const col = columns.value[idx];
    const hasCards = col.id && (props.board.columns.find(c => c.id === col.id)?.cards || []).length > 0;
    if (hasCards) {
        if (!confirm('このカラムにはカードがあります。削除するとカードも削除されます。続けますか？')) return;
    }
    columns.value.splice(idx, 1);
}

function moveUp(idx) {
    if (idx === 0) return;
    const arr = columns.value;
    [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
}

function moveDown(idx) {
    if (idx >= columns.value.length - 1) return;
    const arr = columns.value;
    [arr[idx], arr[idx + 1]] = [arr[idx + 1], arr[idx]];
}

async function save() {
    if (columns.value.length === 0) { alert('カラムは1つ以上必要です'); return; }
    for (const col of columns.value) {
        if (!col.name.trim()) { alert('カラム名を入力してください'); return; }
    }
    saving.value = true;
    try {
        const res = await axios.put(route('team-rooms.board.columns.update', { team: props.team.id }), {
            columns: columns.value.map((c, i) => ({ ...c, sort_order: i })),
        });
        emit('updated', res.data);
    } catch {
        alert('保存に失敗しました');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="rounded border border-amber-200 bg-amber-50 p-4">
        <h4 class="mb-3 font-semibold text-amber-900">ボード編集モード</h4>

        <div class="space-y-2">
            <div
                v-for="(col, idx) in columns"
                :key="idx"
                class="flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2"
            >
                <!-- 並び替え -->
                <div class="flex flex-col gap-0.5">
                    <button type="button" :disabled="idx === 0" class="text-gray-400 hover:text-gray-600 disabled:opacity-30" @click="moveUp(idx)">▲</button>
                    <button type="button" :disabled="idx === columns.length - 1" class="text-gray-400 hover:text-gray-600 disabled:opacity-30" @click="moveDown(idx)">▼</button>
                </div>

                <!-- カラム名 -->
                <input
                    v-model="col.name"
                    type="text"
                    maxlength="100"
                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                />

                <!-- カラー選択 -->
                <div class="flex items-center gap-1">
                    <span :class="['inline-block h-3 w-3 rounded-full', colorPreview[col.color] || 'bg-gray-400']"></span>
                    <select v-model="col.color" class="rounded border border-gray-300 px-2 py-1 text-xs">
                        <option v-for="c in colorOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                </div>

                <!-- 削除 -->
                <button
                    type="button"
                    class="rounded border border-red-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                    @click="removeColumn(idx)"
                >削除</button>
            </div>
        </div>

        <button
            type="button"
            class="mt-3 rounded border border-dashed border-gray-300 px-4 py-1.5 text-sm text-gray-500 hover:border-gray-400 hover:text-gray-700"
            @click="addColumn"
        >+ カラムを追加</button>

        <div class="mt-4 flex gap-2">
            <button
                type="button"
                class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                @click="emit('cancel')"
            >キャンセル</button>
            <button
                type="button"
                :disabled="saving"
                class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                @click="save"
            >{{ saving ? '保存中...' : '保存' }}</button>
        </div>
    </div>
</template>
