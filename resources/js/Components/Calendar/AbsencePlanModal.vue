<script setup>
import axios from 'axios';
import {ref,reactive,computed,watch,onMounted,onUnmounted,nextTick} from 'vue';
import {route} from 'ziggy-js';
const props=defineProps({date:{type:String,required:true},item:{type:Object,default:null}});
const emit=defineEmits(['close','saved']);
const form=reactive({date:props.item?.date||props.date,kind:props.item?.kind||'paid_leave',start_time:props.item?.start_time||'',end_time:props.item?.end_time||''});
const initial=JSON.stringify(form),defaults=ref(null),loading=ref(false),busy=ref(false),error=ref(''),dialog=ref(null);
const key=crypto.randomUUID();let pending=null,sequence=0;
const labels={paid_leave:'有休',half_am:'午前休',half_pm:'午後休',late:'遅刻',early_leave:'早退'};
const startLabel=computed(()=>['half_am','late'].includes(form.kind)?'勤務開始時刻':'退出予定時刻');
const endLabel=computed(()=>['half_am','late'].includes(form.kind)?'出勤・到着予定時刻':'勤務終了時刻');
const headers=()=>({'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),Accept:'application/json'});
function applyDefaults(){const d=defaults.value;if(!d)return;if(['half_am','late'].includes(form.kind)){form.start_time=d.start_time||'';form.end_time=form.kind==='half_am'&&d.start_time&&d.break_end>d.start_time&&d.break_end<d.end_time?d.break_end:'';}else if(['half_pm','early_leave'].includes(form.kind)){form.start_time=form.kind==='half_pm'&&d.end_time&&d.break_start>d.start_time&&d.break_start<d.end_time?d.break_start:'';form.end_time=d.end_time||'';}}
async function loadDefaults(){const seq=++sequence;loading.value=true;error.value='';try{const {data}=await axios.get(route('personal_absence.defaults'),{params:{date:form.date}});if(seq!==sequence)return;defaults.value=data;if(!props.item||form.date!==props.item.date)applyDefaults();}catch(e){if(seq===sequence){defaults.value=null;error.value=e.response?.data?.message||'勤務時間の候補を取得できませんでした。再取得してください。';}}finally{if(seq===sequence)loading.value=false;}}
watch(()=>form.date,loadDefaults,{immediate:true});watch(()=>form.kind,applyDefaults);
function close(){if(!busy.value&&(JSON.stringify(form)===initial||confirm('入力を破棄して閉じますか？')))emit('close');}
async function save(){if(busy.value||loading.value||!defaults.value)return;const payload={date:form.date,kind:form.kind,start_time:form.kind==='paid_leave'?null:form.start_time||null,end_time:form.kind==='paid_leave'?null:form.end_time||null};if(!props.item&&pending&&JSON.stringify(payload)!==pending){error.value='前回の保存結果が不明です。同じ内容で再送するか、登録済みか確認してください。';return;}busy.value=true;error.value='';try{let res;if(props.item)res=await axios.put(route('personal_absence.update',{plan:props.item.id}),{...payload,version:props.item.version},{headers:headers()});else{pending=JSON.stringify(payload);res=await axios.post(route('personal_absence.store'),{...payload,request_key:key},{headers:headers()});}emit('saved',res.data.item.date);}catch(e){if(e.response?.status===422)pending=null;error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||'保存結果が不明です。入力は残しています。';}finally{busy.value=false;}}
async function remove(){if(busy.value||!props.item||!confirm('この休暇・遅刻・早退の予定を削除しますか？ 在席ボードへの予定反映も解除します。'))return;busy.value=true;error.value='';try{await axios.delete(route('personal_absence.destroy',{plan:props.item.id}),{data:{version:props.item.version},headers:headers()});emit('saved',form.date);}catch(e){error.value=e.response?.data?.message||'削除できませんでした。';}finally{busy.value=false;}}
let previousFocus,overflow;
function keydown(e){if(e.key==='Escape'){e.preventDefault();e.stopPropagation();}if(e.key!=='Tab')return;const els=[...(dialog.value?.querySelectorAll('button:not(:disabled),input:not(:disabled),select:not(:disabled)')||[])].filter(el=>!el.closest('fieldset:disabled'));if(!els.length){e.preventDefault();dialog.value?.focus();return;}if(!dialog.value.contains(document.activeElement)||(e.shiftKey&&document.activeElement===els[0])||(!e.shiftKey&&document.activeElement===els.at(-1))){e.preventDefault();(e.shiftKey?els.at(-1):els[0]).focus();}}
onMounted(async()=>{previousFocus=document.activeElement;overflow=document.body.style.overflow;document.body.style.overflow='hidden';document.addEventListener('keydown',keydown);await nextTick();dialog.value?.focus();});onUnmounted(()=>{sequence++;document.body.style.overflow=overflow;document.removeEventListener('keydown',keydown);previousFocus?.focus?.();});
</script>
<template>
<Teleport to="body"><div class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40 p-3" @keydown.esc.prevent.stop>
<section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="absence-title" tabindex="-1" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-5 shadow-xl">
    <div class="mb-4 flex items-center justify-between gap-3"><h2 id="absence-title" class="text-lg font-semibold">{{item?'休暇・遅刻・早退の予定を編集':'休暇・遅刻・早退'}}</h2><button v-if="item" type="button" :disabled="busy" class="rounded bg-red-600 px-3 py-1.5 text-sm text-white disabled:opacity-50" @click="remove">削除</button></div>
    <p class="mb-4 rounded bg-blue-50 p-3 text-sm text-blue-800">在席ボードに予定として反映します。日報の勤務時刻は変更しません。</p>
    <p v-if="error" role="alert" class="mb-3 break-words text-sm text-red-700">{{error}} <button v-if="!defaults&&!loading" type="button" class="underline" @click="loadDefaults">再取得</button></p>
    <form @submit.prevent="save"><fieldset :disabled="busy||loading" class="space-y-4">
        <label class="block text-sm">日付<input v-model="form.date" required type="date" class="mt-1 w-full rounded border p-2" /></label>
        <label class="block text-sm">区分<select v-model="form.kind" class="mt-1 w-full rounded border py-2 pl-3 pr-9"><option v-for="(label,kind) in labels" :key="kind" :value="kind" :disabled="defaults&&!defaults.kinds.find(k=>k.slug===kind)?.enabled&&item?.kind!==kind">{{label}}{{defaults&&!defaults.kinds.find(k=>k.slug===kind)?.enabled?'（在席ボードで無効）':''}}</option></select></label>
        <p v-if="loading" class="text-xs text-gray-500">勤務時間の候補を取得中…</p>
        <template v-if="form.kind!=='paid_leave'">
            <p v-if="defaults&&!defaults.start_time" class="text-xs text-amber-800">勤務時間を確定できません。対象日の時刻を入力してください。</p>
            <div class="grid grid-cols-2 gap-3"><label class="block text-sm">{{startLabel}}<input v-model="form.start_time" required type="time" class="mt-1 w-full rounded border p-2" /></label><label class="block text-sm">{{endLabel}}<input v-model="form.end_time" required type="time" class="mt-1 w-full rounded border p-2" /></label></div>
        </template>
        <div class="rounded border bg-gray-50 p-3 text-sm"><span class="font-medium">登録する不在予定</span><p class="mt-1">{{form.date}}・{{labels[form.kind]}} {{form.kind==='paid_leave'?'（終日）':(form.start_time||'未入力')+'〜'+(form.end_time||'未入力')}}</p></div>
    </fieldset><div class="mt-5 flex justify-end gap-3"><button type="button" :disabled="busy" class="rounded border px-4 py-2 text-sm disabled:opacity-50" @click="close">キャンセル</button><button type="submit" :disabled="busy||loading||!defaults" class="rounded bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">{{busy?'保存中…':'保存'}}</button></div></form>
</section></div></Teleport>
</template>
