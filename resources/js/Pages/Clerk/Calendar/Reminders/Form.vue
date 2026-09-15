<script setup>
import { CLERK_EVENT_COLORS, CLERK_EVENT_COLOR_KEYS } from '@/Components/Clerk/clerkEventColors';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    reminder: { type: Object, default: null },
    today: { type: String, required: true },
});

const editing = computed(() => Boolean(props.reminder?.id));
const form = useForm({
    content: props.reminder?.content ?? '',
    color_key: props.reminder?.color_key ?? 'orange',
    starts_on: props.reminder?.starts_on ?? props.today,
    ends_on: props.reminder?.ends_on ?? props.today,
    is_active: props.reminder?.is_active ?? true,
});

function submit() {
    if (editing.value) {
        form.put(route('clerk.reminders.update', { reminder: props.reminder.id }));
        return;
    }
    form.post(route('clerk.reminders.store'));
}
</script>

<template>
    <AppLayout :title="editing ? 'リマインダー編集' : 'リマインダー追加'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.reminders.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ editing ? 'リマインダー編集' : 'リマインダー追加' }}</h2>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form class="space-y-6" @submit.prevent="submit">
                <div>
                    <label for="reminder-content" class="mb-1 block text-sm font-medium text-gray-700">リマインド内容 <span class="text-red-600">*</span></label>
                    <textarea
                        id="reminder-content"
                        v-model="form.content"
                        rows="5"
                        maxlength="1000"
                        class="w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500"
                        placeholder="例：交通費精算の締め切りは9月25日です。"
                    ></textarea>
                    <div class="mt-1 flex justify-between gap-3">
                        <p class="text-xs text-red-600">{{ form.errors.content }}</p>
                        <p class="text-xs text-gray-500">{{ form.content.length }} / 1000</p>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-gray-700">色 <span class="text-red-600">*</span></legend>
                    <div class="flex flex-wrap gap-3">
                        <label v-for="key in CLERK_EVENT_COLOR_KEYS" :key="key" class="cursor-pointer">
                            <input v-model="form.color_key" type="radio" name="reminder-color" :value="key" class="sr-only" />
                            <span
                                class="block h-7 w-7 rounded-full border-2 transition"
                                :class="form.color_key === key ? 'border-gray-800 ring-2 ring-purple-200' : 'border-transparent'"
                                :style="{ backgroundColor: CLERK_EVENT_COLORS[key].hex }"
                                :title="key"
                            ></span>
                        </label>
                    </div>
                    <p v-if="form.errors.color_key" class="mt-1 text-xs text-red-600">{{ form.errors.color_key }}</p>
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="reminder-start" class="mb-1 block text-sm font-medium text-gray-700">表示開始日 <span class="text-red-600">*</span></label>
                        <input id="reminder-start" v-model="form.starts_on" type="date" min="1900-01-01" max="2100-12-31" class="w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" />
                        <p class="mt-1 text-xs text-red-600">{{ form.errors.starts_on }}</p>
                    </div>
                    <div>
                        <label for="reminder-end" class="mb-1 block text-sm font-medium text-gray-700">表示終了日 <span class="text-red-600">*</span></label>
                        <input id="reminder-end" v-model="form.ends_on" type="date" :min="form.starts_on" max="2100-12-31" class="w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" />
                        <p class="mt-1 text-xs text-red-600">{{ form.errors.ends_on }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded text-purple-600 focus:ring-purple-500" />
                    このリマインダーを有効にする
                </label>
                <p class="text-xs text-gray-500">開始日と終了日を含む期間中、対象会社のユーザーのカレンダーに表示します。</p>

                <div v-if="Object.keys(form.errors).length" role="alert" class="rounded border border-red-200 bg-red-50 p-3">
                    <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-700">{{ error }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <button :disabled="form.processing" class="rounded bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                        {{ form.processing ? '保存中…' : editing ? '保存する' : '登録する' }}
                    </button>
                    <Link :href="route('clerk.reminders.index')" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">キャンセル</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
