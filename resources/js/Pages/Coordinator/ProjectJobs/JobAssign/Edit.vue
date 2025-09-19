<template>
    <AppLayout :title="`ジョブ割り当て - ${projectJob.title}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ割り当て：{{ projectJob.title }}</h1>

            <form @submit.prevent="save">
                <div v-for="(block, idx) in assignments" :key="idx" class="mb-4 rounded border p-4">
                    <label class="mb-1 block font-semibold">ジョブ名</label>
                    <div class="flex items-center gap-2">
                        <span class="whitespace-nowrap rounded-l border bg-gray-100 px-3 py-2">{{ block.title_prefix }}</span>
                        <input v-model="block.title_suffix" type="text" class="flex-1 rounded-r border px-3 py-2" />
                    </div>
                    <div v-if="block.selection_label" class="mt-1 text-sm text-gray-600">{{ block.selection_label }}</div>

                    <!-- show client name (read-only) between title and detail -->
                    <label class="mb-1 block font-semibold">クライアント</label>
                    <div class="w-full rounded border bg-gray-50 px-3 py-2">
                        {{ block.project_job?.client?.name || projectJob.client?.name || '-' }}
                    </div>

                    <label class="mb-1 mt-2 block font-semibold">概要</label>
                    <textarea v-model="block.detail" class="w-full rounded border px-3 py-2" rows="3"></textarea>

                    <label class="mb-1 mt-2 block font-semibold">作業詳細</label>
                    <div v-if="block.type_label || block.size_label || block.stage_label || block.status_label" class="mb-2 text-sm text-gray-700">
                        <div v-if="block.type_label">{{ block.type_label }}</div>
                        <div v-if="block.size_label">{{ block.size_label }}</div>
                        <div v-if="block.stage_label">{{ block.stage_label }}</div>
                        <div v-if="block.status_label">{{ block.status_label }}</div>
                    </div>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-2 text-white" @click="openSelector(idx)">選択して挿入</button>

                    <label class="mb-1 mt-2 block font-semibold">難易度</label>
                    <select v-model="block.difficulty" class="w-full rounded border px-3 py-2">
                        <option value="light">軽い</option>
                        <option value="normal">普通</option>
                        <option value="heavy">重い</option>
                    </select>

                    <div class="mt-2">
                        <label class="mb-1 block font-semibold">割当希望日</label>
                        <input v-model="block.desired_start_date" type="date" class="w-full rounded border px-3 py-2" />

                        <div class="mt-2">
                            <label class="mb-1 block font-semibold">終了希望日, 希望時間</label>
                            <div class="flex items-center gap-3">
                                <input
                                    v-model="block.desired_end_date"
                                    :min="minEndDate(idx)"
                                    type="date"
                                    class="rounded border px-3 py-2"
                                    @change="onEndDateChange(idx)"
                                />

                                <select v-model="block.desired_time_hour" class="w-20 rounded border px-3 py-2" @change="onHourChange(idx)">
                                    <option v-for="h in availableHours(idx)" :key="h" :value="h">{{ h }}</option>
                                </select>
                                <select v-model="block.desired_time_min" class="w-20 rounded border px-3 py-2">
                                    <option v-for="m in availableMins(idx, block.desired_time_hour)" :key="m" :value="m">{{ m }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <label class="mb-1 mt-2 block font-semibold">見積時間</label>
                    <div class="flex items-center gap-2">
                        <select v-model="block.estimated_hours" class="w-40 rounded border px-3 py-2">
                            <option value="">未指定</option>
                            <option v-for="opt in estimatedOptions" :key="opt" :value="opt">{{ String(opt).replace('.0', '') }}h</option>
                        </select>
                        <span class="text-sm text-gray-500">(0.25刻み、例: 1.5 = 1時間30分)</span>
                    </div>

                    <label class="mb-1 mt-2 block font-semibold">割当ユーザー</label>
                    <select v-model="block.user_id" class="w-full rounded border px-3 py-2">
                        <option value="">未指定</option>
                        <option v-for="m in members" :key="m.id" :value="m.id">{{ m.id }}：{{ m.name }}</option>
                    </select>

                    <div class="mt-2 text-right">
                        <template v-if="block.linked_assignment_id">
                            <a
                                :href="
                                    route('coordinator.project_jobs.assignments.show', {
                                        projectJob: projectJob.id,
                                        assignment: block.linked_assignment_id,
                                    })
                                "
                                class="ml-3 text-sm text-blue-600"
                                >割当を見る (#{{ block.linked_assignment_id }})</a
                            >
                        </template>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button v-if="!props.editMode" type="button" class="rounded bg-blue-600 px-4 py-2 text-white" @click="addBlock">
                        ジョブブロックを追加
                    </button>
                    <button type="submit" class="rounded bg-green-600 px-4 py-2 text-white">保存する</button>
                    <Link
                        :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })"
                        class="rounded bg-gray-200 px-4 py-2"
                        >戻る</Link
                    >
                </div>
            </form>
        </div>
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
    </AppLayout>
