<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="校正管理者向けガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('guide.index')" class="text-sm text-pink-500 hover:underline">← ガイド一覧</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">校正管理者向けガイド</h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- ヒーローバナー -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-pink-500 to-rose-400 p-8 text-white shadow">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-4xl">🖊️</div>
                    <div>
                        <div class="mb-1 text-sm font-medium text-pink-100">Proof Admin Guide</div>
                        <h1 class="text-3xl font-bold">校正管理者向けガイド</h1>
                        <p class="mt-1 text-pink-100">校正依頼の受付・担当割り当て・チーム管理を行う校正管理者向け説明書</p>
                    </div>
                </div>
            </div>

            <!-- もくじ -->
            <div class="rounded-xl border border-pink-100 bg-pink-50 p-5">
                <h3 class="mb-3 font-semibold text-pink-700">📌 もくじ</h3>
                <ol class="space-y-1 text-sm text-pink-600">
                    <li><a href="#role" class="hover:underline">1. 校正管理者（ProofAdmin）の役割</a></li>
                    <li><a href="#inbox" class="hover:underline">2. 校正依頼受信</a></li>
                    <li><a href="#jobs" class="hover:underline">3. ジョブ管理</a></li>
                    <li><a href="#calendar" class="hover:underline">4. 校正カレンダー</a></li>
                    <li><a href="#workload" class="hover:underline">5. 校正員作業量</a></li>
                    <li><a href="#team" class="hover:underline">6. 校正チーム管理</a></li>
                    <li><a href="#dispatchers" class="hover:underline">7. 単発派遣管理</a></li>
                </ol>
            </div>

            <!-- 1. 役割 -->
            <div id="role" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-100 text-sm text-pink-600 font-bold">1</span>
                    校正管理者（ProofAdmin）の役割
                </h2>
                <p class="mb-5 leading-relaxed text-gray-600">
                    校正管理者は、コーディネーターや案件担当者から届く校正依頼を受け付け、校正チームのメンバーに作業を割り当てる役割を担います。<br />
                    校正ジョブの進捗管理、チームの稼働状況確認、単発派遣スタッフの管理が主な業務です。
                </p>

                <!-- タブメニュー -->
                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">画面上部のタブメニュー</div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tab in [
                            { name: '校正依頼受信', desc: '届いた校正依頼の確認・受付・担当割り当て（未対応件数バッジあり）' },
                            { name: 'ジョブ管理', desc: '校正ジョブの一覧・ステータス管理' },
                            { name: '校正カレンダー', desc: '校正スケジュールをカレンダーで確認' },
                            { name: '校正員作業量', desc: '各校正メンバーの稼働状況・作業量の確認' },
                            { name: '校正チーム管理', desc: '校正チームのメンバー登録・管理' },
                            { name: '単発派遣管理', desc: '単発・短期の派遣スタッフの登録・管理' },
                        ]" :key="tab.name" class="flex items-center gap-4 px-4 py-3">
                            <span class="w-40 rounded-md bg-pink-50 px-2 py-1 text-center text-sm font-medium text-pink-700">{{ tab.name }}</span>
                            <span class="text-sm text-gray-600">{{ tab.desc }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. 校正依頼受信 -->
            <div id="inbox" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-100 text-sm text-pink-600 font-bold">2</span>
                    校正依頼受信
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-pink-200 bg-pink-50 p-3 text-sm text-pink-700">
                    <strong>「校正依頼受信」タブ</strong>
                </div>
                <p class="mb-4 text-gray-600">
                    コーディネーターや案件担当者から送られた校正依頼が一覧表示されます。タブ名の横に<strong>未対応件数バッジ（赤）</strong>が表示されるため、対応漏れを防げます。
                </p>

                <h3 class="mb-3 font-semibold text-gray-700">基本的な操作フロー</h3>
                <div class="mb-5 flex flex-wrap items-center gap-2">
                    <div v-for="(step, i) in [
                        { icon: '📬', label: '依頼を受信' },
                        { icon: '👀', label: '内容を確認' },
                        { icon: '👤', label: '担当者を割り当て' },
                        { icon: '✅', label: '受付完了' },
                    ]" :key="i" class="flex items-center gap-2">
                        <div class="flex flex-col items-center rounded-xl border border-pink-100 bg-pink-50 px-4 py-3 text-center">
                            <span class="text-2xl">{{ step.icon }}</span>
                            <span class="mt-1 text-xs text-pink-700">{{ step.label }}</span>
                        </div>
                        <span v-if="i < 3" class="text-gray-300 text-xl">→</span>
                    </div>
                </div>

                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>依頼の詳細（作業内容・締め切り・ファイル）を確認できます</li>
                    <li>校正チームのメンバーを担当者として割り当てます</li>
                    <li>受付後はジョブ管理タブで進捗を追跡できます</li>
                </ul>
            </div>

            <!-- 3. ジョブ管理 -->
            <div id="jobs" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-100 text-sm text-pink-600 font-bold">3</span>
                    ジョブ管理
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-pink-200 bg-pink-50 p-3 text-sm text-pink-700">
                    <strong>「ジョブ管理」タブ</strong>
                </div>
                <p class="mb-4 text-gray-600">
                    担当者に割り当てた校正ジョブの一覧と進捗状況を管理します。
                </p>

                <h3 class="mb-3 font-semibold text-gray-700">ジョブのステータス</h3>
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-100">
                    <div v-for="s in [
                        { status: '未読', badge: 'bg-gray-100 text-gray-600', desc: '担当者にまだ確認されていない' },
                        { status: '確認済み', badge: 'bg-green-100 text-green-700', desc: '担当者が内容を確認した' },
                        { status: 'セット済み', badge: 'bg-blue-100 text-blue-700', desc: '担当者がカレンダーにスケジュールを組んだ' },
                        { status: '完了', badge: 'bg-yellow-100 text-yellow-700', desc: '校正作業完了' },
                    ]" :key="s.status" class="flex items-center gap-4 border-b border-gray-50 px-4 py-3 last:border-0">
                        <span class="w-24 rounded-full px-3 py-1 text-center text-xs font-semibold" :class="s.badge">{{ s.status }}</span>
                        <span class="text-sm text-gray-600">{{ s.desc }}</span>
                    </div>
                </div>

                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>一覧から各ジョブの詳細・担当者・締め切りを確認できます</li>
                    <li>ステータスでフィルタリングして未完了のジョブを絞り込めます</li>
                </ul>
            </div>

            <!-- 4. 校正カレンダー -->
            <div id="calendar" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-100 text-sm text-pink-600 font-bold">4</span>
                    校正カレンダー
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-pink-200 bg-pink-50 p-3 text-sm text-pink-700">
                    <strong>「校正カレンダー」タブ</strong>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>校正ジョブのスケジュールをカレンダー形式で一覧表示します</li>
                    <li>担当者ごとのスケジュールを視覚的に把握できます</li>
                    <li>締め切り超過・直近の予定をカレンダー上で確認できます</li>
                </ul>
            </div>

            <!-- 5. 校正員作業量 -->
            <div id="workload" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-100 text-sm text-pink-600 font-bold">5</span>
                    校正員作業量
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-pink-200 bg-pink-50 p-3 text-sm text-pink-700">
                    <strong>「校正員作業量」タブ</strong>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>各校正メンバーの担当ジョブ数・作業量を確認できます</li>
                    <li>特定メンバーへの仕事の偏りを把握し、適切に分配するために活用します</li>
                    <li>期間を指定して集計・比較することができます</li>
                </ul>
            </div>

            <!-- 6. 校正チーム管理 -->
            <div id="team" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-100 text-sm text-pink-600 font-bold">6</span>
                    校正チーム管理
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-pink-200 bg-pink-50 p-3 text-sm text-pink-700">
                    <strong>「校正チーム管理」タブ</strong>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>校正チームのメンバーを登録・編集・削除できます</li>
                    <li>登録したメンバーは校正依頼受信の担当者割り当て時に選択できます</li>
                    <li>メンバーの稼働状況や担当可能な作業種別を管理します</li>
                </ul>
            </div>

            <!-- 7. 単発派遣管理 -->
            <div id="dispatchers" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-100 text-sm text-pink-600 font-bold">7</span>
                    単発派遣管理
                </h2>
                <div class="mb-4 rounded-lg border border-dashed border-pink-200 bg-pink-50 p-3 text-sm text-pink-700">
                    <strong>「単発派遣管理」タブ</strong>
                </div>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>単発・短期で依頼する派遣スタッフの情報を登録・管理します</li>
                    <li>稼働実績の記録や連絡先情報を保持できます</li>
                    <li>校正チームのレギュラーメンバーとは別に管理されます</li>
                </ul>
            </div>

            <!-- ガイド一覧に戻る -->
            <div class="flex justify-center pt-2 pb-4">
                <Link :href="route('guide.index')" class="inline-flex items-center gap-2 rounded-lg border border-pink-200 bg-white px-5 py-2 text-sm font-medium text-pink-600 shadow-sm hover:bg-pink-50">
                    ← ガイド一覧に戻る
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
