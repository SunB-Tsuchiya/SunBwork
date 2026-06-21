<script setup>
import { ref, provide } from 'vue';
import MiniCalendar from './MiniCalendar.vue';

defineProps({
    currentDate: { type: String, required: true },
    viewMode:    { type: String, required: true },
    viewLabel:   { type: String, required: true },
    loading:     { type: Boolean, default: false },
});

defineEmits(['navigate', 'go-today', 'view-mode-change', 'mini-cal-select']);

// スクロールコンテナを子コンポーネントに provide
const scrollEl = ref(null);
provide('calendarScrollEl', scrollEl);
</script>

<template>
    <!-- 2ペインレイアウト: 左サイドバー + 右メイン -->
    <div class="flex" style="height: calc(100vh - 150px); min-height: 520px">

        <!-- ── 左サイドバー（md 未満で非表示） ──────────────────── -->
        <div class="hidden md:flex w-52 shrink-0 border-r border-gray-200 bg-gray-50 flex-col overflow-y-auto">
            <MiniCalendar :date="currentDate" @select="$emit('mini-cal-select', $event)" />

            <!-- 区切り -->
            <div class="mx-3 my-1 border-t border-gray-200" />

            <!-- サイドバー追加コンテンツ（各カレンダーが差し込む） -->
            <div class="px-1 py-1 flex-1">
                <slot name="sidebar" />
            </div>
        </div>

        <!-- ── 右メインエリア ─────────────────────────────────────── -->
        <div class="flex min-h-0 flex-1 min-w-0 flex-col">
            <!-- ツールバー -->
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-white px-4 py-2">
                <!-- prev / next / today -->
                <div class="flex items-center gap-1">
                    <button
                        class="rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100"
                        @click="$emit('navigate', -1)">‹</button>
                    <button
                        class="rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100"
                        @click="$emit('navigate', 1)">›</button>
                    <button
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="$emit('go-today')">today</button>
                </div>

                <!-- 期間ラベル -->
                <div class="flex-1 text-center text-base font-semibold text-gray-800">
                    {{ viewLabel }}
                </div>

                <!-- month / week / day タブ -->
                <div class="flex overflow-hidden rounded border border-gray-300">
                    <button v-for="m in ['month', 'week', 'day']" :key="m"
                        class="px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="viewMode === m
                            ? 'bg-gray-700 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-50'"
                        @click="$emit('view-mode-change', m)">
                        {{ { month: '月', week: '週', day: '日' }[m] }}
                    </button>
                </div>

                <!-- 各カレンダー固有のツールバー要素 -->
                <slot name="toolbar-extra" />
            </div>

            <!-- ローディング -->
            <div v-if="loading" class="flex-1 py-12 text-center text-sm text-gray-400">読み込み中…</div>

            <!-- カレンダー本体 -->
            <div v-else ref="scrollEl" class="min-h-0 flex-1 overflow-auto p-3">
                <slot />
            </div>
        </div>
    </div>
</template>
