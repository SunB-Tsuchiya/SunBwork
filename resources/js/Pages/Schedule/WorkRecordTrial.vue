<script setup>
import axios from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import WorkloadAnalysisShell from '@/Components/Schedule/WorkloadAnalysisShell.vue';
import { workCategories, workActivities, activityLabel } from '@/Constants/personalWorkActivities';

const props = defineProps({ initialDate: { type: String, required: true } });
const today = () => new Date().toLocaleDateString('sv-SE');
const currentTime = () => {
    const now = new Date();
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
};
const date = ref(props.initialDate);
const records = ref([]);
const recent = ref([]);
const dayReview = ref({ reviewed: false, missing_minutes_count: 0, entered_minutes_total: 0 });
const myJobs = ref([]);
const searchedMyJobs = ref([]);
const myJobSearch = ref('');
const selectedMyJob = ref(null);
const projects = ref([]);
const projectSearch = ref('');
const selectedProject = ref(null);
const loading = ref(false);
const saving = ref(false);
const reviewing = ref(false);
const searching = ref(false);
const error = ref('');
const editingId = ref(null);
const form = reactive({
    work_date: date.value, recorded_time: '', project_job_id: null, project_job_assignment_id: null,
    project_name_text: '', work_category: 'other', activity_type: '', title: '',
    progress_state: null, approximate_minutes: '', note: '', continue_from_id: null,
});
const stateLabels = { worked: '進めた', handed_off: '返却・引き渡した', waiting: '確認待ち' };
const projectLabel = (project) => [project.title, project.jobcode, project.client_name].filter(Boolean).join(' / ');
const hasContent = computed(() => records.value.length > 0);
const canSave = computed(() => !saving.value && !!form.activity_type);
const visibleMyJobs = computed(() => {
    const jobs = myJobSearch.value.trim() ? searchedMyJobs.value : myJobs.value;
    return selectedMyJob.value && !jobs.some(job => job.id === selectedMyJob.value.id)
        ? [selectedMyJob.value, ...jobs] : jobs;
});

async function loadDay() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('schedule.work_record_trial.index'), { params: { date: date.value } });
        records.value = data.records || [];
        recent.value = data.recent || [];
        dayReview.value = data.day_review || { reviewed: false, missing_minutes_count: 0, entered_minutes_total: 0 };
        myJobs.value = data.my_jobs || [];
        if (form.project_job_assignment_id) {
            selectedMyJob.value = myJobs.value.find(job => job.id === form.project_job_assignment_id) || selectedMyJob.value;
        }
    } catch (err) {
        records.value = [];
        dayReview.value = { reviewed: false, missing_minutes_count: 0, entered_minutes_total: 0 };
        error.value = err.response?.data?.message || '作業記録を読み込めませんでした。';
    } finally {
        loading.value = false;
    }
}

async function loadProjects() {
    searching.value = true;
    try {
        const { data } = await axios.get(route('schedule.department_work.options'), {
            params: { q: projectSearch.value, selected_id: form.project_job_id || undefined },
        });
        projects.value = data.projects || [];
        if (!editingId.value && !form.continue_from_id && form.work_category === 'other') {
            form.work_category = data.default_category || 'other';
        }
    } catch {
        error.value = '案件候補を読み込めませんでした。';
    } finally {
        searching.value = false;
    }
}

function resetForm() {
    editingId.value = null;
    Object.assign(form, {
        work_date: date.value, recorded_time: '',
        project_job_id: null, project_job_assignment_id: null, project_name_text: '',
        work_category: form.work_category || 'other', activity_type: '',
        title: '', progress_state: null, approximate_minutes: '', note: '', continue_from_id: null,
    });
    selectedProject.value = null;
    selectedMyJob.value = null;
    myJobSearch.value = '';
    projectSearch.value = '';
    error.value = '';
}