</template>

<script setup>
import SelectionModal from '@/Components/SelectionModal.vue';
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ projectJob: Object, members: Array, assignments: Array, editMode: { type: Boolean, default: false } });
const page = usePage();

const hours = Array.from({ length: 17 }, (_, i) => String(6 + i).padStart(2, '0'));
const mins = ['00', '15', '30', '45'];
// estimated hours options: 0.25 to 8.0 in 0.25 steps
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
        // split title into immutable prefix and editable suffix
        title_prefix: `${props.projectJob?.title || ''}：`,
        title_suffix: (() => {
            const raw = a.title || '';
            const prefix = `「${props.projectJob?.title || ''}：`;
            if (!raw) return '';
            if (raw.startsWith(prefix)) return raw.slice(prefix.length).trim();
            return raw;
        })(),
        detail: a.detail || '',
        difficulty: a.difficulty || 'normal',
        desired_start_date: a.desired_start_date || a.desired_date || '',
        desired_end_date: a.desired_end_date || '',
        desired_time_hour: a.desired_time ? a.desired_time.split(':')[0] || '09' : a.desired_time_hour || '09',
        desired_time_min: a.desired_time ? a.desired_time.split(':')[1] || '00' : a.desired_time_min || '00',
        estimated_hours: a.estimated_hours !== undefined && a.estimated_hours !== null ? a.estimated_hours : '',
        user_id: a.user_id || (a.user ? a.user.id : '') || '',
        // preserve work_item lookup ids if present (so save() includes them)
        work_item_type_id: a.work_item_type_id || null,
        size_id: a.size_id || null,
        stage_id: a.stage_id || null,
        status_id: a.status_id || null,
        company_id: a.company_id || null,
        department_id: a.department_id || null,
        // UX labels for display
        type_label: a.type_label || makeLabel('types', a.work_item_type_id),
        size_label: a.size_label || makeLabel('sizes', a.size_id),
        stage_label: a.stage_label || makeLabel('stages', a.stage_id),
        status_label: a.status_label || makeLabel('statuses', a.status_id),
        // include project_job info when available so templates can display client safely
        project_job:
            a.project_job ||
            (props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title, client: props.projectJob.client || null } : null),
    };
}

const assignments = ref(props.assignments && props.assignments.length ? props.assignments.map(normalizeAssignment) : [normalizeAssignment({})]);
// ensure each assignment block has UX fields
assignments.value.forEach((a) => {
    if (a.saving === undefined) a.saving = false;
    if (a.linked_assignment_id === undefined) a.linked_assignment_id = null;
    if (a.title_prefix === undefined) a.title_prefix = `「${props.projectJob?.title || ''}：`;
    if (a.title_suffix === undefined) a.title_suffix = '';
});
const showSelector = ref(false);
// which assignment block index the selector will populate
const selectorTargetIndex = ref(null);

// use shared toast composable
const { toasts, showToast, dismissToast, toastClass } = useToasts();

