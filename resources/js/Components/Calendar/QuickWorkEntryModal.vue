<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import { workCategories, workActivities } from '@/Constants/personalWorkActivities';

const props = defineProps({
    show: { type: Boolean, default: false },
    date: { type: String, default: '' },
    startMin: { type: Number, default: null },
    endMin: { type: Number, default: null },
    entry: { type: Object, default: null },
});
const emit = defineEmits(['close', 'saved']);
const projects = ref([]);
const recentUnits = ref([]);
const recentTitles = ref([]);
const showTitleSuggestions = ref(false);
const projectSearch = ref('');
const selectedProject = ref(null);
const projectPickerOpen = ref(false);
const includeCompleted = ref(false);
const showMore = ref(false);
let searchTimer;
let searchRequest = 0;
const saving = ref(false);
const loading = ref(false);
const error = ref('');
const form = reactive({
    work_date: '', work_category: 'other', activity_type: '', plan_slot: 'hour',
    planned_start_time: '', planned_end_time: '',
    project_job_id: '', project_name_text: '', title: '', entry_visibility: 'department',
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
                include_completed: includeCompleted.value ? 1 : 0,
            },
        });
        if (request !== searchRequest) return;
        projects.value = data.projects || [];
        recentUnits.value = data.recent_units || [];
        recentTitles.value = data.recent_titles || [];
        selectedProject.value = projects.value.find(project => String(project.id) === String(form.project_job_id)) || selectedProject.value;
        if (applyDefaultCategory && !props.entry) form.work_category = data.default_category || 'other';
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
    projectSearch.value = '';
    includeCompleted.value = false;
    projectPickerOpen.value = true;
    loadProjects();
}

function chooseProject(project) {
    form.project_job_id = project.id;
    selectedProject.value = project;
    projectSearch.value = '';
    projectPickerOpen.value = false;
}

function clearProject() {
    form.project_job_id = '';
    selectedProject.value = null;
    projectSearch.value = '';
    projectPickerOpen.value = false;
    loadProjects();
}

watch(() => props.show, async (show) => {
    if (!show) { showTitleSuggestions.value = false; projectPickerOpen.value = false; return; }
    error.value = '';
    clearTimeout(searchTimer);
    projectSearch.value = '';
    selectedProject.value = null;
    projectPickerOpen.value = false;
    includeCompleted.value = false;
    form.work_date = props.entry?.work_date || props.date;
    form.work_category = props.entry?.work_category || 'other';
    form.activity_type = props.entry?.activity_type || '';
    form.plan_slot = props.entry?.plan_slot || 'hour';
    form.planned_start_time = props.entry?.planned_start_time || (props.startMin !== null ? time(props.startMin) : '');
    form.planned_end_time = props.entry?.planned_end_time || (props.endMin !== null && props.endMin < 1440 ? time(props.endMin) : '');
    form.project_job_id = props.entry?.project_job_id || '';
    form.project_name_text = props.entry?.project_name_text || '';
    form.title = props.entry?.title || '';
    form.entry_visibility = props.entry?.entry_visibility || 'department';
    form.actual_quantity = props.entry?.actual_quantity ?? '';
    form.actual_unit = props.entry?.actual_unit || '';
    form.result_note = props.entry?.result_note || '';
    showMore.value = Boolean(form.actual_quantity !== '' || form.actual_unit || form.result_note || form.activity_type || form.entry_visibility === 'private');
    await loadProjects(true);
});

async function save() {
    saving.value = true;
    error.value = '';
    try {
        const payload = {
            ...form, project_job_id: form.project_job_id || null,
            actual_quantity: form.actual_quantity === '' ? null : form.actual_quantity,
        };
        if (props.entry?.id) {
            await axios.put(route('schedule.department_work.update', { entry: props.entry.id }), payload);
        } else {
            await axios.post(route('schedule.department_work.store'), payload);
        }
        emit('saved', form.work_date);
    } catch (err) {
        const first = Object.values(err.response?.data?.errors || {})[0];
        error.value = first?.[0] || err.response?.data?.message || '保存できませんでした。';
    } finally {
        saving.value = false;
    }
}

