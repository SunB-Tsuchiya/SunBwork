<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminNavigationTabs from '@/Components/AdminNavigationTabs.vue';
import { ref, computed } from 'vue';

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
    const company = props.companies.find(c => c.id == form.company_id);
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
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    CSV一括登録
                </h2>
                <Link :href="route('admin.users.create')" class="text-gray-600 hover:text-gray-900">
                    ← 新規ユーザー登録に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- ナビゲーションタブ -->
                <AdminNavigationTabs active="users" />
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- 説明セクション -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">CSVファイル形式について</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-700 mb-3">CSVファイルは以下の形式で作成してください：</p>
                                <div class="bg-white p-3 rounded border font-mono text-sm">
                                    <div class="text-gray-500 mb-1"># ヘッダー行（必須）</div>
                                    <div class="font-semibold">name,email,password,assignment,user_role</div>
                                    <div class="text-gray-500 mt-2 mb-1"># データ行の例</div>
                                    <div>山田太郎,yamada@example.com,password123,管理者,user</div>
                                    <div>佐藤花子,sato@example.com,password456,進行管理,leader</div>
                                </div>
                                <div class="mt-4 text-sm text-gray-600">
                                    <h4 class="font-semibold mb-2">各列の説明：</h4>
                                    <ul class="space-y-1">
                                        <li><strong>name:</strong> ユーザー名</li>
                                        <li><strong>email:</strong> メールアドレス（重複不可）</li>
                                        <li><strong>password:</strong> パスワード</li>
                                        <li><strong>assignment:</strong> 担当（部署により異なる）</li>
                                        <li><strong>user_role:</strong> システム権限（admin/leader/coordinator/user のいずれか）</li>
                                    </ul>
                                </div>

                                <!-- 自動修正機能の説明 -->
                                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                                    <h4 class="font-semibold text-blue-800 mb-2">🔧 自動修正機能</h4>
                                    <p class="text-sm text-blue-700 mb-3">
                                        以下のような軽微なタイポや表記ゆれは自動的に修正されます：
                                    </p>
                                    <div class="text-xs text-blue-600 space-y-2">
                                        <div>
                                            <strong>担当:</strong> 
                                            「admin」→「管理者」、「operator」→「オペレーター」、「その他」→「そのほか」など
                                        </div>
                                        <div>
                                            <strong>システム権限:</strong> 
                                            「管理者」→「admin」、「リーダー」→「leader」、「コーディネーター」→「coordinator」、「ユーザー」→「user」など
                                        </div>
                                    </div>
                                    <p class="text-xs text-blue-600 mt-2">
                                        ※ 自動修正できない場合はエラーになります
                                    </p>
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
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
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
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">-- 部署を選択してください --</option>
                                    <option v-for="department in selectedCompanyDepartments" :key="department.id" :value="department.id">
                                        {{ department.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.department_id" />
                                <p class="mt-1 text-sm text-gray-600">
                                    CSV内のユーザーは全て選択した部署のチームに追加されます
                                </p>
                            </div>

                            <div class="mb-6">
                                <InputLabel for="csv_file" value="CSVファイル" />
                                
                                <div class="mt-2">
                                    <!-- 隠しファイル入力 -->
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept=".csv,.txt"
                                        @change="onFileChange"
                                        class="hidden"
                                    />
                                    
                                    <!-- カスタムファイル選択ボタン -->
                                    <div class="flex items-center space-x-4">
                                        <button
                                            type="button"
                                            @click="selectFile"
                                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:border-blue-300 focus:ring focus:ring-blue-200 active:text-gray-800 active:bg-gray-50 disabled:opacity-25 transition ease-in-out duration-150"
                                        >
                                            📁 ファイルを選択
                                        </button>
                                        
                                        <span v-if="fileName" class="text-sm text-gray-600">
                                            選択されたファイル: {{ fileName }}
                                        </span>
                                        <span v-else class="text-sm text-gray-500">
                                            ファイルが選択されていません
                                        </span>
                                    </div>
                                </div>
                                
                                <InputError class="mt-2" :message="form.errors.csv_file" />
                            </div>

                            <!-- アクションボタン -->
                            <div class="flex items-center justify-between">
                                <Link 
                                    :href="route('admin.users.create')" 
                                    class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
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
                </div>
            </div>
    </AppLayout>
</template>
