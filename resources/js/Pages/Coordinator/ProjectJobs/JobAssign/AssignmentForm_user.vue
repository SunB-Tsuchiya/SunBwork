<template>
    <form @submit.prevent="save">
        <div v-for="(block, idx) in assignments" :key="idx" class="mb-4 rounded border p-4">
            <label class="mb-1 block font-semibold">クライアント</label>
            <div class="w-full">
                <select v-model="block._client_id" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                    <option value="">-- 選択 --</option>
                    <option v-for="c in userClients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>
            </div>

            <label class="mb-1 mt-2 block font-semibold">プロジェクト名</label>
            <div>
                <select v-model="block.project_job_id" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                    <option value="">-- 選択 --</option>
                    <option v-for="p in projectsForBlock(block)" :key="p.id" :value="p.id">{{ p.title || p.name }}</option>
                </select>
            </div>

            <!-- removed duplicate read-only project name display -->

            <label class="mb-1 mt-2 block font-semibold">ジョブ名</label>
            <div>
                <input v-model="block.title_suffix" :disabled="!editMode" type="text" class="w-full rounded border px-3 py-2" />
            </div>

            <label class="mb-1 mt-2 block font-semibold">概要</label>
            <textarea v-model="block.detail" :disabled="!editMode" class="w-full rounded border px-3 py-2" rows="3"></textarea>

            <label class="mb-1 mt-2 block font-semibold">作業詳細</label>
            <!-- Company / Department (always visible) -->
            <!-- Inline 2x2 dropdowns: always visible under 作業詳細. Selecting updates the block immediately. -->
            <!-- Company/Department hidden: not displayed but IDs preserved for DB -->
            <div>
                <input type="hidden" v-model="block.company_id" />
                <input type="hidden" v-model="block.department_id" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">Type</label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ itemName('types', block.work_item_type_id) }}
                    </div>
                    <select
                        v-else
                        v-model="block.work_item_type_id"
                        :disabled="!hasDepartment(block) || !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="t in typesForSelect(block.company_id, block.department_id)" :key="t.id" :value="String(t.id)">
                            {{ t.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Size</label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">{{ itemName('sizes', block.size_id) }}</div>
                    <select
                        v-else
                        v-model="block.size_id"
                        :disabled="!hasDepartment(block) || !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="s in sizesForSelect(block.company_id, block.department_id)" :key="s.id" :value="String(s.id)">
                            {{ s.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Stage</label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ itemName('stages', block.stage_id) }}
                    </div>
                    <select
                        v-else
                        v-model="block.stage_id"
                        :disabled="!hasDepartment(block) || !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="st in stagesForSelect(block.company_id, block.department_id)" :key="st.id" :value="String(st.id)">
                            {{ st.name }}
                        </option>
                    </select>
                </div>
                <div v-if="!props.hideStatus">
                    <label class="block text-sm font-medium">Status</label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ itemName('statuses', block.status_id) }}
                    </div>
                    <select
                        v-else
                        v-model="block.status_id"
                        :disabled="!editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="st in statusesForSelect(block.company_id, block.department_id)" :key="st.id" :value="String(st.id)">
                            {{ st.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Quantity (数量) : four digit selects 0-9 and a unit select (ページ / ファイル) -->
            <label class="mb-1 mt-4 block font-semibold">数量</label>
            <div class="mt-1 flex items-center gap-2">
                <div class="flex gap-1">
                    <select
                        v-for="n in 4"
                        :key="n"
                        :aria-label="`amount-digit-${n}`"
                        v-model="block[`amount_digit_${n - 1}`]"
                        @change="onAmountDigitsChange(idx)"
                        :disabled="!editMode"
                        class="w-14 rounded border px-2 py-1 text-sm"
                    >
                        <option v-for="d in Array.from({ length: 10 }, (_, i) => i)" :key="d" :value="String(d)">{{ d }}</option>
                    </select>
                </div>
                <div>
                    <select v-model="block.amounts_unit" :disabled="!editMode" class="mt-1 w-32 rounded border px-2 py-1 text-sm">
                        <option value="page">ページ</option>
                        <option value="file">ファイル</option>
                    </select>
                </div>
                <div class="ml-2 text-sm text-gray-600">
                    <span v-if="block.amounts !== undefined">
                        {{ block.amounts }}
                        {{ block.amounts_unit === 'page' ? 'ページ' : block.amounts_unit === 'file' ? 'ファイル' : '' }}</span
                    >
                </div>
            </div>

            <!-- restore difficulty / desired dates / estimated / assignee -->
            <label class="mb-1 mt-4 block font-semibold">難易度</label>
            <select v-model="block.difficulty" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                <option value="light">軽い</option>
                <option value="normal">普通</option>
                <option value="heavy">重い</option>
            </select>

            <div
                v-if="block.id && (block.desired_start_date || block.desired_end_date || block.desired_time_hour || block.desired_time_min)"
                class="mt-2 flex gap-4"
            >
                <!-- <div class="flex-1">
                    <label class="mb-1 block font-semibold">割当希望日</label>
                    <div v-if="block.desired_start_date" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ formatStart(block) }}
                    </div>
                </div> -->
                <div class="flex-1">
                    <label class="mb-1 block font-semibold">締め切り</label>
                    <div v-if="block.desired_end_date || block.desired_time_hour" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ formatEnd(block) }}
                    </div>
                </div>
            </div>

            <label class="mb-1 mt-2 block font-semibold">見積時間</label>
            <div class="flex items-center gap-2">
                <div v-if="block.id && block.estimated_hours" class="mt-1 w-40 rounded border bg-gray-50 px-3 py-2 text-sm">
                    {{ formatEstimated(block) }}
                </div>
            </div>

            <label class="mb-1 mt-2 block font-semibold">割当ユーザー</label>
            <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">{{ memberName(block.user_id) }}</div>
            <select v-else v-model="block.user_id" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                <option value="">未指定</option>
                <option v-for="m in props.members || members" :key="m.id" :value="m.id">{{ m.id }}：{{ m.name }}</option>
            </select>
        </div>

        <!-- Inline event date/time editor (placed above the save button) -->
        <div v-if="editMode" class="mb-4 rounded border p-4">
            <label class="block text-sm font-medium text-gray-700">作業日</label>
            <div class="mt-1 flex items-center gap-2">
                <input type="date" v-model="workDate" class="rounded border px-3 py-2" />
            </div>
            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700">時間</label>
                <div class="mt-1 flex items-end gap-4">
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600">開始</label>
                        <div class="flex gap-2">
                            <select v-model="startTimeHour" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <select v-model="startTimeMin" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="m in mins" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600">終了</label>
                        <div class="flex gap-2">
                            <select v-model="endTimeHour" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <select v-model="endTimeMin" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                                <option v-for="m in mins" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-2" v-if="editMode">
            <!-- <button type="submit" class="rounded bg-green-600 px-4 py-2 text-white">保存する</button> -->
            <!-- テストです。 -->
            <button type="submit" @click.prevent="save" :disabled="saving" class="rounded bg-green-600 px-4 py-2 text-white">保存する</button>
        </div>
    </form>
