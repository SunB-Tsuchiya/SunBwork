<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import CategoryStep from '@/Components/SunA/GeneralAffairs/Nencho/Steps/CategoryStep.vue';
import DependentsStep from '@/Components/SunA/GeneralAffairs/Nencho/Steps/DependentsStep.vue';
import IncomeStep from '@/Components/SunA/GeneralAffairs/Nencho/Steps/IncomeStep.vue';
import InsuranceStep from '@/Components/SunA/GeneralAffairs/Nencho/Steps/InsuranceStep.vue';
import PersonStep from '@/Components/SunA/GeneralAffairs/Nencho/Steps/PersonStep.vue';
import ReviewStep from '@/Components/SunA/GeneralAffairs/Nencho/Steps/ReviewStep.vue';
import SpouseStep from '@/Components/SunA/GeneralAffairs/Nencho/Steps/SpouseStep.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    campaign: { type: Object, required: true },
    submission: { type: Object, required: true },
    spouse: { type: Object, default: null },
    dependents: { type: Array, default: () => [] },
    insuranceItems: { type: Object, default: () => ({}) },
    step: { type: Number, required: true },
    steps: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
    canWithdraw: { type: Boolean, default: false },
    canCarryOver: { type: Boolean, default: false },
    canReturn: { type: Boolean, default: false },
    proxy: { type: Object, default: null },
    lock: { type: Object, default: null },
    routePrefix: { type: String, default: 'user.general_affairs.nencho' },
    reviewed: { type: Boolean, default: false },
    stepStatus: { type: Object, default: () => ({}) },
    problemSteps: { type: Array, default: () => [] },
    missing: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    myNumberRegistered: { type: Boolean, default: false },
    statuses: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    prefectures: { type: Array, default: () => [] },
});

// 本人入力と総務の代理入力で、同じフォームを使う（ルート名の接頭辞を切り替える）
provide('nenchoRoutePrefix', props.routePrefix);
const nr = (name, params = {}) => route(`${props.routePrefix}.${name}`, params);
const backHref = computed(() => (props.proxy ? route('clerk.general_affairs.nencho.campaigns.show', { campaign: props.proxy.campaign_id }) : route('user.general_affairs.nencho.index')));
const stepRef = ref(null);

// ---- 同時編集の防止（編集ロック）----
// 画面を開いている間は 1 分ごとにロックを延長し、閉じたら解除する。ほかの人・別の画面が編集を始めたら、保存できなくなる
const lockToken = computed(() => props.lock?.token ?? null);
provide('nenchoLockToken', lockToken);
const lockLost = ref(false);
const lostHolder = ref(null);
const canEdit = computed(() => props.editable && !lockLost.value);
let beatTimer = null;

async function beat() {
    if (!lockToken.value) return;
    try {
        const { data } = await axios.post(nr('heartbeat', { submission: props.submission.id }), { lock_token: lockToken.value });
        lockLost.value = !data.ok;
        lostHolder.value = data.holder ?? null;
    } catch {
        /* 通信できないときは何もしない（次の保存で確認される） */
    }
}
function releaseLock() {
    if (!lockToken.value) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    // ページを離れるときでも送れるよう keepalive を使う
    fetch(nr('release', { submission: props.submission.id }), {
        method: 'POST', keepalive: true, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ lock_token: lockToken.value }),
    });
}
onMounted(() => {
    beatTimer = setInterval(beat, 60000);
    window.addEventListener('pagehide', releaseLock);
});
onBeforeUnmount(() => {
    clearInterval(beatTimer);
    window.removeEventListener('pagehide', releaseLock);
    releaseLock();
});
const holderLabel = computed(() => {
    const h = props.lock?.holder ?? lostHolder.value;
    return h ? `${h.name}さん（${h.mode_label}）` : '他の方';
});
function takeOver() {
    if (!confirm(`${holderLabel.value}の編集を引き継ぎます。相手の画面では、保存できなくなります。よろしいですか？`)) return;
    router.post(nr('takeover', { submission: props.submission.id }));
}
const components = { 1: PersonStep, 2: CategoryStep, 3: IncomeStep, 4: SpouseStep, 5: DependentsStep, 6: InsuranceStep, 7: ReviewStep };
const current = computed(() => components[props.step]);
const processing = computed(() => stepRef.value?.processing ?? false);
const carryScope = { 2: 'category', 4: 'spouse', 5: 'dependents', 6: 'insurance' };
const showCarry = computed(() => canEdit.value && props.canCarryOver && carryScope[props.step]);

