<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import ScheduleCalendar from '@/Components/Schedule/ScheduleCalendar.vue';
import DepartmentWorkBoard from '@/Components/Schedule/DepartmentWorkBoard.vue';
import DepartmentWorkRecords from '@/Components/Schedule/DepartmentWorkRecords.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    initialDate:        { type: String, default: '' },
    overlays:           { type: Array,  default: () => [] },
    rooms:              { type: Array,  default: () => [] },
    eventItemTypes:     { type: Array,  default: () => [] },
    meetingDefinitions: { type: Array,  default: () => [] },
    companies:          { type: Array,  default: () => [] },
    departments:        { type: Array,  default: () => [] },
    worktypes:          { type: Array,  default: () => [] },
    dailyWorktypes:     { type: Array,  default: () => [] },
    defaultWorktype:    { type: Object, default: null },
    departmentWorkEnabled: { type: Boolean, default: false },
    showDepartmentWork: { type: Boolean, default: false },
});

// 一般会社（サンエー印刷等）は評価システム系の「部署の作業」を出さず、共有予定のみ表示する
const isGeneral = computed(() => usePage().props.auth?.companyType === 'general');
const workEnabled = computed(() => props.departmentWorkEnabled && !isGeneral.value);

const boardVisible = computed(() => workEnabled.value && props.showDepartmentWork);
const departmentTab = ref(new URLSearchParams(window.location.search).get('view') === 'records' ? 'records' : 'plans');
function selectDepartmentTab(value, today = false) {
    departmentTab.value = value;
    const url = new URL(window.location.href);
    url.searchParams.set('view', value);
    if (today) url.searchParams.delete('record_date');
    window.history.replaceState(window.history.state, '', url);
}
const pageGuideOpen = ref(false);
const pageTitle = computed(() => boardVisible.value ? '部署の作業' : '予定表');
</script>

<template>
    <AppLayout :title="pageTitle" :fluid="true">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ pageTitle }}</h2>
                <button v-if="boardVisible" type="button" class="inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-medium text-teal-700 hover:bg-teal-100" @click="pageGuideOpen = true">📖 このページの見方</button>
                <Link v-else-if="!isGeneral" :href="route('guide.schedule')" class="inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-medium text-teal-700 hover:bg-teal-100">
                    📖 使い方ガイド
                </Link>
            </div>
        </template>

        <Modal :show="pageGuideOpen" max-width="2xl" @close="pageGuideOpen = false">
            <section class="p-4 sm:p-6" aria-labelledby="department-page-guide-title">
                <div class="flex items-center justify-between gap-3 border-b pb-3">
                    <h3 id="department-page-guide-title" class="text-lg font-semibold">このページの見方</h3>
                    <button type="button" class="rounded border px-3 py-1 text-sm hover:bg-gray-50" @click="pageGuideOpen = false">閉じる</button>
                </div>
                <div class="mt-4 space-y-5 text-sm leading-relaxed text-gray-700">
                    <section>
                        <h4 class="font-semibold text-blue-800">部署の作業予定</h4>
                        <p class="mt-2">同じ部署の予定を、担当者ごとに横向きの時間表で確認できます。予定が重なる場合は段を分けて表示します。</p>
                        <p class="mt-2">担当者名や予定バーを押すと、その人の予定・結果が開きます。バーにマウスを重ねても予定の内容を確認できます。灰色の予定は件名を非公開にしています。</p>
                        <p class="mt-2">「前日」「翌日」は土日・登録祝日を飛ばします。別の日を見たいときは日付欄で指定してください。</p>
                        <p class="mt-2">予定枠の埋まり具合は予定を登録した範囲の目安です。「未登録・不明」や空いている時間は、仕事がないことを意味しません。</p>
                    </section>
                    <section class="border-t pt-4">
                        <h4 class="font-semibold text-blue-800">みんなの作業記録</h4>
                        <p class="mt-2">直近5営業日の記録時間を確認できます。土日・登録祝日は対象日に表示しません。</p>
                        <p class="mt-2">時間のバーは「ジョブ（旧形式・依頼）」「マイジョブ（新形式）」「会議・外出」「その他業務」の内訳です。休憩や重複を除いた記録時間を表示します。</p>
                        <p class="mt-2">「並び替え」で設定順・名前順・総時間や内訳の多い順へ切り替えられます。時間の長さは成果や評価点・順位を示すものではありません。</p>
                        <p class="mt-2">「自分の記録を確認」では、自分の記録時間、作業種類の未指定、勤務時間内の予定の空きを確認できます。空きは未就労を意味しません。「この日の記録を確認・入力」から自分のカレンダーを開いて確認・入力してください。</p>
                    </section>
                </div>
            </section>
        </Modal>
        <div v-if="boardVisible">
            <div role="group" aria-label="部署の作業の表示" class="mb-3 flex flex-wrap gap-2 border-b border-gray-200">
                <button v-for="item in [['plans', '部署の作業予定'], ['records', 'みんなの作業記録']]" :key="item[0]" type="button" :aria-pressed="departmentTab === item[0]" class="rounded-t px-4 py-2 text-sm font-semibold" :class="departmentTab === item[0] ? 'border border-b-white bg-white text-blue-700' : 'text-gray-600 hover:bg-gray-100'" @click="selectDepartmentTab(item[0])">{{ item[1] }}</button>
            </div>
            <template v-if="departmentTab === 'plans'">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded border border-blue-100 bg-blue-50 px-3 py-2 text-sm">
                    <span>会議・仕事の配分・応援も、記録すると仕事の内容が伝わります。</span>
                    <button type="button" class="font-semibold text-blue-700 underline" @click="selectDepartmentTab('records', true)">今日の記録を確認</button>
                </div>
                <DepartmentWorkBoard :initial-date="initialDate" />
            </template>
            <DepartmentWorkRecords v-else />
        </div>
        <template v-else>
        <!-- 会議室予約テスト中バナー -->
        <div class="mb-2 flex items-center gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span><strong>会議室予約はテスト機能です。</strong>会議室の予約は引き続き Outlook で行ってください。</span>
        </div>

        <div class="rounded-lg bg-white shadow overflow-hidden" style="min-height: calc(100vh - 150px)">
            <ScheduleCalendar
                :initial-date="initialDate"
                :initial-overlays="overlays"
                :event-item-types="eventItemTypes"
                :meeting-definitions="meetingDefinitions"
                :rooms="rooms"
                :companies="companies"
                :departments="departments"
                :worktypes="worktypes"
                :daily-worktypes="dailyWorktypes"
                :trial-evaluation-enabled="Boolean(usePage().props.auth?.canAccessWorkRecordTrial)"
                :default-worktype="defaultWorktype" />
        </div>
        </template>
    </AppLayout>
</template>
