<script setup>
import { toRefs } from 'vue';
import { personalItemTypes } from '@/utils/personalMonthCalendar';
const props=defineProps({state:{type:Object,required:true}});
const {todoOpen,showCompleted,page,data,loading,error,busy}=toRefs(props.state);
</script>
<template>
<section class="rounded border border-gray-200 bg-white p-2">
    <button type="button" class="flex w-full items-center justify-between gap-1 text-left text-xs font-semibold text-gray-800" :aria-expanded="todoOpen" @click="todoOpen=!todoOpen"><span>TODO・締切（{{ data.todo_total }}）</span><span aria-hidden="true">{{ todoOpen?'−':'＋' }}</span></button>
    <div v-show="todoOpen" class="mt-3">
        <div class="flex items-center justify-between gap-1 text-xs"><label class="flex items-center gap-1"><input v-model="showCompleted" type="checkbox" />完了も表示</label><button type="button" :disabled="busy" class="text-blue-700 underline disabled:opacity-50" @click="state.createTodo">追加</button></div>
        <p class="mt-2 text-[11px] text-gray-500">別の月・期限未定のTODOも表示。</p>
        <p v-if="loading" class="mt-2 text-xs text-gray-500">読み込み中…</p>
        <p v-if="error" role="alert" class="mt-2 break-words text-xs text-red-700">{{ error }} <button type="button" class="underline" @click="state.load">再読込</button></p>
        <p v-if="!data.todos.length && !loading" class="mt-3 text-xs text-gray-500">該当するTODOはありません。</p>
        <ul class="mt-2 divide-y"><li v-for="item in data.todos" :key="item.id" class="flex items-start gap-1.5 py-2"><input type="checkbox" class="mt-1 shrink-0" :checked="item.completed" :disabled="busy" :aria-label="item.title+'の完了切替'" @change="state.toggle(item)" /><button type="button" :disabled="busy" class="min-w-0 flex-1 text-left disabled:opacity-50" @click="state.edit(item)"><span class="block break-words text-xs leading-relaxed" :class="item.completed?'text-gray-400 line-through':''">{{ item.title }}</span><span class="mt-1 block text-[11px]" :class="item.overdue?'font-semibold text-red-700':'text-gray-500'">{{ personalItemTypes[item.item_type] }}・{{ item.start_date || '期限未定' }} {{ item.start_time || '' }}{{ item.overdue?'（期限超過）':'' }}</span></button></li></ul>
        <div v-if="data.todo_last_page>1" class="mt-2 flex justify-between text-xs"><button type="button" :disabled="page<=1 || loading" class="text-blue-700 disabled:text-gray-400" @click="state.changePage(page-1)">前へ</button><span>{{ page }} / {{ data.todo_last_page }}</span><button type="button" :disabled="page>=data.todo_last_page || loading" class="text-blue-700 disabled:text-gray-400" @click="state.changePage(page+1)">次へ</button></div>
    </div>
</section>
</template>
