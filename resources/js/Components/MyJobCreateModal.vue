<script setup>
import axios from 'axios';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import MyJobSourcePicker from '@/Components/MyJobSourcePicker.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    date: { type: String, default: '' },
    startMin: { type: Number, default: null },
    endMin: { type: Number, default: null },
});
const emit = defineEmits(['close', 'saved']);
const options = ref(null);
const loading = ref(false);
const loadError = ref('');
const picker = ref(null);
const formAssignments = ref([]);
const formKey = ref(0);
const reuseAssignmentId = ref(null);
let requestSequence = 0;

async function loadOptions() {
    const sequence = ++requestSequence;
    loading.value = true;
    loadError.value = '';
    try {
        const params = { myjob_modal: 1, date: props.date || new Date().toLocaleDateString('sv-SE') };
        if (props.startMin != null) {
            params.startHour = Math.floor(props.startMin / 60);
            params.startMinute = props.startMin % 60;
        }
        if (props.endMin != null) {
            params.endHour = Math.floor(props.endMin / 60);
            params.endMinute = props.endMin % 60;
        }
        const { data } = await axios.get(route('events.create_job'), { params });
        if (sequence === requestSequence && props.show) {
            options.value = data;
            formAssignments.value = data.assignments || [];
            formKey.value += 1;
        }
    } catch {
        if (sequence === requestSequence && props.show) {
            loadError.value = 'マイジョブの入力候補を読み込めませんでした。もう一度開いてください。';
        }
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}

watch(() => props.show, visible => {
    if (visible) {
        options.value = null;
        picker.value = null;
        reuseAssignmentId.value = null;
        loadOptions();
    } else {
        requestSequence += 1;
    }
});

function close() {
    picker.value = null;
    emit('close');
}

function onKeydown(event) {
    if (props.show && event.key === 'Escape') {
        if (picker.value) picker.value = null;
        else close();
    }
}

function onSourceSelected({ record, kind }) {
    if (!options.value || !record?.project_job_id) return;
    const clientId = record.client_id ?? null;
    if (clientId && !options.value.userClients?.some(client => String(client.id) === String(clientId))) {
        options.value.userClients = [...(options.value.userClients || []), { id: clientId, name: record.client_name || '取引先' }];
    }
    if (!options.value.userProjects?.some(project => String(project.id) === String(record.project_job_id))) {
        options.value.userProjects = [...(options.value.userProjects || []), {
            id: record.project_job_id,
            title: record.project_job_name || '案件',
            client_id: clientId,
        }];
    }
    const assignment = kind === 'request' ? {
        project_job_id: record.project_job_id,
        _client_id: clientId ?? '',
        title_suffix: record.title ?? '',
        desired_end_date: record.desired_end_date ?? '',
        supersedes_assignment_id: record.id,
    } : {
        project_job_id: record.project_job_id,
        _client_id: clientId ?? '',
        title_suffix: record.title ?? '',
        detail: record.detail ?? '',
        work_item_type_id: record.work_item_type_id ?? null,
        size_id: record.size_id ?? null,
        stage_id: record.stage_id ?? null,
        difficulty_id: record.difficulty_id ?? null,
        desired_end_date: record.desired_end_date ?? '',
        desired_time: record.desired_time ?? null,
        estimated_hours: record.estimated_hours ?? null,
        amounts: null,
        status_id: null,
        ...(kind === 'continue' ? { source_assignment_id: record.id } : {}),
    };
    reuseAssignmentId.value = kind === 'new' ? record.id : null;
    formAssignments.value = [assignment];
    formKey.value += 1;
    picker.value = null;
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40 p-3 sm:p-6" @click.self="close">
            <div role="dialog" aria-modal="true" aria-labelledby="my-job-modal-title"
                class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 sm:px-6">
                    <h2 id="my-job-modal-title" class="text-lg font-semibold">ジョブ作成</h2>
                    <button type="button" aria-label="閉じる" class="rounded px-2 py-1 text-xl text-gray-500 hover:bg-gray-100" @click="close">×</button>
                </div>
                <div class="flex flex-wrap gap-2 border-b px-4 py-2 sm:px-6">
                    <button type="button" :disabled="!options" class="rounded border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50" @click="picker = 'past'">過去データから流用</button>
                    <button type="button" :disabled="!options" class="rounded border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-700 hover:bg-indigo-100 disabled:opacity-50" @click="picker = 'request'">依頼ジョブとして登録</button>
                </div>
                <div class="overflow-y-auto px-4 py-4 sm:px-6">
                    <p v-if="loadError" role="alert" class="mb-3 rounded bg-red-50 p-3 text-sm text-red-700">{{ loadError }}</p>
                    <p v-if="loading" class="py-8 text-center text-sm text-gray-500">入力候補を読み込み中…</p>
                    <MyJobSourcePicker v-if="options && picker" :key="picker" :mode="picker" @cancel="picker = null" @selected="onSourceSelected" />
                    <div v-if="options" v-show="!picker" class="rounded border border-gray-100 p-4 shadow-sm">
                        <h3 class="mb-4 text-lg font-medium text-gray-700">割当内容</h3>
                        <AssignmentForm
                            :key="formKey"
                            mode="user"
                            :project-job="options.projectJob || null"
                            :members="options.members || []"
                            :assignments="formAssignments"
                            :event="options.prefillEvent || null"
                            :edit-mode="true"
                            :default-user-id="options.members?.[0]?.id || null"
                            :user-clients="options.userClients || []"
                            :user-projects="options.userProjects || []"
                            :other-client-id="options.other_client_id"
                            :other-project-id="options.other_project_id"
                            :default-status-id="options.in_progress_status_id"
                            :modal-context="options"
                            :modal-reuse-assignment-id="reuseAssignmentId"
                            @saved="date => emit('saved', date)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
