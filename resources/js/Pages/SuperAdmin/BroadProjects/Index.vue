<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import Modal from '@/Components/Modal.vue';
import BroadProjectForm from './BroadProjectForm.vue';
import { treeRows, broadProjectColor, largeGroups, siblings, shiftOrder } from '@/utils/broadProjectTree';

const props = defineProps({
    projects: { type: Array, default: () => [] },
    routePrefix: { type: String, default: 'superadmin.broad_projects' },
    canManage: { type: Boolean, default: false },
});
const query = ref('');
const showInactive = ref(false);
const expanded = ref(new Set());
const rows = computed(() => treeRows(props.projects, query.value, showInactive.value, expanded.value));
const modal = ref(null);
const editingProject = ref(null);
const initialParentId = ref(null);
function openCreate(parentId = null) {
    editingProject.value = null; initialParentId.value = parentId; modal.value = 'create';
}
function openEdit(project) { editingProject.value = project; modal.value = 'edit'; }
const moveForm = useForm({parent_id: null});
const orderForm = useForm({parent_id: null, current_ids: [], ordered_ids: []});
const orderRows = computed(() => orderForm.ordered_ids.map(id => props.projects.find(p => p.id === id)).filter(Boolean));
const modalTitle = computed(() => ({
    edit: '名前などを編集', create: initialParentId.value ? '中グループを追加' : '大グループを追加',
    move: '中グループを移動', order: orderForm.parent_id ? '中グループの並べ替え' : '大グループの並べ替え',
})[modal.value] || '');
function openMove(project) {
    editingProject.value = project;
    moveForm.parent_id = null; moveForm.clearErrors(); modal.value = 'move';
}
function move() {
    moveForm.post(route(`${props.routePrefix}.move`, {broadProject: editingProject.value.id}), {
        preserveScroll: true, onSuccess: () => {modal.value = null;},
    });
}
function openOrder(parentId = null) {
    orderForm.clearErrors(); orderForm.parent_id = parentId;
    orderForm.current_ids = siblings(props.projects, parentId).map(p => p.id);
    orderForm.ordered_ids = [...orderForm.current_ids]; modal.value = 'order';
}
function saveOrder() {
    orderForm.post(route(`${props.routePrefix}.reorder`), {preserveScroll: true, onSuccess: () => {modal.value = null;}});
}
function toggle(id) {
    const next = new Set(expanded.value);
    if (next.has(id)) next.delete(id); else next.add(id);
    expanded.value = next;
}
</script>

