<template>
    <form @submit.prevent="save">
        <div v-for="(block, idx) in assignments" :key="idx" class="mb-4 rounded border p-4">
            <!-- クライアント -->
            <label class="mb-1 block font-semibold">クライアント</label>
            <div v-if="props.mode === 'coordinator'" class="w-full rounded border bg-gray-50 px-3 py-2">
                {{ clientName(block) }}
            </div>
            <div v-else class="w-full">
                <select v-model="block._client_id" :disabled="!editMode" class="w-full rounded border px-3 py-2" @change="onClientChange(idx)">
                    <option value="">-- 選択 --</option>
                    <option v-for="c in userClients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>
            </div>

            <!-- プロジェクト名 -->
            <label class="mb-1 mt-2 block font-semibold">プロジェクト名</label>
            <div v-if="props.mode === 'coordinator'" class="w-full rounded border bg-gray-50 px-3 py-2">
                {{ projectName(block) }}
            </div>
            <div v-else>
                <select v-model="block.project_job_id" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                    <option value="">-- 選択 --</option>
                    <option v-for="p in projectsForBlock(block)" :key="p.id" :value="p.id">{{ p.title || p.name }}</option>
                </select>
            </div>

            <label class="mb-1 mt-2 block font-semibold">ジョブ名</label>
            <div>
                <input v-model="block.title_suffix" :disabled="!editMode" type="text" class="w-full rounded border px-3 py-2" />
            </div>

            <label class="mb-1 mt-2 block font-semibold">概要</label>
            <textarea v-model="block.detail" :disabled="!editMode" class="w-full rounded border px-3 py-2" rows="3"></textarea>

            <!-- 割当ユーザー（概要直下に移動） -->
            <label class="mb-1 mt-3 block font-semibold">割当ユーザー</label>
            <div v-if="!editMode" class="mt-1 flex items-center gap-2 rounded border bg-gray-50 px-3 py-2 text-sm">
                <span>{{ memberName(block.user_id) }}</span>
                <span
                    v-if="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type]"
                    class="rounded-full px-2 py-0 text-xs font-medium"
                    :class="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].cls"
                >
                    {{ EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].label }}
                </span>
            </div>
            <div v-else>
                <select v-model="block.user_id" class="w-full rounded border px-3 py-2" @change="onUserChange(block)">
                    <option value="">未指定</option>
                    <option v-for="m in props.members || members" :key="m.id" :value="m.id">
                        {{ m.name }}{{ m.assignment_name ? '（' + m.assignment_name + '）' : '' }}
                        {{ ['dispatch','outsource','contract'].includes(m.employment_type) ? '【' + m.employment_type_label + '】' : '' }}
                    </option>
                </select>
                <!-- 選択後の雇用形態バッジ（派遣・業務委託のみ表示） -->
                <div
                    v-if="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type]"
                    class="mt-1.5 flex items-center gap-1.5 rounded border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs"
                >
                    <span
                        class="rounded-full px-2 py-0.5 font-medium"
                        :class="EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].cls"
                    >
                        {{ EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].label }}
                    </span>
                    <span class="text-orange-700">
                        このユーザーは{{ EMPLOYMENT_BADGE[memberEmploymentType(block.user_id)?.employment_type].label }}です。
                    </span>
                </div>
            </div>

            <!-- 作業詳細ヘッダー＋フィルター -->
            <div class="mb-1 mt-4 flex flex-wrap items-center gap-2">
                <span class="font-semibold">作業詳細</span>
                <template v-if="editMode">
                    <select v-model="block._type_filter" class="rounded border px-2 py-1 text-xs text-gray-700">
                        <option value="">業種：全部表示</option>
                        <option value="dtp">組版・DTP</option>
                        <option value="design">デザイン・制作</option>
                        <option value="proof">校正・確認</option>
                        <option value="mgmt">進行管理・事務</option>
                        <option value="sales">営業</option>
                        <option value="common">共通</option>
                    </select>
                    <select v-model="block._medium_filter" class="rounded border px-2 py-1 text-xs text-gray-700">
                        <option value="paper">紙媒体</option>
                        <option value="digital">デジタル</option>
                        <option value="">媒体：全表示</option>
                    </select>
                </template>
            </div>
            <div>
                <input type="hidden" v-model="block.company_id" />
                <input type="hidden" v-model="block.department_id" />
            </div>

            <!-- 作業種別（Type）＋ ステージ（Stage）を1行に -->
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">作業種別</label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ itemName('types', block.work_item_type_id) }}
                    </div>
                    <select
                        v-else
                        v-model="block.work_item_type_id"
                        :disabled="props.mode === 'coordinator' ? (!hasDepartment(block) || !editMode) : !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <template v-for="grp in typesGrouped(block.company_id, block.department_id, block._type_filter || '')" :key="grp.group">
                            <optgroup :label="grp.label">
                                <option v-for="t in grp.items" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                            </optgroup>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">ステージ（校数）</label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ itemName('stages', block.stage_id) }}
                    </div>
                    <select
                        v-else
                        v-model="block.stage_id"
                        :disabled="props.mode === 'coordinator' ? (!hasDepartment(block) || !editMode) : !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="st in stagesForSelect(block.company_id, block.department_id)" :key="st.id" :value="String(st.id)">
                            {{ st.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- サイズ（Size）＋ ステータス（user モードのみ）を1行に -->
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">サイズ</label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">{{ itemName('sizes', block.size_id) }}</div>
                    <select
                        v-else
                        v-model="block.size_id"
                        :disabled="props.mode === 'coordinator' ? (!hasDepartment(block) || !editMode) : !editMode"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <template v-for="grp in sizesGrouped(block.company_id, block.department_id, block._medium_filter ?? 'paper')" :key="grp.group">
                            <optgroup :label="grp.label">
                                <option v-for="s in grp.items" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </optgroup>
                        </template>
                    </select>
                </div>
                <!-- Status: coordinator では非表示、user では表示 -->
                <div v-if="props.mode === 'user'">
                    <label class="block text-xs font-medium text-gray-600">
                        ステータス
                        <span v-if="!block.id && props.defaultStatusId" class="ml-1 font-normal text-gray-400">（新規固定）</span>
                    </label>
                    <div v-if="!editMode" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ itemName('statuses', block.status_id) }}
                    </div>
                    <select
                        v-else
                        v-model="block.status_id"
                        :disabled="!editMode || (!block.id && props.defaultStatusId !== null && props.defaultStatusId !== undefined)"
                        @change="onInlineSelectionChange(idx)"
                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                    >
                        <option value="">-- 選択 --</option>
                        <option v-for="st in statusesForSelect(block.company_id, block.department_id)" :key="st.id" :value="String(st.id)">
                            {{ st.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- 数量（ページ数を数値入力に変更） -->
            <div class="mt-3 flex items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">数量</label>
                    <div v-if="!editMode" class="mt-1 rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ block.amounts != null ? block.amounts : '-' }}
                        {{ block.amounts_unit === 'page' ? 'ページ' : block.amounts_unit === 'file' ? 'ファイル' : '' }}
                    </div>
                    <input
                        v-else
                        type="number"
                        v-model.number="block.amounts"
                        min="0"
                        max="9999"
                        step="1"
                        :disabled="!editMode"
                        class="mt-1 w-24 rounded border px-3 py-1.5 text-sm"
                        @change="onInlineSelectionChange(idx)"
                    />
                </div>
                <div v-if="editMode">
                    <label class="block text-xs font-medium text-gray-600">単位</label>
                    <select v-model="block.amounts_unit" :disabled="!editMode" class="mt-1 w-28 rounded border px-2 py-1.5 text-sm">
                        <option value="page">ページ</option>
                        <option value="file">ファイル</option>
                    </select>
                </div>
                <div v-else class="pb-2 text-sm text-gray-500">
                    {{ block.amounts_unit === 'page' ? 'ページ' : block.amounts_unit === 'file' ? 'ファイル' : '' }}
                </div>
            </div>

            <label class="mb-1 mt-4 block font-semibold">難易度</label>
            <select v-model="block.difficulty" :disabled="!editMode" class="w-full rounded border px-3 py-2">
                <option value="light">軽い</option>
                <option value="normal">普通</option>
                <option value="heavy">重い</option>
            </select>

            <!-- 締め切り: coordinator = 常に編集可, user = 既存レコードのみ読み取り表示 -->
            <div class="mt-2" v-if="props.mode === 'coordinator'">
                <div class="mt-2">
                    <label class="mb-1 block font-semibold">締め切り</label>
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
            <div
                v-else-if="block.id && (block.desired_start_date || block.desired_end_date || block.desired_time_hour || block.desired_time_min)"
                class="mt-2 flex gap-4"
            >
                <div class="flex-1">
                    <label class="mb-1 block font-semibold">締め切り</label>
                    <div v-if="block.desired_end_date || block.desired_time_hour" class="mt-1 w-full rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ formatEnd(block) }}
                    </div>
                </div>
            </div>

            <!-- 見積時間: coordinator = 常に編集可, user = 既存レコードのみ読み取り表示 -->
            <label class="mb-1 mt-2 block font-semibold">見積時間</label>
            <div class="flex items-center gap-2">
                <template v-if="props.mode === 'coordinator'">
                    <select v-model="block.estimated_hours" :disabled="!editMode" class="w-40 rounded border px-3 py-2">
                        <option value="">未指定</option>
                        <option v-for="opt in estimatedOptions" :key="opt" :value="opt">{{ String(opt).replace('.0', '') }}h</option>
                    </select>
                    <span class="text-sm text-gray-500">(0.25刻み、例: 1.5 = 1時間30分)</span>
                </template>
                <template v-else>
                    <div v-if="block.id && block.estimated_hours" class="mt-1 w-40 rounded border bg-gray-50 px-3 py-2 text-sm">
                        {{ formatEstimated(block) }}
                    </div>
                </template>
            </div>

            <div v-if="props.mode === 'coordinator'" class="mt-2 text-right">
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

        <!-- インラインイベント日時エディタ (user モードのみ) -->
        <div v-if="props.mode === 'user' && editMode" class="mb-4 rounded border p-4">
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
            <template v-if="props.mode === 'coordinator'">
                <button type="button" class="rounded bg-blue-600 px-4 py-2 text-white" @click="addBlock">ジョブブロックを追加</button>
                <button type="submit" class="rounded bg-green-600 px-4 py-2 text-white">保存する</button>
                <Link
                    :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob ? projectJob.id : '' })"
                    class="rounded bg-gray-200 px-4 py-2"
                    >戻る</Link
                >
            </template>
            <template v-else>
                <button type="submit" @click.prevent="save" :disabled="saving" class="rounded bg-green-600 px-4 py-2 text-white">保存する</button>
            </template>
        </div>
    </form>

    <SelectionModal
        v-if="props.mode === 'coordinator' && showSelector"
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
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, inject, onMounted, ref, watch } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'coordinator' },
    projectJob: Object,
    members: Array,
    assignments: Array,
    editMode: { type: Boolean, default: false },
    defaultUserId: { type: [Number, String], default: null },
    hideStatus: { type: Boolean, default: false },
    defaultStatusId: { type: [Number, String], default: null },
    currentCompanyId: { type: [Number, String], default: null },
    currentDepartmentId: { type: [Number, String], default: null },
    userClients: { type: Array, default: () => [] },
    userProjects: { type: Array, default: () => [] },
    otherClientId: { type: [Number, String], default: null },
    otherProjectId: { type: [Number, String], default: null },
    event: { type: Object, default: null },
});
const page = usePage();

