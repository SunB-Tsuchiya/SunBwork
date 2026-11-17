<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import { personalItemColors } from '@/utils/personalMonthCalendar';
const props=defineProps({item:{type:Object,required:true},lists:{type:Array,default:()=>[]},today:String});
const emit=defineEmits(['saved','deleted','dirty','close']);
const form=reactive({}),baseline=ref(''),busy=ref(false),error=ref('');
watch(()=>props.item,item=>{Object.assign(form,{item_type:item.item_type,title:item.title,note:item.note||'',start_date:item.start_date||'',start_time:item.start_time||'',color_key:item.color_key||'indigo',todo_list_id:item.todo_list_id||null,is_important:Boolean(item.is_important),focus_date:item.focus_date||null,steps:structuredClone(JSON.parse(JSON.stringify(item.steps||[])))});baseline.value=JSON.stringify(form);error.value='';},{immediate:true});
const dirty=computed(()=>JSON.stringify(form)!==baseline.value);watch(dirty,value=>emit('dirty',value),{immediate:true});
onBeforeUnmount(()=>emit('dirty',false));
const headers=()=>({'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),Accept:'application/json'});
function addStep(){if(form.steps.length<50)form.steps.push({id:globalThis.crypto.randomUUID(),title:'',completed:false});}
async function toggleStep(index){if(busy.value)return;const auto=!dirty.value;form.steps[index].completed=!form.steps[index].completed;if(!auto)return;busy.value=true;error.value='';try{const {data}=await axios.patch(route('personal_todo.items.meta',{item:props.item.id}),{version:props.item.version,steps:form.steps},{headers:headers()});emit('saved',data.item);}catch(e){error.value=e.response?.data?.message||'チェックを保存できませんでした。入力は残っています。';}finally{busy.value=false;}}
async function save(){if(busy.value)return;busy.value=true;error.value='';try{const {data}=await axios.put(route('personal_calendar.items.update',{item:props.item.id}),{...form,start_date:form.start_date||null,start_time:form.start_time||null,end_date:null,end_time:null,all_day:!form.start_time,note:form.note||null,version:props.item.version},{headers:headers()});emit('saved',data.item);}catch(e){const detail=Object.values(e.response?.data?.errors||{}).flat().join(' ');error.value=detail||e.response?.data?.message||'保存できませんでした。入力は残っています。';}finally{busy.value=false;}}
async function remove(){if(busy.value||!confirm('このTODO・締め切りを削除しますか？'))return;busy.value=true;error.value='';try{await axios.delete(route('personal_calendar.items.destroy',{item:props.item.id}),{data:{version:props.item.version},headers:headers()});emit('deleted');}catch(e){error.value=e.response?.data?.message||'削除できませんでした。';}finally{busy.value=false;}}
</script>
<template>
<section class="min-w-0 rounded border bg-white p-4">
    <div class="mb-4 flex items-center justify-between gap-2"><h3 class="font-semibold">TODOの詳細</h3><div class="flex gap-3"><button type="button" :disabled="busy" class="text-sm text-gray-600 underline" @click="emit('close')">閉じる</button><button type="button" :disabled="busy" class="rounded bg-red-600 px-3 py-1.5 text-sm text-white disabled:opacity-50" @click="remove">削除</button></div></div>
    <p v-if="error" role="alert" class="mb-3 break-words text-sm text-red-700">{{ error }}</p>
    <form @submit.prevent="save"><fieldset :disabled="busy" class="space-y-3">
        <label class="block text-sm">名前<input v-model="form.title" required maxlength="255" class="mt-1 w-full rounded border p-2" /></label>
        <div class="flex flex-wrap gap-4 text-sm"><label class="flex items-center gap-2"><input v-model="form.is_important" type="checkbox" />★ 重要</label><label class="flex items-center gap-2"><input type="checkbox" :checked="form.focus_date===today" @change="form.focus_date=$event.target.checked?today:null" />今日やる</label></div>
        <p class="text-xs text-gray-500">「今日やる」は期限を変更しません。</p>
        <label class="block text-sm">種類<select v-model="form.item_type" class="mt-1 w-full rounded border p-2"><option value="todo">TODO</option><option value="deadline">締め切り</option></select></label>
        <div class="grid grid-cols-2 gap-2"><label class="text-sm">期限日<input v-model="form.start_date" type="date" :required="form.item_type==='deadline'" class="mt-1 w-full rounded border p-2" /></label><label class="text-sm">時刻（任意）<input v-model="form.start_time" type="time" class="mt-1 w-full rounded border p-2" /></label></div>
        <label class="block text-sm">リスト<select v-model="form.todo_list_id" class="mt-1 w-full rounded border p-2"><option :value="null">未整理</option><option v-for="list in lists.filter(l=>!l.archived||l.id===form.todo_list_id)" :key="list.id" :value="list.id">{{ list.name }}{{list.archived?'（アーカイブ）':''}}</option></select></label>
        <label class="block text-sm">色<select v-model="form.color_key" class="mt-1 w-full rounded border p-2"><option v-for="(_,key) in personalItemColors" :key="key" :value="key">{{ {indigo:'藍',blue:'青',cyan:'水色',teal:'青緑',green:'緑',yellow:'黄',orange:'橙',red:'赤',pink:'ピンク',purple:'紫',gray:'灰'}[key] }}</option></select></label>
        <label class="block text-sm">メモ<textarea v-model="form.note" rows="4" maxlength="2000" class="mt-1 w-full rounded border p-2" /></label>
        <section><div class="flex items-center justify-between gap-2 text-sm"><h4 class="font-medium">チェック項目（{{ form.steps.filter(s=>s.completed).length }}/{{ form.steps.length }}）</h4><button type="button" :disabled="form.steps.length>=50" class="text-blue-700 underline disabled:opacity-50" @click="addStep">追加</button></div>
            <div v-for="(step,index) in form.steps" :key="step.id" class="mt-2 flex items-start gap-2"><input type="checkbox" :checked="step.completed" :aria-label="step.title+'のチェック'" class="mt-2" @change="toggleStep(index)" /><input v-model="step.title" required maxlength="255" aria-label="チェック項目の名前" class="min-w-0 flex-1 rounded border p-1.5 text-sm" /><button type="button" :aria-label="step.title+'を削除'" class="mt-1 text-sm text-red-700" @click="form.steps.splice(index,1)">×</button></div>
            <p class="mt-2 text-xs text-gray-500">チェックがすべて終わっても、TODOの完了は一覧で選びます。</p>
        </section>
        <div class="flex items-center justify-end gap-3"><span v-if="dirty" class="text-xs text-amber-800">未保存の変更あり</span><button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">{{ busy?'保存中…':'変更を保存' }}</button></div>
    </fieldset></form>
</section>
</template>
