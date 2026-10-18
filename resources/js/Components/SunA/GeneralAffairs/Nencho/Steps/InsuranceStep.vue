<script setup>
import { useNenchoLockToken, useNenchoRoute } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/useNenchoRoutes';
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import Field from '../Fields/Field.vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    submission: { type: Object, required: true },
    insuranceItems: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: false },
});

const nenchoRoute = useNenchoRoute();
const lockToken = useNenchoLockToken();

const MAX = 20;
const s = props.submission;
const kinds = Object.entries(props.options.insuranceKinds ?? {});
const blank = () => ({ company_name: '', insurance_type: '', period: '', contractor_name: '', beneficiary_name: '', insured_name: '', payee_name: '', new_old: '', pension_start_on: '', quake_class: '', amount: '' });
const fromServer = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ''])) });
const u = s.life_under23_dependent ?? {};
const form = useForm({
    items: Object.fromEntries(kinds.map(([k]) => [k, (props.insuranceItems[k] ?? []).map(fromServer)])),
    small_biz_kyosai: s.small_biz_kyosai ?? '', dc_corporate: s.dc_corporate ?? '', dc_individual: s.dc_individual ?? '', disabled_kyosai: s.disabled_kyosai ?? '',
    under23: { name: u.name ?? '', name_kana: u.name_kana ?? '', birthday: u.birthday ?? '', relation: u.relation ?? '', income_estimate: u.income_estimate ?? '' },
});
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';
const err = (k, i, f) => form.errors[`items.${k}.${i}.${f}`] ?? '';
const add = (k) => { if (form.items[k].length < MAX) form.items[k].push(blank()); };
const remove = (k, i) => { if (confirm('この行を削除しますか？')) form.items[k].splice(i, 1); };
const isLife = (k) => k.startsWith('life_');

function save(go) {
    form.transform((d) => ({ ...d, go, lock_token: lockToken?.value })).put(nenchoRoute('steps.save', { submission: s.id, step: 6 }), { preserveScroll: true });
}
defineExpose({ save, get processing() { return form.processing; } });
</script>

