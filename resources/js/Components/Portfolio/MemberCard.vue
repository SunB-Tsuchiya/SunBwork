<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

// 登録者 1 人分のカード（管理画面「メンバーのポートフォリオ」）。
// カード・名前・サムネイルのどれをクリックしても、その人のページ（閲覧専用）へ移る。
// 「客先で見せる」「編集する」は、そのボタンだけが働く（カードの遷移とは別）。
const props = defineProps({
    member: { type: Object, required: true },
});

const memberHref = computed(() => route('admin.portfolio.members.show', { member: props.member.id }));
const viewHref = computed(() => route('portfolio.view', { owner_id: props.member.id }));
const thumbUrl = (it) => route('portfolio.api.files', { item: it.id, kind: 'thumb' });

const TYPE_LABEL = { image: '画像', pdf: 'PDF', video: '動画' };
const TYPE_MARK = { pdf: 'PDF', video: '▶' };

const countText = computed(() =>
    Object.entries(props.member.counts ?? {})
        .filter(([, n]) => n > 0)
        .map(([t, n]) => `${TYPE_LABEL[t]} ${n}`)
        .join(' ・ '),
);

// 問題のある作品（アップ中・変換中のまま残っている／変換に失敗した）。Admin は直せないので、本人に確認してもらう目印にする
const problemText = computed(() => {
    const p = props.member.problem ?? {};
    const parts = [];
    if (p.failed) parts.push(`変換に失敗 ${p.failed}`);
    if (p.uploading) parts.push(`アップ中のまま ${p.uploading}`);
    return parts.join(' ・ ');
});

// 最終更新の相対表示。日付はブラウザのローカル日付で比べる（JST で、UTC とのずれを出さない）
const updatedText = computed(() => {
    if (!props.member.updated_at) return '';
    const day = (d) => Date.parse(`${d.toLocaleDateString('sv-SE')}T00:00:00Z`);
    const diff = Math.round((day(new Date()) - day(new Date(props.member.updated_at))) / 86400000);
    if (diff <= 0) return '今日';
    if (diff === 1) return '昨日';
    if (diff < 30) return `${diff}日前`;
    if (diff < 365) return `${Math.floor(diff / 30)}か月前`;
    return new Date(props.member.updated_at).toLocaleDateString('sv-SE');
});

// カードの余白をクリックしたときだけ遷移（中のリンクやボタンは、それぞれの動きを優先する）
function onCardClick(e) {
    if (e.target.closest('a, button')) return;
    router.visit(memberHref.value);
}
</script>

<template>
    <article
        class="cursor-pointer rounded-lg border border-gray-200 bg-white p-4 transition-shadow hover:shadow-md"
        :class="problemText ? 'border-l-4 border-l-red-400' : ''"
        @click="onCardClick"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="memberHref" class="text-base font-semibold text-gray-900 hover:underline">{{ member.name }}</Link>
                    <span v-if="member.role_label" class="rounded px-1.5 py-0.5 text-[10px] font-medium" :class="member.role_label === 'SuperAdmin' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-700'">{{ member.role_label }}</span>
                    <span v-if="member.is_self" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">自分</span>
                </div>
                <div class="mt-0.5 text-xs text-gray-500">
                    {{ member.department }}<template v-if="member.position_title"> ・ {{ member.position_title }}</template>
                </div>
            </div>
            <div class="flex shrink-0 gap-2">
                <Link v-if="member.can_edit" :href="route('user.portfolio.manage')" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">編集する →</Link>
                <a :href="viewHref" target="_blank" rel="noopener noreferrer" class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">客先で見せる ↗</a>
            </div>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
            <span><strong class="text-gray-900">作品 {{ member.total }}</strong><template v-if="countText">（{{ countText }}）</template></span>
            <span v-if="updatedText" class="text-gray-400">最終更新 {{ updatedText }}</span>
            <span v-if="problemText" class="rounded bg-red-50 px-2 py-0.5 font-medium text-red-700" title="本人に確認してもらってください（Admin は直せません）">⚠ 要確認: {{ problemText }}</span>
        </div>

        <!-- サムネイル（最大 4 点。クリックでその人のページへ） -->
        <div v-if="member.previews.length" class="mt-3 flex flex-wrap items-stretch gap-2">
            <Link v-for="p in member.previews" :key="p.id" :href="memberHref" class="relative block h-20 w-28 overflow-hidden rounded-md bg-gray-100 ring-1 ring-black/5 hover:ring-gray-400">
                <img :src="thumbUrl(p)" alt="" class="h-full w-full object-cover" loading="lazy" draggable="false" />
                <span v-if="TYPE_MARK[p.type]" class="absolute left-1 top-1 rounded bg-white/90 px-1 text-[10px] font-medium text-gray-700">{{ TYPE_MARK[p.type] }}</span>
            </Link>
            <Link v-if="member.more > 0" :href="memberHref" class="flex h-20 w-16 items-center justify-center rounded-md bg-gray-50 text-sm font-medium text-gray-500 ring-1 ring-black/5 hover:bg-gray-100">+{{ member.more }}</Link>
        </div>
        <p v-else class="mt-3 text-xs text-gray-400">完成した作品がまだありません。</p>
    </article>
</template>
