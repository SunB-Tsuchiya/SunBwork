<template>
    <AppLayout title="Coordinator 設定">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">設定</h2>
        </template>
        <template #tabs>
            <CoordinatorNavigationTabs active="settings" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <h3 class="mb-6 text-base font-semibold text-gray-700">ジョブ一覧の表示設定</h3>

            <div class="max-w-sm space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">デフォルトのグループ表示</label>
                    <select
                        v-model="form.jobbox_group_mode"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-400 focus:outline-none focus:ring-1 focus:ring-green-400"
                    >
                        <option value="date">日付ごと</option>
                        <option value="client">クライアントごと</option>
                        <option value="project">案件ごと</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">ジョブ一覧ページを開いたときの初期グループ表示を設定します。</p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        @click="save"
                        :disabled="saving"
                        :class="saving
                            ? 'cursor-not-allowed rounded bg-green-300 px-5 py-2 text-sm font-medium text-white'
                            : 'rounded bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700'"
                    >
                        {{ saving ? '保存中…' : '保存' }}
                    </button>
                    <span v-if="saved" class="text-sm text-green-600">保存しました</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    setting: Object,
});

const form = reactive({
    jobbox_group_mode: props.setting?.jobbox_group_mode ?? 'date',
});

const saving = ref(false);
const saved = ref(false);

function save() {
    saving.value = true;
    saved.value = false;
    router.put(route('coordinator.settings.update'), form, {
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true;
            setTimeout(() => { saved.value = false; }, 2000);
        },
        onFinish: () => { saving.value = false; },
    });
}
</script>
