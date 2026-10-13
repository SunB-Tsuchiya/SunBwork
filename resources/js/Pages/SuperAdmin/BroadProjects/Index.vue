<script setup>
import axios from 'axios';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps({ projects: { type: Array, default: () => [] } });

const editingId = ref(null);
const clientSearch = ref('');
const clientOptions = ref([]);
const clientLoading = ref(false);
const form = useForm({ name: '', client_id: null, fiscal_year: null, is_active: true });
let clientRequest = 0;

function reset() {
    clientRequest++;
    clientLoading.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    clientSearch.value = '';
    clientOptions.value = [];
}

function edit(project) {
    clientRequest++;
    clientLoading.value = false;
    editingId.value = project.id;
    form.name = project.name;
    form.client_id = project.client_id;
    form.fiscal_year = project.fiscal_year;
    form.is_active = project.is_active;
    form.clearErrors();
    clientSearch.value = project.client?.name || '';
    clientOptions.value = [];
}

async function searchClients() {
    const request = ++clientRequest;
    clientLoading.value = true;
    try {
        const { data } = await axios.get(route('superadmin.broad_projects.clients'), { params: { q: clientSearch.value } });
        if (request === clientRequest) clientOptions.value = data;
    } catch {
        if (request === clientRequest) clientOptions.value = [];
    } finally {
        if (request === clientRequest) clientLoading.value = false;
    }
}

function chooseClient(client) {
    clientRequest++;
    clientLoading.value = false;
    form.client_id = client.id;
    clientSearch.value = client.name;
    clientOptions.value = [];
}

function clearClient() {
    clientRequest++;
    clientLoading.value = false;
    form.client_id = null;
    clientSearch.value = '';
    clientOptions.value = [];
}

function save() {
    const options = { preserveScroll: true, onSuccess: reset };
    if (editingId.value) form.put(route('superadmin.broad_projects.update', { broadProject: editingId.value }), options);
    else form.post(route('superadmin.broad_projects.store'), options);
}
</script>

<template>
    <AppLayout title="大まかな案件の管理">
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">大まかな案件の管理</h2></template>
        <div class="space-y-5">
            <div class="rounded bg-white p-5 shadow">
                <p class="text-sm text-gray-600">細かな案件を作る前に、その日の作業先として使う大まかな案件を登録します。情報出版の試用向けです。</p>
                <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="save">
                    <label class="text-sm">名前（必須）
                        <input v-model="form.name" type="text" maxlength="255" required class="mt-1 w-full rounded border p-2" />
                        <span v-if="form.errors.name" class="block text-red-700">{{ form.errors.name }}</span>
                    </label>
                    <label class="text-sm">年度（任意）
                        <input v-model="form.fiscal_year" type="number" min="1900" max="2100" class="mt-1 w-full rounded border p-2" placeholder="例：2026" />
                        <span v-if="form.errors.fiscal_year" class="block text-red-700">{{ form.errors.fiscal_year }}</span>
                    </label>
                    <div class="relative text-sm sm:col-span-2">
                        <label for="broad-project-client">取引先（任意）</label>
                        <div class="mt-1 flex gap-2">
                            <input id="broad-project-client" v-model="clientSearch" type="search" autocomplete="off" placeholder="名前で検索" class="w-full rounded border p-2" @input="form.client_id = null; searchClients()" @focus="searchClients" />
                            <button v-if="form.client_id" type="button" class="shrink-0 text-sm text-blue-700 underline" @click="clearClient">解除</button>
                        </div>
                        <span v-if="form.errors.client_id" class="block text-red-700">{{ form.errors.client_id }}</span>
                        <p v-if="clientSearch && !form.client_id" class="mt-1 text-xs text-gray-500">取引先を登録する場合は候補から選択してください。</p>
                        <div v-if="clientOptions.length" class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded border bg-white shadow">
                            <button v-for="client in clientOptions" :key="client.id" type="button" class="block w-full px-3 py-2 text-left hover:bg-yellow-50" @click="chooseClient(client)">{{ client.name }}</button>
                        </div>
                        <p v-else-if="clientLoading" class="text-xs text-gray-500">検索中...</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" />簡易予定で選択できる</label>
                    <div class="flex justify-end gap-3">
                        <button v-if="editingId" type="button" class="text-sm text-gray-600 underline" @click="reset">編集をやめる</button>
                        <button type="submit" :disabled="form.processing" class="rounded bg-yellow-500 px-4 py-2 text-sm font-medium text-gray-900 disabled:opacity-50">{{ editingId ? '変更を保存' : '登録する' }}</button>
                    </div>
                </form>
            </div>
            <div class="rounded bg-white p-5 shadow">
                <h3 class="font-semibold">登録済みの大まかな案件</h3>
                <p v-if="!projects.length" class="mt-3 text-sm text-gray-500">まだ登録されていません。</p>
                <ul v-else class="mt-3 divide-y">
                    <li v-for="project in projects" :key="project.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div><span class="font-medium">{{ project.name }}</span><span class="ml-2 text-gray-500">{{ [project.client?.name, project.fiscal_year ? `${project.fiscal_year}年度` : null].filter(Boolean).join(' / ') }}</span><span v-if="!project.is_active" class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs">休止中</span></div>
                        <button type="button" class="shrink-0 text-blue-700 underline" @click="edit(project)">編集</button>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
