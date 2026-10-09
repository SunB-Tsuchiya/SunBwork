<script setup>
import { computed, onMounted, ref } from 'vue';
import { workCategories, workActivities, activityLabel } from '@/Constants/personalWorkActivities';

const props = defineProps({ candidates: { type: Array, default: () => [] }, modelValue: { type: Array, default: () => [] }, errors: { type: Object, default: () => ({}) } });
const emit = defineEmits(['update:modelValue']);
const enabled = ref(props.modelValue.length > 0 || props.candidates.some((candidate) => candidate.selected));
const rows = ref(props.candidates.map((candidate) => ({ ...candidate, selected: props.modelValue.some((row) =>
    (candidate.entry_id && Number(row.entry_id) === Number(candidate.entry_id)) ||
    (candidate.source_event_id && Number(row.source_event_id) === Number(candidate.source_event_id))) || candidate.selected })));
const selectedCount = computed(() => rows.value.filter((row) => row.selected).length);
const selectedMinutes = computed(() => rows.value.filter((row) => row.selected).reduce((sum, row) => sum + (Number(row.actual_minutes) || 0), 0));
const timeLabel = (minutes) => `${Math.floor(minutes / 60)}時間${minutes % 60 ? `${minutes % 60}分` : ''}`;
function changeCategory(row) {
    if (!(workActivities[row.work_category] || []).some(([code]) => code === row.activity_type)) row.activity_type = '';
    publish();
}
function publish() {
    emit('update:modelValue', enabled.value ? rows.value.filter((row) => row.selected).map((row) => ({
        entry_id: row.entry_id, source_event_id: row.source_event_id, title: row.title, work_category: row.work_category,
        activity_type: row.activity_type, actual_minutes: row.actual_minutes,
        result_note: row.result_note, actual_quantity: row.actual_quantity === '' ? null : row.actual_quantity, actual_unit: row.actual_unit || null,
    })) : []);
}
function addManual() {
    enabled.value = true;
    rows.value.push({ key: `manual-${Date.now()}-${rows.value.length}`, entry_id: null, source_event_id: null,
        title: '', project_name: null, work_category: 'other', activity_type: '', actual_minutes: null,
        selected: true, result_note: '', actual_quantity: null, actual_unit: '' });
    publish();
}
onMounted(() => { if (enabled.value) publish(); });
</script>

<template>
    <section class="mb-4 rounded border bg-blue-50 p-4">
        <h3 class="font-semibold">今日の作業結果</h3>
        <p class="mt-1 text-sm text-gray-600">カレンダーで記録した作業結果を選び、日報に文章として取り込みます。既存のジョブ予定も個別に選択できます。</p>
        <p class="mt-1 text-xs text-gray-500">取り込んだ内容には通常の日報と同じ閲覧権限が適用されます。</p>
        <div class="mt-3 flex gap-4 text-sm">
            <label><input v-model="enabled" type="radio" :value="true" @change="publish" /> 予定を反映する</label>
            <label><input v-model="enabled" type="radio" :value="false" @change="publish" /> 反映しない</label>
        </div>
        <button type="button" class="mt-3 rounded border border-blue-400 bg-white px-3 py-1 text-sm text-blue-700" @click="addManual">予定外の作業を追加</button>
        <div v-if="enabled" class="mt-3 space-y-3">
            <div v-for="row in rows" :key="row.key" class="rounded border bg-white p-3">
                <label class="flex items-start gap-2 text-sm font-medium">
                    <input v-model="row.selected" type="checkbox" class="mt-1" @change="publish" />
                    <span>{{ row.title }}<span v-if="row.project_name && row.project_name !== row.title" class="ml-2 text-gray-500">／{{ row.project_name }}</span></span>
                </label>
                <p v-if="row.entry_id && !row.ready" class="mt-2 text-xs text-amber-700">実績が空欄の場合は、ここで入力できます。作業中に記録する場合はカレンダーでこの予定を開いてください。</p>
                <div v-if="row.entry_id && row.ready" class="mt-2 rounded bg-pink-50 p-2 text-sm text-gray-700">
                    <span class="font-medium">{{ activityLabel(row.activity_type, row.work_category) }}</span>
                    <span class="ml-2">{{ row.actual_minutes }}分</span>
                    <span v-if="row.actual_quantity !== null" class="ml-2">{{ row.actual_quantity }}{{ row.actual_unit }}</span>
                    <p class="mt-1 whitespace-pre-wrap">{{ row.result_note }}</p>
                </div>
                <div v-if="row.selected && (!row.entry_id || !row.ready)" class="mt-3 grid gap-2 sm:grid-cols-[1fr_7rem_6rem]">
                    <div v-if="!row.entry_id && !row.source_event_id" class="sm:col-span-3">
                        <label class="text-sm">作業名 <span class="text-red-600">必須</span><input v-model="row.title" type="text" maxlength="255" required class="mt-1 w-full rounded border p-2" @input="publish" /></label>
                    </div>
                    <div class="sm:col-span-3 grid gap-2 sm:grid-cols-3">
                        <label class="text-sm">大まかな作業区分<select v-model="row.work_category" required class="mt-1 w-full rounded border p-2" @change="changeCategory(row)"><option v-for="[code, label] in workCategories" :key="code" :value="code">{{ label }}</option></select></label>
                        <label class="text-sm">作業種類 <span class="text-red-600">必須</span><select v-model="row.activity_type" required class="mt-1 w-full rounded border p-2" @change="publish"><option value="">選択してください</option><option v-for="[code, label] in workActivities[row.work_category] || workActivities.other" :key="code" :value="code">{{ label }}</option></select></label>
                        <label v-if="!row.entry_id" class="text-sm">実績時間（分） <span class="text-red-600">必須</span><input v-model.number="row.actual_minutes" type="number" min="1" max="1440" step="1" required class="mt-1 w-full rounded border p-2" @input="publish" /></label>
                        <p v-else class="self-end rounded bg-gray-50 p-2 text-sm">カレンダーの時間：{{ row.actual_minutes }}分</p>
                    </div>
                    <label class="text-sm">今日の進み・結果 <span class="text-red-600">必須</span>
                        <input v-model="row.result_note" type="text" maxlength="2000" required class="mt-1 w-full rounded border p-2" placeholder="例：初校を提出、確認待ち" @input="publish" />
                    </label>
                    <label class="text-sm">数量（任意）<input v-model="row.actual_quantity" type="number" min="0" step="0.01" class="mt-1 w-full rounded border p-2" placeholder="100" @input="publish" /></label>
                    <label class="text-sm">単位<input v-model="row.actual_unit" type="text" maxlength="30" class="mt-1 w-full rounded border p-2" placeholder="ページ・件など" :required="row.actual_quantity !== null && row.actual_quantity !== ''" @input="publish" /></label>
                </div>
            </div>
            <p class="text-xs text-gray-500">選択中：{{ selectedCount }}件、実績時間の合計：{{ timeLabel(selectedMinutes) }}。簡易予定の実績はカレンダーで修正できます。既存ジョブ予定と予定外作業はここで入力できます。</p>
        </div>
        <p v-if="errors.work_entries" class="mt-2 text-sm text-red-700">{{ errors.work_entries }}</p>
    </section>
</template>
