<script setup>
import axios from 'axios';
import { broadProjectColor } from '@/utils/broadProjectTree';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { route } from 'ziggy-js';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    project: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    initialParentId: { type: Number, default: null },
    routePrefix: { type: String, default: 'superadmin.broad_projects' },
    inlineCreate: { type: Boolean, default: false },
    initialName: { type: String, default: '' },
    scope: { type: Object, default: null },
    allowStatus: { type: Boolean, default: true },
});
const emit = defineEmits(['saved', 'cancel', 'busy', 'existing']);
const inlineSaving = ref(false);
const inlineError = ref('');
const isEditing = computed(() => Boolean(props.project?.id));
const clientSearch = ref('');
const selectedClients = ref([...(props.project?.clients ?? (props.project?.client ? [props.project.client] : []))]);
const clientOptions = ref([]);
const clientLoading = ref(false);
const clientPickerOpen = ref(false);
const clientPickerRef = ref(null);
const clientInputId = isEditing.value ? 'broad-project-edit-client' : 'broad-project-create-client';
const clientOptionsId = `${clientInputId}-options`;
const form = useForm({
    name: props.project?.name || props.initialName,
    ...(!props.project?.id ? {group_type: props.initialParentId || (props.inlineCreate && props.projects.some(p => p.parent_id === null && p.selectable)) ? 'middle' : 'large', parent_id: props.initialParentId} : {}),
    client_ids: selectedClients.value.map(client => client.id),
    fiscal_year: props.project?.fiscal_year ?? null,
    is_active: props.project?.is_active ?? true,
});
const clientError = computed(() => Object.entries(form.errors).find(([key]) => key === 'client_ids' || key.startsWith('client_ids.'))?.[1]);
const isPaused = computed({
    get: () => !form.is_active,
    set: (value) => { form.is_active = !value; },
});
let clientRequest = 0;

function closeClientOptions() {
    clientRequest++;
    clientLoading.value = false;
    clientPickerOpen.value = false;
    clientOptions.value = [];
}

function onOutsidePointerDown(event) {
    if (clientPickerOpen.value && !clientPickerRef.value?.contains(event.target)) closeClientOptions();
}

function onEscape(event) {
    if (event.key === 'Escape' && clientPickerOpen.value) {
        event.preventDefault();
        event.stopPropagation();
        closeClientOptions();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onOutsidePointerDown);
    document.addEventListener('keydown', onEscape, true);
});
onUnmounted(() => {
    document.removeEventListener('pointerdown', onOutsidePointerDown);
    document.removeEventListener('keydown', onEscape, true);
});

async function searchClients() {
    const request = ++clientRequest;
    const term = clientSearch.value.trim();
    clientOptions.value = [];
    if (!term) {
        clientPickerOpen.value = false;
        clientLoading.value = false;
        return;
    }
    clientPickerOpen.value = true;
    clientLoading.value = true;
    try {
        const { data } = await axios.get(route(`${props.routePrefix}.clients`), { params: { q: term } });
        if (request === clientRequest && clientPickerOpen.value) clientOptions.value = data;
    } catch {
        if (request === clientRequest) clientOptions.value = [];
    } finally {
        if (request === clientRequest) clientLoading.value = false;
    }
}

function focusClientSearch() {
    if (clientSearch.value.trim()) searchClients();
}

function chooseClient(client) {
    closeClientOptions();
    if (!form.client_ids.includes(client.id)) {
        selectedClients.value.push(client);
        form.client_ids.push(client.id);
    }
    clientSearch.value = '';
}

function removeClient(id) {
    selectedClients.value = selectedClients.value.filter(client => client.id !== id);
    form.client_ids = form.client_ids.filter(clientId => clientId !== id);
}