const injectedAuthUser = inject('authUser', null);
const injectedUser = inject('user', null);

function effectiveAuthUser() {
    return (
        injectedAuthUser ||
        (page.props && page.props.auth && page.props.auth.user ? page.props.auth.user : null) ||
        (page.props && page.props.user ? page.props.user : null) ||
        null
    );
}

// Debug UI state (user mode)
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

// Inline event editor state (user mode)
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

// hours computed: coordinator = 06-22, user = 07-23
const hours = computed(() => {
    if (props.mode === 'coordinator') {
        return Array.from({ length: 17 }, (_, i) => String(6 + i).padStart(2, '0'));
    }
    return Array.from({ length: 17 }, (_, i) => String(7 + i).padStart(2, '0'));
});
const mins = ['00', '15', '30', '45'];
const estimatedOptions = Array.from({ length: 32 }, (_, i) => Number(((i + 1) * 0.25).toFixed(2)));

function makeLabel(kind, id) {
    if (!id) return null;
    const list = { types: page.props.types, sizes: page.props.sizes, statuses: page.props.statuses, stages: page.props.stages }[kind];
    if (!Array.isArray(list)) return null;
    const found = list.find((x) => String(x.id) === String(id));
    return found ? `${kind.replace(/s$/, '')}: ${found.name}` : null;
}

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
    try {
        const k2 = String(val).toLowerCase();
        const fallbackIds = { light: 1, normal: 2, heavy: 3 };
        if (Object.prototype.hasOwnProperty.call(fallbackIds, k2)) return fallbackIds[k2];
    } catch (e) {}
    return null;
}