</template>

<script setup>
import { inertiaFetch } from '@/Composables/useInertiaFetch';
import { usePage } from '@inertiajs/vue3';
import { computed, inject, onMounted, ref, watch } from 'vue';

const props = defineProps({
    projectJob: Object,
    members: Array,
    assignments: Array,
    editMode: { type: Boolean, default: true },
    userClients: { type: Array, default: () => [] },
    userProjects: { type: Array, default: () => [] },
    event: { type: Object, default: null },
    hideStatus: { type: Boolean, default: false },
    defaultStatusId: { type: [Number, String], default: null },
});
const page = usePage();
// Try to use injected auth/user from layout (provided via AppLayout.vue)
const injectedAuthUser = inject('authUser', null);
const injectedUser = inject('user', null);

// Helper to get the effective auth user (injected -> page.props.auth.user -> page.props.user)
function effectiveAuthUser() {
    return (
        injectedAuthUser ||
        (page.props && page.props.auth && page.props.auth.user ? page.props.auth.user : null) ||
        (page.props && page.props.user ? page.props.user : null) ||
        null
    );
}
// Debug UI state
const showDebug = ref(false);
const debugStr = computed(() => {
    try {
        return JSON.stringify(
            {
                injectedAuthUser: injectedAuthUser || null,
                injectedUser: injectedUser || null,
                effectiveAuthUser: effectiveAuthUser(),
                pagePropsAuthUser: page.props && page.props.auth ? page.props.auth.user : null,
                pagePropsUser: page.props ? page.props.user : null,
                pagePropsCompany: page.props ? page.props.company : null,
                pagePropsDepartment: page.props ? page.props.department : null,
                types_count: Array.isArray(page.props?.types) ? page.props.types.length : 0,
                sizes_count: Array.isArray(page.props?.sizes) ? page.props.sizes.length : 0,
                stages_count: Array.isArray(page.props?.stages) ? page.props.stages.length : 0,
                statuses_count: Array.isArray(page.props?.statuses) ? page.props.statuses.length : 0,
                types_sample: Array.isArray(page.props?.types) && page.props.types.length ? page.props.types.slice(0, 3) : [],
                sizes_sample: Array.isArray(page.props?.sizes) && page.props.sizes.length ? page.props.sizes.slice(0, 3) : [],
                stages_sample: Array.isArray(page.props?.stages) && page.props.stages.length ? page.props.stages.slice(0, 3) : [],
                statuses_sample: Array.isArray(page.props?.statuses) && page.props.statuses.length ? page.props.statuses.slice(0, 3) : [],
            },
            null,
            2,
        );
    } catch (e) {
        return String(e);
    }
});
// mount hook (no debug logging)
onMounted(() => {});

// Inline event editor state (optional)
import { onMounted as onMountedLocal } from 'vue';
const workDate = ref('');
const startTimeHour = ref('09');
const startTimeMin = ref('00');
const endTimeHour = ref('10');
const endTimeMin = ref('00');