function cancel() {
    if (inlineSaving.value) return;
    if (props.inlineCreate && form.isDirty && !confirm('追加内容を破棄して案件検索に戻りますか？')) return;
    emit('cancel');
}
const similarProjects = computed(() => {
    const name = form.name.normalize('NFKC').trim().toLocaleLowerCase();
    if (!props.inlineCreate || !name) return [];
    return props.projects.filter(p => p.name.normalize('NFKC').toLocaleLowerCase().includes(name)).slice(0, 5);
});
async function save() {
    if (props.inlineCreate) {
        if (inlineSaving.value) return;
        inlineSaving.value = true; inlineError.value = ''; form.clearErrors(); emit('busy', true);
        try {
            const { data } = await axios.post(route('user.work.broad_projects.quick_store'), {
                name: form.name, group_type: form.group_type,
                ...(form.group_type === 'middle' ? { parent_id: form.parent_id } : {}),
                fiscal_year: form.fiscal_year || null, client_ids: form.client_ids, scope: props.scope,
            }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'), Accept: 'application/json' } });
            if (data.scope?.company_id !== props.scope?.company_id || data.scope?.department_id !== props.scope?.department_id || !data.project?.id || !data.project.selectable) throw Error('scope');
            emit('saved', data.project);
        } catch (err) {
            for (const [key, value] of Object.entries(err.response?.data?.errors || {})) form.setError(key, Array.isArray(value) ? value[0] : value);
            inlineError.value = err.response?.data?.message || '保存結果を確認できませんでした。入力は残っています。重複を避けるため、案件検索で登録済みか確認してください。';
        } finally {
            inlineSaving.value = false; emit('busy', false);
        }
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (isEditing.value) emit('saved');
            else {
                form.reset();
                form.clearErrors();
                selectedClients.value = [];
                clientSearch.value = '';
                closeClientOptions();
                emit('saved');
            }
        },
    };
    if (isEditing.value) form.put(route(`${props.routePrefix}.update`, { broadProject: props.project.id }), options);
    else form.post(route(`${props.routePrefix}.store`), options);
}
</script>