function normalizeAssignment(a) {
    if (props.mode === 'coordinator') {
        return {
            id: a.id || null,
            title_prefix: `${props.projectJob?.title || ''}：`,
            title_suffix: (() => {
                const raw = a.title || '';
                if (!raw) return '';
                const pj = props.projectJob?.title || '';
                const candidates = [];
                if (pj) {
                    candidates.push(`「${pj}：`);
                    candidates.push(`${pj}：`);
                    candidates.push(`${pj}:`);
                }
                for (const pref of candidates) {
                    if (raw.startsWith(pref)) return raw.slice(pref.length).trim();
                }
                if (raw.includes('：'))
                    return raw
                        .replace(/^.*？：/, '')
                        .replace(/^.*：/, '')
                        .trim();
                if (raw.includes(':')) return raw.replace(/^.*?:/, '').trim();
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
            status_id: 1,
            company_id: a.company_id || null,
            department_id: a.department_id || null,
            type_label: a.type_label || makeLabel('types', a.work_item_type_id),
            size_label: a.size_label || makeLabel('sizes', a.size_id),
            stage_label: a.stage_label || makeLabel('stages', a.stage_id),
            status_label: a.status_label || makeLabel('statuses', a.status_id),
            amounts: a.amounts !== undefined && a.amounts !== null ? a.amounts : a.amounts || 0,
            amounts_unit: a.amounts_unit || 'page',
            project_job:
                a.project_job ||
                (props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title, client: props.projectJob.client || null } : null),
        };
    } else {
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
            start_time_hour: a.start_time
                ? a.start_time.split(':')[0] || '09'
                : a.start_time_hour || (a.desired_time ? a.desired_time.split(':')[0] : '09'),
            start_time_min: a.start_time
                ? a.start_time.split(':')[1] || '00'
                : a.start_time_min || (a.desired_time ? a.desired_time.split(':')[1] : '00'),
            estimated_hours: a.estimated_hours !== undefined && a.estimated_hours !== null ? a.estimated_hours : '',
            work_item_type_id: a.work_item_type_id ?? null,
            size_id: a.size_id ?? null,
            stage_id: a.stage_id ?? null,
            status_id: a.status_id ?? null,
            amounts: a.amounts !== undefined && a.amounts !== null ? a.amounts : a.amounts_unit ? 0 : undefined,
            amounts_unit: a.amounts_unit ?? 'page',
        };
    }
}

