<script setup>
import { CLERK_EVENT_COLORS, CLERK_EVENT_COLOR_KEYS } from '@/Components/Clerk/clerkEventColors';
import { changeNotice, scheduleTypes, weekdays } from '@/Components/Clerk/clerkScheduleRuleLabels';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ scheduleRule: { type: Object, default: null }, colorSettings: { type: Array, default: () => [] }, today: String });
const editing = computed(() => !!props.scheduleRule);
const form = useForm({
    title: props.scheduleRule?.title ?? '',
    description: props.scheduleRule?.description ?? '',
    color_key: props.scheduleRule?.color_key ?? 'indigo',
    recurrence: props.scheduleRule?.recurrence ?? 'monthly_day',
    day_of_month: props.scheduleRule?.day_of_month ?? 25,
    ordinal: props.scheduleRule?.ordinal ?? 2,
    day_of_week: props.scheduleRule?.day_of_week ?? 2,
    custom_dates: [...(props.scheduleRule?.custom_dates ?? [])],
    starts_on: props.scheduleRule?.starts_on ?? props.today,
    ends_on: props.scheduleRule?.ends_on ?? '',
    is_active: props.scheduleRule?.is_active ?? true,
});
const dateInput = ref('');
const previewDates = ref(null);
const previewBusy = ref(false);
const previewError = ref('');
let previewRevision = 0;
watch(
    () => form.data(),
    () => {
        previewRevision++;
        previewDates.value = null;
        previewError.value = '';
    },
    { deep: true },
);
function addDate() {
    if (dateInput.value && !form.custom_dates.includes(dateInput.value)) form.custom_dates.push(dateInput.value);
    form.custom_dates.sort();
    dateInput.value = '';
}
async function preview() {
    const revision = ++previewRevision;
    previewBusy.value = true;
    previewError.value = '';
    form.clearErrors();
    try {
        const response = await axios.post(route('clerk.calendar.schedule_rules.preview'), form.data());
        if (revision === previewRevision) previewDates.value = response.data.dates;
    } catch (error) {
        if (revision !== previewRevision) return;
        const errors = error.response?.data?.errors;
        if (errors) for (const [key, messages] of Object.entries(errors)) form.setError(key, messages[0]);
        else previewError.value = '予定日を確認できませんでした。もう一度お試しください。';
    } finally {
        previewBusy.value = false;
    }
}
function save() {
    if (editing.value && !confirm(`${changeNotice}\n日付条件を変えると、保持した個別変更分と新しい予定が両方残ることがあります。保存しますか？`))
        return;
    if (editing.value) form.put(route('clerk.calendar.schedule_rules.update', { scheduleRule: props.scheduleRule.id }));
    else form.post(route('clerk.calendar.schedule_rules.store'));
}
function colorLabel(key) {
    return props.colorSettings.find((item) => item.color_key === key)?.label || key;
}
</script>

