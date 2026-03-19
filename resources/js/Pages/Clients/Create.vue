<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    return ['admin', 'superadmin'].includes(role) ? 'admin' : 'leader';
});

const form = useForm({
    name: '',
    detail: '',
});

function submit() {
    form.post(route(`${routePrefix.value}.clients.store`));
}
</script>

<template>
    <AppLayout title="クライアント新規作成">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアント新規作成</h2>
                <Link :href="route(`${routePrefix}.clients.index`)" class="text-gray-600 hover:text-gray-900">← 一覧に戻る</Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- CSV一括登録 -->
            <div class="mb-6">
                <h3 class="mb-2 text-base font-medium text-orange-800">CSV一括登録</h3>
                <p class="mb-3 text-sm text-orange-700">CSVファイルを使用して複数のクライアントを一度に登録できます。</p>
                <Link
                    :href="route(`${routePrefix}.clients.csv.upload`)"
                    class="inline-flex items-center rounded-md border border-transparent bg-orange-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-orange-700 focus:outline-none"
                >
                    📄 CSVファイルをアップロード
                </Link>
            </div>

            <div class="relative mb-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-white px-2 text-gray-500">または個別に登録</span>
                </div>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block">名前</label>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-2 py-1" />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-2 py-1"></textarea>
                </div>
                <button type="submit" class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700">登録</button>
            </form>
        </div>
    </AppLayout>
</template>
