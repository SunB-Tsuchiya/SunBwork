<script setup>
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import MyJobSourcePicker from '@/Components/MyJobSourcePicker.vue';
import { computed, reactive, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import { treeRows } from '@/utils/broadProjectTree';
import { workCategories, workActivities } from '@/Constants/personalWorkActivities';

const props = defineProps({
    show: { type: Boolean, default: false },
    entryPoint: { type: String, default: 'calendar' },
    date: { type: String, default: '' },
    startMin: { type: Number, default: null },
    endMin: { type: Number, default: null },
    entry: { type: Object, default: null },
    continuationSource: { type: Object, default: null },
    reuseEntryId: { type: Number, default: null },
});
const emit = defineEmits(['close', 'saved', 'deleted']);
const isMyJob = computed(() => props.entryPoint === 'myjob');
const requestToken = ref('');
const sourcePickerOpen = ref(false);
const historyChoice = ref(null);
const page = usePage();
const initialWorkCategory = ref('other');
const categoryTouched = ref(false);
function changeWorkCategory() {
    categoryTouched.value = true;
    form.activity_type = '';
}
const activeContinuation = computed(() => historyChoice.value?.kind === 'continue'
    ? { type: historyChoice.value.type, id: historyChoice.value.id } : props.continuationSource);
const canSelectSource = computed(() => !props.entry?.id && !props.continuationSource
    && (isMyJob.value || page.props.userWorkHub?.enabled));
const canCreateLegacy = computed(() => canSelectSource.value && !props.entry && !historyChoice.value && !props.reuseEntryId);
const heading = computed(() => activeContinuation.value ? '続きのマイジョブを作成' : props.entry?.id ? '作業予定を編集' : (isMyJob.value ? 'マイジョブ作成' : '簡単な作業予定を追加'));
const projects = ref([]);
const broadProjects = ref([]);
const expandedBroadProjects = ref(new Set());
const recentUnits = ref([]);
const recentTitles = ref([]);
const showTitleSuggestions = ref(false);
const projectSearch = ref('');
const selectedProject = ref(null);
const selectedBroadProject = ref(null);
const projectPickerOpen = ref(false);
const includeCompleted = ref(false);
const showMore = ref(false);
let searchTimer;
let searchRequest = 0;
const saving = ref(false);
const handingOff = ref(false);
const loading = ref(false);
const error = ref('');
const form = reactive({
    work_date: '', work_category: 'other', activity_type: '', plan_slot: 'hour',
    planned_start_time: '', planned_end_time: '',
    project_job_id: '', broad_project_id: '', project_name_text: '', title: '', entry_visibility: 'department',
    actual_quantity: '', actual_unit: '', result_note: '',
});
const categories = workCategories;
const unitSuggestions = computed(() => [...new Set([...recentUnits.value, 'ページ', 'ファイル', '件', '枚', '点', '冊'])]);
const titleSuggestions = computed(() => recentTitles.value
    .filter((item) => item.title.includes(form.title.trim()))
    .sort((a, b) => Number(b.project_job_id && String(b.project_job_id) === String(form.project_job_id))
        - Number(a.project_job_id && String(a.project_job_id) === String(form.project_job_id)))
    .slice(0, 8));
const time = (minutes) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
const projectLabel = (project) => [project.title, project.jobcode, project.client_name].filter(Boolean).join(' / ');
const broadProjectLabel = (project, full = false) => [project.path || project.name, project.display_clients?.length ? (full ? project.display_clients.map(client => client.name).join('・') : `${project.display_clients.slice(0, 2).map(client => client.name).join('・')}${project.display_clients.length > 2 ? `・ほか${project.display_clients.length - 2}社` : ''}`) : project.client_name, !project.path && project.fiscal_year ? `${project.fiscal_year}年度` : null].filter(Boolean).join(' / ');
const matchingBroadProjects = computed(() => broadProjects.value.filter(project => {
    const words = projectSearch.value.normalize('NFKC').trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
    return words.every(word => broadProjectLabel(project, true).normalize('NFKC').toLocaleLowerCase().includes(word));
}));
const broadProjectGroups = computed(() => [
    {key: 'favorites', label: 'お気に入りの大まかな案件', items: matchingBroadProjects.value.filter(p => p.is_favorite)},
].filter(group => group.items.length));
const broadTreeRows = computed(() => treeRows(broadProjects.value.map(project => ({
    ...project, selectable: project.is_active, client: { name: project.client_name || '' },
})), projectSearch.value, true, expandedBroadProjects.value));
function toggleBroadProject(id) {
    const next = new Set(expandedBroadProjects.value);
    if (next.has(id)) next.delete(id); else next.add(id);
    expandedBroadProjects.value = next;
}
const projectGroups = computed(() => [
    { key: 'team', label: '担当・チーム案件' },
    { key: 'recent', label: '最近使った案件・過去のマイジョブ' },
    { key: 'search', label: 'その他の検索結果' },
].map(group => ({ ...group, items: projects.value.filter(project => project.group === group.key) })).filter(group => group.items.length));

async function loadProjects(applyDefaultCategory = false) {
    const request = ++searchRequest;
    loading.value = true;
    try {
        const { data } = await axios.get(route('schedule.department_work.options'), {
            params: {
                q: projectSearch.value,
                selected_id: form.project_job_id || undefined,
                selected_broad_id: form.broad_project_id || undefined,
                include_completed: includeCompleted.value ? 1 : 0,
            },
        });
        if (request !== searchRequest) return;
        projects.value = data.projects || [];
        broadProjects.value = data.broad_projects || [];
        recentUnits.value = data.recent_units || [];
        recentTitles.value = data.recent_titles || [];
        selectedProject.value = projects.value.find(project => String(project.id) === String(form.project_job_id)) || selectedProject.value;
        selectedBroadProject.value = broadProjects.value.find(project => String(project.id) === String(form.broad_project_id)) || selectedBroadProject.value;
        if (applyDefaultCategory && !props.entry && !props.continuationSource && !props.reuseEntryId && !historyChoice.value && !categoryTouched.value) {
            initialWorkCategory.value = data.default_category || 'other';
            form.work_category = initialWorkCategory.value;
        }
    } catch {
        if (request === searchRequest) error.value = '案件候補を読み込めませんでした。';
    } finally {
        if (request === searchRequest) loading.value = false;
    }
}

watch(projectSearch, () => {
    if (!props.show || !projectPickerOpen.value) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadProjects, 250);
});
watch(includeCompleted, () => {
    if (props.show && projectPickerOpen.value) loadProjects();
});

