<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SchoolHeader from '@/Components/MGinbon/SchoolHeader.vue';

const props = defineProps({
  project: { type: Object, required: true },
  projectJob: { type: Object, required: true },
  units: { type: Object, required: true },
  mediaOptions: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
});

const form = reactive({ ...props.filters });
const rows = ref(JSON.parse(JSON.stringify(props.units.data ?? [])));
const selections = reactive({});
const processing = reactive({});
const errors = reactive({});

const displayedRange = computed(() => props.units.total
  ? `${props.units.from}～${props.units.to}校 / ${props.units.total}校`
  : '0件');

function search() {
  router.get(route('user.project_jobs.mginbon_progress.show', { projectJob: props.projectJob.id }), { ...form }, {
    preserveState: true, preserveScroll: true, replace: true,
  });
}

function stagesFor(item) {
  const result = new Map();
  for (const subject of item.subjects ?? []) {
    for (const task of subject.tasks ?? []) {
      if (!result.has(task.stage_id)) result.set(task.stage_id, { id: task.stage_id, name: task.name, code: task.code });
    }
  }
  return [...result.values()];
}

function taskFor(subject, stageId) {
  return (subject.tasks ?? []).find((task) => task.stage_id === stageId);
}

function key(item, stage) {
  return `${item.id}:${stage.id}`;
}

function selectableSubjects(item, stage) {
  return (item.subjects ?? []).filter((subject) => {
    const task = taskFor(subject, stage.id);
    return task && !task.assignment_id && task.status === 'not_started';
  });
}

function selected(item, stage) {
  const k = key(item, stage);
  if (!(k in selections)) {
    const candidates = selectableSubjects(item, stage);
    const useAll = ['問題', '解答のみ'].includes(item.media_name);
    selections[k] = useAll ? candidates.map((subject) => subject.id) : (candidates[0] ? [candidates[0].id] : []);
  }
  return selections[k];
}

function toggleSubject(item, stage, subjectId) {
  const list = selected(item, stage);
  const index = list.indexOf(subjectId);
  if (index >= 0) list.splice(index, 1);
  else list.push(subjectId);
}

function selectAll(item, stage) {
  selections[key(item, stage)] = selectableSubjects(item, stage).map((subject) => subject.id);
}

function ownAssignments(item, stage) {
  const assignments = new Map();
  for (const subject of item.subjects ?? []) {
    const task = taskFor(subject, stage.id);
    if (task?.assignment_id && task.is_mine) {
      if (!assignments.has(task.assignment_id)) assignments.set(task.assignment_id, []);
      assignments.get(task.assignment_id).push(subject.name);
    }
  }
  return [...assignments.entries()].map(([id, subjects]) => ({ id, subjects }));
}

async function register(item, stage) {
  const k = key(item, stage);
  const subjectIds = [...selected(item, stage)];
  errors[k] = '';
  if (!subjectIds.length) {
    errors[k] = '教科を選択してください。';
    return;
  }
  processing[k] = true;
  try {
    const response = await axios.post(route('user.project_jobs.mginbon_progress.register', { projectJob: props.projectJob.id }), {
      item_id: item.id, stage_id: stage.id, subject_ids: subjectIds,
    });
    for (const subject of item.subjects ?? []) {
      if (!subjectIds.includes(subject.id)) continue;
      const task = taskFor(subject, stage.id);
      if (task) {
        task.status = 'assigned';
        task.assignment_id = response.data.assignment_id;
        task.user_name = '自分';
      }
    }
    selections[k] = [];
  } catch (error) {
    errors[k] = error?.response?.data?.message ?? error?.response?.data?.error ?? '登録に失敗しました。';
  } finally {
    processing[k] = false;
  }
}

function paginationLabel(label) {
  return label.replace('&laquo; Previous', '前へ').replace('Next &raquo;', '次へ');
}
</script>

