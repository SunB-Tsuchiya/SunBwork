<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import MonthView from '@/Components/Schedule/MonthView.vue';
import PersonalCalendarItemModal from './PersonalCalendarItemModal.vue';
import { isCalendarJob, personalMonthEvents, personalItemTypes } from '@/utils/personalMonthCalendar';
const props=defineProps({year:Number,month:Number,events:{type:Array,default:()=>[]},ownerKey:{type:String,required:true}});
const emit=defineEmits(['event-click']);
const showJobs=ref(false),showCompleted=ref(false),page=ref(1),data=ref({items:[],todos:[],todo_total:0,todo_last_page:1}),loading=ref(false),error=ref(''),busy=ref(false),editor=ref(null);
try {showJobs.value=localStorage.getItem('personal_month_jobs_'+props.ownerKey)==='1';}catch{}
watch(showJobs,v=>{try{localStorage.setItem('personal_month_jobs_'+props.ownerKey,v?'1':'0');}catch{}});
const range=computed(()=>({start:`${props.year}-${String(props.month).padStart(2,'0')}-01`,end:new Date(props.year,props.month,0,12).toLocaleDateString('sv-SE')}));
let sequence=0;
async function load(){const seq=++sequence;loading.value=true;error.value='';try{const res=await axios.get(route('personal_calendar.items.index'),{params:{...range.value,todo_page:page.value,show_completed:showCompleted.value?1:0}});if(seq===sequence)data.value=res.data;}catch(e){if(seq===sequence)error.value=e.response?.data?.message||'個人予定・TODOを読み込めませんでした。';}finally{if(seq===sequence)loading.value=false;}}
watch(range,()=>{page.value=1;load();},{immediate:true});watch(showCompleted,()=>{page.value=1;load();});
async function changePage(value){page.value=value;await load();}
const monthEvents=computed(()=>[...props.events.filter(e=>showJobs.value||!isCalendarJob(e)),...personalMonthEvents(data.value.items,range.value.start,range.value.end)]);
function create(date='',endDate=''){editor.value={item:null,date,endDate,type:'event'};}
function clicked(e){if(e._personal_item)editor.value={item:e._personal_item};else emit('event-click',e);}
async function saved(){editor.value=null;await load();}
async function toggle(item){if(busy.value)return;busy.value=true;error.value='';try{await axios.patch(route('personal_calendar.items.complete',{item:item.id}),{version:item.version,completed:!item.completed},{headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),Accept:'application/json'}});await load();}catch(e){error.value=e.response?.data?.message||'完了状態を保存できませんでした。';}finally{busy.value=false;}}
</script>
<template>
<div :inert="Boolean(editor)" class="space-y-3">
    <div class="flex flex-wrap items-center gap-4 rounded border border-blue-100 bg-blue-50 p-3 text-sm"><span class="font-medium text-blue-900">個人の月予定・TODO（試用）</span><label class="flex items-center gap-2"><input v-model="showJobs" type="checkbox" />ジョブも表示</label><label class="flex items-center gap-2"><input v-model="showCompleted" type="checkbox" />完了も表示</label><button type="button" class="ml-auto rounded bg-blue-600 px-3 py-2 text-white" @click="create()">予定・締め切りを追加</button></div>
    <p class="text-xs text-gray-500">本人専用です。日付を押して追加できます。期間はドラッグでも指定できます。週・日のジョブ入力とは別に管理します。</p>
    <p v-if="error" role="alert" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ error }} <button type="button" class="underline" @click="load">再読み込み</button></p>
    <p v-if="loading" class="text-xs text-gray-500">読み込み中…</p><p v-if="data.truncated" class="text-sm text-amber-800">月の個人予定が500件を超えています。表示を絞って確認してください。</p>
    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <MonthView :year="year" :month="month" :events="monthEvents" selectable-range @date-click="create" @range-select="create($event.start,$event.end)" @event-click="clicked" />
        <aside class="rounded border bg-white p-4"><div class="flex items-center justify-between"><h3 class="font-semibold">{{ showCompleted ? 'TODO・締め切り' : '未完了TODO・締め切り' }}（{{ data.todo_total }}）</h3><button type="button" class="text-sm text-blue-700 underline" @click="editor={item:null,date:'',type:'todo'}">追加</button></div>
            <p class="mt-2 text-xs text-gray-500">別の月の未完了や、期限未定のTODOも表示します。</p>
            <p v-if="!data.todos.length && !loading" class="mt-4 text-sm text-gray-500">該当するTODOはありません。</p>
            <ul class="mt-3 divide-y"><li v-for="item in data.todos" :key="item.id" class="flex items-start gap-2 py-3"><input type="checkbox" class="mt-1" :checked="item.completed" :disabled="busy" :aria-label="item.title+'の完了切替'" @change="toggle(item)" /><button type="button" class="min-w-0 flex-1 text-left" @click="editor={item}"><span class="block break-words text-sm" :class="item.completed?'text-gray-400 line-through':''">{{ item.title }}</span><span class="mt-1 block text-xs" :class="item.overdue?'font-semibold text-red-700':'text-gray-500'">{{ personalItemTypes[item.item_type] }}・{{ item.start_date || '期限未定' }} {{ item.start_time || '' }}{{ item.overdue?'（期限超過）':'' }}</span></button></li></ul>
            <div v-if="data.todo_last_page>1" class="mt-3 flex justify-between text-sm"><button type="button" :disabled="page<=1 || loading" class="text-blue-700 disabled:text-gray-400" @click="changePage(page-1)">前へ</button><span>{{ page }} / {{ data.todo_last_page }}</span><button type="button" :disabled="page>=data.todo_last_page || loading" class="text-blue-700 disabled:text-gray-400" @click="changePage(page+1)">次へ</button></div>
        </aside>
    </div>
</div>
<PersonalCalendarItemModal v-if="editor" v-bind="editor" @close="editor=null" @saved="saved" />
</template>