async function remove() {
    if (!props.entry?.id || !confirm('この作業予定を削除しますか？')) return;
    saving.value = true;
    error.value = '';
    try {
        await axios.delete(route('schedule.department_work.destroy', { entry: props.entry.id }));
        emit('saved', form.work_date);
    } catch (err) {
        error.value = err.response?.data?.message || '削除できませんでした。';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40 px-4" @click.self="emit('close')">
            <div role="dialog" aria-modal="true" aria-label="簡単な作業予定" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ entry ? '作業予定を編集' : '簡単な作業予定を追加' }}</h2>
                    <button type="button" class="text-sm text-gray-500 hover:underline" @click="emit('close')">閉じる</button>
                </div>
                <p class="mt-1 text-xs text-gray-500">カレンダーで選んだ時刻を、その日の作業予定として保存します。あとから時刻や内容を直せます。</p>
                <p v-if="error" class="mt-3 rounded bg-red-50 p-2 text-sm text-red-700" role="alert">{{ error }}</p>
                <form class="mt-4 space-y-3" @submit.prevent="save">
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
                        <select v-model="form.work_category" required class="mt-1 w-full rounded border p-2" @change="form.activity_type = ''"><option v-for="[key, label] in categories" :key="key" :value="key">{{ label }}</option></select>
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
                        <button v-else type="button" class="mt-1 w-full rounded border border-blue-300 bg-blue-50 px-3 py-2 text-left text-blue-800 hover:bg-blue-100" @click="openProjectPicker">案件を探す</button>
                    </div>
                    <label v-if="!form.project_job_id" class="block text-sm">案件がまだない場合の名前（任意）<input v-model="form.project_name_text" type="text" maxlength="255" placeholder="例：実力チェックテスト" class="mt-1 w-full rounded border p-2" /></label>
                    <div class="relative text-sm">
                        <label for="quick-work-title" class="block">作業名（任意）</label>
                        <input id="quick-work-title" v-model="form.title" type="text" maxlength="255" autocomplete="off" aria-autocomplete="list" :aria-expanded="showTitleSuggestions && titleSuggestions.length > 0" aria-controls="quick-work-title-suggestions" class="mt-1 w-full rounded border p-2" @focus="showTitleSuggestions = true" @input="showTitleSuggestions = true" @keydown.esc="showTitleSuggestions = false" @blur="showTitleSuggestions = false" />
                        <div v-if="showTitleSuggestions && titleSuggestions.length" id="quick-work-title-suggestions" role="listbox" class="absolute z-30 mt-1 max-h-44 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                            <p class="px-3 py-1 text-xs text-gray-500">以前入力した作業名</p>
                            <button v-for="item in titleSuggestions" :key="item.title" type="button" role="option" class="block w-full px-3 py-2 text-left hover:bg-pink-50" @pointerdown.prevent="form.title = item.title; showTitleSuggestions = false">{{ item.title }}</button>
                        </div>
                    </div>
                    <button type="button" class="text-sm text-blue-700 underline" :aria-expanded="showMore" @click="showMore = !showMore">
                        {{ showMore ? '詳しい項目を閉じる' : '数量・進みなどを入力する（任意）' }}
                    </button>
                    <div v-if="showMore" class="space-y-3 rounded-lg border border-pink-200 bg-pink-50/50 p-3">
                        <label class="block text-sm">作業種類（任意）
                            <select v-model="form.activity_type" class="mt-1 w-full rounded border p-2"><option value="">未指定</option><option v-for="[key, label] in workActivities[form.work_category] || workActivities.other" :key="key" :value="key">{{ label }}</option></select>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm">数量<input v-model="form.actual_quantity" type="number" min="0" max="99999999" step="0.01" class="mt-1 w-full rounded border p-2" placeholder="例：100" /></label>
                            <label class="text-sm">単位<input v-model="form.actual_unit" type="text" list="quick-work-unit-suggestions" maxlength="30" :required="form.actual_quantity !== ''" class="mt-1 w-full rounded border p-2" placeholder="ページ・件など" /></label>
                        </div>
                        <datalist id="quick-work-unit-suggestions"><option v-for="unit in unitSuggestions" :key="unit" :value="unit" /></datalist>
                        <div class="flex flex-wrap gap-1.5" aria-label="よく使う単位">
                            <button v-for="unit in unitSuggestions.slice(0, 6)" :key="unit" type="button" class="rounded-full border border-pink-200 bg-white px-2 py-0.5 text-xs text-pink-800 hover:bg-pink-100" @click="form.actual_unit = unit">{{ unit }}</button>
                        </div>
                        <label class="block text-sm">今日の進み・結果（任意）<textarea v-model="form.result_note" rows="2" maxlength="2000" class="mt-1 w-full rounded border p-2" placeholder="例：初校を提出、確認待ち"></textarea></label>
                        <label class="block text-sm">部署一覧での表示
                            <select v-model="form.entry_visibility" class="mt-1 w-full rounded border p-2"><option value="department">内容を表示</option><option value="private">内容を非公開</option></select>
                        </label>
                    </div>
                    <div class="flex justify-between border-t pt-3">
                        <button v-if="entry && !entry.diary_id" type="button" :disabled="saving" class="text-sm text-red-700 hover:underline" @click="remove">削除</button><span v-else></span>
                        <button type="submit" :disabled="saving" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{{ saving ? '保存中...' : '保存' }}</button>
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
                <label for="quick-work-project-search" class="mt-4 block text-sm">案件名・番号・取引先名
                    <input id="quick-work-project-search" v-model="projectSearch" type="search" autocomplete="off" placeholder="入力して検索" class="mt-1 w-full rounded border p-2" />
                </label>
                <label class="mt-3 flex items-center gap-2 text-sm"><input v-model="includeCompleted" type="checkbox" />完了した案件も表示する</label>
                <div class="mt-3 max-h-80 overflow-y-auto rounded border" aria-label="案件候補">
                    <p v-if="loading" class="p-3 text-sm text-gray-500">検索中...</p>
                    <p v-else-if="!projectGroups.length" class="p-3 text-sm text-gray-500">該当する案件がありません。閉じて仮の案件名を入力できます。</p>
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
