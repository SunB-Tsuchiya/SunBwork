<template>
    <AppLayout title="ステータス更新">
        <template #header>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                    @click="router.back()"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    戻る
                </button>
                <h2 class="text-base font-semibold text-gray-800">🐬 ステータス更新</h2>
            </div>
        </template>

        <div class="mx-auto max-w-sm px-3">
            <div class="rounded-xl bg-white shadow-sm overflow-hidden">

                <!-- ひとこと -->
                <div class="px-4 pt-4 pb-3">
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-gray-500">ひとこと（あれば）</label>
                        <button
                            v-if="localComment"
                            type="button"
                            class="text-xs text-gray-400 active:text-gray-600"
                            @click="localComment = ''"
                        >× クリア</button>
                    </div>
                    <textarea
                        v-model="localComment"
                        rows="2"
                        placeholder="例: 午後から外出します"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                    />
                </div>

                <!-- ステータスボタン -->
                <div class="px-4 pb-3">
                    <label class="mb-2 block text-xs font-medium text-gray-500">ステータスを選んで押してください</label>
                    <div class="grid grid-cols-3 gap-1">
                        <button
                            v-for="s in displayStatuses"
                            :key="s.slug"
                            type="button"
                            class="rounded-lg px-1 py-2 text-xs font-medium transition-all active:scale-95"
                            :class="btnClasses(s)"
                            :disabled="saving"
                            @click="selectStatus(s.slug)"
                        >{{ s.label }}</button>
                    </div>
                </div>

                <!-- フッター -->
                <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                    <button
                        type="button"
                        class="text-sm text-red-500 active:text-red-700"
                        :disabled="saving"
                        @click="handleClear"
                    >削除する</button>
                    <button
                        type="button"
                        class="text-sm text-gray-500 active:text-gray-700"
                        @click="router.back()"
                    >キャンセル</button>
                </div>
            </div>

            <!-- 保存中インジケータ -->
            <p v-if="saving" class="mt-3 text-center text-xs text-gray-400">更新中...</p>
            <p v-if="saved" class="mt-3 text-center text-xs text-green-600">✓ 更新しました</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUSES, getBtnClasses, resolveStatus } from '@/Components/Iruka/statusConfig.js';

const props = defineProps({
    userId:         { type: Number, required: true },
    currentStatus:  { type: String, default: 'left' },
    currentComment: { type: String, default: '' },
    statuses:       { type: Array, default: () => [] },
});

const localStatus  = ref(props.currentStatus);
const localComment = ref(props.currentComment);
const saving       = ref(false);
const saved        = ref(false);

const displayStatuses = computed(() => {
    if (props.statuses && props.statuses.length > 0) {
        return props.statuses.map(s => resolveStatus(s));
    }
    return STATUSES;
});

function btnClasses(s) {
    return getBtnClasses(s, localStatus.value === s.slug);
}

async function selectStatus(slug) {
    if (saving.value) return;
    localStatus.value = slug;
    saving.value = true;
    saved.value  = false;
    try {
        await window.axios.post(`/presence/${props.userId}`, {
            status:  slug,
            comment: localComment.value,
        });
        saved.value = true;
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
        setTimeout(() => router.back(), 600);
    } catch (_) {
        saving.value = false;
    }
}

async function handleClear() {
    if (saving.value) return;
    saving.value = true;
    saved.value  = false;
    try {
        await window.axios.post('/presence/self/clear');
        saved.value = true;
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
        setTimeout(() => router.back(), 600);
    } catch (_) {
        saving.value = false;
    }
}
</script>