const assignments = ref(props.assignments && props.assignments.length ? props.assignments.map(normalizeAssignment) : [normalizeAssignment({})]);

if (props.mode === 'coordinator') {
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
        if (a.status_id === undefined) a.status_id = 1;
        if ((a.difficulty_id === undefined || a.difficulty_id === null) && a.difficulty) {
            try {
                const resolved = resolveDifficultyId(a.difficulty);
                if (resolved !== null) a.difficulty_id = resolved;
            } catch (e) {}
        }
        const amt = a.amounts !== undefined && a.amounts !== null && a.amounts !== '' ? Number(a.amounts) : null;
        if (amt !== null && !Number.isNaN(amt)) {
            const abs = Math.max(0, Math.floor(Math.abs(amt)) % 10000);
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
        if (a._type_filter === undefined) a._type_filter = '';
        if (a._medium_filter === undefined) a._medium_filter = 'paper';
    });

    assignments.value.forEach((a) => {
        try {
            if (a.project_job) {
                if (!a.project_job.title && !a.project_job.name) {
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

    if (!props.editMode) {
        const memberNameLocal = (userId) => {
            if (!userId) return '';
            const m = (props.members || []).find((mm) => String(mm.id) === String(userId));
            if (m && (m.name || m.full_name)) return m.name || m.full_name;
            if (typeof userId === 'object' && userId !== null) return userId.name || userId.id || '';
            return String(userId || '');
        };

        assignments.value = assignments.value.map((b) => {
            const copy = { ...b };
            try {
                copy.company_id = companyName(b.company_id) || copy.company_id;
                copy.department_id = departmentName(b.department_id) || copy.department_id;
                copy.work_item_type_id = itemName('types', b.work_item_type_id) || copy.work_item_type_id;
                copy.size_id = itemName('sizes', b.size_id) || copy.size_id;
                copy.stage_id = itemName('stages', b.stage_id) || copy.stage_id;
                copy.status_id = itemName('statuses', b.status_id) || copy.status_id;
                copy.user_id = memberNameLocal(b.user_id) || (b.user && (b.user.name || b.user.id)) || copy.user_id;
            } catch (e) {}
            return copy;
        });
    }
} else {
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
        if (a.status_id === undefined || a.status_id === null) {
            if (!a.id && props.defaultStatusId !== null && props.defaultStatusId !== undefined) {
                a.status_id = String(props.defaultStatusId);
            } else {
                a.status_id = a.status_id || null;
            }
        }
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
}

const showSelector = ref(false);
const selectorTargetIndex = ref(null);
const { toasts, showToast, dismissToast, toastClass } = useToasts();

onMounted(() => {
    if (props.mode === 'coordinator') {
        try {
            const list = window?.page?.props?.difficulties || page.props.difficulties || null;
            if (Array.isArray(list)) {
                // difficulties loaded
            }
        } catch (e) {}
    } else {
        // user mode: init inline event editor
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
    }
});

// Watch for user mode prop changes
watch(
    () => ({
        injectedAuthUser: injectedAuthUser,
        injectedUser: injectedUser,
        effective: effectiveAuthUser(),
        pageAuthUser: page.props && page.props.auth ? page.props.auth.user : null,
        pageUser: page.props ? page.props.user : null,
    }),
    (val) => {},
    { deep: true },
);

watch(assignments, () => {}, { deep: true });

// assignment_name → _type_filter の自動マッピング
const ASSIGNMENT_TYPE_MAP = {
    '組版': 'dtp',
    'オペレーター': 'dtp',
    'DTP': 'dtp',
    'dtp': 'dtp',
    'デザイナー': 'design',
    'デザイン': 'design',
    '制作': 'design',
    '校正': 'proof',
    '進行管理': 'mgmt',
    '営業': 'sales',
};

function assignmentNameToTypeFilter(assignmentName) {
    if (!assignmentName) return '';
    for (const [key, val] of Object.entries(ASSIGNMENT_TYPE_MAP)) {
        if (assignmentName.includes(key)) return val;
    }
    return '';
}

function onUserChange(block) {
    const membersList = props.members || members.value || [];
    const found = membersList.find((m) => String(m.id) === String(block.user_id));
    if (found && found.assignment_name) {
        block._type_filter = assignmentNameToTypeFilter(found.assignment_name);
    }
}

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
        if (props.projectJob && (props.projectJob.title || props.projectJob.name)) return props.projectJob.title || props.projectJob.name;
        const pid = block && block.project_job && (block.project_job.id || block.project_job.project_job_id);
        if (pid && Array.isArray(page.props.userProjects)) {
            const found = page.props.userProjects.find((p) => String(p.id) === String(pid));
            if (found) return found.name || found.title || found.project_name || '';
        }
        if (block && block.project_job_id) {
            const found = (props.userProjects || []).find((p) => String(p.id) === String(block.project_job_id));
            if (found) return found.title || found.name;
        }
    } catch (e) {}
    return '-';
}

function onSelected(payload) {
    const idx = selectorTargetIndex.value;
    const makeLabel2 = (kind, id) => {
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
            status_id: 1,
            company_id: payload.company_id,
            department_id: payload.department_id,
            saving: false,
            linked_assignment_id: null,
            type_label: makeLabel2('types', payload.work_item_type_id),
            size_label: makeLabel2('sizes', payload.size_id),
            stage_label: makeLabel2('stages', payload.stage_id),
            status_label: makeLabel2('statuses', payload.status_id),
        });
    } else {
        const b = assignments.value[idx];
        b.work_item_type_id = payload.work_item_type_id;
        b.size_id = payload.size_id;
        b.stage_id = payload.stage_id || null;
        b.status_id = 1;
        b.company_id = payload.company_id;
        b.department_id = payload.department_id;
        b.type_label = makeLabel2('types', payload.work_item_type_id);
        b.size_label = makeLabel2('sizes', payload.size_id);
        b.stage_label = makeLabel2('stages', payload.stage_id);
        b.status_label = makeLabel2('statuses', payload.status_id);
    }
    selectorTargetIndex.value = null;
}

