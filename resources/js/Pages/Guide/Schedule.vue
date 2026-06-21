<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="予定表（スケジュール）使い方ガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('guide.index')" class="text-sm text-teal-500 hover:underline">← ガイド一覧</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">予定表（スケジュール）使い方ガイド</h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- ヒーローバナー -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-teal-500 to-cyan-400 p-8 text-white shadow">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-4xl">📅</div>
                    <div>
                        <div class="mb-1 text-sm font-medium text-teal-100">Schedule Guide</div>
                        <h1 class="text-3xl font-bold">予定表（スケジュール）使い方ガイド</h1>
                        <p class="mt-1 text-teal-100">会議・打合せ・イベントの管理と会議室予約の使い方</p>
                    </div>
                </div>
            </div>

            <!-- もくじ -->
            <div class="rounded-xl border border-teal-100 bg-teal-50 p-5">
                <h3 class="mb-3 font-semibold text-teal-700">📌 もくじ</h3>
                <ol class="space-y-1 text-sm text-teal-600">
                    <li><a href="#difference" class="hover:underline">1. 予定表とカレンダーの違い（まず読んでください）</a></li>
                    <li><a href="#overview" class="hover:underline">2. 画面の見方</a></li>
                    <li><a href="#create-event" class="hover:underline">3. 予定を作成する</a></li>
                    <li><a href="#room-reservation" class="hover:underline">4. 会議室を予約する</a></li>
                    <li><a href="#overlay" class="hover:underline">5. 他の人の予定を表示する（オーバーレイ）</a></li>
                    <li><a href="#notifications" class="hover:underline">6. 通知について</a></li>
                    <li><a href="#permissions" class="hover:underline">7. 権限について</a></li>
                </ol>
            </div>

            <!-- 1. 予定表とカレンダーの違い -->
            <div id="difference" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-sm font-bold text-teal-600">1</span>
                    予定表とカレンダーの違い（まず読んでください）
                </h2>

                <!-- 重要バナー -->
                <div class="mb-5 flex gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <span class="text-2xl">💡</span>
                    <div>
                        <div class="mb-1 font-semibold text-teal-700">SunBWork には「予定表」と「カレンダー」の2種類があります</div>
                        <p class="text-sm text-teal-600">それぞれ<strong>用途が異なります</strong>。下の表をご確認ください。</p>
                    </div>
                </div>

                <!-- 比較表 -->
                <div class="mb-5 overflow-hidden rounded-xl border border-gray-200">
                    <div class="grid grid-cols-3 bg-gray-100 px-4 py-2 text-xs font-bold text-gray-600">
                        <div></div>
                        <div class="text-center text-teal-700">📅 予定表（スケジュール）</div>
                        <div class="text-center text-blue-700">📋 カレンダー（ユーザー）</div>
                    </div>
                    <div v-for="row in [
                        { label: '主な用途', sched: '会議・打合せ・社内イベントの共有', cal: 'ジョブの作業記録・個人のスケジュール管理' },
                        { label: '対象', sched: '会社全体・チーム向けの予定', cal: '自分個人の作業予定・日々の記録' },
                        { label: '打合せの予定', sched: '表示される（主役）', cal: '登録できるが、予定表と重複する場合あり' },
                        { label: '作業ジョブ', sched: '表示されない', cal: '主役（作業ジョブの時間管理に使う）' },
                        { label: '他人の予定', sched: '追加表示できる（オーバーレイ）', cal: '表示されない' },
                        { label: '会議室予約', sched: '予約・確認できる', cal: '表示されない' },
                    ]" :key="row.label"
                        class="grid grid-cols-3 items-start border-t border-gray-100 px-4 py-3">
                        <div class="text-xs font-semibold text-gray-500">{{ row.label }}</div>
                        <div class="px-2 text-xs text-gray-700">{{ row.sched }}</div>
                        <div class="px-2 text-xs text-gray-700">{{ row.cal }}</div>
                    </div>
                </div>

                <!-- ポイントまとめ -->
                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-teal-100 bg-teal-50 p-4">
                        <div class="mb-2 flex items-center gap-2 font-semibold text-teal-700">
                            <span class="text-xl">📅</span> 予定表（スケジュール）はこんな時に使う
                        </div>
                        <ul class="space-y-1 text-sm text-teal-600">
                            <li>・ 会議・打合せ・社内イベントを登録・確認したい</li>
                            <li>・ 会議室を予約したい</li>
                            <li>・ 同僚がいつ空いているか確認したい</li>
                            <li>・ 複数人の予定を並べて見たい</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <div class="mb-2 flex items-center gap-2 font-semibold text-blue-700">
                            <span class="text-xl">📋</span> カレンダーはこんな時に使う
                        </div>
                        <ul class="space-y-1 text-sm text-blue-600">
                            <li>・ 今日・今週の自分の作業スケジュールを組みたい</li>
                            <li>・ ジョブの作業時間を記録したい</li>
                            <li>・ 日報に作業タイムラインを反映させたい</li>
                        </ul>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                    <strong>⚠️ ポイント：</strong>
                    ユーザーのカレンダーには打合せなどの予定も入力できますが、<strong>予定表に表示されるのは「予定表」機能で登録した打合せ・会議などのみ</strong>です。
                    カレンダーに入力した打合せは予定表には自動反映されません。
                </div>
            </div>

            <!-- 2. 画面の見方 -->
            <div id="overview" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-sm font-bold text-teal-600">2</span>
                    画面の見方
                </h2>

                <div class="mb-5 overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">画面構成</div>
                    <div v-for="item in [
                        { area: '左サイドバー', icon: '📆', desc: 'ミニカレンダーと今日の会議室予約一覧。日付をクリックすると日ビューへ移動します。' },
                        { area: '中央カレンダー', icon: '📋', desc: '月・週・日の3つのビューで予定を確認できます。ビューの切り替えはツールバーのボタンから。' },
                        { area: '日ビュー（メイン）', icon: '👥', desc: '自分のカラム＋追加した人のカラムが横並びで表示されます。時間帯ごとの予定が一目でわかります。' },
                        { area: '会議室カラム', icon: '🏢', desc: '日ビューで各会議室の予約状況を確認できます。グレーの時間帯は予約不可（営業時間外）です。' },
                        { area: '右上ツールバー', icon: '🔧', desc: '他の人の予定を追加（オーバーレイ）したり、通知を確認したりするボタンがあります。' },
                    ]" :key="item.area" class="flex items-start gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="text-xl">{{ item.icon }}</span>
                        <div>
                            <div class="text-sm font-semibold text-gray-700">{{ item.area }}</div>
                            <div class="text-xs text-gray-500">{{ item.desc }}</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3 font-semibold text-gray-700">ビューの切り替え</div>
                <div class="grid grid-cols-3 gap-3">
                    <div v-for="v in [
                        { name: '月ビュー', icon: '🗓️', desc: '月単位でイベントの有無を把握。忙しい日を一覧で確認。' },
                        { name: '週ビュー', icon: '📅', desc: '月〜日の7日間をタイムライン形式で確認。' },
                        { name: '日ビュー', icon: '⏱️', desc: '複数人の予定を横並びで詳細表示（メインビュー）。' },
                    ]" :key="v.name" class="rounded-lg border border-teal-100 bg-teal-50 p-3 text-center">
                        <div class="mb-1 text-2xl">{{ v.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-teal-700">{{ v.name }}</div>
                        <div class="text-xs text-teal-500">{{ v.desc }}</div>
                    </div>
                </div>
            </div>

            <!-- 3. 予定を作成する -->
            <div id="create-event" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-sm font-bold text-teal-600">3</span>
                    予定を作成する
                </h2>

                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-teal-100 bg-teal-50 p-4">
                        <div class="mb-2 font-semibold text-teal-700">📌 作成できる予定の種類</div>
                        <ul class="space-y-1 text-sm text-teal-600">
                            <li>・ 会議</li>
                            <li>・ 打合せ（社内・顧客）</li>
                            <li>・ 外出・顧客訪問</li>
                            <li>・ 来社対応</li>
                            <li>・ その他イベント</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <div class="mb-2 font-semibold text-gray-700">📝 設定できる項目</div>
                        <ul class="space-y-1 text-sm text-gray-600">
                            <li>・ タイトル・種別</li>
                            <li>・ 日付・開始〜終了時刻（15分単位）</li>
                            <li>・ 公開範囲（社内公開 / 非公開など）</li>
                            <li>・ 参加者（複数人追加可）</li>
                            <li>・ メモ・備考</li>
                        </ul>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">予定の作成手順</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="step in [
                        { num: '①', action: '日ビューで時間帯をドラッグ（または日付クリック）', desc: '自分のカラムをドラッグすると、その時間帯が自動入力された状態でフォームが開きます' },
                        { num: '②', action: 'タイトル・種別を入力', desc: '会議・打合せ・外出などの種別を選ぶとアイコンや色が変わります' },
                        { num: '③', action: '参加者を追加（任意）', desc: '社内のユーザーを参加者として追加できます。追加された人の予定に登録されます' },
                        { num: '④', action: '会議室を選択（任意）', desc: 'タイムラインで空いている会議室をクリックすると自動選択されます' },
                        { num: '⑤', action: '「保存」ボタンを押す', desc: '予定が登録され、カレンダーに表示されます' },
                    ]" :key="step.num" class="flex items-start gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-700">{{ step.num }}</span>
                        <div>
                            <div class="text-sm font-semibold text-gray-700">{{ step.action }}</div>
                            <div class="text-xs text-gray-500">{{ step.desc }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                    <strong>⚠️ 注意：</strong>
                    予定は<strong>同日内のみ</strong>設定できます（日をまたいだ登録は禁止）。出張・宿泊などは別途 Outlook にてご対応ください。
                </div>
            </div>

            <!-- 4. 会議室を予約する -->
            <div id="room-reservation" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-sm font-bold text-teal-600">4</span>
                    会議室を予約する
                </h2>

                <div class="mb-4 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <div class="mb-1 font-semibold text-amber-700">現在テスト中の機能です</div>
                        <p class="text-sm text-amber-600">
                            会議室予約はテスト機能のため、<strong>正式な会議室予約は引き続き Outlook で行ってください。</strong>
                        </p>
                    </div>
                </div>

                <div class="mb-5 overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">予約できる会議室</div>
                    <div v-for="room in ['田端会議室', '田端多目的ルーム', '田端応接室']" :key="room"
                        class="flex items-center gap-3 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="text-lg">🏢</span>
                        <span class="text-sm text-gray-700">{{ room }}</span>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">会議室予約の手順</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="step in [
                        { num: '①', action: '日ビューで会議室カラムの時間帯をドラッグ', desc: '会議室カラムは右側に表示されています。ドラッグすると自動的にその会議室と時間帯が選択された状態でフォームが開きます' },
                        { num: '②', action: 'タイトル・参加者を入力', desc: '予定のタイトルと参加者を設定します。参加者に競合予定がある場合は黄色いバナーで通知されます' },
                        { num: '③', action: '「保存」ボタンを押す', desc: '予約が完了し、会議室カラムにブロックが表示されます' },
                    ]" :key="step.num" class="flex items-start gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-700">{{ step.num }}</span>
                        <div>
                            <div class="text-sm font-semibold text-gray-700">{{ step.action }}</div>
                            <div class="text-xs text-gray-500">{{ step.desc }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-teal-100 bg-teal-50 p-3 text-sm">
                        <div class="mb-1 font-semibold text-teal-700">✅ できること</div>
                        <ul class="space-y-1 text-xs text-teal-600">
                            <li>・ 終了時刻と次の予約開始時刻が同じ（連続予約）はOK</li>
                            <li>・ 自分の既存の予定に会議室を後から紐づける</li>
                            <li>・ 予約内容の編集・削除（予約者・Admin のみ）</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-red-100 bg-red-50 p-3 text-sm">
                        <div class="mb-1 font-semibold text-red-700">❌ できないこと</div>
                        <ul class="space-y-1 text-xs text-red-600">
                            <li>・ 他人の予約を編集・削除（閲覧のみ）</li>
                            <li>・ 予約可能時間外の予約</li>
                            <li>・ 時間が重複する予約</li>
                            <li>・ 日をまたいだ予約</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 5. 他の人の予定を表示する（オーバーレイ） -->
            <div id="overlay" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-sm font-bold text-teal-600">5</span>
                    他の人の予定を表示する（オーバーレイ）
                </h2>

                <div class="mb-4 flex gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <span class="text-2xl">👥</span>
                    <div>
                        <div class="mb-1 font-semibold text-teal-700">空き時間の確認に使えます</div>
                        <p class="text-sm text-teal-600">
                            他のメンバーの予定を自分の日ビューに追加表示（オーバーレイ）することで、
                            <strong>その人がいつ空いているか</strong>を一目で確認できます。
                            会議の日程調整や打合せの設定に役立ちます。
                        </p>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">オーバーレイの追加手順</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="step in [
                        { num: '①', action: '右上の「＋ 人を追加」ボタンをクリック', desc: 'オーバーレイパネルが開きます' },
                        { num: '②', action: '追加したい人を選択', desc: '名前で検索するか、会社・部署タブから絞り込んで選択します' },
                        { num: '③', action: '日ビューに新しいカラムが追加される', desc: '追加した人の予定が薄い色で表示されます。自分の予定はフルカラーで表示されます' },
                        { num: '④', action: '設定は自動で保存される', desc: '次回ログイン時も同じ設定が引き継がれます。不要になったら「×」ボタンで削除できます' },
                    ]" :key="step.num" class="flex items-start gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-700">{{ step.num }}</span>
                        <div>
                            <div class="text-sm font-semibold text-gray-700">{{ step.action }}</div>
                            <div class="text-xs text-gray-500">{{ step.desc }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm text-gray-600">
                    <strong>📌 表示されるのは：</strong>
                    相手が「社内公開」以上に設定した予定のみです。「非公開」に設定された予定は表示されません。
                </div>
            </div>

            <!-- 6. 通知について -->
            <div id="notifications" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-sm font-bold text-teal-600">6</span>
                    通知について
                </h2>

                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="item in [
                        { icon: '🌅', title: '朝の予定まとめ通知', desc: '毎朝8時ごろ、その日に予定がある方に通知が届きます。右上のベルアイコンから確認できます。' },
                        { icon: '👤', title: '参加者招待通知', desc: '誰かに予定の参加者として追加されると通知が届きます。招待への承諾・辞退はカレンダーから行えます。' },
                    ]" :key="item.title" class="flex items-start gap-4 border-b border-gray-50 px-4 py-4 last:border-0">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-teal-50 text-xl">{{ item.icon }}</div>
                        <div>
                            <div class="text-sm font-semibold text-gray-700">{{ item.title }}</div>
                            <div class="text-xs text-gray-500">{{ item.desc }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. 権限について -->
            <div id="permissions" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-sm font-bold text-teal-600">7</span>
                    権限について
                </h2>

                <div class="overflow-hidden rounded-xl border border-gray-200">
                    <div class="grid grid-cols-6 bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600">
                        <div class="col-span-2">操作</div>
                        <div class="text-center">User</div>
                        <div class="text-center">Coordinator</div>
                        <div class="text-center">Admin</div>
                        <div class="text-center">SuperAdmin</div>
                    </div>
                    <div v-for="row in [
                        { op: '予定表の閲覧', user: '○', coord: '○', admin: '○', super: '○' },
                        { op: '自分の予定を作成', user: '○', coord: '○', admin: '○', super: '○' },
                        { op: '他者の予定に追加', user: '—', coord: '○', admin: '○', super: '○' },
                        { op: '会議室を予約', user: '○', coord: '○', admin: '○', super: '○' },
                        { op: '自分の予約を編集・削除', user: '○', coord: '○', admin: '○', super: '○' },
                        { op: '他者の予約を編集・削除', user: '—', coord: '—', admin: '○', super: '○' },
                        { op: '会議室マスタ管理', user: '—', coord: '—', admin: '○', super: '○' },
                    ]" :key="row.op"
                        class="grid grid-cols-6 items-center border-t border-gray-100 px-3 py-2.5">
                        <div class="col-span-2 text-xs text-gray-700">{{ row.op }}</div>
                        <div class="text-center text-xs font-medium" :class="row.user === '○' ? 'text-teal-600' : 'text-gray-300'">{{ row.user }}</div>
                        <div class="text-center text-xs font-medium" :class="row.coord === '○' ? 'text-teal-600' : 'text-gray-300'">{{ row.coord }}</div>
                        <div class="text-center text-xs font-medium" :class="row.admin === '○' ? 'text-teal-600' : 'text-gray-300'">{{ row.admin }}</div>
                        <div class="text-center text-xs font-medium" :class="row.super === '○' ? 'text-teal-600' : 'text-gray-300'">{{ row.super }}</div>
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    ※ Coordinator が他者予定に追加できる権限は、Admin設定で変更可能です。
                </p>
            </div>

            <!-- ガイド一覧に戻る -->
            <div class="flex justify-center pb-4 pt-2">
                <Link :href="route('guide.index')" class="inline-flex items-center gap-2 rounded-lg border border-teal-200 bg-white px-5 py-2 text-sm font-medium text-teal-600 shadow-sm hover:bg-teal-50">
                    ← ガイド一覧に戻る
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
