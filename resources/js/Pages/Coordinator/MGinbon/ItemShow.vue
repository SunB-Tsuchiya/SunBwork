<script setup>
import { reactive } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  item: { type: Object, required: true }, history: { type: Array, default: () => [] },
  stageDefinitions: { type: Array, default: () => [] }, users: { type: Array, default: () => [] },
  subcontractors: { type: Array, default: () => [] },
});

const dateFields = [
  ['manuscript_received_on', '入稿日'], ['text_input_completed_on', '文字UP'],
  ['drawing_completed_on', '作図UP'], ['initial_shared_on', '初校出'],
  ['initial_text_proof_started_on', '初校校正入り'], ['initial_text_proof_completed_on', '初校校正UP'],
  ['initial_returned_on', '初校戻り'], ['reproof_scan_check_started_on', '校正①入り'],
  ['reproof_scan_check_completed_on', '校正①UP'], ['reproof_shared_on', '再校出'],
  ['reproof_text_proof_started_on', '校正②入り'], ['reproof_text_proof_completed_on', '校正②UP'],
  ['reproof_returned_on', '再校戻り'], ['third_shared_on', '三校出'],
  ['third_returned_on', '三校戻り'], ['fourth_shared_on', '四校出'],
  ['fourth_returned_on', '四校戻り'], ['fifth_shared_on', '五校出'], ['completed_on', '校了'],
];
const measurementFields = [
  ['scan:internal', '社内scan'], ['scan:subcontracted', '外注scan'],
  ['drawing:internal', '社内作図'], ['drawing:subcontracted', '外注作図'],
];

const form = useForm({
  updated_at: props.item.updated_at,
  display_name: props.item.display_name,
  school_category: props.item.school_category,
  n_category: props.item.n_category,
  publication_status: props.item.publication_status,
  note: props.item.note,
  review_status: props.item.review_status,
  shared_dates: {
    original_received_on: props.item.shared_dates?.original_received_on ?? '',
    original_scan_completed_on: props.item.shared_dates?.original_scan_completed_on ?? '',
  },
  subjects: props.item.subjects.map((subject) => ({
    id: subject.id,
    dates: Object.fromEntries(dateFields.map(([code]) => [code, subject.dates?.[code] ?? ''])),
    measurements: Object.fromEntries(measurementFields.map(([code]) => [code, subject.measurements?.[code] ?? 0])),
  })),
});

const defaultAllSubjects = ['問題', '解答のみ'].includes(props.item.media_name);
const assignment = reactive({
  stage_definition_id: props.stageDefinitions[0]?.id ?? '',
  subject_ids: defaultAllSubjects ? props.item.subjects.map((subject) => subject.id) : (props.item.subjects[0] ? [props.item.subjects[0].id] : []),
  target: '', processing: false, error: '',
});

function submit() {
  form.put(route('coordinator.mginbon.items.update', { item: props.item.id }), { preserveScroll: true });
}

function selectAllSubjects() {
  assignment.subject_ids = props.item.subjects.map((subject) => subject.id);
}

function assignStage() {
  assignment.error = '';
  if (!assignment.stage_definition_id || !assignment.subject_ids.length || !assignment.target) {
    assignment.error = '工程、対象教科、担当先を選択してください。';
    return;
  }
  assignment.processing = true;
  router.post(route('coordinator.mginbon.items.assign_stage', { item: props.item.id }), {
    stage_definition_id: assignment.stage_definition_id,
    subject_ids: assignment.subject_ids,
    target: assignment.target,
    updated_at: props.item.updated_at,
  }, {
    preserveScroll: true,
    onError: (errors) => { assignment.error = Object.values(errors)[0] ?? '担当登録に失敗しました。'; },
    onFinish: () => { assignment.processing = false; },
  });
}

