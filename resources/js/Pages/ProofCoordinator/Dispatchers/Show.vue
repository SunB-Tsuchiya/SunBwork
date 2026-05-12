<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dispatcher:      { type: Object, required: true },
    assignmentCount: { type: Number, default: 0 },
});

const flash = computed(() => usePage().props.flash ?? {});

function destroy() {
    if (!confirm(`「${props.dispatcher.name}」を削除しますか？`)) return;
    router.delete(route('proof_coordinator.dispatchers.destroy', props.dispatcher.id));
}
</script>

<template>
    <AppLayout title="単発派遣 詳細">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('proof_coordinator.dispatchers.index')" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300">← 一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">単発派遣 詳細</h2>
            </div>
        </template>

        <template #headerExtras>
            <Link
                :href="route('proof_coordinator.dispatchers.edit', dispatcher.id)"
                class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            >編集</Link>
            <button
                v-if="assignmentCount === 0"
                type="button"
                @click="destroy"
                class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
            >削除</button>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="dispatchers" />
        </template>

        <div class="space-y-4">
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">名前 / 会社名</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ dispatcher.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">アサイン表示</dt>
                        <dd class="mt-1">
                            <span
                                :class="dispatcher.is_active ? 'bg-pink-100 text-pink-700' : 'bg-gray-100 text-gray-500'"
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                            >
                                {{ dispatcher.is_active ? 'オン' : 'オフ' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">メールアドレス</dt>
                        <dd class="mt-1 text-sm text-gray-700">{{ dispatcher.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">電話番号</dt>
                        <dd class="mt-1 text-sm text-gray-700">{{ dispatcher.phone ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500">備考</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-700">{{ dispatcher.notes ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">校正ジョブ割当数</dt>
                        <dd class="mt-1 text-sm text-gray-700">{{ assignmentCount }} 件</dd>
                    </div>
                </dl>

                <p v-if="assignmentCount > 0" class="mt-4 text-xs text-gray-400">
                    ※ 校正ジョブが {{ assignmentCount }} 件あるため削除できません。アサイン表示をオフにしてください。
                </p>

                <div v-if="flash.dispatcherDeleteError" class="mt-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    校正ジョブが {{ flash.dispatcherDeleteError.count }} 件紐づいているため削除できません。
                </div>
            </div>
        </div>
    </AppLayout>
</template>
