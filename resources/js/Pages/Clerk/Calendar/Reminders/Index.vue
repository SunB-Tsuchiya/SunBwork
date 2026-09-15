<script setup>
import { CLERK_EVENT_COLORS } from '@/Components/Clerk/clerkEventColors';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    reminders: { type: Array, default: () => [] },
    today: { type: String, required: true },
});

const busyId = ref(null);

function status(reminder) {
    if (!reminder.is_active) return { label: '停止中', classes: 'bg-gray-100 text-gray-600' };
    if (props.today < reminder.starts_on) return { label: '表示予定', classes: 'bg-blue-100 text-blue-700' };
    if (props.today > reminder.ends_on) return { label: '終了', classes: 'bg-gray-100 text-gray-600' };
    return { label: '表示中', classes: 'bg-amber-100 text-amber-800' };
}

function toggle(reminder) {
    const action = reminder.is_active ? '停止' : '再開';
    if (!confirm(`このリマインダーを${action}しますか？`)) return;
    busyId.value = reminder.id;
    router.patch(
        route('clerk.reminders.toggle', { reminder: reminder.id }),
        { is_active: !reminder.is_active },
        { preserveScroll: true, onFinish: () => { busyId.value = null; } },
    );
}

function remove(reminder) {
    if (!confirm('このリマインダーを削除しますか？')) return;
    busyId.value = reminder.id;
    router.delete(route('clerk.reminders.destroy', { reminder: reminder.id }), {
        preserveScroll: true,
        onFinish: () => { busyId.value = null; },
    });
}
</script>

<template>
    <AppLayout title="リマインダー設定">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.calendar')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300">
                    ← カレンダーに戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">リマインダー設定</h2>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">表示期間中、ユーザーのカレンダー上部へ案内を表示します。</p>
                <Link :href="route('clerk.reminders.create')" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    リマインダーを追加
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left">リマインド内容</th>
                            <th class="px-3 py-3 text-left">表示期間</th>
                            <th class="px-3 py-3 text-left">状態</th>
                            <th class="px-3 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="reminder in reminders" :key="reminder.id" class="hover:bg-gray-50">
                            <td class="min-w-72 px-3 py-3 whitespace-pre-wrap break-words">
                                <span class="mr-2 inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: CLERK_EVENT_COLORS[reminder.color_key]?.hex }"></span>
                                {{ reminder.content }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">{{ reminder.starts_on }} ～ {{ reminder.ends_on }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="status(reminder).classes">{{ status(reminder).label }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <Link :href="route('clerk.reminders.edit', { reminder: reminder.id })" class="mr-3 text-purple-700 hover:underline">編集</Link>
                                <button :disabled="busyId === reminder.id" class="mr-3 text-gray-600 hover:underline disabled:opacity-50" @click="toggle(reminder)">
                                    {{ reminder.is_active ? '停止' : '再開' }}
                                </button>
                                <button :disabled="busyId === reminder.id" class="text-red-600 hover:underline disabled:opacity-50" @click="remove(reminder)">削除</button>
                            </td>
                        </tr>
                        <tr v-if="!reminders.length">
                            <td colspan="4" class="px-3 py-8 text-center text-gray-500">リマインダーはまだありません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
