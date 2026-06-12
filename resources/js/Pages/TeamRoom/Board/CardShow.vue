<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    team:  { type: Object, required: true },
    board: { type: Object, required: true },
    card:  { type: Object, required: true },
});

const colorMap = {
    yellow: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    blue:   'bg-blue-100 text-blue-800 border-blue-300',
    green:  'bg-green-100 text-green-800 border-green-300',
    red:    'bg-red-100 text-red-800 border-red-300',
    purple: 'bg-purple-100 text-purple-800 border-purple-300',
    orange: 'bg-orange-100 text-orange-800 border-orange-300',
    gray:   'bg-gray-100 text-gray-800 border-gray-300',
};

function colBadge(color) {
    return colorMap[color] || colorMap.gray;
}

function formatDate(d) {
    if (!d) return '';
    return String(d).slice(0, 16).replace('T', ' ');
}

function deleteCard() {
    if (!confirm('このカードを削除しますか？')) return;
    router.delete(route('team-rooms.board.cards.destroy', { team: props.team.id, card: props.card.id }));
}

// ── カードカラー ──────────────────────────────────────────────────
const CARD_COLORS = {
    indigo: { swatch: 'bg-indigo-400',  border: 'border-l-indigo-400', bg: 'bg-indigo-50'  },
    blue:   { swatch: 'bg-blue-400',    border: 'border-l-blue-400',   bg: 'bg-blue-50'    },
    teal:   { swatch: 'bg-teal-500',    border: 'border-l-teal-500',   bg: 'bg-teal-50'    },
    green:  { swatch: 'bg-green-500',   border: 'border-l-green-500',  bg: 'bg-green-50'   },
    yellow: { swatch: 'bg-yellow-400',  border: 'border-l-yellow-400', bg: 'bg-yellow-50'  },
    orange: { swatch: 'bg-orange-400',  border: 'border-l-orange-400', bg: 'bg-orange-50'  },
    red:    { swatch: 'bg-red-400',     border: 'border-l-red-400',    bg: 'bg-red-50'     },
    pink:   { swatch: 'bg-pink-400',    border: 'border-l-pink-400',   bg: 'bg-pink-50'    },
    purple: { swatch: 'bg-purple-400',  border: 'border-l-purple-400', bg: 'bg-purple-50'  },
    gray:   { swatch: 'bg-gray-400',    border: 'border-l-gray-400',   bg: 'bg-gray-100'   },
};

const currentColor = ref(props.card.card_color ?? null);

async function setColor(key) {
    const prev = currentColor.value;
    currentColor.value = key;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('team-rooms.board.cards.updateColor', { team: props.team.id, card: props.card.id }),
            { card_color: key },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
    } catch {
        currentColor.value = prev;
    }
}

function cardBorderClass() {
    const c = currentColor.value;
    return c && CARD_COLORS[c] ? [CARD_COLORS[c].border, CARD_COLORS[c].bg] : 'border-l-gray-200';
}
</script>

<template>
    <AppLayout :title="`${team.name} - カード詳細`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('team-rooms.show', { team: team.id }) + '?tab=board'"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← ボードに戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">カード詳細</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow border-l-4 transition-colors" :class="cardBorderClass()">
            <!-- カラムバッジ + カードカラー選択 -->
            <div class="mb-4 flex items-center justify-between gap-3">
                <span
                    class="inline-block rounded border px-3 py-1 text-sm font-medium"
                    :class="colBadge(card.column?.color)"
                >{{ card.column?.name ?? '（未設定）' }}</span>

                <!-- カードカラー選択スウォッチ -->
                <div class="flex items-center gap-1">
                    <span class="mr-1 text-xs text-gray-400">カード色：</span>
                    <button
                        v-for="(cfg, key) in CARD_COLORS"
                        :key="key"
                        type="button"
                        :title="key"
                        :class="[
                            cfg.swatch,
                            'h-5 w-5 rounded-full border-2 transition-transform hover:scale-110',
                            currentColor === key ? 'border-gray-700 scale-110' : 'border-white',
                        ]"
                        @click="setColor(key)"
                    />
                    <button
                        v-if="currentColor"
                        type="button"
                        class="ml-1 rounded border border-gray-200 px-1.5 py-0.5 text-xs text-gray-400 hover:bg-gray-100"
                        title="色をリセット"
                        @click="setColor(null)"
                    >×</button>
                </div>
            </div>

            <!-- タイトル -->
            <h3 class="mb-4 text-xl font-bold text-gray-900">{{ card.title }}</h3>

            <!-- 説明 -->
            <div class="mb-6">
                <p class="mb-1 text-xs font-semibold text-gray-500">説明</p>
                <div class="whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 min-h-[80px]">
                    {{ card.description || '（説明なし）' }}
                </div>
            </div>

            <!-- メタ情報 -->
            <dl class="mb-6 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-gray-500">作成者</dt>
                    <dd class="text-gray-800">{{ card.creator?.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500">作成日時</dt>
                    <dd class="text-gray-800">{{ formatDate(card.created_at) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500">最終更新</dt>
                    <dd class="text-gray-800">{{ formatDate(card.updated_at) }}</dd>
                </div>
            </dl>

            <!-- ボタン -->
            <div class="flex items-center gap-3">
                <Link
                    :href="route('team-rooms.board.cards.edit', { team: team.id, card: card.id })"
                    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >編集</Link>
                <Link
                    :href="route('team-rooms.show', { team: team.id }) + '?tab=board'"
                    class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >ボードに戻る</Link>
                <button
                    type="button"
                    class="ml-auto rounded border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                    @click="deleteCard"
                >削除</button>
            </div>
        </div>
    </AppLayout>
</template>
