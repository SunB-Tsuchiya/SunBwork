<script setup>
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import WorkloadAnalysisShell from '@/Components/Schedule/WorkloadAnalysisShell.vue';

const props = defineProps({ initialDate: { type: String, default: '' } });
const date = props.initialDate || new Date().toLocaleDateString('sv-SE');
const queryPeriod = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('period');
const period = ['day', 'week', 'month'].includes(queryPeriod) ? queryPeriod : 'day';
const queryScoreVersion = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('score_version');
const scoreVersion = queryScoreVersion === 'trial_time_v3' ? 'trial_time_v3' : 'trial_time_v2';
</script>

<template>
    <WorkloadAnalysisShell title="作業量分析ガイド" active="guide" :date="date" :period="period" :score-version="scoreVersion">
        <div class="mx-auto max-w-5xl space-y-5">
            <section class="rounded-lg bg-white p-5 shadow sm:p-6">
                <p class="text-xs font-semibold text-blue-700">まずは概要</p>
                <h2 class="mt-1 text-xl font-bold text-gray-900">この画面は、記録された仕事を確認するための試用分析です</h2>
                <p class="mt-3 text-sm leading-6 text-gray-700">
                    表の順番や点数だけで、仕事の成果・能力・査定を決めるものではありません。
                    記録がそろっているかを確かめ、作業内容と担当した負担を一緒に見ます。
                </p>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <div class="text-sm font-semibold text-blue-900">1. 記録の状態</div>
                        <p class="mt-1 text-sm text-gray-700">記録日と勤怠日を見ます。未入力は「仕事をしていない」という意味ではありません。</p>
                    </div>
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <div class="text-sm font-semibold text-blue-900">2. 作業の内容</div>
                        <p class="mt-1 text-sm text-gray-700">工程候補・入力件数・作業種類を見ます。工程候補は完了数ではありません。</p>
                    </div>
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <div class="text-sm font-semibold text-blue-900">3. 担当した負担</div>
                        <p class="mt-1 text-sm text-gray-700">記録時間と残業・案件登録・管理を分けて見ます。補助点は切り替えて比較できます。</p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <Link :href="route('schedule.department_work.details', { date, score_version: scoreVersion })" class="rounded bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">詳しい作業量分析を見る</Link>
                    <Link :href="route('schedule.department_work.results', { date, period, score_version: scoreVersion })" class="text-sm font-medium text-blue-700 underline">簡易順位を見る</Link>
                </div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow sm:p-6" aria-labelledby="detail-heading">
                <h2 id="detail-heading" class="text-lg font-bold text-gray-900">さらに知りたいとき</h2>
                <p class="mt-1 text-sm text-gray-600">気になる項目だけ開いて読めます。</p>

                <div class="mt-4 space-y-3">
                    <details class="group rounded border border-gray-200 p-4">
                        <summary class="cursor-pointer font-semibold text-gray-900">詳細 1｜誰の、どの記録が入る？</summary>
                        <div class="mt-3 space-y-3 text-sm leading-6 text-gray-700">
                            <p>現在は情報出版の Super Admin が試用しています。分析対象は個人別の設定を優先し、未設定なら正社員・契約社員を含め、派遣・業務委託を除きます。対象外人数は表示しますが、対象外の人を0点順位に入れません。</p>
                            <p>選んだ月の「1か月」、または選んだ月と前2か月の「3か月」を集計します。開始・終了時刻のある簡易作業、過去のマイジョブ・依頼ジョブ、仕事の会議・外出が対象です。簡易作業の進み・作業種類は任意で、未指定の作業種類には標準係数を使います。未来の日付や今日のこれからの時刻、辞退した会議は実績にしません。</p>
                            <p>非公開案件や会社・部署の閲覧範囲を守って集計します。表示されない記録があっても、本人が仕事をしていないとは判断できません。</p>
                        </div>
                    </details>

                    <details class="group rounded border border-gray-200 p-4">
                        <summary class="cursor-pointer font-semibold text-gray-900">詳細 2｜工程候補・入力件数・表示順の違い</summary>
                        <div class="mt-3 space-y-3 text-sm leading-6 text-gray-700">
                            <p><strong>入力件数</strong>は、対象になった記録の数です。予定を日報へ取り込んだ場合は二重に数えません。記録を細かく分けると件数は増えます。</p>
                            <p><strong>工程候補</strong>は、同じ人・日付・作業区分・作業種類・案件や工程をまとめた参考数です。例えば同じ日に同じ案件・作業種類を2回記録すると、入力2件でも工程候補1件になりえます。翌日の記録は別候補です。案件のない記録は作業名で、案件も作業名もない記録や会議は予定ごとに区別します。</p>
                            <p>「工程候補が多い順」「入力件数が多い順」「暫定点が高い順」は、それぞれの数値で表を並べ替えるだけです。同じ数値なら名前順です。工程候補は完了の確認や品質の判定をしていません。</p>
                            <p>作業区分・入力元の選択は<strong>該当する人を表示する条件</strong>です。表に出るその人の件数・時間・点数は、選択した種類だけでなく、その人の全記録の合計です。</p>
                        </div>
                    </details>

                    <details class="group rounded border border-gray-200 p-4">
                        <summary class="cursor-pointer font-semibold text-gray-900">詳細 3｜時間・点数・案件負担の計算</summary>
                        <div class="mt-3 space-y-3 text-sm leading-6 text-gray-700">
                            <p><strong>記録日／勤怠日</strong>は、認定した仕事時間のある日数／出退勤時刻を登録した日数です。横の割合はこの2つの日数の比で、仕事を記録した時間の割合ではありません。</p>
                            <p><strong>記録時間</strong>はJSTで数え、昼休憩と同じ人の重複時間を除きます。会議・業務外出が長い作業枠と重なる時間は、会議・外出を先に数えます。別々の作業が重なる場合、その時間は等分します。</p>
                            <p><strong>基本点</strong>は、記録時間 × 10点/時 × 作業種類係数です。係数は種類ごとに異なり、現在はおおむね0.8〜1.3です。基本点を「作業点（会議・訪問以外）」と「会議・訪問点」に分けて表示します。後者には連絡・打合せ、業務外出・訪問、訪問・商談を含みます。内訳表示によって、合計や係数は変わりません。ページ数、サイズ、難易度は点数に掛けません。</p>
                            <p v-if="scoreVersion === 'trial_time_v3'"><strong>残業追加点</strong>は、勤務記録の残業と作業記録が重なる時間のうち1日2時間まで、基本点の0.5倍です。超過分にも実作業の基本点は付きます。月45時間は会社の目標で、超過は上長の確認用に表示し、点数は下げません。</p>
                            <p v-else><strong>残業追加点</strong>は、勤務記録の残業と実際の作業記録が重なる時間だけに付きます。最初の3時間は基本点の0.5倍、超過分は0.2倍です。記録のない残業時間は別表示します。</p>
                            <p v-if="scoreVersion === 'trial_time_v3'"><strong>案件登録・管理</strong>の自動加点はありません。登録件数と活動管理案件数は参考情報として表示し、記録した進行調整・打合せ・登録作業は基本点で扱います。</p>
                            <p v-else><strong>案件登録点</strong>は登録者に5点/件です。<strong>案件管理点</strong>は、対象期間の各日について、さかのぼる14日間にチーム活動がある案件を数え、リーダー／副リーダーと作業メンバー数で調整する試験値です。どちらも成果の確定点ではありません。</p>
                            <p>「暫定合計」は作業点と会議・訪問点、それに適用中の追加点を足した表示値です。画面の切り替えは記録を変更しません。</p>
                            <p v-if="scoreVersion === 'trial_time_v3'">新しい試算では、公開範囲と業務の認定を分けます。非公開の業務も内容を伏せて時間と点数に含めます。「そのほか（業務）」は通常の時間点の0.9倍で計算し、「そのほか（私用）」は時間・点数の対象外です。以前の「そのほか」は業務として扱うので、私用の予定は本人が種類を変更してください。会議・訪問・外出も集計候補です。招待された会議は、2026年10月まで承認済みなら予定終了後、未回答なら予定日の翌日から集計します。2026年11月以降は承認済みだけを候補とし、正社員・契約社員は同日の勤怠記録も必要です。派遣・業務委託は承認済みなら候補にします。辞退済みは除外します。終了した業務予定は、削除・中止されていなければ実施したものとして扱います。</p>
                        </div>
                    </details>

                    <details class="group rounded border border-gray-200 p-4">
                        <summary class="cursor-pointer font-semibold text-gray-900">詳細 4｜評価を見直すときの手順</summary>
                        <div class="mt-3 space-y-3 text-sm leading-6 text-gray-700">
                            <p>まず入力がそろっている期間か確かめ、次に担当と作業種類ごとの実績を複数月で見ます。そのうえで、管理者が作業結果の具体性、難しさ、納期や進行管理の負担を確認します。</p>
                            <p>現在の暫定点は記録時間の影響が大きく、入力件数は分割方法で増えます。工程候補は自動完了を含むジョブの本当の完了数を保証しません。長い単一記録への自動減点や、新しい査定点はまだ決めていません。</p>
                            <p>旧方式は<Link :href="route('superadmin.workload_analyzer.archive')" class="text-blue-700 underline">旧方式アーカイブ</Link>で参照できます。旧方式も現DBによる再計算表示で、過去時点の固定結果ではありません。</p>
                        </div>
                    </details>
                </div>
            </section>
        </div>
    </WorkloadAnalysisShell>
</template>
