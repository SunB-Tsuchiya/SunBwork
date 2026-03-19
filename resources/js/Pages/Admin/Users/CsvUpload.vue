<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    companies: Array,
});

const form = useForm({
    csv_file: null,
    company_id: '',
    department_id: '',
});

const fileInput = ref(null);
const fileName = ref('');

// 選択した会社の部署一覧を取得
const selectedCompanyDepartments = computed(() => {
    if (!form.company_id) return [];
    const company = props.companies.find((c) => c.id == form.company_id);
    return company ? company.departments : [];
});

// 会社が変更された時に部署をリセット
const onCompanyChange = () => {
    form.department_id = '';
};

const selectFile = () => {
    fileInput.value.click();
};

const onFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.csv_file = file;
        fileName.value = file.name;
    }
};

const submit = () => {
    form.post(route('admin.users.csv.preview'));
};
</script>

<template>
    <AppLayout title="CSV一括登録">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">CSV一括登録</h2>
                <Link :href="route('admin.users.create')" class="text-gray-600 hover:text-gray-900"> ← 新規ユーザー登録に戻る </Link>
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
                        <div class="font-semibold">name,email,password,assignment,user_role</div>
                        <div class="mb-1 mt-2 text-gray-500"># データ行の例</div>
                        <div>山田太郎,yamada@example.com,password123,管理者,user</div>
                        <div>佐藤花子,sato@example.com,password456,進行管理,leader</div>
                    </div>
                    <div class="mt-4 text-sm text-gray-600">
                        <h4 class="mb-2 font-semibold">各列の説明：</h4>
                        <ul class="space-y-1">
                            <li><strong>name:</strong> ユーザー名</li>
                            <li><strong>email:</strong> メールアドレス（重複不可）</li>
                            <li><strong>password:</strong> パスワード</li>
                            <li>
                                <strong>assignment:</strong> 担当（部署により異なる）
                                <ul class="ml-4 mt-1 space-y-1 text-xs text-gray-600">
                                    <li>情報出版：進行管理、オペレーター、校正、営業、そのほか</li>
                                    <li>オンデマンド：進行管理、オペレーター、そのほか</li>
                                    <li>製版：進行管理、オペレーター、そのほか</li>
                                </ul>
                            </li>
                            <li><strong>user_role:</strong> システム権限（admin/leader/coordinator/user のいずれか）</li>
                        </ul>
                    </div>

                    <!-- 自動修正機能の説明 -->
                    <div class="mt-6 rounded-lg bg-blue-50 p-4">
                        <h4 class="mb-2 font-semibold text-blue-800">🔧 自動修正機能</h4>
                        <p class="mb-3 text-sm text-blue-700">以下のような軽微なタイポや表記ゆれは自動的に修正されます：</p>
                        <div class="space-y-2 text-xs text-blue-600">
                            <div>
                                <strong>担当:</strong>
                                「admin」→「管理者」、「operator」→「オペレーター」、「その他」→「そのほか」など
                            </div>
                            <div>
                                <strong>システム権限:</strong>
                                「管理者」→「admin」、「リーダー」→「leader」、「コーディネーター」→「coordinator」、「ユーザー」→「user」など
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-blue-600">※ 自動修正できない場合はエラーになります</p>
                    </div>
                </div>
            </div>

            <!-- ファイルアップロードフォーム -->
            <form @submit.prevent="submit">
                <!-- 会社選択 -->
                <div class="mb-6">
                    <InputLabel for="company" value="会社" />
                    <select
                        id="company"
                        v-model="form.company_id"
                        @change="onCompanyChange"
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

                <!-- 部署選択 -->
                <div class="mb-6">
                    <InputLabel for="department" value="部署" />
                    <select
                        id="department"
                        v-model="form.department_id"
                        :disabled="!form.company_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="">-- 部署を選択してください --</option>
                        <option v-for="department in selectedCompanyDepartments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.department_id" />
                    <p class="mt-1 text-sm text-gray-600">CSV内のユーザーは全て選択した部署のチームに追加されます</p>
                </div>

                <div class="mb-6">
                    <InputLabel for="csv_file" value="CSVファイル" />

                    <div class="mt-2">
                        <!-- 隠しファイル入力 -->
                        <input ref="fileInput" type="file" accept=".csv,.txt" @change="onFileChange" class="hidden" />

                        <!-- カスタムファイル選択ボタン -->
                        <div class="flex items-center space-x-4">
                            <button
                                type="button"
                                @click="selectFile"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:border-blue-300 focus:outline-none focus:ring focus:ring-blue-200 active:bg-gray-50 active:text-gray-800 disabled:opacity-25"
                            >
                                📁 ファイルを選択
                            </button>

                            <span v-if="fileName" class="text-sm text-gray-600"> 選択されたファイル: {{ fileName }} </span>
                            <span v-else class="text-sm text-gray-500"> ファイルが選択されていません </span>
                        </div>
                        <div class="mt-2">
                            <a
                                :href="route('admin.users.csv.sample')"
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
                        :href="route('admin.users.create')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white ring-gray-300 transition duration-150 ease-in-out hover:bg-gray-600 focus:border-gray-700 focus:outline-none focus:ring active:bg-gray-700 disabled:opacity-25"
                    >
                        キャンセル
                    </Link>

                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing || !form.csv_file }"
                        :disabled="form.processing || !form.csv_file"
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
