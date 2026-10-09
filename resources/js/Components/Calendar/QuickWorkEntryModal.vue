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
let searchTimer;
let searchRequest = 0;
const saving = ref(false);
const loading = ref(false);
const error = ref('');
const form = reactive({
    work_date: '', work_category: 'other', activity_type: '', plan_slot: 'am',
    planned_start_time: '09:00', planned_end_time: '10:00',
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
            params: { q: projectSearch.value, selected_id: form.project_job_id || undefined },
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
    if (!props.show) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadProjects, 250);
});

function chooseProject(project) {
    form.project_job_id = project.id;
    selectedProject.value = project;
    projectSearch.value = '';
}

function clearProject() {
    form.project_job_id = '';
    selectedProject.value = null;
    projectSearch.value = '';
    loadProjects();
}

watch(() => props.show, async (show) => {
    if (!show) { showTitleSuggestions.value = false; return; }
    error.value = '';
    clearTimeout(searchTimer);
    projectSearch.value = '';
    selectedProject.value = null;
    form.work_date = props.entry?.work_date || props.date;
    form.work_category = props.entry?.work_category || 'other';
    form.activity_type = props.entry?.activity_type || '';
    form.plan_slot = props.entry?.plan_slot || (props.startMin !== null && props.endMin !== null && props.endMin < 1440 ? 'hour' : 'am');
    form.planned_start_time = props.entry?.planned_start_time || (props.startMin !== null ? time(props.startMin) : '09:00');
    form.planned_end_time = props.entry?.planned_end_time || (props.endMin !== null && props.endMin < 1440 ? time(props.endMin) : '10:00');
    form.project_job_id = props.entry?.project_job_id || '';
    form.project_name_text = props.entry?.project_name_text || '';
    form.title = props.entry?.title || '';
    form.entry_visibility = props.entry?.entry_visibility || 'department';
    form.actual_quantity = props.entry?.actual_quantity ?? '';
    form.actual_unit = props.entry?.actual_unit || '';
    form.result_note = props.entry?.result_note || '';
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
                <p class="mt-1 text-xs text-gray-500">会議などの予定やジョブの詳細登録は、カレンダーの従来の入力から行えます。</p>
                <p v-if="error" class="mt-3 rounded bg-red-50 p-2 text-sm text-red-700" role="alert">{{ error }}</p>
                <form class="mt-4 space-y-3" @submit.prevent="save">
                    <label class="block text-sm">日付<input v-model="form.work_date" type="date" required class="mt-1 w-full rounded border p-2" /></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm">大まかな作業区分
                            <select v-model="form.work_category" required class="mt-1 w-full rounded border p-2" @change="form.activity_type = ''"><option v-for="[key, label] in categories" :key="key" :value="key">{{ label }}</option></select>
                        </label>
                        <label class="text-sm">予定枠
                            <select v-model="form.plan_slot" required class="mt-1 w-full rounded border p-2"><option value="am">午前</option><option value="pm">午後</option><option value="full">終日</option><option value="hour">時間指定</option></select>
                        </label>
                    </div>
                    <label class="block text-sm">作業種類（朝は任意）
                        <select v-model="form.activity_type" class="mt-1 w-full rounded border p-2"><option value="">後で決める</option><option v-for="[key, label] in workActivities[form.work_category] || workActivities.other" :key="key" :value="key">{{ label }}</option></select>
                    </label>
                    <div v-if="form.plan_slot === 'hour'" class="grid grid-cols-2 gap-3">
                        <label class="text-sm">開始<input v-model="form.planned_start_time" type="time" step="900" required class="mt-1 w-full rounded border p-2" /></label>
                        <label class="text-sm">終了<input v-model="form.planned_end_time" type="time" step="900" required class="mt-1 w-full rounded border p-2" /></label>
                    </div>
                    <div class="text-sm">
                        <label for="quick-work-project-search" class="block">案件（任意）</label>
                        <div v-if="selectedProject" class="mt-1 flex items-center justify-between gap-2 rounded border border-blue-200 bg-blue-50 p-2">
                            <span class="min-w-0 truncate">{{ projectLabel(selectedProject) }}</span>
                            <button type="button" class="shrink-0 text-blue-700 underline" @click="clearProject">変更・解除</button>
                        </div>
                        <template v-else>
                            <input id="quick-work-project-search" v-model="projectSearch" type="search" autocomplete="off" placeholder="案件名・案件番号・取引先名で検索" class="mt-1 w-full rounded border p-2" />
                            <div class="mt-1 max-h-44 overflow-y-auto rounded border" aria-label="案件候補">
                                <p v-if="loading" class="p-2 text-gray-500">検索中...</p>
                                <p v-else-if="!projectGroups.length" class="p-2 text-gray-500">該当する案件がありません。下の自由入力も使えます。</p>
                                <div v-for="group in projectGroups" :key="group.key">
                                    <p class="bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600">{{ group.label }}</p>
                                    <button v-for="project in group.items" :key="project.id" type="button" class="block w-full px-2 py-1.5 text-left hover:bg-blue-50" @click="chooseProject(project)">{{ projectLabel(project) }}</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <label v-if="!form.project_job_id" class="block text-sm">案件名を自由入力（任意）<input v-model="form.project_name_text" type="text" maxlength="255" class="mt-1 w-full rounded border p-2" /></label>
                    <div class="relative text-sm">
                        <label for="quick-work-title" class="block">作業名（任意）</label>
                        <input id="quick-work-title" v-model="form.title" type="text" maxlength="255" autocomplete="off" aria-autocomplete="list" :aria-expanded="showTitleSuggestions && titleSuggestions.length > 0" aria-controls="quick-work-title-suggestions" class="mt-1 w-full rounded border p-2" @focus="showTitleSuggestions = true" @input="showTitleSuggestions = true" @keydown.esc="showTitleSuggestions = false" @blur="showTitleSuggestions = false" />
                        <div v-if="showTitleSuggestions && titleSuggestions.length" id="quick-work-title-suggestions" role="listbox" class="absolute z-30 mt-1 max-h-44 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                            <p class="px-3 py-1 text-xs text-gray-500">以前入力した作業名</p>
                            <button v-for="item in titleSuggestions" :key="item.title" type="button" role="option" class="block w-full px-3 py-2 text-left hover:bg-pink-50" @pointerdown.prevent="form.title = item.title; showTitleSuggestions = false">{{ item.title }}</button>
                        </div>
                    </div>
                    <div class="space-y-3 rounded-lg border border-pink-200 bg-pink-50/50 p-3">
                        <div>
                            <h3 class="text-sm font-semibold text-pink-900">作業した内容を記録（あとから入力可）</h3>
                            <p class="mt-1 text-xs text-gray-600">数量は作業中に記録できます。日報には保存した実績を取り込みます。</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm">数量<input v-model="form.actual_quantity" type="number" min="0" max="99999999" step="0.01" class="mt-1 w-full rounded border p-2" placeholder="例：100" /></label>
                            <label class="text-sm">単位<input v-model="form.actual_unit" type="text" list="quick-work-unit-suggestions" maxlength="30" :required="form.actual_quantity !== ''" class="mt-1 w-full rounded border p-2" placeholder="ページ・件など" /></label>
                        </div>
                        <datalist id="quick-work-unit-suggestions"><option v-for="unit in unitSuggestions" :key="unit" :value="unit" /></datalist>
                        <div class="flex flex-wrap gap-1.5" aria-label="よく使う単位">
                            <button v-for="unit in unitSuggestions.slice(0, 6)" :key="unit" type="button" class="rounded-full border border-pink-200 bg-white px-2 py-0.5 text-xs text-pink-800 hover:bg-pink-100" @click="form.actual_unit = unit">{{ unit }}</button>
                        </div>
                        <label class="block text-sm">今日の進み・結果<textarea v-model="form.result_note" rows="2" maxlength="2000" class="mt-1 w-full rounded border p-2" placeholder="例：初校を提出、確認待ち"></textarea></label>
                        <p class="text-xs text-gray-600">カレンダーの予定時間を作業時間として記録します。結果を入力するときは、上の作業種類も選んでください。</p>
                    </div>
                    <label class="block text-sm">部署一覧での表示
                        <select v-model="form.entry_visibility" class="mt-1 w-full rounded border p-2"><option value="department">内容を表示</option><option value="private">内容を非公開</option></select>
                    </label>
                    <div class="flex justify-between border-t pt-3">
                        <button v-if="entry && !entry.diary_id" type="button" :disabled="saving" class="text-sm text-red-700 hover:underline" @click="remove">削除</button><span v-else></span>
                        <button type="submit" :disabled="saving" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{{ saving ? '保存中...' : '保存' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