<template>
    <AppLayout title="大まかな案件">
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">大まかな案件</h2></template>
        <template v-if="!routePrefix.startsWith('superadmin.')" #tabs>
            <UserNavigationTabs v-if="routePrefix.startsWith('user.')" active="broad_projects" />
            <CoordinatorNavigationTabs v-else-if="routePrefix.startsWith('coordinator.')" active="broad_projects" />
            <LeaderNavigationTabs v-else active="broad_projects" />
        </template>
        <div class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-600">部署で使う案件の分類です。グループを開くと中の分類を確認できます。大グループと、その中の中グループで整理します。名前の編集・中グループの移動・部署共通の並べ替えは別々に操作できます。</p>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="query" type="search" maxlength="100" placeholder="名前・親グループ・取引先・年度" aria-label="大まかな案件を検索" class="w-full rounded border px-3 py-2 text-sm sm:w-80" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="showInactive" type="checkbox" />休止中も表示</label>
                </div>
                <button v-if="canManage" type="button" class="rounded px-4 py-2 text-sm font-medium" :class="broadProjectColor(routePrefix)" @click="openCreate()">大グループを追加</button>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-4 text-sm">
                <span class="text-gray-500">登録 {{ projects.length }}件 ／ 表示 {{ rows.length }}件</span>
                <button type="button" class="text-blue-700 underline" @click="expanded = new Set(projects.map(p => p.id))">すべて開く</button>
                <button type="button" class="text-blue-700 underline" @click="expanded = new Set()">すべて閉じる</button>
            </div>
            <button v-if="canManage && largeGroups(projects).length > 1" type="button" class="mt-3 text-sm text-blue-700 underline" @click="openOrder()">大グループの並べ替え</button>
            <p v-if="query.trim()" class="mt-2 text-xs text-gray-500">検索中は一致した分類と親グループを開いて表示します。</p>
            <p v-if="!rows.length" class="py-8 text-center text-sm text-gray-500">該当する大まかな案件はありません。</p>
            <ul v-else class="mt-3 divide-y divide-gray-200">
                <li v-for="project in rows" :key="project.id" class="flex flex-wrap items-center justify-between gap-3 py-3 hover:bg-gray-50">
                    <div class="min-w-0 flex-1" :style="{ paddingLeft: `${project.depth * 20}px` }">
                        <div class="flex items-start gap-2">
                            <button v-if="project.childCount" type="button" class="w-6 shrink-0 rounded border text-gray-600" :aria-expanded="project.open" :aria-label="`${project.name}を${project.open ? '閉じる' : '開く'}`" @click="toggle(project.id)">{{ project.open ? '−' : '＋' }}</button>
                            <span v-else class="w-6 shrink-0" aria-hidden="true"></span>
                            <div class="min-w-0 break-words">
                                <span class="mr-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ project.parent_id ? '中' : '大' }}</span><span class="font-medium">{{ project.name }}</span>
                                <span v-if="project.childCount" class="ml-2 text-xs text-gray-500">{{ project.childCount }}分類</span>
                                <span v-if="!project.is_active" class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">休止中</span>
                                <span v-else-if="!project.selectable" class="ml-2 text-xs text-gray-500">親グループが休止中</span>
                                <p v-if="project.client?.name || project.fiscal_year" class="mt-1 text-sm text-gray-500">{{ [project.client?.name, project.fiscal_year ? `${project.fiscal_year}年度` : null].filter(Boolean).join(' ／ ') }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="canManage" class="flex shrink-0 flex-wrap gap-4 text-sm">
                        <button v-if="!project.parent_id" type="button" class="text-blue-700 underline" :aria-label="`${project.name}に中グループを追加`" @click="openCreate(project.id)">中グループを追加</button>
                        <button v-if="!project.parent_id && siblings(projects, project.id).length > 1" type="button" class="text-blue-700 underline" :aria-label="`${project.name}内の並べ替え`" @click="openOrder(project.id)">中グループの並べ替え</button>
                        <button v-if="project.parent_id" type="button" class="text-blue-700 underline" :aria-label="`${project.name}を別の大グループへ移動`" @click="openMove(project)">グループ移動</button>
                        <button type="button" class="text-blue-700 underline" :aria-label="`${project.name}を編集`" @click="openEdit(project)">編集</button>
                    </div>
                </li>
            </ul>
        </div>
        <Modal :show="Boolean(modal)" max-width="lg" :aria-label="modalTitle" @close="modal = null">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h3 class="text-lg font-semibold">{{ modalTitle }}</h3>
                <button type="button" class="text-sm text-gray-600 underline" @click="modal = null">閉じる</button>
            </div>
            <div class="p-6">
                <BroadProjectForm v-if="modal === 'edit' || modal === 'create'" :key="`${modal}-${editingProject?.id || initialParentId || 0}`" :project="editingProject" :projects="projects" :initial-parent-id="initialParentId" :route-prefix="routePrefix" @saved="modal = null" @cancel="modal = null" />
                <form v-if="modal === 'move'" class="space-y-4" @submit.prevent="move">
                    <p class="text-sm">「{{ editingProject.name }}」を別の大グループへ移動します。名前や作業の参照は保持します。</p>
                    <label class="block text-sm">移動先の大グループ
                        <select v-model="moveForm.parent_id" required class="mt-1 w-full rounded border p-2">
                            <option :value="null" disabled>大グループを選択</option>
                            <option v-for="parent in largeGroups(projects).filter(p => p.id !== editingProject.parent_id)" :key="parent.id" :value="parent.id">{{ parent.path }}{{ !parent.selectable ? '［休止中］' : '' }}</option>
                        </select>
                    </label>
                    <p v-if="moveForm.errors.parent_id || moveForm.errors.name" class="text-sm text-red-700">{{ moveForm.errors.parent_id || moveForm.errors.name }}</p>
                    <div class="flex justify-end"><button type="submit" :disabled="moveForm.processing" class="rounded px-4 py-2 text-sm disabled:opacity-50" :class="broadProjectColor(routePrefix)">移動する</button></div>
                </form>
                <form v-if="modal === 'order'" class="space-y-4" @submit.prevent="saveOrder">
                    <p class="text-sm text-gray-600">部署全員に共通の並び順です。休止中の分類も含めて表示しています。</p>
                    <p v-if="orderForm.parent_id" class="font-medium">{{ projects.find(p => p.id === orderForm.parent_id)?.name }}</p>
                    <ul class="max-h-96 divide-y overflow-y-auto rounded border">
                        <li v-for="(project, index) in orderRows" :key="project.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <span>{{ project.name }}{{ project.fiscal_year ? `（${project.fiscal_year}年度）` : '' }}<span v-if="!project.is_active" class="ml-1 text-gray-500">［休止中］</span></span>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" :disabled="index === 0 || orderForm.processing" :aria-label="`${project.name}を上へ`" class="rounded border px-2 py-1 disabled:opacity-30" @click="orderForm.ordered_ids = shiftOrder(orderForm.ordered_ids, index, -1)">↑</button>
                                <button type="button" :disabled="index === orderRows.length - 1 || orderForm.processing" :aria-label="`${project.name}を下へ`" class="rounded border px-2 py-1 disabled:opacity-30" @click="orderForm.ordered_ids = shiftOrder(orderForm.ordered_ids, index, 1)">↓</button>
                            </div>
                        </li>
                    </ul>
                    <p v-if="Object.keys(orderForm.errors).length" class="text-sm text-red-700">{{ Object.values(orderForm.errors)[0] }}</p>
                    <div class="flex justify-end"><button type="submit" :disabled="orderForm.processing" class="rounded px-4 py-2 text-sm disabled:opacity-50" :class="broadProjectColor(routePrefix)">並び順を保存</button></div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
