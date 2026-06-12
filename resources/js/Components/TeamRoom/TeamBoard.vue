<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import TeamBoardCard from './TeamBoardCard.vue';
import TeamBoardEditMode from './TeamBoardEditMode.vue';

const props = defineProps({
    team:  { type: Object, required: true },
    board: { type: Object, default: null },
});

const emit = defineEmits(['board-created', 'board-updated']);

const viewMode = ref('board'); // 'board' | 'list'
const editMode = ref(false);
const creating = ref(false);

// ────────────────── カラースタイル ──────────────────
const colorMap = {
    yellow: { border: 'border-yellow-400', header: 'bg-yellow-100 text-yellow-800', bg: 'bg-yellow-50',  barText: 'text-yellow-800'  },
    blue:   { border: 'border-blue-400',   header: 'bg-blue-100 text-blue-800',     bg: 'bg-blue-50',    barText: 'text-blue-800'    },
    green:  { border: 'border-green-500',  header: 'bg-green-100 text-green-800',   bg: 'bg-green-50',   barText: 'text-green-800'   },
    red:    { border: 'border-red-400',    header: 'bg-red-100 text-red-800',       bg: 'bg-red-50',     barText: 'text-red-800'     },
    purple: { border: 'border-purple-400', header: 'bg-purple-100 text-purple-800', bg: 'bg-purple-50',  barText: 'text-purple-800'  },
    orange: { border: 'border-orange-400', header: 'bg-orange-100 text-orange-800', bg: 'bg-orange-50',  barText: 'text-orange-800'  },
    gray:   { border: 'border-gray-400',   header: 'bg-gray-100 text-gray-800',     bg: 'bg-gray-50',    barText: 'text-gray-800'    },
};

function colStyle(color) {
    return colorMap[color] || colorMap.blue;
}

// ────────────────── 折り畳み ──────────────────
const openColumns = ref(new Set());

function initOpenColumns() {
    if (!props.board) return;
    openColumns.value = new Set((props.board.columns || []).map(c => c.id));
}

function toggleColumn(colId) {
    const s = new Set(openColumns.value);
    if (s.has(colId)) { s.delete(colId); } else { s.add(colId); }
    openColumns.value = s;
}

// board が渡されたら折り畳み状態を初期化
watch(() => props.board, (b) => { if (b) initOpenColumns(); }, { immediate: true });

// ────────────────── ボード作成 ──────────────────
async function createBoard() {
    creating.value = true;
    try {
        const res = await axios.post(route('team-rooms.board.store', { team: props.team.id }));
        emit('board-created', res.data);
    } catch {
        alert('ボードの作成に失敗しました');
    } finally {
        creating.value = false;
    }
}

// ────────────────── カード作成 ──────────────────
const showCardForm  = ref(false);
const cardForm      = ref({ title: '', description: '', team_board_column_id: null });
const cardSaving    = ref(false);

function openCardForm(columnId) {
    cardForm.value = { title: '', description: '', team_board_column_id: columnId };
    showCardForm.value = true;
}

async function saveCard() {
    if (!cardForm.value.title.trim()) { alert('タイトルは必須です'); return; }
    cardSaving.value = true;
    try {
        const res = await axios.post(route('team-rooms.board.cards.store', { team: props.team.id }), cardForm.value);
        const col = (props.board?.columns || []).find(c => c.id === cardForm.value.team_board_column_id);
        if (col) {
            if (!col.cards) col.cards = [];
            col.cards.push(res.data);
        }
        showCardForm.value = false;
        emit('board-updated', props.board);
    } catch {
        alert('カードの作成に失敗しました');
    } finally {
        cardSaving.value = false;
    }
}

