<script setup>
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, watch } from 'vue';

const props = defineProps({
    quickEntryId:            { type: Number, default: null },
    show:                    { type: Boolean, default: false },
    initialTitle:            { type: String,  default: '' },
    projectJobAssignmentId:  { type: Number,  default: null },
    projectJobId:            { type: Number,  default: null },
    proofCellId:             { type: Number,  default: null },
    proofRowId:              { type: Number,  default: null },
    proofColKey:             { type: String,  default: null },
});

const emit = defineEmits(['close', 'sent']);
const errors = ref({});

const form = ref({
    title:                     '',
    deadline_date:             '',
    deadline_hour:             17,
    deadline_minute:           30,
    note:                      '',
    project_job_assignment_id: null,
    project_job_id:            null,
    proof_cell_id:             null,
    proof_row_id:              null,
    proof_col_key:             null,
});

const submitting = ref(false);

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = [0, 15, 30, 45];

function todayStr() {
    const d = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// モーダルが開くたびに初期値をセット
watch(() => props.show, (val) => {
    if (val) {
        errors.value = {};
        const t = props.initialTitle;
        const proofTitle = t
            ? (t.endsWith('_組版') ? t.replace(/_組版$/, '_校正') : t + '_校正')
            : '';
        form.value = {
            title:                     Array.from(proofTitle).slice(0, 255).join(''),
            deadline_date:             todayStr(),
            deadline_hour:             17,
            deadline_minute:           30,
            note:                      '',
            project_job_assignment_id: props.projectJobAssignmentId,
            project_job_id:            props.projectJobId,
            proof_cell_id:             props.proofCellId,
            proof_row_id:              props.proofRowId,
            proof_col_key:             props.proofColKey,
        };
    }
});

function submit() {
    if (submitting.value || !form.value.deadline_date) return;
    errors.value = {};
    submitting.value = true;

    // JST指定でISO文字列に変換（UTC保存）
    const h = String(form.value.deadline_hour).padStart(2, '0');
    const m = String(form.value.deadline_minute).padStart(2, '0');
    const deadline = new Date(`${form.value.deadline_date}T${h}:${m}:00+09:00`).toISOString();

    const payload = {
        title:                     form.value.title,
        deadline,
        note:                      form.value.note,
        project_job_assignment_id: form.value.project_job_assignment_id,
        project_job_id:            form.value.project_job_id,
        proof_cell_id:             form.value.proof_cell_id || null,
        proof_row_id:              form.value.proof_row_id || null,
        proof_col_key:             form.value.proof_col_key || null,
    };
    const body = props.quickEntryId ? { title: payload.title, deadline: payload.deadline, note: payload.note } : payload;
    const destination = props.quickEntryId
        ? route('user.myjobbox.entries.proof_request', { entry: props.quickEntryId })
        : route('proof_requests.store');
    router.post(destination, body, {
        preserveScroll: true,
        onError: value => { errors.value = value; },
        onSuccess: () => { emit('sent'); emit('close'); },
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
            <!-- オーバーレイ -->
            <div class="absolute inset-0 bg-black/40" @click="!submitting && emit('close')" />

            <!-- モーダル本体 -->
            <div class="relative z-10 mx-3 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-4 shadow-xl sm:p-6">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">校正依頼を送る</h3>

                <p v-if="quickEntryId" class="mb-3 text-sm text-gray-600">校正窓口へ依頼と通知を送ります。元のマイジョブは完了になりません。</p>
                <p v-for="(error, key) in errors" :key="key" role="alert" class="mb-2 text-sm text-red-700">{{ error }}</p>
                <div class="space-y-4">
                    <!-- ジョブ名 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ジョブ名</label>
                        <input
                            v-model="form.title"
                            maxlength="255"
                            type="text"
                            class="mt-1 w-full rounded border-gray-300 text-sm"
                            placeholder="例: クライアント案件-校正"
                        />
                    </div>

                    <!-- 校正締め切り -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            校正締め切り <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <input
                                v-model="form.deadline_date"
                                type="date"
                                class="w-full min-w-0 rounded border-gray-300 text-sm sm:w-auto sm:flex-1"
                            />
                            <select v-model="form.deadline_hour" class="rounded border-gray-300 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">時</span>
                            <select v-model="form.deadline_minute" class="rounded border-gray-300 text-sm">
                                <option v-for="min in minutes" :key="min" :value="min">{{ String(min).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">分</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">※ジョブ全体の締め切りとは別に設定してください</p>
                    </div>

                    <!-- 備考 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">備考（任意）</label>
                        <textarea
                            v-model="form.note"
                            maxlength="1000"
                            rows="3"
                            class="mt-1 w-full rounded border-gray-300 text-sm"
                            placeholder="校正の注意点など"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        :disabled="submitting"
                        @click="!submitting && emit('close')"
                        class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        キャンセル
                    </button>
                    <button
                        @click="submit"
                        :disabled="!form.deadline_date || submitting"
                        class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ submitting ? '送信中...' : '校正依頼を送る' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
