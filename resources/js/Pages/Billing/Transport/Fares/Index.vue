<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { normalizeText, parseYen, formatYen } from '@/Components/Billing/Transport/transportNormalize';

const props = defineProps({
    routeBase:    { type: String, required: true },
    counts:       { type: Object, required: true },
    status:       { type: String, default: 'verified' },
    q:            { type: String, default: '' },
    fares:        { type: Array, default: () => [] },
});

const errors = ref({});
const rt = (name, params) => route(props.routeBase + name, params);
const yen = (n) => (n === null || n === undefined ? '―' : `${Number(n).toLocaleString()}円`);
const field = 'w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-purple-400 focus:outline-none';
const cell = 'rounded border border-gray-300 px-2 py-1 text-sm text-right focus:border-purple-400 focus:outline-none';

// ─── 登録（経路と運賃だけ。同じ経路があれば更新） ─────────────────
const form = ref({ station_a: '', station_b: '', fare_ic: '', fare_ticket: '' });
const saving = ref(false);
function submitForm() {
    errors.value = {};
    saving.value = true;
    router.post(rt('fares.store'), {
        station_a: form.value.station_a, station_b: form.value.station_b,
        fare_ic: form.value.fare_ic === '' ? null : form.value.fare_ic,
        fare_ticket: form.value.fare_ticket === '' ? null : form.value.fare_ticket,
    }, {
        preserveScroll: true,
        onSuccess: () => { form.value = { station_a: '', station_b: '', fare_ic: '', fare_ticket: '' }; },
        onError: (e) => { errors.value = e; },
        onFinish: () => { saving.value = false; },
    });
}

// ─── 一覧 ─────────────────────────────────────────
const local = ref([]);
watch(() => props.fares, (v) => {
    local.value = v.map((f) => {
        // 未登録の候補は、申請された金額（参考）を、その種別（IC / 切符）の欄に入れておく。そのまま「登録」できる
        const ic = f.fare_ic ?? (f.reported_fare_type !== 'ticket' ? f.reported_fare : null);
        const ticket = f.fare_ticket ?? (f.reported_fare_type === 'ticket' ? f.reported_fare : null);
        return { ...f, ic: formatYen(ic), ticket: formatYen(ticket), saving: false };
    });
}, { immediate: true, deep: true });

function onYen(f, k) {
    const n = parseYen(f[k]);
    if (n !== null) f[k] = formatYen(n);
}
function saveRow(f) {
    errors.value = {};
    f.saving = true;
    router.put(rt('fares.update', { fare: f.id }), {
        station_a: f.station_a, station_b: f.station_b,
        fare_ic: f.ic === '' ? null : parseYen(f.ic), fare_ticket: f.ticket === '' ? null : parseYen(f.ticket),
    }, {
        preserveScroll: true, preserveState: true,
        onError: (e) => { errors.value = e; },
        onFinish: () => { f.saving = false; },
    });
}
function removeRow(f) {
    if (!confirm(`「${f.station_a} － ${f.station_b}」を削除します。よろしいですか？`)) return;
    router.delete(rt('fares.destroy', { fare: f.id }), { preserveScroll: true });
}

const search = ref(props.q);
function go(params) {
    router.get(rt('fares.index'), { status: props.status, q: search.value, ...params }, { preserveScroll: true, preserveState: true });
}
const isCandidates = computed(() => props.status === 'unverified');
</script>

