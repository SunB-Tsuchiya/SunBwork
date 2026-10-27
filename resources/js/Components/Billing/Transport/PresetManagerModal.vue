<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { normalizeText, parseYen, formatYen } from './transportNormalize';

const props = defineProps({
    presets:  { type: Array, default: () => [] },   // 登録済み（id あり）
    purposes: { type: Object, required: true },
    routeBase: { type: String, default: 'user.billing.transport.' },
});
defineEmits(['close']);

const local = ref([]);
watch(() => props.presets, (v) => {
    local.value = v.map((p) => ({ ...p, amountText: formatYen(p.base_amount), viaText: (p.via_stations ?? []).join('、'), saving: false }));
}, { immediate: true, deep: true });

const errors = ref({});

// 経由地は「池袋、新宿」のように、「、」「,」で区切って入れる
const parseVia = (text) => String(text ?? '').split(/[、,，]/).map((s) => normalizeText(s.trim())).filter(Boolean);

// 新しく登録する（申請の入力とは関係なく、ここで直接登録できる）
const blank = () => ({ destination: '', purpose: 'round_trip', purpose_text: '', station_from: '', station_to: '', viaText: '', fare_type: 'ic', amountText: '', auto_double: true });
const draft = ref(blank());
const adding = ref(false);
function addNew() {
    errors.value = {};
    const d = draft.value;
    if (!d.destination.trim()) { errors.value = { destination: '行先を入力してください。' }; return; }
    adding.value = true;
    router.post(route(props.routeBase + 'presets.store'), {
        destination: d.destination, purpose: d.purpose, purpose_text: d.purpose_text,
        station_from: d.station_from, station_to: d.station_to, via_stations: parseVia(d.viaText), fare_type: d.fare_type,
        base_amount: parseYen(d.amountText) ?? 0, auto_double: d.auto_double && ['round_trip', 'other'].includes(d.purpose),
    }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { draft.value = blank(); },
        onError: (e) => { errors.value = e; },
        onFinish: () => { adding.value = false; },
    });
}