function openProjectPicker() {
    expandedBroadProjects.value = new Set();
    projectSearch.value = '';
    includeCompleted.value = false;
    projectPickerOpen.value = true;
    loadProjects();
}

function chooseProject(project) {
    form.project_job_id = project.id;
    form.broad_project_id = '';
    selectedProject.value = project;
    selectedBroadProject.value = null;
    projectSearch.value = '';
    projectPickerOpen.value = false;
}

function chooseBroadProject(project) {
    form.broad_project_id = project.id;
    form.project_job_id = '';
    selectedBroadProject.value = project;
    selectedProject.value = null;
    projectSearch.value = '';
    projectPickerOpen.value = false;
}

function clearProject() {
    form.project_job_id = '';
    form.broad_project_id = '';
    selectedProject.value = null;
    selectedBroadProject.value = null;
    projectSearch.value = '';
    projectPickerOpen.value = false;
    loadProjects();
}

watch(() => props.show, async (show) => {
    if (!show) { showTitleSuggestions.value = false; projectPickerOpen.value = false; return; }
    error.value = '';
    sourcePickerOpen.value = false; historyChoice.value = null; handingOff.value = false;
    clearTimeout(searchTimer);
    projectSearch.value = '';
    selectedProject.value = null;
    selectedBroadProject.value = null;
    projectPickerOpen.value = false;
    includeCompleted.value = false;
    categoryTouched.value = false;
    initialWorkCategory.value = 'other';
    form.work_date = props.entry?.work_date || props.date;
    form.work_category = props.entry?.work_category || 'other';
    form.activity_type = props.entry?.activity_type || '';
    form.plan_slot = props.entry?.plan_slot || 'hour';
    form.planned_start_time = props.entry?.planned_start_time || (props.startMin !== null ? time(props.startMin) : '');
    form.planned_end_time = props.entry?.planned_end_time || (props.endMin !== null && props.endMin < 1440 ? time(props.endMin) : '');
    form.project_job_id = props.entry?.project_job_id || '';
    form.broad_project_id = props.entry?.broad_project_id || '';
    form.project_name_text = props.entry?.project_name_text || '';
    form.title = props.entry?.title || '';
    form.entry_visibility = props.entry?.entry_visibility || 'department';
    form.actual_quantity = props.entry?.actual_quantity ?? '';
    form.actual_unit = props.entry?.actual_unit || 'ページ';
    form.result_note = props.entry?.result_note || '';
    showMore.value = Boolean(form.result_note || form.activity_type || form.entry_visibility === 'private');
    if (props.continuationSource) {
        form.work_date = props.date || props.entry?.work_date || '';
        form.plan_slot = 'hour';
        form.planned_start_time = props.startMin !== null ? time(props.startMin) : '';
        form.planned_end_time = props.endMin !== null && props.endMin < 1440 ? time(props.endMin) : '';
        form.actual_quantity = ''; form.result_note = '';
        requestToken.value = globalThis.crypto.randomUUID();
        if (form.project_job_id && props.entry?.project_label) selectedProject.value = { id: form.project_job_id, title: props.entry.project_label };
        showMore.value = Boolean(form.activity_type || form.entry_visibility === 'private');
    }
    await loadProjects(true);
});

