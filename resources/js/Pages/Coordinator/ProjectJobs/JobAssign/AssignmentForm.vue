<template>
    <form @submit.prevent="save">
        <div v-for="(block, idx) in assignments" :key="idx" class="mb-4 rounded border p-4">
            <!-- show assigned user's company / department (read-only) above title -->
            <label class="mb-1 block font-semibold">クライアント</label>
            <div class="w-full rounded border bg-gray-50 px-3 py-2">
                {{ clientName(block) }}
            </div>

            <!-- プロジェクト名を上に表示 -->
            <label class="mb-1 block font-semibold">プロジェクト名</label>
            <div class="w-full rounded border bg-gray-50 px-3 py-2">
                {{ projectName(block) }}
            </div>

            <label class="mb-1 block font-semibold">ジョブ名</label>
            <div>
                <input v-model="block.title_suffix" :disabled="!editMode" type="text" class="w-full rounded border px-3 py-2" />
            </div>
            <!-- selection_label is legacy; we prefer showing individual fields in the form -->
            <!-- (kept for backward compat but hidden when we render individual fields) -->

            <label class="mb-1 mt-2 block font-semibold">概要</label>
            <textarea v-model="block.detail" :disabled="!editMode" class="w-full rounded border px-3 py-2" rows="3"></textarea>

            <label class="mb-1 mt-2 block font-semibold">作業詳細</label>
            <!-- Company/Department are not shown in this form, but keep hidden inputs so values are submitted -->
            <div>
                <input type="hidden" v-model="block.company_id" />
                <input type="hidden" v-model="block.department_id" />
            </div>

            <!-- Inline 2x2 dropdowns: always visible under 作業詳細. Selecting updates the block immediately. -->
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
                <div>
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
                        <option v-for="st in page.props.statuses" :key="st.id" :value="String(st.id)">{{ st.name }}</option>
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

            <label class="mb-1 mt-4 block font-semibold">難易度</label>
            <select v-model="block.difficulty" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                <option value="light">軽い</option>
                <option value="normal">普通</option>
                <option value="heavy">重い</option>
            </select>

            <div class="mt-2">
                <label class="mb-1 block font-semibold">割当希望日</label>
                <input v-model="block.desired_start_date" :disabled="!editMode" type="date" class="w-full rounded border px-3 py-2" />

                <div class="mt-2">
                    <label class="mb-1 block font-semibold">終了希望日, 希望時間</label>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="block.desired_end_date"
                            :min="minEndDate(idx)"
                            type="date"
                            class="rounded border px-3 py-2"
                            @change="onEndDateChange(idx)"
                            :disabled="!editMode"
                        />

                        <select
                            v-model="block.desired_time_hour"
                            :disabled="!editMode"
                            class="w-20 rounded border px-3 py-2"
                            @change="onHourChange(idx)"
                        >
                            <option v-for="h in availableHours(idx)" :key="h" :value="h">{{ h }}</option>
                        </select>
                        <select v-model="block.desired_time_min" :disabled="!editMode" class="w-20 rounded border px-3 py-2">
                            <option v-for="m in availableMins(idx, block.desired_time_hour)" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <label class="mb-1 mt-2 block font-semibold">見積時間</label>
            <div class="flex items-center gap-2">
                <select v-model="block.estimated_hours" :disabled="!editMode" class="w-40 rounded border px-3 py-2">
                    <option value="">未指定</option>
                    <option v-for="opt in estimatedOptions" :key="opt" :value="opt">{{ String(opt).replace('.0', '') }}h</option>
                </select>
                <span class="text-sm text-gray-500">(0.25刻み、例: 1.5 = 1時間30分)</span>
            </div>

            <label class="mb-1 mt-2 block font-semibold">割当ユーザー</label>
            <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">{{ memberName(block.user_id) }}</div>
            <select v-else v-model="block.user_id" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                <option value="">未指定</option>
                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.id }}：{{ m.name }}</option>
            </select>

            <div class="mt-2 text-right">
                <template v-if="block.linked_assignment_id">
                    <a
                        :href="
                            route('coordinator.project_jobs.assignments.show', {
                                projectJob: block.project_job && block.project_job.id ? block.project_job.id : projectJob ? projectJob.id : '',
                                assignment: block.linked_assignment_id,
                            })
                        "
                        class="ml-3 text-sm text-blue-600"
                        >割当を見る (#{{ block.linked_assignment_id }})</a
                    >
                </template>
            </div>
        </div>

        <div class="flex gap-2" v-if="editMode">
            <button type="button" class="rounded bg-blue-600 px-4 py-2 text-white" @click="addBlock">ジョブブロックを追加</button>
            <button type="submit" class="rounded bg-green-600 px-4 py-2 text-white">保存する</button>
            <Link
                :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob ? projectJob.id : '' })"
                class="rounded bg-gray-200 px-4 py-2"
                >戻る</Link
            >
        </div>
    </form>

    <SelectionModal
        v-if="showSelector"
        :show="showSelector"
        :companies="$page.props.companies || []"
        :types="$page.props.types || []"
        :sizes="$page.props.sizes || []"
        :stages="$page.props.stages || []"
        :statuses="$page.props.statuses || []"
        :user-role="$page.props.auth.user.user_role || null"
        :current-company-id="$page.props.company ? $page.props.company.id : $page.props.auth.user.company_id || ''"
        :current-department-id="$page.props.department ? $page.props.department.id : $page.props.auth.user.department_id || ''"
        @close="showSelector = false"
        @selected="onSelected"
    />

    <!-- Toast container -->
    <div class="fixed bottom-4 right-4 z-50 space-y-2">
        <div v-for="t in toasts" :key="t.id" :class="toastClass(t.type)" class="max-w-sm">
            <div class="flex items-center justify-between">
                <div>{{ t.message }}</div>
                <button @click="dismissToast(t.id)" class="ml-3 text-xs text-white">✕</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import SelectionModal from '@/Components/SelectionModal.vue';
