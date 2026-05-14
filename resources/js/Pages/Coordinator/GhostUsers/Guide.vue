<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="テストユーザー 使い方ガイド">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">テストユーザー 使い方ガイド</h2>
        </template>
        <template #tabs>
            <CoordinatorNavigationTabs active="ghost_users" />
        </template>

        <div class="space-y-5">

            <!-- 戻るボタン -->
            <div>
                <button
                    @click="router.get(route('coordinator.ghost_users.index'))"
                    class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                >
                    ← テストユーザー管理に戻る
                </button>
            </div>

            <!-- 概要 -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">テストユーザー（ゴーストユーザー）とは</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Coordinator がユーザー視点の操作（ジョブ受信・進行表からの自己割当・割当完了など）を
                    実際のフローでシミュレートするための専用アカウントです。
                    作成した Coordinator にのみ紐づき、他のユーザーのリストには一切表示されません。
                </p>
                <div class="mt-4 flex flex-wrap gap-3 text-sm">
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-3 py-1 text-green-700 font-medium">
                        上限 1 アカウント
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-yellow-50 px-3 py-1 text-yellow-700 font-medium">
                        作成から 14 日で自動削除
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-blue-700 font-medium">
                        自分だけに見えるアカウント
                    </span>
                </div>
            </div>

            <!-- 使い方ステップ -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <h3 class="mb-4 text-base font-semibold text-gray-800">使い方（手順）</h3>
                <ol class="space-y-4 text-sm text-gray-700">
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">1</span>
                        <div>
                            <p class="font-medium">「テストユーザーを作成」ボタンをクリック</p>
                            <p class="mt-0.5 text-gray-500">テストユーザー管理ページで作成します。アカウントは自動的に「テスト_〔あなたの名前〕」という名前で作られます。</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">2</span>
                        <div>
                            <p class="font-medium">ジョブをテストユーザーに割り当てる</p>
                            <p class="mt-0.5 text-gray-500">ジョブ割り当て画面の担当者選択リストに「<span class="font-semibold text-green-700">[テスト]</span>」ラベル付きで末尾に表示されます。通常ユーザーと同様に割り当てられます。</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">3</span>
                        <div>
                            <p class="font-medium">「ゴーストとして操作する」ボタンをクリック</p>
                            <p class="mt-0.5 text-gray-500">テストユーザー管理ページの操作ボタンでセッションが切り替わります。画面最上部にオレンジのバナーが表示されます。</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">4</span>
                        <div>
                            <p class="font-medium">ユーザー操作をシミュレートする</p>
                            <p class="mt-0.5 text-gray-500">MyJobBox・JobBox・案件一覧・進行表・自己割当などの操作が可能です。実際のユーザーと同じ画面・データが表示されます。</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">5</span>
                        <div>
                            <p class="font-medium">「Coordinator に戻る」ボタンをクリック</p>
                            <p class="mt-0.5 text-gray-500">画面上部のバナーにある「Coordinator に戻る」ボタンでいつでも自分のセッションに戻れます。</p>
                        </div>
                    </li>
                </ol>
            </div>

            <!-- できること / できないこと -->
            <div class="grid gap-5 sm:grid-cols-2">

                <!-- できること -->
                <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                    <h3 class="mb-3 flex items-center gap-2 text-base font-semibold text-gray-800">
                        <span class="text-green-600">✓</span> できること
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-green-600">●</span>
                            <div>
                                <p class="font-medium">MyJobBox（マイジョブボックス）</p>
                                <p class="text-gray-500">自分に届いたジョブの確認・完了操作</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-green-600">●</span>
                            <div>
                                <p class="font-medium">JobBox（ジョブボックス）</p>
                                <p class="text-gray-500">割り当てられたジョブの一覧確認・完了操作</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-green-600">●</span>
                            <div>
                                <p class="font-medium">案件一覧・案件詳細</p>
                                <p class="text-gray-500">チームメンバーとして登録されている案件のみ閲覧可能</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-green-600">●</span>
                            <div>
                                <p class="font-medium">進行表の閲覧</p>
                                <p class="text-gray-500">案件に紐づく進行表の確認</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-green-600">●</span>
                            <div>
                                <p class="font-medium">自己割当（マイジョブ作成）</p>
                                <p class="text-gray-500">進行表のセルから自分でジョブを登録・編集</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- できないこと -->
                <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                    <h3 class="mb-3 flex items-center gap-2 text-base font-semibold text-gray-800">
                        <span class="text-red-500">✕</span> できないこと
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-red-400">●</span>
                            <div>
                                <p class="font-medium">日報・工数入力</p>
                                <p class="text-gray-500">日報の記入および工数の登録はできません</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-red-400">●</span>
                            <div>
                                <p class="font-medium">カレンダー</p>
                                <p class="text-gray-500">スケジュールカレンダーは利用できません</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-red-400">●</span>
                            <div>
                                <p class="font-medium">チャット・メッセージ</p>
                                <p class="text-gray-500">チャット機能は利用できません</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-red-400">●</span>
                            <div>
                                <p class="font-medium">通知</p>
                                <p class="text-gray-500">通知の受信・閲覧はできません</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-red-400">●</span>
                            <div>
                                <p class="font-medium">分析・レポート</p>
                                <p class="text-gray-500">工数分析・月報等の分析機能は利用できません</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex-shrink-0 text-red-400">●</span>
                            <div>
                                <p class="font-medium">プロフィール・設定の変更</p>
                                <p class="text-gray-500">テストユーザーのプロフィールや設定は変更できません</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 注意点 -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <h3 class="mb-4 text-base font-semibold text-gray-800">注意事項</h3>
                <ul class="space-y-3 text-sm text-gray-700">
                    <li class="flex gap-3 rounded-md bg-yellow-50 px-4 py-3">
                        <span class="flex-shrink-0 text-yellow-500 font-bold">!</span>
                        <div>
                            <p class="font-medium text-yellow-800">14 日後に自動削除されます</p>
                            <p class="mt-0.5 text-yellow-700">テストユーザーは作成から 14 日後に自動削除されます。削除時は、そのユーザーに紐づく割り当てジョブ・スケジュールなどの関連データもすべて消去されます。</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-md bg-red-50 px-4 py-3">
                        <span class="flex-shrink-0 text-red-500 font-bold">!</span>
                        <div>
                            <p class="font-medium text-red-800">手動削除するとデータも消えます</p>
                            <p class="mt-0.5 text-red-700">「削除」ボタンで手動削除した場合も、関連データはすべて削除されます。削除前に確認ダイアログが表示されますが、一度削除すると元に戻せません。</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-md bg-blue-50 px-4 py-3">
                        <span class="flex-shrink-0 text-blue-500 font-bold">i</span>
                        <div>
                            <p class="font-medium text-blue-800">自分のアカウントにのみ紐づきます</p>
                            <p class="mt-0.5 text-blue-700">テストユーザーは作成した Coordinator にのみ表示されます。他の Coordinator や Admin・Leader のユーザーリストには一切表示されません。</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-md bg-blue-50 px-4 py-3">
                        <span class="flex-shrink-0 text-blue-500 font-bold">i</span>
                        <div>
                            <p class="font-medium text-blue-800">案件・進行表はチームメンバー登録が必要です</p>
                            <p class="mt-0.5 text-blue-700">テストユーザーが案件や進行表を閲覧・操作するには、事前に Coordinator がその案件のチームメンバーにテストユーザーを追加する必要があります。</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-md bg-blue-50 px-4 py-3">
                        <span class="flex-shrink-0 text-blue-500 font-bold">i</span>
                        <div>
                            <p class="font-medium text-blue-800">割り当て画面では [テスト] ラベルで表示されます</p>
                            <p class="mt-0.5 text-blue-700">ジョブ割り当ての担当者選択時、テストユーザーはリストの末尾に「<span class="font-semibold">[テスト] テスト_〇〇</span>」として表示されます。</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-md bg-gray-50 px-4 py-3">
                        <span class="flex-shrink-0 text-gray-500 font-bold">i</span>
                        <div>
                            <p class="font-medium text-gray-800">1 アカウントが上限です</p>
                            <p class="mt-0.5 text-gray-600">テストユーザーは同時に 1 アカウントのみ作成できます。削除してから新たに作成してください。</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-md bg-gray-50 px-4 py-3">
                        <span class="flex-shrink-0 text-gray-500 font-bold">i</span>
                        <div>
                            <p class="font-medium text-gray-800">ゴーストモード中は上部バナーが表示されます</p>
                            <p class="mt-0.5 text-gray-600">テストユーザーとして操作中は、画面最上部にオレンジ色のバナーが常に表示されます。「Coordinator に戻る」ボタンをクリックするといつでも自分のセッションに戻れます。</p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- 戻るボタン（下） -->
            <div class="pb-2">
                <button
                    @click="router.get(route('coordinator.ghost_users.index'))"
                    class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                >
                    ← テストユーザー管理に戻る
                </button>
            </div>

        </div>
    </AppLayout>
</template>
