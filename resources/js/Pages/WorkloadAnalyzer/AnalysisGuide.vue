<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ legacyArchive: { type: Boolean, default: false } });
const page = usePage();
const rolePrefix = computed(() => {
    const role = String(page.props.auth?.user?.user_role || page.props.user?.user_role || '').toLowerCase();
    if (role.includes('super')) return 'superadmin';
    if (role.includes('admin')) return 'admin';
    return 'leader';
});
const indexUrl = computed(() => route(props.legacyArchive
    ? 'superadmin.workload_analyzer.archive'
    : `${rolePrefix.value}.workload_analyzer.index`));
</script>

<template>
    <AppLayout title="旧方式・作業量分析ガイド">
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="indexUrl" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← 分析画面に戻る</Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">旧方式・作業量分析ガイド</h2>
            </div>
        </template>

        <Head title="旧方式・作業量分析ガイド" />

        <div class="space-y-5">
            <section class="rounded bg-white p-5 shadow sm:p-6">
                <p class="text-xs font-semibold text-orange-700">まずは概要</p>
                <h3 class="mt-1 text-xl font-bold text-gray-900">旧方式の点数は、6項目の相対順位を足した参考値です</h3>
                <p class="mt-3 text-sm leading-6 text-gray-700">
                    月ごとにページ関連の4項目、予定・イベント、残業を集計し、各項目を0〜100点に変えて合計します。
                    最大600点ですが、実際に完了した仕事の量や質を表す点数ではありません。
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded border border-orange-100 bg-orange-50 p-4">
                        <div class="text-sm font-semibold text-orange-900">何を比べる？</div>
                        <p class="mt-1 text-sm text-gray-700">担当が同じ人同士。3人未満の担当は部署全体で比べます。</p>
                    </div>
                    <div class="rounded border border-orange-100 bg-orange-50 p-4">
                        <div class="text-sm font-semibold text-orange-900">点数の意味は？</div>
                        <p class="mt-1 text-sm text-gray-700">6項目での相対的な位置を足した値です。月や比較相手が変わると点数も変わります。</p>
                    </div>
                    <div class="rounded border border-orange-100 bg-orange-50 p-4">
                        <div class="text-sm font-semibold text-orange-900">どう扱う？</div>
                        <p class="mt-1 text-sm text-gray-700">ページ数の重複反映など既知のずれがあります。査定の確定値にしません。</p>
                    </div>
                </div>
                <p class="mt-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                    この画面は現在のデータベースを旧式で再計算します。過去に表示した点数の保存版ではありません。
                    <span v-if="legacyArchive">情報出版の Super Admin では、新方式の試用画面を別に用意しています。</span>
                </p>
            </section>

            <section class="rounded bg-white p-5 shadow sm:p-6" aria-labelledby="legacy-details-heading">
                <h3 id="legacy-details-heading" class="text-lg font-bold text-gray-900">詳しく知りたいとき</h3>
                <p class="mt-1 text-sm text-gray-600">気になる項目だけ開いてください。</p>
                <div class="mt-4 space-y-3">
                    <details class="rounded border border-gray-200 p-4">
                        <summary class="cursor-pointer font-semibold text-gray-900">詳細 1｜6つの項目</summary>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-left text-sm text-gray-700">
                                <thead class="bg-gray-50 text-gray-600"><tr><th class="px-3 py-2">項目</th><th class="px-3 py-2">主に使う記録</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><th class="px-3 py-2 font-medium">ステージ</th><td class="px-3 py-2">ジョブのページ数と校正段階などの係数</td></tr>
                                    <tr><th class="px-3 py-2 font-medium">サイズ</th><td class="px-3 py-2">同じジョブのページ数と用紙サイズなどの係数</td></tr>
                                    <tr><th class="px-3 py-2 font-medium">種別</th><td class="px-3 py-2">同じジョブのページ数と作業種別などの係数</td></tr>
                                    <tr><th class="px-3 py-2 font-medium">難易度</th><td class="px-3 py-2">同じジョブのページ数と難易度係数</td></tr>
                                    <tr><th class="px-3 py-2 font-medium">イベント</th><td class="px-3 py-2">予定・イベントの時間と種類の係数</td></tr>
                                    <tr><th class="px-3 py-2 font-medium">残業</th><td class="px-3 py-2">勤怠の残業分と通常／超過の係数</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-sm text-gray-700">最初の4項目には同じページ数が繰り返し入ることがあります。ページ数が案件全体の値なら、実際にその人が触れた量とも一致しません。</p>
                    </details>

                    <details class="rounded border border-gray-200 p-4">
                        <summary class="cursor-pointer font-semibold text-gray-900">詳細 2｜0〜600点になるまで</summary>
                        <ol class="mt-3 list-inside list-decimal space-y-2 text-sm leading-6 text-gray-700">
                            <li>6項目それぞれについて、数量や時間に係数を掛けた元の値を作ります。</li>
                            <li>同じ担当が3人以上なら担当内で、3人未満なら部署内で、その項目の位置を0〜100点に換算します。同点は同じ平均順位です。</li>
                            <li>6項目の点を足して総合点を表示します。偏差値は総合点を会社全体で比べた別の参考値です。</li>
                        </ol>
                        <p class="mt-3 rounded bg-gray-50 p-3 text-sm text-gray-700">各項目の0〜100点は作業時間や完了数の単位ではありません。比較する人の顔ぶれや担当設定が変われば、同じ作業記録でも相対点は変わります。</p>
                    </details>

                    <details class="rounded border border-gray-200 p-4">
                        <summary class="cursor-pointer font-semibold text-gray-900">詳細 3｜読むときの注意</summary>
                        <div class="mt-3 space-y-3 text-sm leading-6 text-gray-700">
                            <p>自己登録のジョブが重複して集計される経路があります。ページ数も複数項目へ重ねて入るため、点数が実作業量に比例するとは限りません。</p>
                            <p>担当は現在の登録状態で比較します。期間によってジョブは希望終了日、イベントは実施日時、残業は勤怠日を使うため、同じ月の数字でも元の期間が一致しない場合があります。</p>
                            <p>入力がないことを無作業と判断せず、記録内容や担当業務を確認してください。係数の変更後も現DBから再計算するので、以前見た点数と違うことがあります。</p>
                        </div>
                    </details>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
