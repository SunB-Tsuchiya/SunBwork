<script setup>
import Field from './Field.vue';

// 異動（月日・事由）。model は { month, day, reason, reason_other } を持つオブジェクト
const props = defineProps({
    model: { type: Object, required: true },
    reasons: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    prefix: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';
const err = (k) => props.errors[`${props.prefix}${k}`] ?? '';
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-4">
        <Field label="異動の月" :error="err('month')">
            <input v-model.number="model.month" :disabled="disabled" type="number" min="1" max="12" :class="input" />
        </Field>
        <Field label="異動の日" :error="err('day')">
            <input v-model.number="model.day" :disabled="disabled" type="number" min="1" max="31" :class="input" />
        </Field>
        <Field label="異動の事由" :error="err('reason')">
            <select v-model.number="model.reason" :disabled="disabled" :class="input">
                <option value="">選択</option>
                <option v-for="r in reasons" :key="r.value" :value="Number(r.value)">{{ r.label }}</option>
            </select>
        </Field>
        <Field v-if="model.reason === 99" label="その他の事由" :error="err('reason_other')">
            <input v-model="model.reason_other" :disabled="disabled" type="text" maxlength="50" :class="input" />
        </Field>
    </div>
</template>
