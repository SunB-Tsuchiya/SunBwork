<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    type: { type: Object, default: null },
    features: { type: Object, default: () => ({}) },
    nextSortOrder: { type: Number, default: 10 },
});

const editing = computed(() => Boolean(props.type?.id));
const form = useForm({
    name: props.type?.name ?? '',
    description: props.type?.description ?? '',
    feature_key: props.type?.feature_key ?? '',
    sort_order: props.type?.sort_order ?? props.nextSortOrder,
    is_active: props.type?.is_active ?? true,
});

function submit() {
    if (editing.value) {
        form.put(route('clerk.general_affairs.application_types.update', { applicationType: props.type.id }));
        return;
    }
    form.post(route('clerk.general_affairs.application_types.store'));
}
</script>

<template>
    <AppLayout :title="editing ? '申請の種類を編集' : '申請の種類を追加'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.application_types.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ editing ? '申請の種類を編集' : '申請の種類を追加' }}</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form class="space-y-6" @submit.prevent="submit">
                <div>
                    <label for="type-name" class="mb-1 block text-sm font-medium text-gray-700">申請の名前 <span class="text-red-600">*</span></label>
                    <input id="type-name" v-model="form.name" type="text" maxlength="100" class="w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" placeholder="例：年末調整" />
                    <p class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="type-description" class="mb-1 block text-sm font-medium text-gray-700">説明</label>
                    <textarea id="type-description" v-model="form.description" rows="4" maxlength="1000" class="w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" placeholder="例：年末調整に必要な書類の提出申請です。"></textarea>
                    <div class="mt-1 flex justify-between gap-3">
                        <p class="text-xs text-red-600">{{ form.errors.description }}</p>
                        <p class="text-xs text-gray-500">{{ (form.description ?? '').length }} / 1000</p>
                    </div>
                </div>

                <div class="max-w-xs">
                    <label for="type-feature" class="mb-1 block text-sm font-medium text-gray-700">連携する機能</label>
                    <select id="type-feature" v-model="form.feature_key" class="w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500">
                        <option value="">連携しない（枠の表示のみ）</option>
                        <option v-for="(label, key) in features" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">選んだ機能の画面へ、申請一覧の枠からリンクします。</p>
                    <p class="mt-1 text-xs text-red-600">{{ form.errors.feature_key }}</p>
                </div>

                <div class="max-w-xs">
                    <label for="type-sort" class="mb-1 block text-sm font-medium text-gray-700">並び順 <span class="text-red-600">*</span></label>
                    <input id="type-sort" v-model.number="form.sort_order" type="number" min="0" max="9999" class="w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" />
                    <p class="mt-1 text-xs text-gray-500">数字が小さいほど先に表示されます。</p>
                    <p class="mt-1 text-xs text-red-600">{{ form.errors.sort_order }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded text-purple-600 focus:ring-purple-500" />
                    この申請を有効にする（申請一覧に表示する）
                </label>

                <div v-if="Object.keys(form.errors).length" role="alert" class="rounded border border-red-200 bg-red-50 p-3">
                    <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-700">{{ error }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <button :disabled="form.processing" class="rounded bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                        {{ form.processing ? '保存中…' : editing ? '保存する' : '登録する' }}
                    </button>
                    <Link :href="route('clerk.general_affairs.application_types.index')" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">キャンセル</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
