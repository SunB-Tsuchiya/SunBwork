<script setup>
import axios from 'axios';
import { parentOptions, broadProjectColor } from '@/utils/broadProjectTree';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    project: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    initialParentId: { type: Number, default: null },
    routePrefix: { type: String, default: 'superadmin.broad_projects' },
    allowStatus: { type: Boolean, default: true },
});
const emit = defineEmits(['saved', 'cancel']);
const isEditing = computed(() => Boolean(props.project?.id));
const clientSearch = ref(props.project?.client?.name || '');
const clientOptions = ref([]);
const clientLoading = ref(false);
const clientPickerOpen = ref(false);
const clientPickerRef = ref(null);
const clientInputId = isEditing.value ? 'broad-project-edit-client' : 'broad-project-create-client';
const clientOptionsId = `${clientInputId}-options`;
const form = useForm({
    name: props.project?.name || '',
    parent_id: props.project?.parent_id ?? props.initialParentId,
    client_id: props.project?.client_id ?? null,
    fiscal_year: props.project?.fiscal_year ?? null,
    is_active: props.project?.is_active ?? true,
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
    if (clientSearch.value.trim() && !form.client_id) searchClients();
}

function chooseClient(client) {
    closeClientOptions();
    form.client_id = client.id;
    clientSearch.value = client.name;
}

function clearClient() {
    closeClientOptions();
    form.client_id = null;
    clientSearch.value = '';
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (isEditing.value) emit('saved');
            else {
                form.reset();
                form.clearErrors();
                clearClient();
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
        <label class="text-sm sm:col-span-2">親グループ（任意）
            <select v-model="form.parent_id" class="mt-1 w-full rounded border p-2">
                <option :value="null">なし（大グループ）</option>
                <option v-for="parent in parentOptions(projects, project?.id)" :key="parent.id" :value="parent.id">{{ parent.path }}{{ !parent.selectable ? '［休止中］' : '' }}</option>
            </select>
            <span v-if="form.errors.parent_id" class="block text-red-700">{{ form.errors.parent_id }}</span>
            <span class="mt-1 block text-xs text-gray-500">分類は3段まで。親を休止すると、その中の分類も新規入力の候補から外れます。</span>
        </label>
        <label class="text-sm">名前（必須）
            <input v-model="form.name" type="text" maxlength="255" required class="mt-1 w-full rounded border p-2" />
            <span v-if="form.errors.name" class="block text-red-700">{{ form.errors.name }}</span>
        </label>
        <label class="text-sm">年度（任意）
            <input v-model="form.fiscal_year" type="number" min="1900" max="2100" class="mt-1 w-full rounded border p-2" placeholder="例：2026" />
            <span v-if="form.errors.fiscal_year" class="block text-red-700">{{ form.errors.fiscal_year }}</span>
        </label>
        <div ref="clientPickerRef" class="relative text-sm sm:col-span-2">
            <label :for="clientInputId">取引先（任意）</label>
            <div class="mt-1 flex gap-2">
                <input :id="clientInputId" v-model="clientSearch" type="search" autocomplete="off" placeholder="名前で検索" :aria-expanded="clientPickerOpen" :aria-controls="clientOptionsId" class="w-full rounded border p-2" @input="form.client_id = null; searchClients()" @focus="focusClientSearch" />
                <button v-if="form.client_id" type="button" class="shrink-0 text-sm text-blue-700 underline" @click="clearClient">解除</button>
            </div>
            <span v-if="form.errors.client_id" class="block text-red-700">{{ form.errors.client_id }}</span>
            <p v-if="clientSearch && !form.client_id" class="mt-1 text-xs text-gray-500">取引先を登録する場合は候補から選択してください。</p>
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
        <label v-if="allowStatus" class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" />簡易予定で選択できる</label>
        <div class="flex justify-end gap-3">
            <button v-if="isEditing" type="button" class="text-sm text-gray-600 underline" @click="emit('cancel')">キャンセル</button>
            <button type="submit" :disabled="form.processing" class="rounded px-4 py-2 text-sm font-medium disabled:opacity-50" :class="broadProjectColor(routePrefix)">{{ isEditing ? '変更を保存' : '登録する' }}</button>
        </div>
    </form>
</template>