function normalizeToDateTimePartsLocal(dt) {
    if (!dt) return { date: '', time: '' };
    const s = String(dt);
    const m = s.match(/(\d{4}-\d{2}-\d{2})[T ]?(\d{2}:\d{2})/);
    if (m) return { date: m[1], time: m[2] };
    const parts = s.replace('T', ' ').split(' ');
    return { date: parts[0] || '', time: (parts[1] || '').slice(0, 5) };
}

onMountedLocal(() => {
    // prefer explicit event prop if provided, else derive from first assignment
    const ev = props.event || (assignments.value && assignments.value[0] ? assignments.value[0] : null);
    if (ev) {
        const s = normalizeToDateTimePartsLocal(ev.start || ev.desired_start_date || ev.start_time || '');
        const e = normalizeToDateTimePartsLocal(ev.end || ev.desired_end_date || ev.desired_time || '');
        workDate.value = s.date || (assignments.value[0] ? assignments.value[0].desired_start_date || '' : '');
        if (s.time) {
            const [sh, sm] = String(s.time).split(':');
            startTimeHour.value = sh || '09';
            startTimeMin.value = sm || '00';
        } else if (assignments.value[0]) {
            startTimeHour.value = assignments.value[0].start_time_hour || '09';
            startTimeMin.value = assignments.value[0].start_time_min || '00';
        }
        if (e.time) {
            const [eh, em] = String(e.time).split(':');
            endTimeHour.value = eh || startTimeHour.value || '10';
            endTimeMin.value = em || startTimeMin.value || '00';
        } else if (assignments.value[0]) {
            endTimeHour.value = assignments.value[0].desired_time_hour || startTimeHour.value || '10';
            endTimeMin.value = assignments.value[0].desired_time_min || startTimeMin.value || '00';
        }
    }
});

// Watch injected/inferred user and page props for changes
watch(
    () => ({
        injectedAuthUser: injectedAuthUser,
        injectedUser: injectedUser,
        effective: effectiveAuthUser(),
        pageAuthUser: page.props && page.props.auth ? page.props.auth.user : null,
        pageUser: page.props ? page.props.user : null,
    }),
    (val) => {
        // removed verbose prop change logs - keep debug panel for inspection
    },
    { deep: true },
);
const hours = Array.from({ length: 17 }, (_, i) => String(7 + i).padStart(2, '0'));
const mins = ['00', '15', '30', '45'];
const estimatedOptions = Array.from({ length: 32 }, (_, i) => Number(((i + 1) * 0.25).toFixed(2)));

function resolveDifficultyId(val) {
    if (val === undefined || val === null || val === '') return null;
    const num = Number(val);
    if (!Number.isNaN(num) && String(val).trim() !== '') return num;
    const list = window?.page?.props?.difficulties || page.props.difficulties || null;
    if (Array.isArray(list)) {
        const lower = String(val).toLowerCase();
        const found = list.find((d) => {
            try {
                if (!d) return false;
                const parts = [d.name, d.slug, d.key, d.label].filter(Boolean);
                return parts.some((p) => String(p).toLowerCase() === lower);
            } catch (e) {
                return false;
            }
        });
        if (found) return found.id;
    }
    try {
        const map = { light: 'light', normal: 'normal', heavy: 'heavy' };
        const k = String(val).toLowerCase();
        if (map[k]) {
            if (Array.isArray(window?.page?.props?.difficulties)) {
                const f = window.page.props.difficulties.find((d) => String(d.slug || d.key || d.name).toLowerCase() === k);
                if (f) return f.id;
            }
        }
    } catch (e) {}
    // Final fallback: common english keys -> assumed canonical IDs
    try {
        const k2 = String(val).toLowerCase();
        const fallbackIds = { light: 1, normal: 2, heavy: 3 };
        if (Object.prototype.hasOwnProperty.call(fallbackIds, k2)) return fallbackIds[k2];
    } catch (e) {}
    return null;
}

function normalizeAssignment(a) {
    return {
        id: a.id || null,
        project_job: a.project_job || null,
        project_job_id: a.project_job_id || (a.project_job && a.project_job.id) || null,
        _client_id: a._client_id || (a.project_job && (a.project_job.client?.id || a.project_job.client_id)) || '',
        title_suffix: a.title ? a.title.replace(/^.*：/, '').trim() : a.title_suffix || '',
        detail: a.detail || '',
        user_id: a.user_id || (effectiveAuthUser() ? effectiveAuthUser().id : null),
        difficulty: a.difficulty || 'normal',
        difficulty_id: a.difficulty_id ?? null,
        desired_start_date: a.desired_start_date || a.desired_date || '',
        desired_end_date: a.desired_end_date || '',
        desired_time_hour: a.desired_time ? a.desired_time.split(':')[0] || '09' : a.desired_time_hour || '09',
        desired_time_min: a.desired_time ? a.desired_time.split(':')[1] || '00' : a.desired_time_min || '00',
        // start_time is separated from desired_time (start vs end). If incoming data has start_time use it, else fall back to desired_time
        start_time_hour: a.start_time
            ? a.start_time.split(':')[0] || '09'
            : a.start_time_hour || (a.desired_time ? a.desired_time.split(':')[0] : '09'),
        start_time_min: a.start_time
            ? a.start_time.split(':')[1] || '00'
            : a.start_time_min || (a.desired_time ? a.desired_time.split(':')[1] : '00'),
        estimated_hours: a.estimated_hours !== undefined && a.estimated_hours !== null ? a.estimated_hours : '',
        // preserve lookup ids and amounts if provided by incoming data
        work_item_type_id: a.work_item_type_id ?? null,
        size_id: a.size_id ?? null,
        stage_id: a.stage_id ?? null,
        status_id: a.status_id ?? null,
        amounts: a.amounts !== undefined && a.amounts !== null ? a.amounts : a.amounts_unit ? 0 : undefined,
        amounts_unit: a.amounts_unit ?? 'page',
    };
}

