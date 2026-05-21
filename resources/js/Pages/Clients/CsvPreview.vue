<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    csvData:        Array,
    errors:         Array,
    hasErrors:      Boolean,
    prefix:         String,
    company_id:     { type: [String, Number], default: null },
    company:        { type: Object, default: null },
    department_ids: { type: Array, default: () => [] },
});

// skip 行と CSV エラー行を除外して登録対象とする
const actionRows = props.csvData.filter(row =>
    row.status !== 'skip' &&
    !props.errors.some(e => e.includes(`行 ${row.line}:`))
);
const skipRows = props.csvData.filter(row => row.status === 'skip');
const newCount     = computed(() => actionRows.filter(r => r.status === 'new').length);
const mergeDeptCount = computed(() => actionRows.filter(r => r.status === 'add_dept').length);

const form = useForm({
    clients:        actionRows,
    company_id:     props.company_id,
    department_ids: props.department_ids,
});

const submit = () => {
    form.post(route(`${props.prefix}.clients.csv.store`));
};

const statusBadge = (row) => {
    if (row.status === 'new')      return { label: '新規登録', cls: 'bg-green-100 text-green-700' };
    if (row.status === 'add_dept') return { label: '部署追加', cls: 'bg-blue-100 text-blue-700' };
    if (row.status === 'skip')     return { label: '登録済み', cls: 'bg-gray-100 text-gray-500' };
    return { label: '新規登録', cls: 'bg-green-100 text-green-700' };
};
</script>

<template>
    <AppLayout title="クライアントCSV登録確認">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">クライアントCSV登録確認</h2>
                <Link :href="route(`${prefix}.clients.csv.upload`)" class="text-gray-600 hover:text-gray-900">← CSVアップロードに戻る</Link>
            </div>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 登録先会社（SuperAdmin時のみ表示） -->
            <div v-if="company" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <h3 class="mb-1 text-sm font-medium text-blue-800">📍 登録先の会社</h3>
                <span class="text-sm text-blue-900">{{ company.name }}</span>
            </div>

            <!-- CSV エラー表示 -->
            <div v-if="hasErrors" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <h3 class="mb-3 text-lg font-medium text-red-800">⚠️ エラーが検出されました</h3>
                <ul class="space-y-1">
                    <li v-for="(error, i) in errors" :key="i" class="text-sm text-red-700">{{ error }}</li>
                </ul>
                <p class="mt-2 text-sm text-red-600">エラーのある行は登録されません。</p>
            </div>

            <!-- 処理サマリー -->
            <div class="mb-4 flex flex-wrap gap-3 text-sm">
                <span v-if="newCount > 0" class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 font-medium text-green-700">
                    🆕 新規登録 {{ newCount }}件
                </span>
                <span v-if="mergeDeptCount > 0" class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 font-medium text-blue-700">
                    ➕ 部署追加（既存） {{ mergeDeptCount }}件
                </span>
                <span v-if="skipRows.length > 0" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-500">
                    ✅ 登録済みスキップ {{ skipRows.length }}件
                </span>
            </div>

            <!-- プレビューテーブル -->
            <div class="mb-6">
                <h3 class="mb-3 text-lg font-medium text-gray-900">登録内容プレビュー（全 {{ csvData.length }}行）</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">行</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">アクション</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">クライアント名</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Client ID</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">詳細</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="row in csvData"
                                :key="row.line"
                                class="hover:bg-gray-50"
                                :class="{ 'opacity-50': row.status === 'skip' }"
                            >
                                <td class="px-3 py-2 text-sm text-gray-500">{{ row.line }}</td>
                                <td class="px-3 py-2 text-sm">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="statusBadge(row).cls"
                                    >{{ statusBadge(row).label }}</span>
                                    <div v-if="row.status === 'add_dept'" class="mt-0.5 text-xs text-gray-400">
                                        既存: {{ row.matched_client_name }}
                                    </div>
                                    <div v-if="row.status === 'skip'" class="mt-0.5 text-xs text-gray-400">
                                        登録済み: {{ row.matched_client_name }}
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ row.name }}</td>
                                <td class="px-3 py-2 font-mono text-sm text-gray-600">{{ row.client_code || '-' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600">{{ row.detail || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- アクションボタン -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route(`${prefix}.clients.csv.upload`)"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-600 focus:outline-none"
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
                    <span v-else>✅ {{ form.clients.length }}件を実行する</span>
                </PrimaryButton>
                <p v-else class="text-sm text-gray-500">実行する処理がありません（全行スキップ）</p>
            </div>
        </div>
    </AppLayout>
</template>
