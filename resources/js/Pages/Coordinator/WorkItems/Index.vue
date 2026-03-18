<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
const props = defineProps({
    types: { type: Array, default: () => [] },
    sizes: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    company: { type: Object, default: () => null },
    department: { type: Object, default: () => null },
    companies: { type: Array, default: () => [] },
    user_role: { type: String, default: () => null },
    user_company_id: { type: [String, Number], default: () => null },
    user_department_id: { type: [String, Number], default: () => null },
});

// statuses are handled locally for display purposes
import LookupModal from '@/Components/LookupModal.vue';
import { Inertia } from '@inertiajs/inertia';
import { computed, ref, watch } from 'vue';

// local reactive copies so we can reorder in-place (props are readonly)
const typesList = ref(props.types ? props.types.slice() : []);
const sizesList = ref(props.sizes ? props.sizes.slice() : []);
const stagesList = ref(props.stages ? props.stages.slice() : []);
const statusesList = ref(props.statuses ? props.statuses.slice() : []);

// filtered lists based on selected company/department (empty selection shows all/global items)
const filteredTypes = computed(() => {
    const compId = selectedCompanyId.value ? String(selectedCompanyId.value) : null;
    const deptId = selectedDepartmentId.value ? String(selectedDepartmentId.value) : null;
    return typesList.value.filter((t) => {
        // item is global if company_id is null
        if (!t.company_id && !t.department_id) return true;
        if (compId && String(t.company_id) !== compId) return false;
        if (deptId && String(t.department_id) !== deptId) return false;
        // if company selected but item has company_id null, still show (global)
        return true;
    });
});

const filteredSizes = computed(() => {
    const compId = selectedCompanyId.value ? String(selectedCompanyId.value) : null;
    const deptId = selectedDepartmentId.value ? String(selectedDepartmentId.value) : null;
    return sizesList.value.filter((s) => {
        if (!s.company_id && !s.department_id) return true;
        if (compId && String(s.company_id) !== compId) return false;
        if (deptId && String(s.department_id) !== deptId) return false;
        return true;
    });
});

const filteredStages = computed(() => {
    const compId = selectedCompanyId.value ? String(selectedCompanyId.value) : null;
    const deptId = selectedDepartmentId.value ? String(selectedDepartmentId.value) : null;
    return stagesList.value.filter((st) => {
        if (!st.company_id && !st.department_id) return true;
        if (compId && String(st.company_id) !== compId) return false;
        if (deptId && String(st.department_id) !== deptId) return false;
        return true;
    });
});

const filteredStatuses = computed(() => {
    const compId = selectedCompanyId.value ? String(selectedCompanyId.value) : null;
    const deptId = selectedDepartmentId.value ? String(selectedDepartmentId.value) : null;
    return statusesList.value.filter((st) => {
        if (!st.company_id && !st.department_id) return true;
        if (compId && String(st.company_id) !== compId) return false;
        if (deptId && String(st.department_id) !== deptId) return false;
        return true;
    });
});

// modal state
const showModal = ref(false);
const modalKind = ref(null);
const modalItem = ref(null);
const modalMode = ref('create');
// toast state (bottom-right short messages)
const toasts = ref([]);
// selection state for header dropdowns
const selectedCompanyId = ref(props.company ? props.company.id : props.user_company_id || '');
const selectedDepartmentId = ref(props.department ? props.department.id : props.user_department_id || '');

const headerDepartments = computed(() => {
    const comp = props.companies.find((c) => String(c.id) === String(selectedCompanyId.value));
    return comp ? comp.departments || [] : [];
});

function onHeaderCompanyChange() {
    selectedDepartmentId.value = '';
}

// debounce helper
let debounceTimer = null;
function sendHeaderFilters() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const params = Object.assign({}, props.filters || {});
        if (selectedCompanyId.value) params.company = selectedCompanyId.value;
        else delete params.company;
        if (selectedDepartmentId.value) params.department = selectedDepartmentId.value;
        else delete params.department;
        Inertia.get(route('coordinator.work_items.index'), params, { preserveState: true, replace: true });
    }, 500);
}

