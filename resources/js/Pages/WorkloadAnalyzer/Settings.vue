<script setup>
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive } from 'vue';

const page = usePage();
const props = defineProps({
    stages: { type: Array, default: () => [] },
    sizes: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    difficulties: { type: Array, default: () => [] },
});

// build options 0..3 step 0.25
const options = [];
for (let v = 0; v <= 12; v++) {
    options.push((v * 0.25).toFixed(2));
}

// reactive maps for each table keyed by id -> coefficient string
const stagesMap = reactive({});
const sizesMap = reactive({});
const typesMap = reactive({});
const difficultiesMap = reactive({});

// initialize maps from props
props.stages.forEach((s) => {
    stagesMap[s.id] = String(typeof s.coefficient !== 'undefined' ? parseFloat(s.coefficient).toFixed(2) : '1.00');
});
props.sizes.forEach((s) => {
    sizesMap[s.id] = String(typeof s.coefficient !== 'undefined' ? parseFloat(s.coefficient).toFixed(2) : '1.00');
});
props.types.forEach((t) => {
    typesMap[t.id] = String(typeof t.coefficient !== 'undefined' ? parseFloat(t.coefficient).toFixed(2) : '1.00');
});
props.difficulties.forEach((d) => {
    difficultiesMap[d.id] = String(typeof d.coefficient !== 'undefined' ? parseFloat(d.coefficient).toFixed(2) : '1.00');
});

const saving = reactive({ stages: false, sizes: false, types: false, difficulties: false });

const { showToast } = useToasts();

function saveTable(table) {
    // build rows array from the corresponding map
    let rows = [];
    if (table === 'stages') {
        rows = Object.keys(stagesMap).map((id) => ({ id: Number(id), coefficient: Number(parseFloat(stagesMap[id]) || 0) }));
    } else if (table === 'sizes') {
        rows = Object.keys(sizesMap).map((id) => ({ id: Number(id), coefficient: Number(parseFloat(sizesMap[id]) || 0) }));
    } else if (table === 'types') {
        rows = Object.keys(typesMap).map((id) => ({ id: Number(id), coefficient: Number(parseFloat(typesMap[id]) || 0) }));
    } else if (table === 'difficulties') {
        rows = Object.keys(difficultiesMap).map((id) => ({ id: Number(id), coefficient: Number(parseFloat(difficultiesMap[id]) || 0) }));
    }

    saving[table] = true;
    axios
        .post('/leader/workload-analyzer/settings', { table, rows })
        .then((res) => {
            showToast(res.data && res.data.message ? res.data.message : '保存しました。', 'success');
        })
        .catch((err) => {
            console.error('saveTable axios error', err);
            showToast('保存に失敗しました。', 'error');
        })
        .finally(() => {
            saving[table] = false;
        });
}
</script>

<template>
    <AppLayout title="作業量分析 設定">
        <Head title="作業量分析 設定" />

        <div class="rounded bg-white p-6 shadow">
                    <h1 class="mb-4 text-xl font-semibold">作業量分析 - 設定</h1>

                    <div class="space-y-6">
                        <!-- Stages -->
                        <section>
                            <h2 class="mb-2 text-sm font-medium">ステージ</h2>
                            <div class="rounded border bg-gray-50 p-3">
                                <div v-if="!props.stages.length" class="text-sm text-gray-500">ステージが設定されていません。</div>
                                <div v-for="s in props.stages" :key="s.id" class="flex items-center justify-between py-1">
                                    <div class="text-sm text-gray-700">{{ s.name }}</div>
                                    <div>
                                        <select v-model="stagesMap[s.id]" class="w-28 rounded border px-2 py-1 text-sm">
                                            <option v-for="o in options" :key="o" :value="o">{{ o }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pt-3">
                                    <button
                                        :disabled="saving.stages"
                                        @click="saveTable('stages')"
                                        class="inline-flex items-center rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-700"
                                    >
                                        <span v-if="!saving.stages">保存（ステージ）</span>
                                        <span v-else>保存中…</span>
                                    </button>
                                </div>
                            </div>
                        </section>

                        <!-- Sizes -->
                        <section>
                            <h2 class="mb-2 text-sm font-medium">サイズ</h2>
                            <div class="rounded border bg-gray-50 p-3">
                                <div v-if="!props.sizes.length" class="text-sm text-gray-500">サイズが設定されていません。</div>
                                <div v-for="z in props.sizes" :key="z.id" class="flex items-center justify-between py-1">
                                    <div class="text-sm text-gray-700">{{ z.name }}</div>
                                    <div>
                                        <select v-model="sizesMap[z.id]" class="w-28 rounded border px-2 py-1 text-sm">
                                            <option v-for="o in options" :key="o" :value="o">{{ o }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pt-3">
                                    <button
                                        :disabled="saving.sizes"
                                        @click="saveTable('sizes')"
                                        class="inline-flex items-center rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-700"
                                    >
                                        <span v-if="!saving.sizes">保存（サイズ）</span>
                                        <span v-else>保存中…</span>
                                    </button>
                                </div>
                            </div>
                        </section>

                        <!-- Types -->
                        <section>
                            <h2 class="mb-2 text-sm font-medium">種別</h2>
                            <div class="rounded border bg-gray-50 p-3">
                                <div v-if="!props.types.length" class="text-sm text-gray-500">種別が設定されていません。</div>
                                <div v-for="t in props.types" :key="t.id" class="flex items-center justify-between py-1">
                                    <div class="text-sm text-gray-700">{{ t.name }}</div>
                                    <div>
                                        <select v-model="typesMap[t.id]" class="w-28 rounded border px-2 py-1 text-sm">
                                            <option v-for="o in options" :key="o" :value="o">{{ o }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pt-3">
                                    <button
                                        :disabled="saving.types"
                                        @click="saveTable('types')"
                                        class="inline-flex items-center rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-700"
                                    >
                                        <span v-if="!saving.types">保存（種別）</span>
                                        <span v-else>保存中…</span>
                                    </button>
                                </div>
                            </div>
                        </section>

                        <!-- Difficulties -->
                        <section>
                            <h2 class="mb-2 text-sm font-medium">難易度</h2>
                            <div class="rounded border bg-gray-50 p-3">
                                <div v-if="!props.difficulties.length" class="text-sm text-gray-500">難易度が設定されていません。</div>
                                <div v-for="d in props.difficulties" :key="d.id" class="flex items-center justify-between py-1">
                                    <div class="text-sm text-gray-700">{{ d.name }}</div>
                                    <div>
                                        <select v-model="difficultiesMap[d.id]" class="w-28 rounded border px-2 py-1 text-sm">
                                            <option v-for="o in options" :key="o" :value="o">{{ o }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pt-3">
                                    <button
                                        :disabled="saving.difficulties"
                                        @click="saveTable('difficulties')"
                                        class="inline-flex items-center rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-700"
                                    >
                                        <span v-if="!saving.difficulties">保存（難易度）</span>
                                        <span v-else>保存中…</span>
                                    </button>
                                </div>
                            </div>
                        </section>

                        <div class="pt-4">
                            <a href="/leader/workload-analyzer" class="text-sm text-gray-600">キャンセル</a>
                        </div>
                    </div>
        </div>
    </AppLayout>
</template>
