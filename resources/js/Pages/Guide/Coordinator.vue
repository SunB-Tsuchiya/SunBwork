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
                <h2 class="text-xl font-semibold leading-tight text-gray-800">コーディネーター向けガイド</h2>
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
                    <li><a href="#clients" class="hover:underline">3. クライアントを登録する</a></li>
                    <li><a href="#projects" class="hover:underline">4. 案件を登録する</a></li>
                    <li><a href="#jobs" class="hover:underline">5. ジョブを作成してユーザーに割り当てる</a></li>
                    <li><a href="#progress" class="hover:underline">6. 進捗を確認する</a></li>
                    <li><a href="#calendar" class="hover:underline">7. 案件カレンダーで全体を把握する</a></li>
                    <li><a href="#schedule" class="hover:underline">8. スケジュール（ガントチャート）</a></li>
                    <li><a href="#other" class="hover:underline">9. その他の機能</a></li>
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
                            { name: '案件一覧', desc: '担当している案件（プロジェクト）の一覧' },
                            { name: 'ジョブ一覧', desc: 'ユーザーへの割当状況・メッセージの確認' },
                            { name: '案件カレンダー', desc: '全案件をカレンダーで俯瞰表示' },
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

            <!-- 3. クライアント登録 -->
            <div id="clients" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">3</span>
                    クライアントを登録する
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

            <!-- 4. 案件登録 -->
            <div id="projects" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">4</span>
                    案件を登録する
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「案件一覧」タブ → 新規作成</strong>
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-100">
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
                <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">
                    <strong>💡 重複チェックについて：</strong><br />
                    似たタイトルが存在する場合、警告が表示されます。「それでも登録する」か「タイトルを変える」かを選択できます。
                </div>
            </div>

            <!-- 5. ジョブ割り当て -->
            <div id="jobs" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">5</span>
                    ジョブを作成してユーザーに割り当てる
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    <strong>「案件一覧」→ 案件を選択 → 「ジョブ一覧」</strong>
                </div>

                <h3 class="mb-3 font-semibold text-gray-700">入力項目</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="row in [
                        { item: 'タイトル', desc: '作業の名称' },
                        { item: '担当者', desc: '割り当てるユーザー（複数選択可）' },
                        { item: '締め切り日', desc: '希望する完了期限' },
                        { item: '作業種別', desc: '作業の種類（ステージ・サイズ・種別など）' },
                        { item: 'メッセージ', desc: '担当者へのコメント・指示' },
                    ]" :key="row.item" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-28 rounded-md bg-green-50 px-2 py-1 text-center text-sm font-medium text-green-700">{{ row.item }}</span>
                        <span class="text-sm text-gray-600">{{ row.desc }}</span>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
                    <strong>⚠️ 派遣・業務委託のユーザー：</strong>
                    オレンジ色の警告が表示されます（雇用形態の確認を促します）。
                </div>
            </div>

            <!-- 6. 進捗確認 -->
            <div id="progress" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">6</span>
                    進捗を確認する（ジョブ一覧）
                </h2>
                <p class="mb-4 text-gray-600">
                    <strong class="text-green-600">「ジョブ一覧」タブ</strong>で、割り当てた全ジョブの一覧と進捗状況を確認できます。
                </p>
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="s in [
                        { status: '受信済み', badge: 'bg-indigo-100 text-indigo-700', desc: 'ユーザーにまだ確認されていない' },
                        { status: '確認済み', badge: 'bg-green-100 text-green-700', desc: 'ユーザーが内容を確認した' },
                        { status: '進行中', badge: 'bg-indigo-100 text-indigo-700', desc: '作業中' },
                        { status: 'セット済み', badge: 'bg-blue-100 text-blue-700', desc: 'ユーザーが予定表にスケジュールを組んだ' },
                        { status: '完了', badge: 'bg-yellow-100 text-yellow-700', desc: '作業完了' },
                    ]" :key="s.status" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-24 rounded-full px-3 py-1 text-center text-xs font-semibold" :class="s.badge">{{ s.status }}</span>
                        <span class="text-sm text-gray-600">{{ s.desc }}</span>
                    </div>
                </div>
            </div>

            <!-- 7. 案件カレンダー -->
            <div id="calendar" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">7</span>
                    案件カレンダーで全体を把握する
                </h2>
                <p class="mb-3 text-gray-600">
                    <strong class="text-green-600">「案件カレンダー」タブ</strong>では、担当全案件の締め切り・スケジュールをカレンダー上で色分け表示します。
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-green-50 p-4">
                        <div class="mb-1 text-sm font-semibold text-green-700">🎨 12色の自動割り当て</div>
                        <p class="text-sm text-green-600">案件ごとに色が自動割り当てされ、一目で識別できます。</p>
                    </div>
                    <div class="rounded-lg bg-green-50 p-4">
                        <div class="mb-1 text-sm font-semibold text-green-700">✅ 完了済み非表示</div>
                        <p class="text-sm text-green-600">「完了済み案件を非表示」チェックで進行中案件のみ表示できます。</p>
                    </div>
                </div>
            </div>

            <!-- 8. スケジュール -->
            <div id="schedule" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">8</span>
                    スケジュール（ガントチャート）
                </h2>
                <p class="text-gray-600">
                    案件の詳細ページから「スケジュール」を登録でき、ガントチャート形式でプロジェクトの工程を管理できます。
                </p>
            </div>

            <!-- 9. その他 -->
            <div id="other" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-sm text-green-600 font-bold">9</span>
                    その他の機能
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="f in [
                        { icon: '✉️', title: 'メッセージ・チャット', desc: 'ユーザーとのやりとりはメッセージ・チャットで行えます。' },
                        { icon: '🤖', title: 'AI チャット', desc: '案件に関するサポートや文章生成にAIアシスタントを利用できます。' },
                    ]" :key="f.title" class="rounded-xl border border-gray-100 p-4">
                        <div class="mb-2 text-2xl">{{ f.icon }}</div>
                        <div class="mb-1 font-semibold text-gray-700">{{ f.title }}</div>
                        <p class="text-sm text-gray-500">{{ f.desc }}</p>
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
