<!--
 この画面は「プロジェクトジョブ登録フロー」の詳細登録用（step1）です。
 1. 伝票番号・案件タイトル・担当ユーザーID・クライアントID・詳細のみを登録。
 2. 登録後はshow画面へ遷移し、確認・案内を出す。
 3. confirmダイアログで「続いてメンバーを登録しますか？」を表示し、OKならProjectTeamMember/indexへ遷移。
 4. teammember/scheduleはnullで送信し、あとで登録。
-->

<template>
    <AppLayout title="プロジェクトジョブ作成">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-6 text-2xl font-bold">プロジェクトジョブ作成</h1>
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">伝票番号</label>
                    <input
                        v-model="form.jobcode"
                        type="text"
                        class="w-full rounded border px-3 py-2"
                        required
                        pattern="^[0-9\-]+$"
                        inputmode="text"
                        title="数字とハイフンのみ入力できます"
                        @input="validateJobcode"
                    />
                    <div v-if="jobcodeError" class="mt-1 text-sm text-red-600">{{ jobcodeError }}</div>
                    <div v-if="form.errors.jobcode" class="mt-1 text-sm text-red-600">{{ form.errors.jobcode }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">案件タイトル</label>
                    <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2" required />
                    <div v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">担当ユーザー</label>
                    <input v-model="form.user_name" type="text" class="w-full rounded border bg-gray-100 px-3 py-2" readonly />
                    <div v-if="form.errors.user_id || form.errors.coordinator_id" class="mt-1 text-sm text-red-600">
                        {{ form.errors.user_id || form.errors.coordinator_id }}
                    </div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">クライアント</label>
                    <div class="flex items-center gap-2">
                        ID:<input v-model="form.client_id" type="number" class="w-16 rounded border bg-gray-100 px-3 py-2" readonly />
                        <input v-model="form.client_name" type="text" class="w-60 rounded border bg-gray-100 px-3 py-2" readonly />
                        <button type="button" class="rounded bg-blue-100 px-3 py-2 text-blue-700" @click="openClientModal">検索</button>
                    </div>
                    <div v-if="form.errors.client_id" class="mt-1 text-sm text-red-600">{{ form.errors.client_id }}</div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-3 py-2" rows="3"></textarea>
                    <div v-if="form.errors.detail" class="mt-1 text-sm text-red-600">{{ form.errors.detail }}</div>
                </div>

                <!-- メンバー・スケジュール登録は後続ステップで実装 -->
                <div class="mt-6 flex gap-4">
                    <button type="submit" class="rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-700">作成</button>
                    <!-- Optional: if this page was opened with ?project_job_id=..., allow direct open of schedule -->
                    <button v-if="projectJobId" type="button" class="rounded bg-blue-100 px-4 py-2 text-blue-700" @click="goSchedule">
                        スケジュール設定
                    </button>
                    <!-- <button type="button" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600" @click="clearFormAndRoute">情報をクリアする</button> -->
                </div>
            </form>

            <!-- クライアント検索モーダル -->
            <DialogModal :show="showClientModal" @close="closeClientModal">
                <template #title>クライアント検索</template>
                <template #content>
                    <div class="mb-2 flex gap-4">
                        <label><input type="radio" value="id" v-model="clientSearchMode" /> IDで検索</label>
                        <label><input type="radio" value="name" v-model="clientSearchMode" /> 名前で検索</label>
                        <label><input type="radio" value="list" v-model="clientSearchMode" /> 一覧から検索</label>
                    </div>
                    <div v-if="clientSearchMode === 'id'" class="mb-2">
                        <input v-model="clientSearch.id" type="number" placeholder="IDを入力" class="rounded border px-2 py-1" />
                        <button class="ml-2 rounded bg-blue-500 px-2 py-1 text-white" @click="searchClientById">検索</button>
                    </div>
                    <div v-if="clientSearchMode === 'name'" class="mb-2">
                        <input v-model="clientSearch.name" type="text" placeholder="名前を入力" class="rounded border px-2 py-1" />
                        <button class="ml-2 rounded bg-blue-500 px-2 py-1 text-white" @click="searchClientByName">検索</button>
                    </div>
                    <!-- clientSearchModeが'list'のときはボタンを表示せず、一覧を自動で出す -->
                    <div v-if="clientSearchResult">
                        <div class="mt-2">
                            検索結果: <span class="font-bold">{{ clientSearchResult.id }} {{ clientSearchResult.name }}</span>
                            <button class="ml-2 rounded bg-green-500 px-2 py-1 text-white" @click="selectClient(clientSearchResult)">選択</button>
                        </div>
                    </div>
                </template>
                <template #footer>
                    <button class="rounded bg-gray-300 px-4 py-2" @click="closeClientModal">閉じる</button>
                </template>
            </DialogModal>

            <!-- クライアント一覧モーダル -->
            <DialogModal :show="showClientListModal" @close="closeClientListModal">
                <template #title>クライアント一覧</template>
                <template #content>
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>会社名</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="client in clientList" :key="client.id" @click="selectClient(client)" class="cursor-pointer hover:bg-blue-100">
                                <td>{{ client.id }}</td>
                                <td>{{ client.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="clientList.length === 0" class="py-4 text-gray-500">クライアントがありません</div>
                </template>
                <template #footer>
                    <button class="rounded bg-gray-300 px-4 py-2" @click="closeClientListModal">閉じる</button>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();
const form = useForm({
    jobcode: '',
    title: '',
    user_id: page.props.auth.user.id,
    user_name: page.props.auth.user.name,
    client_id: '',
    client_name: '',
    detail: '',
    teammember: null,
    schedule: null,
});

const jobcodeError = ref('');
function validateJobcode(e) {
    const val = e.target.value;
    if (/^[0-9\-]*$/.test(val)) {
        jobcodeError.value = '';
    } else {
        jobcodeError.value = '数字とハイフンのみ入力できます';
    }
}

// クライアント検索用
const showClientModal = ref(false);
const showClientListModal = ref(false);
const clientSearchMode = ref('id');
const clientSearch = ref({ id: '', name: '' });
const clientSearchResult = ref(null);
const clientList = ref([]);

// clientSearchModeが'list'になったら自動で一覧モーダルを開く
watch(clientSearchMode, (val) => {
    if (val === 'list') {
        openClientListModal();
    }
});

function openClientModal() {
    // クライアント一覧を即取得して空ならアラート
    fetch('/api/clients')
        .then((res) => res.json())
        .then((data) => {
            if (data.length === 0) {
                alert(
                    'クライアントが登録されていません。\n進行管理の権限ではクライアント作成はできません。\nチームリーダーに作成を依頼してください。',
                );
            } else {
                showClientModal.value = true;
                clientSearchResult.value = null;
            }
        });
}
function closeClientModal() {
    showClientModal.value = false;
}
function openClientListModal() {
    // クライアント一覧取得APIを呼ぶ想定
    fetch('/api/clients')
        .then((res) => res.json())
        .then((data) => {
            clientList.value = data;
            showClientListModal.value = true;
        });
}
function closeClientListModal() {
    showClientListModal.value = false;
}
function searchClientById() {
    if (!clientSearch.value.id) return;
    fetch(`/api/clients/${clientSearch.value.id}`)
        .then((res) => (res.ok ? res.json() : null))
        .then((data) => {
            clientSearchResult.value = data;
        });
}
function searchClientByName() {
    if (!clientSearch.value.name) return;
    fetch(`/api/clients?name=${encodeURIComponent(clientSearch.value.name)}`)
        .then((res) => (res.ok ? res.json() : null))
        .then((data) => {
            clientSearchResult.value = data && data.length ? data[0] : null;
        });
}
function selectClient(client) {
    form.client_id = client.id;
    form.client_name = client.name;
    closeClientModal();
    closeClientListModal();
}

// エラー項目の日本語ラベル
const errorLabels = {
    jobcode: '伝票番号',
    title: '案件タイトル',
    user_id: '担当ユーザーID',
    user_name: '担当ユーザー',
    coordinator_id: '担当ユーザー',
    client_id: 'クライアントID',
    client_name: 'クライアント名',
    detail: '詳細',
};

function submit() {
    // teammember/scheduleはnullで送信
    form.teammember = null;
    form.schedule = null;
    // submit to server; server redirects to index and Index.vue handles follow-up prompts
    form.post(route('coordinator.project_jobs.store'), {
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => {
            // 重大なバリデーションエラー時はalertも出す
            if (errors && Object.keys(errors).length > 0) {
                let msg = '入力内容に誤りがあります。\n';
                for (const key in errors) {
                    const label = errorLabels[key] || key;
                    msg += `・${label}: ${errors[key]}\n`;
                }
                alert(msg);
            }
        },
    });
}

// If this page was opened with ?project_job_id=123, show a quick link to the calendar PoC
const projectJobId = (() => {
    try {
        const params = new URLSearchParams(window.location.search);
        return params.get('project_job_id');
    } catch (e) {
        return null;
    }
})();

function goSchedule() {
    if (!projectJobId) return;
    router.visit(route('coordinator.project_jobs.schedule', { projectJob: projectJobId }));
}

// スケジュール・メンバー登録は後続ステップで実装
</script>

<style scoped>
/* 必要に応じてスタイル追加 */
</style>
