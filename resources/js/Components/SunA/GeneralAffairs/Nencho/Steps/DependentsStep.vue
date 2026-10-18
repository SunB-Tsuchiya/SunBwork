<script setup>
import { useNenchoLockToken, useNenchoRoute } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/useNenchoRoutes';
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import AddressFields from '../Fields/AddressFields.vue';
import ChangeFields from '../Fields/ChangeFields.vue';
import DisabilityFields from '../Fields/DisabilityFields.vue';
import Field from '../Fields/Field.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    submission: { type: Object, required: true },
    dependents: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
    prefectures: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
});

const nenchoRoute = useNenchoRoute();
const lockToken = useNenchoLockToken();

const MAX = 20;
const blank = () => ({
    id: null, masked_my_number: null, kind: '', name: '', name_kana: '', my_number: '', clear_my_number: false, relation: '', relation_other: '', birthday: '',
    elderly_class: 0, specific_class: 0, income_estimate: '', retirement_income: '', nonresident: false, nonresident_reason: '',
    address_same: true, postal_code: '', prefecture: '', city: '', street: '',
    disability_class: 0, disability_handbook_type: '', disability_issued_on: '', disability_detail: '',
    other_earner: { name: '', relation: '', relation_other: '', address_same: true, postal_code: '', prefecture: '', city: '', street: '' },
    remittance_amount: '', remittance_unit: '', remittance_unit_other: '',
    change: { month: '', day: '', reason: '', reason_other: '' },
});
const fromServer = (d) => ({
    ...blank(), ...Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v ?? blank()[k]])),
    my_number: '', clear_my_number: false,
    other_earner: { ...blank().other_earner, ...(d.other_earner ?? {}) },
    change: { ...blank().change, ...(d.change ?? {}) },
});
const form = useForm({ dependents: props.dependents.map(fromServer) });
const canAdd = computed(() => props.editable && form.dependents.length < MAX);
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';
const err = (i, k) => form.errors[`dependents.${i}.${k}`] ?? '';
const errs = (i) => Object.fromEntries(Object.entries(form.errors).filter(([k]) => k.startsWith(`dependents.${i}.`)).map(([k, v]) => [k.slice(`dependents.${i}.`.length), v]));

function add() { form.dependents.push(blank()); }
function remove(i) { if (confirm('この扶養親族を削除しますか？')) form.dependents.splice(i, 1); }
function save(go) {
    form.transform((d) => ({ ...d, go, lock_token: lockToken?.value })).put(nenchoRoute('steps.save', { submission: props.submission.id, step: 5 }), { preserveScroll: true });
}
defineExpose({ save, get processing() { return form.processing; } });
</script>

