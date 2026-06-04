<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    team:  { type: Object, required: true },
    board: { type: Object, required: true },
    card:  { type: Object, required: true },
});

const form = useForm({
    title:                props.card.title ?? '',
    description:          props.card.description ?? '',
    team_board_column_id: props.card.team_board_column_id,
});

function save() {
    form.put(route('team-rooms.board.cards.update', { team: props.team.id, card: props.card.id }));
}
</script>

<template>
    <AppLayout :title="`${team.name} - カード編集`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('team-rooms.board.cards.show', { team: team.id, card: card.id })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 詳細に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">カード編集</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="save" class="space-y-4">

                <!-- カラム -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">カラム</label>
                    <select
                        v-model="form.team_board_column_id"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                    >
                        <option v-for="col in board.columns" :key="col.id" :value="col.id">{{ col.name }}</option>
                    </select>
                </div>

                <!-- タイトル -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="255"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.title }"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                </div>

                <!-- 説明 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">説明</label>
                    <textarea
                        v-model="form.description"
                        rows="6"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                    ></textarea>
                </div>

                <!-- ボタン -->
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                    >{{ form.processing ? '保存中...' : '保存' }}</button>
                    <Link
                        :href="route('team-rooms.board.cards.show', { team: team.id, card: card.id })"
                        class="rounded bg-gray-200 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                    >キャンセル</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
