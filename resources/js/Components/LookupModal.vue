<script setup>
import { computed, ref, watch } from 'vue';
const props = defineProps({
    kind: { type: String, required: true },
    show: { type: Boolean, default: false },
    // optional item for edit
    item: { type: Object, default: null },
    // 'create' or 'edit'
    mode: { type: String, default: 'create' },
    companies: { type: Array, default: () => [] },
    userRole: { type: String, default: null },
    currentCompanyId: { type: [String, Number], default: '' },
    currentDepartmentId: { type: [String, Number], default: '' },
});
const emits = defineEmits(['created', 'close']);

// flatten departments from provided companies for easy select options
const departmentsFlattened = computed(() => {
    const out = [];
    (props.companies || []).forEach((c) => {
        (c.departments || []).forEach((d) => {
            out.push({ id: d.id, name: `${c.name} / ${d.name}`, company_id: c.id });
        });
    });
    return out;
});

// departments filtered by selected company in this modal
const departmentsForCompany = computed(() => {
    const sel = formState.value.company_id ? String(formState.value.company_id) : '';
    if (!sel) return departmentsFlattened.value;
    return departmentsFlattened.value.filter((d) => String(d.company_id) === sel);
});

// if user is not superadmin, force company_id to currentCompanyId
if (props.userRole !== 'superadmin') {
    formState.value.company_id = props.currentCompanyId || '';
}

// ensure department is cleared if it doesn't belong to selected company
watch(
    () => formState.value.company_id,
    (val) => {
        if (!val) return;
        const dept = formState.value.department_id;
        if (dept) {
            const found = departmentsFlattened.value.find((d) => String(d.id) === String(dept) && String(d.company_id) === String(val));
            if (!found) formState.value.department_id = '';
        }
    },
);

const formState = ref({ name: '', width: '', height: '', unit: 'mm', company_id: '', department_id: '' });
const formErrors = ref({});

function resetForm() {
    formState.value = { name: '', width: '', height: '', unit: 'mm' };
    formErrors.value = {};
}

// initialize when item prop changes (for edit)
watch(
    () => props.item,
    (it) => {
        if (it) {
            formState.value = {
                name: it.name ?? '',
                width: it.width ?? '',
                height: it.height ?? '',
                unit: it.unit ?? 'mm',
                company_id: (it.company_id ?? props.currentCompanyId) || '',
                department_id: (it.department_id ?? props.currentDepartmentId) || '',
            };
            formErrors.value = {};
        } else if (props.mode === 'create') {
            // initialize to current header selection when creating
            formState.value.company_id = props.currentCompanyId || '';
            formState.value.department_id = props.currentDepartmentId || '';
            // keep other defaults
            formState.value.name = '';
            formState.value.width = '';
            formState.value.height = '';
            formState.value.unit = 'mm';
            formErrors.value = {};
        }
    },
    { immediate: true },
);

function validateForm() {
    formErrors.value = {};
    if (!formState.value.name || String(formState.value.name).trim() === '') {
        formErrors.value.name = '名前を入力してください';
    }
    if (props.kind === 'Size') {
        const w = parseFloat(formState.value.width);
        const h = parseFloat(formState.value.height);
        if (!w || w <= 0) formErrors.value.width = '幅は正の数で入力してください';
        if (!h || h <= 0) formErrors.value.height = '高さは正の数で入力してください';
        if (!formState.value.unit) formErrors.value.unit = '単位を入力してください';
    }
    return Object.keys(formErrors.value).length === 0;
}

async function submit() {
    if (!validateForm()) return;
    const tableMap = { Type: 'types', Size: 'sizes', Stage: 'stages', Status: 'statuses' };
    const table = tableMap[props.kind];
    const payload = { table, name: formState.value.name };
    if (table === 'sizes') {
        payload.width = parseFloat(formState.value.width);
        payload.height = parseFloat(formState.value.height);
        payload.unit = formState.value.unit || 'mm';
    }
    // include company/department when provided
    if (formState.value.company_id) payload.company_id = formState.value.company_id;
    if (formState.value.department_id) payload.department_id = formState.value.department_id;

    try {
        let res;
        // if edit mode, call update endpoint
        if (props.mode === 'edit' && props.item && props.item.id) {
            res = await fetch(route('coordinator.assignments.lookups.update', { table: table, id: props.item.id }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin',
            });
        } else {
            res = await fetch(route('coordinator.assignments.lookups.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin',
            });
        }

        const body = await res.json().catch(() => null);
        if (!res.ok) {
            formErrors.value.form =
                (body && (body.error || (body.errors && Object.values(body.errors).flat()[0]))) ||
                (props.mode === 'edit' ? '更新に失敗しました' : '追加に失敗しました');
            return;
        }
        if (body && body.item) {
            emits('created', body.item);
            resetForm();
        }
        emits('close');
    } catch (err) {
        formErrors.value.form = props.mode === 'edit' ? '更新中にエラーが発生しました' : '追加中にエラーが発生しました';
    }
}

function close() {
    resetForm();
    emits('close');
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
        <div class="w-full max-w-lg rounded bg-white p-6 shadow-lg">
            <h3 class="mb-4 text-lg font-medium">{{ props.kind }} の{{ props.mode === 'edit' ? '編集' : '新規作成' }}</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">名前</label>
                    <input v-model="formState.name" class="mt-1 w-full rounded border px-2 py-1" />
                    <div v-if="formErrors.name" class="text-sm text-red-600">{{ formErrors.name }}</div>
                </div>
                <div class="grid grid-cols-2 items-end gap-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">会社（任意）</label>
                        <select
                            v-model="formState.company_id"
                            :disabled="props.userRole !== 'superadmin'"
                            class="mt-1 w-full rounded border px-2 py-1 text-sm"
                        >
                            <option value="">-- グローバル / 未設定 --</option>
                            <option v-for="c in props.companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">部署（任意）</label>
                        <select v-model="formState.department_id" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                            <option value="">-- 指定なし --</option>
                            <option v-for="d in departmentsForCompany" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="props.kind === 'Size'" class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">単位</label>
                        <input v-model="formState.unit" class="mt-1 w-full rounded border px-2 py-1" />
                        <div v-if="formErrors.unit" class="text-sm text-red-600">{{ formErrors.unit }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">横</label>
                        <input v-model="formState.width" type="number" class="mt-1 w-full rounded border px-2 py-1" />
                        <div v-if="formErrors.width" class="text-sm text-red-600">{{ formErrors.width }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">縦</label>
                        <input v-model="formState.height" type="number" class="mt-1 w-full rounded border px-2 py-1" />
                        <div v-if="formErrors.height" class="text-sm text-red-600">{{ formErrors.height }}</div>
                    </div>
                </div>
                <div v-if="formErrors.form" class="text-sm text-red-600">{{ formErrors.form }}</div>
            </div>
            <div class="mt-4 flex justify-end space-x-2">
                <button @click="close" class="rounded bg-gray-200 px-4 py-2">キャンセル</button>
                <button @click="submit" class="rounded bg-blue-600 px-4 py-2 text-white">{{ props.mode === 'edit' ? '更新' : '作成' }}</button>
            </div>
        </div>
    </div>
</template>
