<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  previewCount: { type: Number, default: null }, previewing: { type: Boolean, default: false },
  projectId: { type: Number, required: true }, currentCriteria: { type: Object, required: true },
  displayMode: { type: String, default: 'list' }, simulationActive: { type: Boolean, default: false },
});
const emit = defineEmits(['apply', 'restore', 'simulate', 'retry', 'execute', 'close']);
const tab = ref('common');
const combineMode = ref('replace');
const dateCode = ref('manuscript_received_on');
const relativeDate = ref('relative:today');
const subject = ref('');
const customDays = ref(7);
const tabs = [
  ['common', 'よく使う検索'], ['date', '日付・未入力'], ['anomaly', '異常チェック'], ['saved', 'お気に入り保存'], ['history', '履歴'],
];
const dateFields = [
  ['manuscript_received_on', '入稿'], ['manuscript_due_on', '入稿指定'], ['initial_shared_on', '初校出'],
  ['initial_returned_on', '初校戻り'], ['reproof_shared_on', '再校出'], ['reproof_returned_on', '再校戻り'], ['completed_on', '校了'],
];
const relativeOptions = [
  ['relative:today', '今日'], ['relative:yesterday', '昨日'], ['relative:tomorrow', '明日'],
  ['relative:this_week', '今週'], ['relative:next_week', '来週'], ['relative:past_days:7', '過去7日'],
  ['relative:next_days:7', '今後7日'], ['relative:this_month', '今月'],
  ['relative:before_today', '今日以前'], ['relative:after_today', '今日以降'],
];
const presets = [
  { label: '入稿日未入力', description: '入稿日が登録されていない媒体', conditions: [{ field: 'milestone', code: 'manuscript_received_on', subject: '', value: '=' }] },
  { label: '初校出が今日', description: '本日初校出の媒体', conditions: [{ field: 'milestone', code: 'initial_shared_on', subject: '', value: 'relative:today' }] },
  { label: '今週校了', description: '校了日が今週の媒体', conditions: [{ field: 'milestone', code: 'completed_on', subject: '', value: 'relative:this_week' }] },
  { label: '今後7日の入稿指定', description: '入稿指定日が今日から7日以内', conditions: [{ field: 'milestone', code: 'manuscript_due_on', subject: '', value: 'relative:next_days:7' }] },
  { label: '校了日未入力', description: '校了日が登録されていない媒体', conditions: [{ field: 'milestone', code: 'completed_on', subject: '', value: '=' }] },
];
const anomalyPresets = [
  { label: '初校戻りが初校出より前', code: 'initial_return_before_shared' },
  { label: '再校戻りが再校出より前', code: 'reproof_return_before_shared' },
  { label: '校了済み・途中工程未入力', code: 'completed_with_missing_steps' },
  { label: '担当者未設定', code: 'missing_actor' },
  { label: '30日以上更新なし', code: 'stale_30_days' },
  { label: '校正UPが校正入より前', code: 'proof_completed_before_started' },
  { label: '同一工程・教科に複数担当', code: 'multiple_active_actor' },
  { label: '学校・媒体・教科の重複', code: 'duplicate_item_subject' },
  { label: '無効な担当者を参照', code: 'inactive_actor_reference' },
  { label: '前工程完了・次工程未着手', code: 'previous_done_next_missing' },
  { label: '入稿指定日超過・入稿未完了', code: 'overdue_pending' },
  { label: '科目の工程タスク欠落', code: 'missing_stage_task' },
];
const subjectScope = computed(() => subject.value || '');
const savedSearches = ref([]);
const histories = ref([]);
const loadingLibrary = ref(false);
const libraryError = ref('');
const saveName = ref('');
const saveDescription = ref('');
const saveScope = ref('personal');
const saveAllYears = ref(false);
const saving = ref(false);
const hasCurrentConditions = computed(() => (props.currentCriteria?.requests ?? []).some((request) => request.conditions?.length));
async function loadLibrary(kind = tab.value) {
  if (! ['saved', 'history'].includes(kind)) return;
  loadingLibrary.value = true; libraryError.value = '';
  try {
    if (kind === 'saved') {
      const response = await axios.get(route('coordinator.mginbon.saved_searches.index'), { params: { project_id: props.projectId } });
      savedSearches.value = response.data.saved_searches ?? [];
    } else {
      const response = await axios.get(route('coordinator.mginbon.search_histories.index'), { params: { project_id: props.projectId } });
      histories.value = response.data.histories ?? [];
    }
  } catch (error) { libraryError.value = error.response?.data?.message || '検索データを読み込めませんでした。'; }
  finally { loadingLibrary.value = false; }
}
async function saveCurrent(criteria = props.currentCriteria, name = saveName.value) {
  if (! name.trim() || !(criteria?.requests ?? []).length) return;
  saving.value = true; libraryError.value = '';
  try {
    await axios.post(route('coordinator.mginbon.saved_searches.store'), {
      project_id: saveAllYears.value ? null : props.projectId, name: name.trim(), description: saveDescription.value || null,
      scope: saveScope.value, criteria, display_mode: props.displayMode,
    });
    saveName.value = ''; saveDescription.value = ''; await loadLibrary('saved');
  } catch (error) { libraryError.value = error.response?.data?.message || Object.values(error.response?.data?.errors ?? {})[0]?.[0] || '保存できませんでした。'; }
  finally { saving.value = false; }
}
async function deleteSaved(row) {
  if (! window.confirm(`「${row.name}」を削除しますか？`)) return;
  await axios.delete(route('coordinator.mginbon.saved_searches.destroy', { savedSearch: row.id }));
  await loadLibrary('saved');
}
async function clearHistory() {
  if (! window.confirm('この年度の検索履歴をすべて削除しますか？')) return;
  await axios.delete(route('coordinator.mginbon.search_histories.clear'), { data: { project_id: props.projectId } });
  histories.value = [];
}
async function promoteHistory(row) {
  const name = window.prompt('お気に入り名を入力してください。', row.summary);
  if (name) { tab.value = 'saved'; await saveCurrent(row.criteria, name); }
}
function restore(row, execute = false) { emit('restore', { criteria: row.criteria, displayMode: row.display_mode, execute }); }
watch(tab, (value) => loadLibrary(value));
function applyPreset(preset) { emit('apply', { mode: combineMode.value, request: { omit: false, label: preset.label, conditions: preset.conditions } }); }
function applyRelative() {
  let value = relativeDate.value;
  if (value === 'custom_past') value = `relative:past_days:${Math.max(1, Number(customDays.value) || 1)}`;
  if (value === 'custom_next') value = `relative:next_days:${Math.max(1, Number(customDays.value) || 1)}`;
  const label = `${dateFields.find(([code]) => code === dateCode.value)?.[1]}：${relativeOptions.find(([code]) => code === relativeDate.value)?.[1] || customDays.value + '日'}`;
  applyPreset({ label, conditions: [{ field: 'milestone', code: dateCode.value, subject: subjectScope.value, value }] });
}
function applyMissing() { applyPreset({ label: `${dateFields.find(([code]) => code === dateCode.value)?.[1]}未入力`, conditions: [{ field: 'milestone', code: dateCode.value, subject: subjectScope.value, value: '=' }] }); }
function applyAnomaly(rule) { emit('apply', { mode: combineMode.value, request: { omit: false, label: rule.label, conditions: [{ field: 'anomaly', code: rule.code, subject: '', value: '1' }] } }); }
</script>

