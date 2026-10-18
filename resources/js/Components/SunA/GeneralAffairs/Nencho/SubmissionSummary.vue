<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// 年末調整の提出内容の表示（従業員の確認画面・総務の確認画面で共通）。stepHref があれば各工程への「修正する」リンクを出す
const props = defineProps({
    submission: { type: Object, required: true },
    spouse: { type: Object, default: null },
    dependents: { type: Array, default: () => [] },
    insuranceItems: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    stepHref: { type: Function, default: null },
});

const s = props.submission;
const label = (list, v) => list?.find((o) => String(o.value) === String(v))?.label ?? '—';
const yen = (v) => (v === null || v === undefined || v === '' ? '—' : `${Number(v).toLocaleString()} 円`);
const val = (v) => (v === null || v === undefined || v === '' ? '—' : v);
const address = (o) => (o.address_same ? '申告者と同じ' : [o.postal_code && `〒${o.postal_code}`, o.prefecture, o.city, o.street].filter(Boolean).join(' ') || '—');
const personAddress = [s.postal_code && `〒${s.postal_code}`, s.prefecture, s.city, s.street].filter(Boolean).join(' ') || '—';
const kinds = Object.entries(props.options.insuranceKinds ?? {});
const insuranceCount = computed(() => kinds.reduce((n, [k]) => n + (props.insuranceItems[k]?.length ?? 0), 0));
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-2">
            <div class="flex items-center justify-between"><h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">1. 本人情報</h4><Link v-if="stepHref" :href="stepHref(1)" class="text-xs text-blue-700 hover:underline">修正する</Link></div>
            <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">氏名</dt><dd>{{ val(s.name) }}（{{ val(s.name_kana) }}）</dd></div>
                <div><dt class="text-gray-500">生年月日</dt><dd>{{ val(s.birthday) }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-gray-500">住所</dt><dd>{{ personAddress }}</dd></div>
                <div><dt class="text-gray-500">世帯主</dt><dd>{{ val(s.household_head_name) }}（{{ label(options.householdRelations, s.household_head_relation) }}）</dd></div>
            </dl>
        </section>

        <section class="space-y-2">
            <div class="flex items-center justify-between"><h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">2. 本人の区分</h4><Link v-if="stepHref" :href="stepHref(2)" class="text-xs text-blue-700 hover:underline">修正する</Link></div>
            <p v-if="s.no_category" class="text-sm text-gray-700">いずれにも該当しない</p>
            <dl v-else class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">障害者</dt><dd>{{ label(options.disabilityClasses, s.disability_class) }}</dd></div>
                <div><dt class="text-gray-500">ひとり親・寡婦</dt><dd>{{ label(options.singleParentClasses, s.single_parent_class) }}</dd></div>
                <div><dt class="text-gray-500">勤労学生</dt><dd>{{ s.working_student ? `該当（${val(s.school_name)}）` : '非該当' }}</dd></div>
                <div><dt class="text-gray-500">住民税（ひとり親・寡婦）</dt><dd>{{ label(options.singleParentClasses, s.resident_tax_single_parent_class) }}</dd></div>
            </dl>
            <p class="text-sm text-gray-600">従たる給与の申告書の提出：{{ s.has_secondary_job_form ? 'あり' : 'なし' }}</p>
        </section>

        <section class="space-y-2">
            <div class="flex items-center justify-between"><h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">3. 所得の見積り</h4><Link v-if="stepHref" :href="stepHref(3)" class="text-xs text-blue-700 hover:underline">修正する</Link></div>
            <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">給与収入</dt><dd>{{ yen(s.salary_income) }}</dd></div>
                <div><dt class="text-gray-500">給与所得の見積額</dt><dd>{{ yen(s.salary_income_estimate) }}</dd></div>
                <div><dt class="text-gray-500">給与所得以外の所得の見積額</dt><dd>{{ yen(s.other_income_estimate) }}</dd></div>
                <div><dt class="text-gray-500">合計所得の見積額</dt><dd class="font-semibold">{{ yen(s.total_income_estimate) }}</dd></div>
            </dl>
        </section>

        <section class="space-y-2">
            <div class="flex items-center justify-between"><h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">4. 配偶者</h4><Link v-if="stepHref" :href="stepHref(4)" class="text-xs text-blue-700 hover:underline">修正する</Link></div>
            <p v-if="!spouse || spouse.spouse_status !== 1" class="text-sm text-gray-700">{{ label(options.spouseStatus, spouse?.spouse_status ?? 0) }}</p>
            <dl v-else class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">氏名</dt><dd>{{ val(spouse.name) }}（{{ val(spouse.name_kana) }}）</dd></div>
                <div><dt class="text-gray-500">生年月日</dt><dd>{{ val(spouse.birthday) }}</dd></div>
                <div><dt class="text-gray-500">種別</dt><dd>{{ label(options.spouseKinds, spouse.kind) }}</dd></div>
                <div><dt class="text-gray-500">マイナンバー</dt><dd>{{ spouse.masked_my_number ?? '未登録' }}</dd></div>
                <div><dt class="text-gray-500">住所</dt><dd>{{ address(spouse) }}</dd></div>
                <div><dt class="text-gray-500">所得の見積額</dt><dd>{{ yen(spouse.income_estimate) }}</dd></div>
            </dl>
        </section>

        <section class="space-y-2">
            <div class="flex items-center justify-between"><h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">5. 扶養親族（{{ dependents.length }}人）</h4><Link v-if="stepHref" :href="stepHref(5)" class="text-xs text-blue-700 hover:underline">修正する</Link></div>
            <ul v-if="dependents.length" class="divide-y divide-gray-100 text-sm">
                <li v-for="(d, i) in dependents" :key="d.id" class="py-2">
                    {{ i + 1 }}. {{ val(d.name) }}（{{ label(options.dependentRelations, d.relation) }}） / {{ label(options.dependentKinds, d.kind) }} / 所得 {{ yen(d.income_estimate) }} / マイナンバー {{ d.masked_my_number ?? '未登録' }}
                </li>
            </ul>
            <p v-else class="text-sm text-gray-700">いません</p>
        </section>

        <section class="space-y-2">
            <div class="flex items-center justify-between"><h4 class="border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-800">6. 保険料控除（{{ insuranceCount }}件）</h4><Link v-if="stepHref" :href="stepHref(6)" class="text-xs text-blue-700 hover:underline">修正する</Link></div>
            <template v-for="[k, kl] in kinds" :key="k">
                <div v-if="insuranceItems[k]?.length">
                    <p class="text-xs font-medium text-gray-600">{{ kl }}</p>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="(r, i) in insuranceItems[k]" :key="i" class="py-1.5">{{ val(r.company_name ?? r.payee_name) }} / {{ val(r.insurance_type) }} / {{ yen(r.amount) }}</li>
                    </ul>
                </div>
            </template>
            <p v-if="!insuranceCount" class="text-sm text-gray-700">契約・支払いはありません</p>
            <p class="text-sm text-gray-600">小規模企業共済等：{{ yen(s.small_biz_kyosai) }} / 企業型DC {{ yen(s.dc_corporate) }} / 個人型DC {{ yen(s.dc_individual) }} / 心身障害者扶養共済 {{ yen(s.disabled_kyosai) }}</p>
        </section>
    </div>
</template>
