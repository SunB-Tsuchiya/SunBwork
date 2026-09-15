<script setup>
import { CLERK_EVENT_COLORS } from '@/Components/Clerk/clerkEventColors';
import { changeNotice, scheduleLabel } from '@/Components/Clerk/clerkScheduleRuleLabels';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';

defineProps({ rules: { type: Array, default: () => [] } });
const busy = ref(false);
const error = ref('');
function change(rule, remove = false) {
    const action = remove ? '削除' : rule.is_active ? '停止' : '再開';
    if (!confirm(`「${rule.title}」を${action}しますか？\n${changeNotice}`)) return;
    busy.value = true;
    error.value = '';
    const options = {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
        onError: () => {
            error.value = '変更できませんでした。もう一度お試しください。';
        },
    };
    if (remove) router.delete(route('clerk.calendar.schedule_rules.destroy', { scheduleRule: rule.id }), options);
    else router.patch(route('clerk.calendar.schedule_rules.toggle', { scheduleRule: rule.id }), { is_active: !rule.is_active }, options);
}
</script>

<template>
    <AppLayout title="予定日設定">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.calendar.settings')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300"
                    >← 設定に戻る</Link
                >
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">予定日設定</h2>
            </div>
        </template>
        <div class="rounded bg-white p-6 shadow">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">交通費申請・外注費提出などの予定を、指定した日付に自動登録します。</p>
                <Link
                    :href="route('clerk.calendar.schedule_rules.create')"
                    class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700"
                    >予定日設定を追加</Link
                >
            </div>
            <p v-if="error" role="alert" class="mb-3 text-sm text-red-600">{{ error }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left">タイトル</th>
                            <th class="px-3 py-3 text-left">予定日</th>
                            <th class="px-3 py-3 text-left">適用期間</th>
                            <th class="px-3 py-3 text-left">状態</th>
                            <th class="px-3 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="rule in rules" :key="rule.id" class="hover:bg-gray-50">
                            <td class="px-3 py-3">
                                <span
                                    class="mr-2 inline-block h-3 w-3 rounded-full"
                                    :style="{ backgroundColor: CLERK_EVENT_COLORS[rule.color_key]?.hex }"
                                ></span
                                >{{ rule.title }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">{{ scheduleLabel(rule) }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ rule.starts_on }} 〜 {{ rule.ends_on || '期限なし' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <span :class="rule.is_active ? 'text-purple-700' : 'text-gray-500'">{{ rule.is_active ? '有効' : '停止中' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <Link
                                    :href="route('clerk.calendar.schedule_rules.edit', { scheduleRule: rule.id })"
                                    class="mr-3 text-purple-700 hover:underline"
                                    >編集</Link
                                >
                                <button :disabled="busy" class="mr-3 text-gray-600 hover:underline disabled:opacity-50" @click="change(rule)">
                                    {{ rule.is_active ? '停止' : '再開' }}
                                </button>
                                <button :disabled="busy" class="text-red-600 hover:underline disabled:opacity-50" @click="change(rule, true)">
                                    削除
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rules.length">
                            <td colspan="5" class="px-3 py-8 text-center text-gray-500">予定日設定はまだありません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-gray-500">{{ changeNotice }} 個別に削除した回は、再開しても復活しません。</p>
        </div>
    </AppLayout>
</template>