function onSelected(payload) {
    // if a target index is set, populate that block, otherwise create a new block
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
            // store selection ids so submit can persist them if needed
            work_item_type_id: payload.work_item_type_id,
            size_id: payload.size_id,
            stage_id: payload.stage_id || null,
            status_id: payload.status_id,
            company_id: payload.company_id,
            department_id: payload.department_id,
            // UX fields
            saving: false,
            linked_assignment_id: null,
            // display labels
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
        // update labels
        b.type_label = makeLabel('types', payload.work_item_type_id);
        b.size_label = makeLabel('sizes', payload.size_id);
        b.stage_label = makeLabel('stages', payload.stage_id);
        b.status_label = makeLabel('statuses', payload.status_id);
    }
    // reset selector target
    selectorTargetIndex.value = null;
}

function openSelector(idx) {
    // set which assignment block the selector should populate and show the modal
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
        user_id: '',
        saving: false,
        linked_assignment_id: null,
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
    // if end date is today, disallow hours earlier than now
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
    // same hour as now: allow only mins >= current minute rounded to next quarter
    const curMin = now.getMinutes();
    const nextQuarter = Math.ceil(curMin / 15) * 15;
    return mins.filter((m) => Number(m) >= nextQuarter);
}

function onEndDateChange(idx) {
    const a = assignments.value[idx];
    // if end date is before start date, clamp it
    if (a.desired_start_date && a.desired_end_date && a.desired_end_date < a.desired_start_date) {
        a.desired_end_date = a.desired_start_date;
    }
}

function onHourChange(idx) {
    const a = assignments.value[idx];
    // if selected hour is now greater than available hours, clamp minute options
    const avail = availableMins(idx, a.desired_time_hour);
    if (!avail.includes(a.desired_time_min)) {
        a.desired_time_min = avail.length ? avail[0] : '00';
    }
}

function save() {
    if (props.editMode && assignments.value.length === 1 && assignments.value[0].id) {
        const a = assignments.value[0];
        const payload = {
            title: `${a.title_prefix}${a.title_suffix ? ' ' + a.title_suffix : ''}`,
            detail: a.detail,
            difficulty: a.difficulty,
            estimated_hours: a.estimated_hours || null,
            desired_start_date: a.desired_start_date || null,
            desired_end_date: a.desired_end_date || null,
            desired_time: String(a.desired_time_hour).padStart(2, '0') + ':' + String(a.desired_time_min).padStart(2, '0'),
            user_id: a.user_id || null,
            // include lookup ids when updating a single assignment
            work_item_type_id: a.work_item_type_id || null,
            size_id: a.size_id || null,
            stage_id: a.stage_id || null,
            status_id: a.status_id || null,
            company_id: a.company_id || null,
            department_id: a.department_id || null,
        };
        router.put(route('coordinator.project_jobs.assignments.update', { projectJob: props.projectJob.id, assignment: a.id }), payload);
        return;
    }

    const payload = {
        assignments: assignments.value.map((a) => ({
            title: `${a.title_prefix}${a.title_suffix ? ' ' + a.title_suffix : ''}`,
            detail: a.detail,
            difficulty: a.difficulty,
            estimated_hours: a.estimated_hours || null,
            desired_start_date: a.desired_start_date || null,
            desired_end_date: a.desired_end_date || null,
            desired_time: String(a.desired_time_hour).padStart(2, '0') + ':' + String(a.desired_time_min).padStart(2, '0'),
            user_id: a.user_id || null,
            // include work_item payload so backend can create WorkItem linked to assignment
            // lookup ids moved to top-level to match server-side schema
            work_item_type_id: a.work_item_type_id || null,
            size_id: a.size_id || null,
            status_id: a.status_id || null,
            company_id: a.company_id || null,
            department_id: a.department_id || null,
            stage_id: a.stage_id || null,
        })),
    };
    router.post(route('coordinator.project_jobs.assignments.store', { projectJob: props.projectJob.id }), payload);
}

// WorkItem saving now happens server-side when creating ProjectJobAssignment.
</script>

<style scoped>
/* small styles */
</style>
