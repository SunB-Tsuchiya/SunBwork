<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ToastUnified from '@/Components/ToastUnified.vue';

const props = defineProps({
  project: { type: Object, required: true }, projects: { type: Array, default: () => [] }, lists: { type: Array, default: () => [] },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  actorListCodes: { type: Array, default: () => [] },
});
const selectedId = ref(props.lists[0]?.id ?? null);
const showDeleted = ref(false);
const edits = reactive({});
const addForm = useForm({ value_list_id: selectedId.value, value: '', actor_key: '' });
const copyForm = useForm({ source_project_id: '', target_project_id: props.project.id });
const selectedList = computed(() => props.lists.find((list) => list.id === selectedId.value) ?? props.lists[0] ?? null);
const visibleItems = computed(() => selectedList.value?.items.filter((item) => item.is_active || showDeleted.value) ?? []);
const isActorList = computed(() => props.actorListCodes.includes(selectedList.value?.code));
const actorKey = (item) => item.linked_user_id ? 'user:' + item.linked_user_id : (item.linked_subcontractor_id ? 'subcontractor:' + item.linked_subcontractor_id : '');
function actorLinks(key) { const [type, id] = String(key || '').split(':'); return { linked_user_id: type === 'user' ? Number(id) : null, linked_subcontractor_id: type === 'subcontractor' ? Number(id) : null }; }
function actorStillAvailable(key) { const [type, id] = String(key || '').split(':'); if (!type) return true; const rows = type === 'user' ? props.actorOptions.users : props.actorOptions.subcontractors; return rows.some((actor) => actor.id === Number(id)); }
function actorName(key) { const [type, id] = String(key || '').split(':'); const rows = type === 'user' ? props.actorOptions.users : props.actorOptions.subcontractors; return rows.find((actor) => actor.id === Number(id))?.name ?? ''; }
function syncAddValue() { const name = actorName(addForm.actor_key); if (name) addForm.value = name; }
function syncItemValue(item) { const name = actorName(edits[item.id].actor_key); if (name) edits[item.id].value = name; }

function initializeEdits() {
  props.lists.forEach((list) => list.items.forEach((item) => { edits[item.id] = { value: item.value, is_active: Boolean(item.is_active), actor_key: actorKey(item) }; }));
}
initializeEdits();
watch(selectedId, (id) => { addForm.value_list_id = id; addForm.value = ''; addForm.actor_key = ''; addForm.clearErrors(); });
watch(() => props.lists, initializeEdits, { deep: true });

function changeYear(event) { router.get(route('coordinator.mginbon.value_masters.index'), { year: Number(event.target.value) }); }
function copyFromYear() {
  if (!copyForm.source_project_id) return;
  const source = props.projects.find((row) => row.id === Number(copyForm.source_project_id));
  const sourceYear = source ? source.year : "";
  if (!window.confirm(sourceYear + "年の値一覧で" + props.project.year + "年の値一覧を置き換えます。よろしいですか？")) return;
  copyForm.post(route("coordinator.mginbon.value_masters.copy"), { preserveScroll: true });
}
function addValue() {
  Object.assign(addForm, actorLinks(addForm.actor_key));
  addForm.post(route('coordinator.mginbon.value_masters.items.store'), { preserveScroll: true, onSuccess: () => addForm.reset('value') });
}
function saveItem(item) {
  const payload = { ...edits[item.id], ...actorLinks(edits[item.id].actor_key) };
  router.patch(route('coordinator.mginbon.value_masters.items.update', { item: item.id }), payload, { preserveScroll: true });
}
function deleteItem(item) {
  if (!window.confirm("「" + item.value + "」を削除しますか？")) return;
  router.delete(route("coordinator.mginbon.value_masters.items.destroy", { item: item.id }), { preserveScroll: true });
}
function restoreItem(item) {
  edits[item.id].is_active = true;
  saveItem(item);
}
function move(item, offset) {
  const items = [...selectedList.value.items];
  const activeItems = items.filter((row) => row.is_active);
  const activeIndex = activeItems.findIndex((row) => row.id === item.id);
  const target = activeItems[activeIndex + offset];
  if (!target) return;
  const itemIndex = items.findIndex((row) => row.id === item.id);
  const destination = items.findIndex((row) => row.id === target.id);
  [items[itemIndex], items[destination]] = [items[destination], items[itemIndex]];
  router.post(route('coordinator.mginbon.value_masters.reorder', { valueList: selectedList.value.id }), { item_ids: items.map((item) => item.id) }, { preserveScroll: true });
}
</script>

