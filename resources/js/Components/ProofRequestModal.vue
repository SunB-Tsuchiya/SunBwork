<script setup>
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    show:                    { type: Boolean, default: false },
    initialTitle:            { type: String,  default: '' },
    projectJobAssignmentId:  { type: Number,  default: null },
    projectJobId:            { type: Number,  default: null },
    proofCellId:             { type: Number,  default: null },
});

const emit = defineEmits(['close']);

const form = ref({
    title:                     '',
    deadline_date:             '',
    deadline_hour:             17,
    deadline_minute:           30,
    note:                      '',
    project_job_assignment_id: null,
    project_job_id:            null,
    proof_cell_id:             null,
});

const submitting = ref(false);

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = [0, 15, 30, 45];

// モーダルが開くたびに初期値をセット
watch(() => props.show, (val) => {
    if (val) {
        const t = props.initialTitle;
        const proofTitle = t
            ? (t.endsWith('_組版') ? t.replace(/_組版$/, '_校正') : t + '_校正')
            : '';
        form.value = {
            title:                     proofTitle,
            deadline_date:             '',
            deadline_hour:             17,
            deadline_minute:           30,
            note:                      '',
            project_job_assignment_id: props.projectJobAssignmentId,
            project_job_id:            props.projectJobId,
            proof_cell_id:             props.proofCellId,
        };
    }
});

function submit() {
    if (!form.value.deadline_date) return;
    submitting.value = true;

    // JST指定でISO文字列に変換（UTC保存）
    const h = String(form.value.deadline_hour).padStart(2, '0');
    const m = String(form.value.deadline_minute).padStart(2, '0');
    const deadline = new Date(`${form.value.deadline_date}T${h}:${m}:00+09:00`).toISOString();

    router.post(route('proof_requests.store'), {
        title:                     form.value.title,
        deadline,
        note:                      form.value.note,
        project_job_assignment_id: form.value.project_job_assignment_id,
        project_job_id:            form.value.project_job_id,
        proof_cell_id:             form.value.proof_cell_id || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
            emit('close');
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
            <!-- オーバーレイ -->
            <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

            <!-- モーダル本体 -->
            <div class="relative z-10 w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">校正依頼を送る</h3>

                <div class="space-y-4">
                    <!-- ジョブ名 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ジョブ名</label>
                        <input
                            v-model="form.title"
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
                        <div class="mt-1 flex items-center gap-2">
                            <input
                                v-model="form.deadline_date"
                                type="date"
                                class="flex-1 rounded border-gray-300 text-sm"
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
                            rows="3"
                            class="mt-1 w-full rounded border-gray-300 text-sm"
                            placeholder="校正の注意点など"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="emit('close')"
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
