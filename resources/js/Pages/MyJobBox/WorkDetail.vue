<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import QuickWorkEntryModal from '@/Components/Calendar/QuickWorkEntryModal.vue';
import { workCategories, workActivities } from '@/Constants/personalWorkActivities';

const props = defineProps({ work: Object, time: Object, ancestors: Array, back: Object });
const editing = ref(false);
const backHref = computed(() => props.back?.from === 'calendar'
    ? route('calendar.index', { date: props.work.work_date, view: 'day' })
    : route('user.myjobbox.unified_preview', {
        period: props.back?.period || props.work.work_date.slice(0, 7), q: props.back?.q || undefined,
        page: props.back?.page || undefined, hide_completed: props.back?.hide_completed,
    }));
const category = computed(() => workCategories.find(([key]) => key === props.work.work_category)?.[1] || 'その他');
const activity = computed(() => (workActivities[props.work.work_category] || []).find(([key]) => key === props.work.activity_type)?.[1] || '未指定');
const quantity = computed(() => props.work.actual_quantity == null ? '未入力' : `${Number(props.work.actual_quantity)} ${props.work.actual_unit || ''}`.trim());
const minutes = value => `${Number(Number(value || 0).toFixed(1))}分`;
function deleted() {
    editing.value = false;
    router.visit(backHref.value);
}
function saved() {
    editing.value = false;
    router.reload({ only: ['work', 'time', 'ancestors'], preserveScroll: true });
}
</script>

<template>
    <AppLayout title="マイジョブ詳細">
        <template #header>
            <div class="flex flex-wrap items-center gap-3"><Link :href="backHref" class="rounded border bg-white px-3 py-2 text-sm text-blue-700">戻る</Link><h2 class="text-base font-semibold text-gray-800 sm:text-xl">マイジョブ詳細</h2></div>
        </template>
        <template #tabs><UserNavigationTabs active="myjob" /></template>
        <div class="space-y-4">
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <div><h3 class="text-base font-semibold text-gray-900">{{ work.title || '（作業名なし）' }}</h3><p class="mt-1 text-sm text-gray-500">{{ work.classification || work.project || '案件未指定' }}</p></div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="work.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'">{{ work.completed ? '完了' : '未完了' }}</span>
                </div>
                <div class="border-b border-gray-100 px-5 py-4">
                    <h4 class="mb-3 text-xs font-medium text-gray-400">案件情報</h4>
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div class="flex gap-4"><dt class="shrink-0 text-gray-500">案件名</dt><dd class="font-medium text-gray-900">{{ work.project || '未指定' }}</dd></div>
                        <div class="flex gap-4"><dt class="shrink-0 text-gray-500">大まかな案件</dt><dd class="font-medium text-gray-900">{{ work.classification || '未指定' }}</dd></div>
                    </dl>
                </div>
                <div class="border-b border-gray-100 px-5 py-4">
                    <h4 class="mb-3 text-xs font-medium text-gray-400">作業詳細</h4>
                    <div class="flex flex-wrap gap-2">
                        <div class="flex items-center gap-1.5 rounded-md bg-blue-50 px-3 py-1.5"><span class="text-xs text-blue-500">作業区分</span><span class="text-sm font-semibold text-blue-800">{{ category }}</span></div>
                        <div class="flex items-center gap-1.5 rounded-md bg-indigo-50 px-3 py-1.5"><span class="text-xs text-indigo-500">種類</span><span class="text-sm font-semibold text-indigo-800">{{ activity }}</span></div>
                        <div class="flex items-center gap-1.5 rounded-md bg-teal-50 px-3 py-1.5"><span class="text-xs text-teal-500">数量</span><span class="text-sm font-semibold text-teal-800">{{ quantity }}</span></div>
                    </div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs text-gray-500">進み・結果</dt><dd class="mt-1 whitespace-pre-wrap text-gray-900">{{ work.result_note || '未入力' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">部署一覧での表示</dt><dd class="mt-1 text-gray-900">{{ work.entry_visibility === 'private' ? '内容を非公開' : '内容を表示' }}</dd></div>
                    </dl>
                </div>
                <div class="px-5 py-4">
                    <h4 class="mb-3 text-xs font-medium text-gray-400">スケジュール</h4>
                    <dl class="grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-gray-500">作業日</dt><dd class="mt-1 font-medium text-gray-900">{{ work.work_date }}</dd></div>
                        <div><dt class="text-xs text-gray-500">開始</dt><dd class="mt-1 font-medium text-gray-900">{{ work.display_start || work.planned_start_time || '未指定' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">終了</dt><dd class="mt-1 font-medium text-gray-900">{{ work.display_end || work.planned_end_time || '未指定' }}</dd></div>
                    </dl>
                </div>
            </section>
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white px-5 py-4 shadow-sm">
                <h3 class="font-semibold text-gray-800">時間の内訳</h3>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-gray-500">予定枠</dt><dd class="mt-1 font-medium">{{ minutes(time.planned) }}</dd></div>
                    <div><dt class="text-gray-500">昼休憩との重なり</dt><dd class="mt-1">{{ minutes(time.lunch) }}</dd></div>
                    <div><dt class="text-gray-500">昼休憩を除く予定枠</dt><dd class="mt-1">{{ minutes(time.afterLunch) }}</dd></div>
                    <div><dt class="text-gray-500">記録時間</dt><dd class="mt-1 font-medium text-blue-700">{{ minutes(time.recorded) }}</dd></div>
                </dl>
                <p class="mt-4 text-xs leading-relaxed text-gray-500">記録時間は、作業分析の試用V3と同じ条件で、現在までの時間に昼休憩・重複・会議などの配分を反映します。未来の予定枠や完了状態とは別です。</p>
            </section>
            <section v-if="ancestors.length > 1" class="rounded bg-white p-4 shadow sm:p-6">
                <h3 class="font-semibold text-gray-800">続き元の仕事</h3>
                <ol class="mt-3 space-y-2 text-sm"><li v-for="item in ancestors" :key="item.type + ':' + item.id">
                    <Link :href="item.type === 'entry' ? route('user.myjobbox.entries.show', { entry: item.id }) : route('user.myjobbox.show', { assignment: item.id })" class="text-blue-700 underline">{{ item.title || '（作業名なし）' }}</Link><span class="ml-2 text-gray-500">{{ item.completed ? '完了' : '未完了' }}</span>
                </li></ol>
            </section>
            <div class="flex flex-wrap items-center justify-end gap-2 rounded-lg border border-gray-200 bg-white px-5 py-3 shadow-sm">
                <Link :href="route('calendar.index', { date: work.work_date, view: 'day' })" class="rounded bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">この日のカレンダー</Link>
                <button type="button" class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="editing = true">予定を編集</button>
            </div>
        </div>
        <QuickWorkEntryModal :show="editing" :entry="work" entry-point="myjob" @close="editing = false" @saved="saved" @deleted="deleted" />
    </AppLayout>
</template>
