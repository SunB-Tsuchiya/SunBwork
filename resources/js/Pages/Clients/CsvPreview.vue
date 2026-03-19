<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    csvData:    Array,
    errors:     Array,
    hasErrors:  Boolean,
    prefix:     String,
    company_id: { type: [String, Number], default: null },
    company:    { type: Object, default: null },
});

const form = useForm({
    clients:    props.csvData.filter((row, i) => !props.errors.some((e) => e.includes(`行 ${i + 1}:`))),
    company_id: props.company_id,
});

const submit = () => {
    form.post(route(`${props.prefix}.clients.csv.store`));
};
</script>

<template>
    <AppLayout title="クライアントCSV登録確認">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアントCSV登録確認</h2>
                <Link :href="route(`${prefix}.clients.csv.upload`)" class="text-gray-600 hover:text-gray-900">← CSVアップロードに戻る</Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- 登録先会社（SuperAdmin時のみ表示） -->
            <div v-if="company" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <h3 class="mb-1 text-sm font-medium text-blue-800">📍 登録先の会社</h3>
                <span class="text-sm text-blue-900">{{ company.name }}</span>
            </div>

            <!-- エラー表示 -->
            <div v-if="hasErrors" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <h3 class="mb-3 text-lg font-medium text-red-800">⚠️ エラーが検出されました</h3>
                <ul class="space-y-1">
                    <li v-for="(error, i) in errors" :key="i" class="text-sm text-red-700">{{ error }}</li>
                </ul>
                <p class="mt-2 text-sm text-red-600">エラーのある行は登録されません。</p>
            </div>

            <!-- プレビューテーブル -->
            <div class="mb-6">
                <h3 class="mb-3 text-lg font-medium text-gray-900">登録内容プレビュー（{{ form.clients.length }}件）</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">行</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">クライアント名</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">詳細</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="row in form.clients" :key="row.line" class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm text-gray-500">{{ row.line }}</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ row.name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ row.detail || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- アクションボタン -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route(`${prefix}.clients.csv.upload`)"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-600 focus:outline-none disabled:opacity-25"
                >
                    戻る
                </Link>
                <PrimaryButton
                    v-if="form.clients.length > 0"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    class="ml-4"
                    @click="submit"
                >
                    <span v-if="form.processing">登録中...</span>
                    <span v-else>✅ {{ form.clients.length }}件を登録する</span>
                </PrimaryButton>
            </div>
        </div>
    </AppLayout>
</template>
