<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import Modal from '@/Components/Modal.vue';
import BroadProjectForm from './BroadProjectForm.vue';
import { treeRows, broadProjectColor } from '@/utils/broadProjectTree';

const props = defineProps({
    projects: { type: Array, default: () => [] },
    routePrefix: { type: String, default: 'superadmin.broad_projects' },
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
            <p class="text-sm text-gray-600">部署で使う案件の分類です。グループを開くと中の分類を確認できます。名前や親グループは後から編集できます。</p>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="query" type="search" maxlength="100" placeholder="名前・親グループ・取引先・年度" aria-label="大まかな案件を検索" class="w-full rounded border px-3 py-2 text-sm sm:w-80" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="showInactive" type="checkbox" />休止中も表示</label>
                </div>
                <button type="button" class="rounded px-4 py-2 text-sm font-medium" :class="broadProjectColor(routePrefix)" @click="openCreate()">大まかな案件を追加</button>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-4 text-sm">
                <span class="text-gray-500">登録 {{ projects.length }}件 ／ 表示 {{ rows.length }}件</span>
                <button type="button" class="text-blue-700 underline" @click="expanded = new Set(projects.map(p => p.id))">すべて開く</button>
                <button type="button" class="text-blue-700 underline" @click="expanded = new Set()">すべて閉じる</button>
            </div>
            <p v-if="query.trim()" class="mt-2 text-xs text-gray-500">検索中は一致した分類と親グループを開いて表示します。</p>
            <p v-if="!rows.length" class="py-8 text-center text-sm text-gray-500">該当する大まかな案件はありません。</p>
            <ul v-else class="mt-3 divide-y divide-gray-200">
                <li v-for="project in rows" :key="project.id" class="flex flex-wrap items-center justify-between gap-3 py-3 hover:bg-gray-50">
                    <div class="min-w-0 flex-1" :style="{ paddingLeft: `${project.depth * 20}px` }">
                        <div class="flex items-start gap-2">
                            <button v-if="project.childCount" type="button" class="w-6 shrink-0 rounded border text-gray-600" :aria-expanded="project.open" :aria-label="`${project.name}を${project.open ? '閉じる' : '開く'}`" @click="toggle(project.id)">{{ project.open ? '−' : '＋' }}</button>
                            <span v-else class="w-6 shrink-0" aria-hidden="true"></span>
                            <div class="min-w-0 break-words">
                                <span class="font-medium">{{ project.name }}</span>
                                <span v-if="project.childCount" class="ml-2 text-xs text-gray-500">{{ project.childCount }}分類</span>
                                <span v-if="!project.is_active" class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">休止中</span>
                                <span v-else-if="!project.selectable" class="ml-2 text-xs text-gray-500">親グループが休止中</span>
                                <p v-if="project.client?.name || project.fiscal_year" class="mt-1 text-sm text-gray-500">{{ [project.client?.name, project.fiscal_year ? `${project.fiscal_year}年度` : null].filter(Boolean).join(' ／ ') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-4 text-sm">
                        <button v-if="project.depth < 2" type="button" class="text-blue-700 underline" :aria-label="`${project.name}の下に追加`" @click="openCreate(project.id)">下に追加</button>
                        <button type="button" class="text-blue-700 underline" :aria-label="`${project.name}を編集`" @click="openEdit(project)">編集</button>
                    </div>
                </li>
            </ul>
        </div>
        <Modal :show="Boolean(modal)" max-width="lg" aria-label="大まかな案件の登録・編集" @close="modal = null">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h3 class="text-lg font-semibold">{{ modal === 'edit' ? '大まかな案件を編集' : '大まかな案件を追加' }}</h3>
                <button type="button" class="text-sm text-gray-600 underline" @click="modal = null">閉じる</button>
            </div>
            <div class="p-6">
                <BroadProjectForm v-if="modal" :key="`${modal}-${editingProject?.id || initialParentId || 0}`" :project="editingProject" :projects="projects" :initial-parent-id="initialParentId" :route-prefix="routePrefix" @saved="modal = null" @cancel="modal = null" />
            </div>
        </Modal>
    </AppLayout>
</template>