function companyNameFromId(companyId) {
    if (!companyId) return companyName(companyId);
    const fromList = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    if (fromList && fromList.name) return fromList.name;
    if (page.props && page.props.company && String(page.props.company.id) === String(companyId) && page.props.company.name) {
        return page.props.company.name;
    }
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

function departmentNameFromId(departmentId) {
    if (!departmentId) return departmentName(departmentId);
    const fromList = departmentsFlattened().find((d) => String(d.id) === String(departmentId));
    if (fromList && fromList.name) return fromList.name;
    if (page.props && page.props.department && String(page.props.department.id) === String(departmentId) && page.props.department.name) {
        return page.props.department.name;
    }
    return departmentName(departmentId);
}

function departmentNameFromBlock(block) {
    try {
        if (!block) return departmentNameFromId(null);
        if (block.department && (block.department.name || block.department.department_name)) {
            return block.department.name || block.department.department_name;
        }
        if (block.department_name) return block.department_name;
        if (block.project_job && block.project_job.department) {
            const d = block.project_job.department;
            if (d.name) return d.name;
            if (d.department_name) return d.department_name;
        }
    } catch (e) {}
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

function companyById(companyId) {
    if (!companyId) return null;
    const found = (page.props.companies || []).find((c) => String(c.id) === String(companyId));
    return found || null;
}

function departmentById(departmentId) {
    if (!departmentId) return null;
    const all = departmentsFlattened();
    const found = all.find((d) => String(d.id) === String(departmentId));
    return found || null;
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

/** 選択中ユーザーの雇用形態情報を返す */
function memberEmploymentType(userId) {
    if (!userId) return null;
    const membersList = props.members || [];
    return membersList.find((m) => String(m.id) === String(userId)) ?? null;
}

const EMPLOYMENT_BADGE = {
    dispatch:  { label: '派遣社員', cls: 'bg-orange-100 text-orange-700' },
    outsource: { label: '業務委託', cls: 'bg-purple-100 text-purple-700' },
    contract:  { label: '契約社員', cls: 'bg-green-100 text-green-700' },
};

// ── グループ化ヘルパー ─────────────────────────────────────────────────────

const TYPE_GROUP_ORDER  = ['dtp', 'design', 'proof', 'mgmt', 'sales', 'common'];
const TYPE_GROUP_LABELS = {
    dtp:    '組版・DTP',
    design: 'デザイン・制作',
    proof:  '校正・確認',
    mgmt:   '進行管理・事務',
    sales:  '営業',
    common: '共通',
};
const SIZE_GROUP_ORDER  = ['paper', 'digital'];
const SIZE_GROUP_LABELS = { paper: '紙媒体', digital: 'デジタル' };

// typeFilter: '' = 全部表示、それ以外 = そのグループのみ表示
function typesGrouped(companyId, departmentId, typeFilter) {
    const list     = typesForSelect(companyId, departmentId);
    const filtered = typeFilter ? list.filter((t) => (t.group || 'common') === typeFilter) : list;
    if (typeFilter) {
        // 単一グループ → optgroup 不要のフラット表示用に 1グループとして返す
        return [{ group: typeFilter, label: TYPE_GROUP_LABELS[typeFilter] || typeFilter, items: filtered }];
    }
    const map = {};
    for (const t of filtered) {
        const g = t.group || 'common';
        if (!map[g]) map[g] = [];
        map[g].push(t);
    }
    return TYPE_GROUP_ORDER
        .filter((g) => map[g])
        .map((g) => ({ group: g, label: TYPE_GROUP_LABELS[g] || g, items: map[g] }));
}

// mediumFilter: '' = 全表示、'paper' = 紙媒体のみ、'digital' = デジタルのみ
function sizesGrouped(companyId, departmentId, mediumFilter) {
    const list     = sizesForSelect(companyId, departmentId);
    const filtered = mediumFilter ? list.filter((s) => (s.group || 'paper') === mediumFilter) : list;
    if (mediumFilter) {
        return [{ group: mediumFilter, label: SIZE_GROUP_LABELS[mediumFilter] || mediumFilter, items: filtered }];
    }
    const map = {};
    for (const s of filtered) {
        const g = s.group || 'paper';
        if (!map[g]) map[g] = [];
        map[g].push(s);
    }
    return SIZE_GROUP_ORDER
        .filter((g) => map[g])
        .map((g) => ({ group: g, label: SIZE_GROUP_LABELS[g] || g, items: map[g] }));
}

function typesForSelect(companyId, departmentId) {
    const list = page.props.types || [];
    const auth = effectiveAuthUser();
    const comp = companyId ?? (page.props.company ? page.props.company.id : auth && auth.company_id) ?? '';
    const dept = departmentId ?? (page.props.department ? page.props.department.id : auth && auth.department_id) ?? '';
    const sComp = String(comp ?? '');
    const sDept = String(dept ?? '');
    return list.filter((t) => {
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
    b.type_label = b.work_item_type_id ? itemName('types', b.work_item_type_id) : null;
    b.size_label = b.size_id ? itemName('sizes', b.size_id) : null;
    b.stage_label = b.stage_id ? itemName('stages', b.stage_id) : null;
    b.status_label = b.status_id ? itemName('statuses', b.status_id) : null;
}

function hasDepartment(block) {
    return block && block.department_id !== undefined && block.department_id !== null && String(block.department_id) !== '';
}

function companyDisabled() {
    try {
        const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
        return role !== 'superadmin';
    } catch (e) {
        return false;
    }
}

function departmentDisabled() {
    try {
        const role = effectiveAuthUser() && effectiveAuthUser().user_role ? effectiveAuthUser().user_role : null;
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
    if (props.otherClientId !== null && String(block._client_id) === String(props.otherClientId)) {
        return (props.userProjects || []).filter((p) => props.otherProjectId !== null && String(p.id) === String(props.otherProjectId));
    }
    return (props.userProjects || []).filter((p) => String(p.client_id) === String(block._client_id));
}

function onClientChange(idx) {
    const b = assignments.value[idx];
    if (!b) return;
    if (props.otherClientId !== null && String(b._client_id) === String(props.otherClientId)) {
        b.project_job_id = props.otherProjectId !== null ? String(props.otherProjectId) : '';
    } else {
        b.project_job_id = '';
    }
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
        status_id: 1,
        saving: false,
        linked_assignment_id: null,
        amount_digit_0: '0',
        amount_digit_1: '0',
        amount_digit_2: '0',
        amount_digit_3: '0',
        amounts: 0,
        amounts_unit: 'page',
        project_job: props.projectJob ? { id: props.projectJob.id, title: props.projectJob.title } : null,
        _type_filter: '',
        _medium_filter: 'paper',
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
    if (!a.desired_end_date) return hours.value;
    const today = todayDateStr();
    if (a.desired_end_date !== today) return hours.value;
    const now = new Date();
    const currentHour = String(now.getHours()).padStart(2, '0');
    return hours.value.filter((h) => h >= currentHour);
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

function assembleTitle(a) {
    try {
        if (a?.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
        let t = a?.title_prefix ?? '';
        t = String(t || '').replace(/^\s+|\s+$/g, '');
        t = t.replace(/^.*?[：:]/, '');
        t = t.replace(/^\u300c|\u300d|"|'/g, '').replace(/\u300c|\u300d|"|'$/g, '');
        return t.trim();
    } catch (e) {
        return '';
    }
}

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

function memberCompanyName(userId, block) {
    try {
        const m = memberById(userId);
        if (m) {
            if (m.company && (m.company.name || m.company.company_name)) return m.company.name || m.company.company_name;
            if (m.company_name) return m.company_name;
            if (m.company_id) return companyNameFromId(m.company_id);
        }
    } catch (e) {}
    try {
        if (block) {
            if (block.company && (block.company.name || block.company.company_name)) return block.company.name || block.company.company_name;
            if (block.company_name) return block.company_name;
            if (block.project_job && block.project_job.client && (block.project_job.client.name || block.project_job.client.client_name))
                return block.project_job.client.name || block.project_job.client.client_name;
            if (block.company_id) return companyNameFromId(block.company_id);
        }
    } catch (e) {}
    return companyNameFromId(null);
}

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
    return departmentNameFromId(null);
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

function authCompanyName() {
    try {
        const auth = effectiveAuthUser();
        if (auth) {
            if (auth.company && (auth.company.name || auth.company.company_name)) return auth.company.name || auth.company.company_name;
            if (auth.company_name) return auth.company_name;
            if (auth.company_id) return companyNameFromId(auth.company_id);
        }
    } catch (e) {}
    try {
        if (page.props && page.props.company && page.props.company.name) return page.props.company.name;
    } catch (e) {}
    return companyNameFromId(null);
}

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

const saving = ref(false);

async function save() {
    if (!props.editMode) {
        return;
    }

    if (props.mode === 'coordinator') {
        saving.value = true;

        function assembleTitleCoord(a) {
            if (a.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
            const maybe = a.project_job && (a.project_job.title || a.project_job.name) ? a.project_job.title || a.project_job.name : null;
            if (!maybe) return '';
            if (maybe.includes('：')) return maybe.replace(/^.*：/, '').trim();
            return String(maybe).trim();
        }

        const payload = {
            assignments: assignments.value.map((a) => ({
                title: assembleTitleCoord(a),
                detail: a.detail || '',
                user_id: a.user_id || (effectiveAuthUser() ? effectiveAuthUser().id : null),
                sender_id: effectiveAuthUser() ? effectiveAuthUser().id : null,
                project_job_id: a.project_job_id || null,
                company_id: a.company_id || null,
                department_id: a.department_id || null,
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
                status_id: 1,
                amounts: typeof a.amounts === 'number' ? a.amounts : Number(a.amounts) || 0,
                amounts_unit: a.amounts_unit || 'page',
            })),
        };

        const coordinatorProjectJobId =
            props.projectJob && props.projectJob.id ? props.projectJob.id : payload.assignments[0] ? payload.assignments[0].project_job_id : null;

        if (!coordinatorProjectJobId) {
            alert('プロジェクトが選択されていません。プロジェクトを選択してください。');
            saving.value = false;
            return;
        }

        router.post(
            route('coordinator.project_jobs.assignments.store', { projectJob: coordinatorProjectJobId }),
            payload,
            {
                onFinish: () => { saving.value = false; },
                onError: () => { alert('保存に失敗しました'); },
            }
        );
        return;
    } else {
        // user mode save
        saving.value = true;

        try {
            if (assignments.value && assignments.value.length) {
                const a0 = assignments.value[0];
                if (workDate.value) a0.desired_start_date = workDate.value;
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

        function assembleTitleUser(a) {
            if (a.title_suffix && String(a.title_suffix).trim() !== '') return String(a.title_suffix).trim();
            const maybe = a.project_job && (a.project_job.title || a.project_job.name) ? a.project_job.title || a.project_job.name : null;
            if (!maybe) return '';
            if (maybe.includes('：')) return maybe.replace(/^.*：/, '').trim();
            return String(maybe).trim();
        }

        const payload = {
            assignments: assignments.value.map((a) => ({
                id: a.id || null,
                title: assembleTitleUser(a),
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
            const firstAssignmentHasId = payload.assignments[0] && payload.assignments[0].id;
            if (firstAssignmentHasId) {
                const assignmentId = payload.assignments[0].id;
                const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
                const xsrf = match ? decodeURIComponent(match[1]) : null;
                if (allForAuth) {
                    const url = route('user.project_jobs.assignments.update', { projectJob: computedProjectJobId, assignment: assignmentId });
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
                const url = route('user.project_jobs.assignments.store', { projectJob: computedProjectJobId });
                const rel =
                    typeof window !== 'undefined' && url && url.indexOf(window.location.origin) === 0 ? url.replace(window.location.origin, '') : url;

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
}
</script>

<style scoped>
/* small styles (none) */
</style>