const stepHref = (no) => nr('show', { submission: props.submission.id, step: no });
const save = (go) => stepRef.value?.save(go);

function carryOver() {
    if (!confirm('前年の内容を読み込みます。このステップに入力済みの内容は置き換わります。よろしいですか？')) return;
    router.post(nr('carry_over', { submission: props.submission.id }), { scope: carryScope[props.step], lock_token: lockToken.value }, { preserveScroll: true });
}
// ステップの状態: 現在 / 要確認（不備あり）/ 完了 / 未着手
const returnSteps = computed(() => (props.submission.status === 'returned' ? props.submission.return_steps ?? [] : []));
const statusOf = (no) => {
    if (no === props.step) return 'current';
    if (returnSteps.value.includes(no)) return 'warn';
    const st = props.stepStatus[no];
    if (!st) return props.submission.status === 'submitted' ? 'done' : 'pending';
    if (st.visited && st.missing > 0) return 'warn';
    if (st.visited) return 'done';
    return 'pending';
};
const stepClass = {
    current: 'bg-blue-600 text-white',
    warn: 'border border-red-300 bg-red-50 text-red-700 hover:bg-red-100',
    done: 'border border-green-300 bg-green-50 text-green-700 hover:bg-green-100',
    pending: 'border border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100',
};
const stepIcon = (no) => ({ current: '●', warn: '⚠', done: '✓', pending: '○' }[statusOf(no)]);
const returnedAt = computed(() => (props.submission.returned_at ? String(props.submission.returned_at).replace('T', ' ').slice(0, 16) : ''));
const progress = computed(() => Math.round((props.step / props.steps.length) * 100));
const otherProblems = computed(() => props.problemSteps.filter((n) => n !== props.step));
const nextLabel = computed(() => {
    if (!props.reviewed) return '保存して次へ →';
    return otherProblems.value.length ? '保存して次の不備へ →' : '保存して確認画面へ →';
});
const badge = { draft: 'bg-amber-100 text-amber-800', submitted: 'bg-green-100 text-green-700', returned: 'bg-red-100 text-red-700' };
</script>

