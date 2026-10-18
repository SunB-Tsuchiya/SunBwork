<script setup>
import { useNenchoLockToken, useNenchoRoute } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/useNenchoRoutes';
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import AddressFields from '../Fields/AddressFields.vue';
import ChangeFields from '../Fields/ChangeFields.vue';
import DisabilityFields from '../Fields/DisabilityFields.vue';
import Field from '../Fields/Field.vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    submission: { type: Object, required: true },
    spouse: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
    prefectures: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
});

const nenchoRoute = useNenchoRoute();
const lockToken = useNenchoLockToken();

const sp = props.spouse ?? {};
const ch = sp.change ?? {};
const form = useForm({
    spouse_status: sp.spouse_status ?? 0, kind: sp.kind ?? '',
    name: sp.name ?? '', name_kana: sp.name_kana ?? '', birthday: sp.birthday ?? '',
    my_number: '', clear_my_number: false,
    address_same: sp.address_same ?? true, postal_code: sp.postal_code ?? '', prefecture: sp.prefecture ?? '', city: sp.city ?? '', street: sp.street ?? '',
    income_estimate: sp.income_estimate ?? '', retirement_income: sp.retirement_income ?? '',
    nonresident: sp.nonresident ?? false, elderly: sp.elderly ?? false,
    remittance_amount: sp.remittance_amount ?? '', remittance_unit: sp.remittance_unit ?? '', remittance_unit_other: sp.remittance_unit_other ?? '',
    disability_class: sp.disability_class ?? 0, disability_handbook_type: sp.disability_handbook_type ?? '', disability_issued_on: sp.disability_issued_on ?? '', disability_detail: sp.disability_detail ?? '',
    change: { month: ch.month ?? '', day: ch.day ?? '', reason: ch.reason ?? '', reason_other: ch.reason_other ?? '' },
});
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';

function save(go) {
    form.transform((d) => ({ ...d, go, lock_token: lockToken?.value })).put(nenchoRoute('steps.save', { submission: props.submission.id, step: 4 }), { preserveScroll: true });
}
defineExpose({ save, get processing() { return form.processing; } });
</script>

<template>
    <div class="space-y-6">
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-gray-700">配偶者はいますか <span class="text-red-600">*</span></legend>
            <div class="flex flex-wrap gap-4">
                <label v-for="o in options.spouseStatus" :key="o.value" class="flex items-center gap-2 text-sm text-gray-800">
                    <input v-model.number="form.spouse_status" :disabled="!editable" type="radio" :value="Number(o.value)" class="text-blue-600 focus:ring-blue-500" />{{ o.label }}
                </label>
            </div>
            <p v-if="form.errors.spouse_status" class="mt-1 text-xs text-red-600">{{ form.errors.spouse_status }}</p>
        </fieldset>

        <section v-if="form.spouse_status === 2" class="space-y-3">
            <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">本年中の異動</h4>
            <ChangeFields :model="form.change" :reasons="options.changeReasons.spouse" :errors="form.errors" prefix="change." :disabled="!editable" />
        </section>

        <template v-if="form.spouse_status === 1">
            <section class="space-y-4">
                <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">配偶者の情報</h4>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="配偶者の種別" required :error="form.errors.kind">
                        <select v-model.number="form.kind" :disabled="!editable" :class="input">
                            <option value="">選択してください</option>
                            <option v-for="o in options.spouseKinds" :key="o.value" :value="Number(o.value)">{{ o.label }}</option>
                        </select>
                    </Field>
                    <Field label="生年月日" required :error="form.errors.birthday" example="1977/09/05（19770905・昭和52年9月5日 でも可）"><input v-model="form.birthday" :disabled="!editable" type="text" inputmode="numeric" v-norm="'date'" :class="input" /></Field>
                    <Field label="氏名" hint="姓と名の間にスペースを入れてください（全角・半角どちらでも可。自動で全角にそろえます）" required :error="form.errors.name" example="例：山田　太郎"><input v-model="form.name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field label="フリガナ" hint="全角カナ（ひらがなで入力しても自動で直します）" required :error="form.errors.name_kana" example="例：ヤマダ　タロウ"><input v-model="form.name_kana" v-norm="'kana'" :disabled="!editable" type="text" maxlength="60" :class="input" /></Field>
                </div>
                <div class="grid gap-3 rounded border border-amber-200 bg-amber-50 p-3 sm:grid-cols-2">
                    <Field label="配偶者のマイナンバー（半角数字12桁）" :error="form.errors.my_number" :hint="spouse?.masked_my_number ? `登録済み：${spouse.masked_my_number}（変更する場合のみ入力）` : '試用中はダミーの番号を入力してください'">
                        <input v-model="form.my_number" v-norm="'digits'" :disabled="!editable" type="text" inputmode="numeric" maxlength="16" autocomplete="off" :class="input" />
                    </Field>
                    <label v-if="spouse?.masked_my_number && editable" class="flex items-end gap-2 pb-2 text-sm text-gray-700">
                        <input v-model="form.clear_my_number" type="checkbox" class="rounded text-red-600 focus:ring-red-500" />登録済みの番号を削除する
                    </label>
                </div>
                <fieldset><legend class="mb-2 text-sm font-medium text-gray-700">住所又は居所</legend>
                    <AddressFields :model="form" :errors="form.errors" :prefectures="prefectures" :disabled="!editable" />
                </fieldset>
            </section>

            <section class="space-y-4">
                <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">所得・その他</h4>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="本年中の所得の見積額（円）" required :error="form.errors.income_estimate" example="例：500,000"><input v-model="form.income_estimate" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                    <Field label="源泉徴収される退職手当等に係る退職所得（円）" :error="form.errors.retirement_income" hint="ない場合は空欄" example="例：4,500,000"><input v-model="form.retirement_income" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                </div>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.elderly" :disabled="!editable" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" />老人控除対象配偶者に該当する</label>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.nonresident" :disabled="!editable" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" />非居住者である</label>
                </div>
                <div v-if="form.nonresident" class="grid gap-3 sm:grid-cols-3">
                    <Field label="送金額（生計を一にする事実）" :error="form.errors.remittance_amount" example="例：4,500,000"><input v-model="form.remittance_amount" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                    <Field label="単位" :error="form.errors.remittance_unit">
                        <select v-model.number="form.remittance_unit" :disabled="!editable" :class="input">
                            <option value="">選択</option>
                            <option v-for="o in options.remittanceUnits" :key="o.value" :value="Number(o.value)">{{ o.label }}</option>
                        </select>
                    </Field>
                    <Field v-if="form.remittance_unit === 99" label="その他の単位" :error="form.errors.remittance_unit_other"><input v-model="form.remittance_unit_other" v-norm="'text'" :disabled="!editable" type="text" maxlength="15" :class="input" /></Field>
                </div>
                <DisabilityFields :model="form" :classes="options.disabilityClasses" :errors="form.errors" :disabled="!editable" />
            </section>

            <section class="space-y-3">
                <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">本年中の異動（ある場合のみ）</h4>
                <ChangeFields :model="form.change" :reasons="options.changeReasons.spouse" :errors="form.errors" prefix="change." :disabled="!editable" />
            </section>
        </template>
    </div>
</template>