function historyValue(value) {
  if (value === null || value === undefined || value === '') return '空欄';
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function stageStatusLabel(status) {
  return { not_started: '未着手', assigned: '登録済', in_progress: '作業中', completed: '完了' }[status] ?? status;
}

function stageStatusClass(status) {
  return {
    assigned: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-indigo-100 text-indigo-800',
    completed: 'bg-green-100 text-green-800',
  }[status] ?? 'bg-gray-100 text-gray-600';
}

function lifecycleDate(value) {
  return value ? String(value).slice(0, 10) : '';
}
</script>

<template>
  <AppLayout :title="`${item.display_name}・${item.media_name}`">
    <template #header>
      <div class="flex flex-wrap items-center gap-3">
        <Link :href="route('coordinator.mginbon.index', { year: item.year })" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 銀本進行へ戻る</Link>
        <h2 class="text-base font-semibold text-gray-800 sm:text-xl">銀本進行・詳細編集</h2>
      </div>
    </template>

    <form class="space-y-4" @submit.prevent="submit">
      <section class="overflow-hidden rounded border border-cyan-200 bg-white shadow">
        <div class="grid grid-cols-1 bg-cyan-50 text-sm md:grid-cols-[6rem_8rem_minmax(16rem,1fr)_9rem]">
          <div class="bg-gray-600 px-3 py-3 font-semibold text-white">{{ item.mikuni_code || '共通' }}</div>
          <div class="border-r border-white px-3 py-2"><span class="text-[10px] text-gray-500">日能研</span><br>{{ item.n_code || '—' }}</div>
          <div class="border-r border-white px-3 py-2"><label class="text-[10px] text-gray-500">学校・名称</label><input v-model="form.display_name" class="mt-1 w-full rounded border-gray-300 text-sm font-semibold" /></div>
          <div class="bg-green-100 px-3 py-3 text-center font-medium text-green-900">{{ item.media_name }}</div>
        </div>
        <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
          <label class="text-xs text-gray-600">日能研分類<input v-model="form.n_category" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
          <label class="text-xs text-gray-600">学校分類<input v-model="form.school_category" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
          <label class="text-xs text-gray-600">銀本掲載<input v-model="form.publication_status" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
          <label class="text-xs text-gray-600">確認状態<select v-model="form.review_status" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="draft">下書き</option><option value="review_required">要確認</option><option value="confirmed">確認済み</option></select></label>
          <div class="grid grid-cols-2 gap-2"><label class="text-xs text-gray-600">原本入稿<input v-model="form.shared_dates.original_received_on" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" /></label><label class="text-xs text-gray-600">scan UP<input v-model="form.shared_dates.original_scan_completed_on" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" /></label></div>
          <label class="sm:col-span-2 lg:col-span-5 text-xs text-gray-600">メモ<textarea v-model="form.note" rows="2" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea></label>
        </div>
      </section>

      <section class="rounded border border-green-200 bg-white p-4 shadow">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div><h3 class="font-semibold text-gray-800">工程の仮担当を設定</h3><p class="mt-1 text-xs text-gray-500">依頼ジョブは送信しません。ユーザーがMyJobへ登録した時点で正式担当になります。</p></div>
          <button type="button" class="rounded border border-green-300 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-800 hover:bg-green-100" @click="selectAllSubjects">4教科に反映</button>
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-[15rem_1fr_18rem_auto] lg:items-end">
          <label class="text-xs text-gray-600">工程<select v-model="assignment.stage_definition_id" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="">選択してください</option><option v-for="stage in stageDefinitions" :key="stage.id" :value="stage.id">{{ stage.name }}</option></select></label>
          <fieldset><legend class="mb-1 text-xs text-gray-600">対象教科</legend><div class="flex flex-wrap gap-2"><label v-for="subject in item.subjects" :key="subject.id" class="flex items-center gap-1 rounded border bg-gray-50 px-3 py-2 text-sm"><input v-model="assignment.subject_ids" type="checkbox" :value="subject.id" />{{ subject.name }}</label></div></fieldset>
          <label class="text-xs text-gray-600">担当先<select v-model="assignment.target" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="">選択してください</option><option value="clear">選択教科の仮担当を解除</option><optgroup label="社員"><option v-for="user in users" :key="`u${user.id}`" :value="`user:${user.id}`">{{ user.name }}</option></optgroup><optgroup label="外注先"><option v-for="vendor in subcontractors" :key="`s${vendor.id}`" :value="`subcontractor:${vendor.id}`">{{ vendor.name }}</option></optgroup></select></label>
          <button type="button" :disabled="assignment.processing" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50" @click="assignStage">{{ assignment.processing ? '設定中…' : '仮担当を反映' }}</button>
        </div>
        <p v-if="assignment.error" class="mt-2 text-sm text-red-600">{{ assignment.error }}</p>
      </section>

      <section v-for="(subject, index) in item.subjects" :key="subject.id" class="rounded border bg-white shadow">
        <div class="flex items-center justify-between rounded-t bg-cyan-50 px-4 py-2"><h3 class="font-semibold text-cyan-950">{{ subject.name }}</h3><span class="text-xs text-gray-500">科目ID {{ subject.id }}</span></div>
        <div class="grid gap-4 p-4 xl:grid-cols-[18rem_1fr]">
          <div>
            <h4 class="mb-2 text-xs font-semibold text-gray-600">作業点数</h4>
            <div class="grid grid-cols-2 gap-2"><label v-for="[code, label] in measurementFields" :key="code" class="text-xs text-gray-600">{{ label }}<input v-model.number="form.subjects[index].measurements[code]" type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm" /></label></div>
            <h4 class="mb-2 mt-4 text-xs font-semibold text-gray-600">工程担当</h4>
            <div v-if="subject.stages.length" class="space-y-1 text-xs">
              <div v-for="stage in subject.stages" :key="stage.code" class="border-b py-1">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-gray-500">{{ stage.name }}</span>
                  <span class="text-right font-medium" :class="stage.planned ? 'border-b border-dashed border-amber-700 text-amber-900' : ''">{{ stage.actor || '—' }}<small v-if="stage.planned" class="ml-1">仮</small></span>
                </div>
                <div v-if="stage.status !== 'not_started'" class="mt-1 flex items-center justify-end gap-2">
                  <span class="rounded px-1.5 py-0.5 text-[10px]" :class="stageStatusClass(stage.status)">{{ stageStatusLabel(stage.status) }}</span>
                  <span v-if="stage.completed_at" class="text-[10px] text-gray-500">完了 {{ lifecycleDate(stage.completed_at) }}</span>
                  <span v-else-if="stage.assigned_at" class="text-[10px] text-gray-500">登録 {{ lifecycleDate(stage.assigned_at) }}</span>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-gray-400">担当候補なし</p>
          </div>
          <div>
            <h4 class="mb-2 text-xs font-semibold text-gray-600">工程日付</h4>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"><label v-for="[code, label] in dateFields" :key="code" class="text-xs text-gray-600">{{ label }}<input v-model="form.subjects[index].dates[code]" type="date" class="mt-1 w-full rounded border-gray-300 text-xs" /></label></div>
          </div>
        </div>
      </section>

      <div v-if="Object.keys(form.errors).length" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">入力内容を確認してください。<ul class="mt-1 list-disc pl-5"><li v-for="(message, field) in form.errors" :key="field">{{ message }}</li></ul></div>
      <div class="sticky bottom-3 flex justify-end"><button type="submit" :disabled="form.processing" class="rounded bg-green-600 px-6 py-2 font-medium text-white shadow hover:bg-green-700 disabled:opacity-50">{{ form.processing ? '保存中…' : '変更を保存' }}</button></div>
    </form>

    <section class="mt-6 rounded bg-white p-4 shadow">
      <h3 class="font-semibold text-gray-800">変更履歴</h3>
      <div v-if="history.length" class="mt-3 divide-y text-xs"><div v-for="entry in history" :key="entry.id" class="grid gap-1 py-2 sm:grid-cols-[10rem_1fr]"><span class="text-gray-500">{{ entry.created_at }}</span><span><b>{{ entry.field_path }}</b>：{{ historyValue(entry.old_value) }} → {{ historyValue(entry.new_value) }}</span></div></div>
      <p v-else class="mt-2 text-sm text-gray-500">手動変更はまだありません。</p>
    </section>
  </AppLayout>
</template>
