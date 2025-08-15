
<template>
  <AppLayout title="プロジェクトジョブ作成">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-2xl mx-auto p-6 bg-white rounded shadow">
      <h1 class="text-2xl font-bold mb-6">プロジェクトジョブ作成</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block mb-1 font-semibold">伝票番号</label>
          <input v-model="form.jobcode" type="text" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">案件タイトル</label>
          <input v-model="form.name" type="text" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">担当ユーザーID</label>
          <input v-model="form.user_id" type="number" class="w-full border rounded px-3 py-2 bg-gray-100" readonly />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">クライアントID</label>
          <div class="flex gap-2 items-center">
            <input v-model="form.client_id" type="number" class="w-32 border rounded px-3 py-2 bg-gray-100" readonly />
            <button type="button" class="bg-blue-100 text-blue-700 px-3 py-2 rounded" @click="openClientModal">検索</button>
          </div>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">詳細</label>
          <textarea v-model="form.detail" class="w-full border rounded px-3 py-2" rows="3"></textarea>
        </div>
        <div class="mb-4 flex gap-4">
          <button type="button" class="bg-blue-100 text-blue-700 px-4 py-2 rounded" @click="goSchedule">スケジュール設定</button>
          <button type="button" class="bg-green-100 text-green-700 px-4 py-2 rounded" @click="goTeammember">チームメンバー設定</button>
        </div>
        <div class="mt-6">
          <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">作成</button>
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
            <input v-model="clientSearch.id" type="number" placeholder="IDを入力" class="border rounded px-2 py-1" />
            <button class="ml-2 bg-blue-500 text-white px-2 py-1 rounded" @click="searchClientById">検索</button>
          </div>
          <div v-if="clientSearchMode === 'name'" class="mb-2">
            <input v-model="clientSearch.name" type="text" placeholder="名前を入力" class="border rounded px-2 py-1" />
            <button class="ml-2 bg-blue-500 text-white px-2 py-1 rounded" @click="searchClientByName">検索</button>
          </div>
          <div v-if="clientSearchMode === 'list'">
            <button class="mb-2 bg-gray-200 px-2 py-1 rounded" @click="openClientListModal">一覧を表示</button>
          </div>
          <div v-if="clientSearchResult">
            <div class="mt-2">検索結果: <span class="font-bold">{{ clientSearchResult.id }} {{ clientSearchResult.name }}</span>
              <button class="ml-2 bg-green-500 text-white px-2 py-1 rounded" @click="selectClient(clientSearchResult)">選択</button>
            </div>
          </div>
        </template>
        <template #footer>
          <button class="bg-gray-300 px-4 py-2 rounded" @click="closeClientModal">閉じる</button>
        </template>
      </DialogModal>

      <!-- クライアント一覧モーダル -->
      <DialogModal :show="showClientListModal" @close="closeClientListModal">
        <template #title>クライアント一覧</template>
        <template #content>
          <table class="min-w-full">
            <thead>
              <tr><th>ID</th><th>会社名</th></tr>
            </thead>
            <tbody>
              <tr v-for="client in clientList" :key="client.id" @click="selectClient(client)" class="hover:bg-blue-100 cursor-pointer">
                <td>{{ client.id }}</td>
                <td>{{ client.name }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="clientList.length === 0" class="text-gray-500 py-4">クライアントがありません</div>
        </template>
        <template #footer>
          <button class="bg-gray-300 px-4 py-2 rounded" @click="closeClientListModal">閉じる</button>
        </template>
      </DialogModal>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';

const page = usePage();
const form = useForm({
  jobcode: '',
  name: '',
  user_id: page.props.auth.user.id,
  client_id: '',
  detail: '',
  teammember: null,
  schedule: null,
});

// クライアント検索用
const showClientModal = ref(false);
const showClientListModal = ref(false);
const clientSearchMode = ref('id');
const clientSearch = ref({ id: '', name: '' });
const clientSearchResult = ref(null);
const clientList = ref([]);

function openClientModal() {
  // クライアント一覧を即取得して空ならアラート
  fetch('/api/clients')
    .then(res => res.json())
    .then(data => {
      if (data.length === 0) {
        alert('クライアントが登録されていません。\n進行管理の権限ではクライアント作成はできません。\nチームリーダーに作成を依頼してください。');
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
    .then(res => res.json())
    .then(data => {
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
    .then(res => res.ok ? res.json() : null)
    .then(data => { clientSearchResult.value = data; });
}
function searchClientByName() {
  if (!clientSearch.value.name) return;
  fetch(`/api/clients?name=${encodeURIComponent(clientSearch.value.name)}`)
    .then(res => res.ok ? res.json() : null)
    .then(data => { clientSearchResult.value = data && data.length ? data[0] : null; });
}
function selectClient(client) {
  form.client_id = client.id;
  closeClientModal();
  closeClientListModal();
}

function submit() {
  form.post(route('coordinator.project_jobs.store'));
}

function goSchedule() {
  router.visit(route('coordinator.project_jobs.schedule'));
}
function goTeammember() {
  router.visit(route('coordinator.project_jobs.teammember'));
}
</script>

<style scoped>
/* 必要に応じてスタイル追加 */
</style>