<template>
  <AppLayout :title="`${project.year}年 銀本進行`">
    <template #header>
      <div class="flex flex-wrap items-center gap-3">
        <Link :href="route('user.project_jobs.show', { projectJob: projectJob.id }) + '?tab=progress'" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 案件の進行管理表へ戻る</Link>
        <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ project.year }}年 銀本進行</h2>
      </div>
    </template>

    <div class="space-y-4">
      <section class="rounded bg-white p-4 shadow sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="font-semibold text-gray-900">{{ projectJob.title }}</div>
            <p class="mt-1 text-sm text-gray-600">空いている工程と教科を選ぶと、通常のMyJobとして登録されます。</p>
          </div>
          <Link :href="route('user.myjobbox.index')" class="rounded border border-blue-300 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">MyJobBOXを開く</Link>
        </div>
        <form class="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-[2fr_1fr_8rem_auto]" @submit.prevent="search">
          <label class="text-xs font-medium text-gray-600">学校名・コード<input v-model="form.search" type="search" class="mt-1 w-full rounded border-gray-300 text-sm" placeholder="学校名、みくに、日能研" /></label>
          <label class="text-xs font-medium text-gray-600">媒体<select v-model="form.media" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="">すべて</option><option v-for="media in mediaOptions" :key="media" :value="media">{{ media }}</option></select></label>
          <label class="text-xs font-medium text-gray-600">表示件数<select v-model.number="form.per_page" class="mt-1 w-full rounded border-gray-300 text-sm"><option :value="10">10件</option><option :value="25">25件</option><option :value="50">50件</option></select></label>
          <button type="submit" class="self-end rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">検索</button>
        </form>
      </section>

      <div class="text-sm text-gray-600">{{ displayedRange }}</div>

      <section v-if="rows.length" class="space-y-4">
        <article v-for="unit in rows" :key="unit.id" class="overflow-hidden rounded border border-blue-200 bg-white shadow-sm">
          <SchoolHeader :unit="unit" tone="blue" />
          <section v-for="item in unit.items" :key="item.id" class="border-b last:border-b-0">
            <div class="border-b bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-900">{{ item.media_name }}</div>
            <div class="divide-y">
            <div v-for="stage in stagesFor(item)" :key="stage.id" class="grid gap-3 p-3 lg:grid-cols-[12rem_1fr_auto] lg:items-center">
              <div class="font-medium text-gray-800">{{ stage.name }}</div>
              <div class="flex flex-wrap gap-2">
                <button v-for="subject in item.subjects" :key="subject.id" type="button"
                  class="rounded border px-3 py-2 text-sm"
                  :disabled="!taskFor(subject, stage.id) || !!taskFor(subject, stage.id)?.assignment_id || taskFor(subject, stage.id)?.status !== 'not_started'"
                  :class="selected(item, stage).includes(subject.id) ? 'border-blue-500 bg-blue-100 text-blue-800' : (taskFor(subject, stage.id)?.assignment_id ? 'border-gray-200 bg-gray-100 text-gray-500' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50')"
                  @click="toggleSubject(item, stage, subject.id)">
                  <span class="font-medium">{{ subject.name }}</span>
                  <span v-if="taskFor(subject, stage.id)?.assignment_id" class="ml-1 text-xs">{{ taskFor(subject, stage.id)?.user_name || '登録済' }}</span>
                </button>
              </div>
              <div class="flex flex-wrap items-center justify-end gap-2">
                <Link v-for="assignment in ownAssignments(item, stage)" :key="assignment.id"
                  :href="route('user.myjobbox.show', { assignment: assignment.id })"
                  class="rounded border border-blue-300 bg-blue-50 px-2 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100">
                  登録内容を確認（{{ assignment.subjects.join('・') }}）
                </Link>
                <button v-if="selectableSubjects(item, stage).length > 1" type="button" class="rounded border border-blue-300 px-2 py-1.5 text-xs text-blue-700 hover:bg-blue-50" @click="selectAll(item, stage)">4教科を選択</button>
                <button type="button" :disabled="processing[key(item, stage)] || !selectableSubjects(item, stage).length" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-gray-300" @click="register(item, stage)">{{ processing[key(item, stage)] ? '登録中…' : 'MyJobに登録' }}</button>
              </div>
              <p v-if="errors[key(item, stage)]" class="text-sm text-red-600 lg:col-start-2 lg:col-span-2">{{ errors[key(item, stage)] }}</p>
            </div>
            </div>
          </section>
        </article>
      </section>
      <div v-else class="rounded bg-white p-8 text-center text-sm text-gray-500 shadow">条件に一致する媒体はありません。</div>

      <nav v-if="units.links.length > 3" class="flex flex-wrap justify-center gap-1" aria-label="ページ移動">
        <template v-for="link in units.links" :key="`${link.label}-${link.url}`">
          <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded border px-3 py-1.5 text-sm" :class="link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">{{ paginationLabel(link.label) }}</Link>
          <span v-else class="rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-400">{{ paginationLabel(link.label) }}</span>
        </template>
      </nav>
    </div>
  </AppLayout>
</template>
