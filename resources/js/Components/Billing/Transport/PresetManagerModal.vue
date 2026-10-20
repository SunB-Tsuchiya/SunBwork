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
    local.value = v.map((p) => ({ ...p, amountText: formatYen(p.base_amount), saving: false }));
}, { immediate: true, deep: true });

const errors = ref({});

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
        station_from: p.station_from, station_to: p.station_to, fare_type: p.fare_type,
        base_amount: parseYen(p.amountText) ?? 0, auto_double: p.auto_double && p.purpose === 'round_trip',
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
                        登録は、入力した行の「登録」ボタンでできます。ここでは、登録の修正と削除ができます。行先を入力すると、候補として出ます（過去の入力履歴も、自動で候補に出ます）。
                    </p>
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
                                </td>
                                <td class="px-2 py-1 text-center">
                                    <select v-model="p.fare_type" :class="cell"><option value="ic">IC</option><option value="ticket">切符</option></select>
                                </td>
                                <td class="px-2 py-1">
                                    <div class="flex items-center gap-1">
                                        <input type="text" inputmode="numeric" v-model="p.amountText" @blur="onAmount(p)" :class="cell + ' w-24 text-right'" />
                                        <span class="text-xs text-gray-500">円</span>
                                    </div>
                                    <label v-if="p.purpose === 'round_trip'" class="mt-1 flex items-center gap-1 text-xs text-gray-600">
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