<template>
    <AppLayout title="運賃マスタ">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.applications.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">← 申請に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">運賃マスタ</h2>
            </div>
        </template>

        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="space-y-6">
            <div v-if="Object.keys(errors).length" class="rounded bg-red-50 p-3 text-sm text-red-600">
                <ul class="list-inside list-disc"><li v-for="(m, k) in errors" :key="k">{{ m }}</li></ul>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <p class="mb-4 text-sm text-gray-600">
                    よく使う経路と運賃を登録しておくと、申請の詳細画面で、運賃を自動で検算します。申請された経路は、自動で「未登録の候補」に溜まります。
                </p>

                <!-- 登録 -->
                <div id="fare-form" class="rounded border border-gray-200 p-4">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">登録する</h3>
                    <div class="flex flex-wrap items-start gap-3">
                        <div><label class="mb-1 block text-xs text-gray-600">出発駅</label><input type="text" v-model="form.station_a" @blur="form.station_a = normalizeText(form.station_a)" :class="field + ' w-36'" /><p class="mt-0.5 text-xs text-gray-400">例：　田端</p></div>
                        <div><label class="mb-1 block text-xs text-gray-600">到着駅</label><input type="text" v-model="form.station_b" @blur="form.station_b = normalizeText(form.station_b)" :class="field + ' w-36'" /><p class="mt-0.5 text-xs text-gray-400">例：　恵比寿</p></div>
                        <div><label class="mb-1 block text-xs text-gray-600">IC の運賃（片道）</label><input type="text" inputmode="numeric" v-model="form.fare_ic" @blur="form.fare_ic = formatYen(parseYen(form.fare_ic) ?? 0)" :class="field + ' w-28 text-right'" /><p class="mt-0.5 text-xs text-gray-400">例：　194</p></div>
                        <div><label class="mb-1 block text-xs text-gray-600">切符の運賃（片道）</label><input type="text" inputmode="numeric" v-model="form.fare_ticket" @blur="form.fare_ticket = formatYen(parseYen(form.fare_ticket) ?? 0)" :class="field + ' w-28 text-right'" /><p class="mt-0.5 text-xs text-gray-400">同じなら空でも可</p></div>
                        <div class="pt-5"><button type="button" @click="submitForm" :disabled="saving" class="rounded bg-purple-600 px-6 py-1.5 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">登録</button></div>
                    </div>
                </div>
            </div>

            <!-- 一覧 -->
            <div class="rounded bg-white p-6 shadow">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <button type="button" @click="go({ status: 'verified' })" class="rounded border px-4 py-1.5 text-sm" :class="!isCandidates ? 'border-purple-400 bg-purple-50 font-medium' : 'border-gray-200 hover:bg-gray-50'">登録済み {{ counts.verified }}</button>
                    <button type="button" @click="go({ status: 'unverified' })" class="rounded border px-4 py-1.5 text-sm" :class="isCandidates ? 'border-amber-400 bg-amber-50 font-medium' : 'border-gray-200 hover:bg-gray-50'">
                        未登録の候補 <span :class="counts.unverified ? 'rounded-full bg-amber-500 px-1.5 text-xs text-white' : ''">{{ counts.unverified }}</span>
                    </button>
                    <div class="ml-auto flex items-center gap-2">
                        <input type="text" v-model="search" @keydown.enter.prevent="go({ q: search })" :class="field + ' w-44'" />
                        <button type="button" @click="go({ q: search })" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">駅名で探す</button>
                    </div>
                </div>
                <p v-if="isCandidates" class="mb-3 text-xs text-gray-500">申請で使われたが、まだ登録していない経路です（使われた回数の多い順）。申請された金額が入っています。確認して「登録」を押すと、次から自動で検算されます。</p>

                <p v-if="!local.length" class="py-10 text-center text-sm text-gray-400">{{ isCandidates ? '未登録の候補はありません。' : '登録はまだありません。' }}</p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-300 bg-gray-50 text-xs text-gray-600">
                                <th class="px-2 py-2 text-left">経路</th>
                                <th class="px-2 py-2 text-left">IC（片道）</th>
                                <th class="px-2 py-2 text-left">切符（片道）</th>
                                <th v-if="isCandidates" class="px-2 py-2 text-left">使われた回数</th>
                                <th v-else class="px-2 py-2 text-left">登録日</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in local" :key="f.id" class="border-b border-gray-100">
                                <td class="px-2 py-1.5 text-gray-800">{{ f.station_a }} － {{ f.station_b }}</td>
                                <td class="px-2 py-1.5"><input type="text" inputmode="numeric" v-model="f.ic" @blur="onYen(f, 'ic')" :class="cell + ' w-24'" /> 円</td>
                                <td class="px-2 py-1.5"><input type="text" inputmode="numeric" v-model="f.ticket" @blur="onYen(f, 'ticket')" :class="cell + ' w-24'" /> 円</td>
                                <td v-if="isCandidates" class="px-2 py-1.5 text-xs text-gray-600">{{ f.use_count }} 回<span v-if="f.last_used_at">（最後 {{ f.last_used_at }}）</span></td>
                                <td v-else class="px-2 py-1.5 text-xs text-gray-600">
                                    {{ f.checked_at }}<span v-if="f.recheck" class="ml-1 rounded bg-amber-200 px-1.5 py-0.5 text-amber-900">1年以上前</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-right">
                                    <button type="button" :disabled="f.saving" @click="saveRow(f)"
                                        class="rounded bg-purple-600 px-3 py-1 text-xs font-medium text-white hover:bg-purple-700 disabled:opacity-50">{{ isCandidates ? '登録' : '保存' }}</button>
                                    <button type="button" @click="removeRow(f)" class="ml-1 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50">削除</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
