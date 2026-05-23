<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import useToasts from '@/Composables/useToasts';
import axios from 'axios';
import { route } from 'ziggy-js';

const { showToast } = useToasts();

const props = defineProps({
    salesReps:   { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const PRESET_COMPANIES = ['株式会社サンエー印刷', '株式会社サン・ブレーン'];

const localReps = ref([...props.salesReps]);
watch(() => props.salesReps, (val) => { localReps.value = [...val]; });

// ── 新規登録フォーム ──────────────────────────────────
const showCreateForm = ref(false);
const createForm = useForm({
    name:           '',
    company:        '',
    company_custom: '',
    department_ids: props.departments.map(d => d.id),
});
const createCompanyMode = ref('preset');

function normalizeName(s) {
    return s.replace(/[　\s]+/g, ' ').trim();
}

function computedCompany(form, mode) {
    return mode === 'custom' ? form.company_custom : form.company;
}

function submitCreate() {
    const payload = {
        name:           normalizeName(createForm.name),
        company:        computedCompany(createForm, createCompanyMode.value),
        department_ids: createForm.department_ids,
    };
    router.post(route('coordinator.sales_reps.store'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateForm.value = false;
            createForm.reset();
            createCompanyMode.value = 'preset';
            showToast('営業担当を登録しました。', 'success', 3000);
        },
        onError: (errors) => {
            showToast(errors.name ?? '登録に失敗しました。', 'error', 5000);
        },
    });
}

// ── 編集フォーム ──────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name:           '',
    company:        '',
    company_custom: '',
    department_ids: [],
});
const editCompanyMode = ref('preset');

function startEdit(rep) {
    editingId.value = rep.id;
    const isPreset = PRESET_COMPANIES.includes(rep.company ?? '');
    editCompanyMode.value = isPreset ? 'preset' : 'custom';
    editForm.name    = rep.name;
    editForm.company = isPreset ? (rep.company ?? '') : '';
    editForm.company_custom = isPreset ? '' : (rep.company ?? '');
    editForm.department_ids = rep.departments?.map(d => d.id) ?? [];
}

function cancelEdit() { editingId.value = null; }

function submitEdit(rep) {
    const payload = {
        name:           normalizeName(editForm.name),
        company:        computedCompany(editForm, editCompanyMode.value),
        department_ids: editForm.department_ids,
    };
    router.patch(route('coordinator.sales_reps.update', { salesRep: rep.id }), payload, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; showToast('更新しました。', 'success', 3000); },
        onError: (errors) => { showToast(errors.name ?? '更新に失敗しました。', 'error', 5000); },
    });
}

function destroy(rep) {
    if (!confirm(`「${rep.name}」を削除しますか？`)) return;
    router.delete(route('coordinator.sales_reps.destroy', { salesRep: rep.id }), {
        preserveScroll: true,
        onSuccess: () => { showToast('削除しました。', 'success', 3000); },
    });
}

function deptLabel(rep) {
    return rep.departments?.map(d => d.name).join('・') || '—';
}

const searchQ = ref('');
const filteredReps = computed(() => {
    const q = searchQ.value.trim().toLowerCase();
    if (!q) return localReps.value;
    return localReps.value.filter(r =>
        r.name.toLowerCase().includes(q) || (r.company ?? '').toLowerCase().includes(q)
    );
});

// ── 並べ替え ──────────────────────────────────────────
const reorderSaving = ref(false);

async function saveOrder() {
    reorderSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        await axios.post(route('coordinator.sales_reps.reorder'), {
            ids: localReps.value.map(r => r.id),
        }, { headers: { 'X-CSRF-TOKEN': csrf } });
    } catch {
        showToast('並び順の保存に失敗しました。', 'error', 4000);
    } finally {
        reorderSaving.value = false;
    }
}

