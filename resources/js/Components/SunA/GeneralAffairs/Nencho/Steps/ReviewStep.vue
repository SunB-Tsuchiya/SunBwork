<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { useNenchoLockToken, useNenchoRoute } from '@/Components/SunA/GeneralAffairs/Nencho/Fields/useNenchoRoutes';
import SubmissionSummary from '../SubmissionSummary.vue';
import { route } from 'ziggy-js';

const props = defineProps({
    submission: { type: Object, required: true },
    spouse: { type: Object, default: null },
    dependents: { type: Array, default: () => [] },
    insuranceItems: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: false },
    missing: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    canWithdraw: { type: Boolean, default: false },
    canReturn: { type: Boolean, default: false },
    proxy: { type: Object, default: null },
    steps: { type: Array, default: () => [] },
});

const s = props.submission;
const nenchoRoute = useNenchoRoute();
const lockToken = useNenchoLockToken();
const stepHref = (no) => nenchoRoute('show', { submission: s.id, step: no });
// 代理入力の提出: 本人確認の記録（任意）
const confirmForm = useForm({ confirmation: { paper: false, person: false, note: '' } });

function submit() {
    if (!confirm(props.proxy ? `${props.proxy.user_name}さんの申告を、代理で提出します。よろしいですか？` : 'この内容で提出します。よろしいですか？（締切前であれば、取り下げて修正できます）')) return;
    if (props.proxy) {
        confirmForm.transform((d) => ({ ...d, lock_token: lockToken?.value })).post(nenchoRoute('submit', { submission: s.id }));
        return;
    }
    router.post(nenchoRoute('submit', { submission: s.id }), { lock_token: lockToken?.value });
}
function returnToSelf() {
    // 総務の権限を持つ人が、自分の提出物を差し戻して修正する（総務と従業員を兼ねる人向け）
    const comment = window.prompt('差戻しのコメントを入力してください（例：住所を修正する）', '');
    if (comment === null) return;
    if (!comment.trim()) { window.alert('コメントを入力してください。'); return; }
    router.post(route('clerk.general_affairs.nencho.submissions.return', { submission: s.id }), { comment, steps: [] });
}
function withdraw() {
    if (!confirm('提出を取り下げます。修正して、もう一度提出してください。よろしいですか？')) return;
    router.post(nenchoRoute('withdraw', { submission: s.id }));
}
defineExpose({ save: () => {}, processing: false });
</script>

<template>
    <div class="space-y-6">
        <div v-if="missing.length" class="rounded border border-red-200 bg-red-50 p-4">
            <p class="mb-2 text-sm font-semibold text-red-800">未入力の項目があります（提出する前に入力してください）</p>
            <ul class="space-y-1 text-sm text-red-700">
                <li v-for="(m, i) in missing" :key="i"><Link :href="stepHref(m.step)" class="underline hover:no-underline">{{ m.message }}</Link></li>
            </ul>
        </div>
        <div v-for="(n, i) in notices" :key="`n${i}`" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ n }}</div>

        <SubmissionSummary :submission="submission" :spouse="spouse" :dependents="dependents" :insurance-items="insuranceItems" :options="options" :step-href="stepHref" />

        <div v-if="proxy && editable" class="space-y-3 rounded border border-gray-200 bg-gray-50 p-4">
            <p class="text-sm font-semibold text-gray-800">代理入力の本人確認の記録（任意）</p>
            <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="confirmForm.confirmation.paper" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" />紙の申告書を受け取った</label>
            <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="confirmForm.confirmation.person" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" />本人に内容を確認した</label>
            <div>
                <label for="proxy-note" class="mb-1 block text-xs font-medium text-gray-700">メモ（任意）</label>
                <input id="proxy-note" v-model="confirmForm.confirmation.note" type="text" maxlength="500" class="w-full rounded border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                <p class="mt-1 text-xs text-gray-500">例：6/10 紙の申告書を受け取り、本人に電話で内容を確認</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
            <Link :href="stepHref(6)" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">← 前へ</Link>
            <div class="flex flex-wrap gap-2">
                <button v-if="canReturn" type="button" class="rounded border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50" @click="returnToSelf">差戻しにして修正する（総務の操作）</button>
                <button v-if="canWithdraw && !proxy" type="button" class="rounded border border-amber-400 bg-white px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50" @click="withdraw">提出を取り下げて修正する</button>
                <button v-if="editable" type="button" :disabled="missing.length > 0" class="rounded bg-green-600 px-6 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50" @click="submit">この内容で提出する</button>
            </div>
        </div>
    </div>
</template>
