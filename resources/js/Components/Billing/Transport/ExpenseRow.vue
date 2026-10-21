<script setup>
import { computed, ref, watch } from 'vue';
import { normalizeText, parseYen, formatYen, parseOccurrenceDate } from './transportNormalize';

const props = defineProps({
    modelValue: { type: Object, required: true },
    purposes:   { type: Object, required: true },
    index:      { type: Number, required: true },
    month:      { type: String, required: true },   // 対象月 YYYY-MM
    readonly:   { type: Boolean, default: false },
    error:      { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'remove', 'duplicate', 'multi', 'register', 'next', 'destination-change']);

function update(field, value) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

const purposeOptions = computed(() =>
    Object.entries(props.purposes).map(([value, label]) => ({ value, label }))
);
const showPurposeText = computed(() => props.modelValue.purpose === 'other');
const fareTypes = [
    { value: 'ic', label: 'IC' },
    { value: 'ticket', label: '切符' },
];

// ─ 発生日（1 つのテキスト欄。0422 / 4/22 / 22 などで入力 → 欄を離れると YYYY-MM-DD） ─
const dateText = ref(props.modelValue.occurrence_date ?? '');
watch(() => props.modelValue.occurrence_date, (v) => { dateText.value = v ?? ''; });
function onDateBlur() {
    const next = parseOccurrenceDate(dateText.value, props.month);
    dateText.value = next ?? '';
    update('occurrence_date', next || null);
}

// ─ テキスト（全角に整える） ─
function onTextBlur(field, e) {
    const next = normalizeText(e.target.value);
    e.target.value = next;
    update(field, next);
}

// ─ 金額（円・整数・入力中にカンマ。往復は片道×2 にできる） ─
// 「片道の金額を×2（往復）」は、「打合せ（往復）」と「その他」で使える
const canDouble = computed(() => ['round_trip', 'other'].includes(props.modelValue.purpose));
const baseText = ref(formatYen(props.modelValue.base_amount ?? props.modelValue.amount));
watch(() => props.modelValue.base_amount, (v) => {
    if (parseYen(baseText.value) !== v) baseText.value = formatYen(v);
});

function applyAmount(base, doubled) {
    emit('update:modelValue', {
        ...props.modelValue,
        base_amount: base,
        auto_double: doubled,
        amount: base * (canDouble.value && doubled ? 2 : 1),
    });
}
function onAmountInput(e) {
    const n = parseYen(e.target.value);
    if (n === null) return;   // 数字として読めない途中入力はそのまま（欄を離れたときに直す）
    baseText.value = formatYen(n);
    e.target.value = baseText.value;
    applyAmount(n, props.modelValue.auto_double);
}
function onAmountBlur(e) {
    const n = parseYen(e.target.value) ?? 0;
    baseText.value = formatYen(n);
    e.target.value = baseText.value;
    applyAmount(n, props.modelValue.auto_double);
}
// 用件を変えたとき: 往復なら「×2」を自動でチェック、「その他」は今の状態のまま、それ以外は外して金額を片道に戻す
function onPurposeChange(e) {
    const purpose = e.target.value;
    const base = props.modelValue.base_amount ?? props.modelValue.amount;
    const doubled = purpose === 'round_trip' || (purpose === 'other' && !!props.modelValue.auto_double);
    emit('update:modelValue', {
        ...props.modelValue,
        purpose,
        base_amount: base,
        auto_double: doubled,
        amount: base * (doubled ? 2 : 1),
    });
}
function onDoubleChange(e) {
    applyAmount(props.modelValue.base_amount ?? props.modelValue.amount, e.target.checked);
}
watch(canDouble, (val) => {
    if (!val && props.modelValue.auto_double) {
        applyAmount(props.modelValue.base_amount ?? props.modelValue.amount, false);
    }
});

const inputCls = 'w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-50';
</script>

<template>
    <tr class="border-b border-gray-200 align-top hover:bg-gray-50" :class="{ 'bg-red-50': error }" :data-row="index">
        <!-- 行番号（エラーの「○行目」と対応する） -->
        <td class="w-8 px-1 py-2 text-center text-xs text-gray-400 select-none">{{ index + 1 }}</td>

        <td class="px-2 py-1">
            <input type="text" inputmode="numeric" v-model="dateText" @blur="onDateBlur" :disabled="readonly" data-field="date"
                placeholder="" :class="inputCls" />
            <p v-if="error" class="mt-0.5 text-xs text-red-600">{{ error }}</p>
        </td>

        <td class="px-2 py-1">
            <input type="text" :value="modelValue.destination" :disabled="readonly" list="transport-destinations" autocomplete="off"
                @input="update('destination', $event.target.value)" @change="$emit('destination-change', $event.target.value)"
                @blur="onTextBlur('destination', $event)" :class="inputCls" />
        </td>

        <td class="px-2 py-1">
            <div class="flex flex-col gap-1">
                <select :value="modelValue.purpose" :disabled="readonly"
                    @change="onPurposeChange" :class="inputCls">
                    <option v-for="opt in purposeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <input v-if="showPurposeText" type="text" :value="modelValue.purpose_text" :disabled="readonly"
                    @input="update('purpose_text', $event.target.value)" @blur="onTextBlur('purpose_text', $event)"
                    :class="inputCls" />
                <p v-if="showPurposeText" class="text-xs text-gray-500">例：　客先訪問</p>
            </div>
        </td>

        <td class="px-2 py-1">
            <div class="flex items-center gap-1">
                <input type="text" :value="modelValue.station_from" :disabled="readonly"
                    @input="update('station_from', $event.target.value)" @blur="onTextBlur('station_from', $event)"
                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-50" />
                <span class="shrink-0 text-sm text-gray-400">－</span>
                <input type="text" :value="modelValue.station_to" :disabled="readonly"
                    @input="update('station_to', $event.target.value)" @blur="onTextBlur('station_to', $event)"
                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-50" />
            </div>
        </td>

        <td class="px-2 py-1">
            <div class="flex justify-center gap-1">
                <button v-for="ft in fareTypes" :key="ft.value" type="button" :disabled="readonly"
                    @click="update('fare_type', ft.value)"
                    :class="[
                        'whitespace-nowrap rounded border px-2.5 py-1 text-xs font-medium transition-colors',
                        modelValue.fare_type === ft.value
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-100',
                    ]">{{ ft.label }}</button>
            </div>
        </td>

        <td class="px-2 py-1">
            <div class="space-y-1">
                <div class="flex items-center gap-1">
                    <input type="text" inputmode="numeric" :value="readonly ? formatYen(modelValue.amount) : baseText" :disabled="readonly"
                        @input="onAmountInput" @blur="onAmountBlur" @keydown.enter.prevent="$emit('next')"
                        class="w-24 rounded border border-gray-300 px-2 py-1 text-right text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-50" />
                    <span class="text-sm text-gray-500">円</span>
                </div>
                <p v-if="readonly && modelValue.auto_double" class="text-xs text-gray-500">（片道 {{ formatYen(modelValue.base_amount) }}円 ×2）</p>
                <label v-if="canDouble && !readonly" class="flex cursor-pointer select-none items-center gap-1">
                    <input type="checkbox" :checked="modelValue.auto_double" @change="onDoubleChange"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    <span class="text-xs text-gray-600">片道の金額を×2（往復）</span>
                    <span v-if="modelValue.auto_double" class="text-xs font-medium text-blue-700">
                        ＝ {{ modelValue.amount.toLocaleString() }}円
                    </span>
                </label>
            </div>
        </td>

        <td class="px-1 py-1">
            <div v-if="!readonly" class="grid grid-cols-2 gap-0.5 text-xs">
                <button type="button" @click="$emit('duplicate')" title="この行を、日付だけ空にして複製します" class="rounded px-1.5 py-1 text-gray-600 hover:bg-gray-100">複製</button>
                <button type="button" @click="$emit('multi')" title="同じ内容を、複数の日にまとめて追加します" class="rounded px-1.5 py-1 text-gray-600 hover:bg-gray-100">複数日</button>
                <button type="button" @click="$emit('register')" title="行先・区間・金額を「よく使う行き先」に登録します" class="rounded px-1.5 py-1 text-blue-600 hover:bg-blue-50">登録</button>
                <button type="button" @click="$emit('remove')" class="rounded px-1.5 py-1 text-red-500 hover:bg-red-50 hover:text-red-700">削除</button>
            </div>
        </td>
    </tr>
</template>