<template>
    <AppLayout :title="campaign.title">
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="backHref" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    {{ proxy ? '← 回収状況に戻る' : '← 年末調整に戻る' }}
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ campaign.title }}</h2>
                <span class="rounded-full px-3 py-1 text-xs font-medium" :class="badge[submission.status]">{{ statuses[submission.status] ?? submission.status }}</span>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs v-if="proxy" active="applications" />
            <UserNavigationTabs v-else active="nencho" />
        </template>

        <div class="space-y-4">
            <!-- 総務の代理入力 -->
            <div v-if="proxy" class="rounded border border-purple-300 bg-purple-50 p-3 text-sm text-purple-900">
                <span class="font-semibold">代理入力中：</span>{{ proxy.user_name }}さんの申告を、総務が入力しています。入力者は「総務の代理」として記録されます。提出前に、本人確認の記録（任意）を残せます。
            </div>
            <div v-if="campaign.message && !proxy" class="whitespace-pre-wrap rounded border border-blue-200 bg-blue-50 p-3 text-sm text-gray-700">{{ campaign.message }}</div>
            <!-- 総務から差し戻された場合: コメントと、直してほしい工程 -->
            <div v-if="submission.status === 'returned'" class="space-y-2 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">総務から差し戻されました（{{ submission.returned_by_name }}　{{ returnedAt }}）</p>
                <p v-if="submission.return_comment" class="whitespace-pre-wrap">{{ submission.return_comment }}</p>
                <p v-if="returnSteps.length" class="text-xs">
                    直してほしい工程：
                    <Link v-for="n in returnSteps" :key="n" :href="stepHref(n)" class="mr-2 font-medium underline hover:no-underline">{{ n }}. {{ steps.find((x) => x.no === n)?.label }}</Link>
                </p>
                <p class="text-xs text-red-700">内容を修正して、確認画面からもう一度提出してください（締切後でも修正できます）。</p>
            </div>
            <div v-if="lock?.blocked" class="space-y-2 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">{{ holderLabel }}が編集中です（{{ lock.holder?.since }} から）。このページでは編集できません（読み取り専用）。</p>
                <p class="text-xs">同時に編集すると、入力が上書きされるため、編集を制限しています。相手が画面を閉じるか、一定時間操作がなくなると、編集できるようになります。</p>
                <button v-if="lock.canTakeOver" type="button" class="rounded border border-amber-500 bg-white px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100" @click="takeOver">編集を引き継ぐ</button>
            </div>
            <div v-if="lockLost" class="space-y-2 rounded border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">{{ holderLabel }}が編集を始めたため、この画面では保存できません。</p>
                <p class="text-xs">入力中の内容を残したい場合は、内容を控えてから、ページを開き直してください。</p>
                <button type="button" class="rounded border border-red-400 bg-white px-3 py-1.5 text-xs font-medium text-red-800 hover:bg-red-100" @click="router.reload()">ページを開き直す</button>
            </div>
            <div v-if="!editable && !lock?.blocked && !lockLost && submission.status !== 'returned'" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <template v-if="submission.status === 'submitted'">提出済みです（内容の確認のみ）。締切前であれば、「確認・提出」のステップから取り下げて修正できます。</template>
                <template v-else>現在は入力できません（受付期間外です）。内容の確認のみできます。</template>
            </div>

            <!-- 今どこか: 工程の進み具合とステップ（クリックで任意の工程へ移動） -->
            <div class="rounded bg-white p-4 shadow">
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-800">いま <span class="text-lg text-blue-700">{{ step }}</span> / {{ steps.length }} 工程目：{{ steps.find((s) => s.no === step)?.label }}</p>
                    <p v-if="reviewed && problemSteps.length" class="text-xs font-medium text-red-700">⚠ 不備のある工程：{{ problemSteps.join('・') }}</p>
                    <p v-else-if="reviewed && submission.status !== 'submitted'" class="text-xs font-medium text-green-700">✓ すべての工程に不備はありません。確認画面から提出できます。</p>
                </div>
                <div class="mb-3 h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="step" :aria-valuemin="1" :aria-valuemax="steps.length">
                    <div class="h-full rounded-full bg-blue-500 transition-all" :style="{ width: progress + '%' }"></div>
                </div>
                <nav class="flex flex-wrap gap-2" aria-label="入力のステップ">
                    <Link v-for="s in steps" :key="s.no" :href="stepHref(s.no)" :title="statusOf(s.no) === 'warn' ? '不備があります' : statusOf(s.no) === 'done' ? '入力済み' : ''"
                        :class="['rounded-full px-3 py-1.5 text-xs font-medium sm:text-sm', stepClass[statusOf(s.no)]]">
                        {{ stepIcon(s.no) }} {{ s.no }}. {{ s.label }}
                    </Link>
                </nav>
                <p class="mt-2 text-xs text-gray-500">どの工程にも、上のボタンからいつでも移動できます（入力した内容は、「保存」すると残ります）。 ✓ 入力済み　⚠ 不備あり　○ これから</p>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-800">{{ steps.find((s) => s.no === step)?.label }}</h3>
                    <button v-if="showCarry" type="button" class="rounded border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-800 hover:bg-blue-100" @click="carryOver">前年の内容を読み込む</button>
                </div>

                <component :is="current" ref="stepRef" :submission="submission" :spouse="spouse" :dependents="dependents" :insurance-items="insuranceItems"
                    :options="options" :prefectures="prefectures" :editable="canEdit" :my-number-registered="myNumberRegistered"
                    :missing="missing" :notices="notices" :can-withdraw="canWithdraw" :can-return="canReturn" :proxy="proxy" :steps="steps" />

                <div v-if="step !== 7" class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
                    <div class="flex gap-2">
                        <Link v-if="step > 1" :href="stepHref(step - 1)" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">← 前へ</Link>
                    </div>
                    <div v-if="canEdit" class="flex flex-wrap gap-2">
                        <button type="button" :disabled="processing" class="rounded border border-blue-300 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 disabled:opacity-50" @click="save('stay')">下書き保存</button>
                        <button v-if="reviewed" type="button" :disabled="processing" class="rounded border border-green-400 bg-white px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50 disabled:opacity-50" @click="save('review')">保存して確認画面へ</button>
                        <button type="button" :disabled="processing" class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" @click="save('next')">{{ nextLabel }}</button>
                    </div>
                    <Link v-else :href="stepHref(step + 1)" class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700">次へ →</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
