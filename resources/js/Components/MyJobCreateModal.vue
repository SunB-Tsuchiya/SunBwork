<script setup>
import axios from 'axios';
import { computed, nextTick, reactive, ref, watch, onMounted, onUnmounted } from 'vue';
import { route } from 'ziggy-js';
import { useFileAnalyzer } from '@/composables/useFileAnalyzer';

const props = defineProps({
    show: { type: Boolean, default: false },
    date: { type: String, default: '' },
    startMin: { type: Number, default: null },
    endMin: { type: Number, default: null },
});
const emit = defineEmits(['close', 'saved']);
const options = ref(null);
const loading = ref(false);
const saving = ref(false);
const loadError = ref('');
const saveError = ref('');
const errors = ref({});
const showDetails = ref(false);
const projectSearch = ref('');
const titleInput = ref(null);
const analyzer = useFileAnalyzer();
const fileSummary = analyzer.summary;
const fileAnalyzing = analyzer.analyzing;
const rejectedFiles = analyzer.rejectedFiles;
let requestSequence = 0;

const form = reactive({
    project_job_id: '', title: '', date: '', start: '', end: '',
    amounts: '', amounts_unit: 'page', detail: '', work_item_type_id: '',
    stage_id: '', size_id: '', difficulty_id: '',
});