import { inertiaFetch } from '@/Composables/useInertiaFetch';
import useToasts from '@/Composables/useToasts';
import { Link, usePage } from '@inertiajs/vue3';
import { inject, onMounted, ref } from 'vue';

const props = defineProps({
    projectJob: Object,
    members: Array,
    assignments: Array,
    editMode: { type: Boolean, default: false },
    defaultUserId: { type: [Number, String], default: null },
});
const page = usePage();

// injected values may be provided by AppLayout (same pattern as AssignmentForm_user.vue)
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

const hours = Array.from({ length: 17 }, (_, i) => String(6 + i).padStart(2, '0'));
const mins = ['00', '15', '30', '45'];
const estimatedOptions = Array.from({ length: 32 }, (_, i) => Number(((i + 1) * 0.25).toFixed(2)));

function makeLabel(kind, id) {
    if (!id) return null;
    const list = { types: page.props.types, sizes: page.props.sizes, statuses: page.props.statuses, stages: page.props.stages }[kind];
    if (!Array.isArray(list)) return null;
    const found = list.find((x) => String(x.id) === String(id));
    return found ? `${kind.replace(/s$/, '')}: ${found.name}` : null;
}

function normalizeAssignment(a) {
    return {
        id: a.id || null,
        title_prefix: `${props.projectJob?.title || ''}：`,
        title_suffix: (() => {
            const raw = a.title || '';
            if (!raw) return '';
            const pj = props.projectJob?.title || '';
            // common formats to strip:
            // 1) Leading Japanese quote + project + full-width colon: 「Project：Job
            // 2) Project + full-width colon: Project：Job
            // 3) Project + ascii colon: Project:Job
            // 4) If none match, but raw contains a colon, assume prefix up to the last colon
            // Trim surrounding whitespace and quotes after stripping
            const candidates = [];
            if (pj) {
                candidates.push(`「${pj}：`);
                candidates.push(`${pj}：`);
                candidates.push(`${pj}:`);
            }
            // Try exact prefix matches first
            for (const pref of candidates) {
                if (raw.startsWith(pref)) return raw.slice(pref.length).trim();
            }
            // If raw contains a full-width colon or ascii colon, strip leading prefix up to the first colon
            if (raw.includes('：'))
                return raw
                    .replace(/^.*？：/, '')
                    .replace(/^.*：/, '')
                    .trim();
            if (raw.includes(':')) return raw.replace(/^.*?:/, '').trim();
            // fallback: return raw as-is
            return raw;
        })(),
        detail: a.detail || '',
        difficulty: a.difficulty || 'normal',
        difficulty_id: a.difficulty_id ?? null,
        desired_start_date: a.desired_start_date || a.desired_date || '',
        desired_end_date: a.desired_end_date || '',
        desired_time_hour: a.desired_time ? a.desired_time.split(':')[0] || '09' : a.desired_time_hour || '09',
        desired_time_min: a.desired_time ? a.desired_time.split(':')[1] || '00' : a.desired_time_min || '00',
        estimated_hours: a.estimated_hours !== undefined && a.estimated_hours !== null ? a.estimated_hours : '',
        user_id: a.user_id || (a.user ? a.user.id : '') || '',
        work_item_type_id: a.work_item_type_id || null,
        size_id: a.size_id || null,
        stage_id: a.stage_id || null,
        status_id: a.status_id || null,
        company_id: a.company_id || null,
        department_id: a.department_id || null,
        type_label: a.type_label || makeLabel('types', a.work_item_type_id),
        size_label: a.size_label || makeLabel('sizes', a.size_id),
        stage_label: a.stage_label || makeLabel('stages', a.stage_id),
        status_label: a.status_label || makeLabel('statuses', a.status_id),
        // preserve amounts so later initialization can split digits for the 4-select UI
        amounts: a.amounts !== undefined && a.amounts !== null ? a.amounts : a.amounts || 0,
        amounts_unit: a.amounts_unit || 'page',
        project_job:
            a.project_job ||
            (props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title, client: props.projectJob.client || null } : null),
    };
}

