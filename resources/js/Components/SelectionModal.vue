<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
const props = defineProps({
    show: { type: Boolean, default: false },
    companies: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    sizes: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    userRole: { type: String, default: null },
    currentCompanyId: { type: [String, Number], default: '' },
    currentDepartmentId: { type: [String, Number], default: '' },
});
const emit = defineEmits(['close', 'selected']);

const form = ref({
    company_id: props.currentCompanyId || '',
    department_id: props.currentDepartmentId || '',
    type_id: '',
    size_id: '',
    stage_id: '',
    status_id: '',
});

const page = usePage();

// determine if company/department should be changeable according to role
const companyDisabled = computed(() => {
    // only superadmin can change company
    return props.userRole !== 'superadmin';
});
const departmentDisabled = computed(() => {
    // leader and coordinator cannot change department
    return props.userRole === 'leader' || props.userRole === 'coordinator';
});

const departmentsFlattened = computed(() => {
    const out = [];
    (props.companies || []).forEach((c) => {
        const deps = c && c.departments ? c.departments : [];
        // departments might be plain arrays or Eloquent collections/models
        Array.from(deps || []).forEach((d) => {
            const id = d && (d.id ?? d['id']) ? (d.id ?? d['id']) : null;
            const name = d && (d.name ?? d['name']) ? (d.name ?? d['name']) : null;
            out.push({ id: id, name: name, company_id: c && (c.id ?? c['id']) ? (c.id ?? c['id']) : null });
        });
    });
    return out;
});

const departmentsForCompany = computed(() => {
    if (!form.value.company_id) return departmentsFlattened.value;
    return departmentsFlattened.value.filter((d) => String(d.company_id) === String(form.value.company_id));
});

// filter types/sizes/stages by selected department (require department selected)
const typesForSelect = computed(() => {
    const list = props.types || [];
    if (!form.value.department_id) return [];
    return list.filter((t) => {
        const compMatch = !t.company_id || String(t.company_id) === String(form.value.company_id);
        const deptMatch = !t.department_id || String(t.department_id) === String(form.value.department_id);
        return compMatch && deptMatch;
    });
});

const sizesForSelect = computed(() => {
    const list = props.sizes || [];
    if (!form.value.department_id) return [];
    return list.filter((s) => {
        const compMatch = !s.company_id || String(s.company_id) === String(form.value.company_id);
        const deptMatch = !s.department_id || String(s.department_id) === String(form.value.department_id);
        return compMatch && deptMatch;
    });
});

const stagesForSelect = computed(() => {
    const list = props.stages || [];
    if (!form.value.department_id) return [];
    return list.filter((st) => {
        const compMatch = !st.company_id || String(st.company_id) === String(form.value.company_id);
        const deptMatch = !st.department_id || String(st.department_id) === String(form.value.department_id);
        return compMatch && deptMatch;
    });
});

// Safe fallbacks: only when department selected; otherwise keep empty to force department choice
const typesForSelectSafe = computed(() => {
    const f = typesForSelect.value || [];
    if (!form.value.department_id) return [];
    if (f.length === 0 && Array.isArray(props.types) && props.types.length > 0) return props.types;
    return f;
});

const sizesForSelectSafe = computed(() => {
    const f = sizesForSelect.value || [];
    if (!form.value.department_id) return [];
    if (f.length === 0 && Array.isArray(props.sizes) && props.sizes.length > 0) return props.sizes;
    return f;
});

// keep department in sync when company changes
watch(
    () => form.value.company_id,
    (newVal) => {
        if (!newVal) return;
        // if selected department doesn't belong to new company, clear it
        if (form.value.department_id) {
            const ok = departmentsFlattened.value.find(
                (d) => String(d.id) === String(form.value.department_id) && String(d.company_id) === String(newVal),
            );
            if (!ok) form.value.department_id = '';
        }
        // when company is fixed (not changeable) and no department provided, try to set from page props
    },
);

function submit() {
    // emit selected payload
    emit('selected', {
        company_id: form.value.company_id || null,
        department_id: form.value.department_id || null,
        work_item_type_id: form.value.type_id || null,
        size_id: form.value.size_id || null,
        stage_id: form.value.stage_id || null,
        status_id: form.value.status_id || null,
    });
    emit('close');
}

function close() {
    emit('close');
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
        <div class="w-full max-w-2xl rounded bg-white p-6 shadow-lg">
            <h3 class="mb-4 text-lg font-medium">Work Item 選択</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">会社</label>
                    <select v-model="form.company_id" :disabled="companyDisabled" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                        <option value="">-- グローバル / 未設定 --</option>
                        <option v-for="c in props.companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">部署</label>
                    <select v-model="form.department_id" :disabled="departmentDisabled" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                        <option value="">-- 指定なし --</option>
                        <option v-for="d in departmentsForCompany" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Type</label>
                    <select v-model="form.type_id" :disabled="!form.department_id" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                        <option value="">-- 選択 --</option>
                        <option v-for="t in typesForSelectSafe" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                    </select>
                    <p v-if="!form.department_id" class="mt-1 text-xs text-gray-500">部署を選択すると対応するタイプが表示されます</p>
                </div>
                <div>
                    <label class="block text-sm font-medium">Size</label>
                    <select v-model="form.size_id" :disabled="!form.department_id" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                        <option value="">-- 選択 --</option>
                        <option v-for="s in sizesForSelectSafe" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                    </select>
                    <p v-if="!form.department_id" class="mt-1 text-xs text-gray-500">部署を選択すると対応するサイズが表示されます</p>
                </div>
                <div>
                    <label class="block text-sm font-medium">Stage</label>
                    <select v-model="form.stage_id" :disabled="!form.department_id" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                        <option value="">-- 選択 --</option>
                        <option v-for="st in stagesForSelect" :key="st.id" :value="String(st.id)">{{ st.name }}</option>
                    </select>
                    <p v-if="!form.department_id" class="mt-1 text-xs text-gray-500">部署を選択すると対応するステージが表示されます</p>
                </div>
                <div>
                    <label class="block text-sm font-medium">Status</label>
                    <select v-model="form.status_id" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                        <option value="">-- 選択 --</option>
                        <option v-for="st in props.statuses" :key="st.id" :value="String(st.id)">{{ st.name }}</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex justify-end space-x-2">
                <button @click="close" class="rounded bg-gray-200 px-4 py-2">キャンセル</button>
                <button @click="submit" class="rounded bg-blue-600 px-4 py-2 text-white">選択して挿入</button>
            </div>
        </div>
    </div>
</template>
