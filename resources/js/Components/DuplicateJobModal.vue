<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    // 入力中の受注番号（見出しに表示する）
    jobcode: { type: String, default: '' },
    // 受注番号が完全一致した既存案件
    jobcodeDuplicates: { type: Array, default: () => [] },
    // 同一クライアント内で品名が類似した既存案件
    titleDuplicates: { type: Array, default: () => [] },
    // 確定ボタンのラベル（未指定なら重複種別から自動決定）
    confirmLabel: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const hasJobcodeDuplicates = computed(() => props.jobcodeDuplicates.length > 0);

const modalTitle = computed(() =>
    hasJobcodeDuplicates.value ? '同じ受注番号の案件があります' : '類似案件が見つかりました',
);

const resolvedConfirmLabel = computed(
    () => props.confirmLabel || (hasJobcodeDuplicates.value ? '別作業として登録する' : 'それでも登録する'),
);
</script>

<template>
    <DialogModal :show="show" @close="emit('close')">
        <template #title>
            <span class="flex items-center gap-2 text-yellow-700">
                <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                {{ modalTitle }}
            </span>
        </template>

        <template #content>
            <div class="space-y-4">
                <!-- 受注番号の重複 -->
                <div v-if="hasJobcodeDuplicates" class="space-y-2">
                    <p class="text-sm text-gray-700">
                        受注番号 <span class="font-semibold">{{ jobcode }}</span> の案件がすでに登録されています。
                        <span class="text-gray-600">同じ受注番号でも、別作業としてこのまま登録できます。</span>
                    </p>
                    <ul class="divide-y divide-gray-200 rounded border border-gray-200 bg-gray-50">
                        <li v-for="job in jobcodeDuplicates" :key="job.id" class="px-3 py-2 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="job.company_name"
                                            class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs"
                                            :class="job.same_company ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'"
                                        >
                                            {{ job.company_name }}
                                        </span>
                                        <span class="truncate font-medium text-gray-800">{{ job.title }}</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        <span v-if="job.client_name">{{ job.client_name }}</span>
                                        <span v-if="job.leader_name"> / リーダー: {{ job.leader_name }}</span>
                                        <span v-if="job.created_at"> / {{ job.created_at }}</span>
                                    </div>
                                </div>
                                <a
                                    :href="route('coordinator.project_jobs.show', { projectJob: job.id })"
                                    target="_blank"
                                    rel="noopener"
                                    class="whitespace-nowrap text-xs text-blue-600 underline hover:text-blue-800"
                                >
                                    別タブで開く
                                </a>
                            </div>
                        </li>
                    </ul>
                    <p class="text-xs text-gray-600">
                        品名に作業内容（例: 可変・発送）を入れておくと、一覧で見分けやすくなります。
                    </p>
                </div>

                <!-- 案件名の重複 -->
                <div v-if="titleDuplicates.length > 0" class="space-y-2">
                    <p class="text-sm text-gray-700">同一クライアントに似た名前の案件がすでに登録されています。</p>
                    <ul class="divide-y divide-yellow-100 rounded border border-yellow-200 bg-yellow-50">
                        <li v-for="job in titleDuplicates" :key="job.id" class="flex items-center justify-between px-3 py-2 text-sm">
                            <span class="font-medium text-gray-800">{{ job.title }}</span>
                            <span class="ml-3 whitespace-nowrap text-xs text-gray-500">{{ job.created_at }}</span>
                        </li>
                    </ul>
                </div>

                <p class="text-sm text-gray-600">内容を修正するか、このまま登録するか選択してください。</p>
            </div>
        </template>

        <template #footer>
            <div class="flex w-full justify-between">
                <button
                    class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300"
                    @click="emit('close')"
                >
                    閉じる（内容を修正する）
                </button>
                <button
                    class="rounded bg-orange-500 px-4 py-2 text-sm text-white hover:bg-orange-600"
                    @click="emit('confirm')"
                >
                    {{ resolvedConfirmLabel }}
                </button>
            </div>
        </template>
    </DialogModal>
</template>
