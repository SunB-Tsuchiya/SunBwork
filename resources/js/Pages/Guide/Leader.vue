<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="リーダー向けガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('guide.index')" class="text-sm text-orange-500 hover:underline">← ガイド一覧</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">リーダー向けガイド</h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- ヒーローバナー -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-orange-500 to-amber-400 p-8 text-white shadow">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-4xl">👑</div>
                    <div>
                        <div class="mb-1 text-sm font-medium text-orange-100">Leader Guide</div>
                        <h1 class="text-3xl font-bold">リーダー向けガイド</h1>
                        <p class="mt-1 text-orange-100">チーム管理・日報確認・作業量分析などの部門リーダー向け説明書</p>
                    </div>
                </div>
            </div>

            <!-- もくじ -->
            <div class="rounded-xl border border-orange-100 bg-orange-50 p-5">
                <h3 class="mb-3 font-semibold text-orange-700">📌 もくじ</h3>
                <ol class="space-y-1 text-sm text-orange-600">
                    <li><a href="#role" class="hover:underline">1. リーダーの役割</a></li>
                    <li><a href="#flow" class="hover:underline">2. 基本的な使い方の流れ</a></li>
                    <li><a href="#users" class="hover:underline">3. ユーザー管理（部署リーダーのみ）</a></li>
                    <li><a href="#project-jobs" class="hover:underline">4. 案件総覧（部署リーダー・Admin以上のみ）</a></li>
                    <li><a href="#teams" class="hover:underline">5. チーム管理</a></li>
                    <li><a href="#clients" class="hover:underline">6. クライアント管理</a></li>
                    <li><a href="#diary" class="hover:underline">7. 日報管理</a></li>
                    <li><a href="#workload" class="hover:underline">8. 作業量分析</a></li>
                    <li><a href="#workload-setting" class="hover:underline">9. 作業項目設定</a></li>
                    <li><a href="#workrecord" class="hover:underline">10. 勤務時間管理</a></li>
                    <li><a href="#dispatch" class="hover:underline">11. 派遣管理</a></li>
                    <li><a href="#permissions" class="hover:underline">12. Leader権限管理</a></li>
                    <li><a href="#meeting" class="hover:underline">13. 会議設定</a></li>
                </ol>
            </div>

            <!-- 1. 役割 -->
            <div id="role" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">1</span>
                    リーダーの役割
                </h2>
                <p class="mb-5 leading-relaxed text-gray-600">
                    リーダーは、所属部署・チームのメンバー管理、日報の確認、作業量の分析を担います。
                    メンバーが適切な環境で働けるよう、チーム構成の整備や権限の付与を行うことも重要な業務です。
                </p>

                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">画面上部のタブメニュー（権限・役割によって異なります）</div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tab in [
                            { name: 'ユーザー管理', cond: '部署リーダーのみ', desc: '部署内ユーザーの登録・編集' },
                            { name: '案件総覧', cond: '部署リーダー・Admin以上', desc: '自部署の案件を一覧・詳細確認（読み取り専用）' },
                            { name: 'チーム管理', cond: '全リーダー', desc: 'チーム・ユニットの構成管理' },
                            { name: 'クライアント管理', cond: '権限あり', desc: '取引先の登録・編集' },
                            { name: '日報管理', cond: '権限あり', desc: '部署内の日報を一覧・確認' },
                            { name: '作業量分析', cond: '権限あり', desc: 'メンバーの作業量・負荷を分析' },
                            { name: '作業項目設定', cond: '権限あり', desc: '作業種別・サイズ・ステージの設定' },
                            { name: '勤務時間管理', cond: '権限あり', desc: '部署の勤務記録の確認・管理' },
                            { name: '派遣管理', cond: '権限あり', desc: '派遣・業務委託メンバーの情報管理' },
                            { name: 'Leader権限管理', cond: '全リーダー', desc: '他リーダーへの権限付与・変更' },
                            { name: '会議設定', cond: '全リーダー', desc: '定例会議の参加メンバー・スケジュール設定' },
                        ]" :key="tab.name" class="flex items-center gap-4 px-4 py-3">
                            <span class="w-36 rounded-md bg-orange-50 px-2 py-1 text-center text-sm font-medium text-orange-700">{{ tab.name }}</span>
                            <span class="w-32 text-xs text-gray-400">{{ tab.cond }}</span>
                            <span class="text-sm text-gray-600">{{ tab.desc }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. 基本の流れ -->
            <div id="flow" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">2</span>
                    基本的な使い方の流れ
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-for="(step, i) in [
                        { icon: '🏗️', label: 'チームとメンバーを整える' },
                        { icon: '📖', label: 'メンバーの日報を確認する' },
                        { icon: '📊', label: '作業量・負荷を分析する' },
                        { icon: '💬', label: 'フィードバック・指示を行う' },
                    ]" :key="i" class="flex items-center gap-2">
                        <div class="flex flex-col items-center rounded-xl border border-orange-100 bg-orange-50 px-4 py-3 text-center">
                            <span class="text-2xl">{{ step.icon }}</span>
                            <span class="mt-1 text-xs text-orange-700">{{ step.label }}</span>
                        </div>
                        <span v-if="i < 3" class="text-xl text-gray-300">→</span>
                    </div>
                </div>
            </div>

            <!-- 3. ユーザー管理 -->
            <div id="users" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-1 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">3</span>
                    ユーザー管理
                </h2>
                <div class="mb-4 text-xs font-medium text-orange-500">※ 部署リーダーのみ表示</div>
                <p class="mb-4 text-gray-600">自部署に所属するメンバーの情報を管理できます。</p>
                <div class="mb-4 grid gap-3 sm:grid-cols-3">
                    <div v-for="item in [
                        { icon: '➕', title: 'メンバー新規登録・編集', desc: '所属部署・チーム・担当の変更' },
                        { icon: '👔', title: '役職・雇用形態の設定', desc: '正社員・派遣・業務委託など' },
                        { icon: '📂', title: 'CSV 一括登録', desc: 'まとめてユーザーを登録できます' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4">
                        <div class="mb-2 text-xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">雇用形態の種類</div>
                    <div v-for="e in [
                        { type: '正社員・契約社員', desc: '日報提出が原則必須' },
                        { type: '派遣・業務委託', desc: '日報提出は任意（設定で変更可）' },
                    ]" :key="e.type" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-36 rounded-md bg-orange-50 px-2 py-1 text-center text-sm font-medium text-orange-700">{{ e.type }}</span>
                        <span class="text-sm text-gray-600">{{ e.desc }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. 案件総覧 -->
            <div id="project-jobs" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-1 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">4</span>
                    案件総覧
                </h2>
                <div class="mb-4 text-xs font-medium text-orange-500">※ 部署リーダー・Admin以上のみ表示</div>
                <p class="mb-4 text-gray-600">
                    <strong class="text-orange-600">「案件総覧」タブ</strong>では、自部署に関係するすべての案件を閲覧できます（読み取り専用）。<br />
                    案件の担当 Coordinator・メンバー・ステータスを確認できます。
                </p>
                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <div v-for="item in [
                        { icon: '📋', title: '案件一覧を見る', desc: '自部署の全案件を一覧表示。担当Co・完了状態を確認できます' },
                        { icon: '🔍', title: '案件詳細を確認', desc: '担当Coordinator・サブCo・チームメンバーを詳細ページで確認できます' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4">
                        <div class="mb-2 text-xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                    <strong>📊 進行管理表について：</strong>
                    <span class="mt-1 block">
                        Leader 自身がメンバーとして案件に関わっている場合は、User エリアの「案件確認」タブから進行管理表を直接確認できます。
                    </span>
                </div>
            </div>

            <!-- 5. チーム管理 -->
            <div id="teams" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">5</span>
                    チーム管理
                </h2>
                <p class="mb-4 text-gray-600">部署内のチーム・ユニット構成を管理できます。</p>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">チームの種類</div>
                    <div v-for="t in [
                        { type: '部門チーム', desc: '部署全体を束ねるチーム' },
                        { type: 'ユニット', desc: '部門内の小グループ' },
                    ]" :key="t.type" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-28 rounded-md bg-orange-50 px-2 py-1 text-center text-sm font-medium text-orange-700">{{ t.type }}</span>
                        <span class="text-sm text-gray-600">{{ t.desc }}</span>
                    </div>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>チーム・ユニットの新規作成・編集</li>
                    <li>メンバーの追加・削除</li>
                    <li>サブリーダーの設定（担当チームのみ管理権限を持つ）</li>
                </ul>
            </div>

            <!-- 6. クライアント管理 -->
            <div id="clients" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">6</span>
                    クライアント管理
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-orange-600">「クライアント管理」タブ</strong>では、取引先（クライアント）の情報を管理できます。
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="item in [
                        { icon: '➕', title: '新規登録・編集', desc: 'クライアント名・連絡先などの登録・更新' },
                        { icon: '🗑️', title: '削除', desc: '案件が紐づいていない場合のみ削除可能' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4">
                        <div class="mb-2 text-xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 7. 日報管理 -->
            <div id="diary" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">7</span>
                    日報管理
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-orange-600">「日報管理」タブ</strong>では、部署内の全メンバーの日報を一覧で確認できます。
                </p>

                <div class="mb-5 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="text-2xl">✨</span>
                    <div>
                        <div class="mb-1 font-semibold text-amber-700">日報にはその日のカレンダータイムラインが自動表示されます</div>
                        <p class="text-sm text-amber-600">
                            日報を開くと、そのメンバーが<strong>その日にカレンダーに登録していたスケジュール</strong>がタイムライン形式で自動表示されます。
                            文章の記録とカレンダーの記録を同時に把握でき、作業実態を正確に確認できます。
                        </p>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">確認できる内容</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="item in [
                        { icon: '📝', title: '本文', desc: 'メンバーが記述した作業記録' },
                        { icon: '📅', title: 'タイムライン', desc: 'カレンダーの予定が自動反映（当日の作業スケジュール）' },
                        { icon: '⏰', title: '勤務情報バー', desc: '始業・終業・休憩時間' },
                        { icon: '💬', title: 'コメント', desc: 'リーダーからフィードバックを追加できます' },
                    ]" :key="item.title" class="rounded-xl border border-orange-50 bg-orange-50 p-4">
                        <div class="mb-1 text-xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-orange-700">{{ item.title }}</div>
                        <p class="text-xs text-orange-600">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 8. 作業量分析 -->
            <div id="workload" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">8</span>
                    作業量分析
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-orange-600">「作業量分析」タブ</strong>では、メンバーごとの作業量・負荷をスコアで可視化します。
                </p>

                <h3 class="mb-3 font-semibold text-gray-700">スコアの見方</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="s in [
                        { metric: '総合ポイント', range: '0〜600', desc: '6カテゴリの合計スコア' },
                        { metric: 'パーセンタイル', range: '0〜100', desc: '同職種内での相対位置' },
                        { metric: '偏差値（参考）', range: '〜', desc: '全社を母集団とした分布上の位置' },
                    ]" :key="s.metric" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-32 rounded-md bg-orange-50 px-2 py-1 text-center text-sm font-medium text-orange-700">{{ s.metric }}</span>
                        <span class="w-16 text-xs text-gray-400">{{ s.range }}</span>
                        <span class="text-sm text-gray-600">{{ s.desc }}</span>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">6つのカテゴリ</h3>
                <div class="mb-4 grid gap-2 sm:grid-cols-3">
                    <div v-for="c in ['ステージ（作業のフェーズ）', 'サイズ（作業量の大きさ）', '種別（作業の種類）', '難易度（ページ単位）', 'イベント（会議・外出など）', '残業（通常・超過）']"
                        :key="c" class="rounded-lg bg-orange-50 px-3 py-2 text-center text-xs text-orange-700">
                        {{ c }}
                    </div>
                </div>

                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">
                    <strong>💡 活用例：</strong><br />
                    特定のメンバーの負荷が高い場合は、ジョブの再分配やフォローを検討してください。
                </div>
            </div>

            <!-- 9. 作業項目設定 -->
            <div id="workload-setting" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">9</span>
                    作業項目設定
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-orange-600">「作業項目設定」タブ</strong>では、作業量分析に使用する項目（作業種別・サイズ・ステージ）を設定・編集できます。
                </p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="item in [
                        { icon: '🏷️', title: '作業種別', desc: '組版・校正・デザインなど作業の種類を定義' },
                        { icon: '📐', title: 'サイズ', desc: '作業量の大きさの区分（小・中・大など）' },
                        { icon: '🔄', title: 'ステージ', desc: '初校・再校・校了など作業フェーズを定義' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4 text-center">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 10. 勤務時間管理 -->
            <div id="workrecord" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">10</span>
                    勤務時間管理
                </h2>
                <p class="text-gray-600">
                    <strong class="text-orange-600">「勤務時間管理」タブ</strong>では、部署内の勤務記録（始業・終業・休憩）を確認・管理できます。
                </p>
            </div>

            <!-- 11. 派遣管理 -->
            <div id="dispatch" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">11</span>
                    派遣管理
                </h2>
                <p class="text-gray-600">
                    <strong class="text-orange-600">「派遣管理」タブ</strong>では、派遣・業務委託メンバーの詳細情報（派遣会社・契約期間など）を管理できます。
                </p>
            </div>

            <!-- 12. Leader権限管理 -->
            <div id="permissions" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">12</span>
                    Leader権限管理
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-orange-600">「Leader権限管理」タブ</strong>では、同じリーダーロールを持つメンバーの権限を管理できます。
                </p>
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">設定できる権限フラグ</div>
                    <div v-for="p in [
                        { flag: 'ユーザー管理', desc: '部署ユーザーの登録・編集' },
                        { flag: 'クライアント管理', desc: '取引先の管理' },
                        { flag: '日報管理', desc: '部署の日報確認・コメント' },
                        { flag: '作業量分析', desc: '作業量スコアの閲覧' },
                        { flag: '作業項目設定', desc: '作業種別・サイズなどの編集' },
                        { flag: '勤務時間管理', desc: '勤務記録の閲覧・編集' },
                        { flag: '派遣管理', desc: '派遣・業務委託情報の管理' },
                    ]" :key="p.flag" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-36 rounded-md bg-orange-50 px-2 py-1 text-center text-sm font-medium text-orange-700">{{ p.flag }}</span>
                        <span class="text-sm text-gray-600">{{ p.desc }}</span>
                    </div>
                </div>
            </div>

            <!-- 13. 会議設定 -->
            <div id="meeting" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-sm font-bold text-orange-600">13</span>
                    会議設定
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-orange-600">「会議設定」タブ</strong>では、定例会議の参加メンバーとスケジュールを設定できます。
                    設定した会議は参加メンバーのカレンダーに自動反映されます。
                </p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="item in [
                        { icon: '📋', title: '会議の登録・編集', desc: '会議名・曜日・時間・繰り返し設定' },
                        { icon: '👥', title: 'メンバー選択', desc: '部署・担当での絞り込みで参加者を選択' },
                        { icon: '📅', title: 'カレンダー自動反映', desc: '参加メンバーのカレンダーに会議予定が自動表示' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4 text-center">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- ガイド一覧に戻る -->
            <div class="flex justify-center pb-4 pt-2">
                <Link :href="route('guide.index')" class="inline-flex items-center gap-2 rounded-lg border border-orange-200 bg-white px-5 py-2 text-sm font-medium text-orange-600 shadow-sm hover:bg-orange-50">
                    ← ガイド一覧に戻る
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
