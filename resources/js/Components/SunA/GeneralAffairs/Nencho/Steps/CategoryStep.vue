<script setup>
import { useNenchoLockToken, useNenchoRoute } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/useNenchoRoutes';
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import ChangeFields from '../Fields/ChangeFields.vue';
import DisabilityFields from '../Fields/DisabilityFields.vue';
import Field from '../Fields/Field.vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    submission: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: false },
});

const nenchoRoute = useNenchoRoute();
const lockToken = useNenchoLockToken();

const s = props.submission;
const ch = s.category_changes ?? {};
const blankChange = (k) => ({ month: ch[k]?.month ?? '', day: ch[k]?.day ?? '', reason: ch[k]?.reason ?? '', reason_other: ch[k]?.reason_other ?? '' });
const form = useForm({
    no_category: s.no_category ?? false,
    disability_class: s.disability_class ?? 0, disability_handbook_type: s.disability_handbook_type ?? '', disability_issued_on: s.disability_issued_on ?? '', disability_detail: s.disability_detail ?? '',
    single_parent_class: s.single_parent_class ?? 0,
    working_student: s.working_student ?? false, school_name: s.school_name ?? '', enrolled_on: s.enrolled_on ?? '',
    resident_tax_single_parent_class: s.resident_tax_single_parent_class ?? 0,
    has_secondary_job_form: s.has_secondary_job_form ?? false,
    category_changes: { disability: blankChange('disability'), single_parent: blankChange('single_parent'), working_student: blankChange('working_student') },
});
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';

function save(go) {
    form.transform((d) => ({ ...d, go, lock_token: lockToken?.value })).put(nenchoRoute('steps.save', { submission: s.id, step: 2 }), { preserveScroll: true });
}
defineExpose({ save, get processing() { return form.processing; } });
</script>

<template>
    <div class="space-y-6">
        <label class="flex items-start gap-2 rounded border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800">
            <input v-model="form.no_category" :disabled="!editable" type="checkbox" class="mt-0.5 rounded text-blue-600 focus:ring-blue-500" />
            <span>障害者・ひとり親・寡婦・勤労学生の<strong>いずれにも該当しない</strong>（該当する場合は、チェックを外して入力してください）</span>
        </label>

        <template v-if="!form.no_category">
            <section class="space-y-3">
                <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">障害者</h4>
                <DisabilityFields :model="form" :classes="options.disabilityClasses.filter((c) => c.value <= 2)" :errors="form.errors" :disabled="!editable" />
                <div v-if="form.disability_class > 0 || form.category_changes.disability.reason">
                    <p class="mb-1 text-xs text-gray-500">本年中に異動（交付・返納など）があった場合のみ入力してください。</p>
                    <ChangeFields :model="form.category_changes.disability" :reasons="options.changeReasons.disability" :errors="form.errors" prefix="category_changes.disability." :disabled="!editable" />
                </div>
            </section>

            <section class="space-y-3">
                <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">ひとり親・寡婦</h4>
                <Field label="区分" :error="form.errors.single_parent_class">
                    <select v-model.number="form.single_parent_class" :disabled="!editable" :class="[input, 'sm:max-w-xs']">
                        <option v-for="c in options.singleParentClasses" :key="c.value" :value="Number(c.value)">{{ c.label }}</option>
                    </select>
                </Field>
                <div v-if="form.single_parent_class > 0">
                    <p class="mb-1 text-xs text-gray-500">本年中に異動（結婚・離婚など）があった場合のみ入力してください。</p>
                    <ChangeFields :model="form.category_changes.single_parent" :reasons="options.changeReasons.single_parent" :errors="form.errors" prefix="category_changes.single_parent." :disabled="!editable" />
                </div>
            </section>

            <section class="space-y-3">
                <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">勤労学生</h4>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.working_student" :disabled="!editable" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" />
                    勤労学生に該当する
                </label>
                <template v-if="form.working_student">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Field label="学校名" :error="form.errors.school_name"><input v-model="form.school_name" v-norm="'text'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                        <Field label="入学年月日" :error="form.errors.enrolled_on" example="1977/09/05（19770905・昭和52年9月5日 でも可）"><input v-model="form.enrolled_on" :disabled="!editable" type="text" inputmode="numeric" v-norm="'date'" :class="input" /></Field>
                    </div>
                    <p class="text-xs text-gray-500">本年中に異動（入学・卒業など）があった場合のみ入力してください。</p>
                    <ChangeFields :model="form.category_changes.working_student" :reasons="options.changeReasons.working_student" :errors="form.errors" prefix="category_changes.working_student." :disabled="!editable" />
                </template>
            </section>

            <section class="space-y-3">
                <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">住民税に関する事項（ひとり親・寡婦）</h4>
                <Field label="区分" :error="form.errors.resident_tax_single_parent_class">
                    <select v-model.number="form.resident_tax_single_parent_class" :disabled="!editable" :class="[input, 'sm:max-w-xs']">
                        <option v-for="c in options.singleParentClasses" :key="c.value" :value="Number(c.value)">{{ c.label }}</option>
                    </select>
                </Field>
            </section>
        </template>

        <label class="flex items-start gap-2 text-sm text-gray-800">
            <input v-model="form.has_secondary_job_form" :disabled="!editable" type="checkbox" class="mt-0.5 rounded text-blue-600 focus:ring-blue-500" />
            <span>他の勤め先に「従たる給与についての扶養控除等申告書」を提出している</span>
        </label>
    </div>
</template>
