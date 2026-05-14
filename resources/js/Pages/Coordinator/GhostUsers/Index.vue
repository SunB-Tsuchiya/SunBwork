<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    ghosts: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const errorMsg = computed(() => page.props.errors?.ghost ?? null);

const hasGhost = computed(() => props.ghosts.length > 0);

function remainingDays(expiresAt) {
    const diff = new Date(expiresAt) - new Date();
    return Math.max(0, Math.ceil(diff / 86400000));
}

const creating = ref(false);
function createGhost() {
    creating.value = true;
    router.post(route('coordinator.ghost_users.store'), {}, {
        onFinish: () => { creating.value = false; },
    });
}

function switchToGhost(ghostId) {
    // Inertia を経由しない通常フォーム送信（セッション保存の確実性のため）
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('coordinator.ghost_users.switch', { ghostUserId: ghostId });
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = '_token';
    input.value = page.props.csrf_token;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function deleteGhost(ghost) {
    if (!confirm(`「${ghost.name}」を削除しますか？\n関連データもすべて削除されます。`)) return;
    router.delete(route('coordinator.ghost_users.destroy', { ghostUserId: ghost.id }));
}
</script>

<template>
    <AppLayout title="テストユーザー管理">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">テストユーザー管理</h2>
        </template>
        <template #tabs>
            <CoordinatorNavigationTabs active="ghost_users" />
        </template>

        <div class="space-y-4">
            <!-- flash / error -->
            <div v-if="flash.success" class="rounded bg-green-50 px-4 py-3 text-sm text-green-700 shadow-sm">
                {{ flash.success }}
            </div>
            <div v-if="errorMsg" class="rounded bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
                {{ errorMsg }}
            </div>

            <!-- メインカード -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <div class="mb-5 text-sm text-gray-600 leading-relaxed">
                    <p>Coordinator セッション内でユーザー操作（ジョブ受信・進行表からの自己割当など）をシミュレートできます。</p>
                    <p class="mt-1">上限 <strong>1 アカウント</strong>・作成から <strong>14 日</strong> で自動削除されます。</p>
                    <p class="mt-1 text-xs text-gray-400">利用可能機能: MyJobBox・JobBox・割当完了のみ</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        @click="createGhost"
                        :disabled="hasGhost || creating"
                        :class="[
                            'rounded px-4 py-2 text-sm font-medium text-white transition',
                            hasGhost || creating
                                ? 'cursor-not-allowed bg-green-200'
                                : 'bg-green-600 hover:bg-green-700',
                        ]"
                    >
                        {{ creating ? '作成中…' : 'テストユーザーを作成' }}
                    </button>
                    <button
                        @click="router.get(route('coordinator.ghost_users.guide'))"
                        class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition"
                    >
                        使い方ガイド
                    </button>
                </div>

                <!-- 一覧 -->
                <div class="mt-6">
                    <p v-if="!hasGhost" class="text-sm text-gray-400">テストユーザーはまだ作成されていません。</p>

                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs text-gray-500">
                                <th class="pb-2 pr-4 font-medium">名前</th>
                                <th class="pb-2 pr-4 font-medium">残り日数</th>
                                <th class="pb-2 font-medium">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ghost in ghosts" :key="ghost.id" class="border-b last:border-0">
                                <td class="py-3 pr-4 font-medium text-gray-800">{{ ghost.name }}</td>
                                <td class="py-3 pr-4">
                                    <span
                                        :class="remainingDays(ghost.ghost_expires_at) <= 3
                                            ? 'text-red-600 font-semibold'
                                            : 'text-gray-600'"
                                    >
                                        残り {{ remainingDays(ghost.ghost_expires_at) }} 日
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            @click="switchToGhost(ghost.id)"
                                            class="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700"
                                        >
                                            ゴーストとして操作する
                                        </button>
                                        <button
                                            @click="deleteGhost(ghost)"
                                            class="rounded border border-red-300 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                        >
                                            削除
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