// ────────────────── カード移動 ──────────────────
async function moveCard(card, targetColumnId) {
    try {
        await axios.put(route('team-rooms.board.cards.update', { team: props.team.id, card: card.id }), {
            team_board_column_id: targetColumnId,
        });
        const board = props.board;
        if (!board) return;
        for (const col of board.columns) {
            const idx = (col.cards || []).findIndex(c => c.id === card.id);
            if (idx >= 0) { col.cards.splice(idx, 1); break; }
        }
        const targetCol = board.columns.find(c => c.id === targetColumnId);
        if (targetCol) {
            if (!targetCol.cards) targetCol.cards = [];
            targetCol.cards.push({ ...card, team_board_column_id: targetColumnId });
        }
        emit('board-updated', props.board);
    } catch {
        alert('移動に失敗しました');
    }
}

// ────────────────── カードカラー変更 ──────────────────
async function changeCardColor(card, colorKey) {
    const prev = card.card_color;
    card.card_color = colorKey;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('team-rooms.board.cards.updateColor', { team: props.team.id, card: card.id }),
            { card_color: colorKey },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
    } catch {
        card.card_color = prev;
    }
}

// ────────────────── カード削除 ──────────────────
async function deleteCard(card) {
    if (!confirm('このカードを削除しますか？')) return;
    try {
        await axios.delete(route('team-rooms.board.cards.destroy', { team: props.team.id, card: card.id }));
        const board = props.board;
        if (!board) return;
        for (const col of board.columns) {
            const idx = (col.cards || []).findIndex(c => c.id === card.id);
            if (idx >= 0) { col.cards.splice(idx, 1); break; }
        }
        emit('board-updated', props.board);
    } catch {
        alert('削除に失敗しました');
    }
}

function onBoardUpdated(updatedBoard) {
    emit('board-updated', updatedBoard);
    editMode.value = false;
}

// ────────────────── ドラッグ＆ドロップ ──────────────────
const draggedCard       = ref(null);
const dragOverColId     = ref(null);
const dragOverCardId    = ref(null);
const dragInsertPosition = ref(null); // 'before' | 'after'

function onDragStart(card) {
    draggedCard.value = card;
}

function onDragOver(colId) {
    if (!draggedCard.value) return;
    dragOverColId.value = colId;
}

function onDragOverCard(event, card, colId) {
    if (!draggedCard.value) return;
    if (draggedCard.value.team_board_column_id !== colId) return;
    dragOverCardId.value = card.id;
    const rect = event.currentTarget.getBoundingClientRect();
    dragInsertPosition.value = event.clientY < rect.top + rect.height / 2 ? 'before' : 'after';
}

function onDragLeave() {
    dragOverColId.value      = null;
    dragOverCardId.value     = null;
    dragInsertPosition.value = null;
}

async function onDrop(colId) {
    dragOverColId.value  = null;
    const overCardId = dragOverCardId.value;
    const insertPos  = dragInsertPosition.value;
    dragOverCardId.value     = null;
    dragInsertPosition.value = null;
    if (!draggedCard.value) return;

    const card = draggedCard.value;
    draggedCard.value = null;

    // 同カラム内並び替え
    if (card.team_board_column_id === colId) {
        if (overCardId !== null && overCardId !== card.id) {
            await reorderCards(card, colId, overCardId, insertPos ?? 'before');
        }
        return;
    }

    // 別カラムへ移動
    await moveCard(card, colId);
}

function onDragEnd() {
    draggedCard.value        = null;
    dragOverColId.value      = null;
    dragOverCardId.value     = null;
    dragInsertPosition.value = null;
}

// ────────────────── カード並び替え ──────────────────
async function reorderCards(movingCard, colId, targetCardId, position) {
    const col = props.board?.columns.find(c => c.id === colId);
    if (!col || !col.cards) return;

    const cards = [...col.cards];
    const fromIdx = cards.findIndex(c => c.id === movingCard.id);
    if (fromIdx === -1) return;

    cards.splice(fromIdx, 1);
    let toIdx = cards.findIndex(c => c.id === targetCardId);
    if (toIdx === -1) {
        cards.push(movingCard);
    } else {
        if (position === 'after') toIdx += 1;
        cards.splice(toIdx, 0, movingCard);
    }

    col.cards = cards;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        await axios.patch(
            route('team-rooms.board.cards.reorder', { team: props.team.id }),
            { ids: cards.map(c => c.id) },
            { headers: { 'X-CSRF-TOKEN': csrf } }
        );
    } catch { /* no-op */ }
}