const assignments = ref(props.assignments && props.assignments.length ? props.assignments.map(normalizeAssignment) : [normalizeAssignment({})]);

// initialize defaults similar to coordinator form
assignments.value.forEach((a) => {
    if (a.company_id === undefined || a.company_id === null || a.company_id === '') {
        const auth = effectiveAuthUser();
        const defaultCompany = page.props.company ? page.props.company.id : auth && auth.company_id ? auth.company_id : null;
        a.company_id = defaultCompany;
    }
    if (a.department_id === undefined || a.department_id === null || a.department_id === '') {
        const auth = effectiveAuthUser();
        const defaultDepartment = page.props.department ? page.props.department.id : auth && auth.department_id ? auth.department_id : null;
        a.department_id = defaultDepartment;
    }
    if (a.work_item_type_id === undefined) a.work_item_type_id = a.work_item_type_id || null;
    if (a.size_id === undefined) a.size_id = a.size_id || null;
    if (a.stage_id === undefined) a.stage_id = a.stage_id || null;
    if (a.status_id === undefined) a.status_id = a.status_id || null;
    if (a.difficulty === undefined) a.difficulty = a.difficulty || 'normal';
    if (a.difficulty_id === undefined || a.difficulty_id === null) {
        try {
            a.difficulty_id = resolveDifficultyId(a.difficulty);
        } catch (e) {
            a.difficulty_id = null;
        }
    }
    if (a.desired_start_date === undefined) a.desired_start_date = a.desired_start_date || '';
    if (a.desired_end_date === undefined) a.desired_end_date = a.desired_end_date || '';
    if (a.desired_time_hour === undefined) a.desired_time_hour = a.desired_time_hour || '09';
    if (a.desired_time_min === undefined) a.desired_time_min = a.desired_time_min || '00';
    if (a.start_time_hour === undefined) a.start_time_hour = a.start_time_hour || a.desired_time_hour || '09';
    if (a.start_time_min === undefined) a.start_time_min = a.start_time_min || a.desired_time_min || '00';
    if (a.estimated_hours === undefined) a.estimated_hours = a.estimated_hours || '';
    if (a.amount_digit_0 === undefined) a.amount_digit_0 = a.amounts ? String(Math.floor(a.amounts / 1000) % 10) : '0';
    if (a.amount_digit_1 === undefined) a.amount_digit_1 = a.amounts ? String(Math.floor(a.amounts / 100) % 10) : '0';
    if (a.amount_digit_2 === undefined) a.amount_digit_2 = a.amounts ? String(Math.floor(a.amounts / 10) % 10) : '0';
    if (a.amount_digit_3 === undefined) a.amount_digit_3 = a.amounts ? String(a.amounts % 10) : '0';
    if (a.amounts === undefined) a.amounts = a.amounts || 0;
    if (a.amounts_unit === undefined) a.amounts_unit = a.amounts_unit || 'page';
});

// Ensure non-superadmin users always have company/department populated from controller or auth user
try {
    const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
    if (role !== 'superadmin') {
        const auth2 = effectiveAuthUser();
        const forcedCompany = page.props.company ? page.props.company.id : auth2 && auth2.company_id ? auth2.company_id : null;
        const forcedDepartment = page.props.department ? page.props.department.id : auth2 && auth2.department_id ? auth2.department_id : null;
        assignments.value.forEach((a) => {
            if (!a.company_id && forcedCompany) a.company_id = forcedCompany;
            if (!a.department_id && forcedDepartment) a.department_id = forcedDepartment;
        });
    }
} catch (e) {}

function departmentsFlattened() {
    const out = [];
    (page.props.companies || []).forEach((c) => {
        const deps = c && c.departments ? c.departments : [];
        Array.from(deps || []).forEach((d) => {
            const id = d && (d.id ?? d['id']) ? (d.id ?? d['id']) : null;
            const name = d && (d.name ?? d['name']) ? (d.name ?? d['name']) : null;
            out.push({ id: id, name: name, company_id: c && (c.id ?? c['id']) ? (c.id ?? c['id']) : null });
        });
    });
    return out;
}

