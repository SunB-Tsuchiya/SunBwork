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
                        <label class="mb-2 block text-xs font-medium text-gray-500">ステータスを選んで押してください</label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                v-for="s in displayStatuses"
                                :key="s.slug"
                                type="button"
                                class="rounded-lg px-2 py-2.5 text-xs font-medium transition-all"
                                :class="btnClasses(s)"
                                @click="selectStatus(s.slug)"
                            >{{ s.label }}</button>
                        </div>
                    </div>
                </div>

                <!-- フッター -->
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-3">
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
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { STATUSES, getBtnClasses } from './statusConfig.js';

const props = defineProps({
    show:       { type: Boolean, default: false },
    targetUser: { type: Object, required: true },
    isSelf:     { type: Boolean, default: false },
    statuses:   { type: Array, default: null }, // DB順の [{slug, sort_order}]、null = 静的定義順
});

const emit = defineEmits(['close', 'save', 'clear']);

const localStatus  = ref(props.targetUser.status ?? 'present');
const localComment = ref(props.targetUser.comment ?? '');

// DB順があればそれを使い、なければ静的 STATUSES 順にフォールバック
const displayStatuses = computed(() => {
    if (props.statuses && props.statuses.length > 0) {
        return props.statuses
            .map(s => STATUSES.find(st => st.slug === s.slug))
            .filter(Boolean);
    }
    return STATUSES;
});

watch(() => props.targetUser, (u) => {
    localStatus.value  = u.status ?? 'present';
    localComment.value = u.comment ?? '';
}, { deep: true });

watch(() => props.show, (v) => {
    if (v) {
        localStatus.value  = props.targetUser.status ?? 'present';
        localComment.value = props.targetUser.comment ?? '';
    }
});

function btnClasses(s) {
    return getBtnClasses(s, localStatus.value === s.slug);
}

function selectStatus(slug) {
    localStatus.value = slug;
    emit('save', { userId: props.targetUser.id, status: slug, comment: localComment.value });
}

function handleClear() {
    emit('clear');
}
</script>