function moveUp(idx) {
    if (idx === 0) return;
    const arr = [...localReps.value];
    [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
    localReps.value = arr;
    saveOrder();
}

function moveDown(idx) {
    if (idx === localReps.value.length - 1) return;
    const arr = [...localReps.value];
    [arr[idx], arr[idx + 1]] = [arr[idx + 1], arr[idx]];
    localReps.value = arr;
    saveOrder();
}

// ── 一括挿入モード ────────────────────────────────────
const bulkMode   = ref(false);
const bulkText   = ref('');
const bulkSaving = ref(false);
const bulkResult = ref(null);

function toggleBulkMode() { bulkMode.value = !bulkMode.value; bulkText.value = ''; bulkResult.value = null; }

const bulkNames = computed(() =>
    bulkText.value.split('\n').map(n => normalizeName(n)).filter(n => n !== '')
);
const bulkInternalDups = computed(() => {
    const seen = new Set(); const dups = new Set();
    for (const n of bulkNames.value) { if (seen.has(n)) dups.add(n); seen.add(n); }
    return dups;
});
const bulkDbDups = computed(() => {
    const dbNames = new Set(localReps.value.map(r => r.name));
    return new Set(bulkNames.value.filter(n => dbNames.has(n)));
});
const bulkNewCount = computed(() =>
    [...new Set(bulkNames.value)].filter(n => !bulkDbDups.value.has(n)).length
);

async function executeBulkStore() {
    if (bulkNames.value.length === 0 || bulkSaving.value) return;
    bulkSaving.value = true;
    bulkResult.value = null;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const company = computedCompany(createForm, createCompanyMode.value);
    try {
        const res = await axios.post(route('coordinator.sales_reps.bulkStore'), {
            names:          bulkNames.value,
            company:        company || null,
            department_ids: createForm.department_ids,
        }, { headers: { 'X-CSRF-TOKEN': csrf } });
        bulkResult.value = res.data;
        bulkText.value = '';
        showToast(`${res.data.created}件の営業担当を登録しました。`, 'success', 4000);
        router.reload({ preserveScroll: true });
    } catch {
        showToast('登録に失敗しました。', 'error', 4000);
    } finally {
        bulkSaving.value = false;
    }
}
</script>

<template>
    <AppLayout title="営業担当管理">
        <template #header>
            <h2 class="text-xl font-semibold text-green-800">営業担当管理</h2>
        </template>
        <template #tabs>
            <CoordinatorNavigationTabs active="sales_reps" />
        </template>

        <div class="space-y-4">

            <div class="flex items-center gap-3 flex-wrap">
                <input v-model="searchQ" type="text" placeholder="氏名・会社で絞り込み"
                    class="rounded border border-gray-300 px-3 py-1.5 text-sm w-60 focus:outline-none focus:ring-1 focus:ring-green-400" />
                <button @click="showCreateForm = !showCreateForm; bulkMode = false; bulkText = ''; bulkResult = null"
                    class="rounded bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800">
                    + 新規登録
                </button>
                <span v-if="!searchQ && localReps.length > 1" class="text-xs text-gray-400">↑↓ で表示順を変更できます</span>
                <span v-if="searchQ" class="text-xs text-gray-400">絞り込み中は並べ替えできません</span>
            </div>

            <!-- 新規登録フォーム -->
            <div v-if="showCreateForm" class="rounded border border-green-200 bg-green-50 p-4 space-y-3">
                <p class="text-sm font-semibold text-green-800">新規営業担当登録</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs text-gray-600">氏名 <span class="text-red-500">*</span></label>
                            <button type="button"
                                class="text-xs rounded px-2 py-0.5 transition-colors"
                                :class="bulkMode ? 'bg-indigo-600 text-white' : 'border border-indigo-400 text-indigo-600 hover:bg-indigo-50'"
                                @click="toggleBulkMode">{{ bulkMode ? '一括挿入 ON' : '一括挿入' }}</button>
                        </div>
                        <template v-if="!bulkMode">
                            <input v-model="createForm.name" type="text" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                            <p v-if="createForm.errors.name" class="text-xs text-red-500 mt-0.5">{{ createForm.errors.name }}</p>
                        </template>
                        <template v-else>
                            <textarea v-model="bulkText" rows="6"
                                placeholder="名前を1行ずつ入力&#10;例）&#10;田中 太郎&#10;鈴木 花子"
                                class="w-full rounded border border-gray-300 px-2 py-1 text-sm font-mono resize-y focus:outline-none focus:ring-1 focus:ring-indigo-400"></textarea>
                            <div v-if="bulkNames.length > 0" class="mt-2 flex flex-wrap gap-1">
                                <span v-for="(name, idx) in bulkNames" :key="idx"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="bulkDbDups.has(name) ? 'bg-red-100 text-red-700' : bulkInternalDups.has(name) ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'"
                                >{{ name }}</span>
                            </div>
                            <div v-if="bulkNames.length > 0" class="mt-1 flex flex-wrap gap-3 text-xs text-gray-500">
                                <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-green-400"></span>登録OK: {{ bulkNewCount }}件</span>
                                <span v-if="bulkDbDups.size > 0" class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>DB登録済みスキップ: {{ bulkDbDups.size }}件</span>
                                <span v-if="bulkInternalDups.size > 0" class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-yellow-400"></span>テキスト内重複スキップ: {{ bulkInternalDups.size }}件</span>
                            </div>
                        </template>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">会社</label>
                        <select v-model="createCompanyMode" class="w-full rounded border border-gray-300 px-2 py-1 text-sm mb-1">
                            <option value="preset">プリセットから選択</option>
                            <option value="custom">自由入力</option>
                        </select>
                        <select v-if="createCompanyMode === 'preset'" v-model="createForm.company"
                            class="w-full rounded border border-gray-300 px-2 py-1 text-sm">
                            <option value="">— 選択 —</option>
                            <option v-for="c in PRESET_COMPANIES" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <input v-else v-model="createForm.company_custom" type="text"
                            class="w-full rounded border border-gray-300 px-2 py-1 text-sm" placeholder="会社名を入力" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">部署（複数可）</label>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="d in departments" :key="d.id" class="flex items-center gap-1 text-sm cursor-pointer">
                                <input type="checkbox" :value="d.id" v-model="createForm.department_ids" class="rounded" />
                                {{ d.name }}
                            </label>
                        </div>
                    </div>
                </div>
                <div v-if="!bulkMode" class="flex gap-2">
                    <button @click="submitCreate" :disabled="!createForm.name"
                        class="rounded bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50">登録</button>
                    <button @click="showCreateForm = false"
                        class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">キャンセル</button>
                </div>
                <div v-else class="flex items-center gap-3 flex-wrap">
                    <button type="button" :disabled="bulkNewCount === 0 || bulkSaving"
                        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        @click="executeBulkStore">{{ bulkSaving ? '登録中...' : `一括登録（${bulkNewCount}件）` }}</button>
                    <button @click="showCreateForm = false; bulkMode = false; bulkText = ''"
                        class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">キャンセル</button>
                    <span v-if="bulkResult" class="text-sm text-green-700 font-medium">
                        {{ bulkResult.created }}件登録完了<span v-if="bulkResult.skipped > 0">・{{ bulkResult.skipped }}件スキップ</span>
                    </span>
                </div>
            </div>

            <!-- 一覧テーブル -->
            <div class="overflow-x-auto rounded border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th v-if="!searchQ" class="px-2 py-2 w-16 text-center">順序</th>
                            <th class="px-4 py-2 text-left">氏名</th>
                            <th class="px-4 py-2 text-left">会社</th>
                            <th class="px-4 py-2 text-left">部署</th>
                            <th class="px-4 py-2 text-left w-36">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="filteredReps.length === 0">
                            <td :colspan="searchQ ? 4 : 5" class="px-4 py-6 text-center text-gray-400 text-sm">登録がありません</td>
                        </tr>
                        <template v-for="(rep, idx) in filteredReps" :key="rep.id">
                            <tr v-if="editingId !== rep.id" class="hover:bg-gray-50">
                                <td v-if="!searchQ" class="px-2 py-1 text-center whitespace-nowrap">
                                    <button @click="moveUp(idx)" :disabled="idx === 0 || reorderSaving"
                                        class="rounded px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-default" title="上へ">▲</button>
                                    <button @click="moveDown(idx)" :disabled="idx === localReps.length - 1 || reorderSaving"
                                        class="rounded px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-default" title="下へ">▼</button>
                                </td>
                                <td class="px-4 py-2 font-medium">{{ rep.name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ rep.company || '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ deptLabel(rep) }}</td>
                                <td class="px-4 py-2">
                                    <button @click="startEdit(rep)" class="rounded bg-yellow-500 px-2 py-0.5 text-xs text-white hover:bg-yellow-600 mr-1">編集</button>
                                    <button @click="destroy(rep)" class="rounded bg-red-500 px-2 py-0.5 text-xs text-white hover:bg-red-600">削除</button>
                                </td>
                            </tr>
                            <tr v-else class="bg-yellow-50">
                                <td v-if="!searchQ" class="px-2 py-1"></td>
                                <td class="px-4 py-2">
                                    <input v-model="editForm.name" type="text" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                    <p v-if="editForm.errors.name" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.name }}</p>
                                </td>
                                <td class="px-4 py-2 space-y-1">
                                    <select v-model="editCompanyMode" class="w-full rounded border border-gray-300 px-2 py-1 text-xs">
                                        <option value="preset">プリセット</option>
                                        <option value="custom">自由入力</option>
                                    </select>
                                    <select v-if="editCompanyMode === 'preset'" v-model="editForm.company"
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-sm">
                                        <option value="">— 選択 —</option>
                                        <option v-for="c in PRESET_COMPANIES" :key="c" :value="c">{{ c }}</option>
                                    </select>
                                    <input v-else v-model="editForm.company_custom" type="text"
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-sm" placeholder="会社名" />
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <label v-for="d in departments" :key="d.id" class="flex items-center gap-1 text-xs cursor-pointer">
                                            <input type="checkbox" :value="d.id" v-model="editForm.department_ids" class="rounded" />
                                            {{ d.name }}
                                        </label>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <button @click="submitEdit(rep)" class="rounded bg-green-700 px-2 py-0.5 text-xs text-white hover:bg-green-800 mr-1">保存</button>
                                    <button @click="cancelEdit" class="rounded border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50">×</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
