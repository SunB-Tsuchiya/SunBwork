<script setup>
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import AppLayout from '@/layouts/AppLayout.vue';
import AddressFields from '@/Components/SunA/GeneralAffairs/Nencho/Fields/AddressFields.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import axios from 'axios';
import { Link, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    employee: { type: Object, required: true },
    profile: { type: Object, default: null },
    maskedMyNumber: { type: String, default: null },
    prefectures: { type: Array, default: () => [] },
    relations: { type: Object, default: () => ({}) },
    employmentLabels: { type: Object, default: () => ({}) },
    abilities: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: props.profile?.name ?? props.employee.name ?? '',
    name_kana: props.profile?.name_kana ?? '',
    birthday: props.profile?.birthday ?? '',
    postal_code: props.profile?.postal_code ?? '',
    prefecture: props.profile?.prefecture ?? '',
    city: props.profile?.city ?? '',
    street: props.profile?.street ?? '',
    nearest_station: props.profile?.nearest_station ?? '',
    household_head_name: props.profile?.household_head_name ?? '',
    household_head_relation: props.profile?.household_head_relation ?? '',
    household_head_relation_other: props.profile?.household_head_relation_other ?? '',
    employee_number: props.profile?.employee_number ?? '',
    my_number: '',
    clear_my_number: false,
});

const readonly = !props.abilities.editEmployees;
const revealed = ref(null);
const revealError = ref('');
let hideTimer = null;

// マイナンバーの全文表示（表示のたびにサーバー側で記録される）。30 秒で自動的に伏せ字へ戻す
async function reveal() {
    revealError.value = '';
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const { data } = await axios.post(route('clerk.general_affairs.employees.my_number.reveal', { user: props.employee.id }), {}, { headers: { 'X-CSRF-TOKEN': token } });
        revealed.value = data.my_number;
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => { revealed.value = null; }, 30000);
    } catch (e) {
        revealError.value = e.response?.data?.message ?? '表示できませんでした。';
    }
}
onBeforeUnmount(() => clearTimeout(hideTimer));

function submit() {
    form.put(route('clerk.general_affairs.employees.update', { user: props.employee.id }));
}
const input = 'w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500 disabled:bg-gray-100';
</script>

