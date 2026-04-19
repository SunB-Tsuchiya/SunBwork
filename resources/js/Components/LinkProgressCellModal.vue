<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    show:         { type: Boolean, default: false },
    assignmentId: { type: Number,  default: null },
});

const emit = defineEmits(['close', 'linked']);

// ── ステート ────────────────────────────────────────────
const loading       = ref(false);
const sheets        = ref([]);
const selectedSheet = ref(null);
const selectedRowId = ref(null);
const selectedColKey = ref(null);
const submitting    = ref(false);
const conflict      = ref(null); // { existing_title, existing_id }

// 現在選択中のシートオブジェクト
const currentSheet = computed(() =>
    sheets.value.find((s) => s.id === selectedSheet.value) ?? null
);

// 行フラット（親→子順で表示）
const displayRows = computed(() => {
    if (!currentSheet.value) return [];
    const rows = currentSheet.value.rows;
    const result = [];
    const parents = rows.filter((r) => !r.parent_id);
    for (const p of parents) {
        result.push(p);
        for (const c of rows.filter((r) => r.parent_id === p.id)) {
            result.push({ ...c, _isChild: true });
        }
    }
    return result;
});

// ── モーダルを開くたびにデータ取得 ────────────────────────
watch(() => props.show, async (val) => {
    if (!val || !props.assignmentId) return;
    reset();
    loading.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(route('coordinator.progress_sheets.assignments.link_options', { assignment: props.assignmentId }), {
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            sheets.value = data.sheets ?? [];
            if (sheets.value.length === 1) selectedSheet.value = sheets.value[0].id;
        }
    } catch { /* ignore */ }
    finally { loading.value = false; }
});

function reset() {
    sheets.value      = [];
    selectedSheet.value  = null;
    selectedRowId.value  = null;
    selectedColKey.value = null;
    conflict.value    = null;
}

async function submit(force = false) {
    if (!selectedSheet.value || !selectedRowId.value || !selectedColKey.value) return;
    submitting.value = true;
    conflict.value   = null;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(route('coordinator.progress_sheets.assignments.link_cell', { assignment: props.assignmentId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                sheet_id: selectedSheet.value,
                row_id:   selectedRowId.value,
                col_key:  selectedColKey.value,
                force,
            }),
        });
        if (res.status === 409) {
            const data = await res.json();
            conflict.value = data;
            return;
        }
        if (res.ok) {
            emit('linked');
            emit('close');
        }
    } catch { /* ignore */ }
    finally { submitting.value = false; }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

            <div class="relative z-10 w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">進行表セルに紐づける</h3>

                <!-- ローディング -->
                <div v-if="loading" class="py-8 text-center text-sm text-gray-400">読み込み中…</div>

                <!-- 進行表なし -->
                <div v-else-if="sheets.length === 0" class="py-6 text-center text-sm text-gray-500">
                    この案件には進行管理表がありません。
                </div>

                <template v-else>
                    <!-- 競合警告 -->
                    <div v-if="conflict" class="mb-4 rounded border border-orange-300 bg-orange-50 p-3 text-sm">
                        <p class="font-medium text-orange-700">このセルには既にジョブが登録されています：</p>
                        <p class="mt-1 text-orange-800">「{{ conflict.existing_title }}」(#{{ conflict.existing_id }})</p>
                        <p class="mt-2 text-xs text-orange-600">上書きすると、既存のジョブとの紐づけが解除されます。</p>
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50"
                                @click="conflict = null"
                            >キャンセル</button>
                            <button
                                type="button"
                                class="rounded bg-orange-500 px-3 py-1 text-xs font-medium text-white hover:bg-orange-600"
                                :disabled="submitting"
                                @click="submit(true)"
                            >{{ submitting ? '処理中…' : '上書きして紐づける' }}</button>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- シート選択 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">進行管理表</label>
                            <select
                                v-model="selectedSheet"
                                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                                @change="selectedRowId = null; selectedColKey = null"
                            >
                                <option :value="null">— 選択してください —</option>
                                <option v-for="s in sheets" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <!-- 行選択 -->
                        <div v-if="selectedSheet && currentSheet">
                            <label class="block text-sm font-medium text-gray-700">行（台割）</label>
                            <select
                                v-model="selectedRowId"
                                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                            >
                                <option :value="null">— 選択してください —</option>
                                <option
                                    v-for="r in displayRows"
                                    :key="r.id"
                                    :value="r.id"
                                >{{ r._isChild ? '　' + r.label : r.label }}</option>
                            </select>
                        </div>

                        <!-- 列選択 -->
                        <div v-if="selectedSheet && selectedRowId && currentSheet">
                            <label class="block text-sm font-medium text-gray-700">列（セル）</label>
                            <select
                                v-model="selectedColKey"
                                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                            >
                                <option :value="null">— 選択してください —</option>
                                <option v-for="leaf in currentSheet.leaves" :key="leaf.key" :value="leaf.key">
                                    {{ leaf.path_label }}
                                </option>
                            </select>
                        </div>

                        <!-- 選択サマリー -->
                        <div v-if="selectedSheet && selectedRowId && selectedColKey" class="rounded bg-indigo-50 px-3 py-2 text-xs text-indigo-700">
                            <span class="font-medium">{{ currentSheet?.name }}</span>
                            ／
                            <span>{{ displayRows.find(r => r.id === selectedRowId)?.label }}</span>
                            ／
                            <span>{{ currentSheet?.leaves.find(l => l.key === selectedColKey)?.path_label }}</span>
                        </div>
                    </div>
                </template>

                <!-- フッターボタン -->
                <div v-if="!conflict" class="mt-5 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        @click="emit('close')"
                    >キャンセル</button>
                    <button
                        v-if="sheets.length > 0"
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="!selectedSheet || !selectedRowId || !selectedColKey || submitting"
                        @click="submit(false)"
                    >{{ submitting ? '処理中…' : '紐づける' }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
