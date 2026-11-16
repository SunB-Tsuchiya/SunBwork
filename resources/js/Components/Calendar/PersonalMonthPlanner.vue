<script setup>
import { computed, toRefs } from 'vue';
import MonthView from '@/Components/Schedule/MonthView.vue';
import { isCalendarJob, personalMonthEvents } from '@/utils/personalMonthCalendar';
const props=defineProps({year:Number,month:Number,events:{type:Array,default:()=>[]},state:{type:Object,required:true}});
const emit=defineEmits(['event-click']);
const {showJobs,showCompleted,data,loading,error,range}=toRefs(props.state);
const monthEvents=computed(()=>[...props.events.filter(e=>showJobs.value||!isCalendarJob(e)),...personalMonthEvents(data.value.items,range.value.start,range.value.end)]);
function clicked(event){if(event._personal_item)props.state.edit(event._personal_item);else emit('event-click',event);}
</script>
<template>
<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-4 rounded border border-blue-100 bg-blue-50 p-3 text-sm"><span class="font-medium text-blue-900">個人の月予定（試用）</span><label class="flex items-center gap-2"><input v-model="showJobs" type="checkbox" />ジョブも表示</label><label class="flex items-center gap-2"><input v-model="showCompleted" type="checkbox" />完了も表示</label><button type="button" class="ml-auto rounded bg-blue-600 px-3 py-2 text-white" @click="state.create()">予定・締め切りを追加</button></div>
    <p class="text-xs text-gray-500">本人専用です。日付を押して追加できます。期間はドラッグでも指定できます。TODO・締め切りは左の一覧で月・週・日から確認できます。</p>
    <p v-if="error" role="alert" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ error }} <button type="button" class="underline" @click="state.load">再読み込み</button></p>
    <p v-if="loading" class="text-xs text-gray-500">読み込み中…</p><p v-if="data.truncated" class="text-sm text-amber-800">月の個人予定が500件を超え、表示上限に達しています。</p>
    <MonthView :year="year" :month="month" :events="monthEvents" selectable-range @date-click="state.create" @range-select="state.create($event.start,$event.end)" @event-click="clicked" />
</div>
</template>
