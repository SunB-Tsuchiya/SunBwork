<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
    modelValue:  { type: Object, required: true },
    purposes:    { type: Object, required: true },
    index:       { type: Number, required: true },
    billingYear: { type: Number, required: true },
});

const emit = defineEmits(['update:modelValue', 'remove']);

function update(field, value) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

const purposeOptions = computed(() =>
    Object.entries(props.purposes).map(([value, label]) => ({ value, label }))
);

const showPurposeText = computed(() => props.modelValue.purpose === 'other');

const fareTypes = [
    { value: 'ic',     label: 'IC' },
    { value: 'ticket', label: '切符' },
];

// 往復自動計算
const isRoundTrip = computed(() => props.modelValue.purpose === 'round_trip');

function onBaseAmountInput(e) {
    const base       = parseInt(e.target.value) || 0;
    const multiplier = (isRoundTrip.value && props.modelValue.auto_double) ? 2 : 1;
    emit('update:modelValue', {
        ...props.modelValue,
        base_amount: base,
        amount:      base * multiplier,
    });
}

function onAutoDoubleChange(e) {
    const checked    = e.target.checked;
    const base       = props.modelValue.base_amount ?? props.modelValue.amount;
    const multiplier = (isRoundTrip.value && checked) ? 2 : 1;
    emit('update:modelValue', {
        ...props.modelValue,
        auto_double: checked,
        base_amount: base,
        amount:      base * multiplier,
    });
}

// 用件が往復以外に変わったらチェックを外して金額を再計算
watch(isRoundTrip, (val) => {
    if (!val && props.modelValue.auto_double) {
        const base = props.modelValue.base_amount ?? props.modelValue.amount;
        emit('update:modelValue', {
            ...props.modelValue,
            auto_double: false,
            amount:      base,
        });
    }
});
</script>

<template>
    <tr class="border-b border-gray-200 hover:bg-gray-50">

        <!-- 発生日（カレンダーピッカー） -->
        <td class="px-2 py-1">
            <input
                type="date"
                :value="modelValue.occurrence_date ?? ''"
                @input="update('occurrence_date', $event.target.value || null)"
                class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none"
            />
        </td>

        <!-- 行先 -->
        <td class="px-2 py-1">
            <input
                type="text"
                :value="modelValue.destination"
                @input="update('destination', $event.target.value)"
                placeholder="行先"
                class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none"
            />
        </td>

        <!-- 用件 -->
        <td class="px-2 py-1">
            <div class="flex flex-col gap-1">
                <select
                    :value="modelValue.purpose"
                    @change="update('purpose', $event.target.value)"
                    class="rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none"
                >
                    <option v-for="opt in purposeOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <input
                    v-if="showPurposeText"
                    type="text"
                    :value="modelValue.purpose_text"
                    @input="update('purpose_text', $event.target.value)"
                    placeholder="内容を入力"
                    class="rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-400 focus:outline-none"
                />
            </div>
        </td>

        <!-- 区間（出発 → 到着） -->
        <td class="px-2 py-1">
            <div class="flex items-center gap-1">
                <input
                    type="text"
                    :value="modelValue.station_from"
                    @input="update('station_from', $event.target.value)"
                    placeholder="出発駅"
                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none"
                />
                <span class="shrink-0 text-gray-400 text-sm">→</span>
                <input
                    type="text"
                    :value="modelValue.station_to"
                    @input="update('station_to', $event.target.value)"
                    placeholder="到着駅"
                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none"
                />
            </div>
        </td>

        <!-- IC / 切符 -->
        <td class="px-2 py-1">
            <div class="flex gap-1 justify-center">
                <button
                    v-for="ft in fareTypes"
                    :key="ft.value"
                    type="button"
                    @click="update('fare_type', ft.value)"
                    :class="[
                        'rounded px-2.5 py-1 text-xs font-medium border transition-colors whitespace-nowrap',
                        modelValue.fare_type === ft.value
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-100',
                    ]"
                >
                    {{ ft.label }}
                </button>
            </div>
        </td>

        <!-- 金額 -->
        <td class="px-2 py-1">
            <div class="space-y-1">
                <div class="flex items-center gap-1">
                    <input
                        type="number"
                        :value="modelValue.base_amount ?? modelValue.amount"
                        @input="onBaseAmountInput"
                        min="0"
                        :placeholder="isRoundTrip ? '片道' : '金額'"
                        class="w-20 rounded border border-gray-300 px-2 py-1 text-sm text-right focus:border-blue-400 focus:outline-none"
                    />
                    <span class="text-sm text-gray-500">円</span>
                </div>
                <!-- 往復チェックボックス（往復用件のときのみ表示） -->
                <label
                    v-if="isRoundTrip"
                    class="flex items-center gap-1 cursor-pointer select-none"
                >
                    <input
                        type="checkbox"
                        :checked="modelValue.auto_double"
                        @change="onAutoDoubleChange"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span class="text-xs text-gray-600">往復×2</span>
                    <span
                        v-if="modelValue.auto_double"
                        class="text-xs font-medium text-blue-700"
                    >
                        = {{ modelValue.amount.toLocaleString() }}円
                    </span>
                </label>
            </div>
        </td>

        <!-- 削除 -->
        <td class="px-2 py-1 text-center">
            <button
                type="button"
                @click="$emit('remove')"
                class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50 hover:text-red-700"
            >
                削除
            </button>
        </td>
    </tr>
</template>