const assignments = ref(props.assignments && props.assignments.length ? props.assignments.map(normalizeAssignment) : [normalizeAssignment({})]);
assignments.value.forEach((a) => {
    if (a.saving === undefined) a.saving = false;
    if (a.linked_assignment_id === undefined) a.linked_assignment_id = null;
    if (a.title_prefix === undefined) a.title_prefix = `「${props.projectJob?.title || ''}：`;
    if (a.title_suffix === undefined) a.title_suffix = '';
    if (a.showInlineSelector === undefined) a.showInlineSelector = false;
    const authForDefaults = effectiveAuthUser();
    const defaultCompany =
        authForDefaults && authForDefaults.company_id ? authForDefaults.company_id : page.props.company ? page.props.company.id : null;
    const defaultDepartment =
        authForDefaults && authForDefaults.department_id ? authForDefaults.department_id : page.props.department ? page.props.department.id : null;
    if (a.company_id === undefined || a.company_id === null || a.company_id === '') a.company_id = defaultCompany;
    if (a.department_id === undefined || a.department_id === null || a.department_id === '') a.department_id = defaultDepartment;
    if (a.work_item_type_id === undefined) a.work_item_type_id = a.work_item_type_id || null;
    if (a.size_id === undefined) a.size_id = a.size_id || null;
    if (a.stage_id === undefined) a.stage_id = a.stage_id || null;
    if (a.status_id === undefined) a.status_id = a.status_id || null;
    // ensure difficulty_id is populated when only difficulty name/string is present
    if ((a.difficulty_id === undefined || a.difficulty_id === null) && a.difficulty) {
        try {
            const resolved = resolveDifficultyId(a.difficulty);
            if (resolved !== null) a.difficulty_id = resolved;
        } catch (e) {}
    }
    // ensure amounts is numeric
    const amt = a.amounts !== undefined && a.amounts !== null && a.amounts !== '' ? Number(a.amounts) : null;
    if (amt !== null && !Number.isNaN(amt)) {
        const abs = Math.max(0, Math.floor(Math.abs(amt)) % 10000); // 0-9999
        const d0 = Math.floor(abs / 1000) % 10;
        const d1 = Math.floor(abs / 100) % 10;
        const d2 = Math.floor(abs / 10) % 10;
        const d3 = Math.floor(abs % 10);
        if (a.amount_digit_0 === undefined) a.amount_digit_0 = String(d0);
        if (a.amount_digit_1 === undefined) a.amount_digit_1 = String(d1);
        if (a.amount_digit_2 === undefined) a.amount_digit_2 = String(d2);
        if (a.amount_digit_3 === undefined) a.amount_digit_3 = String(d3);
    } else {
        if (a.amount_digit_0 === undefined) a.amount_digit_0 = '0';
        if (a.amount_digit_1 === undefined) a.amount_digit_1 = '0';
        if (a.amount_digit_2 === undefined) a.amount_digit_2 = '0';
        if (a.amount_digit_3 === undefined) a.amount_digit_3 = '0';
    }
    if (a.amounts === undefined) a.amounts = a.amounts || 0;
    if (a.amounts_unit === undefined) a.amounts_unit = a.amounts_unit || 'page';
});

// Ensure project_job.title/name is populated for display
assignments.value.forEach((a) => {
    try {
        if (a.project_job) {
            if (!a.project_job.title && !a.project_job.name) {
                // try props.projectJob
                if (props.projectJob && (props.projectJob.title || props.projectJob.name)) {
                    a.project_job.title = props.projectJob.title || props.projectJob.name || '';
                } else if (Array.isArray(page.props.userProjects)) {
                    const pid = a.project_job.id || a.project_job.project_job_id || null;
                    if (pid) {
                        const found = page.props.userProjects.find((p) => String(p.id) === String(pid));
                        if (found) a.project_job.title = found.title || found.name || found.project_name || '';
                    }
                }
            }
        }
    } catch (e) {}
});

