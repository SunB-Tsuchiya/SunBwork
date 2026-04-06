<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    subcontractor: Object,
    coordinators: Array,
    assignmentCount: Number,
});

const page = usePage();
const showDeleteConfirm = ref(false);
const deleteError = ref(page.props.subcontractorDeleteError ?? null);

watch(() => page.props.subcontractorDeleteError, (v) => { deleteError.value = v ?? null; });

function destroy() {
    router.delete(route('coordinator.subcontractors.destroy', props.subcontractor.id));
    showDeleteConfirm.value = false;
}
</script>

<template>
    <AppLayout title="外注先詳細">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">外注先 詳細</h2>
                <div class="flex items-center gap-3">
                    <Link :href="route('coordinator.subcontractors.edit', props.subcontractor.id)" class="rounded bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">編集</Link>
                    <Link :href="route('coordinator.subcontractors.index')" class="text-gray-600 hover:text-gray-900 text-sm">← 一覧に戻る</Link>
                </div>
            </div>
        </template>

        <!-- 削除エラー -->
        <div v-if="deleteError" class="mb-4 rounded bg-red-50 p-4 text-sm text-red-700">
            この外注先には {{ deleteError.count }} 件の割当があるため削除できません。先に割当を削除してください。
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- 基本情報 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 text-base font-semibold text-gray-700">基本情報</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">名前 / 会社名</dt>
                        <dd class="text-gray-900">{{ props.subcontractor.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">メールアドレス</dt>
                        <dd class="text-gray-900">{{ props.subcontractor.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">電話番号</dt>
                        <dd class="text-gray-900">{{ props.subcontractor.phone ?? '—' }}</dd>
                    </div>
                    <div v-if="props.subcontractor.notes">
                        <dt class="text-xs font-medium text-gray-500">備考</dt>
                        <dd class="whitespace-pre-wrap text-gray-900">{{ props.subcontractor.notes }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 管理担当Coordinator -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 text-base font-semibold text-gray-700">管理担当 Coordinator</h3>
                <template v-if="props.subcontractor.coordinators && props.subcontractor.coordinators.length">
                    <ul class="space-y-1">
                        <li
                            v-for="co in props.subcontractor.coordinators"
                            :key="co.id"
                            class="flex items-center gap-2 text-sm text-gray-800"
                        >
                            <span class="h-2 w-2 rounded-full bg-green-400 flex-shrink-0"></span>
                            {{ co.name }}
                        </li>
                    </ul>
                </template>
                <p v-else class="text-sm text-gray-400">担当Coordinatorが設定されていません</p>

                <div class="mt-4">
                    <p class="text-xs text-gray-500">割当数: {{ props.assignmentCount }} 件</p>
                </div>
            </div>
        </div>

        <!-- 削除 -->
        <div class="mt-6 rounded bg-white p-6 shadow">
            <h3 class="mb-2 text-base font-semibold text-red-700">外注先の削除</h3>
            <p class="mb-4 text-sm text-gray-600">
                割当が残っている場合は削除できません。
            </p>
            <button
                type="button"
                @click="showDeleteConfirm = true"
                class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"
            >
                この外注先を削除する
            </button>
        </div>
    </AppLayout>

    <!-- 削除確認モーダル -->
    <Teleport to="body">
        <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showDeleteConfirm = false" />
            <div class="relative z-10 w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-gray-900">削除の確認</h3>
                <p class="mb-5 text-sm text-gray-700">
                    「<strong>{{ props.subcontractor.name }}</strong>」を削除しますか？この操作は元に戻せません。
                </p>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showDeleteConfirm = false" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">キャンセル</button>
                    <button type="button" @click="destroy" class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">削除する</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
