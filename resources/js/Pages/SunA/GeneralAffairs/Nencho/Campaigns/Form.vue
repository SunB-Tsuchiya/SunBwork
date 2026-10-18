<script setup>
import { vNorm } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/normalize';
import AppLayout from '@/layouts/AppLayout.vue';
import AddressFields from '@/Components/SunA/GeneralAffairs/Nencho/Fields/AddressFields.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    campaign: { type: Object, default: null },
    companies: { type: Array, default: () => [] },
    lastInfo: { type: Object, default: () => ({}) },
    defaults: { type: Object, default: null },
    statuses: { type: Object, default: () => ({}) },
    prefectures: { type: Array, default: () => [] },
});

const editing = computed(() => Boolean(props.campaign?.id));
const src = props.campaign ?? props.defaults ?? {};
const form = useForm({
    company_id: src.company_id ?? '',
    fiscal_year: src.fiscal_year ?? '',
    title: src.title ?? '',
    opens_on: src.opens_on ?? '',
    due_on: src.due_on ?? '',
    message: src.message ?? '',
    status: src.status ?? 'open',
    tax_office_name: src.tax_office_name ?? '',
    municipality_name: src.municipality_name ?? '',
    payer_name: src.payer_name ?? '',
    payer_corporate_number: src.payer_corporate_number ?? '',
    payer_postal_code: src.payer_postal_code ?? '',
    payer_prefecture: src.payer_prefecture ?? '',
    payer_city: src.payer_city ?? '',
    payer_street: src.payer_street ?? '',
});

const companyName = computed(() => props.companies.find((c) => c.id === (props.campaign?.company_id ?? form.company_id))?.name ?? '');

// 会社を切り替えたら、その会社の前回の会社側情報（税務署・支払者）を引き継ぐ
function onCompanyChange() {
    const last = props.lastInfo[form.company_id];
    if (last) Object.assign(form, last);
}

function submit() {
    if (editing.value) {
        form.put(route('clerk.general_affairs.nencho.campaigns.update', { campaign: props.campaign.id }));
        return;
    }
    form.post(route('clerk.general_affairs.nencho.campaigns.store'));
}
const input = 'w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500';
</script>

<template>
    <AppLayout :title="editing ? '受付の編集' : '受付の作成'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.nencho.campaigns.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ editing ? '受付の編集' : '受付の作成' }}</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form class="space-y-8" @submit.prevent="submit">
                <section class="space-y-4">
                    <h3 class="border-l-4 border-purple-500 pl-2 text-base font-semibold text-gray-800">受付の内容</h3>
                    <div>
                        <label for="c-company" class="mb-1 block text-sm font-medium text-gray-700">会社 <span class="text-red-600">*</span></label>
                        <select v-if="!editing" id="c-company" v-model="form.company_id" :class="input" @change="onCompanyChange">
                            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-else class="text-sm font-medium text-gray-800">{{ companyName }}（会社は変更できません）</p>
                        <p class="mt-1 text-xs text-red-600">{{ form.errors.company_id }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="c-year" class="mb-1 block text-sm font-medium text-gray-700">年度（西暦） <span class="text-red-600">*</span></label>
                            <input id="c-year" v-model.number="form.fiscal_year" type="number" min="2020" max="2100" :class="input" />
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.fiscal_year }}</p>
                        </div>
                        <div>
                            <label for="c-status" class="mb-1 block text-sm font-medium text-gray-700">状態 <span class="text-red-600">*</span></label>
                            <select id="c-status" v-model="form.status" :class="input">
                                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                        </div>
                    </div>
                    <div>
                        <label for="c-title" class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-600">*</span></label>
                        <input id="c-title" v-model="form.title" type="text" maxlength="100" :class="input" />
                        <p class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="c-open" class="mb-1 block text-sm font-medium text-gray-700">受付開始日 <span class="text-red-600">*</span></label>
                            <input id="c-open" v-model="form.opens_on" type="date" :class="input" />
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.opens_on }}</p>
                        </div>
                        <div>
                            <label for="c-due" class="mb-1 block text-sm font-medium text-gray-700">提出締切日 <span class="text-red-600">*</span></label>
                            <input id="c-due" v-model="form.due_on" type="date" :min="form.opens_on" :class="input" />
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.due_on }}</p>
                        </div>
                    </div>
                    <div>
                        <label for="c-message" class="mb-1 block text-sm font-medium text-gray-700">従業員への案内文</label>
                        <textarea id="c-message" v-model="form.message" rows="5" maxlength="2000" :class="input" placeholder="例：令和8年分の年末調整の控除申告を入力してください。ご不明な点は総務までご連絡ください。"></textarea>
                        <p class="mt-1 text-xs text-red-600">{{ form.errors.message }}</p>
                    </div>
                </section>

                <section class="space-y-4">
                    <h3 class="border-l-4 border-purple-500 pl-2 text-base font-semibold text-gray-800">会社側の情報（申告書に記載。従業員には入力させません）</h3>
                    <p class="text-xs text-gray-500">あとから入力・変更できます。前回の受付の内容を引き継いでいます。</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="c-tax" class="mb-1 block text-sm font-medium text-gray-700">所轄税務署</label>
                            <input id="c-tax" v-model="form.tax_office_name" v-norm="'text'" type="text" maxlength="10" :class="input" />
                            <p class="mt-1 text-xs text-gray-600">例：中央</p>
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.tax_office_name }}</p>
                        </div>
                        <div>
                            <label for="c-muni" class="mb-1 block text-sm font-medium text-gray-700">所轄市区町村</label>
                            <input id="c-muni" v-model="form.municipality_name" v-norm="'text'" type="text" maxlength="35" :class="input" />
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.municipality_name }}</p>
                        </div>
                        <div>
                            <label for="c-payer" class="mb-1 block text-sm font-medium text-gray-700">給与の支払者の名称</label>
                            <input id="c-payer" v-model="form.payer_name" v-norm="'text'" type="text" maxlength="30" :class="input" />
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.payer_name }}</p>
                        </div>
                        <div>
                            <label for="c-corp" class="mb-1 block text-sm font-medium text-gray-700">法人番号（13桁）</label>
                            <input id="c-corp" v-model="form.payer_corporate_number" v-norm="'digits'" type="text" inputmode="numeric" maxlength="20" :class="input" />
                            <p class="mt-1 text-xs text-red-600">{{ form.errors.payer_corporate_number }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <AddressFields :model="form" :keys="{ postal: 'payer_postal_code', prefecture: 'payer_prefecture', city: 'payer_city', street: 'payer_street' }" :errors="form.errors" :prefectures="prefectures" :allow-same="false" />
                        </div>
                    </div>
                </section>

                <div v-if="Object.keys(form.errors).length" role="alert" class="rounded border border-red-200 bg-red-50 p-3">
                    <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-700">{{ error }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <button :disabled="form.processing" class="rounded bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                        {{ form.processing ? '保存中…' : editing ? '保存する' : '作成する' }}
                    </button>
                    <Link :href="route('clerk.general_affairs.nencho.campaigns.index')" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">キャンセル</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