// If we're in read-only (show) mode, convert id fields into their display names
// so the form shows the same values that the edit UI uses (names rather than numeric ids).
if (!props.editMode) {
    const memberName = (userId) => {
        if (!userId) return '';
        const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
        if (m && (m.name || m.full_name)) return m.name || m.full_name;
        if (typeof userId === 'object' && userId !== null) return userId.name || userId.id || '';
        return String(userId || '');
    };

    assignments.value = assignments.value.map((b) => {
        const copy = { ...b };
        try {
            // replace id fields with human-readable names
            copy.company_id = companyName(b.company_id) || copy.company_id;
            copy.department_id = departmentName(b.department_id) || copy.department_id;
            copy.work_item_type_id = itemName('types', b.work_item_type_id) || copy.work_item_type_id;
            copy.size_id = itemName('sizes', b.size_id) || copy.size_id;
            copy.stage_id = itemName('stages', b.stage_id) || copy.stage_id;
            copy.status_id = itemName('statuses', b.status_id) || copy.status_id;
            // user -> display name
            copy.user_id = memberName(b.user_id) || (b.user && (b.user.name || b.user.id)) || copy.user_id;
        } catch (e) {}
        return copy;
    });
}

const showSelector = ref(false);
const selectorTargetIndex = ref(null);
const { toasts, showToast, dismissToast, toastClass } = useToasts();

onMounted(() => {
    try {
        const list = window?.page?.props?.difficulties || page.props.difficulties || null;
        if (Array.isArray(list)) {
            // console.log(
            //     '[AssignmentForm] difficulties id/name list:',
            //     list.map((d) => ({ id: d?.id ?? null, name: d?.name ?? null, slug: d?.slug ?? null })),
            // );
        } else {
            // console.log('[AssignmentForm] difficulties is not an array:', list);
        }
    } catch (e) {
        // console.error('[AssignmentForm] error logging difficulties on mount', e);
    }
});

function clientName(block) {
    try {
        if (block && block.project_job && block.project_job.client) {
            const c = block.project_job.client;
            if (c.name) return c.name;
            if (c.client_name) return c.client_name;
            if (c.name_en) return c.name_en;
        }
        if (block && block.project_job && block.project_job.client_name) return block.project_job.client_name;
        if (props.projectJob && props.projectJob.client) {
            const pc = props.projectJob.client;
            if (pc.name) return pc.name;
            if (pc.client_name) return pc.client_name;
        }
        if (props.projectJob && props.projectJob.client_name) return props.projectJob.client_name;
        const clientId =
            (block && block.project_job && (block.project_job.client?.id || block.project_job.client_id)) ||
            (props.projectJob && (props.projectJob.client?.id || props.projectJob.client_id));
        if (clientId && Array.isArray(page.props.clients)) {
            const found = page.props.clients.find((x) => String(x.id) === String(clientId));
            if (found && found.name) return found.name;
        }
    } catch (e) {}
    return '-';
}

function projectName(block) {
    try {
        if (block && block.project_job) {
            const pj = block.project_job;
            if (pj.title) return pj.title;
            if (pj.name) return pj.name;
            if (pj.project_name) return pj.project_name;
        }
        // fallback: props.projectJob
        if (props.projectJob && (props.projectJob.title || props.projectJob.name)) return props.projectJob.title || props.projectJob.name;
        // fallback: try to find in page.props.userProjects by id
        const pid = block && block.project_job && (block.project_job.id || block.project_job.project_job_id);
        if (pid && Array.isArray(page.props.userProjects)) {
            const found = page.props.userProjects.find((p) => String(p.id) === String(pid));
            if (found) return found.name || found.title || found.project_name || '';
        }
    } catch (e) {}
    return '-';
}

