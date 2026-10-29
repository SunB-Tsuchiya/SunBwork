<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費メモの窓（Phase24-5）。部署シートの科目・支払先の行から開き、その付け先のメモを追加・編集・削除する。
// 翌年度に同じ付け先を開いたときは、前年度のメモも並べて見られる（読み取りのみ）。
// 編集・削除できるのは、書いた本人とSuperAdminだけ（サーバー側でも判定。画面は can_edit で出し分ける）。

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentKey: { type: String, required: true },
    departmentLabel: { type: String, default: '' },
    fiscalYear: { type: Number, required: true },
    // 付け先: category=null なら部署全体、vendor=null なら科目全体
    target: { type: Object, required: true },
    notes: { type: Array, default: () => [] },
    previousNotes: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'changed']);

const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;
const { fiscalPeriodLabel } = useSalesChart();

// 暦月の選択肢（4月始まり）。空=年間へのメモ
const monthOptions = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
const monthLabel = (m) => (m ? `${m}月` : '年間');

const targetLabel = computed(() => {
    const t = props.target;
    if (t.category === null) return `${props.departmentLabel}（部署全体）`;
    return t.vendor ? `${t.category} › ${t.vendor}` : `${t.category}（科目全体）`;
});

const newBody = ref('');
const newMonth = ref('');
const saving = ref(false);
const errorMessage = ref('');

const editingId = ref(null);
const editBody = ref('');
const editMonth = ref('');

const dateText = (iso) => (iso ? new Date(iso).toLocaleDateString('ja-JP') : '');

const request = async (fn) => {
    saving.value = true;
    errorMessage.value = '';
    try {
        await fn();
        emit('changed');
        return true;
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? '保存に失敗しました。';
        return false;
    } finally {
        saving.value = false;
    }
};

const add = async () => {
    if (!newBody.value.trim()) return;
    const ok = await request(() => axios.post(route(rn('api.expense_notes.store')), {
        department_key: props.departmentKey,
        fiscal_year: props.fiscalYear,
        category: props.target.category,
        vendor_name: props.target.vendor,
        calendar_month: newMonth.value || null,
        body: newBody.value,
    }));
    if (ok) {
        newBody.value = '';
        newMonth.value = '';
    }
};

const startEdit = (n) => {
    editingId.value = n.id;
    editBody.value = n.body;
    editMonth.value = n.calendar_month ?? '';
};
const saveEdit = async () => {
    if (!editBody.value.trim()) return;
    const ok = await request(() => axios.put(route(rn('api.expense_notes.update'), { note: editingId.value }), {
        body: editBody.value,
        calendar_month: editMonth.value || null,
    }));
    if (ok) editingId.value = null;
};
const remove = async (n) => {
    if (!window.confirm('このメモを削除しますか？')) return;
    await request(() => axios.delete(route(rn('api.expense_notes.destroy'), { note: n.id })));
};

const onKeydown = (e) => {
    if (e.key === 'Escape') emit('close');
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3">
            <!-- 背景クリックで閉じる（外枠に@click.selfを付けると、文字選択のドラッグを外で離したときにも閉じてしまうため背景要素だけで受ける） -->
            <div class="absolute inset-0 cursor-pointer bg-black/40" @click="emit('close')" />
            <div class="relative max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-2 border-b border-gray-200 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800">メモ: {{ targetLabel }}</h2>
                        <p class="text-[11px] text-gray-500">{{ departmentLabel }}・{{ fiscalPeriodLabel(fiscalYear) }}</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600" title="閉じる" @click="emit('close')">✕</button>
                </div>

                <div class="space-y-3 px-4 py-3 text-xs">
                    <p v-if="errorMessage" class="rounded bg-red-50 p-2 text-red-700">{{ errorMessage }}</p>

                    <p v-if="!notes.length" class="text-gray-400">この年度のメモはまだありません。</p>
                    <ul class="space-y-2">
                        <li v-for="n in notes" :key="n.id" class="rounded border border-gray-200 p-2">
                            <div class="mb-1 flex flex-wrap items-center gap-x-2 text-[11px] text-gray-500">
                                <span class="font-medium text-gray-700">{{ n.author_name }}</span>
                                <span>{{ dateText(n.created_at) }}{{ n.is_edited ? '（編集済み）' : '' }}</span>
                                <span v-if="n.calendar_month" class="rounded bg-indigo-50 px-1 text-indigo-700">{{ n.calendar_month }}月</span>
                                <span v-if="n.can_edit && editingId !== n.id" class="ml-auto flex gap-2">
                                    <button type="button" class="text-indigo-600 hover:underline" @click="startEdit(n)">編集</button>
                                    <button type="button" class="text-red-500 hover:underline" :disabled="saving" @click="remove(n)">削除</button>
                                </span>
                            </div>
                            <template v-if="editingId === n.id">
                                <textarea v-model="editBody" rows="3" maxlength="1000" class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <div class="mt-1 flex items-center gap-2">
                                    <select v-model="editMonth" class="rounded-md border-gray-300 text-xs shadow-sm">
                                        <option value="">年間</option>
                                        <option v-for="m in monthOptions" :key="m" :value="m">{{ monthLabel(m) }}</option>
                                    </select>
                                    <button type="button" :disabled="saving || !editBody.trim()" class="rounded bg-indigo-600 px-2 py-1 font-bold text-white hover:bg-indigo-700 disabled:opacity-50" @click="saveEdit">保存</button>
                                    <button type="button" class="text-gray-500 hover:underline" @click="editingId = null">キャンセル</button>
                                </div>
                            </template>
                            <p v-else class="whitespace-pre-wrap break-words text-gray-800">{{ n.body }}</p>
                        </li>
                    </ul>

                    <div class="rounded border border-dashed border-gray-300 p-2">
                        <p class="mb-1 font-medium text-gray-700">メモを追加</p>
                        <textarea
                            v-model="newBody"
                            rows="3"
                            maxlength="1000"
                            placeholder="例: 臨時案件のため増"
                            class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <label class="flex items-center gap-1 text-gray-600">
                                対象の月
                                <select v-model="newMonth" class="rounded-md border-gray-300 text-xs shadow-sm">
                                    <option value="">年間</option>
                                    <option v-for="m in monthOptions" :key="m" :value="m">{{ monthLabel(m) }}</option>
                                </select>
                            </label>
                            <span class="text-gray-400">{{ newBody.length }}/1000</span>
                            <button type="button" :disabled="saving || !newBody.trim()" class="ml-auto rounded-md bg-indigo-600 px-3 py-1 font-bold text-white hover:bg-indigo-700 disabled:opacity-50" @click="add">追加</button>
                        </div>
                    </div>

                    <div v-if="previousNotes.length" class="rounded bg-gray-50 p-2">
                        <p class="mb-1 font-medium text-gray-700">{{ fiscalPeriodLabel(fiscalYear - 1) }}のメモ（前年度）</p>
                        <ul class="space-y-1.5">
                            <li v-for="n in previousNotes" :key="n.id" class="text-gray-700">
                                <span class="mr-1 text-[11px] text-gray-500">{{ n.author_name }}・{{ dateText(n.created_at) }}<template v-if="n.calendar_month">・{{ n.calendar_month }}月</template></span>
                                <span class="whitespace-pre-wrap break-words">{{ n.body }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