function useRecent(row, isContinuation) {
    resetForm();
    Object.assign(form, {
        project_job_id: row.project_job_assignment_id ? null : row.project_job_id,
        project_job_assignment_id: row.project_job_assignment_id,
        project_name_text: row.project_job_id || row.project_job_assignment_id ? '' : (row.project_name_text || ''),
        work_category: row.work_category,
        activity_type: row.activity_type,
        title: row.title || '',
        continue_from_id: isContinuation ? row.id : null,
    });
    selectedProject.value = row.project_job_id && !row.project_job_assignment_id
        ? { id: row.project_job_id, title: row.project_name } : null;
    selectedMyJob.value = myJobs.value.find(job => job.id === row.project_job_assignment_id) || null;
    document.getElementById('trial-work-record-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function editRecord(row) {
    resetForm();
    editingId.value = row.id;
    Object.assign(form, {
        work_date: row.work_date, recorded_time: row.recorded_time || '',
        project_job_id: row.project_job_assignment_id ? null : row.project_job_id,
        project_job_assignment_id: row.project_job_assignment_id,
        project_name_text: row.project_job_id || row.project_job_assignment_id ? '' : (row.project_name_text || ''),
        work_category: row.work_category, activity_type: row.activity_type,
        title: row.title || '', progress_state: row.progress_state,
        approximate_minutes: row.approximate_minutes ?? '', note: row.note || '', continue_from_id: null,
    });
    selectedProject.value = row.project_job_id && !row.project_job_assignment_id
        ? { id: row.project_job_id, title: row.project_name } : null;
    selectedMyJob.value = myJobs.value.find(job => job.id === row.project_job_assignment_id) || null;
    document.getElementById('trial-work-record-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function chooseProject(project) {
    form.project_job_id = project.id;
    form.project_job_assignment_id = null;
    selectedMyJob.value = null;
    form.project_name_text = '';
    selectedProject.value = project;
    projectSearch.value = '';
}

function clearProject() {
    form.project_job_id = null;
    selectedProject.value = null;
}

function changeMyJob() {
    selectedMyJob.value = visibleMyJobs.value.find(job => job.id === form.project_job_assignment_id) || null;
    if (form.project_job_assignment_id) {
        form.project_job_id = null;
        form.project_name_text = '';
        selectedProject.value = null;
    }
    form.continue_from_id = null;
}

function changeCategory() {
    if (!(workActivities[form.work_category] || []).some(([code]) => code === form.activity_type)) form.activity_type = '';
    form.continue_from_id = null;
}

async function saveRecord() {
    saving.value = true;
    error.value = '';
    const payload = {
        ...form,
        project_job_id: form.project_job_id || null,
        project_job_assignment_id: form.project_job_assignment_id || null,
        recorded_time: form.recorded_time || null,
        approximate_minutes: form.approximate_minutes === '' ? null : Number(form.approximate_minutes),
    };
    try {
        if (editingId.value) {
            await axios.put(route('schedule.work_record_trial.update', { record: editingId.value }), payload);
        } else {
            await axios.post(route('schedule.work_record_trial.store'), payload);
        }
        const savedDate = form.work_date;
        if (date.value === savedDate) {
            resetForm();
            await loadDay();
        } else {
            date.value = savedDate;
        }
    } catch (err) {
        const first = Object.values(err.response?.data?.errors || {})[0];
        error.value = first?.[0] || err.response?.data?.message || '保存できませんでした。';
    } finally {
        saving.value = false;
    }
}

async function removeRecord(row) {
    if (!window.confirm('この試用記録を削除しますか？')) return;
    error.value = '';
    try {
        await axios.delete(route('schedule.work_record_trial.destroy', { record: row.id }));
        if (editingId.value === row.id) resetForm();
        await loadDay();
    } catch (err) {
        error.value = err.response?.data?.message || '削除できませんでした。';
    }
}

async function finishDay() {
    reviewing.value = true;
    error.value = '';
    try {
        await axios.post(route('schedule.work_record_trial.review_day'), { work_date: date.value });
        await loadDay();
    } catch (err) {
        const first = Object.values(err.response?.data?.errors || {})[0];
        error.value = first?.[0] || err.response?.data?.message || '一日の確認を保存できませんでした。';
    } finally {
        reviewing.value = false;
    }
}

watch(date, () => { resetForm(); loadDay(); });
watch(myJobSearch, (value, _oldValue, onCleanup) => {
    const term = value.trim();
    if (!term) {
        searchedMyJobs.value = [];
        return;
    }
    let active = true;
    const timer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('schedule.work_record_trial.my_jobs'), { params: { q: term } });
            if (active) searchedMyJobs.value = data.my_jobs || [];
        } catch {
            if (active) error.value = 'マイジョブの検索に失敗しました。';
        }
    }, 250);
    onCleanup(() => { active = false; clearTimeout(timer); });
});
onMounted(async () => { await Promise.all([loadDay(), loadProjects()]); });
</script>