function onSelected(payload) {
    const idx = selectorTargetIndex.value;
    const makeLabel = (kind, id) => {
        if (!id) return null;
        const list = { types: page.props.types, sizes: page.props.sizes, statuses: page.props.statuses, stages: page.props.stages }[kind];
        if (!Array.isArray(list)) return null;
        const found = list.find((x) => String(x.id) === String(id));
        return found ? `${kind.replace(/s$/, '')}: ${found.name}` : null;
    };

    if (idx === null || idx === undefined) {
        assignments.value.push({
            title_prefix: `「${props.projectJob?.title || ''}：`,
            title_suffix: payload.size_id ? `サイズ: ${payload.size_id}` : '',
            detail: '',
            difficulty: 'normal',
            desired_date: '',
            desired_time_hour: '09',
            desired_time_min: '00',
            estimated_hours: '',
            user_id: '',
            work_item_type_id: payload.work_item_type_id,
            size_id: payload.size_id,
            stage_id: payload.stage_id || null,
            status_id: payload.status_id,
            company_id: payload.company_id,
            department_id: payload.department_id,
            saving: false,
            linked_assignment_id: null,
            type_label: makeLabel('types', payload.work_item_type_id),
            size_label: makeLabel('sizes', payload.size_id),
            stage_label: makeLabel('stages', payload.stage_id),
            status_label: makeLabel('statuses', payload.status_id),
        });
    } else {
        const b = assignments.value[idx];
        b.work_item_type_id = payload.work_item_type_id;
        b.size_id = payload.size_id;
        b.stage_id = payload.stage_id || null;
        b.status_id = payload.status_id;
        b.company_id = payload.company_id;
        b.department_id = payload.department_id;
        b.type_label = makeLabel('types', payload.work_item_type_id);
        b.size_label = makeLabel('sizes', payload.size_id);
        b.stage_label = makeLabel('stages', payload.stage_id);
        b.status_label = makeLabel('statuses', payload.status_id);
    }
    selectorTargetIndex.value = null;
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
    // try userProjects/client info (some pages expose company via project -> client)
    try {
        const up = page.props.userProjects || page.props.user_projects || null;
        if (Array.isArray(up)) {
            const found = up.find((p) => {
                if (!p) return false;
                if (p.client && (String(p.client.id) === String(companyId) || String(p.client_id) === String(companyId))) return true;
                if (p.company && (String(p.company.id) === String(companyId) || String(p.company_id) === String(companyId))) return true;
                return false;
            });
            if (found) {
                if (found.client && found.client.name) return found.client.name;
                if (found.company && found.company.name) return found.company.name;
            }
        }
    } catch (e) {}
    // fallback: original companyName() which will at least return the id string
    return companyName(companyId);
}

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

// block 内の情報から部署名を優先取得。見つからなければ departmentNameFromId にフォールバック。
function departmentNameFromBlock(block) {
    try {
        if (!block) return departmentNameFromId(null);
        if (block.department && (block.department.name || block.department.department_name)) {
            return block.department.name || block.department.department_name;
        }
        if (block.department_name) return block.department_name;
        // project_job にネストされている場合
        if (block.project_job && block.project_job.department) {
            const d = block.project_job.department;
            if (d.name) return d.name;
            if (d.department_name) return d.department_name;
        }
    } catch (e) {
        // ignore
    }
    return departmentNameFromId(block && block.department_id ? block.department_id : null);
}

function companyName(companyId) {
    if (!companyId) return 'グローバル/未設定';
    const found = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    return found ? found.name : String(companyId);
}

function departmentName(departmentId) {
    if (!departmentId) return '指定なし';
    const all = departmentsFlattened();
    const found = all.find((d) => String(d.id) === String(departmentId));
    return found ? found.name : String(departmentId);
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
    // if userId already a string name (from normalization), return it
    if (typeof userId === 'string' && isNaN(Number(userId))) return userId;
    const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
    if (m) return m.name || m.full_name || String(m.id);
    // fallback: look in page props users if available
    const pageUsers = page.props.users || page.props.members || [];
    const found = (Array.isArray(pageUsers) ? pageUsers : []).find((u) => String(u.id) === String(userId));
    if (found) return found.name || found.full_name || String(found.id);
    // if user object exists on block
    try {
        if (userId && typeof userId === 'object') return userId.name || userId.full_name || String(userId.id || '');
    } catch (e) {}
    return String(userId);
}

function typesForSelect(companyId, departmentId) {
    const list = page.props.types || [];
    const comp = companyId || (page.props.company ? page.props.company.id : page.props.auth.user.company_id) || '';
    const dept = departmentId || (page.props.department ? page.props.department.id : page.props.auth.user.department_id) || '';
    return list.filter((t) => {
        const compMatch = !t.company_id || String(t.company_id) === String(comp);
        const deptMatch = !t.department_id || String(t.department_id) === String(dept);
        return compMatch && deptMatch;
    });
}

function sizesForSelect(companyId, departmentId) {
    const list = page.props.sizes || [];
    const comp = companyId || (page.props.company ? page.props.company.id : page.props.auth.user.company_id) || '';
    const dept = departmentId || (page.props.department ? page.props.department.id : page.props.auth.user.department_id) || '';
    return list.filter((s) => {
        const compMatch = !s.company_id || String(s.company_id) === String(comp);
        const deptMatch = !s.department_id || String(s.department_id) === String(dept);
        return compMatch && deptMatch;
    });
}

function stagesForSelect(companyId, departmentId) {
    const list = page.props.stages || [];
    const comp = companyId || (page.props.company ? page.props.company.id : page.props.auth.user.company_id) || '';
    const dept = departmentId || (page.props.department ? page.props.department.id : page.props.auth.user.department_id) || '';
    return list.filter((st) => {
        const compMatch = !st.company_id || String(st.company_id) === String(comp);
        const deptMatch = !st.department_id || String(st.department_id) === String(dept);
        return compMatch && deptMatch;
    });
}

