<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="コーディネーター向けガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('guide.index')" class="text-sm text-green-500 hover:underline">← ガイド一覧</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">コーディネーター向けガイド</h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- ヒーローバナー -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-green-600 to-emerald-400 p-8 text-white shadow">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-4xl">🗂️</div>
                    <div>
                        <div class="mb-1 text-sm font-medium text-green-100">Coordinator Guide</div>
                        <h1 class="text-3xl font-bold">コーディネーター向けガイド</h1>
                        <p class="mt-1 text-green-100">案件管理・ジョブ割り当ての進行管理担当者向け説明書</p>
                    </div>
                </div>
            </div>

            <!-- もくじ -->
            <div class="rounded-xl border border-green-100 bg-green-50 p-5">
                <h3 class="mb-3 font-semibold text-green-700">📌 もくじ</h3>
                <ol class="space-y-1 text-sm text-green-600">
                    <li><a href="#role" class="hover:underline">1. コーディネーターの役割</a></li>
                    <li><a href="#flow" class="hover:underline">2. 基本的な使い方の流れ</a></li>
                    <li><a href="#clients" class="hover:underline">3. クライアント管理</a></li>
                    <li><a href="#subcontractors" class="hover:underline">4. 外注先管理</a></li>
                    <li><a href="#projects" class="hover:underline">5. 案件一覧（案件の作成・編集・完了）</a></li>
                    <li><a href="#project-detail" class="hover:underline">6. 案件詳細の使い方</a></li>
                    <li><a href="#jobs" class="hover:underline">7. ジョブ一覧とジョブ管理</a></li>
                    <li><a href="#calendar" class="hover:underline">8. 案件カレンダー</a></li>
                    <li><a href="#progress-sheet" class="hover:underline">9. 進行表一覧</a></li>
                    <li><a href="#progress-report" class="hover:underline">10. 進行レポート</a></li>
                    <li><a href="#settings" class="hover:underline">11. 設定</a></li>
                </ol>
            </div>

            <!-- 1. 役割 -->
            <div id="role" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">1</span>
                    コーディネーターの役割
                </h2>
                <p class="leading-relaxed text-gray-600">
                    コーディネーターは、クライアントからの案件を登録・管理し、必要な作業（ジョブ）を担当ユーザーに割り当てる役割を担います。<br />
                    ユーザーが作業を進められるよう、ジョブの内容・締め切り・優先度を整えて配置することが主な業務です。
                </p>

                <!-- タブメニュー -->
                <div class="mt-5 overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">画面上部のタブメニュー</div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tab in [
                            { name: 'クライアント管理', desc: '取引先（クライアント）の登録・編集' },
                            { name: '外注先管理', desc: '外注先・業務委託先の登録・編集' },
                            { name: '案件一覧', desc: '担当している案件（プロジェクト）の一覧' },
                            { name: 'ジョブ一覧', desc: 'ユーザーへの割当状況・進捗の確認' },
                            { name: '案件カレンダー', desc: '全案件をカレンダーで俯瞰表示' },
                            { name: '進行表一覧', desc: '案件ごとの進行表を一覧表示' },
                            { name: '進行レポート', desc: '案件・ジョブの進捗をレポート形式で確認' },
                            { name: '設定', desc: 'ジョブ一覧のグループ表示方法を設定' },
                        ]" :key="tab.name" class="flex items-center gap-4 px-4 py-3">
                            <span class="w-40 rounded-md bg-green-50 px-2 py-1 text-center text-sm font-medium text-green-700">{{ tab.name }}</span>
                            <span class="text-sm text-gray-600">{{ tab.desc }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. 基本的な流れ -->
            <div id="flow" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">2</span>
                    基本的な使い方の流れ
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-for="(step, i) in [
                        { icon: '🏢', label: 'クライアントを登録する' },
                        { icon: '📁', label: '案件を登録する' },
                        { icon: '📝', label: 'ジョブを作成する' },
                        { icon: '📤', label: 'ユーザーに割り当てる' },
                        { icon: '📊', label: '進捗を確認する' },
                    ]" :key="i" class="flex items-center gap-2">
                        <div class="flex flex-col items-center rounded-xl border border-green-100 bg-green-50 px-4 py-3 text-center">
                            <span class="text-2xl">{{ step.icon }}</span>
                            <span class="mt-1 text-xs text-green-700">{{ step.label }}</span>
                        </div>
                        <span v-if="i < 4" class="text-gray-300 text-xl">→</span>
                    </div>
                </div>
            </div>

            <!-- 3. クライアント管理 -->
            <div id="clients" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">3</span>
                    クライアント管理
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「クライアント管理」タブ → 新規登録</strong>
                </div>
                <ul class="mb-4 list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>クライアント名・担当者・連絡先などを入力します</li>
                    <li>登録時に名前の重複チェックが自動で行われます（同一名はブロック）</li>
                    <li>CSV でまとめて登録することもできます</li>
                </ul>
                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                    <strong>⚠️ 削除について：</strong><br />
                    紐づいている案件がある場合は削除できません。代わりに「統合」機能で別のクライアントに案件を移行できます。
                </div>
            </div>

            <!-- 4. 外注先管理 -->
            <div id="subcontractors" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">4</span>
                    外注先管理
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「外注先管理」タブ</strong>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>外注先・業務委託先の会社情報（名称・担当者・連絡先）を登録・管理します</li>
                    <li>登録した外注先はジョブ作成時の担当者選択で参照できます</li>
                    <li>一覧から編集・削除が可能です</li>
                </ul>
            </div>

            <!-- 5. 案件一覧 -->
            <div id="projects" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">5</span>
                    案件一覧（案件の作成・編集・完了）
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「案件一覧」タブ → 新規作成</strong>
                </div>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="row in [
                        { item: 'クライアント', desc: '登録済みのクライアントを選択' },
                        { item: '案件名', desc: 'プロジェクトのタイトル（重複チェックあり）' },
                        { item: 'サブコーディネーター', desc: '共同で管理するコーディネーターを複数追加可' },
                        { item: 'メモ', desc: '案件に関するノート' },
                    ]" :key="row.item" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-44 rounded-md bg-green-50 px-2 py-1 text-center text-sm font-medium text-green-700">{{ row.item }}</span>
                        <span class="text-sm text-gray-600">{{ row.desc }}</span>
                    </div>
                </div>
                <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">
                    <strong>💡 重複チェックについて：</strong><br />
                    似たタイトルが存在する場合、警告が表示されます。「それでも登録する」か「タイトルを変える」かを選択できます。
                </div>
                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                    <strong>✅ 案件の完了：</strong><br />
                    案件詳細ページの「完了にする」ボタンで案件を完了状態にできます。完了した案件はカレンダー・一覧の「完了済み非表示」フィルターで隠せます。
                </div>
            </div>

            <!-- 6. 案件詳細の使い方 -->
            <div id="project-detail" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">6</span>
                    案件詳細の使い方
                </h2>
                <p class="mb-4 text-gray-600">
                    案件一覧から案件名をクリックすると案件詳細ページに移動します。詳細ページには以下の5つのタブがあります。
                </p>
                <div class="mb-6 overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">案件詳細ページ内のタブ</div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tab in [
                            { name: '概要・メンバー', desc: '案件の基本情報の確認・編集、参加メンバーの管理' },
                            { name: '進行管理表', desc: '各ジョブの進捗状況を表形式で一覧確認' },
                            { name: 'スケジュール', desc: 'スケジュール表（一覧）とカレンダーで工程を登録・管理' },
                            { name: '連携設定', desc: '外部サービスとの連携設定' },
                            { name: 'ジョブ履歴', desc: '案件に紐づく全ジョブの履歴を確認' },
                        ]" :key="tab.name" class="flex items-center gap-4 px-4 py-3">
                            <span class="w-40 rounded-md bg-green-50 px-2 py-1 text-center text-sm font-medium text-green-700">{{ tab.name }}</span>
                            <span class="text-sm text-gray-600">{{ tab.desc }}</span>
                        </div>
                    </div>
                </div>

                <!-- 補足: スケジュールタブの詳細 -->
                <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-5">
                    <h3 class="mb-3 font-semibold text-indigo-700">📅 スケジュールタブの使い方</h3>
                    <p class="mb-4 text-sm text-indigo-800">
                        スケジュールタブでは<strong>スケジュール表（一覧）</strong>と<strong>カレンダー</strong>の2種類の表示で、案件の工程を登録・管理できます。
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <div class="mb-2 font-semibold text-gray-700">📋 スケジュール表</div>
                            <ul class="space-y-1 text-sm text-gray-600">
                                <li>「スケジュール」ボタンを押すと一覧パネルが展開します</li>
                                <li>工程名・開始日・終了日・進捗（%）を一覧で確認できます</li>
                                <li>「編集モード」でスケジュールの追加・編集・削除が可能です</li>
                                <li>締め切り超過は赤色、3日以内は黄色でアラート表示されます</li>
                                <li>CSV出力・CSV取込にも対応しています</li>
                            </ul>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <div class="mb-2 font-semibold text-gray-700">🗓️ カレンダー</div>
                            <ul class="space-y-1 text-sm text-gray-600">
                                <li>スケジュールがカレンダー上にイベントとして表示されます</li>
                                <li>「月カレンダー」「週間プランナー」を切り替えられます</li>
                                <li>未完了のスケジュールは青色、完了済みは緑色で色分けされます</li>
                                <li>カレンダー上からも予定の追加・編集が可能です</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. ジョブ一覧とジョブ管理 -->
            <div id="jobs" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">7</span>
                    ジョブ一覧とジョブ管理
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-green-600">「ジョブ一覧」タブ</strong>では、割り当てた全ジョブの一覧と進捗状況を確認できます。
                </p>

                <h3 class="mb-3 font-semibold text-gray-700">ジョブ作成の入力項目</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="row in [
                        { item: 'タイトル', desc: '作業の名称' },
                        { item: '担当者', desc: '割り当てるユーザー（複数選択可）' },
                        { item: '締め切り日', desc: '希望する完了期限' },
                        { item: '作業種別', desc: '作業の種類（ステージ・サイズ・種別など）' },
                        { item: 'メモ', desc: '担当者へのコメント・指示' },
                    ]" :key="row.item" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-28 rounded-md bg-green-50 px-2 py-1 text-center text-sm font-medium text-green-700">{{ row.item }}</span>
                        <span class="text-sm text-gray-600">{{ row.desc }}</span>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">ジョブのステータス</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="s in [
                        { status: '未読', badge: 'bg-gray-100 text-gray-600', desc: 'ユーザーにまだ確認されていない' },
                        { status: '確認済み', badge: 'bg-green-100 text-green-700', desc: 'ユーザーが内容を確認した' },
                        { status: 'セット済み', badge: 'bg-blue-100 text-blue-700', desc: 'ユーザーがカレンダーにスケジュールを組んだ' },
                        { status: '完了', badge: 'bg-yellow-100 text-yellow-700', desc: '作業完了' },
                    ]" :key="s.status" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-24 rounded-full px-3 py-1 text-center text-xs font-semibold" :class="s.badge">{{ s.status }}</span>
                        <span class="text-sm text-gray-600">{{ s.desc }}</span>
                    </div>
                </div>

                <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">
                    <strong>💡 グループ表示について：</strong><br />
                    「設定」タブでジョブ一覧の並び順を「日付ごと・クライアントごと・案件ごと」に切り替えられます。
                </div>

                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                    <strong>⚠️ 派遣・業務委託のユーザー：</strong>
                    オレンジ色の警告が表示されます（雇用形態の確認を促します）。
                </div>
            </div>

            <!-- 8. 案件カレンダー -->
            <div id="calendar" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">8</span>
                    案件カレンダー
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-green-600">「案件カレンダー」タブ</strong>では、担当している全案件のスケジュールをひとつのカレンダーで俯瞰できます。各案件のスケジュールが期間イベントとして表示されます。
                </p>
                <div class="mb-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-green-50 p-4">
                        <div class="mb-1 text-sm font-semibold text-green-700">🔵 色分け表示</div>
                        <p class="text-sm text-green-600">未完了のスケジュールは青色、完了済みは緑色で表示されます。</p>
                    </div>
                    <div class="rounded-lg bg-green-50 p-4">
                        <div class="mb-1 text-sm font-semibold text-green-700">🔗 案件ごとに詳細へ</div>
                        <p class="text-sm text-green-600">カレンダー上の案件名ボタンをクリックすると、その案件のカレンダー＆スケジュール表ページが開きます。</p>
                    </div>
                </div>

                <!-- 補足: 案件ごとのカレンダー＆スケジュール表 -->
                <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-5">
                    <h3 class="mb-3 font-semibold text-indigo-700">📌 案件ごとのカレンダー＆スケジュール表</h3>
                    <p class="mb-4 text-sm text-indigo-800">
                        案件名ボタンを押した先は<strong>案件詳細のスケジュールタブ</strong>と同一の画面です。スケジュール表とカレンダーの両方が使用できます。
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <div class="mb-2 font-semibold text-gray-700">📋 スケジュール表（一覧）</div>
                            <ul class="space-y-1 text-sm text-gray-600">
                                <li>「スケジュール」ボタンで工程の一覧パネルを展開</li>
                                <li>工程名・開始日・終了日・進捗（%）の確認・編集が可能</li>
                                <li>「編集モード」でスケジュールの追加・削除が可能</li>
                                <li>締め切りアラート（赤：超過 / 黄：3日以内）</li>
                                <li>CSV出力・CSV取込に対応</li>
                            </ul>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <div class="mb-2 font-semibold text-gray-700">🗓️ カレンダー</div>
                            <ul class="space-y-1 text-sm text-gray-600">
                                <li>「月カレンダー」「週間プランナー」を切り替えて確認できます</li>
                                <li>スケジュールが期間イベントとして表示されます</li>
                                <li>未完了は青色、完了済みは緑色で色分けされます</li>
                                <li>カレンダー上からも予定の追加・編集が可能です</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 9. 進行表一覧 -->
            <div id="progress-sheet" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">9</span>
                    進行表一覧
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「進行表一覧」タブ</strong>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>案件ごとの進行表（工程・状態・担当）をまとめて一覧表示します</li>
                    <li>案件名や状態でフィルタリングして目的の進行表にすばやくアクセスできます</li>
                    <li>進行表の詳細ページでは各工程のステータスを更新できます</li>
                </ul>
            </div>

            <!-- 10. 進行レポート -->
            <div id="progress-report" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">10</span>
                    進行レポート
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「進行レポート」タブ</strong>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>案件・ジョブの進捗をレポート形式で確認できます</li>
                    <li>期間・クライアント・担当者などの条件で絞り込み表示ができます</li>
                    <li>各ジョブのステータス（未読・確認済み・セット済み・完了）の集計が確認できます</li>
                </ul>
            </div>

            <!-- 11. 設定 -->
            <div id="settings" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">11</span>
                    設定
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「設定」タブ</strong>
                </div>
                <p class="mb-3 text-sm text-gray-600">ジョブ一覧のグループ表示方法を設定できます。</p>
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="row in [
                        { item: '日付ごと', desc: '締め切り日でグループ化して表示' },
                        { item: 'クライアントごと', desc: 'クライアント名でグループ化して表示' },
                        { item: '案件ごと', desc: '案件名でグループ化して表示' },
                    ]" :key="row.item" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-36 rounded-md bg-green-50 px-2 py-1 text-center text-sm font-medium text-green-700">{{ row.item }}</span>
                        <span class="text-sm text-gray-600">{{ row.desc }}</span>
                    </div>
                </div>
            </div>

            <!-- ガイド一覧に戻る -->
            <div class="flex justify-center pt-2 pb-4">
                <Link :href="route('guide.index')" class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-white px-5 py-2 text-sm font-medium text-green-600 shadow-sm hover:bg-green-50">
                    ← ガイド一覧に戻る
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
