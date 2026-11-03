<script setup>
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
defineProps({ series: { type: Object, required: true } });
const minutes = value => `${Number(Number(value || 0).toFixed(1))}分`;
</script>

<template>
<section v-if="series.items.length > 1" class="overflow-hidden rounded-lg border border-orange-200 bg-orange-50 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-orange-200 bg-orange-100 px-5 py-3"><h3 class="text-sm font-semibold text-orange-800">↩ 続きジョブ シリーズ（{{ series.items.length }}件）</h3><span class="text-xs text-orange-800">{{ series.allCompleted ? '系列すべて完了' : '未完了の仕事あり' }}</span></div>
                <ol class="divide-y divide-orange-100">
                    <li v-for="(item, index) in series.items" :key="item.key" class="flex items-start gap-3 px-5 py-3" :class="item.current ? 'bg-orange-100' : ''">
                        <span class="rounded-full bg-orange-200 px-2 text-xs text-orange-800">{{ index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <Link :href="item.type === 'entry' ? route('user.myjobbox.entries.show', { entry: item.id }) : route('user.myjobbox.show', { assignment: item.id })" class="text-sm text-blue-700 underline">{{ item.title || '（作業名なし）' }}</Link>
                            <span v-if="item.current" class="ml-2 rounded-full bg-orange-600 px-2 py-0.5 text-xs text-white">現在</span><span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="item.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'">{{ item.completed ? '完了' : '未完了' }}</span>
                            <p v-for="(slot, slotIndex) in item.slots || []" :key="slotIndex" class="mt-1 text-xs text-gray-500">{{ slot.date }} {{ slot.start }}〜{{ slot.endDate !== slot.date ? slot.endDate + ' ' : '' }}{{ slot.end }}</p><p v-if="!(item.slots || []).length" class="mt-1 text-xs text-gray-500">未予定</p>
                            <p v-if="item.parentKey" class="mt-1 text-xs text-orange-800">続き元：{{ series.items.findIndex(parent => parent.key === item.parentKey) + 1 }}. {{ series.items.find(parent => parent.key === item.parentKey)?.title || '（作業名なし）' }}</p>
                        </div>
                        <div v-if="item.time" class="text-right text-xs"><p>休憩を除く予定 {{ minutes(item.time.afterLunch) }}</p><p class="mt-1 text-blue-700">記録 {{ minutes(item.time.recorded) }}</p></div><span v-else class="text-xs text-gray-500">時間は旧詳細で確認</span>
                    </li>
                </ol>
                <div v-if="series.totals" class="flex flex-wrap justify-between gap-2 border-t border-orange-200 px-5 py-3 text-sm font-semibold text-orange-800"><span>シリーズ合計</span><span>休憩を除く予定 {{ minutes(series.totals.afterLunch) }} ／ 記録 {{ minutes(series.totals.recorded) }}</span></div>
                <p class="px-5 pb-3 text-xs text-gray-500">予定は各枠の合計、記録は同じ日の他の仕事も含めた重複・会議等の配分後の合計です。</p>
            </section>
</template>