const localDate = () => new Date().toLocaleDateString('sv-SE');
const toTime = (minutes) => minutes == null || minutes < 0 || minutes >= 1440 ? ''
    : `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
const clientName = (project) => (options.value?.userClients || [])
    .find(client => String(client.id) === String(project.client_id))?.name || '';
const projectLabel = (project) => [project.title, clientName(project)].filter(Boolean).join(' / ');
const projects = computed(() => {
    const term = projectSearch.value.trim().toLocaleLowerCase();
    return (options.value?.userProjects || []).filter(project =>
        !term || projectLabel(project).toLocaleLowerCase().includes(term));
});
const currentProject = computed(() => (options.value?.userProjects || [])
    .find(project => String(project.id) === String(form.project_job_id)));
const filterMaster = (name) => (options.value?.[name] || []).filter(item =>
    (!item.company_id || String(item.company_id) === String(options.value?.company?.id))
    && (!item.department_id || String(item.department_id) === String(options.value?.department?.id)));

function resetForm() {
    Object.assign(form, {
        project_job_id: '', title: '', date: props.date || localDate(),
        start: toTime(props.startMin), end: toTime(props.endMin),
        amounts: '', amounts_unit: 'page', detail: '', work_item_type_id: '',
        stage_id: '', size_id: '', difficulty_id: '',
    });
    options.value = null;
    errors.value = {};
    loadError.value = '';
    saveError.value = '';
    showDetails.value = false;
    projectSearch.value = '';
    analyzer.clearFiles();
    analyzer.clearRejected();
}

async function loadOptions() {
    const sequence = ++requestSequence;
    loading.value = true;
    try {
        const params = { myjob_modal: 1, date: form.date };
        if (props.startMin != null) {
            params.startHour = Math.floor(props.startMin / 60);
            params.startMinute = props.startMin % 60;
        }
        if (props.endMin != null) {
            params.endHour = Math.floor(props.endMin / 60);
            params.endMinute = props.endMin % 60;
        }
        const { data } = await axios.get(route('events.create_job'), { params });
        if (sequence !== requestSequence || !props.show) return;
        options.value = data;
        if (data?.assignments?.[0]?.project_job_id) {
            form.project_job_id = String(data.assignments[0].project_job_id);
        }
        await nextTick();
        titleInput.value?.focus();
    } catch {
        if (sequence === requestSequence && props.show) {
            loadError.value = 'マイジョブの入力候補を読み込めませんでした。もう一度開いてください。';
        }
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}

watch(() => props.show, (visible) => {
    if (visible) {
        resetForm();
        loadOptions();
    } else {
        requestSequence += 1;
    }
});

watch(analyzer.summary, (summary) => {
    if (!summary.totalFiles) return;
    const allHavePages = analyzer.results.value.every(file => file.pages != null);
    form.amounts = String(allHavePages ? summary.totalPages : summary.totalFiles);
    form.amounts_unit = allHavePages ? 'page' : 'file';
});

function onFileChange(event) {
    analyzer.analyzeFiles(event.target.files);
    event.target.value = '';
}

function close() {
    if (!saving.value) emit('close');
}

function onKeydown(event) {
    if (props.show && event.key === 'Escape') close();
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

function validate() {
    const next = {};
    if (!form.project_job_id) next.project_job_id = '案件を選択してください。';
    if (!form.title.trim()) next.title = 'ジョブ名を入力してください。';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(form.date)) next.date = '作業日を選択してください。';
    if (!/^\d{2}:\d{2}$/.test(form.start)) next.start = '開始時刻を入力してください。';
    if (!/^\d{2}:\d{2}$/.test(form.end)) next.end = '終了時刻を入力してください。';
    if (form.start && form.end && form.end <= form.start) next.end = '終了時刻は開始時刻より後にしてください。';
    if (form.amounts !== '' && (!/^\d+$/.test(String(form.amounts)) || Number(form.amounts) < 0)) {
        next.amounts = '数量は0以上の整数で入力してください。';
    }
    errors.value = next;
    return Object.keys(next).length === 0;
}

async function save() {
    if (saving.value || !options.value || !validate()) return;
    saving.value = true;
    saveError.value = '';
    errors.value = {};
    const assignment = {
        title: form.title.trim(),
        project_job_id: Number(form.project_job_id),
        detail: form.detail.trim() || null,
        desired_start_date: form.date,
        start_time: form.start,
        desired_time: form.end,
        amounts: form.amounts === '' ? null : Number(form.amounts),
        amounts_unit: form.amounts === '' ? null : form.amounts_unit,
        status_id: options.value.in_progress_status_id || null,
        company_id: options.value.company?.id || null,
        department_id: options.value.department?.id || null,
        work_item_type_id: form.work_item_type_id || null,
        stage_id: form.stage_id || null,
        size_id: form.size_id || null,
        difficulty_id: form.difficulty_id || null,
        file_info: analyzer.buildFileInfo(),
    };
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        await axios.post(route('user.project_jobs.assignments.store', { projectJob: assignment.project_job_id }),
            { myjob_modal: 1, assignments: [assignment] },
            { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        emit('saved', form.date);
    } catch (error) {
        if (error.response?.status === 422) {
            const serverErrors = error.response.data?.errors || {};
            const fieldMap = {
                'assignments.0.title': 'title',
                'assignments.0.desired_start_date': 'date',
                'assignments.0.start_time': 'start',
                'assignments.0.desired_time': 'end',
                'assignments.0.amounts': 'amounts',
            };
            for (const [key, messages] of Object.entries(serverErrors)) {
                errors.value[fieldMap[key] || key] = Array.isArray(messages) ? messages[0] : String(messages);
            }
            const otherMessages = Object.entries(serverErrors)
                .filter(([key]) => !fieldMap[key])
                .map(([, messages]) => Array.isArray(messages) ? messages[0] : String(messages));
            saveError.value = otherMessages.join(' ') || '入力内容を確認してください。';
        } else {
            saveError.value = '保存できませんでした。通信状況を確認して、もう一度お試しください。';
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40 p-3 sm:p-6" @click.self="close">
            <div role="dialog" aria-modal="true" aria-labelledby="my-job-modal-title"
                class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-4 py-3 sm:px-6">
                    <h2 id="my-job-modal-title" class="text-lg font-semibold">マイジョブ登録</h2>
                    <button type="button" aria-label="閉じる" class="rounded px-2 py-1 text-xl text-gray-500 hover:bg-gray-100" @click="close">×</button>
                </div>
                <div class="overflow-y-auto px-4 py-4 sm:px-6">
                    <p v-if="loadError" role="alert" class="mb-3 rounded bg-red-50 p-3 text-sm text-red-700">{{ loadError }}</p>
                    <p v-if="loading" class="py-8 text-center text-sm text-gray-500">入力候補を読み込み中…</p>
                    <form v-if="options" id="my-job-create-form" class="space-y-4" @submit.prevent="save">
                        <div>
                            <label for="my-job-project-search" class="block text-sm font-medium">案件</label>
                            <input id="my-job-project-search" v-model="projectSearch" type="search" placeholder="案件名・取引先で絞り込み"
                                class="mt-1 w-full rounded border px-3 py-2 text-sm" />
                            <select v-model="form.project_job_id" class="mt-1 w-full rounded border px-3 py-2 text-sm" :aria-invalid="!!errors.project_job_id">
                                <option value="">選択してください</option>
                                <option v-for="project in projects" :key="project.id" :value="String(project.id)">{{ projectLabel(project) }}</option>
                                <option v-if="currentProject && !projects.some(project => String(project.id) === String(currentProject.id))"
                                    :value="String(currentProject.id)">{{ projectLabel(currentProject) }}</option>
                            </select>
                            <p v-if="errors.project_job_id" class="mt-1 text-xs text-red-600">{{ errors.project_job_id }}</p>
                        </div>
                        <div>
                            <label for="my-job-title" class="block text-sm font-medium">ジョブ名</label>
                            <input id="my-job-title" ref="titleInput" v-model="form.title" type="text" maxlength="255"
                                class="mt-1 w-full rounded border px-3 py-2" :aria-invalid="!!errors.title" />
                            <p v-if="errors.title" class="mt-1 text-xs text-red-600">{{ errors.title }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label for="my-job-date" class="block text-sm font-medium">作業日</label>
                                <input id="my-job-date" v-model="form.date" type="date" class="mt-1 w-full rounded border px-3 py-2" :aria-invalid="!!errors.date" />
                                <p v-if="errors.date" class="mt-1 text-xs text-red-600">{{ errors.date }}</p>
                            </div>
                            <div>
                                <label for="my-job-start" class="block text-sm font-medium">開始</label>
                                <input id="my-job-start" v-model="form.start" type="time" class="mt-1 w-full rounded border px-3 py-2" :aria-invalid="!!errors.start" />
                                <p v-if="errors.start" class="mt-1 text-xs text-red-600">{{ errors.start }}</p>
                            </div>
                            <div>
                                <label for="my-job-end" class="block text-sm font-medium">終了</label>
                                <input id="my-job-end" v-model="form.end" type="time" class="mt-1 w-full rounded border px-3 py-2" :aria-invalid="!!errors.end" />
                                <p v-if="errors.end" class="mt-1 text-xs text-red-600">{{ errors.end }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="my-job-amounts" class="block text-sm font-medium">数量（任意）</label>
                                <input id="my-job-amounts" v-model="form.amounts" type="number" min="0" step="1"
                                    class="mt-1 w-32 rounded border px-3 py-2" :aria-invalid="!!errors.amounts" />
                                <p v-if="errors.amounts" class="mt-1 text-xs text-red-600">{{ errors.amounts }}</p>
                            </div>
                            <div>
                                <label for="my-job-unit" class="block text-sm font-medium">単位</label>
                                <select id="my-job-unit" v-model="form.amounts_unit" class="mt-1 rounded border px-3 py-2">
                                    <option value="page">ページ</option><option value="file">ファイル</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="text-sm font-medium text-indigo-700 hover:underline" @click="showDetails = !showDetails">
                            {{ showDetails ? '詳細を閉じる ▲' : '詳細を開く ▼' }}
                        </button>
                        <div v-if="showDetails" class="space-y-4 rounded border bg-gray-50 p-4">
                            <div>
                                <label for="my-job-detail" class="block text-sm font-medium">概要</label>
                                <textarea id="my-job-detail" v-model="form.detail" rows="3" class="mt-1 w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label for="my-job-files" class="block text-sm font-medium">作業ファイル情報</label>
                                <input id="my-job-files" type="file" multiple class="mt-1 block w-full text-sm" @change="onFileChange" />
                                <p class="mt-1 text-xs text-gray-500">ファイルの種類・ページ数などを読み取り、情報だけ保存します。</p>
                                <p v-if="fileAnalyzing" class="text-xs text-gray-500">ファイルを確認中…</p>
                                <p v-if="fileSummary.totalFiles" class="text-xs text-gray-700">
                                    {{ fileSummary.totalFiles }}ファイル / {{ fileSummary.totalPages }}ページ
                                    <button type="button" class="ml-2 text-red-600 underline" @click="analyzer.clearFiles()">取り消す</button>
                                </p>
                                <p v-if="rejectedFiles.length" class="text-xs text-red-600">対象外 {{ rejectedFiles.length }}件</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div v-for="field in [
                                    { key: 'work_item_type_id', label: '作業種別', list: 'types' },
                                    { key: 'stage_id', label: '工程', list: 'stages' },
                                    { key: 'size_id', label: 'サイズ', list: 'sizes' },
                                    { key: 'difficulty_id', label: '難易度', list: 'difficulties' },
                                ]" :key="field.key">
                                    <label :for="`my-job-${field.key}`" class="block text-sm font-medium">{{ field.label }}</label>
                                    <select :id="`my-job-${field.key}`" v-model="form[field.key]" class="mt-1 w-full rounded border px-3 py-2">
                                        <option value="">未指定</option>
                                        <option v-for="item in filterMaster(field.list)" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <p v-if="saveError" role="alert" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ saveError }}</p>
                    </form>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 sm:px-6">
                    <a :href="route('events.create_job')" class="text-xs text-gray-600 underline">過去データ流用・依頼ジョブ登録は詳しい画面へ</a>
                    <div class="flex gap-2">
                        <button type="button" class="rounded border px-4 py-2 text-sm" @click="close">キャンセル</button>
                        <button type="submit" form="my-job-create-form" :disabled="saving || loading || !options || fileAnalyzing"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {{ saving ? '保存中…' : '保存する' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