<template>
    <div class="space-y-5">
        <p class="rounded bg-blue-50 p-3 text-sm text-gray-700">扶養している親族（配偶者を除く）を、人数分入力してください（最大{{ MAX }}人）。該当する人がいなければ、そのまま次へ進めます。<strong>種別や区分（特定扶養親族など）は、ご自身で選んでください。</strong>不明な点は総務にご確認ください。</p>

        <div v-for="(d, i) in form.dependents" :key="d.id ?? `new-${i}`" class="space-y-4 rounded border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-800">{{ i + 1 }}人目 <span v-if="d.name" class="font-normal text-gray-600">（{{ d.name }}）</span></h4>
                <button v-if="editable" type="button" class="text-xs text-red-600 hover:underline" @click="remove(i)">この人を削除</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="種別" required :error="err(i, 'kind')">
                    <select v-model.number="d.kind" :disabled="!editable" :class="input">
                        <option value="">選択してください</option>
                        <option v-for="o in options.dependentKinds" :key="o.value" :value="Number(o.value)">{{ o.label }}</option>
                    </select>
                </Field>
                <Field label="続柄" required :error="err(i, 'relation')">
                    <select v-model.number="d.relation" :disabled="!editable" :class="input">
                        <option value="">選択してください</option>
                        <option v-for="o in options.dependentRelations" :key="o.value" :value="Number(o.value)">{{ o.label }}</option>
                    </select>
                </Field>
                <Field v-if="d.relation === 99" label="続柄（その他）" :error="err(i, 'relation_other')"><input v-model="d.relation_other" v-norm="'text'" :disabled="!editable" type="text" maxlength="10" :class="input" /></Field>
                <Field label="氏名" hint="姓と名の間にスペースを入れてください（全角・半角どちらでも可。自動で全角にそろえます）" required :error="err(i, 'name')" example="例：山田　太郎"><input v-model="d.name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                <Field label="フリガナ" hint="全角カナ（ひらがなで入力しても自動で直します）" required :error="err(i, 'name_kana')" example="例：ヤマダ　タロウ"><input v-model="d.name_kana" v-norm="'kana'" :disabled="!editable" type="text" maxlength="60" :class="input" /></Field>
                <Field label="生年月日" required :error="err(i, 'birthday')" example="1977/09/05（19770905・昭和52年9月5日 でも可）"><input v-model="d.birthday" :disabled="!editable" type="text" inputmode="numeric" v-norm="'date'" :class="input" /></Field>
                <Field hint="円単位。例：500,000（「50万円」でも可。自動で直します）" label="本年中の所得の見積額（円）" required :error="err(i, 'income_estimate')" example="例：0"><input v-model="d.income_estimate" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                <Field label="源泉徴収される退職手当等に係る退職所得（円）" :error="err(i, 'retirement_income')" hint="ない場合は空欄" example="例：4,500,000"><input v-model="d.retirement_income" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="老人扶養親族" :error="err(i, 'elderly_class')">
                    <select v-model.number="d.elderly_class" :disabled="!editable" :class="input"><option v-for="o in options.elderlyClasses" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                </Field>
                <Field label="特定扶養親族／特定親族" :error="err(i, 'specific_class')">
                    <select v-model.number="d.specific_class" :disabled="!editable" :class="input"><option v-for="o in options.specificClasses" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                </Field>
            </div>
            <div class="grid gap-3 rounded border border-amber-200 bg-amber-50 p-3 sm:grid-cols-2">
                <Field label="マイナンバー（半角数字12桁）" :error="err(i, 'my_number')" :hint="d.masked_my_number ? `登録済み：${d.masked_my_number}（変更する場合のみ入力）` : '試用中はダミーの番号を入力してください'">
                    <input v-model="d.my_number" v-norm="'digits'" :disabled="!editable" type="text" inputmode="numeric" maxlength="16" autocomplete="off" :class="input" />
                </Field>
                <label v-if="d.masked_my_number && editable" class="flex items-end gap-2 pb-2 text-sm text-gray-700"><input v-model="d.clear_my_number" type="checkbox" class="rounded text-red-600 focus:ring-red-500" />登録済みの番号を削除する</label>
            </div>
            <fieldset><legend class="mb-2 text-sm font-medium text-gray-700">住所又は居所</legend>
                <AddressFields :model="d" :errors="errs(i)" :prefectures="prefectures" :disabled="!editable" />
            </fieldset>
            <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="d.nonresident" :disabled="!editable" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" />非居住者である親族／控除対象外国外扶養親族</label>
            <div v-if="d.nonresident" class="grid gap-3 sm:grid-cols-4">
                <Field label="該当する項目" :error="err(i, 'nonresident_reason')">
                    <select v-model.number="d.nonresident_reason" :disabled="!editable" :class="input"><option value="">選択</option><option v-for="o in options.nonresidentReasons" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                </Field>
                <Field label="送金額" :error="err(i, 'remittance_amount')" example="例：4,500,000"><input v-model="d.remittance_amount" v-norm="'yen'" :disabled="!editable" type="text" inputmode="numeric" :class="input" /></Field>
                <Field label="単位" :error="err(i, 'remittance_unit')">
                    <select v-model.number="d.remittance_unit" :disabled="!editable" :class="input"><option value="">選択</option><option v-for="o in options.remittanceUnits" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                </Field>
                <Field v-if="d.remittance_unit === 99" label="その他の単位" :error="err(i, 'remittance_unit_other')"><input v-model="d.remittance_unit_other" v-norm="'text'" :disabled="!editable" type="text" maxlength="15" :class="input" /></Field>
            </div>
            <DisabilityFields :model="d" :classes="options.disabilityClasses" :errors="errs(i)" :disabled="!editable" />
            <div v-if="d.kind === 2" class="space-y-3 rounded border border-gray-200 bg-gray-50 p-3">
                <p class="text-sm font-medium text-gray-700">控除を受ける他の所得者</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Field label="氏名" :error="err(i, 'other_earner.name')" example="例：山田　太郎"><input v-model="d.other_earner.name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" /></Field>
                    <Field label="続柄" :error="err(i, 'other_earner.relation')">
                        <select v-model.number="d.other_earner.relation" :disabled="!editable" :class="input"><option value="">選択</option><option v-for="o in options.otherEarnerRelations" :key="o.value" :value="Number(o.value)">{{ o.label }}</option></select>
                    </Field>
                    <Field v-if="d.other_earner.relation === 99" label="続柄（その他）" :error="err(i, 'other_earner.relation_other')"><input v-model="d.other_earner.relation_other" v-norm="'text'" :disabled="!editable" type="text" maxlength="10" :class="input" /></Field>
                </div>
                <AddressFields :model="d.other_earner" :errors="errs(i)" prefix="other_earner." :prefectures="prefectures" :disabled="!editable" />
            </div>
            <div>
                <p class="mb-1 text-xs text-gray-500">本年中に異動（結婚・出産・就職など）があった場合のみ入力してください。</p>
                <ChangeFields :model="d.change" :reasons="options.changeReasons.dependent" :errors="errs(i)" prefix="change." :disabled="!editable" />
            </div>
        </div>

        <button v-if="canAdd" type="button" class="rounded border border-dashed border-blue-400 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50" @click="add">＋ 扶養親族を追加</button>
        <p v-if="form.errors.dependents" class="text-xs text-red-600">{{ form.errors.dependents }}</p>
    </div>
</template>