function departmentsForCompany(companyId) {
    if (!companyId) return departmentsFlattened();
    return departmentsFlattened().filter((d) => String(d.company_id) === String(companyId));
}

function companyName(companyId) {
    if (!companyId) return 'グローバル/未設定';
    const found = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    return found ? found.name : String(companyId);
}

// Helper that returns company object (or null) for a given id
function companyById(companyId) {
    if (!companyId) return null;
    const found = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    return found || null;
}

// Robust helper to return a company name from an id. Tries multiple sources:
// 1) page.props.companies list
// 2) page.props.company single object
// 3) fallback to companyName()
function companyNameFromId(companyId) {
    if (!companyId) return companyName(companyId);
    // try list
    const fromList = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    if (fromList && fromList.name) return fromList.name;
    // try single prop
    if (page.props && page.props.company && String(page.props.company.id) === String(companyId) && page.props.company.name) {
        return page.props.company.name;
    }
    // fallback
    return companyName(companyId);
}

function departmentName(departmentId) {
    if (!departmentId) return '指定なし';
    const all = departmentsFlattened();
    const found = all.find((d) => String(d.id) === String(departmentId));
    return found ? found.name : String(departmentId);
}

// Helper that returns department object (or null) for a given id
function departmentById(departmentId) {
    if (!departmentId) return null;
    const all = departmentsFlattened();
    const found = all.find((d) => String(d.id) === String(departmentId));
    return found || null;
}

// Robust helper to return a department name from an id. Tries:
// 1) departmentsFlattened()
// 2) page.props.department single object
// 3) fallback to departmentName()
function departmentNameFromId(departmentId) {
    if (!departmentId) return departmentName(departmentId);
    const fromList = departmentsFlattened().find((d) => String(d.id) === String(departmentId));
    if (fromList && fromList.name) return fromList.name;
    if (page.props && page.props.department && String(page.props.department.id) === String(departmentId) && page.props.department.name) {
        return page.props.department.name;
    }
    return departmentName(departmentId);
}

function itemName(kind, id) {
    if (!id) return '';
    const list = { types: page.props.types, sizes: page.props.sizes, statuses: page.props.statuses, stages: page.props.stages }[kind];
    if (!Array.isArray(list)) return String(id);
    const found = list.find((x) => String(x.id) === String(id));
    return found ? found.name : String(id);
}

function memberName(userId) {
    if (!userId) return '未指定';
    if (typeof userId === 'string' && isNaN(Number(userId))) return userId;
    const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
    if (m) return m.name || m.full_name || String(m.id);
    const pageUsers = page.props.users || page.props.members || [];
    const found = (Array.isArray(pageUsers) ? pageUsers : []).find((u) => String(u.id) === String(userId));
    if (found) return found.name || found.full_name || String(found.id);
    try {
        if (userId && typeof userId === 'object') return userId.name || userId.full_name || String(userId.id || '');
    } catch (e) {}
    return String(userId);
}

