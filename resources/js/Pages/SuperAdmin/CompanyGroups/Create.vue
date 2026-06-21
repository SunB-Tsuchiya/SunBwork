<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    companies: { type: Array, required: true },
});

const form = useForm({
    name:        '',
    group_key:   '',
    description: '',
    active:      true,
    company_ids: [],
});

function submit() {
    form.post(route('super-admin.company-groups.store'));
}
</script>

<template>
    <AppLayout title="グループ会社 新規登録">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('super-admin.company-groups.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">グループ会社 新規登録</h2>
            </div>
        </template>

        <template #tabs>
            <SuperAdminNavigationTabs active="company_groups" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mx-auto max-w-2xl">
                <form @submit.prevent="submit" class="space-y-5">

                    <!-- グループ名 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">グループ名 <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="例: サンエー印刷グループ"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- 識別キー -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">識別キー <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.group_key"
                            type="text"
                            required
                            placeholder="例: suna_group（半角英数・ハイフン・アンダースコアのみ）"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                        />
                        <p class="mt-1 text-xs text-gray-400">一意のキーを指定してください。後から変更可能です。</p>
                        <p v-if="form.errors.group_key" class="mt-1 text-xs text-red-600">{{ form.errors.group_key }}</p>
                    </div>

                    <!-- 説明 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">説明</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                        ></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <!-- 有効/無効 -->
                    <div class="flex items-center gap-2">
                        <input id="active" v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" />
                        <label for="active" class="text-sm font-medium text-gray-700">有効</label>
                    </div>

                    <!-- 所属会社 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">所属会社</label>
                        <div class="rounded-md border border-gray-200 divide-y divide-gray-100 max-h-64 overflow-y-auto">
                            <label
                                v-for="c in companies"
                                :key="c.id"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="c.id"
                                    v-model="form.company_ids"
                                    class="h-4 w-4 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500"
                                />
                                <span class="text-sm text-gray-800">{{ c.name }}</span>
                            </label>
                        </div>
                        <p v-if="form.errors.company_ids" class="mt-1 text-xs text-red-600">{{ form.errors.company_ids }}</p>
                    </div>

                    <!-- ボタン -->
                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded bg-yellow-600 px-5 py-2 text-sm font-bold text-white hover:bg-yellow-700 disabled:opacity-50"
                        >{{ form.processing ? '登録中...' : '登録' }}</button>
                        <Link
                            :href="route('super-admin.company-groups.index')"
                            class="rounded border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >キャンセル</Link>
                    </div>

                </form>
            </div>
        </div>
    </AppLayout>
</template>
