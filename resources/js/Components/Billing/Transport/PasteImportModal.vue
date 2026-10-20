<script setup>
import { ref, computed, watch } from 'vue';
import { parsePaste } from './transportInput';
import { shortDate } from './transportNormalize';

const props = defineProps({
    month:    { type: String, required: true },
    purposes: { type: Object, required: true },
    hasRows:  { type: Boolean, default: false },   // 入力中の内容があるか（追加／置き換えの選択を出す）
});
const emit = defineEmits(['close', 'apply']);

const text = ref('');
const mode = ref('append');
const excluded = ref(new Set());   // 取り込まない行（行番号）

const parsed = computed(() => parsePaste(text.value, props.month, props.purposes));
watch(text, () => { excluded.value = new Set(); });

const selected = computed(() => parsed.value.filter((p) => !excluded.value.has(p.line)));
const warnCount = computed(() => parsed.value.filter((p) => p.warnings.length).length);
const total = computed(() => selected.value.reduce((s, p) => s + (p.row.amount || 0), 0));

function toggle(line) {
    const s = new Set(excluded.value);
    s.has(line) ? s.delete(line) : s.add(line);
    excluded.value = s;
}
function purposeLabel(r) {
    return r.purpose === 'other' ? (r.purpose_text || 'その他') : (props.purposes[r.purpose] ?? r.purpose);
}
function apply() {
    emit('apply', { rows: selected.value.map((p) => p.row), mode: props.hasRows ? mode.value : 'append' });
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('close')">
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-lg font-semibold text-gray-800">まとめて貼り付け</h3>
                    <button type="button" class="text-xl leading-none text-gray-400 hover:text-gray-600" @click="$emit('close')">✕</button>
                </div>

                <div class="space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">ここに貼り付けます（複数行できます）</label>
                        <textarea v-model="text" rows="6" wrap="off"
                            class="w-full rounded border border-gray-300 px-3 py-2 font-mono text-sm focus:border-blue-400 focus:outline-none"></textarea>
                        <div class="mt-1 space-y-0.5 text-xs text-gray-500">
                            <p>Excel の伝票の行（発生日から金額まで）を、そのままコピーして貼り付けられます。</p>
                            <p>例：　9月1日 ⇥ 国際交流基金 ⇥ 打合せ（往復） ⇥ 田端－市ヶ谷 ⇥ IC ⇥ 728　（⇥ はタブ。カンマ区切りも可）</p>
                            <p>金額は、そのまま合計として取り込みます（往復の金額を、さらに2倍にはしません）。IC/切符が無ければ「IC」です。</p>
                        </div>
                    </div>

                    <div v-if="parsed.length">
                        <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="font-medium text-gray-800">{{ parsed.length }} 行を読み取りました</span>
                            <span v-if="warnCount" class="text-red-600">注意が必要な行: {{ warnCount }} 行</span>
                            <span class="text-gray-600">取り込む: {{ selected.length }} 行 / 合計 {{ total.toLocaleString() }} 円</span>
                        </div>
                        <div class="max-h-72 overflow-auto rounded border border-gray-200">
                            <table class="w-full border-collapse text-xs">
                                <thead class="sticky top-0 bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-2 py-1.5">取込</th>
                                        <th class="px-2 py-1.5 text-left">発生日</th>
                                        <th class="px-2 py-1.5 text-left">行先</th>
                                        <th class="px-2 py-1.5 text-left">用件</th>
                                        <th class="px-2 py-1.5 text-left">区間</th>
                                        <th class="px-2 py-1.5">IC/切符</th>
                                        <th class="px-2 py-1.5 text-right">金額</th>
                                        <th class="px-2 py-1.5 text-left">注意</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in parsed" :key="p.line" class="border-t border-gray-100"
                                        :class="[p.warnings.length ? 'bg-red-50' : '', excluded.has(p.line) ? 'opacity-40' : '']">
                                        <td class="px-2 py-1 text-center"><input type="checkbox" :checked="!excluded.has(p.line)" @change="toggle(p.line)" /></td>
                                        <td class="px-2 py-1">{{ p.row.occurrence_date ? shortDate(p.row.occurrence_date) : '' }}</td>
                                        <td class="px-2 py-1">{{ p.row.destination }}</td>
                                        <td class="px-2 py-1">{{ purposeLabel(p.row) }}</td>
                                        <td class="px-2 py-1">{{ p.row.station_from }} － {{ p.row.station_to }}</td>
                                        <td class="px-2 py-1 text-center">{{ p.row.fare_type === 'ic' ? 'IC' : '切符' }}</td>
                                        <td class="px-2 py-1 text-right">{{ p.row.amount ? p.row.amount.toLocaleString() : '' }}</td>
                                        <td class="px-2 py-1 text-red-600">{{ p.warnings.join(' / ') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="warnCount" class="mt-1 text-xs text-gray-500">
                            注意の行も取り込めます。取り込んだあとに、入力欄で直してください（日付が対象月の範囲外のままだと、保存できません）。
                        </p>
                    </div>
                    <p v-else-if="text.trim()" class="text-sm text-red-600">読み取れる行がありません。</p>

                    <div v-if="hasRows && parsed.length" class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-1.5"><input type="radio" value="append" v-model="mode" /> 入力中の内容に追加する</label>
                        <label class="flex items-center gap-1.5"><input type="radio" value="replace" v-model="mode" /> 入力中の内容を置き換える</label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t px-5 py-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="$emit('close')">キャンセル</button>
                    <button type="button" :disabled="!selected.length" @click="apply"
                        class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40">
                        {{ selected.length }} 行を取り込む
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
