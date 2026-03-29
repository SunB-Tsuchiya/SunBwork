<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const authUser = page?.props?.value?.auth?.user || page?.props?.value?.user || {};
const userRole = authUser?.user_role || '';
const rolePrefix = (() => {
    const r = String(userRole).toLowerCase();
    if (r.includes('super')) return '/superadmin';
    if (r.includes('admin')) return '/admin';
    return '/leader';
})();

const indexUrl    = computed(() => `${rolePrefix}/workload-analyzer`);
const settingsUrl = computed(() => `${rolePrefix}/workload-analyzer/settings`);
</script>

<template>
    <AppLayout title="作業量分析 — 分析ガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <a :href="indexUrl" class="text-sm text-gray-500 hover:text-gray-700">← 分析一覧に戻る</a>
                <h2 class="text-xl font-semibold text-gray-800">作業量分析 — 分析ガイド</h2>
            </div>
        </template>

        <Head title="作業量分析 — 分析ガイド" />

        <div class="space-y-6">

            <!-- ① 概要 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 border-b pb-2 text-lg font-bold text-orange-700">① この分析の目的</h3>
                <p class="leading-relaxed text-gray-700">
                    作業量分析は、各メンバーが月間にどれだけの作業をこなしたかを数値化し、
                    役割や担当を考慮しながら公平に評価するための仕組みです。
                </p>
                <p class="mt-3 leading-relaxed text-gray-700">
                    組版会社では、組版オペレーター・デザイナー・校正者・進行管理・営業など、
                    担当によって作業の内容や量が根本的に異なります。
                    ページ数をそのまま比較すると、ページ処理量が多い校正者が高得点になりやすく、
                    不公平が生じます。
                    そこでこのシステムでは <strong>「同じ担当同士で比較する」</strong> 方式を採用しています。
                </p>
                <div class="mt-4 rounded bg-orange-50 p-4 text-sm text-orange-800">
                    <strong>基本理念：</strong>
                    校正者は校正者同士で、組版オペレーターは組版オペレーター同士で、
                    同じ土俵（＝同じ担当グループ）の中で相対的な貢献度を測ります。
                </div>
            </div>

            <!-- ② 6つのカテゴリ -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 border-b pb-2 text-lg font-bold text-orange-700">② 6つの評価カテゴリ</h3>
                <p class="mb-4 text-sm text-gray-600">
                    作業は以下の6つの観点から計測されます。各カテゴリが 0〜100 点のスコアになり、合計 0〜600 点が総合ポイントです。
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-gray-700">カテゴリ</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-700">何を測るか</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-700">主な対象担当</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-blue-700">ステージ</td>
                                <td class="px-4 py-2 text-gray-700">校正の進捗段階（初校・再校・校了 など）別のページ×係数×難易度</td>
                                <td class="px-4 py-2 text-gray-500">組版・DTP、校正</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-blue-700">サイズ</td>
                                <td class="px-4 py-2 text-gray-700">用紙サイズ（A4, B5, Web など）別のページ×係数×難易度</td>
                                <td class="px-4 py-2 text-gray-500">組版・DTP、デザイン</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-blue-700">種別</td>
                                <td class="px-4 py-2 text-gray-700">作業の種類（組版新規・修正・デザイン・校正 など）別のページ×係数×難易度</td>
                                <td class="px-4 py-2 text-gray-500">全担当</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-blue-700">難易度</td>
                                <td class="px-4 py-2 text-gray-700">案件の難易度（軽い・普通・重い・重大）を加味したページ×難易度係数</td>
                                <td class="px-4 py-2 text-gray-500">全担当</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-blue-700">イベント</td>
                                <td class="px-4 py-2 text-gray-700">カレンダーに登録された外出・打合せ・顧客訪問などの時間×種別係数</td>
                                <td class="px-4 py-2 text-gray-500">進行管理、営業</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-blue-700">残業</td>
                                <td class="px-4 py-2 text-gray-700">月間残業時間（3時間以内の通常残業 + 3時間超の超過残業で係数が異なる）</td>
                                <td class="px-4 py-2 text-gray-500">全担当</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    ※ 各係数は「<a :href="settingsUrl" class="text-blue-600 underline">作業量分析 設定</a>」画面で調整可能です。
                </p>
            </div>

            <!-- ③ 生スコアの計算式 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 border-b pb-2 text-lg font-bold text-orange-700">③ 生スコアの計算式</h3>
                <p class="mb-3 text-sm text-gray-600">
                    まず各カテゴリの「生スコア」を計算します。
                    これは作業量を係数で重み付けした値で、単位の異なる数値（ページ数・時間・分など）をそのまま足し合わせたものです。
                </p>
                <div class="rounded bg-gray-50 p-4 font-mono text-xs text-gray-800">
                    <div class="mb-1">ステージ生スコア  = Σ ( ページ数 × ステージ係数 × 難易度係数 )</div>
                    <div class="mb-1">サイズ生スコア    = Σ ( ページ数 × サイズ係数   × 難易度係数 )</div>
                    <div class="mb-1">種別生スコア      = Σ ( ページ数 × 種別係数     × 難易度係数 )</div>
                    <div class="mb-1">難易度生スコア    = Σ ( ページ数 × 難易度係数 )</div>
                    <div class="mb-1">イベント生スコア  = Σ ( イベント時間[時間] × イベント種別係数 )</div>
                    <div class="mb-1">残業生スコア（通常）= 合計残業分[≤180分/日] × 通常残業係数</div>
                    <div>残業生スコア（超過）= 合計残業分[＞180分/日] × 超過残業係数</div>
                </div>
                <div class="mt-3 rounded bg-yellow-50 p-3 text-xs text-yellow-800">
                    <strong>Note:</strong> 生スコアだけでは単位が異なるため、そのまま足して比較することはできません。
                    次のステップで「パーセンタイル変換」を行います。
                </div>
            </div>

            <!-- ④ 職種グループ別パーセンタイル（核心） -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 border-b pb-2 text-lg font-bold text-orange-700">④ 職種グループ別パーセンタイル変換（核心）</h3>
                <p class="mb-3 leading-relaxed text-gray-700">
                    生スコアを <strong>同じ担当（職種）のメンバー内で順位付け</strong> し、
                    パーセンタイル（0〜100）に変換します。
                    これが「公平さ」を生む仕組みです。
                </p>

                <!-- 図解ステップ -->
                <div class="mt-4 space-y-3">
                    <div class="flex items-start gap-3 rounded bg-blue-50 p-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                        <div>
                            <p class="text-sm font-semibold text-blue-800">担当グループで分類</p>
                            <p class="mt-1 text-xs text-gray-600">
                                部署のメンバーを「組版オペレーター」「校正者」「進行管理」「営業」… など、
                                それぞれの担当グループに分けます。
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded bg-blue-50 p-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
                        <div>
                            <p class="text-sm font-semibold text-blue-800">グループ内で順位付け</p>
                            <p class="mt-1 text-xs text-gray-600">
                                各カテゴリの生スコアを、同じ担当グループ内で比較して順位を付けます。
                                例：組版オペレーター5人の中で「ステージ生スコア」の1位〜5位を決めます。
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded bg-blue-50 p-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">3</span>
                        <div>
                            <p class="text-sm font-semibold text-blue-800">パーセンタイルに変換（0〜100点）</p>
                            <p class="mt-1 text-xs text-gray-600">
                                グループ内で1位 → 100点、最下位 → 0点、中間は線形補間で計算します。
                                同点タイは全員に同じ平均順位を付与します。
                            </p>
                            <div class="mt-2 rounded bg-white p-2 font-mono text-xs text-gray-700">
                                <div>自分より高い人数 = above（人）</div>
                                <div>自分と同じ人数　 = tied（人）</div>
                                <div>平均順位 avgRank = above + (tied + 1) / 2</div>
                                <div>パーセンタイル　 = (N − avgRank) / (N − 1) × 100</div>
                                <div class="mt-1 text-gray-400">※ N = グループ人数。N = 1 の場合は 100 固定</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded bg-gray-50 p-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-500 text-xs font-bold text-white">!</span>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">グループ人数が3人未満の場合</p>
                            <p class="mt-1 text-xs text-gray-600">
                                担当グループに同職種が 3 人に満たない場合は、
                                統計的な意味が薄いため <strong>部署全体を比較対象</strong> にフォールバックします。
                                個人詳細画面には比較グループが表示されます。
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 具体例 -->
                <div class="mt-5">
                    <p class="mb-2 text-sm font-semibold text-gray-700">▍具体例</p>
                    <div class="overflow-x-auto rounded border">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-left text-gray-600">名前</th>
                                    <th class="px-3 py-2 text-left text-gray-600">担当</th>
                                    <th class="px-3 py-2 text-right text-gray-600">ステージ生スコア</th>
                                    <th class="px-3 py-2 text-right text-gray-600">比較グループ</th>
                                    <th class="px-3 py-2 text-right text-gray-600">ステージ点（0〜100）</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="bg-blue-50">
                                    <td class="px-3 py-1.5 font-medium">田中</td>
                                    <td class="px-3 py-1.5">組版オペレーター</td>
                                    <td class="px-3 py-1.5 text-right">80</td>
                                    <td class="px-3 py-1.5 text-right text-blue-600">組版オペレーター(4人)</td>
                                    <td class="px-3 py-1.5 text-right font-bold text-blue-700">100</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-1.5 font-medium">山田</td>
                                    <td class="px-3 py-1.5">組版オペレーター</td>
                                    <td class="px-3 py-1.5 text-right">60</td>
                                    <td class="px-3 py-1.5 text-right text-blue-600">組版オペレーター(4人)</td>
                                    <td class="px-3 py-1.5 text-right font-bold">67</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-1.5 font-medium">鈴木</td>
                                    <td class="px-3 py-1.5">組版オペレーター</td>
                                    <td class="px-3 py-1.5 text-right">40</td>
                                    <td class="px-3 py-1.5 text-right text-blue-600">組版オペレーター(4人)</td>
                                    <td class="px-3 py-1.5 text-right">33</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-1.5 font-medium">佐藤</td>
                                    <td class="px-3 py-1.5">組版オペレーター</td>
                                    <td class="px-3 py-1.5 text-right">20</td>
                                    <td class="px-3 py-1.5 text-right text-blue-600">組版オペレーター(4人)</td>
                                    <td class="px-3 py-1.5 text-right">0</td>
                                </tr>
                                <tr class="bg-green-50">
                                    <td class="px-3 py-1.5 font-medium">伊藤</td>
                                    <td class="px-3 py-1.5">校正者</td>
                                    <td class="px-3 py-1.5 text-right">250</td>
                                    <td class="px-3 py-1.5 text-right text-green-600">校正者(3人)</td>
                                    <td class="px-3 py-1.5 text-right font-bold text-green-700">100</td>
                                </tr>
                                <tr class="bg-green-50">
                                    <td class="px-3 py-1.5 font-medium">加藤</td>
                                    <td class="px-3 py-1.5">校正者</td>
                                    <td class="px-3 py-1.5 text-right">180</td>
                                    <td class="px-3 py-1.5 text-right text-green-600">校正者(3人)</td>
                                    <td class="px-3 py-1.5 text-right font-bold text-green-700">50</td>
                                </tr>
                                <tr class="bg-green-50">
                                    <td class="px-3 py-1.5 font-medium">高橋</td>
                                    <td class="px-3 py-1.5">校正者</td>
                                    <td class="px-3 py-1.5 text-right">120</td>
                                    <td class="px-3 py-1.5 text-right text-green-600">校正者(3人)</td>
                                    <td class="px-3 py-1.5 text-right">0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        校正者の生スコアは組版オペレーターより大きくても、<strong>比較グループが分かれている</strong>ため、
                        それぞれのグループ内で公平に評価されます。
                    </p>
                </div>
            </div>

            <!-- ⑤ 総合ポイント -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 border-b pb-2 text-lg font-bold text-orange-700">⑤ 総合ポイント（0〜600）</h3>
                <p class="mb-3 text-sm text-gray-700">
                    6つのカテゴリのパーセンタイル点（各 0〜100）を合計したものが総合ポイントです。
                </p>
                <div class="rounded bg-gray-50 p-4 font-mono text-xs text-gray-800">
                    総合ポイント = ステージ + サイズ + 種別 + 難易度 + イベント + 残業
                    <br>
                    （各カテゴリは同職種グループ内パーセンタイル 0〜100。最大 600 点）
                </div>
                <div class="mt-3 grid grid-cols-3 gap-3 text-center text-sm">
                    <div class="rounded bg-green-50 p-3">
                        <div class="text-xl font-bold text-green-700">500〜600</div>
                        <div class="mt-1 text-xs text-gray-600">複数カテゴリで同職種トップクラス</div>
                    </div>
                    <div class="rounded bg-blue-50 p-3">
                        <div class="text-xl font-bold text-blue-700">250〜350</div>
                        <div class="mt-1 text-xs text-gray-600">同職種内で標準的な貢献</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xl font-bold text-gray-500">〜100</div>
                        <div class="mt-1 text-xs text-gray-600">作業量が少ない、またはデータ不足</div>
                    </div>
                </div>
            </div>

            <!-- ⑥ 偏差値 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 border-b pb-2 text-lg font-bold text-orange-700">⑥ 偏差値（参考値）</h3>
                <p class="mb-3 text-sm text-gray-700">
                    総合ポイントを会社全体（全担当混合）で統計処理し、偏差値に変換します。
                    偏差値は集団の中での相対的な位置を示す参考値です。
                </p>
                <div class="rounded bg-gray-50 p-4 font-mono text-xs text-gray-800">
                    <div>比較グループ: 同会社の全メンバー</div>
                    <div>z = ( 自分の総合ポイント − グループ平均 ) / グループ標準偏差</div>
                    <div>偏差値 = 50 + 10 × z</div>
                </div>
                <div class="mt-3 rounded bg-yellow-50 p-3 text-xs text-yellow-800">
                    <strong>注意:</strong>
                    偏差値の比較母集団は「会社全体（全担当混合）」です。
                    パーセンタイル・総合ポイントの比較母集団（「同職種グループ」または部署）とは異なります。
                    偏差値は異なる担当を横断した大まかな位置把握に使用してください。
                </div>
            </div>

            <!-- ⑦ よくある質問 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 border-b pb-2 text-lg font-bold text-orange-700">⑦ よくある質問（Q&amp;A）</h3>
                <div class="space-y-4">
                    <div>
                        <p class="font-semibold text-gray-800">Q. 同職種グループに3人未満しかいない場合は？</p>
                        <p class="mt-1 text-sm text-gray-600">
                            A. 統計的な意味が薄くなるため、部署全体を比較対象にフォールバックします。
                            個人詳細ページに「比較グループ」として表示されるので確認できます。
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Q. ページ数がゼロの担当（営業など）はどうなる？</p>
                        <p class="mt-1 text-sm text-gray-600">
                            A. ページ系のカテゴリ（ステージ・サイズ・種別・難易度）の生スコアは 0 になります。
                            ただし、イベント（外出・打合せ）や残業のカテゴリで評価されます。
                            営業グループでは全員のページ系スコアが 0 に近いため、
                            パーセンタイル変換後も同職種内では公平に評価されます。
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Q. 係数はどこで変更できる？</p>
                        <p class="mt-1 text-sm text-gray-600">
                            A.
                            <a :href="settingsUrl" class="text-blue-600 underline">作業量分析 設定</a>
                            画面でステージ・サイズ・種別・難易度・イベント種別・残業の係数を変更できます。
                            変更は翌月の計算から反映されます。
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Q. 残業の「通常」と「超過」の違いは？</p>
                        <p class="mt-1 text-sm text-gray-600">
                            A. 1日の残業が 3 時間（180分）以内を「通常残業」、超えた分を「超過残業」として分けています。
                            超過残業には別途係数が適用されます（初期値は 0.8 — 過度な残業より効率的な作業を重視するため）。
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Q. カテゴリ別ランクとパーセンタイルは同じ？</p>
                        <p class="mt-1 text-sm text-gray-600">
                            A. 違います。カテゴリ別ランクは各カテゴリの <strong>生スコア</strong> で順位を付けたものです。
                            パーセンタイルは職種グループ内での相対的な位置（0〜100）です。
                            カテゴリ別ランク画面では部署全体の順位を見ることができます。
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Q. 同職種でも月によって担当が変わった場合は？</p>
                        <p class="mt-1 text-sm text-gray-600">
                            A. 計算はユーザーの現在の「担当（assignment_name）」で行います。
                            担当の変更はリアルタイムに反映されます。
                        </p>
                    </div>
                </div>
            </div>

            <!-- 戻るボタン -->
            <div class="flex justify-center pb-4">
                <a
                    :href="indexUrl"
                    class="rounded bg-orange-600 px-6 py-2 text-sm font-medium text-white hover:bg-orange-700"
                >← 分析一覧に戻る</a>
            </div>

        </div>
    </AppLayout>
</template>
