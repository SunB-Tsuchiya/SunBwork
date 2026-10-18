<script setup>
import Field from './Field.vue';
import { vNorm } from './normalize';
import axios from 'axios';
import { ref } from 'vue';
import { route } from 'ziggy-js';

// 住所（申告者と同じ／郵便番号・都道府県・市区町村・丁番地以降）。郵便番号から住所を検索して自動入力する。
// keys: 対象オブジェクト内のキー名（受付の支払者住所のように、キー名が違う場合に指定）
const props = defineProps({
    model: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    prefix: { type: String, default: '' },
    prefectures: { type: Array, default: () => [] },
    allowSame: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    keys: { type: Object, default: () => ({ postal: 'postal_code', prefecture: 'prefecture', city: 'city', street: 'street' }) },
});
const input = 'w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100';
const err = (k) => props.errors[`${props.prefix}${props.keys[k] ?? k}`] ?? '';

const searching = ref(false);
const message = ref('');
const messageIsError = ref(false);
let autoTown = '';   // 郵便番号から自動入力した町域（手入力した番地は上書きしない）

async function lookup() {
    message.value = '';
    const zip = String(props.model[props.keys.postal] ?? '').normalize('NFKC').replace(/\D/g, '');
    if (zip.length !== 7) {
        messageIsError.value = true;
        message.value = '郵便番号を7桁で入力してください。';
        return;
    }
    searching.value = true;
    try {
        const { data } = await axios.get(route('suna.postal_lookup'), { params: { zip } });
        props.model[props.keys.postal] = zip;
        props.model[props.keys.prefecture] = data.prefecture;
        props.model[props.keys.city] = data.city;
        const street = props.model[props.keys.street] ?? '';
        // 町域の自動入力: 番地が空、または前回の自動入力のままなら町域に置き換える（手入力した番地は消さない）
        if (!street || (autoTown && street.startsWith(autoTown))) {
            props.model[props.keys.street] = data.town + street.slice(autoTown.length);
        }
        autoTown = data.town;
        messageIsError.value = false;
        message.value = '住所を自動入力しました。続きの番地・建物名・部屋番号を入力してください。';
    } catch (e) {
        messageIsError.value = true;
        message.value = e.response?.data?.message ?? '住所を検索できませんでした。手入力してください。';
    } finally {
        searching.value = false;
    }
}
// 7 桁そろった郵便番号を入力して欄を離れたとき、住所が空なら自動で検索する
function onZipBlur() {
    const zip = String(props.model[props.keys.postal] ?? '').normalize('NFKC').replace(/\D/g, '');
    if (zip.length === 7 && !props.model[props.keys.prefecture] && !props.model[props.keys.city]) lookup();
}
</script>

<template>
    <div class="space-y-3">
        <label v-if="allowSame" class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="model.address_same" :disabled="disabled" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" />
            住所は申告者（あなた）と同じ
        </label>
        <div v-if="!allowSame || !model.address_same" class="space-y-3">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-48">
                    <Field label="郵便番号（7桁）" :error="err('postal')" hint="ハイフンなしで可。全角でも自動で直します" example="例：1000001">
                        <input v-model="model[keys.postal]" v-norm="'zip'" :disabled="disabled" type="text" inputmode="numeric" maxlength="10" :class="input" @blur="onZipBlur" />
                    </Field>
                </div>
                <button type="button" :disabled="disabled || searching" class="mb-[1.35rem] rounded border border-blue-300 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100 disabled:opacity-50" @click="lookup">
                    {{ searching ? '検索中…' : '郵便番号から住所を入力' }}
                </button>
            </div>
            <p v-if="message" class="text-xs" :class="messageIsError ? 'text-red-600' : 'text-green-700'">{{ message }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <Field label="都道府県" :error="err('prefecture')">
                    <select v-model="model[keys.prefecture]" :disabled="disabled" :class="input">
                        <option value="">選択してください</option>
                        <option v-for="p in prefectures" :key="p" :value="p">{{ p }}</option>
                    </select>
                </Field>
                <Field label="市区町村" :error="err('city')" hint="例：千代田区（郡・町村名まで入力）">
                    <input v-model="model[keys.city]" v-norm="'text'" :disabled="disabled" type="text" maxlength="35" :class="input" />
                </Field>
            </div>
            <Field label="町域・丁目・番地・号・建物名・部屋番号" :error="err('street')" hint="例：千代田１－１－１　○○ビル３０１　／　「３丁目７番７号」「3-7-7」どちらでも可（数字とハイフンは自動で全角にそろえます）" example="例：千代田１－１－１　○○ビル３０１">
                <input v-model="model[keys.street]" v-norm="'text'" :disabled="disabled" type="text" maxlength="100" :class="input" />
            </Field>
        </div>
    </div>
</template>
