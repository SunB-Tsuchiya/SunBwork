<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ client: Object });

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    if (['admin', 'superadmin'].includes(role)) return 'admin';
    if (role === 'coordinator') return 'coordinator';
    return 'leader';
});

const form = useForm({
    name: props.client.name,
    detail: props.client.notes,
});

function submit() {
    form.put(route(`${routePrefix.value}.clients.update`, props.client.id));
}

// 削除モーダル
const showDeleteModal = ref(false);
const deleteError = ref(null);

// サーバーから返ってきた削除エラーをウォッチ
watch(
    () => page.props.clientDeleteError,
    (val) => {
        if (val) {
            deleteError.value = val;
            showDeleteModal.value = true;
        }
    },
    { immediate: true },
);

function confirmDelete() {
    if (!confirm(`「${props.client.name}」を削除してもよいですか？\nこの操作は取り消せません。`)) return;
    router.delete(route(`${routePrefix.value}.clients.destroy`, props.client.id));
}

function closeModal() {
    showDeleteModal.value = false;
}
</script>

<template>
    <AppLayout title="クライアント編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアント編集</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block">名前</label>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-2 py-1" />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-2 py-1"></textarea>
                </div>
                <div class="mt-6 flex gap-4">
                    <button type="submit" class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700">更新</button>
                    <Link :href="route(`${routePrefix}.clients.index`)" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300">一覧へ戻る</Link>
                    <button
                        type="button"
                        class="ml-auto rounded bg-red-600 px-4 py-2 font-bold text-white hover:bg-red-700"
                        @click="confirmDelete"
                    >
                        削除
                    </button>
                </div>
            </form>
        </div>

        <!-- 削除ブロックモーダル -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <!-- オーバーレイ -->
                <div class="absolute inset-0 bg-black/50" @click="closeModal" />

                <!-- モーダル本体 -->
                <div class="relative z-10 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <!-- ヘッダー -->
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">削除できません</h3>
                    </div>

                    <!-- 本文 -->
                    <div v-if="deleteError" class="mb-6 space-y-3">
                        <p class="text-sm text-gray-700">
                            クライアント <strong class="text-gray-900">「{{ deleteError.clientName }}」</strong> には
                            現在 <strong class="text-red-600">{{ deleteError.projectJobCount }} 件</strong> の案件が紐付いているため削除できません。
                        </p>

                        <!-- 案件一覧（最大5件） -->
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-500">紐付いている案件（一部）</p>
                            <ul class="space-y-1">
                                <li v-for="(title, i) in deleteError.projectJobTitles" :key="i" class="flex items-center gap-2 text-sm text-gray-700">
                                    <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400" />
                                    {{ title }}
                                </li>
                            </ul>
                            <p v-if="deleteError.projectJobCount > deleteError.projectJobTitles.length" class="mt-2 text-xs text-gray-500">
                                ほか {{ deleteError.projectJobCount - deleteError.projectJobTitles.length }} 件…
                            </p>
                        </div>

                        <p class="text-sm text-gray-500">
                            削除するには、まず紐付いている案件のクライアントを変更するか、別のクライアントへ統合してください。
                        </p>
                    </div>

                    <!-- フッター -->
                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300"
                            @click="closeModal"
                        >
                            閉じる
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
