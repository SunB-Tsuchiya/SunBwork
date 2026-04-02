<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="ユーザー向けガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('guide.index')" class="text-sm text-blue-500 hover:underline">← ガイド一覧</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">ユーザー向けガイド</h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- ヒーローバナー -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-blue-500 to-sky-400 p-8 text-white shadow">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-4xl">📋</div>
                    <div>
                        <div class="mb-1 text-sm font-medium text-blue-100">User Guide</div>
                        <h1 class="text-3xl font-bold">ユーザー向けガイド</h1>
                        <p class="mt-1 text-blue-100">ジョブの受け取り・予定・日報の基本的な使い方</p>
                    </div>
                </div>
            </div>

            <!-- もくじ -->
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-5">
                <h3 class="mb-3 font-semibold text-blue-700">📌 もくじ</h3>
                <ol class="space-y-1 text-sm text-blue-600">
                    <li><a href="#overview" class="hover:underline">1. SunBWork とは</a></li>
                    <li><a href="#flow" class="hover:underline">2. 基本的な使い方の流れ</a></li>
                    <li><a href="#jobbox" class="hover:underline">3. 依頼されたジョブを受け取る</a></li>
                    <li><a href="#myjobbox" class="hover:underline">4. 自分でジョブを登録する</a></li>
                    <li><a href="#calendar" class="hover:underline">5. 予定表にスケジュールを組む</a></li>
                    <li><a href="#diary" class="hover:underline">6. 日報を書く</a></li>
                    <li><a href="#other" class="hover:underline">7. その他の機能</a></li>
                    <li><a href="#settings" class="hover:underline">8. 設定（勤務形態）</a></li>
                </ol>
            </div>

            <!-- 1. SunBWork とは -->
            <div id="overview" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">1</span>
                    SunBWork とは
                </h2>
                <p class="leading-relaxed text-gray-600">
                    SunBWork は、日々の仕事（ジョブ）を管理し、予定を立て、作業を記録するための業務支援システムです。<br />
                    コーディネーターから仕事を受け取るだけでなく、自分で仕事を登録して進めることもできます。
                </p>

                <div class="mt-5 overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">画面上部のタブメニュー</div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tab in [
                            { name: 'マイジョブ', desc: '自分で登録したジョブの一覧' },
                            { name: '依頼されたジョブ', desc: 'コーディネーターから割り当てられたジョブの一覧' },
                            { name: '日報一覧', desc: '作成した日報の履歴' },
                            { name: '予定表', desc: 'カレンダー形式の予定表' },
                            { name: '設定', desc: '勤務形態・表示設定' },
                        ]" :key="tab.name" class="flex items-center gap-4 px-4 py-3">
                            <span class="w-36 rounded-md bg-blue-50 px-2 py-1 text-center text-sm font-medium text-blue-700">{{ tab.name }}</span>
                            <span class="text-sm text-gray-600">{{ tab.desc }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. 基本的な流れ -->
            <div id="flow" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">2</span>
                    基本的な使い方の流れ
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-for="(step, i) in [
                        { icon: '📨', label: 'コーディネーターがジョブを配置' },
                        { icon: '📬', label: '「依頼されたジョブ」に通知が届く' },
                        { icon: '📅', label: '予定表にスケジュールを組む' },
                        { icon: '⚙️', label: '作業を進める' },
                        { icon: '📝', label: '日報に作業内容を記録する' },
                    ]" :key="i" class="flex items-center gap-2">
                        <div class="flex flex-col items-center rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-center">
                            <span class="text-2xl">{{ step.icon }}</span>
                            <span class="mt-1 text-xs text-blue-700">{{ step.label }}</span>
                        </div>
                        <span v-if="i < 4" class="text-gray-300 text-xl">→</span>
                    </div>
                </div>
            </div>

            <!-- 3. 依頼されたジョブ -->
            <div id="jobbox" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">3</span>
                    依頼されたジョブを受け取る
                </h2>
                <p class="mb-4 text-gray-600">
                    コーディネーターからジョブが割り当てられると、<strong class="text-blue-600">「依頼されたジョブ」タブ</strong>に表示されます。
                </p>
                <div class="mb-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-700">
                    <strong>できること：</strong>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li>ジョブの内容・締め切り・担当者コメントを確認する</li>
                        <li>作業が完了したら「完了」ボタンを押す</li>
                    </ul>
                </div>
                <h3 class="mb-2 font-semibold text-gray-700">ステータスの意味</h3>
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="s in [
                        { status: '受信済み', badge: 'bg-indigo-100 text-indigo-700', desc: '割り当てられたがまだ着手していない' },
                        { status: '進行中', badge: 'bg-indigo-100 text-indigo-700', desc: '作業中' },
                        { status: '確認済み', badge: 'bg-green-100 text-green-700', desc: '内容を確認した' },
                        { status: 'セット済み', badge: 'bg-blue-100 text-blue-700', desc: '予定表にスケジュールを組んだ' },
                        { status: '完了', badge: 'bg-yellow-100 text-yellow-700', desc: '作業完了' },
                    ]" :key="s.status" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-24 rounded-full px-3 py-1 text-center text-xs font-semibold" :class="s.badge">{{ s.status }}</span>
                        <span class="text-sm text-gray-600">{{ s.desc }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. マイジョブ -->
            <div id="myjobbox" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">4</span>
                    自分でジョブを登録する（マイジョブ）
                </h2>
                <p class="mb-4 text-gray-600">
                    コーディネーターからの依頼がなくても、自分の作業を自分で登録して管理できます。
                </p>
                <div class="rounded-lg border border-dashed border-blue-200 bg-blue-50 p-4 text-sm">
                    <div class="mb-2 font-semibold text-blue-700">「マイジョブ」タブ → 新規登録</div>
                    <ul class="list-inside list-disc space-y-1 text-blue-600">
                        <li>作業名・案件名</li>
                        <li>締め切り日</li>
                        <li>作業種別・サイズ・ステージ（ワークロード計算に使用）</li>
                        <li>メモ</li>
                    </ul>
                </div>
            </div>

            <!-- 5. 予定表 -->
            <div id="calendar" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">5</span>
                    予定表にスケジュールを組む
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-blue-600">「予定表」タブ</strong>で、カレンダー形式のスケジュール管理ができます。
                </p>
                <div class="mb-4 grid gap-3 sm:grid-cols-3">
                    <div v-for="item in [
                        { icon: '📌', title: 'ジョブ予定を登録', desc: '作業予定をカレンダーに追加' },
                        { icon: '🗓️', title: 'イベント作成', desc: '日付クリックで予定を登録' },
                        { icon: '🏢', title: 'シフト確認', desc: '出勤・在宅・休暇などを確認' },
                    ]" :key="item.title" class="rounded-lg bg-blue-50 p-4 text-center">
                        <div class="mb-2 text-2xl">{{ item.icon }}</div>
                        <div class="mb-1 text-sm font-semibold text-blue-700">{{ item.title }}</div>
                        <div class="text-xs text-blue-500">{{ item.desc }}</div>
                    </div>
                </div>
                <div class="rounded-lg border border-blue-100 p-4 text-sm">
                    <div class="mb-2 font-semibold text-gray-700">📅 スケジュール登録の手順</div>
                    <ol class="list-inside list-decimal space-y-1 text-gray-600">
                        <li>カレンダーの日付をクリックする</li>
                        <li>「イベントを作成」フォームが開く</li>
                        <li>タイトル・時間帯・関連ジョブを入力して保存する</li>
                    </ol>
                </div>
            </div>

            <!-- 6. 日報 -->
            <div id="diary" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">6</span>
                    日報を書く
                </h2>

                <!-- 注目ポイント -->
                <div class="mb-5 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="text-2xl">✨</span>
                    <div>
                        <div class="mb-1 font-semibold text-amber-700">日報を書くとタイムラインが自動表示されます</div>
                        <p class="text-sm text-amber-600">
                            日報を作成すると、<strong>その日に予定表に登録していたスケジュール</strong>がタイムライン形式で自動的に日報の中に表示されます。
                            文章の記録と予定表の記録を同時に確認できます。
                        </p>
                    </div>
                </div>

                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <div class="mb-2 text-sm font-semibold text-gray-700">📝 日報に含まれるもの</div>
                        <ul class="space-y-1 text-sm text-gray-600">
                            <li>・ 本文（Quillエディタで自由記述）</li>
                            <li>・ <strong>その日のタイムライン（カレンダーの予定が自動反映）</strong></li>
                            <li>・ 勤務情報バー（始業・終業・休憩時間）</li>
                            <li>・ コーディネーター・リーダーからのコメント</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <div class="mb-2 text-sm font-semibold text-blue-700">💡 日報を書く習慣のすすめ</div>
                        <p class="text-sm text-blue-600">
                            毎日日報を書くことで、リーダーやコーディネーターに作業進捗が伝わり、評価・分析にも活用されます。
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-dashed border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">
                    <strong>「日報一覧」タブ → 新規作成</strong>　から日報を書き始められます。
                </div>
            </div>

            <!-- 7. その他 -->
            <div id="other" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">7</span>
                    その他の機能
                </h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div v-for="f in [
                        { icon: '✉️', title: 'メッセージ', desc: 'コーディネーターや他ユーザーからのメッセージを受信・返信できます。仕事の依頼（ジョブリクエスト）も届きます。' },
                        { icon: '💬', title: 'チャット', desc: 'チャットルームでリアルタイムに連絡が取れます。' },
                        { icon: '🤖', title: 'AI チャット', desc: 'わからないことをAIアシスタントに質問できます。' },
                    ]" :key="f.title" class="rounded-xl border border-gray-100 p-4">
                        <div class="mb-2 text-2xl">{{ f.icon }}</div>
                        <div class="mb-1 font-semibold text-gray-700">{{ f.title }}</div>
                        <p class="text-sm text-gray-500">{{ f.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- 8. 設定 -->
            <div id="settings" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-sm text-blue-600 font-bold">8</span>
                    設定（勤務形態）
                </h2>
                <p class="text-gray-600">
                    <strong class="text-blue-600">「設定」タブ</strong>で、自分のデフォルト勤務形態（出勤・在宅・夜勤など）を設定できます。
                    設定した勤務形態は予定表のカレンダーに反映されます。
                </p>
            </div>

            <!-- ガイド一覧に戻る -->
            <div class="flex justify-center pt-2 pb-4">
                <Link :href="route('guide.index')" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-5 py-2 text-sm font-medium text-blue-600 shadow-sm hover:bg-blue-50">
                    ← ガイド一覧に戻る
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