<template>
    <WorkloadAnalysisShell title="作業記録の試用" active="record" :date="date">
        <div class="mx-auto max-w-5xl space-y-5">
            <section class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
                <h2 class="font-semibold">Super Admin 限定の試用</h2>
                <p class="mt-1">途中で短く残した作業を、下の「一日の確認」でまとめて見られます。試用記録は既存の日報、マイジョブ、作業量の点数には反映されません。</p>
            </section>

            <div v-if="error" role="alert" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

            <section id="trial-work-record-form" class="rounded-lg bg-white p-4 shadow sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold">{{ editingId ? '作業記録を修正' : '作業を記録' }}</h2>
                        <p class="text-sm text-gray-600">その日の終わりでも、作業の途中でも、同じ方法で1件ずつ記録できます。件数が多い日も続けて入力できます。</p>
                    </div>
                    <button v-if="editingId" type="button" class="text-sm text-blue-700 underline" @click="resetForm">新規入力に戻る</button>
                </div>

                <div v-if="recent.length && !editingId" class="mt-4 rounded border border-gray-200 bg-gray-50 p-3">
                    <h3 class="text-sm font-semibold">最近の作業から選ぶ</h3>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <div v-for="row in recent" :key="row.id" class="rounded border bg-white px-3 py-2 text-sm">
                            <div class="max-w-64 truncate font-medium">{{ row.project_name || row.title || activityLabel(row.activity_type, row.work_category) }}</div>
                            <div v-if="row.title && row.project_name" class="max-w-64 truncate text-xs text-gray-500">{{ row.title }}</div>
                            <div class="mt-1 flex gap-3">
                                <button type="button" class="text-blue-700 underline" @click="useRecent(row, true)">続き</button>
                                <button type="button" class="text-blue-700 underline" @click="useRecent(row, false)">別の作業</button>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="saveRecord">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">作業日<input v-model="form.work_date" type="date" :max="today()" required class="mt-1 w-full rounded border p-2" /></label>
                        <div class="text-sm">
                            <label>作業を切り替えた時刻（任意）<input v-model="form.recorded_time" type="time" class="mt-1 w-full rounded border p-2" /></label>
                            <button v-if="form.work_date === today()" type="button" class="mt-1 text-blue-700 underline" @click="form.recorded_time = currentTime()">今の時刻を入れる</button>
                            <p class="mt-1 text-xs text-gray-500">その日の終わりに入力する場合は空欄で大丈夫です。</p>
                        </div>
                    </div>
                    <label class="block text-sm">マイジョブを探す（任意）
                        <input v-model="myJobSearch" type="search" maxlength="100" class="mt-1 w-full rounded border p-2" placeholder="作業名・案件名・取引先名" />
                    </label>
                    <label class="block text-sm">既存のマイジョブ（任意）
                        <select v-model="form.project_job_assignment_id" class="mt-1 w-full rounded border p-2" @change="changeMyJob">
                            <option :value="null">選択しない・本筋や仮の案件名から記録する</option>
                            <option v-for="job in visibleMyJobs" :key="job.id" :value="job.id">
                                {{ [job.client_name, job.project_name, job.title].filter(Boolean).join(' ／ ') }}{{ job.completed ? '（表示上は完了）' : '' }}
                            </option>
                        </select>
                        <span class="mt-1 block text-xs text-gray-500">選択してもマイジョブ自体は変更しません。作業記録だけを試用保存します。</span>
                    </label>
                    <div v-if="!form.project_job_assignment_id" class="text-sm">
                        <label for="trial-project-search">案件（任意）</label>
                        <div v-if="selectedProject" class="mt-1 flex items-center justify-between rounded border border-blue-200 bg-blue-50 p-2">
                            <span class="truncate">{{ projectLabel(selectedProject) }}</span>
                            <button type="button" class="shrink-0 text-blue-700 underline" @click="clearProject">変更</button>
                        </div>
                        <div v-else class="mt-1 flex gap-2">
                            <input id="trial-project-search" v-model="projectSearch" type="search" placeholder="案件名・番号で探す" class="min-w-0 flex-1 rounded border p-2" @keydown.enter.prevent="loadProjects" />
                            <button type="button" :disabled="searching" class="rounded border px-3 py-2" @click="loadProjects">検索</button>
                        </div>
                        <div v-if="!selectedProject && projects.length" class="mt-1 max-h-36 overflow-y-auto rounded border">
                            <button v-for="project in projects" :key="project.id" type="button" class="block w-full px-2 py-1.5 text-left hover:bg-blue-50" @click="chooseProject(project)">{{ projectLabel(project) }}</button>
                        </div>
                    </div>
                    <label v-if="!form.project_job_assignment_id && !form.project_job_id" class="block text-sm">仮の案件名（本筋がまだない場合も保存できます）<input v-model="form.project_name_text" type="text" maxlength="255" class="mt-1 w-full rounded border p-2" /></label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">作業区分<select v-model="form.work_category" required class="mt-1 w-full rounded border p-2" @change="changeCategory"><option v-for="[code, label] in workCategories" :key="code" :value="code">{{ label }}</option></select></label>
                        <label class="text-sm">作業種類<select v-model="form.activity_type" required class="mt-1 w-full rounded border p-2"><option value="">選択してください</option><option v-for="[code, label] in workActivities[form.work_category] || workActivities.other" :key="code" :value="code">{{ label }}</option></select></label>
                    </div>
                    <label class="block text-sm">作業名・ファイルの目印（案件もマイジョブもない場合は必須）<input v-model="form.title" type="text" maxlength="255" :required="!form.project_job_assignment_id && !form.project_job_id && !form.project_name_text" class="mt-1 w-full rounded border p-2" placeholder="例：初校A、画像加工など" /></label>
                    <details class="rounded border border-gray-200 p-3 text-sm">
                        <summary class="cursor-pointer font-medium">必要なときだけ、進み・時間・メモを追加</summary>
                        <fieldset class="mt-3">
                            <legend>今回の進み（任意）</legend>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <label v-for="(label, key) in stateLabels" :key="key" class="cursor-pointer rounded border px-3 py-2" :class="form.progress_state === key ? 'border-blue-600 bg-blue-50 text-blue-900' : 'bg-white'">
                                    <input v-model="form.progress_state" type="radio" :value="key" class="sr-only" />{{ label }}
                                </label>
                                <button v-if="form.progress_state" type="button" class="text-blue-700 underline" @click="form.progress_state = null">選択を消す</button>
                            </div>
                        </fieldset>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label>おおよその作業時間（分）<input v-model="form.approximate_minutes" type="number" min="1" max="720" class="mt-1 w-full rounded border p-2" placeholder="任意・採点には使いません" /></label>
                            <label class="sm:col-span-2">補足メモ<textarea v-model="form.note" rows="2" maxlength="2000" class="mt-1 w-full rounded border p-2" placeholder="必要なことだけ"></textarea></label>
                        </div>
                    </details>
                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="!canSave" class="rounded bg-blue-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? '保存中…' : (editingId ? '修正を保存' : '記録を保存') }}</button>
                        <span v-if="form.continue_from_id" class="text-xs text-blue-700">前の記録の続きとして保存</span>
                    </div>
                </form>
            </section>

            <section class="rounded-lg bg-white p-4 shadow sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">一日の確認</h2>
                        <p class="text-sm text-gray-600">記録を見直し、足りない作業だけ上のフォームから追加できます。</p>
                    </div>
                    <label class="text-sm">確認する日<input v-model="date" type="date" :max="today()" class="ml-2 rounded border p-2" /></label>
                </div>
                <p v-if="loading" class="mt-4 text-sm text-gray-500">読み込み中…</p>
                <p v-else-if="!hasContent" class="mt-4 rounded bg-gray-50 p-4 text-sm text-gray-600">この日の試用記録はまだありません。未記録だけで作業の有無は判断できません。</p>
                <template v-else>
                    <p class="mt-3 text-xs text-gray-500">{{ records.length }}件の記録。件数は作業量や評価点ではありません。</p>
                    <div class="mt-3 rounded border border-blue-200 bg-blue-50 p-3 text-sm">
                        <h3 class="font-semibold">一日の終わりの確認</h3>
                        <p class="mt-1">{{ records.length }}件の作業を見直せます。入力済みの目安時間は合計 {{ dayReview.entered_minutes_total }} 分です。</p>
                        <div v-if="dayReview.missing_minutes_count" class="mt-2 rounded border border-amber-200 bg-amber-50 p-2 text-amber-950">
                            <p>目安時間が空欄の作業が {{ dayReview.missing_minutes_count }} 件あります。将来、時間を評価の参考にする際は内容を確認してください。時間は任意で、このまま確認を終えても構いません。</p>
                            <details class="mt-1">
                                <summary class="cursor-pointer underline">空欄の作業を見る</summary>
                                <ul class="mt-1 space-y-1">
                                    <li v-for="row in records.filter(row => !row.approximate_minutes)" :key="row.id">
                                        <button type="button" class="text-left underline" @click="editRecord(row)">{{ row.project_name || row.title || activityLabel(row.activity_type, row.work_category) }}<span v-if="row.title && row.project_name">／{{ row.title }}</span></button>
                                    </li>
                                </ul>
                            </details>
                        </div>
                        <p class="mt-2 text-xs text-gray-600">この確認印は試用記録だけに付きます。既存の日報提出や評価点は変わりません。</p>
                        <p v-if="dayReview.reviewed" class="mt-2 font-semibold text-green-800">この日は確認済みです。作業を追加・修正・削除すると再確認が必要になります。</p>
                        <p v-if="editingId" class="mt-2 text-xs text-gray-600">編集中の記録を保存するか、編集を取り消してから確認してください。</p>
                        <button v-if="!dayReview.reviewed" type="button" :disabled="reviewing || loading || saving || editingId !== null" class="mt-2 rounded bg-blue-700 px-4 py-2 font-semibold text-white disabled:opacity-50" @click="finishDay">{{ reviewing ? '保存中…' : 'この日の記録を確認して終了' }}</button>
                    </div>
                    <ol class="mt-3 space-y-2">
                        <li v-for="row in records" :key="row.id" class="rounded border p-3 text-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span v-if="row.recorded_time" class="font-semibold">{{ row.recorded_time }}</span>
                                        <span v-if="row.progress_state" class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-800">{{ stateLabels[row.progress_state] }}</span>
                                        <span v-if="row.approximate_minutes" class="text-xs text-gray-500">目安 {{ row.approximate_minutes }}分</span>
                                    </div>
                                    <p class="mt-1">{{ row.project_name || '案件なし' }}<span v-if="row.my_job_title">／{{ row.my_job_title }}</span><span v-if="row.title">／{{ row.title }}</span></p>
                                    <p class="text-gray-600">{{ activityLabel(row.activity_type, row.work_category) }}</p>
                                    <p v-if="row.note" class="mt-1 whitespace-pre-wrap text-gray-700">{{ row.note }}</p>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" class="text-blue-700 underline" @click="editRecord(row)">修正</button>
                                    <button type="button" class="text-red-700 underline" @click="removeRecord(row)">削除</button>
                                </div>
                            </div>
                        </li>
                    </ol>
                </template>
            </section>
        </div>
    </WorkloadAnalysisShell>
</template>