function typesForSelect(companyId, departmentId) {
    const list = page.props.types || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((t) => {
        // allow global entries (no company_id/department_id) or matching entries
        const tComp = t.company_id === undefined || t.company_id === null ? '' : String(t.company_id);
        const tDept = t.department_id === undefined || t.department_id === null ? '' : String(t.department_id);
        const compMatch = !t.company_id || tComp === sComp || sComp === '';
        const deptMatch = !t.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

function sizesForSelect(companyId, departmentId) {
    const list = page.props.sizes || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((s) => {
        const tComp = s.company_id === undefined || s.company_id === null ? '' : String(s.company_id);
        const tDept = s.department_id === undefined || s.department_id === null ? '' : String(s.department_id);
        const compMatch = !s.company_id || tComp === sComp || sComp === '';
        const deptMatch = !s.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

function stagesForSelect(companyId, departmentId) {
    const list = page.props.stages || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((st) => {
        const tComp = st.company_id === undefined || st.company_id === null ? '' : String(st.company_id);
        const tDept = st.department_id === undefined || st.department_id === null ? '' : String(st.department_id);
        const compMatch = !st.company_id || tComp === sComp || sComp === '';
        const deptMatch = !st.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

function statusesForSelect(companyId, departmentId) {
    const list = page.props.statuses || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((st) => {
        const tComp = st.company_id === undefined || st.company_id === null ? '' : String(st.company_id);
        const tDept = st.department_id === undefined || st.department_id === null ? '' : String(st.department_id);
        const compMatch = !st.company_id || tComp === sComp || sComp === '';
        const deptMatch = !st.department_id || tDept === sDept || sDept === '';
        return compMatch && deptMatch;
    });
}

// Watch assignments array (no debug logging)
watch(assignments, () => {}, { deep: true });

function hasDepartment(block) {
    return block && block.department_id !== undefined && block.department_id !== null && String(block.department_id) !== '';
}

function companyDisabled() {
    // Mirror coordinator behavior: only superadmin can change company in some contexts
    try {
        const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
        return role !== 'superadmin';
    } catch (e) {
        return false;
    }
}

function departmentDisabled() {
    // Mirror coordinator behavior: restrict department editing for certain roles (leader/coordinator)
    try {
        const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
        // Non-superadmin users belong to a single company/department and should not be able to change it here
        return role !== 'superadmin';
    } catch (e) {
        return false;
    }
}

function onCompanyChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    b.department_id = '';
    b.work_item_type_id = null;
    b.size_id = null;
    b.stage_id = null;
    onInlineSelectionChange(idx);
}

function onDepartmentChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    b.work_item_type_id = null;
    b.size_id = null;
    b.stage_id = null;
    onInlineSelectionChange(idx);
}

function onInlineSelectionChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    b.type_label = b.work_item_type_id ? itemName('types', b.work_item_type_id) : null;
    b.size_label = b.size_id ? itemName('sizes', b.size_id) : null;
    b.stage_label = b.stage_id ? itemName('stages', b.stage_id) : null;
    b.status_label = b.status_id ? itemName('statuses', b.status_id) : null;
}

function onAmountDigitsChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    const d0 = Number(b.amount_digit_0 || '0');
    const d1 = Number(b.amount_digit_1 || '0');
    const d2 = Number(b.amount_digit_2 || '0');
    const d3 = Number(b.amount_digit_3 || '0');
    const value = d0 * 1000 + d1 * 100 + d2 * 10 + d3;
    b.amounts = value;
}

function projectsForBlock(block) {
    if (!block || !block._client_id) return props.userProjects || [];
    return (props.userProjects || []).filter((p) => String(p.client_id) === String(block._client_id));
}

function projectName(block) {
    try {
        if (block && block.project_job && (block.project_job.title || block.project_job.name))
            return block.project_job.title || block.project_job.name;
        if (block && block.project_job_id) {
            const found = (props.userProjects || []).find((p) => String(p.id) === String(block.project_job_id));
            if (found) return found.title || found.name;
        }
    } catch (e) {}
    return '-';
}

function addBlock() {
    assignments.value.push({
        project_job: props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title } : null,
        project_job_id: props.projectJob ? props.projectJob.id : null,
        _client_id: props.projectJob && props.projectJob.client ? props.projectJob.client.id : '',
        title_suffix: '',
        detail: '',
        user_id: effectiveAuthUser() ? effectiveAuthUser().id : null,
        company_id: page.props.company
            ? page.props.company.id
            : effectiveAuthUser() && effectiveAuthUser().company_id
              ? effectiveAuthUser().company_id
              : null,
        department_id: page.props.department
            ? page.props.department.id
            : effectiveAuthUser() && effectiveAuthUser().department_id
              ? effectiveAuthUser().department_id
              : null,
        difficulty: 'normal',
        desired_start_date: '',
        desired_end_date: '',
        desired_time_hour: '09',
        desired_time_min: '00',
        estimated_hours: '',
    });
}

function todayDateStr() {
    const d = new Date();
    return d.toISOString().split('T')[0];
}

function minEndDate(idx) {
    const a = assignments.value[idx];
    return a.desired_start_date || todayDateStr();
}

function availableHours(idx) {
    const a = assignments.value[idx];
    if (!a.desired_end_date) return hours;
    const today = todayDateStr();
    if (a.desired_end_date !== today) return hours;
    const now = new Date();
    const currentHour = String(now.getHours()).padStart(2, '0');
    return hours.filter((h) => h >= currentHour);
}

function availableMins(idx, hour) {
    const a = assignments.value[idx];
    if (!a.desired_end_date) return mins;
    const today = todayDateStr();
    if (a.desired_end_date !== today) return mins;
    const now = new Date();
    const currentHour = String(now.getHours()).padStart(2, '0');
    if (hour > currentHour) return mins;
    const curMin = now.getMinutes();
    const nextQuarter = Math.ceil(curMin / 15) * 15;
    return mins.filter((m) => Number(m) >= nextQuarter);
}

function onEndDateChange(idx) {
    const a = assignments.value[idx];
    if (a.desired_start_date && a.desired_end_date && a.desired_end_date < a.desired_start_date) {
        a.desired_end_date = a.desired_start_date;
    }
}

function onHourChange(idx) {
    const a = assignments.value[idx];
    const avail = availableMins(idx, a.desired_time_hour);
    if (!avail.includes(a.desired_time_min)) {
        a.desired_time_min = avail.length ? avail[0] : '00';
    }
}

function formatStart(block) {
    if (!block) return '';
    const date = block.desired_start_date || '';
    return date ? String(date) : '';
}

function formatEnd(block) {
    if (!block) return '';
    const date = block.desired_end_date || '';
    const hh = block.desired_time_hour;
    const mm = block.desired_time_min || '00';
    if (date && hh) return `${date} ${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
    if (date) return date;
    if (hh) return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
    return '';
}

function formatEstimated(block) {
    if (!block || block.estimated_hours === undefined || block.estimated_hours === null || block.estimated_hours === '') return '';
    return String(block.estimated_hours).replace('.0', '') + 'h';
}
const saving = ref(false);

async function save() {
    saving.value = true;

    // If inline event editor present, copy values into first assignment so backend can sync event
    try {
        if (assignments.value && assignments.value.length) {
            const a0 = assignments.value[0];
            if (workDate.value) a0.desired_start_date = workDate.value;
            // endDate removed; do not override desired_end_date from inline editor
            if (startTimeHour.value) {
                a0.start_time_hour = startTimeHour.value;
                a0.start_time_min = startTimeMin.value || '00';
                a0.start_time = String(a0.start_time_hour).padStart(2, '0') + ':' + String(a0.start_time_min).padStart(2, '0');
            }
            if (endTimeHour.value) {
                a0.desired_time_hour = endTimeHour.value;
                a0.desired_time_min = endTimeMin.value || '00';
                a0.desired_time = String(a0.desired_time_hour).padStart(2, '0') + ':' + String(a0.desired_time_min).padStart(2, '0');
            }
        }
    } catch (e) {}

    function assembleTitle(a) {
        if (a.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
        const maybe = a.project_job && (a.project_job.title || a.project_job.name) ? a.project_job.title || a.project_job.name : null;
        if (!maybe) return '';
        if (maybe.includes('：')) return maybe.replace(/^.*：/, '').trim();
        return String(maybe).trim();
    }

    const payload = {
        assignments: assignments.value.map((a) => ({
            id: a.id || null,
            title: assembleTitle(a),
            detail: a.detail || '',
            user_id: a.user_id || (effectiveAuthUser() ? effectiveAuthUser().id : null),
            sender_id: null,
            project_job_id: a.project_job_id || null,
            company_id: a.company_id || null,
            department_id: a.department_id || null,
            difficulty_id:
                a.difficulty_id ??
                resolveDifficultyId(a.difficulty) ??
                (window?.page?.props?.difficulties
                    ? (window.page.props.difficulties.find((d) => d.name === a.difficulty || d.slug === a.difficulty)?.id ?? null)
                    : null),
            desired_start_date: a.desired_start_date || null,
            desired_end_date: a.desired_end_date || null,
            // Always send time fields if available so server can create Event on new assignments
            start_time: a.start_time_hour
                ? String(a.start_time_hour).padStart(2, '0') + ':' + String(a.start_time_min || '00').padStart(2, '0')
                : (a.start_time ?? null),
            desired_time: a.desired_time_hour
                ? String(a.desired_time_hour).padStart(2, '0') + ':' + String(a.desired_time_min || '00').padStart(2, '0')
                : (a.desired_time ?? null),
            estimated_hours: a.estimated_hours || null,
            work_item_type_id: a.work_item_type_id || null,
            size_id: a.size_id || null,
            stage_id: a.stage_id || null,
            status_id: props.hideStatus
                ? props.defaultStatusId !== null && props.defaultStatusId !== undefined
                    ? Number(props.defaultStatusId)
                    : 2
                : (a.status_id ?? null),
            amounts: typeof a.amounts === 'number' ? a.amounts : Number(a.amounts) || 0,
            amounts_unit: a.amounts_unit || 'page',
        })),
    };

    try {
        const auth = effectiveAuthUser();
        const allForAuth = payload.assignments.every((x) => String(x.user_id) === String(auth ? auth.id : null));
        const computedProjectJobId =
            props.projectJob && props.projectJob.id ? props.projectJob.id : payload.assignments[0] ? payload.assignments[0].project_job_id : null;
        // If editing existing assignment(s) (have id), call update endpoint instead of store
        const firstAssignmentHasId = payload.assignments[0] && payload.assignments[0].id;
        if (firstAssignmentHasId) {
            // Send update. Use user-specific update when the assignment belongs to the current user,
            // otherwise use coordinator update route (coordinator can update any assignment).
            const assignmentId = payload.assignments[0].id;
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const xsrf = match ? decodeURIComponent(match[1]) : null;
            if (allForAuth) {
                const url = route('project_jobs.assignments.update_user', { projectJob: computedProjectJobId, assignment: assignmentId });
                const rel =
                    typeof window !== 'undefined' && url && url.indexOf(window.location.origin) === 0 ? url.replace(window.location.origin, '') : url;
                try {
                    const res = await inertiaFetch(rel, {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Inertia': 'true',
                        },
                        body: JSON.stringify(payload.assignments[0]),
                    });
                    if (!res || res.navigated) return;
                    if (res.ok || res.redirected) {
                        location.reload();
                        return;
                    }
                    const txt = await res.text().catch(() => '');
                    console.error('[AssignmentForm_user] update PATCH failed:', res.status, txt);
                    alert('保存に失敗しました（' + res.status + '）');
                } catch (err) {
                    console.error('[AssignmentForm_user] update PATCH error:', err);
                    alert('保存に失敗しました（ネットワークエラー）');
                } finally {
                    saving.value = false;
                }
                return;
            } else {
                // Coordinator update (PUT)
                const url = route('coordinator.project_jobs.assignments.update', { projectJob: computedProjectJobId, assignment: assignmentId });
                const rel =
                    typeof window !== 'undefined' && url && url.indexOf(window.location.origin) === 0 ? url.replace(window.location.origin, '') : url;
                try {
                    const res = await inertiaFetch(rel, {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Inertia': 'true',
                        },
                        body: JSON.stringify(payload.assignments[0]),
                    });
                    if (!res || res.navigated) return;
                    if (res.ok || res.redirected) {
                        location.reload();
                        return;
                    }
                    const txt = await res.text().catch(() => '');
                    console.error('[AssignmentForm_user] coordinator update PUT failed:', res.status, txt);
                    alert('保存に失敗しました（' + res.status + '）');
                } catch (err) {
                    console.error('[AssignmentForm_user] coordinator update PUT error:', err);
                    alert('保存に失敗しました（ネットワークエラー）');
                } finally {
                    saving.value = false;
                }
                return;
            }
        }

        if (allForAuth) {
            if (!computedProjectJobId) {
                console.error('[AssignmentForm_user] missing projectJob id when attempting user-store', {
                    computedProjectJobId,
                    sampleAssignment: payload.assignments[0],
                });
                try {
                    alert('プロジェクトが選択されていません。プロジェクトを選択してください。');
                } catch (e) {}
                return;
            }
            const url = route('project_jobs.assignments.store_user', { projectJob: computedProjectJobId });
            const rel =
                typeof window !== 'undefined' && url && url.indexOf(window.location.origin) === 0 ? url.replace(window.location.origin, '') : url;

            // CSRF token
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const xsrf = match ? decodeURIComponent(match[1]) : null;

            try {
                const res = await inertiaFetch(rel, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Inertia': 'true',
                    },
                    body: JSON.stringify(payload),
                });

                // ヘルパがナビゲートした場合は戻る（さらに処理不要）
                if (!res || res.navigated) return;

                if (res.ok) {
                    location.reload();
                    return;
                }

                const txt = await res.text().catch(() => '');
                console.error('[AssignmentForm_user] fetch POST failed:', res.status, txt);
                alert('保存に失敗しました（' + res.status + '）');
            } catch (err) {
                console.error('[AssignmentForm_user] fetch POST error:', err);
                alert('保存に失敗しました（ネットワークエラー）');
                return;
            } finally {
                saving.value = false;
            }
        }
    } catch (e) {
        console.warn('[AssignmentForm_user] allForAuth check failed, falling back to coordinator route', e);
    }

    // Coordinator route fallback (same fetch pattern)
    const coordinatorProjectJobId =
        props.projectJob && props.projectJob.id ? props.projectJob.id : payload.assignments[0] ? payload.assignments[0].project_job_id : null;
    if (!coordinatorProjectJobId) {
        console.error('[AssignmentForm_user] missing projectJob id for coordinator-store', { sampleAssignment: payload.assignments[0] });
        try {
            alert('プロジェクトが選択されていません。プロジェクトを選択してください。');
        } catch (e) {}
        saving.value = false;
        return;
    }

    try {
        const url2 = route('coordinator.project_jobs.assignments.store', { projectJob: coordinatorProjectJobId });
        const rel2 =
            typeof window !== 'undefined' && url2 && url2.indexOf(window.location.origin) === 0 ? url2.replace(window.location.origin, '') : url2;
        const token2 = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const match2 = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const xsrf2 = match2 ? decodeURIComponent(match2[1]) : null;
        const res2 = await inertiaFetch(rel2, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token2,
                ...(xsrf2 ? { 'X-XSRF-TOKEN': xsrf2 } : {}),
                'X-Requested-With': 'XMLHttpRequest',
                'X-Inertia': 'true',
            },
            body: JSON.stringify(payload),
        });

        if (!res2 || res2.navigated) return;

        if (res2.redirected && res2.url) {
            window.location.href = res2.url;
            return;
        }
        if (res2.ok) {
            location.reload();
            return;
        }
        if (res2.status === 409) {
            const inertiaLocation = res2.headers.get('x-inertia-location') || res2.headers.get('X-Inertia-Location') || null;
            const locationHeader = res2.headers.get('location') || null;
            const dest = inertiaLocation || locationHeader;
            if (dest) {
                if (/^https?:\/\//i.test(dest)) {
                    window.location.href = dest;
                } else {
                    window.location.href = window.location.origin + dest;
                }
                return;
            }
            window.location.reload();
            return;
        }
        if (res2.status >= 300 && res2.status < 400) {
            const locationHeader = res2.headers.get('location') || null;
            if (locationHeader) {
                if (/^https?:\/\//i.test(locationHeader)) {
                    window.location.href = locationHeader;
                } else {
                    window.location.href = window.location.origin + locationHeader;
                }
                return;
            }
        }
        const txt2 = await res2.text().catch(() => '');
        console.error('[AssignmentForm_user] coordinator fetch POST failed:', res2.status, txt2);
        alert('保存に失敗しました（' + res2.status + '）');
    } catch (err2) {
        console.error('[AssignmentForm_user] coordinator fetch error:', err2);
        alert('保存に失敗しました（ネットワークエラー）');
    } finally {
        saving.value = false;
    }
}
</script>
