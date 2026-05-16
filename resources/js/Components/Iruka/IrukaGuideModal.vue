<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4" @click.self="$emit('close')">
            <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl" @click.stop>

                <!-- ヘッダー -->
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold text-gray-800">
                        <span class="text-xl">🐬</span> イルカボード 使い方ガイド
                    </h2>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="$emit('close')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- タブ -->
                <div class="flex border-b border-gray-200 px-5">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="mr-5 pb-3 pt-3 text-sm font-medium border-b-2 -mb-px transition-colors"
                        :class="activeTab === tab.id
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="activeTab = tab.id"
                    >{{ tab.label }}</button>
                </div>

                <!-- コンテンツ -->
                <div class="max-h-[60vh] overflow-y-auto px-5 py-4 text-sm text-gray-700 space-y-4">

                    <!-- ===== 基本の使い方 ===== -->
                    <div v-show="activeTab === 'basic'">
                        <section class="space-y-3">
                            <div class="flex items-start gap-3 rounded-lg bg-blue-50 p-3">
                                <span class="mt-0.5 text-lg">📋</span>
                                <div>
                                    <p class="font-semibold text-blue-800">イルカボードとは</p>
                                    <p class="mt-1 text-blue-700">チームメンバーの在席状況をリアルタイムで確認できる機能です。30秒ごとに自動更新されます。</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold text-gray-800">🐬 ヘッダーのバッジから</p>
                                <p class="text-gray-600">画面上部の🐬バッジをクリックすると、自分のステータスをすばやく更新できます。</p>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold text-gray-800">👥 他のメンバーのカードから</p>
                                <p class="text-gray-600">ダッシュボードのカードをクリックすると、そのメンバーのステータスを更新できます（全ロール対応）。</p>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold text-gray-800">💬 ひとこと</p>
                                <p class="text-gray-600">自分のステータス更新時のみ、「ひとこと」メモを追加できます。メモはカードに表示されます。</p>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold text-gray-800">🏷️ 部署フィルター</p>
                                <p class="text-gray-600">ボード上部のボタンで部署を絞り込めます。前回の選択は次回も保持されます。</p>
                            </div>

                            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-500">
                                <p class="font-semibold">ステータスの色の見方</p>
                                <div class="mt-1.5 grid grid-cols-2 gap-1">
                                    <span>🟢 在席・テレワーク系</span>
                                    <span>🟡 遅刻・離席系</span>
                                    <span>🔵 会議・打合せ系</span>
                                    <span>🔴 外出・休暇系</span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- ===== カレンダー連携 ===== -->
                    <div v-show="activeTab === 'calendar'">
                        <section class="space-y-3">
                            <div class="flex items-start gap-3 rounded-lg bg-green-50 p-3">
                                <span class="mt-0.5 text-lg">📅</span>
                                <div>
                                    <p class="font-semibold text-green-800">カレンダーと自動連携</p>
                                    <p class="mt-1 text-green-700">カレンダーに登録した予定の種類に応じて、在席ステータスが自動で切り替わります。</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold text-gray-800">🔄 自動切替のタイミング</p>
                                <p class="text-gray-600">在席ボードを誰かが開いたタイミングで、現在時刻のカレンダー予定を確認して自動反映します。</p>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold text-gray-800">📌 イベント種別とステータスの対応</p>
                                <div class="mt-1 overflow-hidden rounded-lg border border-gray-200">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium text-gray-600">カレンダー種別</th>
                                                <th class="px-3 py-2 text-left font-medium text-gray-600">→ ステータス</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr><td class="px-3 py-1.5">会議・Conference</td><td class="px-3 py-1.5 text-blue-600">会議中</td></tr>
                                            <tr><td class="px-3 py-1.5">社内打合せ / クライアント打合せ</td><td class="px-3 py-1.5 text-blue-600">打合せ中</td></tr>
                                            <tr><td class="px-3 py-1.5">外出 / 顧客訪問</td><td class="px-3 py-1.5 text-orange-600">外出中</td></tr>
                                            <tr><td class="px-3 py-1.5">来客対応</td><td class="px-3 py-1.5 text-purple-600">来客対応中</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 rounded-lg bg-yellow-50 p-3 text-xs text-yellow-800">
                                <span class="shrink-0">⚠️</span>
                                <div>
                                    <p class="font-semibold">手動設定が優先されます</p>
                                    <p class="mt-0.5">自分でステータスを手動で変更した場合、カレンダーの自動切替は適用されません。「削除する」でリセットすると、再びカレンダーに従って自動切替されます。</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- ===== 日報自動作成 ===== -->
                    <div v-show="activeTab === 'diary'">
                        <section class="space-y-3">
                            <div class="flex items-start gap-3 rounded-lg bg-indigo-50 p-3">
                                <span class="mt-0.5 text-lg">📝</span>
                                <div>
                                    <p class="font-semibold text-indigo-800">退社時に日報を自動作成</p>
                                    <p class="mt-1 text-indigo-700">「退社」ステータスを選ぶと、その日の日報が自動で作成されます。</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold text-gray-800">⏰ 自動設定される時間</p>
                                <div class="mt-1 overflow-hidden rounded-lg border border-gray-200">
                                    <table class="w-full text-xs">
                                        <tbody class="divide-y divide-gray-100">
                                            <tr>
                                                <td class="bg-gray-50 px-3 py-2 font-medium text-gray-600 w-24">開始時間</td>
                                                <td class="px-3 py-2">勤務形態に設定された定時開始時刻</td>
                                            </tr>
                                            <tr>
                                                <td class="bg-gray-50 px-3 py-2 font-medium text-gray-600">終了時間</td>
                                                <td class="px-3 py-2">「退社」を押した時刻</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 rounded-lg bg-blue-50 p-3 text-xs text-blue-800">
                                <span class="shrink-0">ℹ️</span>
                                <div>
                                    <p class="font-semibold">既存の日報は上書きされません</p>
                                    <p class="mt-0.5">その日すでに日報を作成・提出済みの場合は、自動作成はスキップされます。</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                                <span class="shrink-0">💡</span>
                                <div>
                                    <p class="font-semibold">退社後の確認を忘れずに</p>
                                    <p class="mt-0.5">自動作成された日報の作業内容は空欄です。日報ページから内容を追記・編集してください。</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- フッター -->
                <div class="flex justify-end border-t border-gray-100 px-5 py-3">
                    <button
                        type="button"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200"
                        @click="$emit('close')"
                    >閉じる</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    show: { type: Boolean, default: false },
});

defineEmits(['close']);

const tabs = [
    { id: 'basic',    label: '基本の使い方' },
    { id: 'calendar', label: 'カレンダー連携' },
    { id: 'diary',    label: '日報自動作成' },
];

const activeTab = ref('basic');
</script>
