<script setup>
import axios from 'axios';
import { personalTodoDeleteQuestion } from '@/utils/personalTodoRecurrence';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import { route } from 'ziggy-js';
import { personalItemTypes, personalItemColors } from '@/utils/personalMonthCalendar';
const props = defineProps({ item: { type: Object, default: null }, date: { type: String, default: '' }, endDate: { type: String, default: '' }, type: { type: String, default: 'event' } });
const emit = defineEmits(['close','saved']);
const form = reactive({ item_type: props.item?.item_type || props.type, title: props.item?.title || '', note: props.item?.note || '', start_date: props.item?.start_date || props.date,
    end_date: props.item?.end_date || props.endDate, start_time: props.item?.start_time || '', end_time: props.item?.end_time || '', all_day: props.item?.all_day ?? true, color_key: props.item?.color_key || 'indigo' });
const initial = JSON.stringify(form);
const token = globalThis.crypto.randomUUID();
let submittedPayload = null;
const busy = ref(false), error = ref(''), errors = ref({}), dialog = ref(null);
const isEvent = computed(() => form.item_type === 'event');
function changeType() { form.end_date = ''; form.end_time = ''; }
function changeAllDay() { if (form.all_day) {form.start_time='';form.end_time='';} }
function close() { if (busy.value) return; if (JSON.stringify(form) !== initial && !confirm('入力を破棄して閉じますか？')) return; emit('close'); }
const headers = () => ({ 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'), Accept: 'application/json' });
async function save() {
    if (busy.value) return;
    error.value=''; errors.value={};
    const payload={...form, start_date:form.start_date||null,end_date:form.end_date||null,start_time:form.start_time||null,end_time:form.end_time||null,note:form.note||null};
    // After an uncertain create, keep the original key and payload until the result is resolved.
    if (!props.item && submittedPayload && JSON.stringify(payload)!==submittedPayload) {error.value='前回の保存結果が不明です。入力を控え、閉じて登録済みか確認してください。';return;}
    busy.value=true;
    try {
        if(props.item) await axios.put(route('personal_calendar.items.update',{item:props.item.id}), {...payload,version:props.item.version},{headers:headers()});
        else {submittedPayload=JSON.stringify(payload);await axios.post(route('personal_calendar.items.store'),{...payload,request_key:token},{headers:headers()});}
        emit('saved');
    } catch(e) {
        errors.value=e.response?.data?.errors||{};
        if(e.response?.status===422) submittedPayload=null;
        error.value=e.response?.data?.message || '保存結果を確認できませんでした。同じ内容なら再送できます。内容を変える前に登録済みか確認してください。';
    } finally {busy.value=false;}
}
async function toggleComplete() {
    if (busy.value || !props.item) return;
    if (JSON.stringify(form) !== initial && !confirm('編集内容は保存せず、完了状態だけ変更しますか？')) return;
    busy.value=true;error.value='';
    try {const res=await axios.patch(route('personal_calendar.items.complete',{item:props.item.id}),{version:props.item.version,completed:!props.item.completed},{headers:headers()});emit('saved',res?.data?.item);}
    catch(e){error.value=e.response?.data?.message || '完了状態を保存できませんでした。';}finally{busy.value=false;}
}
async function remove() {
    if (busy.value || !props.item || !confirm(props.item.item_type==='event'?'この個人予定を削除しますか？':personalTodoDeleteQuestion(props.item))) return;
    busy.value=true;error.value='';
    try {await axios.delete(route('personal_calendar.items.destroy',{item:props.item.id}),{data:{version:props.item.version},headers:headers()});emit('saved');}
    catch(e){error.value=e.response?.data?.message || '削除できませんでした。';}finally{busy.value=false;}
}
let overflow, previousFocus;
function keydown(e) {
    if(e.key==='Escape'){e.preventDefault();e.stopPropagation();return;}
    if(e.key!=='Tab')return;
    const els=[...(dialog.value?.querySelectorAll('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled)')||[])].filter(el=>!el.closest('fieldset:disabled'));
    if(!els.length){e.preventDefault();dialog.value?.focus();return;}
    if(!dialog.value.contains(document.activeElement)||(e.shiftKey&&document.activeElement===els[0])||(!e.shiftKey&&document.activeElement===els.at(-1))){e.preventDefault();(e.shiftKey?els.at(-1):els[0]).focus();}
}
onMounted(async()=>{previousFocus=document.activeElement;overflow=document.body.style.overflow;document.body.style.overflow='hidden';document.addEventListener('keydown',keydown);await nextTick();dialog.value?.querySelector('input')?.focus();});
onUnmounted(()=>{document.body.style.overflow=overflow;document.removeEventListener('keydown',keydown);previousFocus?.focus?.();});
</script>
<template>
<Teleport to="body"><div class="fixed inset-0 z-[2300] flex items-center justify-center bg-black/50 px-4">
    <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="personal-item-heading" tabindex="-1" class="max-h-[90vh] w-full max-w-lg overflow-y-auto overscroll-contain rounded-xl bg-white p-5 shadow-xl">
        <div class="flex items-center justify-between gap-3"><h3 id="personal-item-heading" class="font-semibold">{{ item ? '個人予定・TODOを編集' : '個人予定・TODOを追加' }}</h3><div class="flex flex-wrap gap-3"><button v-if="item" type="button" :disabled="busy" class="rounded border border-blue-300 px-3 py-2 text-sm text-blue-700 disabled:opacity-50" @click="toggleComplete">{{ item.completed ? '未完了に戻す' : '完了にする' }}</button><button v-if="item" type="button" :disabled="busy" class="rounded bg-red-600 px-3 py-2 text-sm text-white disabled:opacity-50" @click="remove">削除</button><button type="button" :disabled="busy" class="text-sm text-gray-600 underline" @click="close">閉じる</button></div></div>
        <p class="my-3 text-xs text-gray-500">本人だけの予定・メモです。作業時間や日報には計上されません。</p>
        <p v-if="error" role="alert" class="mb-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="item?.repeat_label" class="mb-3 rounded bg-blue-50 p-2 text-xs text-blue-800">繰り返し：{{item.repeat_label}}。設定変更は「個人TODO」画面で行えます。保存しても繰り返し設定は維持されます。</p>
        <form @submit.prevent="save"><fieldset :disabled="busy" class="space-y-3">
            <label class="block text-sm">種類<select v-model="form.item_type" class="mt-1 w-full rounded border p-2" @change="changeType"><option v-for="(label,key) in personalItemTypes" :key="key" :value="key">{{ label }}</option></select></label>
            <label class="block text-sm">名前（必須）<input v-model="form.title" required maxlength="255" class="mt-1 w-full rounded border p-2" /></label>
            <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">{{ isEvent ? '開始日' : '期限日' }}{{ form.item_type==='todo' ? '（任意）' : '（必須）' }}<input v-model="form.start_date" type="date" :required="form.item_type!=='todo'" class="mt-1 w-full rounded border p-2" /></label><label v-if="isEvent" class="text-sm">終了日（任意）<input v-model="form.end_date" type="date" class="mt-1 w-full rounded border p-2" /></label></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.all_day" type="checkbox" @change="changeAllDay" />{{ isEvent ? '終日' : '時刻を指定しない' }}</label>
            <div v-if="!form.all_day" class="grid gap-3 sm:grid-cols-2"><label class="text-sm">{{ isEvent ? '開始時刻' : '期限時刻' }}<input v-model="form.start_time" type="time" required class="mt-1 w-full rounded border p-2" /></label><label v-if="isEvent" class="text-sm">終了時刻<input v-model="form.end_time" type="time" required class="mt-1 w-full rounded border p-2" /></label></div>
            <label class="block text-sm">色<select v-model="form.color_key" class="mt-1 w-full rounded border p-2"><option v-for="(_,key) in personalItemColors" :key="key" :value="key">{{ {indigo:'藍',blue:'青',cyan:'水色',teal:'青緑',green:'緑',yellow:'黄',orange:'橙',red:'赤',pink:'ピンク',purple:'紫',gray:'灰'}[key] }}</option></select></label>
            <label class="block text-sm">メモ（任意）<textarea v-model="form.note" maxlength="2000" rows="3" class="mt-1 w-full rounded border p-2" /></label>
            <ul v-if="Object.keys(errors).length" role="alert" class="text-sm text-red-700"><li v-for="(messages,key) in errors" :key="key">{{ Array.isArray(messages) ? messages[0] : messages }}</li></ul>
            <div class="flex justify-end gap-3"><button type="button" class="text-sm text-gray-600 underline" @click="close">キャンセル</button><button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">{{ busy ? '保存中…' : '保存' }}</button></div>
        </fieldset></form>
    </section>
</div></Teleport>
</template>
