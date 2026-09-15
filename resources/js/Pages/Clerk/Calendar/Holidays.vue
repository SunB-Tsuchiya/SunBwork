<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({ year: Number, holidays: Array, defaultYears: Array });
const selectedYear = ref(props.year);
const editingId = ref(null);
const form = useForm({ year: props.year, date: '', name: '' });
const deleting = ref(null);
const notice = ref('');
const years = Array.from({ length: 201 }, (_, i) => 1900 + i);

function reset() {
    editingId.value = null;
    form.reset();
    form.year = props.year;
    form.clearErrors();
}
watch(() => props.year, (year) => { selectedYear.value = year; reset(); });
function changeYear() {
    notice.value = '';
    router.get(route('clerk.calendar.holidays.index'), { year: selectedYear.value });
}
function edit(holiday) {
    form.clearErrors();
    editingId.value = holiday.id;
    form.year = props.year;
    form.date = holiday.date;
    form.name = holiday.name;
    notice.value = '';
}
function save() {
    const options = { preserveScroll: true, onSuccess: () => { reset(); notice.value = '保存しました。カレンダーに反映されます。'; } };
    if (editingId.value) form.put(route('clerk.calendar.holidays.update', { holiday: editingId.value }), options);
    else form.post(route('clerk.calendar.holidays.store'), options);
}
function remove(holiday) {
    if (!confirm(`${holiday.date}「${holiday.name}」を削除しますか？`)) return;
    deleting.value = holiday.id;
    router.delete(route('clerk.calendar.holidays.destroy', { holiday: holiday.id }), {
        preserveScroll: true,
        onSuccess: () => { if (editingId.value === holiday.id) reset(); notice.value = '削除しました。'; },
        onError: () => { notice.value = '削除できませんでした。もう一度お試しください。'; },
        onFinish: () => { deleting.value = null; },
    });
}
</script>

<template>
    <AppLayout title="祝日設定">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.calendar.settings')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300">← 設定に戻る</Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">祝日設定</h2>
            </div>
        </template>
        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <label for="holiday-year" class="text-sm font-medium">表示年</label>
                <select id="holiday-year" v-model.number="selectedYear" :disabled="form.processing || deleting !== null" class="rounded border-gray-300 text-sm" @change="changeYear">
                    <option v-for="year in years" :key="year" :value="year">{{ year }}年</option>
                </select>
                <Link :href="route('clerk.calendar')" class="text-sm text-purple-700 hover:underline">カレンダーへ</Link>
            </div>
            <p class="mb-2 text-sm text-gray-600">登録した日付は、会社共通のカレンダーで赤く表示されます。</p>
            <p class="mb-5 text-xs text-gray-500">
                {{ defaultYears.join('・') }}年は公表済みの祝日を初期登録しています。その他の年は日付を確認して追加してください。削除した祝日は自動では戻りません。
                <a href="https://www8.cao.go.jp/chosei/shukujitsu/gaiyou.html" target="_blank" rel="noopener noreferrer" class="text-purple-700 underline">内閣府の祝日一覧</a>
            </p>
            <p v-if="notice" role="status" class="mb-4 rounded bg-purple-50 p-3 text-sm text-purple-800">{{ notice }}</p>
            <form class="mb-5 rounded border border-gray-200 bg-gray-50 p-4" @submit.prevent="save">
                <h3 class="mb-3 text-sm font-semibold">{{ editingId ? '祝日を修正' : '祝日を追加' }}</h3>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="holiday-date" class="mb-1 block text-sm">日付</label>
                        <input id="holiday-date" v-model="form.date" type="date" required :min="`${year}-01-01`" :max="`${year}-12-31`" class="rounded border-gray-300 text-sm" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="holiday-name" class="mb-1 block text-sm">祝日名</label>
                        <input id="holiday-name" v-model="form.name" type="text" maxlength="100" required class="w-full rounded border-gray-300 text-sm" placeholder="祝日名を入力" />
                    </div>
                    <button :disabled="form.processing || deleting !== null" class="rounded bg-purple-600 px-4 py-2 text-sm text-white hover:bg-purple-700 disabled:opacity-50">{{ form.processing ? '保存中…' : editingId ? '保存' : '追加' }}</button>
                    <button v-if="editingId" type="button" :disabled="form.processing" class="rounded bg-gray-200 px-3 py-2 text-sm" @click="reset">キャンセル</button>
                </div>
                <p v-for="(error, key) in form.errors" :key="key" role="alert" class="mt-2 text-sm text-red-600">{{ error }}</p>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left">日付</th><th class="px-4 py-3 text-left">祝日名</th><th class="px-4 py-3 text-right">操作</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="holiday in holidays" :key="holiday.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">{{ holiday.date }}</td>
                            <td class="break-words px-4 py-3">{{ holiday.name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button :disabled="form.processing || deleting !== null" class="mr-4 text-purple-700 hover:underline disabled:opacity-50" @click="edit(holiday)">修正</button>
                                <button :disabled="form.processing || deleting !== null" class="text-red-600 hover:underline disabled:opacity-50" @click="remove(holiday)">{{ deleting === holiday.id ? '削除中…' : '削除' }}</button>
                            </td>
                        </tr>
                        <tr v-if="!holidays.length"><td colspan="3" class="px-4 py-8 text-center text-gray-500">この年の祝日は未登録です。上のフォームから追加できます。</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
