<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
            <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
            <div class="relative w-full max-w-sm rounded-xl bg-white shadow-2xl" @click.stop>
                <!-- タイトル -->
                <div class="border-b border-gray-200 px-5 py-4 text-center">
                    <h2 class="text-base font-semibold text-gray-800">ステータス更新</h2>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <!-- お名前 -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">お名前</label>
                        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            {{ targetUser.name }}
                        </div>
                    </div>

                    <!-- ひとこと（自分のみ編集可） -->
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label class="text-xs font-medium text-gray-500">ひとこと（あれば）</label>
                            <button
                                v-if="isSelf && localComment"
                                type="button"
                                class="text-xs text-gray-400 hover:text-gray-600"
                                @click="localComment = ''"
                            >× クリア</button>
                        </div>
                        <textarea
                            v-if="isSelf"
                            v-model="localComment"
                            rows="2"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                        />
                        <div v-else class="min-h-[52px] rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                            {{ targetUser.comment || '（なし）' }}
                        </div>
                    </div>

                    <!-- ステータスボタン（6行 × 3列） -->
                    <div>
                        <label class="mb-2 block text-xs font-medium text-gray-500">ステータスを選んでください</label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                v-for="s in displayStatuses"
                                :key="s.slug"
                                type="button"
                                class="rounded-lg px-2 py-2.5 text-xs font-medium transition-all"
                                :class="btnClasses(s)"
                                @click="localStatus = s.slug"
                            >{{ s.label }}</button>
                        </div>
                    </div>
                </div>

                <!-- フッター -->
                <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                    <!-- 使い方ガイドボタン -->
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
                        @click="showGuide = true"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        使い方ガイド
                    </button>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 active:bg-blue-800"
                            @click="handleSave"
                        >更新する</button>
                        <button
                            v-if="isSelf"
                            type="button"
                            class="text-sm text-red-500 hover:text-red-700"
                            @click="handleClear"
                        >削除する</button>
                        <button
                            type="button"
                            class="text-sm text-gray-500 hover:text-gray-700"
                            @click="$emit('close')"
                        >キャンセル</button>
                    </div>
                </div>

                <!-- 使い方ガイドモーダル -->
                <IrukaGuideModal :show="showGuide" @close="showGuide = false" />
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { STATUSES, getBtnClasses, resolveStatus } from './statusConfig.js';
import IrukaGuideModal from './IrukaGuideModal.vue';

const props = defineProps({
    show:       { type: Boolean, default: false },
    targetUser: { type: Object, required: true },
    isSelf:     { type: Boolean, default: false },
    statuses:   { type: Array, default: null }, // DB順の [{slug, sort_order}]、null = 静的定義順
});

const emit = defineEmits(['close', 'save', 'clear']);

const localStatus  = ref(props.targetUser.status ?? 'left');
const localComment = ref(props.targetUser.comment ?? '');
const showGuide    = ref(false);

// DB順があればそれを使い（カスタムラベル/カラー反映）、なければ静的 STATUSES 順にフォールバック
const displayStatuses = computed(() => {
    if (props.statuses && props.statuses.length > 0) {
        return props.statuses.map(s => resolveStatus(s));
    }
    return STATUSES;
});

watch(() => props.targetUser, (u) => {
    localStatus.value  = u.status ?? 'left';
    localComment.value = u.comment ?? '';
}, { deep: true });

watch(() => props.show, (v) => {
    if (v) {
        localStatus.value  = props.targetUser.status ?? 'left';
        localComment.value = props.targetUser.comment ?? '';
    }
});

function btnClasses(s) {
    return getBtnClasses(s, localStatus.value === s.slug);
}

function handleSave() {
    emit('save', { userId: props.targetUser.id, status: localStatus.value, comment: localComment.value });
}

function handleClear() {
    emit('clear');
}
</script>