function openInlineSelector(idx) {
    const b = assignments.value[idx];
    b.showInlineSelector = true;
    if (!b.selectionForm) {
        b.selectionForm = {
            company_id: b.company_id || page.props.company ? page.props.company.id : page.props.auth.user.company_id || '',
            department_id: b.department_id || page.props.department ? page.props.department.id : page.props.auth.user.department_id || '',
            type_id: b.work_item_type_id || '',
            size_id: b.size_id || '',
            stage_id: b.stage_id || '',
            status_id: b.status_id || '',
        };
    }
}

function cancelInlineSelector(idx) {
    const b = assignments.value[idx];
    if (b) b.showInlineSelector = false;
}

function applyInlineSelected(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    const f = b.selectionForm || {};
    b.work_item_type_id = f.type_id || null;
    b.size_id = f.size_id || null;
    b.stage_id = f.stage_id || null;
    b.status_id = f.status_id || null;
    b.company_id = f.company_id || null;
    b.department_id = f.department_id || null;
    b.type_label = b.work_item_type_id ? makeLabel('types', b.work_item_type_id) : null;
    b.size_label = b.size_id ? makeLabel('sizes', b.size_id) : null;
    b.stage_label = b.stage_id ? makeLabel('stages', b.stage_id) : null;
    b.status_label = b.status_id ? makeLabel('statuses', b.status_id) : null;
    b.showInlineSelector = false;
}

function onInlineSelectionChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    b.type_label = b.work_item_type_id ? makeLabel('types', b.work_item_type_id) : null;
    b.size_label = b.size_id ? makeLabel('sizes', b.size_id) : null;
    b.stage_label = b.stage_id ? makeLabel('stages', b.stage_id) : null;
    b.status_label = b.status_id ? makeLabel('statuses', b.status_id) : null;
}

function hasDepartment(block) {
    return block && block.department_id !== undefined && block.department_id !== null && String(block.department_id) !== '';
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

function openSelector(idx) {
    selectorTargetIndex.value = idx;
    showSelector.value = true;
}

function addBlock() {
    assignments.value.push({
        title_prefix: `「${props.projectJob?.title || ''}：`,
        title_suffix: '',
        detail: '',
        difficulty: 'normal',
        desired_date: '',
        desired_time_hour: '09',
        desired_time_min: '00',
        estimated_hours: '',
        user_id: props.defaultUserId || '',
        company_id: effectiveAuthUser() ? effectiveAuthUser().company_id : null,
        department_id: effectiveAuthUser() ? effectiveAuthUser().department_id : null,
        saving: false,
        linked_assignment_id: null,
        amount_digit_0: '0',
        amount_digit_1: '0',
        amount_digit_2: '0',
        amount_digit_3: '0',
        amounts: 0,
        amounts_unit: 'page',
        project_job: props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title } : null,
    });
}