async function chooseHistory({ record, kind }) {
    if (saving.value || handingOff.value || !['new', 'continue'].includes(kind)) return;
    const type = record.source_type === 'entry' ? 'entry' : 'assignment';
    const seed = type === 'entry' ? record.work_entry : record.simple_prefill;
    if (!seed) { error.value = 'この仕事は引用できません。ほかの仕事を選んでください。'; return; }
    historyChoice.value = { type, id: record.id, kind };
    Object.assign(form, {
        work_category: seed.work_category || 'other', activity_type: seed.activity_type || '', plan_slot: 'hour',
        title: seed.title || '', project_job_id: seed.project_job_id || '', broad_project_id: seed.broad_project_id || '',
        project_name_text: seed.project_name_text || '', entry_visibility: seed.entry_visibility || 'department',
        actual_quantity: '', actual_unit: seed.actual_unit || 'ページ', result_note: '',
    });
    selectedProject.value = seed.project_job_id ? {id: seed.project_job_id, title: seed.project_label || record.project_job_name} : null;
    selectedBroadProject.value = null;
    showMore.value = Boolean(form.activity_type || form.entry_visibility === 'private');
    requestToken.value = globalThis.crypto.randomUUID();
    sourcePickerOpen.value = false; error.value = '';
    await loadProjects();
}

function openLegacyContinuation() {
    const source = activeContinuation.value;
    if (saving.value || handingOff.value || !source || (source.type && source.type !== 'entry')) return;
    const params = { date: form.work_date, continue_entry: source.id };
    for (const [value, prefix] of [[form.planned_start_time, 'start'], [form.planned_end_time, 'end']]) {
        if (/^\d{2}:\d{2}$/.test(value || '')) {
            params[prefix + 'Hour'] = Number(value.slice(0, 2)); params[prefix + 'Minute'] = Number(value.slice(3));
        }
    }
    handingOff.value = true;
    router.get(route('events.create_job'), params, { onFinish: () => { handingOff.value = false; } });
}

