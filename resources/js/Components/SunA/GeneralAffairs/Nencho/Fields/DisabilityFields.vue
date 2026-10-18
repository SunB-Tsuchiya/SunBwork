<script setup>
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import Field from './Field.vue';

// 障害者である事実（区分・手帳の種類・交付年月日・障害の内容）
const props = defineProps({
    model: { type: Object, required: true },
    classes: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    prefix: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';
const err = (k) => props.errors[`${props.prefix}${k}`] ?? '';
</script>

<template>
    <div class="space-y-3">
        <Field label="障害者の区分" :error="err('disability_class')">
            <select v-model.number="model.disability_class" :disabled="disabled" :class="input">
                <option v-for="c in classes" :key="c.value" :value="Number(c.value)">{{ c.label }}</option>
            </select>
        </Field>
        <div v-if="model.disability_class > 0" class="grid gap-3 sm:grid-cols-3">
            <Field label="手帳の種類" :error="err('disability_handbook_type')" hint="手帳がない場合は「障害者手帳を所有していない」と入力">
                <input v-model="model.disability_handbook_type" :disabled="disabled" type="text" maxlength="20" :class="input" />
            </Field>
            <Field label="交付年月日" :error="err('disability_issued_on')" example="1977/09/05（19770905・昭和52年9月5日 でも可）">
                <input v-model="model.disability_issued_on" :disabled="disabled" type="text" inputmode="numeric" v-norm="'date'" :class="input" />
            </Field>
            <Field label="障害の内容（手帳がない場合）" :error="err('disability_detail')">
                <input v-model="model.disability_detail" :disabled="disabled" type="text" maxlength="50" :class="input" />
            </Field>
        </div>
    </div>
</template>
