<script setup>
import { useNenchoLockToken, useNenchoRoute } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/useNenchoRoutes';
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import AddressFields from '../Fields/AddressFields.vue';
import Field from '../Fields/Field.vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    submission: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    prefectures: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
    myNumberRegistered: { type: Boolean, default: false },
});

const nenchoRoute = useNenchoRoute();
const lockToken = useNenchoLockToken();

const s = props.submission;
const form = useForm({
    name: s.name ?? '', name_kana: s.name_kana ?? '', birthday: s.birthday ?? '',
    postal_code: s.postal_code ?? '', prefecture: s.prefecture ?? '', city: s.city ?? '', street: s.street ?? '',
    household_head_name: s.household_head_name ?? '', household_head_relation: s.household_head_relation ?? '', household_head_relation_other: s.household_head_relation_other ?? '',
});
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';

function save(go) {
    form.transform((d) => ({ ...d, go, lock_token: lockToken?.value })).put(nenchoRoute('steps.save', { submission: s.id, step: 1 }), { preserveScroll: true });
}
defineExpose({ save, get processing() { return form.processing; } });
</script>

<template>
    <div class="space-y-5">
        <p class="rounded bg-blue-50 p-3 text-sm text-gray-700">「社員情報」に登録されている内容を表示しています。変わっている点があれば、直してください（総務が確認して社員情報に反映します）。</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <Field label="氏名" hint="姓と名の間にスペースを入れてください（全角・半角どちらでも可。自動で全角にそろえます）" required :error="form.errors.name" example="例：山田　太郎">
                <input v-model="form.name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" />
            </Field>
            <Field label="フリガナ" hint="全角カナ（ひらがなで入力しても自動で直します）" required :error="form.errors.name_kana" example="例：ヤマダ　タロウ">
                <input v-model="form.name_kana" v-norm="'kana'" :disabled="!editable" type="text" maxlength="60" :class="input" />
            </Field>
            <Field label="生年月日" required :error="form.errors.birthday" example="1977/09/05（19770905・昭和52年9月5日 でも可）">
                <input v-model="form.birthday" :disabled="!editable" type="text" inputmode="numeric" v-norm="'date'" :class="input" />
            </Field>
        </div>
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-gray-700">住所または居所 <span class="text-red-600">*</span></legend>
            <AddressFields :model="form" :errors="form.errors" :prefectures="prefectures" :allow-same="false" :disabled="!editable" />
        </fieldset>
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-gray-700">世帯主 <span class="text-red-600">*</span></legend>
            <div class="grid gap-4 sm:grid-cols-3">
                <Field label="世帯主の氏名" hint="姓と名の間にスペースを入れてください（全角・半角どちらでも可。自動で全角にそろえます）" :error="form.errors.household_head_name" example="例：山田　太郎">
                    <input v-model="form.household_head_name" v-norm="'name'" :disabled="!editable" type="text" maxlength="30" :class="input" />
                </Field>
                <Field label="世帯主との続柄" :error="form.errors.household_head_relation">
                    <select v-model.number="form.household_head_relation" :disabled="!editable" :class="input">
                        <option value="">選択してください</option>
                        <option v-for="r in options.householdRelations" :key="r.value" :value="Number(r.value)">{{ r.label }}</option>
                    </select>
                </Field>
                <Field v-if="form.household_head_relation === 99" label="続柄（その他）" :error="form.errors.household_head_relation_other">
                    <input v-model="form.household_head_relation_other" v-norm="'text'" :disabled="!editable" type="text" maxlength="10" :class="input" />
                </Field>
            </div>
        </fieldset>
        <div class="rounded border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
            <p class="font-medium">あなたのマイナンバー</p>
            <p v-if="myNumberRegistered" class="mt-1">社員情報に登録済みです。提出するときに、自動で引き継がれます（ここで入力する必要はありません）。</p>
            <p v-else class="mt-1 text-amber-800">社員情報にまだ登録されていません。提出後に、総務へご連絡ください。</p>
        </div>
    </div>
</template>