// ────────────────── 一覧ビュー ──────────────────
const allCards = computed(() => {
    if (!props.board) return [];
    const result = [];
    for (const col of (props.board.columns || [])) {
        for (const card of (col.cards || [])) {
            result.push({ ...card, columnName: col.name, columnColor: col.color });
        }
    }
    return result;
});

// 一覧フィルター
const listSearch  = ref('');
const listYear    = ref('');
const listMonth   = ref('');
const listSortDir = ref('desc'); // 'asc' | 'desc'

const availableYears = computed(() => {
    const years = new Set(allCards.value.map(c => {
        const d = c.created_at ? String(c.created_at).slice(0, 4) : null;
        return d;
    }).filter(Boolean));
    return [...years].sort((a, b) => b - a);
});

const availableMonths = computed(() => {
    if (!listYear.value) return [];
    const months = new Set(allCards.value
        .filter(c => c.created_at && String(c.created_at).startsWith(listYear.value))
        .map(c => String(c.created_at).slice(5, 7)));
    return [...months].sort();
});

const filteredCards = computed(() => {
    let list = allCards.value;

    if (listSearch.value.trim()) {
        const q = listSearch.value.toLowerCase();
        list = list.filter(c =>
            (c.title || '').toLowerCase().includes(q) ||
            (c.description || '').toLowerCase().includes(q),
        );
    }

    if (listYear.value) {
        list = list.filter(c => c.created_at && String(c.created_at).startsWith(listYear.value));
    }

    if (listMonth.value) {
        list = list.filter(c => c.created_at && String(c.created_at).slice(5, 7) === listMonth.value);
    }

    return [...list].sort((a, b) => {
        const av = a.created_at ?? '';
        const bv = b.created_at ?? '';
        if (av === bv) return 0;
        return (av < bv ? -1 : 1) * (listSortDir.value === 'asc' ? 1 : -1);
    });
});

function resetListFilters() {
    listSearch.value  = '';
    listYear.value    = '';
    listMonth.value   = '';
    listSortDir.value = 'desc';
}
</script>