<template>
    <AppLayout :title="editing ? '予定日設定を編集' : '予定日設定を追加'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('clerk.calendar.schedule_rules.index')"
                    class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300"
                    >← 一覧に戻る</Link
                >
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ editing ? '予定日設定を編集' : '予定日設定を追加' }}</h2>
            </div>
        </template>
        <div class="rounded bg-white p-6 shadow">
            <form class="space-y-5" @submit.prevent="save">
                <div>
                    <label for="rule-title" class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label
                    ><input
                        id="rule-title"
                        v-model="form.title"
                        type="text"
                        maxlength="255"
                        required
                        class="w-full rounded border-gray-300 p-2 text-sm"
                        placeholder="例：交通費申請"
                    />
                </div>
                <div>
                    <label for="rule-description" class="mb-1 block text-sm font-medium text-gray-700">内容</label
                    ><textarea
                        id="rule-description"
                        v-model="form.description"
                        maxlength="2000"
                        rows="3"
                        class="w-full rounded border-gray-300 p-2 text-sm"
                    ></textarea>
                </div>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-gray-700">色</legend>
                    <div class="flex flex-wrap gap-3">
                        <label v-for="key in CLERK_EVENT_COLOR_KEYS" :key="key" class="flex cursor-pointer items-center gap-1 text-xs text-gray-600">
                            <input v-model="form.color_key" type="radio" :value="key" name="rule-color" class="text-purple-600" />
                            <span class="h-5 w-5 rounded-full" :style="{ backgroundColor: CLERK_EVENT_COLORS[key].hex }"></span>
                            {{ colorLabel(key) }}
                        </label>
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-gray-700">予定日の指定方法 <span class="text-red-500">*</span></legend>
                    <div class="flex flex-wrap gap-4">
                        <label v-for="type in scheduleTypes" :key="type.value" class="flex cursor-pointer items-center gap-1.5 text-sm"
                            ><input v-model="form.recurrence" type="radio" :value="type.value" name="rule-type" class="text-purple-600" />{{
                                type.label
                            }}</label
                        >
                    </div>
                </fieldset>
                <div v-if="form.recurrence === 'monthly_day'">
                    <label for="rule-day" class="mb-1 block text-sm font-medium">日付</label>
                    <select id="rule-day" v-model.number="form.day_of_month" class="rounded border-gray-300 text-sm">
                        <option v-for="day in 31" :key="day" :value="day">毎月{{ day }}日</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        指定日が存在しない月は登録しません。毎月最後の日に登録する場合は「月末」を選んでください。
                    </p>
                </div>
                <p v-if="form.recurrence === 'month_end'" class="text-sm text-gray-600">
                    毎月の最後の日に登録します。2月は28日（うるう年は29日）になります。
                </p>
                <div v-if="form.recurrence === 'monthly_weekday'" class="space-y-3">
                    <div>
                        <label for="rule-ordinal" class="mb-1 block text-sm font-medium">週指定</label
                        ><select id="rule-ordinal" v-model.number="form.ordinal" class="rounded border-gray-300 text-sm">
                            <option v-for="n in 5" :key="n" :value="n">第{{ n }}</option>
                            <option :value="0">最終</option>
                        </select>
                    </div>
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">曜日</legend>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="(day, index) in weekdays" :key="day" class="flex items-center gap-1 text-sm"
                                ><input v-model.number="form.day_of_week" type="radio" name="rule-weekday" :value="index" class="text-purple-600" />{{
                                    day
                                }}</label
                            >
                        </div>
                    </fieldset>
                    <p class="text-xs text-gray-500">「第2火曜日」はその月の2回目の火曜日です。第5曜日がない月は登録しません。</p>
                </div>
                <div v-if="form.recurrence === 'custom_dates'">
                    <label for="rule-custom-date" class="mb-1 block text-sm font-medium">日付を追加</label>
                    <div class="flex flex-wrap gap-2">
                        <input
                            id="rule-custom-date"
                            v-model="dateInput"
                            type="date"
                            :min="form.starts_on"
                            :max="form.ends_on || '2100-12-31'"
                            class="rounded border-gray-300 text-sm"
                        /><button
                            type="button"
                            class="rounded border border-purple-400 px-3 py-1.5 text-sm text-purple-700 hover:bg-purple-50"
                            @click="addDate"
                        >
                            日付を追加
                        </button>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="date in form.custom_dates"
                            :key="date"
                            class="inline-flex items-center gap-2 rounded bg-purple-50 px-2 py-1 text-sm text-purple-800"
                            >{{ date
                            }}<button
                                type="button"
                                :aria-label="`${date}を外す`"
                                @click="form.custom_dates = form.custom_dates.filter((item) => item !== date)"
                            >
                                ×
                            </button></span
                        >
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="rule-start" class="mb-1 block text-sm font-medium">適用開始日 <span class="text-red-500">*</span></label
                        ><input
                            id="rule-start"
                            v-model="form.starts_on"
                            type="date"
                            min="1900-01-01"
                            max="2100-12-31"
                            required
                            class="w-full rounded border-gray-300 text-sm"
                        />
                    </div>
                    <div>
                        <label for="rule-end" class="mb-1 block text-sm font-medium">適用終了日（任意）</label
                        ><input
                            id="rule-end"
                            v-model="form.ends_on"
                            type="date"
                            :min="form.starts_on"
                            max="2100-12-31"
                            class="w-full rounded border-gray-300 text-sm"
                        />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><input v-model="form.is_active" type="checkbox" class="rounded text-purple-600" />この予定日設定を有効にする</label
                >
                <p class="text-xs text-gray-500">終日の予定として登録します。土日・祝日でも指定した日付に登録します。</p>
                <div v-if="Object.keys(form.errors).length" role="alert" class="rounded border border-red-200 bg-red-50 p-3">
                    <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-700">{{ error }}</p>
                </div>
                <div class="rounded border border-gray-200 bg-gray-50 p-4">
                    <button
                        type="button"
                        :disabled="previewBusy || form.processing"
                        class="rounded border border-purple-400 bg-white px-3 py-1.5 text-sm text-purple-700 disabled:opacity-50"
                        @click="preview"
                    >
                        {{ previewBusy ? '確認中…' : '予定日を確認（直近12回）' }}
                    </button>
                    <p v-if="previewError" role="alert" class="mt-2 text-sm text-red-600">{{ previewError }}</p>
                    <p v-if="previewDates && !previewDates.length" class="mt-2 text-sm text-gray-600">
                        今日以降、適用期間内に該当する日付がありません。
                    </p>
                    <div v-if="previewDates?.length" class="mt-3 flex flex-wrap gap-2">
                        <span v-for="date in previewDates" :key="date" class="rounded bg-white px-2 py-1 text-sm">{{ date }}</span>
                    </div>
                    <p v-if="!form.is_active" class="mt-2 text-xs text-gray-500">
                        停止中は自動登録されません。上の確認では、有効にした場合の日付を表示します。
                    </p>
                </div>
                <p v-if="editing" class="text-xs text-gray-500">{{ changeNotice }}</p>
                <div class="flex items-center gap-3">
                    <button
                        :disabled="form.processing || previewBusy"
                        class="rounded bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50"
                    >
                        {{ form.processing ? '保存中…' : editing ? '保存する' : '登録する' }}
                    </button>
                    <Link :href="route('clerk.calendar.schedule_rules.index')" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300"
                        >キャンセル</Link
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>
