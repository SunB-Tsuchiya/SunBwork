<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="管理者向けガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('guide.index')" class="text-sm text-red-500 hover:underline">← ガイド一覧</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">管理者向けガイド（Admin）</h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- ヒーローバナー -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-red-600 to-rose-400 p-8 text-white shadow">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-4xl">🛡️</div>
                    <div>
                        <div class="mb-1 text-sm font-medium text-red-100">Admin Guide</div>
                        <h1 class="text-3xl font-bold">管理者向けガイド</h1>
                        <p class="mt-1 text-red-100">ユーザー・部署・日報・分析など、会社全体の管理機能の説明書</p>
                    </div>
                </div>
            </div>

            <!-- もくじ -->
            <div class="rounded-xl border border-red-100 bg-red-50 p-5">
                <h3 class="mb-3 font-semibold text-red-700">📌 もくじ</h3>
                <ol class="space-y-1 text-sm text-red-600">
                    <li><a href="#role" class="hover:underline">1. Adminの役割</a></li>
                    <li><a href="#flow" class="hover:underline">2. 基本的な使い方の流れ</a></li>
                    <li><a href="#project-jobs" class="hover:underline">3. 案件総覧</a></li>
                    <li><a href="#company" class="hover:underline">4. 会社管理</a></li>
                    <li><a href="#users" class="hover:underline">5. ユーザー管理</a></li>
                    <li><a href="#teams" class="hover:underline">6. 部署管理</a></li>
                    <li><a href="#diary" class="hover:underline">7. 日報管理</a></li>
                    <li><a href="#clients" class="hover:underline">8. クライアント管理</a></li>
                    <li><a href="#workload" class="hover:underline">9. 作業量分析</a></li>
                    <li><a href="#worktype" class="hover:underline">10. 勤務形態設定</a></li>
                    <li><a href="#workrecord" class="hover:underline">11. 勤務時間管理</a></li>
                    <li><a href="#admin-permissions" class="hover:underline">12. Admin権限管理（代表Adminのみ）</a></li>
                    <li><a href="#leader-permissions" class="hover:underline">13. Leader権限管理</a></li>
                    <li><a href="#meeting" class="hover:underline">14. 会議設定</a></li>
                </ol>
            </div>

            <!-- 1. 役割 -->
            <div id="role" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">1</span>
                    Adminの役割
                </h2>
                <p class="mb-5 leading-relaxed text-gray-600">
                    Adminは、会社・部署・ユーザーの全体を管理する権限を持ちます。
                    SuperAdminから権限を委譲された<strong>代表Admin（筆頭管理者）</strong>と、権限フラグで制限された一般Adminの2種類があります。
                </p>

                <!-- タブ一覧 -->
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">画面上部のタブメニュー（権限によって異なります）</div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tab in [
                            { name: '案件総覧', cond: '全Admin', desc: '全案件の一覧・状況確認' },
                            { name: '会社管理', cond: '権限あり', desc: '会社情報の編集' },
                            { name: 'ユーザー管理', cond: '権限あり', desc: '全ユーザーの登録・編集・削除' },
                            { name: '部署管理', cond: '権限あり', desc: '部署・チーム構成の管理' },
                            { name: '日報管理', cond: '権限あり', desc: '全社の日報を一覧・確認' },
                            { name: 'クライアント管理', cond: '権限あり', desc: '取引先の登録・編集' },
                            { name: '作業量分析', cond: '権限あり', desc: '全社・部署・チームの作業量分析' },
                            { name: '勤務形態設定', cond: '権限あり', desc: '出勤・在宅・夜勤などのシフト定義' },
                            { name: '勤務時間管理', cond: '権限あり', desc: '全社の勤務記録の確認・管理' },
                            { name: 'Admin権限管理', cond: '代表Adminのみ', desc: '他Adminへの権限フラグ付与・変更' },
                            { name: 'Leader権限管理', cond: '全Admin', desc: 'Leaderへの権限フラグ付与・変更' },
                            { name: '会議設定', cond: '全Admin', desc: '定例会議の参加メンバー・スケジュール設定' },
                        ]" :key="tab.name" class="flex items-center gap-4 px-4 py-3">
                            <span class="w-36 rounded-md bg-red-50 px-2 py-1 text-center text-sm font-medium text-red-700">{{ tab.name }}</span>
                            <span class="w-32 text-xs text-gray-400">{{ tab.cond }}</span>
                            <span class="text-sm text-gray-600">{{ tab.desc }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. 基本の流れ -->
            <div id="flow" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">2</span>
                    基本的な使い方の流れ
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-for="(step, i) in [
                        { icon: '🏢', label: '会社情報を整える' },
                        { icon: '🏗️', label: '部署・チームを構成する' },
                        { icon: '👥', label: 'ユーザーを登録・編集する' },
                        { icon: '🔑', label: 'Leader権限を付与する' },
                        { icon: '📊', label: '運用状況を確認する' },
                    ]" :key="i" class="flex items-center gap-2">
                        <div class="flex flex-col items-center rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-center">
                            <span class="text-2xl">{{ step.icon }}</span>
                            <span class="mt-1 text-xs text-red-700">{{ step.label }}</span>
                        </div>
                        <span v-if="i < 4" class="text-xl text-gray-300">→</span>
                    </div>
                </div>
            </div>

            <!-- 3. 案件総覧 -->
            <div id="project-jobs" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">3</span>
                    案件総覧
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-red-600">「案件総覧」タブ</strong>では、全社の案件を一覧で確認できます。
                </p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="item in [
                        { icon: '📋', title: '全案件の一覧', desc: '進行中・完了済みを含む全案件を確認' },
                        { icon: '🔍', title: '案件詳細の閲覧', desc: '各案件の進行状況・メンバー・ジョブ履歴を確認' },
                        { icon: '📊', title: 'ステータス把握', desc: 'クライアント・担当者・進捗をまとめて把握' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4 text-center">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 4. 会社管理 -->
            <div id="company" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">4</span>
                    会社管理
                </h2>
                <p class="mb-3 text-gray-600">自社の基本情報を管理します。</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="item in [
                        { icon: '🏢', title: '会社情報の編集', desc: '会社名・所在地・連絡先などの更新' },
                        { icon: '🏷️', title: 'APP_NAMEの反映', desc: 'ブラウザタブの表示名に反映されます' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 5. ユーザー管理 -->
            <div id="users" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">5</span>
                    ユーザー管理
                </h2>
                <p class="mb-4 text-gray-600">全社のユーザーを一元管理できます。</p>
                <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in [
                        { icon: '➕', title: '新規登録', desc: '名前・メール・役職・雇用形態を設定' },
                        { icon: '✏️', title: '情報編集', desc: 'ロール・所属・担当などを変更' },
                        { icon: '🗑️', title: '削除', desc: 'データ確認後に削除' },
                        { icon: '📂', title: 'CSV一括登録', desc: 'まとめて大量登録' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4 text-center">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">ロールの種類</h3>
                <div class="mb-5 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="r in [
                        { role: 'SuperAdmin', badge: 'bg-yellow-100 text-yellow-700', desc: 'システム全体の最高権限者' },
                        { role: 'Admin', badge: 'bg-red-100 text-red-700', desc: '会社単位の管理者（このガイドの対象）' },
                        { role: 'Leader', badge: 'bg-orange-100 text-orange-700', desc: '部署・チームのリーダー' },
                        { role: 'Coordinator', badge: 'bg-green-100 text-green-700', desc: '案件・ジョブの進行管理担当者' },
                        { role: 'Proof Admin', badge: 'bg-pink-100 text-pink-700', desc: '校正ジョブの管理担当者' },
                        { role: 'User', badge: 'bg-blue-100 text-blue-700', desc: '一般作業ユーザー' },
                    ]" :key="r.role" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-28 rounded-full px-3 py-1 text-center text-xs font-semibold" :class="r.badge">{{ r.role }}</span>
                        <span class="text-sm text-gray-600">{{ r.desc }}</span>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">雇用形態</h3>
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="e in [
                        { type: '正社員・契約社員', desc: '日報提出が原則必須' },
                        { type: '派遣・業務委託', desc: '日報提出は任意（設定で変更可）' },
                    ]" :key="e.type" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-40 rounded-md bg-red-50 px-2 py-1 text-center text-sm font-medium text-red-700">{{ e.type }}</span>
                        <span class="text-sm text-gray-600">{{ e.desc }}</span>
                    </div>
                </div>
            </div>

            <!-- 6. 部署管理 -->
            <div id="teams" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">6</span>
                    部署管理
                </h2>
                <p class="mb-4 text-gray-600">部署・チーム・ユニットの構成を管理できます。</p>

                <div class="mb-5 rounded-xl border border-gray-100 bg-gray-50 p-5">
                    <div class="mb-3 text-sm font-semibold text-gray-500">組織の階層構造</div>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">会社</span>
                        </div>
                        <div class="ml-6 flex items-center gap-2">
                            <span class="text-gray-300">└</span>
                            <span class="rounded bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-700">部署（Department）</span>
                        </div>
                        <div class="ml-12 flex items-center gap-2">
                            <span class="text-gray-300">└</span>
                            <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">チーム（部門チーム / ユニット）</span>
                        </div>
                    </div>
                </div>

                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>部署・チーム・ユニットの新規作成・編集・削除</li>
                    <li>チームへのメンバー追加・削除</li>
                    <li>サブリーダーの設定</li>
                </ul>
            </div>

            <!-- 7. 日報管理 -->
            <div id="diary" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">7</span>
                    日報管理
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-red-600">「日報管理」タブ</strong>では、全社のメンバーの日報を一覧で確認できます。
                </p>

                <div class="mb-5 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="text-2xl">✨</span>
                    <div>
                        <div class="mb-1 font-semibold text-amber-700">日報にはその日のカレンダータイムラインが自動表示されます</div>
                        <p class="text-sm text-amber-600">
                            日報を開くと、そのメンバーが<strong>その日にカレンダーに登録していたスケジュール</strong>がタイムライン形式で自動表示されます。
                            文章の記録とカレンダーの記録を同時に把握でき、勤務実態を正確に確認できます。
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="item in [
                        { icon: '📝', title: '本文', desc: 'メンバーが記述した作業記録' },
                        { icon: '📅', title: 'タイムライン', desc: 'カレンダーの予定が自動反映（当日の作業スケジュール）' },
                        { icon: '⏰', title: '勤務情報バー', desc: '始業・終業・休憩時間' },
                        { icon: '💬', title: 'コメント', desc: '管理者からフィードバックを追加できます' },
                    ]" :key="item.title" class="rounded-xl border border-red-50 bg-red-50 p-4">
                        <div class="mb-1 text-xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-red-700">{{ item.title }}</div>
                        <p class="text-xs text-red-600">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 8. クライアント管理 -->
            <div id="clients" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">8</span>
                    クライアント管理
                </h2>
                <p class="mb-4 text-gray-600">全社の取引先を管理できます。</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="item in [
                        { icon: '➕', title: '新規登録・編集', desc: '重複チェック付き。類似名は自動検知してブロック' },
                        { icon: '🔗', title: '統合機能', desc: '複数クライアントを1つに統合し、案件・ジョブを移行' },
                        { icon: '🗑️', title: '削除', desc: '案件が紐づいていない場合のみ削除可能' },
                        { icon: '📂', title: 'CSV一括登録', desc: 'まとめて大量登録できます' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4">
                        <div class="mb-2 text-xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 9. 作業量分析 -->
            <div id="workload" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">9</span>
                    作業量分析
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-red-600">「作業量分析」タブ</strong>では、全社・部署・チームの作業量・負荷をスコアで可視化します。
                </p>

                <h3 class="mb-3 font-semibold text-gray-700">スコアの見方</h3>
                <div class="mb-5 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="s in [
                        { metric: '総合ポイント', range: '0〜600', desc: '6カテゴリの合計スコア' },
                        { metric: 'パーセンタイル', range: '0〜100', desc: '同職種内での相対位置' },
                        { metric: '偏差値（参考）', range: '〜', desc: '全社を母集団とした分布上の位置' },
                    ]" :key="s.metric" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-32 rounded-md bg-red-50 px-2 py-1 text-center text-sm font-medium text-red-700">{{ s.metric }}</span>
                        <span class="w-16 text-xs text-gray-400">{{ s.range }}</span>
                        <span class="text-sm text-gray-600">{{ s.desc }}</span>
                    </div>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">6つのカテゴリ</h3>
                <div class="mb-4 grid gap-2 sm:grid-cols-3">
                    <div v-for="c in ['ステージ（作業のフェーズ）', 'サイズ（作業量の大きさ）', '種別（作業の種類）', '難易度（ページ単位）', 'イベント（会議・外出など）', '残業（通常・超過）']"
                        :key="c" class="rounded-lg bg-red-50 px-3 py-2 text-center text-xs text-red-700">
                        {{ c }}
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="item in [
                        { icon: '🏢', title: '全社一覧', desc: '全メンバーの作業量を一覧表示' },
                        { icon: '🏆', title: '職種別ランキング', desc: '職種ごとのスコアランキング' },
                        { icon: '🔍', title: '個人詳細分析', desc: '期間を指定して個人を深掘り' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4 text-center">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 10. 勤務形態設定 -->
            <div id="worktype" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">10</span>
                    勤務形態設定
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-red-600">「勤務形態設定」タブ</strong>では、出勤・在宅・夜勤などのシフト種別を定義します。
                </p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="item in [
                        { icon: '⏰', title: '開始・終了時刻', desc: '各シフトの始業・終業時刻を設定' },
                        { icon: '📅', title: 'カレンダー反映', desc: 'ユーザーのカレンダーに即時反映' },
                        { icon: '🌙', title: '夜勤対応', desc: '16時以降開始のシフトは翌6時まで表示' },
                    ]" :key="item.title" class="rounded-xl border border-gray-100 p-4 text-center">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-gray-700">{{ item.title }}</div>
                        <p class="text-xs text-gray-500">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 11. 勤務時間管理 -->
            <div id="workrecord" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">11</span>
                    勤務時間管理
                </h2>
                <p class="text-gray-600">
                    <strong class="text-red-600">「勤務時間管理」タブ</strong>では、全社の勤務記録（始業・終業・休憩）を確認・管理できます。
                </p>
            </div>

            <!-- 12. Admin権限管理 -->
            <div id="admin-permissions" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-1 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">12</span>
                    Admin権限管理
                </h2>
                <div class="mb-4 text-xs font-medium text-red-500">※ 代表Admin（筆頭管理者）のみ表示</div>
                <p class="mb-4 text-gray-600">
                    同じAdminロールを持つユーザーの権限フラグを管理できます。
                </p>
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">設定できる権限フラグ</div>
                    <div v-for="p in [
                        { flag: '会社管理', desc: '会社情報の閲覧・編集' },
                        { flag: 'ユーザー管理', desc: 'ユーザーの登録・編集・削除' },
                        { flag: '部署管理', desc: '部署・チーム構成の管理' },
                        { flag: '日報管理', desc: '全社の日報確認・コメント' },
                        { flag: 'クライアント管理', desc: '取引先の管理' },
                        { flag: '作業量分析', desc: '作業量スコアの閲覧' },
                        { flag: '勤務形態設定', desc: 'シフト種別の定義・編集' },
                        { flag: '勤務時間管理', desc: '勤務記録の閲覧・編集' },
                    ]" :key="p.flag" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-36 rounded-md bg-red-50 px-2 py-1 text-center text-sm font-medium text-red-700">{{ p.flag }}</span>
                        <span class="text-sm text-gray-600">{{ p.desc }}</span>
                    </div>
                </div>
            </div>

            <!-- 13. Leader権限管理 -->
            <div id="leader-permissions" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">13</span>
                    Leader権限管理
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-red-600">「Leader権限管理」タブ</strong>では、Leaderロールを持つユーザーの権限フラグを管理できます。
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

            <!-- 14. 会議設定 -->
            <div id="meeting" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600">14</span>
                    会議設定
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-red-600">「会議設定」タブ</strong>では、定例会議の参加メンバーとスケジュールを設定できます。
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
                <Link :href="route('guide.index')" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-5 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50">
                    ← ガイド一覧に戻る
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