<template>
    <div>
        <!-- ボードなし -->
        <div v-if="!board" class="flex flex-col items-center justify-center py-20">
            <p class="mb-6 text-gray-400">ボードがまだ作成されていません</p>
            <button
                type="button"
                :disabled="creating"
                class="rounded bg-indigo-600 px-6 py-3 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                @click="createBoard"
            >{{ creating ? '作成中...' : '+ ボードを作成する' }}</button>
        </div>

        <!-- ボードあり -->
        <template v-else>

            <!-- ヘッダー -->
            <div class="mb-4 flex items-center gap-3">
                <h3 class="font-semibold text-gray-800">{{ board.name }}</h3>
                <div class="flex gap-1 rounded border border-gray-200 bg-gray-100 p-0.5 text-xs">
                    <button
                        type="button"
                        :class="['rounded px-3 py-1 font-medium transition', viewMode === 'board' ? 'bg-white text-gray-800 shadow' : 'text-gray-500 hover:text-gray-700']"
                        @click="viewMode = 'board'"
                    >ボード</button>
                    <button
                        type="button"
                        :class="['rounded px-3 py-1 font-medium transition', viewMode === 'list' ? 'bg-white text-gray-800 shadow' : 'text-gray-500 hover:text-gray-700']"
                        @click="viewMode = 'list'"
                    >一覧</button>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <button
                        v-if="viewMode === 'board' && !editMode"
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                        @click="openCardForm(board.columns[0]?.id)"
                    >+ カード追加</button>
                    <button
                        type="button"
                        :class="['rounded border px-3 py-1.5 text-xs font-medium transition',
                            editMode ? 'border-gray-400 bg-gray-600 text-white hover:bg-gray-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50']"
                        @click="editMode = !editMode"
                    >{{ editMode ? '編集を終了' : '編集モード' }}</button>
                </div>
            </div>

            <!-- 編集モード -->
            <TeamBoardEditMode
                v-if="editMode"
                :team="team"
                :board="board"
                @updated="onBoardUpdated"
                @cancel="editMode = false"
            />

            <!-- ── ボードビュー（全幅） ── -->
            <template v-else-if="viewMode === 'board'">
                <div
                    style="width: 100vw; margin-left: calc(-50vw + 50%); padding-left: 1.5rem; padding-right: 1.5rem; box-sizing: border-box;"
                >
                    <div
                        class="flex gap-4 overflow-x-auto overflow-y-hidden"
                        style="height: calc(100vh - 220px);"
                    >
                        <div
                            v-for="col in board.columns"
                            :key="col.id"
                            :class="[
                                'flex flex-col rounded-lg border-t-4 transition-all',
                                colStyle(col.color).border,
                                colStyle(col.color).bg,
                                dragOverColId === col.id ? 'ring-2 ring-indigo-400' : '',
                            ]"
                            :style="openColumns.has(col.id)
                                ? 'flex: 1 0 240px; min-width: 240px;'
                                : 'flex: 0 0 2.75rem; width: 2.75rem;'"
                            @dragover.prevent="onDragOver(col.id)"
                            @dragleave="onDragLeave"
                            @drop.prevent="onDrop(col.id)"
                        >
                            <!-- 展開状態 -->
                            <template v-if="openColumns.has(col.id)">
                                <!-- カラムヘッダー -->
                                <div
                                    :class="['flex cursor-pointer items-center justify-between rounded-t px-3 py-2 text-sm font-medium select-none shrink-0', colStyle(col.color).header]"
                                    @click="toggleColumn(col.id)"
                                >
                                    <span>{{ col.name }}</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded-full bg-white/60 px-2 text-xs">{{ (col.cards || []).length }}</span>
                                        <span class="text-xs opacity-60">◀</span>
                                    </div>
                                </div>
                                <!-- カード領域（スクロール可） -->
                                <div class="flex-1 space-y-2 overflow-y-auto p-2">
                                    <div
                                        v-for="card in col.cards"
                                        :key="card.id"
                                        draggable="true"
                                        @dragstart="onDragStart(card)"
                                        @dragend="onDragEnd"
                                        @dragover.prevent="onDragOverCard($event, card, col.id)"
                                        @click="router.get(route('team-rooms.board.cards.show', { team: team.id, card: card.id }))"
                                        class="cursor-grab active:cursor-grabbing"
                                        :class="dragOverCardId === card.id && draggedCard?.id !== card.id && draggedCard?.team_board_column_id === col.id
                                            ? (dragInsertPosition === 'before' ? 'border-t-2 border-t-indigo-500' : 'border-b-2 border-b-indigo-500')
                                            : ''"
                                    >
                                        <TeamBoardCard
                                            :card="card"
                                            :columns="board.columns"
                                            @move="moveCard"
                                            @delete="deleteCard"
                                            @color-change="changeCardColor"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- 折り畳み状態（縦バー） -->
                            <template v-else>
                                <div
                                    :class="['flex h-full cursor-pointer flex-col items-center py-3 select-none rounded-lg', colStyle(col.color).header]"
                                    @click="toggleColumn(col.id)"
                                >
                                    <span class="text-xs font-bold" :class="colStyle(col.color).barText">▶</span>
                                    <span
                                        class="mt-2 text-xs font-semibold leading-none"
                                        :class="colStyle(col.color).barText"
                                        style="writing-mode: vertical-rl; text-orientation: mixed;"
                                    >{{ col.name }}</span>
                                    <span
                                        class="mt-2 rounded bg-white/80 px-1 py-0.5 text-center font-semibold leading-none"
                                        style="font-size: 11px; min-width: 1.6rem;"
                                    >{{ (col.cards || []).length }}</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ── 一覧ビュー ── -->
            <template v-else>
                <!-- フィルターバー -->
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <input
                        v-model="listSearch"
                        type="text"
                        placeholder="キーワード検索..."
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                        style="min-width: 160px;"
                    />
                    <select
                        v-model="listYear"
                        class="rounded border border-gray-300 px-2 py-1.5 text-sm"
                        @change="listMonth = ''"
                    >
                        <option value="">年（全て）</option>
                        <option v-for="y in availableYears" :key="y" :value="y">{{ y }}年</option>
                    </select>
                    <select
                        v-model="listMonth"
                        class="rounded border border-gray-300 px-2 py-1.5 text-sm"
                        :disabled="!listYear"
                    >
                        <option value="">月（全て）</option>
                        <option v-for="m in availableMonths" :key="m" :value="m">{{ parseInt(m) }}月</option>
                    </select>
                    <button
                        type="button"
                        :class="['rounded border px-3 py-1.5 text-xs font-medium', listSortDir === 'asc' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50']"
                        @click="listSortDir = 'asc'"
                    >↑昇順</button>
                    <button
                        type="button"
                        :class="['rounded border px-3 py-1.5 text-xs font-medium', listSortDir === 'desc' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50']"
                        @click="listSortDir = 'desc'"
                    >↓降順</button>
                    <button
                        v-if="listSearch || listYear || listMonth"
                        type="button"
                        class="rounded border border-gray-200 px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-50"
                        @click="resetListFilters"
                    >クリア</button>
                    <button
                        type="button"
                        class="ml-auto rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="openCardForm(board.columns[0]?.id)"
                    >+ カード作成</button>
                </div>

                <div v-if="filteredCards.length === 0" class="py-8 text-center text-sm text-gray-400">
                    {{ allCards.length === 0 ? 'カードがありません' : '条件に一致するカードがありません' }}
                </div>

                <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">タイトル</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">ステータス</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">作成日</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr
                            v-for="card in filteredCards"
                            :key="card.id"
                            class="cursor-pointer hover:bg-gray-50"
                            @click="router.get(route('team-rooms.board.cards.show', { team: team.id, card: card.id }))"
                        >
                            <td class="px-4 py-2 font-medium text-gray-800">
                                <div>{{ card.title }}</div>
                                <div v-if="card.description" class="mt-0.5 text-xs text-gray-400">{{ String(card.description).slice(0, 60) }}</div>
                            </td>
                            <td class="px-4 py-2">
                                <span class="rounded border px-2 py-0.5 text-xs font-medium" :class="colStyle(card.columnColor).header">
                                    {{ card.columnName }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ card.created_at ? String(card.created_at).slice(0, 10) : '-' }}</td>
                            <td class="px-4 py-2" @click.stop>
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="route('team-rooms.board.cards.show', { team: team.id, card: card.id })"
                                        class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-50"
                                    >詳細</Link>
                                    <Link
                                        :href="route('team-rooms.board.cards.edit', { team: team.id, card: card.id })"
                                        class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50"
                                    >編集</Link>
                                    <button
                                        type="button"
                                        class="rounded border border-red-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                                        @click.stop="deleteCard(card)"
                                    >削除</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

        </template>

        <!-- カード作成モーダル -->
        <div v-if="showCardForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h4 class="mb-4 text-base font-semibold text-gray-800">カードを作成</h4>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">タイトル <span class="text-red-500">*</span></label>
                        <input v-model="cardForm.title" type="text" maxlength="255"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">説明</label>
                        <textarea v-model="cardForm.description" rows="3"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"></textarea>
                    </div>
                    <div v-if="board">
                        <label class="mb-1 block text-xs font-medium text-gray-600">カラム</label>
                        <select v-model="cardForm.team_board_column_id"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="col in board.columns" :key="col.id" :value="col.id">{{ col.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                        @click="showCardForm = false">キャンセル</button>
                    <button type="button"
                        :disabled="cardSaving"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                        @click="saveCard">{{ cardSaving ? '保存中...' : '作成' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