<template>
    <div class="space-y-8">
        <p class="rounded bg-blue-50 p-3 text-sm text-gray-700">本年中に支払った保険料などを、控除証明書を見ながら入力してください。契約がなければ、そのまま次へ進めます（各最大{{ MAX }}件）。</p>

        <section v-for="[kind, label] in kinds" :key="kind" class="space-y-3">
            <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">{{ label }}</h4>
            <div v-for="(r, i) in form.items[kind]" :key="i" class="space-y-3 rounded border border-gray-200 p-3">
                <div class="flex items-center justify-between"><span class="text-xs font-medium text-gray-600">{{ i + 1 }}件目</span><button v-if="editable" type="button" class="text-xs text-red-600 hover:underline" @click="remove(kind, i)">削除</button></div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Field v-if="kind !== 'social'" label="保険会社名" :error="err(kind, i, 'company_name')"><input v-model="r.company_name" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field :label="kind === 'earthquake' ? '保険等の種類（目的）' : kind === 'social' ? '社会保険の種類' : '保険種類'" :error="err(kind, i, 'insurance_type')"><input v-model="r.insurance_type" v-norm="'text'" :disabled="!editable" type="text" maxlength="30" :class="input" :placeholder="kind === 'social' ? '例：国民年金' : ''" /></Field>
                    <Field v-if="kind !== 'social'" label="保険期間（年。終身は 0）" :error="err(kind, i, 'period')"><input v-model="r.period" v-norm="'digits'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                    <Field v-if="kind !== 'social'" label="契約者名" :error="err(kind, i, 'contractor_name')" example="例：山田　太郎"><input v-model="r.contractor_name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field v-if="isLife(kind)" label="受取人" :error="err(kind, i, 'beneficiary_name')" example="例：山田　太郎"><input v-model="r.beneficiary_name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field v-if="kind === 'earthquake'" label="保険の対象となった家屋等に居住する者等の氏名" :error="err(kind, i, 'insured_name')" example="例：山田　太郎"><input v-model="r.insured_name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field v-if="kind === 'social'" label="保険料支払先の名称" :error="err(kind, i, 'payee_name')"><input v-model="r.payee_name" v-norm="'text'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field v-if="kind === 'social'" label="保険料を負担することとなっている人の氏名" :error="err(kind, i, 'insured_name')" example="例：山田　太郎"><input v-model="r.insured_name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field v-if="kind === 'life_general' || kind === 'life_pension'" label="新・旧の区分" :error="err(kind, i, 'new_old')">
                        <select v-model.number="r.new_old" :disabled="!editable" :class="input"><option value="">選択</option><option v-for="o in options.newOld" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                    </Field>
                    <Field v-if="kind === 'life_pension'" label="支払開始日" :error="err(kind, i, 'pension_start_on')" example="1977/09/05（19770905・昭和52年9月5日 でも可）"><input v-model="r.pension_start_on" :disabled="!editable" type="text" inputmode="numeric" v-norm="'date'" :class="input" /></Field>
                    <Field v-if="kind === 'earthquake'" label="区分" :error="err(kind, i, 'quake_class')">
                        <select v-model.number="r.quake_class" :disabled="!editable" :class="input"><option value="">選択</option><option v-for="o in options.quakeClasses" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                    </Field>
                    <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" :label="kind === 'social' ? '支払った保険料の金額（円）' : '本年中に支払った保険料等の金額（円）'" :error="err(kind, i, 'amount')" example="例：60,000"><input v-model="r.amount" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                </div>
            </div>
            <button v-if="editable && form.items[kind].length < MAX" type="button" class="rounded border border-dashed border-blue-400 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50" @click="add(kind)">＋ {{ label }}を追加</button>
        </section>

        <section v-if="form.items.life_general.length" class="space-y-3 rounded border border-gray-200 bg-gray-50 p-4">
            <h4 class="text-sm font-semibold text-gray-800">23歳未満の扶養親族の情報（一般の生命保険料の新契約に関する特例）</h4>
            <p class="text-xs text-gray-500">23歳未満の扶養親族がいる場合のみ入力してください。</p>
            <div class="grid gap-3 sm:grid-cols-3">
                <Field label="氏名" :error="form.errors['under23.name']" example="例：山田　太郎"><input v-model="form.under23.name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                <Field label="フリガナ" :error="form.errors['under23.name_kana']" example="例：ヤマダ　タロウ"><input v-model="form.under23.name_kana" v-norm="'kana'" :disabled="!editable" type="text" maxlength="60" :class="input" /></Field>
                <Field label="生年月日" :error="form.errors['under23.birthday']" example="1977/09/05（19770905・昭和52年9月5日 でも可）"><input v-model="form.under23.birthday" :disabled="!editable" type="text" inputmode="numeric" v-norm="'date'" :class="input" /></Field>
                <Field label="続柄" :error="form.errors['under23.relation']">
                    <select v-model.number="form.under23.relation" :disabled="!editable" :class="input"><option value="">選択</option><option v-for="o in options.dependentRelations" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                </Field>
                <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="本年中の所得金額の見積額（円）" :error="form.errors['under23.income_estimate']" example="例：4,500,000"><input v-model="form.under23.income_estimate" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
            </div>
        </section>

        <section class="space-y-3">
            <h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">小規模企業共済等掛金控除</h4>
            <p class="text-xs text-gray-500">支払っている掛金がなければ空欄で構いません。</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="独立行政法人中小企業基盤整備機構の共済契約の掛金（円）" :error="form.errors.small_biz_kyosai" example="例：4,500,000"><input v-model="form.small_biz_kyosai" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="確定拠出年金法に規定する企業型年金加入者掛金（円）" :error="form.errors.dc_corporate" example="例：4,500,000"><input v-model="form.dc_corporate" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="確定拠出年金法に規定する個人型年金加入者掛金（円）" :error="form.errors.dc_individual" example="例：4,500,000"><input v-model="form.dc_individual" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="心身障害者扶養共済制度に関する契約の掛金（円）" :error="form.errors.disabled_kyosai" example="例：4,500,000"><input v-model="form.disabled_kyosai" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
            </div>
        </section>
    </div>
</template>
