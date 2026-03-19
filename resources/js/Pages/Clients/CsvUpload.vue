<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.user_role ?? 'leader');
const isSuperAdmin = computed(() => role.value === 'superadmin');
const routePrefix = computed(() => ['admin', 'superadmin'].includes(role.value) ? 'admin' : 'leader');

const form = useForm({
    csv_file:   null,
    company_id: '',
});

const fileInput = ref(null);
const fileName = ref('');

const selectFile = () => fileInput.value?.click();
const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.csv_file = file;
        fileName.value = file.name;
    }
};

const canSubmit = computed(() => {
    if (!form.csv_file) return false;
    if (isSuperAdmin.value && !form.company_id) return false;
    return true;
});

const submit = () => {
    form.post(route(`${routePrefix.value}.clients.csv.preview`));
};
</script>

<template>
    <AppLayout title="クライアントCSV一括登録">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアントCSV一括登録</h2>
                <Link :href="route(`${routePrefix}.clients.create`)" class="text-gray-600 hover:text-gray-900">← 新規作成に戻る</Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- 説明セクション -->
            <div class="mb-8">
                <h3 class="mb-4 text-lg font-medium text-gray-900">CSVファイル形式について</h3>
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="mb-3 text-sm text-gray-700">CSVファイルは以下の形式で作成してください：</p>
                    <div class="rounded border bg-white p-3 font-mono text-sm">
                        <div class="mb-1 text-gray-500"># ヘッダー行（必須）</div>
                        <div class="font-semibold">name,detail</div>
                        <div class="mb-1 mt-2 text-gray-500"># データ行の例</div>
                        <div>株式会社サンプル,詳細テキスト</div>
                        <div>テスト商事,</div>
                    </div>
                    <div class="mt-4 text-sm text-gray-600">
                        <h4 class="mb-2 font-semibold">各列の説明：</h4>
                        <ul class="space-y-1">
                            <li><strong>name:</strong> クライアント名（必須）</li>
                            <li><strong>detail:</strong> 詳細・備考（省略可）</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- アップロードフォーム -->
            <form @submit.prevent="submit">
                <!-- SuperAdminのみ: 会社選択 -->
                <div v-if="isSuperAdmin" class="mb-6">
                    <InputLabel for="company" value="登録先の会社" />
                    <select
                        id="company"
                        v-model="form.company_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="">-- 会社を選択してください --</option>
                        <option v-for="company in companies" :key="company.id" :value="company.id">
                            {{ company.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.company_id" />
                </div>

                <!-- ファイル選択 -->
                <div class="mb-6">
                    <InputLabel for="csv_file" value="CSVファイル" />
                    <div class="mt-2">
                        <input ref="fileInput" type="file" accept=".csv,.txt" @change="onFileChange" class="hidden" />

                        <div class="flex items-center space-x-4">
                            <button
                                type="button"
                                @click="selectFile"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:border-blue-300 focus:outline-none focus:ring focus:ring-blue-200 active:bg-gray-50 active:text-gray-800 disabled:opacity-25"
                            >
                                📁 ファイルを選択
                            </button>
                            <span v-if="fileName" class="text-sm text-gray-600">選択されたファイル: {{ fileName }}</span>
                            <span v-else class="text-sm text-gray-500">ファイルが選択されていません</span>
                        </div>
                        <div class="mt-2">
                            <a
                                :href="route(`${routePrefix}.clients.csv.sample`)"
                                download
                                class="inline-flex items-center rounded border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50"
                            >
                                ⬇ サンプルCSVをダウンロード
                            </a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.csv_file" />
                </div>

                <!-- アクションボタン -->
                <div class="flex items-center justify-between">
                    <Link
                        :href="route(`${routePrefix}.clients.create`)"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-600 focus:outline-none disabled:opacity-25"
                    >
                        キャンセル
                    </Link>
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing || !canSubmit }"
                        :disabled="form.processing || !canSubmit"
                        class="ml-4"
                    >
                        <span v-if="form.processing">処理中...</span>
                        <span v-else>📊 プレビューを確認</span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