<template>
  <section class="no-print border-2 border-teal-700 bg-white shadow-lg">
    <header class="flex items-center gap-3 border-b bg-teal-50 px-3 py-2">
      <strong class="shrink-0 text-sm text-teal-900">便利な検索</strong>
      <span class="text-xs text-teal-800">条件を選ぶと、下の検索ボックスが変化します。</span>
      <label class="ml-auto text-xs">適用方法:
        <select v-model="combineMode" class="h-7 border-gray-400 py-0 text-xs"><option value="replace">現在条件を置換</option><option value="add">OR条件として追加</option><option value="refine">現在結果内を絞り込む</option></select>
      </label>
      <span class="min-w-32 text-right text-xs font-semibold text-teal-800">{{ previewing ? '件数を確認中…' : previewCount === null ? '' : `該当見込み ${previewCount}件` }}</span>
      <button v-if="simulationActive" type="button" class="border border-amber-600 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900 hover:bg-amber-100" @click="emit('retry')">条件をやり直す</button>
      <button type="button" :disabled="!hasCurrentConditions" class="border border-blue-700 bg-blue-700 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40" @click="emit('simulate')">シミュレートする</button>
      <button type="button" :disabled="!hasCurrentConditions" class="border border-green-700 bg-green-700 px-3 py-1 text-xs font-semibold text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-40" @click="emit('execute')">検索実行</button>
      <button type="button" class="border px-2 py-1 text-xs" @click="emit('close')">閉じる</button>
    </header>
    <nav class="flex border-b bg-gray-50">
      <button v-for="entry in tabs" :key="entry[0]" type="button" class="border-r px-4 py-2 text-xs" :class="tab === entry[0] ? 'bg-teal-700 text-white' : 'hover:bg-gray-100'" @click="tab = entry[0]">{{ entry[1] }}</button>
    </nav>
    <div class="p-3 text-xs">
      <div v-if="tab === 'common'" class="grid grid-cols-2 gap-2 lg:grid-cols-3">
        <button v-for="preset in presets" :key="preset.label" type="button" class="border bg-white p-3 text-left hover:border-teal-600 hover:bg-teal-50" @click="applyPreset(preset)"><b class="block">{{ preset.label }}</b><span class="text-gray-500">{{ preset.description }}</span></button>
      </div>
      <div v-else-if="tab === 'date'" class="flex flex-wrap items-end gap-3">
        <label>工程日付<select v-model="dateCode" class="mt-1 block h-8 w-40 py-0 text-xs"><option v-for="entry in dateFields" :key="entry[0]" :value="entry[0]">{{ entry[1] }}</option></select></label>
        <label>教科<select v-model="subject" class="mt-1 block h-8 w-32 py-0 text-xs"><option value="">全教科/共通</option><option value="japanese">国語</option><option value="math">算数</option><option value="social">社会</option><option value="science">理科</option></select></label>
        <label>期間<select v-model="relativeDate" class="mt-1 block h-8 w-36 py-0 text-xs"><option v-for="entry in relativeOptions" :key="entry[0]" :value="entry[0]">{{ entry[1] }}</option><option value="custom_past">過去N日</option><option value="custom_next">今後N日</option></select></label>
        <label v-if="relativeDate.startsWith('custom_')">日数<input v-model.number="customDays" type="number" min="1" max="365" class="mt-1 block h-8 w-20 py-0 text-xs" /></label>
        <button type="button" class="h-8 bg-teal-700 px-4 text-white" @click="applyRelative">期間を適用</button>
        <button type="button" class="h-8 border border-amber-600 bg-amber-50 px-4 text-amber-900" @click="applyMissing">未入力を検索</button>
      </div>
      <div v-else-if="tab === 'anomaly'" class="grid grid-cols-2 gap-2 lg:grid-cols-3"><button v-for="rule in anomalyPresets" :key="rule.code" type="button" class="border border-red-200 bg-red-50 p-3 text-left hover:border-red-500" @click="applyAnomaly(rule)">{{ rule.label }}</button></div>
      <div v-else-if="tab === 'saved'" class="space-y-3">
        <div class="rounded border border-blue-200 bg-blue-50 p-2 text-blue-900">先に条件を選んで「シミュレートする」で結果を確認してください。確認後、検索名を入力するとお気に入りへ保存できます。</div>
        <div class="flex flex-wrap items-end gap-2 rounded border bg-gray-50 p-2">
          <label>検索名<input v-model="saveName" type="text" maxlength="100" class="mt-1 block h-8 w-48 text-xs" placeholder="例：今週の入稿確認" /></label>
          <label>説明<input v-model="saveDescription" type="text" maxlength="500" class="mt-1 block h-8 w-64 text-xs" /></label>
          <label>公開範囲<select v-model="saveScope" class="mt-1 block h-8 py-0 text-xs"><option value="personal">個人用</option><option value="shared">共有</option></select></label>
          <label class="flex h-8 items-center gap-1"><input v-model="saveAllYears" type="checkbox" />全年度共通</label>
          <button type="button" :disabled="saving || !saveName.trim() || !hasCurrentConditions" class="h-8 bg-teal-700 px-4 text-white disabled:opacity-40" @click="saveCurrent()">{{ saving ? '保存中…' : 'お気に入りに保存' }}</button>
        </div>
        <p v-if="!hasCurrentConditions" class="text-amber-700">保存するには、先に検索条件を1つ以上選んでください。</p>
        <p v-else-if="!saveName.trim()" class="text-gray-600">検索名を入力すると保存ボタンが有効になります。</p>
        <p v-if="libraryError" class="text-red-700">{{ libraryError }}</p>
        <div v-if="loadingLibrary" class="p-4 text-center text-gray-500">読み込み中…</div>
        <div v-else-if="!savedSearches.length" class="rounded border border-dashed p-5 text-center text-gray-500">お気に入り検索はありません。</div>
        <div v-else class="grid gap-2 lg:grid-cols-2">
          <article v-for="row in savedSearches" :key="row.id" class="flex items-center gap-2 border p-2">
            <div class="min-w-0 flex-1"><b class="block truncate">{{ row.name }}</b><span class="text-gray-500">{{ row.scope === 'shared' ? '共有' : '個人用' }}・{{ row.project_id ? 'この年度' : '全年度' }}<template v-if="row.description">・{{ row.description }}</template></span></div>
            <button type="button" class="border px-2 py-1" @click="restore(row)">復元</button><button type="button" class="bg-green-700 px-2 py-1 text-white" @click="restore(row, true)">実行</button><button v-if="row.can_edit" type="button" class="text-red-700 underline" @click="deleteSaved(row)">削除</button>
          </article>
        </div>
      </div>
      <div v-else class="space-y-3">
        <div class="flex justify-end"><button v-if="histories.length" type="button" class="text-red-700 underline" @click="clearHistory">履歴をすべて削除</button></div>
        <p v-if="libraryError" class="text-red-700">{{ libraryError }}</p>
        <div v-if="loadingLibrary" class="p-4 text-center text-gray-500">読み込み中…</div>
        <div v-else-if="!histories.length" class="rounded border border-dashed p-5 text-center text-gray-500">検索履歴はありません。</div>
        <div v-else class="space-y-2">
          <article v-for="row in histories" :key="row.id" class="flex items-center gap-2 border p-2">
            <div class="min-w-0 flex-1"><b class="block truncate">{{ row.summary }}</b><span class="text-gray-500">{{ row.executed_at }}・{{ row.result_count }}件</span></div>
            <button type="button" class="border px-2 py-1" @click="restore(row)">復元</button><button type="button" class="bg-green-700 px-2 py-1 text-white" @click="restore(row, true)">再実行</button><button type="button" class="text-teal-800 underline" @click="promoteHistory(row)">保存</button>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>