function removeBlock(i) {
    assignments.value.splice(i, 1);
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

// Resolve difficulty to numeric id.
// Accepts: numeric id (number or numeric string), name, slug, key (case-insensitive)
function resolveDifficultyId(val) {
    if (val === undefined || val === null || val === '') return null;
    // number-like
    const num = Number(val);
    if (!Number.isNaN(num) && String(val).trim() !== '') return num;
    const list = window?.page?.props?.difficulties || page.props.difficulties || null;
    if (Array.isArray(list)) {
        const lower = String(val).toLowerCase();
        const found = list.find((d) => {
            if (!d) return false;
            if (String(d.id) === String(val)) return true;
            if (d.name && String(d.name).toLowerCase() === lower) return true;
            if (d.slug && String(d.slug).toLowerCase() === lower) return true;
            if (d.key && String(d.key).toLowerCase() === lower) return true;
            if (d.value && String(d.value).toLowerCase() === lower) return true;
            return false;
        });
        if (found) return found.id;
    }
    // Fallback: support known english keys mapping to localized names
    try {
        const list = window?.page?.props?.difficulties || page.props.difficulties || null;
        if (Array.isArray(list)) {
            const key = String(val).toLowerCase();
            const synonyms = {
                light: ['軽い', 'かるい', 'light'],
                normal: ['普通', 'ふつう', 'normal'],
                heavy: ['重い', 'おもい', '重度', '重大', 'heavy'],
            };
            if (Object.prototype.hasOwnProperty.call(synonyms, key)) {
                const candidates = synonyms[key];
                const found2 = list.find((d) => {
                    if (!d || !d.name) return false;
                    return candidates.some((s) => String(d.name).toLowerCase() === String(s).toLowerCase());
                });
                if (found2) return found2.id;
            }

            // Also try a contains-match: if val is an english key, find a difficulty whose name contains expected kanji
            const containsMap = {
                light: ['軽'],
                normal: ['普'],
                heavy: ['重', '大'],
            };
            const k2 = String(val).toLowerCase();
            if (Object.prototype.hasOwnProperty.call(containsMap, k2)) {
                const chars = containsMap[k2];
                const found3 = list.find((d) => {
                    if (!d || !d.name) return false;
                    return chars.some((ch) => String(d.name).indexOf(ch) !== -1);
                });
                if (found3) return found3.id;
            }
        }
    } catch (e) {
        // ignore and return null
    }
    return null;
}

// Build the title to send to the server: prefer title_suffix (job name) only.
function assembleTitle(a) {
    try {
        if (a?.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
        // Fallback: strip any leading project name and punctuation from title_prefix
        let t = a?.title_prefix ?? '';
        t = String(t || '').replace(/^\s+|\s+$/g, '');
        // remove leading Japanese quote and anything up to a full-width or ascii colon
        t = t.replace(/^.*?[：:]/, '');
        // remove surrounding quotes if any
        t = t.replace(/^\u300c|\u300d|"|'/g, '').replace(/\u300c|\u300d|"|'$/g, '');
        return t.trim();
    } catch (e) {
        return '';
    }
}

// ユーザー情報オブジェクトを取得（props.members -> page.props.users の順に探索）
function memberById(userId) {
    if (!userId) return null;
    try {
        const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
        if (m) return m;
        const pageUsers = page.props.users || page.props.members || [];
        if (Array.isArray(pageUsers)) {
            const p = pageUsers.find((u) => String(u.id) === String(userId));
            if (p) return p;
        }
    } catch (e) {}
    return null;
}

// 割当ユーザー（または block）の会社名を優先取得。見つからなければ既存の companyNameFromId にフォールバック。
function memberCompanyName(userId, block) {
    try {
        const m = memberById(userId);
        if (m) {
            if (m.company && (m.company.name || m.company.company_name)) return m.company.name || m.company.company_name;
            if (m.company_name) return m.company_name;
            if (m.company_id) return companyNameFromId(m.company_id);
        }
    } catch (e) {}
    // block に会社情報があれば優先
    try {
        if (block) {
            if (block.company && (block.company.name || block.company.company_name)) return block.company.name || block.company.company_name;
            if (block.company_name) return block.company_name;
            // project_job.client があるケース（既存の client 表示を補助）
            if (block.project_job && block.project_job.client && (block.project_job.client.name || block.project_job.client.client_name))
                return block.project_job.client.name || block.project_job.client.client_name;
            if (block.company_id) return companyNameFromId(block.company_id);
        }
    } catch (e) {}
    // 最終フォールバック
    return companyNameFromId(null);
}

// 割当ユーザー（または block）の部署名を優先取得。見つからなければ既存の departmentNameFromId にフォールバック。
function memberDepartmentName(userId, block) {
    try {
        const m = memberById(userId);
        if (m) {
            if (m.department && (m.department.name || m.department.department_name)) return m.department.name || m.department.department_name;
            if (m.department_name) return m.department_name;
            if (m.department_id) return departmentNameFromId(m.department_id);
        }
    } catch (e) {}
    try {
        if (block) {
            if (block.department && (block.department.name || block.department.department_name))
                return block.department.name || block.department.department_name;
            if (block.department_name) return block.department_name;
            if (block.department_id) return departmentNameFromId(block.department_id);
        }
    } catch (e) {}
    // 最終フォールバック
    return departmentNameFromId(null);
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

const saving = ref(false);

async function save() {
    // console.log('[AssignmentForm_user] save invoked', {
    //     editMode: props.editMode,
    //     assignments_sample: assignments.value && assignments.value[0] ? assignments.value[0] : null,
    // });
    if (!props.editMode) {
        // console.log('[AssignmentForm_user] editMode is false — aborting save');
        return;
    }
    saving.value = true;

    function assembleTitle(a) {
        if (a.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
        const maybe = a.project_job && (a.project_job.title || a.project_job.name) ? a.project_job.title || a.project_job.name : null;
        if (!maybe) return '';
        if (maybe.includes('：')) return maybe.replace(/^.*：/, '').trim();
        return String(maybe).trim();
    }

    const payload = {
        assignments: assignments.value.map((a) => ({
            title: assembleTitle(a),
            detail: a.detail || '',
            user_id: a.user_id || (effectiveAuthUser() ? effectiveAuthUser().id : null),
            sender_id: effectiveAuthUser() ? effectiveAuthUser().id : null,
            project_job_id: a.project_job_id || null,
            company_id: a.company_id || null,
            department_id: a.department_id || null,
            difficulty: a.difficulty || 'normal',
            difficulty_id:
                a.difficulty_id ??
                (window?.page?.props?.difficulties
                    ? (window.page.props.difficulties.find((d) => d.name === a.difficulty || d.slug === a.difficulty)?.id ?? null)
                    : null),

            desired_start_date: a.desired_start_date || null,
            desired_end_date: a.desired_end_date || null,
            start_time: String(a.start_time_hour || '00').padStart(2, '0') + ':' + String(a.start_time_min || '00').padStart(2, '0'),
            desired_time: String(a.desired_time_hour || '00').padStart(2, '0') + ':' + String(a.desired_time_min || '00').padStart(2, '0'),
            estimated_hours: a.estimated_hours || null,
            work_item_type_id: a.work_item_type_id || null,
            size_id: a.size_id || null,
            stage_id: a.stage_id || null,
            status_id: a.status_id || null,
            amounts: typeof a.amounts === 'number' ? a.amounts : Number(a.amounts) || 0,
            amounts_unit: a.amounts_unit || 'page',
        })),
    };

    try {
        const auth = effectiveAuthUser();
        const allForAuth = payload.assignments.every((x) => String(x.user_id) === String(auth ? auth.id : null));
        const computedProjectJobId =
            props.projectJob && props.projectJob.id ? props.projectJob.id : payload.assignments[0] ? payload.assignments[0].project_job_id : null;

        // console.log('[AssignmentForm_user] computedProjectJobId:', computedProjectJobId, 'allForAuth?', allForAuth);

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
            // console.log('[AssignmentForm_user] posting (fetch) to (relative):', rel);

            // CSRF token
            const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            try {
                const res = await inertiaFetch(rel, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Inertia': 'true',
                    },
                    body: JSON.stringify(payload),
                });
                // console.log('[AssignmentForm_user] fetch POST status:', res.status, 'ok?', res.ok);
                if (res.ok) {
                    // Successful — refresh to reflect changes (adjust to Inertia visit if you prefer)
                    location.reload();
                    return;
                } else {
                    const txt = await res.text().catch(() => '');
                    console.error('[AssignmentForm_user] fetch POST failed:', res.status, txt);
                    alert('保存に失敗しました（' + res.status + '）');
                    return;
                }
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
        // console.log('[AssignmentForm_user] coordinator-store posting to:', rel2);
        const token2 = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const res2 = await inertiaFetch(rel2, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token2,
                'X-Requested-With': 'XMLHttpRequest',
                'X-Inertia': 'true',
            },
            body: JSON.stringify(payload),
        });

        // inertiaFetch が遷移を実施した場合は処理終了
        if (!res2 || res2.navigated) return;

        if (res2.ok) {
            location.reload();
            return;
        } else {
            const txt2 = await res2.text().catch(() => '');
            console.error('[AssignmentForm_user] coordinator fetch POST failed:', res2.status, txt2);
            alert('保存に失敗しました（' + res2.status + '）');
            return;
        }
    } catch (err2) {
        console.error('[AssignmentForm_user] coordinator fetch error:', err2);
        alert('保存に失敗しました（ネットワークエラー）');
    } finally {
        saving.value = false;
    }
}

// ログイン中ユーザー（auth）の会社名を優先して取得。なければ page.props.company / id ベースにフォールバック。
function authCompanyName() {
    try {
        const auth = effectiveAuthUser();
        // auth がユーザーオブジェクトで company 情報を持つ場合を優先
        if (auth) {
            if (auth.company && (auth.company.name || auth.company.company_name)) return auth.company.name || auth.company.company_name;
            if (auth.company_name) return auth.company_name;
            if (auth.company_id) return companyNameFromId(auth.company_id);
        }
    } catch (e) {}
    // 次にページ単位で渡されている company prop をチェック
    try {
        if (page.props && page.props.company && page.props.company.name) return page.props.company.name;
    } catch (e) {}
    // 最終フォールバック
    return companyNameFromId(null);
}

// ログイン中ユーザー（auth）の部署名を優先して取得。なければ page.props.department / id ベースにフォールバック。
function authDepartmentName() {
    try {
        const auth = effectiveAuthUser();
        if (auth) {
            if (auth.department && (auth.department.name || auth.department.department_name))
                return auth.department.name || auth.department.department_name;
            if (auth.department_name) return auth.department_name;
            if (auth.department_id) return departmentNameFromId(auth.department_id);
        }
    } catch (e) {}
    try {
        if (page.props && page.props.department && page.props.department.name) return page.props.department.name;
    } catch (e) {}
    return departmentNameFromId(null);
}
</script>

<style scoped>
/* small styles (none) */
</style>