watch(selectedCompanyId, () => sendHeaderFilters());
watch(selectedDepartmentId, () => sendHeaderFilters());

function nextToastId() {
    return toasts.value.length ? Math.max(...toasts.value.map((t) => t.id)) + 1 : 1;
}

function showToast(message, type = 'info', timeout = 3000) {
    const id = nextToastId();
    toasts.value.push({ id, message, type });
    setTimeout(() => dismissToast(id), timeout);
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function toastClass(type) {
    if (type === 'success') return 'rounded bg-green-600 px-4 py-2 text-white shadow';
    if (type === 'error') return 'rounded bg-red-600 px-4 py-2 text-white shadow';
    return 'rounded bg-black px-4 py-2 text-white shadow';
}

function openModal(kind) {
    modalKind.value = kind;
    modalItem.value = null;
    modalMode.value = 'create';
    showModal.value = true;
}

function openEditModal(kind, item) {
    modalKind.value = kind;
    modalItem.value = item;
    modalMode.value = 'edit';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function handleCreated(item) {
    const table = modalKind.value && { Type: 'types', Size: 'sizes', Stage: 'stages', Status: 'statuses' }[modalKind.value];
    if (!table) return;
    // if editing, replace existing item
    if (modalMode.value === 'edit' && modalItem.value) {
        const lists = { types: typesList, sizes: sizesList, stages: stagesList, statuses: statusesList };
        const list = lists[table];
        if (list) {
            const idx = list.value.findIndex((x) => x.id === item.id);
            if (idx !== -1) list.value.splice(idx, 1, item);
            else list.value.unshift(item);
        }
    } else {
        if (table === 'types') typesList.value.unshift(item);
        if (table === 'sizes') sizesList.value.unshift(item);
        if (table === 'stages') stagesList.value.unshift(item);
        if (table === 'statuses') statusesList.value.unshift(item);
    }
    // show toast
    showToast(modalMode.value === 'edit' ? '更新しました' : '作成しました', 'success', 2500);
}

async function addNew(kind) {
    const name = typeof window !== 'undefined' ? window.prompt(`${kind} の名前を入力してください:`) : null;
    if (!name) return;

    const tableMap = { Type: 'types', Size: 'sizes', Stage: 'stages', Status: 'statuses' };
    const table = tableMap[kind];
    if (!table) {
        window.alert('追加対象が見つかりません');
        return;
    }

    const payload = { table, name };

    if (table === 'sizes') {
        const unit = window.prompt('単位を入力してください (mm, cm, px など) （デフォルト: mm）', 'mm') || 'mm';
        const w = window.prompt(`横の長さを ${unit} 単位で入力してください (数字)`, '0');
        const h = window.prompt(`縦の長さを ${unit} 単位で入力してください (数字)`, '0');
        const width = parseFloat(w) || 0;
        const height = parseFloat(h) || 0;
        payload.width = width;
        payload.height = height;
        payload.unit = unit;
    }

    try {
        const res = await fetch(route('coordinator.work_items.lookups.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const errBody = await res.json().catch(() => null);
            throw new Error((errBody && errBody.error) || '追加失敗');
        }
        const body = await res.json();
        if (body && body.item) {
            if (table === 'types') typesList.value.unshift(body.item);
            if (table === 'sizes') sizesList.value.unshift(body.item);
            if (table === 'stages') stagesList.value.unshift(body.item);
            if (table === 'statuses') statusesList.value.unshift(body.item);
        }
    } catch (err) {
        console.error(err);
        window.alert('追加に失敗しました: ' + (err.message || ''));
    }
}

// drag state
const dragState = ref({});

function onDragStart(e, table, id) {
    dragState.value = { table, id };
    e.dataTransfer?.setData('text/plain', String(id));
}

function onDragOver(e) {
    e.preventDefault();
}

function onDrop(e, table, dropId) {
    e.preventDefault();
    const draggedId = dragState.value.id || parseInt(e.dataTransfer?.getData('text/plain'));
    if (!draggedId) return;
    reorderLocal(table, draggedId, dropId);
}

function reorderLocal(table, draggedId, dropId) {
    let arrRef = null;
    if (table === 'types') arrRef = typesList;
    if (table === 'sizes') arrRef = sizesList;
    if (table === 'stages') arrRef = stagesList;
    if (!arrRef) return;

    const arr = arrRef.value;
    const draggedIndex = arr.findIndex((x) => x.id === draggedId);
    const dropIndex = arr.findIndex((x) => x.id === dropId);
    if (draggedIndex === -1 || dropIndex === -1) return;

    const item = arr.splice(draggedIndex, 1)[0];
    arr.splice(dropIndex, 0, item);
    // reassign to trigger reactivity
    arrRef.value = arr.slice();

    // immediately persist the new order
    saveOrder(
        table,
        arrRef.value.map((x) => x.id),
    );
}

async function saveOrder(table, ids) {
    try {
        const res = await fetch(route('coordinator.work_items.lookups.save_order'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ table, ids }),
        });
        if (!res.ok) throw new Error('保存に失敗しました');
        // optionally show toast
    } catch (err) {
        console.error(err);
        if (typeof window !== 'undefined') window.alert('並び順の保存に失敗しました');
    }
}
</script>

<template>
    <AppLayout title="Work Items">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>
        <div class="rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-semibold">Work Items（作業項目）</h1>
            <div class="mb-2 flex flex-wrap items-center gap-4">
                <!-- Company select: visible to superadmin only -->
                <div v-if="user_role === 'superadmin'" class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">会社</label>
                    <div>
                        <select
                            v-model="selectedCompanyId"
                            @change="onHeaderCompanyChange"
                            aria-label="会社を選択"
                            class="block w-56 rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">-- 会社を選択 --</option>
                            <option v-for="c in props.companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Department select: visible to superadmin and admin -->
                <div v-if="user_role === 'superadmin' || user_role === 'admin'" class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">部署</label>
                    <div>
                        <select
                            v-model="selectedDepartmentId"
                            :disabled="(user_role === 'admin' && !selectedCompanyId) || (user_role !== 'superadmin' && user_role !== 'admin')"
                            aria-label="部署を選択"
                            :class="[
                                'block w-56 rounded-md border px-2 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                                (user_role === 'admin' && !selectedCompanyId) || (user_role !== 'superadmin' && user_role !== 'admin')
                                    ? 'cursor-not-allowed bg-gray-50 opacity-50'
                                    : 'bg-white',
                            ]"
                        >
                            <option value="">-- 部署を選択 --</option>
                            <option v-for="d in headerDepartments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Fallback display for roles that cannot choose -->
                <div v-if="user_role !== 'superadmin' && user_role !== 'admin'" class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500">会社:</span>
                    <span class="font-medium text-gray-800">{{ company ? company.name : '-' }}</span>
                    <span v-if="department" class="text-sm text-gray-500">/</span>
                    <span v-if="department" class="text-sm text-gray-700">{{ department.name }}</span>
                </div>
            </div>
            <p class="mb-6 text-sm text-gray-600">ここにWorkItemsの一覧を表示します（ひな形）。</p>
            <div class="mb-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Types -->
                    <div class="rounded-md bg-white p-4 shadow">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-lg font-medium">Type</h2>
                            <button @click.prevent="openModal('Type')" class="rounded bg-blue-600 px-3 py-1 text-sm text-white">新規追加</button>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="t in filteredTypes"
                                    :key="t.id"
                                    draggable="true"
                                    @dragstart="(e) => onDragStart(e, 'types', t.id)"
                                    @dragover="onDragOver"
                                    @drop="(e) => onDrop(e, 'types', t.id)"
                                >
                                    <td class="flex items-center justify-between px-3 py-2 text-sm text-gray-900">
                                        <span>{{ t.name }}</span>
                                        <button @click.prevent="openEditModal('Type', t)" class="ml-2 rounded bg-gray-200 px-2 py-1 text-xs">
                                            編集
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!typesList || typesList.length === 0">
                                    <td class="px-3 py-2 text-sm text-gray-500">登録なし</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sizes -->
                    <div class="rounded-md bg-white p-4 shadow">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-lg font-medium">Size</h2>
                            <button @click.prevent="openModal('Size')" class="rounded bg-blue-600 px-3 py-1 text-sm text-white">新規追加</button>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="s in filteredSizes"
                                    :key="s.id"
                                    draggable="true"
                                    @dragstart="(e) => onDragStart(e, 'sizes', s.id)"
                                    @dragover="onDragOver"
                                    @drop="(e) => onDrop(e, 'sizes', s.id)"
                                >
                                    <td class="px-3 py-2 text-sm text-gray-900">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-medium">{{ s.name }}</div>
                                                <div class="text-xs text-gray-500">
                                                    <span v-if="s.width || s.height">
                                                        {{ s.width ? Math.round(Number(s.width)) : '' }}
                                                        {{ s.width && s.height ? 'x' : '' }}
                                                        {{ s.height ? Math.round(Number(s.height)) : '' }}
                                                        {{ s.unit ? s.unit : '' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <button @click.prevent="openEditModal('Size', s)" class="ml-2 rounded bg-gray-200 px-2 py-1 text-xs">
                                                    編集
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!sizesList || sizesList.length === 0">
                                    <td class="px-3 py-2 text-sm text-gray-500">登録なし</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Stages -->
                    <div class="rounded-md bg-white p-4 shadow">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-lg font-medium">Stage</h2>
                            <button @click.prevent="openModal('Stage')" class="rounded bg-blue-600 px-3 py-1 text-sm text-white">新規追加</button>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="st in filteredStages"
                                    :key="st.id"
                                    draggable="true"
                                    @dragstart="(e) => onDragStart(e, 'stages', st.id)"
                                    @dragover="onDragOver"
                                    @drop="(e) => onDrop(e, 'stages', st.id)"
                                >
                                    <td class="flex items-center justify-between px-3 py-2 text-sm text-gray-900">
                                        <span>{{ st.name }}</span>
                                        <button @click.prevent="openEditModal('Stage', st)" class="ml-2 rounded bg-gray-200 px-2 py-1 text-xs">
                                            編集
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!stagesList || stagesList.length === 0">
                                    <td class="px-3 py-2 text-sm text-gray-500">登録なし</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Statuses -->
                    <div class="rounded-md bg-white p-4 shadow">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-lg font-medium">Status</h2>
                            <button @click.prevent="openModal('Status')" class="rounded bg-blue-600 px-3 py-1 text-sm text-white">新規追加</button>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="st in filteredStatuses"
                                    :key="st.id"
                                    draggable="true"
                                    @dragstart="(e) => onDragStart(e, 'statuses', st.id)"
                                    @dragover="onDragOver"
                                    @drop="(e) => onDrop(e, 'statuses', st.id)"
                                >
                                    <td class="flex items-center justify-between px-3 py-2 text-sm text-gray-900">
                                        <span>{{ st.name }}</span>
                                        <button @click.prevent="openEditModal('Status', st)" class="ml-2 rounded bg-gray-200 px-2 py-1 text-xs">
                                            編集
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <LookupModal
            v-if="showModal"
            :kind="modalKind"
            :show="showModal"
            :item="modalItem"
            :mode="modalMode"
            :companies="props.companies"
            :user-role="props.user_role"
            :current-company-id="selectedCompanyId"
            :current-department-id="selectedDepartmentId"
            @created="handleCreated"
            @close="closeModal"
        />
        <!-- Toast container -->
        <div class="fixed bottom-6 right-6 space-y-2">
            <div v-for="t in toasts" :key="t.id" :class="toastClass(t.type)" class="max-w-sm">
                <div class="flex items-center justify-between">
                    <div class="text-sm">{{ t.message }}</div>
                    <button @click="dismissToast(t.id)" class="ml-3 text-xs text-white">✕</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
