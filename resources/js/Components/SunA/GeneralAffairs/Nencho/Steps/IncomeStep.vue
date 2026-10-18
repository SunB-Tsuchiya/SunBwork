<script setup>
import { useNenchoLockToken, useNenchoRoute } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/useNenchoRoutes';
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import Field from '../Fields/Field.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    submission: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

const nenchoRoute = useNenchoRoute();
const lockToken = useNenchoLockToken();

const s = props.submission;
const form = useForm({
    salary_income: s.salary_income ?? '', salary_income_estimate: s.salary_income_estimate ?? '', other_income_estimate: s.other_income_estimate ?? '',
});
const num = (v) => Number(String(v).replace(/[^\d-]/g, '')) || 0;
const total = computed(() => num(form.salary_income_estimate) + num(form.other_income_estimate));
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:max-w-xs';

function save(go) {
    form.transform((d) => ({ ...d, go, lock_token: lockToken?.value })).put(nenchoRoute('steps.save', { submission: s.id, step: 3 }), { preserveScroll: true });
}
defineExpose({ save, get processing() { return form.processing; } });
</script>

<template>
    <div class="space-y-5">
        <p class="rounded bg-blue-50 p-3 text-sm text-gray-700">本年中の見込みの金額を、<strong>円単位</strong>で入力してください。「4500000」「4,500,000」「４５０万円」のどれで入力しても、欄を離れると自動で「4,500,000」の形に直ります。ない場合は「0」を入力してください。給与明細などを参考にしてください。</p>
        <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="本年の給与収入（円）" required :error="form.errors.salary_income" example="例：4,500,000">
            <input v-model="form.salary_income" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" />
        </Field>
        <Field label="本年の給与所得の見積額（円）" required :error="form.errors.salary_income_estimate" hint="給与収入から給与所得控除を引いた金額です。" example="例：3,100,000">
            <input v-model="form.salary_income_estimate" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" />
        </Field>
        <Field label="給与所得以外の所得の見積額（円）" required :error="form.errors.other_income_estimate" hint="副業などがなければ 0。マイナスの場合は先頭に「-」を付けます。" example="例：0">
            <input v-model="form.other_income_estimate" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" />
        </Field>
        <p class="text-sm text-gray-700">本年の合計所得の見積額（自動計算）：<span class="font-semibold">{{ total.toLocaleString() }} 円</span></p>
    </div>
</template>
