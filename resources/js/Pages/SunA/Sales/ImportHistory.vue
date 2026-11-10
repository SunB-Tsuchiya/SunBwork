<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';

// 売上レポートの取込履歴。取込ごとの件数・ファイル名・取込者・日時と、「何が変わったか」（変更ログ）を見る。
// 取込に使ったExcelファイル自体は保存していない（変更のあった行だけを、変更ログとして残している）。
const props = defineProps({
    imports: { type: Array, default: () => [] },
});

const selected = ref(null); // 変更ログを開いている取込
const loading = ref(false);
const error = ref('');
const changes = ref(null);

const importedAt = (iso) => (iso ? new Date(iso).toLocaleString('ja-JP', { dateStyle: 'short', timeStyle: 'short' }) : '');
const KIND_LABELS = { added: '追加', changed: '変更', removed: '削除' };
const KIND_CLASS = { added: 'bg-blue-100 text-blue-700', changed: 'bg-amber-100 text-amber-700', removed: 'bg-red-100 text-red-700' };
const FIELD_LABELS = { amount: '売上金額', order_count: '受注件数', department: '担当部門' };

const loadChanges = async (imp, page = 1) => {
    selected.value = imp;
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.get(route('suna.sales.report.api.history.changes', { import: imp.id }), { params: { page } });
        changes.value = response.data;
    } catch (e) {
        changes.value = null;
        error.value = '変更内容の取得に失敗しました。';
    } finally {
        loading.value = false;
    }
};

const valueText = (c, which) => {
    const v = which === 'old' ? c.old : c.new;
    if (v === null || v === undefined) return '—';
    if (c.field === 'amount') return `¥${Number(v).toLocaleString()}`;
    return v;
};
const pageCount = computed(() => (changes.value ? Math.max(1, Math.ceil(changes.value.total / changes.value.per_page)) : 1));
</script>

<template>
    <ReportShell title="取込履歴" active="history">
        <div class="space-y-4">
            <div class="rounded bg-white p-4 shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-900">取込の履歴</h2>
                <p v-if="!props.imports.length" class="text-xs text-gray-500">まだ取り込まれていません。</p>
                <div v-else class="overflow-x-auto rounded border">
                    <table class="min-w-full text-xs tabular-nums">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="px-2 py-1.5 text-left">取込日時</th>
                                <th class="px-2 py-1.5 text-left">取込者</th>
                                <th class="px-2 py-1.5 text-left">ファイル名</th>
                                <th class="px-2 py-1.5 text-right">対象の年</th>
                                <th class="px-2 py-1.5 text-right">行数</th>
                                <th class="px-2 py-1.5 text-right">追加</th>
                                <th class="px-2 py-1.5 text-right">変更</th>
                                <th class="px-2 py-1.5 text-right">削除</th>
                                <th class="px-2 py-1.5 text-right">変更なし</th>
                                <th class="px-2 py-1.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in props.imports" :key="i.id" class="border-t" :class="selected?.id === i.id ? 'bg-indigo-50' : ''">
                                <td class="px-2 py-1">{{ importedAt(i.imported_at) }}</td>
                                <td class="px-2 py-1">{{ i.imported_by_name }}</td>
                                <td class="px-2 py-1">{{ i.original_filename }}<span v-if="i.replace_years.length" class="ml-1 rounded bg-red-100 px-1 text-[10px] text-red-700">{{ i.replace_years.join('・') }}年を入れ替え</span></td>
                                <td class="px-2 py-1 text-right">{{ i.year_min }}〜{{ i.year_max }}年</td>
                                <td class="px-2 py-1 text-right">{{ i.row_count.toLocaleString() }}</td>
                                <td class="px-2 py-1 text-right">{{ i.added_count.toLocaleString() }}</td>
                                <td class="px-2 py-1 text-right">{{ i.changed_count.toLocaleString() }}</td>
                                <td class="px-2 py-1 text-right">{{ i.removed_count.toLocaleString() }}</td>
                                <td class="px-2 py-1 text-right text-gray-400">{{ i.unchanged_count.toLocaleString() }}</td>
                                <td class="px-2 py-1 text-right">
                                    <button type="button" class="text-indigo-600 hover:underline" @click="loadChanges(i)">変更内容を見る</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">取込に使ったExcelファイルは保存していません。変更のあった行（追加・変更・削除）だけを、変更内容として残しています。</p>
            </div>

            <div v-if="selected" class="rounded bg-white p-4 shadow">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-900">変更内容：{{ importedAt(selected.imported_at) }}・{{ selected.original_filename }}</h3>
                    <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="selected = null; changes = null">× 閉じる</button>
                </div>
                <p v-if="loading" class="text-xs text-gray-500">読み込み中...</p>
                <p v-else-if="error" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ error }}</p>
                <template v-else-if="changes">
                    <ul v-if="changes.import.warnings.length" class="mb-2 list-disc space-y-0.5 rounded bg-amber-50 p-2 pl-6 text-xs text-amber-800">
                        <li v-for="(w, k) in changes.import.warnings" :key="k">{{ w }}</li>
                    </ul>
                    <p v-if="!changes.total" class="text-xs text-gray-500">この取込で変わった行はありません。</p>
                    <template v-else>
                        <div class="max-h-[55vh] overflow-auto rounded border">
                            <table class="min-w-full text-xs tabular-nums">
                                <thead class="sticky top-0 bg-gray-100 text-gray-600">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left">得意先</th>
                                        <th class="px-2 py-1.5 text-right">年</th>
                                        <th class="px-2 py-1.5 text-right">月</th>
                                        <th class="px-2 py-1.5 text-left">種別</th>
                                        <th class="px-2 py-1.5 text-left">項目</th>
                                        <th class="px-2 py-1.5 text-right">変更前</th>
                                        <th class="px-2 py-1.5 text-right">変更後</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(c, k) in changes.changes" :key="k" class="border-t">
                                        <td class="px-2 py-1">{{ c.client_name }}</td>
                                        <td class="px-2 py-1 text-right">{{ c.year }}</td>
                                        <td class="px-2 py-1 text-right">{{ c.month ? `${c.month}月` : '（年）' }}</td>
                                        <td class="px-2 py-1"><span class="rounded px-1 text-[10px]" :class="KIND_CLASS[c.kind]">{{ KIND_LABELS[c.kind] }}</span></td>
                                        <td class="px-2 py-1 text-gray-500">{{ FIELD_LABELS[c.field] }}</td>
                                        <td class="px-2 py-1 text-right text-gray-500">{{ valueText(c, 'old') }}</td>
                                        <td class="px-2 py-1 text-right">{{ valueText(c, 'new') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                            <span>全{{ changes.total.toLocaleString() }}件</span>
                            <button v-if="changes.page > 1" type="button" class="rounded border px-2 py-0.5 hover:bg-gray-50" @click="loadChanges(selected, changes.page - 1)">← 前のページ</button>
                            <span>{{ changes.page }} / {{ pageCount }}</span>
                            <button v-if="changes.page < pageCount" type="button" class="rounded border px-2 py-0.5 hover:bg-gray-50" @click="loadChanges(selected, changes.page + 1)">次のページ →</button>
                        </div>
                    </template>
                </template>
            </div>
        </div>
    </ReportShell>
</template>