<template>
    <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="save">
        <fieldset class="contents" :disabled="inlineSaving">
        <p v-if="inlineError" role="alert" class="text-sm text-red-700 sm:col-span-2">{{ inlineError }}</p>
        <div v-if="inlineCreate" class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
            <label class="text-sm">追加する区分
                <select v-model="form.group_type" :disabled="inlineSaving" class="mt-1 w-full rounded border p-2"><option value="middle" :disabled="!projects.some(p => p.parent_id === null && p.selectable)">中グループ</option><option value="large">大グループを新しく追加</option></select>
            </label>
            <label v-if="form.group_type === 'middle'" class="text-sm">追加先の大グループ（必須）
                <select v-model="form.parent_id" :disabled="inlineSaving" required class="mt-1 w-full rounded border p-2"><option :value="null">選択してください</option><option v-for="parent in projects.filter(p => p.parent_id === null && p.selectable)" :key="parent.id" :value="parent.id">{{ parent.path || parent.name }}</option></select>
            </label>
        </div>
        <p v-else class="text-sm text-gray-600 sm:col-span-2">
            {{ (project?.parent_id || initialParentId) ? '中グループ' : '大グループ' }}
            <span v-if="project?.parent_id || initialParentId"> ／ {{ projects.find(p => p.id === (project?.parent_id || initialParentId))?.name }}</span>
        </p>
        <p v-if="isEditing" class="text-xs text-gray-500 sm:col-span-2">ここでは名前などを編集します。中グループの所属は一覧の「グループ移動」から変更できます。</p>
        <p v-if="form.errors.parent_id || form.errors.group_type" class="text-sm text-red-700 sm:col-span-2">{{ form.errors.parent_id || form.errors.group_type }}</p>
        <label class="text-sm">名前（必須）
            <input v-model="form.name" type="text" maxlength="255" required class="mt-1 w-full rounded border p-2" />
            <span v-if="form.errors.name" class="block text-red-700">{{ form.errors.name }}</span>
        </label>
        <label class="text-sm">年度（任意）
            <input v-model="form.fiscal_year" type="number" min="1900" max="2100" class="mt-1 w-full rounded border p-2" placeholder="例：2026" />
            <span v-if="form.errors.fiscal_year" class="block text-red-700">{{ form.errors.fiscal_year }}</span>
        </label>
        <div ref="clientPickerRef" class="relative text-sm sm:col-span-2">
            <label :for="clientInputId">クライアント（複数選択可・任意）</label>
            <div class="mt-1 flex gap-2">
                <input :id="clientInputId" v-model="clientSearch" type="search" autocomplete="off" placeholder="名前で検索" :aria-expanded="clientPickerOpen" :aria-controls="clientOptionsId" class="w-full rounded border p-2" @input="searchClients" @focus="focusClientSearch" />

            </div>
            <span v-if="clientError" class="block text-red-700">{{ clientError }}</span>
            <p v-if="clientSearch" class="mt-1 text-xs text-gray-500">取引先を登録する場合は候補から選択してください。</p>
            <ul v-if="selectedClients.length" class="mt-2 flex flex-wrap gap-2" aria-label="選択済みクライアント">
                <li v-for="client in selectedClients" :key="client.id" class="flex items-center gap-2 rounded bg-blue-50 px-2 py-1">
                    <span>{{ client.name }}</span><button type="button" class="text-blue-700 underline" :aria-label="`${client.name}を解除`" @click="removeClient(client.id)">解除</button>
                </li>
            </ul>
            <p class="mt-1 text-xs text-gray-500">この分類に関連するクライアントです。仕事を記録する際のクライアントを一つに限定するものではありません。</p>
            <div v-if="clientPickerOpen" :id="clientOptionsId" class="mt-1 w-full rounded border bg-white shadow" :class="{ 'absolute z-10': !isEditing }">
                <div class="flex items-center justify-between border-b px-3 py-2 text-xs text-gray-600">
                    <span>取引先の候補</span>
                    <button type="button" class="text-blue-700 underline" @click="closeClientOptions">閉じる</button>
                </div>
                <div class="max-h-48 overflow-auto">
                    <p v-if="clientLoading" class="px-3 py-2 text-gray-500">検索中...</p>
                    <p v-else-if="!clientOptions.length" class="px-3 py-2 text-gray-500">候補がありません。</p>
                    <button v-for="client in clientOptions" :key="client.id" type="button" class="block w-full px-3 py-2 text-left hover:bg-yellow-50" @click="chooseClient(client)">{{ client.name }}</button>
                </div>
            </div>
        </div>
        <div v-if="allowStatus" class="text-sm sm:col-span-2">
            <label class="flex items-center gap-2"><input v-model="isPaused" type="checkbox" />休止にする</label>
            <p class="mt-1 text-xs text-gray-500">休止すると、新しい作業や案件登録の選択候補から外れます。過去の記録は残ります。大グループを休止すると、その中の中グループも候補から外れます。</p>
        </div>
        <div v-if="similarProjects.length" class="rounded border border-blue-100 bg-blue-50 p-3 text-sm sm:col-span-2">
            <p class="font-medium">登録済みの似た名前</p>
            <div v-for="candidate in similarProjects" :key="candidate.id" class="mt-2 flex items-center justify-between gap-2"><span>{{ candidate.path || candidate.name }} {{ candidate.fiscal_year ? `（${candidate.fiscal_year}年度）` : '' }}{{ candidate.selectable ? '' : '（休止中）' }}</span><button v-if="candidate.selectable" type="button" :disabled="inlineSaving" class="shrink-0 text-blue-700 underline" @click="emit('existing', candidate)">これを選択</button></div>
        </div>
        <div class="flex justify-end gap-3 sm:col-span-2">
            <button v-if="isEditing || inlineCreate" type="button" :disabled="inlineSaving" class="text-sm text-gray-600 underline disabled:opacity-50" @click="cancel">キャンセル</button>
            <button type="submit" :disabled="form.processing || inlineSaving" class="rounded px-4 py-2 text-sm font-medium disabled:opacity-50" :class="broadProjectColor(routePrefix)">{{ inlineSaving ? '保存中…' : inlineCreate ? '追加して選択' : isEditing ? '変更を保存' : '登録する' }}</button>
        </div>
        </fieldset>
    </form>
</template>
