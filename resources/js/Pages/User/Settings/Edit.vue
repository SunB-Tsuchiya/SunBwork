<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    setting:   { type: Object, default: null },
    worktypes: { type: Array, default: () => [] },
});

const form = useForm({
    worktype_id:   props.setting?.worktype_id ?? '',
    calendar_view: props.setting?.calendar_view ?? 'timeGridWeek',
});

function submit() {
    form.put(route('user.settings.update'));
}
</script>

<template>
    <AppLayout title="設定編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-blue-800">設定編集</h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="settings" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="max-w-md space-y-5">

                <!-- 基本勤務形態 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">基本勤務形態</label>
                    <p class="mb-1 text-xs text-gray-500">日報作成時にデフォルトで選択される勤務形態です。</p>
                    <select
                        v-model="form.worktype_id"
                        class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    >
                        <option value="">未設定</option>
                        <option v-for="wt in worktypes" :key="wt.id" :value="wt.id">
                            {{ wt.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.worktype_id" class="mt-1 text-xs text-red-600">
                        {{ form.errors.worktype_id }}
                    </p>
                </div>

                <!-- カレンダー表示 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">カレンダーの初期表示</label>
                    <p class="mb-1 text-xs text-gray-500">予定表を開いたときのデフォルト表示形式です。</p>
                    <select
                        v-model="form.calendar_view"
                        class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    >
                        <option value="timeGridWeek">週表示</option>
                        <option value="dayGridMonth">月表示</option>
                    </select>
                    <p v-if="form.errors.calendar_view" class="mt-1 text-xs text-red-600">
                        {{ form.errors.calendar_view }}
                    </p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        保存
                    </button>
                    <a :href="route('user.settings.index')" class="text-sm text-gray-500 hover:text-gray-700">
                        キャンセル
                    </a>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