function norm(p, field) { p[field] = normalizeText(p[field]); }
function onAmount(p) {
    const n = parseYen(p.amountText);
    if (n !== null) p.amountText = formatYen(n);
}
function save(p) {
    errors.value = {};
    p.saving = true;
    router.put(route(props.routeBase + 'presets.update', { preset: p.id }), {
        destination: p.destination, purpose: p.purpose, purpose_text: p.purpose_text,
        station_from: p.station_from, station_to: p.station_to, via_stations: parseVia(p.viaText), fare_type: p.fare_type,
        base_amount: parseYen(p.amountText) ?? 0, auto_double: p.auto_double && ['round_trip', 'other'].includes(p.purpose),
    }, {
        preserveScroll: true, preserveState: true,
        onError: (e) => { errors.value = e; },
        onFinish: () => { p.saving = false; },
    });
}
function remove(p) {
    if (!confirm(`「${p.destination}」の登録を削除します。よろしいですか？`)) return;
    router.delete(route(props.routeBase + 'presets.destroy', { preset: p.id }), { preserveScroll: true, preserveState: true });
}
const cell = 'w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-400 focus:outline-none';
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('close')">
            <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-lg font-semibold text-gray-800">登録した行先（{{ local.length }}件）</h3>
                    <button type="button" class="text-xl leading-none text-gray-400 hover:text-gray-600" @click="$emit('close')">✕</button>
                </div>
                <div class="overflow-y-auto px-5 py-4">
                    <p class="mb-3 text-xs text-gray-500">
                        下の「新しく登録する」に入力して、行先を登録できます（申請の入力行の「登録」ボタンでも登録できます）。登録の修正と削除もここでできます。行先を入力すると、候補として出ます（過去の入力履歴も、自動で候補に出ます）。
                    </p>

                    <div class="mb-4 rounded border border-blue-200 bg-blue-50 p-3">
                        <p class="mb-2 text-sm font-semibold text-blue-900">新しく登録する</p>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-6">
                            <label class="col-span-2 text-xs text-gray-600">行先
                                <input type="text" v-model="draft.destination" @blur="draft.destination = normalizeText(draft.destination)" :class="cell + ' mt-0.5'" placeholder="例：国際交流基金" />
                            </label>
                            <label class="text-xs text-gray-600">用件
                                <select v-model="draft.purpose" :class="cell + ' mt-0.5'">
                                    <option v-for="(label, key) in purposes" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </label>
                            <label class="col-span-2 text-xs text-gray-600">区間（定期区間外の駅から）
                                <span class="mt-0.5 flex items-center gap-1">
                                    <input type="text" v-model="draft.station_from" @blur="draft.station_from = normalizeText(draft.station_from)" :class="cell" placeholder="池袋" />
                                    <span class="text-gray-400">－</span>
                                    <input type="text" v-model="draft.station_to" @blur="draft.station_to = normalizeText(draft.station_to)" :class="cell" placeholder="新宿" />
                                </span>
                            </label>
                            <label class="col-span-2 text-xs text-gray-600">経由地（路線が変わる駅。なくてもよい）
                                <input type="text" v-model="draft.viaText" :class="cell + ' mt-0.5'" placeholder="例：池袋（複数なら「、」で区切る）" />
                            </label>
                            <label class="text-xs text-gray-600">IC/切符
                                <select v-model="draft.fare_type" :class="cell + ' mt-0.5'"><option value="ic">IC</option><option value="ticket">切符</option></select>
                            </label>
                            <label v-if="draft.purpose === 'other'" class="col-span-2 text-xs text-gray-600 md:col-span-3">用件の内容
                                <input type="text" v-model="draft.purpose_text" @blur="draft.purpose_text = normalizeText(draft.purpose_text)" :class="cell + ' mt-0.5'" />
                            </label>
                            <label class="text-xs text-gray-600">金額（片道または合計）
                                <span class="mt-0.5 flex items-center gap-1">
                                    <input type="text" inputmode="numeric" v-model="draft.amountText" @blur="(() => { const n = parseYen(draft.amountText); if (n !== null) draft.amountText = formatYen(n); })()" :class="cell + ' text-right'" />
                                    <span class="text-xs text-gray-500">円</span>
                                </span>
                            </label>
                            <label v-if="['round_trip', 'other'].includes(draft.purpose)" class="col-span-2 flex items-end gap-1 pb-1 text-xs text-gray-600">
                                <input type="checkbox" v-model="draft.auto_double" /> 片道の金額を×2（往復）
                            </label>
                            <div class="col-span-2 flex items-end justify-end md:col-span-1">
                                <button type="button" :disabled="adding" @click="addNew" class="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50">登録する</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="Object.keys(errors).length" class="mb-3 rounded bg-red-50 p-2 text-sm text-red-600">
                        <ul class="list-inside list-disc"><li v-for="(m, k) in errors" :key="k">{{ m }}</li></ul>
                    </div>
                    <p v-if="!local.length" class="py-8 text-center text-sm text-gray-400">まだ登録がありません。</p>
                    <table v-else class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-300 bg-gray-50 text-xs text-gray-600">
                                <th class="px-2 py-2 text-left">行先</th>
                                <th class="px-2 py-2 text-left">用件</th>
                                <th class="px-2 py-2 text-left">区間</th>
                                <th class="px-2 py-2 text-center">IC/切符</th>
                                <th class="px-2 py-2 text-left">金額（片道または合計）</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in local" :key="p.id" class="border-b border-gray-100 align-top">
                                <td class="px-2 py-1"><input type="text" v-model="p.destination" @blur="norm(p, 'destination')" :class="cell" /></td>
                                <td class="px-2 py-1">
                                    <select v-model="p.purpose" :class="cell">
                                        <option v-for="(label, key) in purposes" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <input v-if="p.purpose === 'other'" type="text" v-model="p.purpose_text" @blur="norm(p, 'purpose_text')" :class="cell + ' mt-1'" />
                                </td>
                                <td class="px-2 py-1">
                                    <div class="flex items-center gap-1">
                                        <input type="text" v-model="p.station_from" @blur="norm(p, 'station_from')" :class="cell" />
                                        <span class="text-gray-400">－</span>
                                        <input type="text" v-model="p.station_to" @blur="norm(p, 'station_to')" :class="cell" />
                                    </div>
                                    <input type="text" v-model="p.viaText" :class="cell + ' mt-1'" placeholder="経由地（なくてもよい。「、」で区切る）" />
                                </td>
                                <td class="px-2 py-1 text-center">
                                    <select v-model="p.fare_type" :class="cell"><option value="ic">IC</option><option value="ticket">切符</option></select>
                                </td>
                                <td class="px-2 py-1">
                                    <div class="flex items-center gap-1">
                                        <input type="text" inputmode="numeric" v-model="p.amountText" @blur="onAmount(p)" :class="cell + ' w-24 text-right'" />
                                        <span class="text-xs text-gray-500">円</span>
                                    </div>
                                    <label v-if="['round_trip', 'other'].includes(p.purpose)" class="mt-1 flex items-center gap-1 text-xs text-gray-600">
                                        <input type="checkbox" v-model="p.auto_double" /> 片道の金額を×2（往復）
                                    </label>
                                </td>
                                <td class="whitespace-nowrap px-2 py-1 text-right">
                                    <button type="button" :disabled="p.saving" @click="save(p)" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50">保存</button>
                                    <button type="button" @click="remove(p)" class="ml-1 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50">削除</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end border-t px-5 py-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="$emit('close')">閉じる</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