<template>
    <AppLayout title="社員情報">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.employees.index', { company: employee.company_id })" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ employee.name }}さんの社員情報</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="employees" />
        </template>

        <div class="space-y-6">
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">ユーザー情報（管理者の「ユーザー管理」で管理。ここでは変更できません）</h3>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">会社</dt><dd class="font-medium text-gray-800">{{ employee.company_name }}</dd></div>
                    <div><dt class="text-gray-500">部署</dt><dd class="font-medium text-gray-800">{{ employee.department ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">雇用区分</dt><dd class="font-medium text-gray-800">{{ employmentLabels[employee.employment_type] ?? employee.employment_type }}</dd></div>
                    <div><dt class="text-gray-500">メール</dt><dd class="font-medium text-gray-800">{{ employee.email }}</dd></div>
                </dl>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <form class="space-y-6" @submit.prevent="submit">
                    <p v-if="readonly" class="rounded bg-amber-50 p-3 text-sm text-amber-800">編集の権限がないため、表示のみです。</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="e-name" class="mb-1 block text-sm font-medium text-gray-700">氏名</label>
                            <input id="e-name" v-model="form.name" v-norm="'name'" :disabled="readonly" type="text" maxlength="30" :class="input" />
                            <p class="mt-1 text-xs text-gray-600">例：山田　太郎</p>
                            <p class="mt-1 text-xs text-gray-500">姓と名の間にスペースを入れてください（全角・半角どちらでも可。自動で全角にそろえます）</p>
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label for="e-kana" class="mb-1 block text-sm font-medium text-gray-700">フリガナ</label>
                            <input id="e-kana" v-model="form.name_kana" v-norm="'kana'" :disabled="readonly" type="text" maxlength="60" :class="input" />
                            <p class="mt-1 text-xs text-gray-600">例：ヤマダ　タロウ</p>
                            <p class="mt-1 text-xs text-gray-500">全角カナ（ひらがなで入力しても自動で直します）</p>
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.name_kana }}</p>
                        </div>
                        <div>
                            <label for="e-birth" class="mb-1 block text-sm font-medium text-gray-700">生年月日</label>
                            <input id="e-birth" v-model="form.birthday" v-norm="'date'" :disabled="readonly" type="text" inputmode="numeric" :class="input" />
                            <p class="mt-1 text-xs text-gray-600">例：1977/09/05（19770905・昭和52年9月5日 でも可）</p>
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.birthday }}</p>
                        </div>
                        <div>
                            <label for="e-no" class="mb-1 block text-sm font-medium text-gray-700">社員番号（半角英数字）</label>
                            <input id="e-no" v-model="form.employee_number" v-norm="'alnum'" :disabled="readonly" type="text" maxlength="20" :class="input" />
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.employee_number }}</p>
                        </div>
                    </div>

                    <fieldset class="space-y-4">
                        <legend class="mb-1 text-sm font-semibold text-gray-700">住所</legend>
                        <AddressFields :model="form" :errors="form.errors" :prefectures="prefectures" :allow-same="false" :disabled="readonly" />
                        <div class="sm:max-w-sm">
                            <label for="e-station" class="mb-1 block text-sm font-medium text-gray-700">最寄り駅</label>
                            <input id="e-station" v-model="form.nearest_station" v-norm="'text'" :disabled="readonly" type="text" maxlength="50" :class="input" />
                            <p class="mt-1 text-xs text-gray-600">例：新宿駅</p>
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.nearest_station }}</p>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-4">
                        <legend class="mb-1 text-sm font-semibold text-gray-700">世帯主</legend>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="e-head" class="mb-1 block text-sm font-medium text-gray-700">世帯主の氏名</label>
                                <input id="e-head" v-model="form.household_head_name" v-norm="'name'" :disabled="readonly" type="text" maxlength="30" :class="input" />
                                <p class="mt-1 text-xs text-gray-600">例：山田　太郎（姓と名の間にスペース。全角・半角どちらでも可）</p>
                            <p class="mt-1 text-xs text-gray-600">例：山田　太郎</p>
                                <p class="mt-1 text-xs text-red-600">{{ form.errors.household_head_name }}</p>
                            </div>
                            <div>
                                <label for="e-rel" class="mb-1 block text-sm font-medium text-gray-700">世帯主との続柄</label>
                                <select id="e-rel" v-model="form.household_head_relation" :disabled="readonly" :class="input">
                                    <option value="">選択してください</option>
                                    <option v-for="(label, code) in relations" :key="code" :value="Number(code)">{{ label }}</option>
                                </select>
                                <p class="mt-1 text-xs text-red-600">{{ form.errors.household_head_relation }}</p>
                            </div>
                            <div v-if="form.household_head_relation === 99">
                                <label for="e-relo" class="mb-1 block text-sm font-medium text-gray-700">続柄（その他）</label>
                                <input id="e-relo" v-model="form.household_head_relation_other" v-norm="'text'" :disabled="readonly" type="text" maxlength="10" :class="input" />
                                <p class="mt-1 text-xs text-red-600">{{ form.errors.household_head_relation_other }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3 rounded border border-amber-200 bg-amber-50 p-4">
                        <legend class="px-1 text-sm font-semibold text-amber-900">マイナンバー</legend>
                        <template v-if="abilities.viewMyNumber">
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="text-gray-600">登録済みの番号:</span>
                                <span class="font-mono text-base tracking-wider text-gray-800">{{ revealed ?? maskedMyNumber ?? '未登録' }}</span>
                                <button v-if="maskedMyNumber && !revealed" type="button" class="rounded border border-amber-400 bg-white px-3 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100" @click="reveal">表示する（記録されます）</button>
                                <button v-if="revealed" type="button" class="rounded border border-gray-300 bg-white px-3 py-1 text-xs text-gray-700 hover:bg-gray-100" @click="revealed = null">隠す</button>
                            </div>
                            <p v-if="revealError" class="text-xs text-red-600">{{ revealError }}</p>
                            <div v-if="!readonly" class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="e-mn" class="mb-1 block text-sm font-medium text-gray-700">登録・変更する番号（半角数字12桁。空欄は変更なし）</label>
                                    <input id="e-mn" v-model="form.my_number" v-norm="'digits'" type="text" inputmode="numeric" maxlength="16" autocomplete="off" :class="input" placeholder="試用中はダミーの番号を入力" />
                                    <p class="mt-1 text-xs text-red-600">{{ form.errors.my_number }}</p>
                                </div>
                                <label v-if="maskedMyNumber" class="flex items-end gap-2 pb-2 text-sm text-gray-700">
                                    <input v-model="form.clear_my_number" type="checkbox" class="rounded text-red-600 focus:ring-red-500" />
                                    登録済みの番号を削除する
                                </label>
                            </div>
                            <p class="text-xs text-amber-800">全文の表示・登録・変更・削除は、だれが行ったかが記録されます。試用中はダミーの番号だけを使ってください。</p>
                        </template>
                        <p v-else class="text-sm text-gray-600">マイナンバーを見る権限がありません。{{ maskedMyNumber ? '（登録済み）' : '（未登録）' }}</p>
                    </fieldset>

                    <div v-if="Object.keys(form.errors).length" role="alert" class="rounded border border-red-200 bg-red-50 p-3">
                        <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-700">{{ error }}</p>
                    </div>

                    <div v-if="!readonly" class="flex items-center gap-3">
                        <button :disabled="form.processing" class="rounded bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">{{ form.processing ? '保存中…' : '保存する' }}</button>
                        <Link :href="route('clerk.general_affairs.employees.index', { company: employee.company_id })" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">キャンセル</Link>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