<template>
  <Head title="銀本・値一覧マスター" />
  <div class="min-h-screen w-full bg-gray-100 p-2 sm:p-3">
    <ToastUnified />
    <section class="sticky top-0 z-40 -mx-2 -mt-2 mb-3 border-b border-gray-500 bg-white shadow-sm sm:-mx-3 sm:-mt-3">
      <div class="overflow-x-auto">
        <div class="min-w-[72rem] text-xs text-gray-800">
          <div class="flex h-12 items-stretch border-b border-gray-300">
            <div class="flex items-center border-r border-gray-300 px-1"><button type="button" class="h-8 w-8 border border-gray-300 bg-white text-2xl leading-none hover:bg-gray-100" @click="window.history.back()">‹</button><button type="button" class="ml-1 h-8 w-8 border border-gray-500 bg-white text-2xl leading-none hover:bg-gray-100" @click="window.history.forward()">›</button></div>
            <div class="flex min-w-48 items-center gap-2 border-r border-gray-300 px-3"><span class="flex h-7 w-7 items-center justify-center rounded-full border-4 border-gray-300 bg-white"></span><div><div class="font-bold">値一覧マスター</div><div class="text-[10px]">{{ project.year }}年 中学入試問題集</div></div></div>
            <Link :href="route('coordinator.mginbon.import_preview')" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">＋</span>取込確認</Link>
            <Link :href="route('coordinator.mginbon.aggregations.index', { year: project.year })" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">Σ</span>集計</Link>
            <Link :href="route('coordinator.mginbon.annual_import.create')" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">⇧</span>年度取込</Link>
            <span class="min-w-20 border-r border-gray-400 bg-gray-700 px-3 py-1 text-center text-white"><span class="block text-lg">≡</span>値一覧</span>
            <Link :href="route('coordinator.dashboard')" class="min-w-24 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">↗</span>通常サイト</Link>
          </div>
          <div class="flex h-8 items-center border-b border-gray-400 bg-gray-50 px-1">
            <Link :href="route('coordinator.mginbon.index', { year: project.year, view: 'preparation' })" class="h-full border-x border-gray-300 bg-white px-4 py-2 font-semibold hover:bg-gray-100">年度準備</Link>
            <Link :href="route('coordinator.mginbon.index', { year: project.year, view: 'list' })" class="h-full border-r border-gray-300 bg-white px-4 py-2 font-semibold hover:bg-gray-100">制作進行</Link>
            <span class="h-full border-r border-green-700 bg-green-700 px-4 py-2 font-semibold text-white">値一覧</span>
          </div>
        </div>
      </div>
    </section>
    <div class="space-y-4">
      <section class="rounded bg-white p-4 shadow sm:p-6">
        <div class="flex flex-wrap items-end gap-4">
          <label class="text-xs text-gray-600">年度<select :value="project.year" class="mt-1 block rounded border-gray-300 text-sm" @change="changeYear"><option v-for="row in projects" :key="row.id" :value="row.year">{{ row.year }}年</option></select></label>
          <p class="pb-2 text-sm text-gray-600">FileMakerの値一覧に相当します。変更はこの年度だけに反映されます。</p>
          <form class="ml-auto flex flex-wrap items-end gap-2 border-l pl-4" @submit.prevent="copyFromYear">
            <label class="text-xs text-gray-600">コピー元年度<select v-model="copyForm.source_project_id" class="mt-1 block rounded border-gray-300 text-sm"><option value="">選択してください</option><option v-for="row in projects.filter((row) => row.id !== project.id)" :key="row.id" :value="row.id">{{ row.year }}年</option></select></label>
            <button :disabled="copyForm.processing || !copyForm.source_project_id" class="rounded border border-green-700 bg-white px-4 py-2 text-sm font-medium text-green-800 hover:bg-green-50 disabled:opacity-40">この年度からコピー</button>
            <p v-if="copyForm.errors.source_project_id" class="w-full text-xs text-red-600">{{ copyForm.errors.source_project_id }}</p>
          </form>
        </div>
      </section>
      <section class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <nav class="overflow-hidden rounded bg-white shadow">
          <div class="border-b bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">13個の項目</div>
          <button v-for="list in lists" :key="list.id" type="button" class="block w-full border-b px-4 py-3 text-left text-sm hover:bg-green-50" :class="selectedId === list.id ? 'bg-green-100 font-semibold text-green-900' : 'text-gray-700'" @click="selectedId = list.id"><span class="mr-2 text-xs">◆</span>{{ list.name }}<span class="float-right text-xs text-gray-400">{{ list.items.filter((item) => item.is_active).length }}</span></button>
        </nav>
        <div v-if="selectedList" class="overflow-hidden rounded bg-white shadow">
          <div class="border-b bg-gray-50 px-4 py-3"><h3 class="font-semibold text-gray-800">{{ selectedList.name }}</h3><p class="mt-1 text-xs text-gray-500">入力候補に出す値を編集できます。削除した値は過去データを守るため内部に保持されます。</p></div>
          <label class="flex items-center justify-end gap-2 border-b px-4 py-2 text-xs text-gray-600"><input v-model="showDeleted" type="checkbox" class="rounded border-gray-300 text-green-600">削除済みも表示</label>
          <form class="flex flex-col gap-2 border-b bg-green-50 p-4 sm:flex-row" @submit.prevent="addValue">
            <input v-model="addForm.value" type="text" class="min-w-0 flex-1 rounded border-gray-300 text-sm" placeholder="新しい値" maxlength="255">
            <select v-if="isActorList" v-model="addForm.actor_key" class="min-w-52 rounded border-gray-300 text-sm" @change="syncAddValue"><option value="">表示専用（リンクなし）</option><optgroup label="社員"><option v-for="actor in actorOptions.users" :key="'user-' + actor.id" :value="'user:' + actor.id">{{ actor.name }}</option></optgroup><optgroup label="外注先"><option v-for="actor in actorOptions.subcontractors" :key="'subcontractor-' + actor.id" :value="'subcontractor:' + actor.id">{{ actor.name }}</option></optgroup></select>
            <button :disabled="addForm.processing || !addForm.value.trim()" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40">新規追加</button>
            <p v-if="addForm.errors.value || addForm.errors.actor" class="self-center text-xs text-red-600">{{ addForm.errors.value || addForm.errors.actor }}</p>
          </form>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50 text-left text-xs text-gray-600"><tr><th class="w-20 px-4 py-3">順番</th><th class="px-4 py-3">値</th><th v-if="isActorList" class="min-w-64 px-4 py-3">案件担当リンク</th><th class="w-24 px-4 py-3 text-center">有効</th><th class="w-24 px-4 py-3"></th></tr></thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="(item, index) in visibleItems" :key="item.id" class="hover:bg-gray-50" :class="{ 'opacity-55': !edits[item.id]?.is_active }">
                  <td class="whitespace-nowrap px-4 py-2"><button type="button" class="h-8 w-8 rounded border bg-white hover:bg-gray-100 disabled:opacity-30" :disabled="!item.is_active || index === 0" @click="move(item, -1)">↑</button><button type="button" class="ml-1 h-8 w-8 rounded border bg-white hover:bg-gray-100 disabled:opacity-30" :disabled="!item.is_active || index === visibleItems.length - 1" @click="move(item, 1)">↓</button></td>
                  <td class="px-4 py-2"><input v-model="edits[item.id].value" type="text" class="w-full rounded border-gray-300 text-sm" maxlength="255"></td>
                  <td v-if="isActorList" class="px-4 py-2"><select v-model="edits[item.id].actor_key" class="w-full rounded border-gray-300 text-sm" @change="syncItemValue(item)"><option value="">表示専用（リンクなし）</option><optgroup label="社員"><option v-for="actor in actorOptions.users" :key="'user-' + actor.id" :value="'user:' + actor.id">{{ actor.name }}</option></optgroup><optgroup label="外注先"><option v-for="actor in actorOptions.subcontractors" :key="'subcontractor-' + actor.id" :value="'subcontractor:' + actor.id">{{ actor.name }}</option></optgroup></select><span class="mt-1 inline-block rounded px-2 py-0.5 text-[10px]" :class="!actorStillAvailable(edits[item.id].actor_key) ? 'bg-red-100 text-red-800' : (edits[item.id].actor_key ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600')">{{ !actorStillAvailable(edits[item.id].actor_key) ? '案件メンバーから外れています' : (edits[item.id].actor_key?.startsWith('user:') ? '社員' : (edits[item.id].actor_key?.startsWith('subcontractor:') ? '外注先' : '表示専用')) }}</span></td>
                  <td class="px-4 py-2 text-center"><input v-model="edits[item.id].is_active" type="checkbox" class="rounded border-gray-300 text-green-600 focus:ring-green-500"></td>
                  <td class="px-4 py-2 text-right"><div class="flex justify-end gap-1"><button v-if="item.is_active" type="button" class="rounded bg-green-600 px-3 py-2 text-xs font-medium text-white hover:bg-green-700" @click="saveItem(item)">保存</button><button v-if="item.is_active" type="button" class="rounded bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700" @click="deleteItem(item)">削除</button><button v-else type="button" class="rounded bg-gray-600 px-3 py-2 text-xs font-medium text-white hover:bg-gray-700" @click="restoreItem(item)">元に戻す</button></div></td>
                </tr>
                <tr v-if="!visibleItems.length"><td :colspan="isActorList ? 5 : 4" class="px-4 py-10 text-center text-gray-500">値がありません。</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