function openLegacyCreate() {
    if (!canCreateLegacy.value || saving.value || handingOff.value) return;
    const hasOtherInput = form.title.trim() || form.project_job_id || form.broad_project_id || form.project_name_text.trim()
        || form.actual_quantity !== '' || form.actual_unit !== 'ページ' || form.result_note.trim() || form.activity_type
        || form.entry_visibility !== 'department' || form.work_category !== initialWorkCategory.value;
    if (hasOtherInput && !confirm('日付・開始終了時刻を引き継いで旧形式を開きます。案件・作業名・数量など、ほかの入力内容は引き継がれません。切り替えますか？')) return;
    const params = { date: form.work_date || props.date || new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' }) };
    for (const [value, prefix] of [[form.planned_start_time, 'start'], [form.planned_end_time, 'end']]) {
        if (/^\d{2}:\d{2}$/.test(value || '')) {
            params[prefix + 'Hour'] = Number(value.slice(0, 2)); params[prefix + 'Minute'] = Number(value.slice(3));
        }
    }
    handingOff.value = true;
    router.get(route('events.create_job'), params, { onFinish: () => { handingOff.value = false; } });
}

function openRequests() {
    if (saving.value || handingOff.value) return;
    const params = { date: form.work_date, open: 'request' };
    for (const [value, prefix] of [[form.planned_start_time, 'start'], [form.planned_end_time, 'end']]) {
        if (/^\d{2}:\d{2}$/.test(value || '')) {
            params[prefix + 'Hour'] = value.slice(0, 2); params[prefix + 'Minute'] = value.slice(3);
        }
    }
    handingOff.value = true;
    router.get(route('events.create_job'), params, { onFinish: () => { handingOff.value = false; } });
}

async function save() {
    if (saving.value || handingOff.value) return;
    saving.value = true;
    error.value = '';
    try {
        const payload = {
            ...form, ...(historyChoice.value?.kind === 'new' ? { [historyChoice.value.type === 'entry' ? 'reuse_entry_id' : 'reuse_assignment_id']: historyChoice.value.id } : (!historyChoice.value && props.reuseEntryId ? { reuse_entry_id: props.reuseEntryId } : {})), project_job_id: form.project_job_id || null, broad_project_id: form.broad_project_id || null,
            actual_quantity: form.actual_quantity === '' ? null : form.actual_quantity,
            actual_unit: form.actual_quantity === '' && !activeContinuation.value && !historyChoice.value ? null : form.actual_unit,
        };
        let response;
        if (activeContinuation.value) {
            response = await axios.post(route(activeContinuation.value.type === 'assignment' ? 'user.myjobbox.work_assignments.continue' : 'user.myjobbox.entries.continue', activeContinuation.value.type === 'assignment' ? { assignment: activeContinuation.value.id } : { entry: activeContinuation.value.id }), { ...payload, request_token: requestToken.value });
        } else if (props.entry?.id) {
            response = await axios.put(route(isMyJob.value ? 'user.myjobbox.entries.update' : 'schedule.department_work.update', { entry: props.entry.id }), payload);
        } else {
            response = await axios.post(route(isMyJob.value || historyChoice.value ? 'user.myjobbox.unified_store' : 'schedule.department_work.store'), payload);
        }
        emit('saved', form.work_date, response.data.id);
    } catch (err) {
        const first = Object.values(err.response?.data?.errors || {})[0];
        error.value = first?.[0] || err.response?.data?.message || '保存できませんでした。';
    } finally {
        saving.value = false;
    }
}

async function remove() {
    if (props.continuationSource || !props.entry?.id || !confirm('この作業予定を削除しますか？')) return;
    saving.value = true;
    error.value = '';
    try {
        await axios.delete(route(isMyJob.value ? 'user.myjobbox.entries.destroy' : 'schedule.department_work.destroy', { entry: props.entry.id }));
        if (isMyJob.value) emit('deleted', form.work_date);
        else emit('saved', form.work_date);
    } catch (err) {
        error.value = err.response?.data?.message || '削除できませんでした。';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40 px-4" @click.self="!saving && !handingOff && emit('close')">
            <div role="dialog" aria-modal="true" :aria-label="heading" :class="sourcePickerOpen ? 'max-w-3xl' : 'max-w-lg'" class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ heading }}</h2>
                    <button type="button" class="text-sm text-gray-500 hover:underline" @click="!saving && !handingOff && emit('close')">閉じる</button>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ isMyJob ? '日付と開始・終了時刻を入力して作業を登録します。登録した作業は、このBOXとカレンダーに表示されます。' : 'カレンダーで選んだ時刻を、その日の作業予定として保存します。あとから時刻や内容を直せます。' }}</p>
                <p v-if="error" class="mt-3 rounded bg-red-50 p-2 text-sm text-red-700" role="alert">{{ error }}</p>
                <div v-if="canSelectSource && !sourcePickerOpen" class="mt-3 flex flex-wrap gap-2 border-b pb-3">
                    <button type="button" :disabled="saving || handingOff" class="rounded border bg-gray-50 px-3 py-1.5 text-sm text-gray-700 disabled:opacity-50" @click="sourcePickerOpen = true">過去データから流用</button>
                    <button type="button" :disabled="saving || handingOff" class="rounded border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-700 disabled:opacity-50" @click="openRequests">{{ handingOff ? '移動中…' : '受けた依頼から登録' }}</button>
                    <button v-if="canCreateLegacy" type="button" :disabled="saving || handingOff" class="ml-auto rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50" title="日付・開始終了時刻を引き継いで旧形式を開きます" @click="openLegacyCreate">旧形式で作成</button>
                </div>
                <MyJobSourcePicker v-if="sourcePickerOpen" mode="past" :simple-input="true" class="mt-4" @cancel="sourcePickerOpen = false" @selected="chooseHistory" />
                <p v-if="historyChoice && !sourcePickerOpen" class="mt-3 rounded bg-indigo-50 p-2 text-sm text-indigo-800">{{ historyChoice.kind === 'continue' ? '選択した仕事の続きとして登録します。' : '元の仕事とは独立した仕事として登録します。' }}</p>
                <form v-show="!sourcePickerOpen" class="mt-4 space-y-3" @submit.prevent="save">
                    <label class="block text-sm">日付<input v-model="form.work_date" type="date" required class="mt-1 w-full rounded border p-2" /></label>
                    <div v-if="entry && form.plan_slot !== 'hour'" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        この記録は以前の「{{ { am: '午前', pm: '午後', full: '終日' }[form.plan_slot] || '予定枠' }}」形式です。
                        <button type="button" class="ml-1 underline" @click="form.plan_slot = 'hour'">開始・終了時刻を指定する</button>
                    </div>
                    <div v-if="form.plan_slot === 'hour'" class="grid grid-cols-2 gap-3">
                        <label class="text-sm">開始時刻<input v-model="form.planned_start_time" type="time" step="60" required class="mt-1 w-full rounded border p-2" /></label>
                        <label class="text-sm">終了時刻<input v-model="form.planned_end_time" type="time" step="60" required class="mt-1 w-full rounded border p-2" /></label>
                    </div>
                    <label class="block text-sm">大まかな作業区分
                        <select v-model="form.work_category" required class="mt-1 w-full rounded border p-2" @change="changeWorkCategory"><option v-for="[key, label] in categories" :key="key" :value="key">{{ label }}</option></select>
                    </label>
                    <div class="text-sm">
                        <span class="block">案件（任意）</span>
                        <div v-if="selectedProject" class="mt-1 flex items-center justify-between gap-2 rounded border border-blue-200 bg-blue-50 p-2">
                            <span class="min-w-0 truncate">{{ projectLabel(selectedProject) }}</span>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" class="text-blue-700 underline" @click="openProjectPicker">変更</button>
                                <button type="button" class="text-blue-700 underline" @click="clearProject">解除</button>
                            </div>
                        </div>
                        <div v-else-if="selectedBroadProject" class="mt-1 flex items-center justify-between gap-2 rounded border border-yellow-200 bg-yellow-50 p-2">
                            <span class="min-w-0 truncate">大まかな案件：{{ broadProjectLabel(selectedBroadProject) }}</span>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" class="text-blue-700 underline" @click="openProjectPicker">変更</button>
                                <button type="button" class="text-blue-700 underline" @click="clearProject">解除</button>
                            </div>
                        </div>
                        <button v-else type="button" class="mt-1 w-full rounded border border-blue-300 bg-blue-50 px-3 py-2 text-left text-blue-800 hover:bg-blue-100" @click="openProjectPicker">案件を探す</button>
                    </div>
                    <label v-if="!form.project_job_id && !form.broad_project_id" class="block text-sm">案件がまだない場合の名前（任意）<input v-model="form.project_name_text" type="text" maxlength="255" placeholder="例：実力チェックテスト" class="mt-1 w-full rounded border p-2" /></label>
                    <div class="relative text-sm">
                        <label for="quick-work-title" class="block">作業名（任意）</label>
                        <input id="quick-work-title" v-model="form.title" type="text" maxlength="255" autocomplete="off" aria-autocomplete="list" :aria-expanded="showTitleSuggestions && titleSuggestions.length > 0" aria-controls="quick-work-title-suggestions" class="mt-1 w-full rounded border p-2" @focus="showTitleSuggestions = true" @input="showTitleSuggestions = true" @keydown.esc="showTitleSuggestions = false" @blur="showTitleSuggestions = false" />
                        <div v-if="showTitleSuggestions && titleSuggestions.length" id="quick-work-title-suggestions" role="listbox" class="absolute z-30 mt-1 max-h-44 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                            <p class="px-3 py-1 text-xs text-gray-500">以前入力した作業名</p>
                            <button v-for="item in titleSuggestions" :key="item.title" type="button" role="option" class="block w-full px-3 py-2 text-left hover:bg-pink-50" @pointerdown.prevent="form.title = item.title; showTitleSuggestions = false">{{ item.title }}</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm">ページ数・数量（任意）<input v-model="form.actual_quantity" type="number" min="0" max="99999999" step="0.01" class="mt-1 w-full rounded border p-2" placeholder="例：100" /></label>
                        <label class="text-sm">単位<input v-model="form.actual_unit" type="text" maxlength="30" :required="form.actual_quantity !== ''" class="mt-1 w-full rounded border p-2" placeholder="ページ・件など" /></label>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs" aria-label="単位の候補">
                        <span class="text-gray-500">単位を選択・自由入力：</span>
                        <button v-for="unit in unitSuggestions" :key="unit" type="button" :aria-pressed="form.actual_unit === unit" class="rounded border px-2 py-1" :class="form.actual_unit === unit ? 'border-blue-400 bg-blue-50 text-blue-800' : 'border-gray-300 text-gray-600 hover:bg-gray-50'" @click="form.actual_unit = unit">{{ unit }}</button>
                    </div>
                    <label class="block text-sm">作業種類（任意）
                        <select v-model="form.activity_type" class="mt-1 w-full rounded border p-2"><option value="">未指定</option><option v-for="[key, label] in workActivities[form.work_category] || workActivities.other" :key="key" :value="key">{{ label }}</option></select>
                    </label>
                    <button type="button" class="text-sm text-blue-700 underline" :aria-expanded="showMore" @click="showMore = !showMore">
                        {{ showMore ? '詳しい項目を閉じる' : '進みなどを入力する（任意）' }}
                    </button>
                    <div v-if="showMore" class="space-y-3 rounded-lg border border-pink-200 bg-pink-50/50 p-3">
                        <label class="block text-sm">今日の進み・結果（任意）<textarea v-model="form.result_note" rows="2" maxlength="2000" class="mt-1 w-full rounded border p-2" placeholder="例：初校を提出、確認待ち"></textarea></label>
                        <label class="block text-sm">部署一覧での表示
                            <select v-model="form.entry_visibility" class="mt-1 w-full rounded border p-2"><option value="department">内容を表示</option><option value="private">内容を非公開</option></select>
                        </label>
                    </div>
                    <div v-if="activeContinuation && (!activeContinuation.type || activeContinuation.type === 'entry')" class="rounded border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
                        <button type="button" :disabled="saving || handingOff" class="font-semibold underline disabled:opacity-50" @click="openLegacyContinuation">日時を引き継いで旧形式で続きを入力</button>
                        <p class="mt-1">詳細な項目を使う場合に選んでください。元の仕事を残し、同じ系列に新しい作業を登録します。</p>
                    </div>
                    <div class="flex justify-between border-t pt-3">
                        <button v-if="entry?.id && !entry.diary_id && !continuationSource" type="button" :disabled="saving || handingOff" class="text-sm text-red-700 hover:underline" @click="remove">削除</button><span v-else></span>
                        <button type="submit" :disabled="saving || handingOff" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{{ saving ? '保存中...' : '保存' }}</button>
                    </div>
                </form>
            </div>
        </div>
        <div v-if="show && projectPickerOpen" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 px-4" @click.self="projectPickerOpen = false">
            <div role="dialog" aria-modal="true" aria-label="案件を探す" class="max-h-[80vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold">案件を探す</h3>
                    <button type="button" class="text-sm text-gray-600 underline" @click="projectPickerOpen = false">閉じる</button>
                </div>
                <label for="quick-work-project-search" class="mt-4 block text-sm">大まかな案件名・案件名・番号・取引先名
                    <input id="quick-work-project-search" v-model="projectSearch" type="search" autocomplete="off" placeholder="入力して検索" class="mt-1 w-full rounded border p-2" />
                </label>
                <label class="mt-3 flex items-center gap-2 text-sm"><input v-model="includeCompleted" type="checkbox" />完了した案件も表示する</label>
                <div class="mt-3 max-h-80 overflow-y-auto rounded border" aria-label="案件候補">
                    <p v-if="loading" class="p-3 text-sm text-gray-500">検索中...</p>
                    <p v-else-if="!projectGroups.length && !matchingBroadProjects.length" class="p-3 text-sm text-gray-500">該当する案件がありません。閉じて仮の案件名を入力できます。</p>
                    <div v-for="group in broadProjectGroups" :key="group.key">
                        <p class="bg-yellow-50 px-3 py-2 text-xs font-semibold text-yellow-800">{{ group.label }}</p>
                        <button v-for="project in group.items" :key="project.id" type="button" class="flex w-full items-center justify-between gap-3 border-t px-3 py-2 text-left text-sm hover:bg-yellow-50" @click="chooseBroadProject(project)">
                            <span>{{ broadProjectLabel(project) }}</span>
                            <span v-if="!project.is_active" class="shrink-0 text-xs text-gray-500">休止中</span>
                        </button>
                    </div>
                    <div v-if="broadTreeRows.length">
                        <p class="bg-yellow-50 px-3 py-2 text-xs font-semibold text-yellow-800">大まかな案件（{{ matchingBroadProjects.length }}件）</p>
                        <div v-for="project in broadTreeRows" :key="project.id" class="flex items-center gap-1 border-t px-2 py-1" :class="{ 'bg-gray-50': project.depth === 0, 'pl-8': project.depth > 0 }">
                            <button v-if="project.childCount" type="button" class="h-8 w-8 shrink-0 rounded border bg-white text-lg text-blue-700" :disabled="Boolean(projectSearch.trim())" :title="projectSearch.trim() ? '検索中は一致する分類を展開します' : '中グループを開閉'" :aria-expanded="project.open" :aria-label="project.name + (project.open ? 'を閉じる' : 'を開く')" @click="toggleBroadProject(project.id)">{{ project.open ? '−' : '+' }}</button>
                            <span v-else class="w-8 shrink-0"></span>
                            <button type="button" class="min-w-0 flex-1 rounded px-2 py-2 text-left text-sm hover:bg-yellow-50" @click="chooseBroadProject(project)">
                                {{ broadProjectLabel(project) }}<span v-if="project.childCount" class="ml-1 text-xs text-gray-500">（{{ project.childCount }}）</span>
                            </button>
                        </div>
                    </div>
                    <div v-for="group in projectGroups" :key="group.key">
                        <p class="bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600">{{ group.label }}</p>
                        <button v-for="project in group.items" :key="project.id" type="button" class="flex w-full items-center justify-between gap-3 border-t px-3 py-2 text-left text-sm hover:bg-blue-50" @click="chooseProject(project)">
                            <span>{{ projectLabel(project) }}</span>
                            <span v-if="project.completed" class="shrink-0 text-xs text-gray-500">完了</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
