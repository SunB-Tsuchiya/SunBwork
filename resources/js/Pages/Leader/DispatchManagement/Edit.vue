<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dispatchUser: { type: Object, required: true },
});

const EMPLOYMENT_TYPE_OPTIONS = [
    { value: 'regular',   label: '正社員',   desc: '日報必須（デフォルト）' },
    { value: 'contract',  label: '契約社員', desc: '日報必須（デフォルト）' },
    { value: 'dispatch',  label: '派遣社員', desc: '日報任意（デフォルト）' },
    { value: 'outsource', label: '業務委託', desc: '日報任意（デフォルト）' },
];

// diary_required の初期値:
//   - override が明示されていれば その値
//   - override が null の場合は employment_type から導出
const initialDiaryOverride = props.dispatchUser.diary_required_override;
const form = useForm({
    employment_type: props.dispatchUser.employment_type || 'regular',
    // null = デフォルト（employment_type に従う）、true/false = 個別上書き
    diary_required:  initialDiaryOverride ?? null,
    // 派遣プロフィール
    agency_name:     props.dispatchUser.agency_name || '',
    contract_start:  props.dispatchUser.contract_start || '',
    contract_end:    props.dispatchUser.contract_end || '',
    dispatch_notes:  props.dispatchUser.dispatch_notes || '',
});

// 現在のemployment_typeで「デフォルト日報義務」はどちらか
const defaultDiaryRequired = computed(() =>
    !['dispatch', 'outsource'].includes(form.employment_type)
);

// 実際に適用される diary_required の値
const effectiveDiaryRequired = computed(() => {
    if (form.diary_required !== null) return form.diary_required;
    return defaultDiaryRequired.value;
});

// 派遣・業務委託かどうか
const isDispatchType = computed(() =>
    ['dispatch', 'outsource'].includes(form.employment_type)
);

// 契約終了日の警告
const contractEndWarning = computed(() => {
    if (!form.contract_end) return null;
    const end = new Date(form.contract_end);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.ceil((end - today) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return { level: 'error', msg: `契約終了日が ${Math.abs(diffDays)} 日前に過ぎています。` };
    if (diffDays <= 30) return { level: 'warn', msg: `契約終了まで残り ${diffDays} 日です。` };
    return null;
});

function submit() {
    form.put(route('leader.dispatch_management.update', { dispatchUser: props.dispatchUser.id }), {
        preserveScroll: true,
    });
}

function resetDiaryToDefault() {
    form.diary_required = null;
}
</script>

<template>
    <AppLayout title="派遣・業務委託管理 編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">雇用形態・設定の編集</h2>
        </template>
        <template #tabs>
            <LeaderNavigationTabs active="dispatch" />
        </template>

        <div class="mx-auto max-w-xl">
            <div class="rounded bg-white p-6 shadow">
                <!-- ユーザー情報 -->
                <div class="mb-6 rounded-lg bg-gray-50 p-4">
                    <p class="text-lg font-semibold text-gray-800">{{ dispatchUser.name }}</p>
                    <p class="text-sm text-gray-500">{{ dispatchUser.email }}</p>
                    <div class="mt-1 flex gap-3 text-xs text-gray-400">
                        <span>{{ dispatchUser.department_name || '部署未設定' }}</span>
                        <span>{{ dispatchUser.assignment_name || '担当未設定' }}</span>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- 雇用形態 -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">雇用形態</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="opt in EMPLOYMENT_TYPE_OPTIONS"
                                :key="opt.value"
                                class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 transition"
                                :class="form.employment_type === opt.value
                                    ? 'border-orange-400 bg-orange-50'
                                    : 'border-gray-200 hover:bg-gray-50'"
                            >
                                <input
                                    type="radio"
                                    :value="opt.value"
                                    v-model="form.employment_type"
                                    class="mt-0.5 accent-orange-500"
                                />
                                <div>
                                    <div class="text-sm font-medium text-gray-800">{{ opt.label }}</div>
                                    <div class="text-xs text-gray-400">{{ opt.desc }}</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 日報設定 -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">日報</label>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <!-- デフォルト表示 -->
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-sm text-gray-600">
                                    <span class="font-medium">デフォルト:</span>
                                    {{ defaultDiaryRequired ? '必須' : '任意' }}
                                    （{{ form.employment_type === 'regular' || form.employment_type === 'contract' ? '正社員・契約社員' : '派遣・業務委託' }}）
                                </span>
                                <button
                                    v-if="form.diary_required !== null"
                                    type="button"
                                    class="text-xs text-gray-400 hover:text-gray-600 underline"
                                    @click="resetDiaryToDefault"
                                >
                                    デフォルトに戻す
                                </button>
                            </div>

                            <!-- 個別上書き -->
                            <div class="space-y-2">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        :value="null"
                                        v-model="form.diary_required"
                                        class="accent-orange-500"
                                    />
                                    <span class="text-sm text-gray-700">
                                        デフォルトに従う
                                        <span class="ml-1 text-xs text-gray-400">（現在: {{ defaultDiaryRequired ? '必須' : '任意' }}）</span>
                                    </span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        :value="true"
                                        v-model="form.diary_required"
                                        class="accent-orange-500"
                                    />
                                    <span class="text-sm text-gray-700">必須（個別上書き）</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        :value="false"
                                        v-model="form.diary_required"
                                        class="accent-orange-500"
                                    />
                                    <span class="text-sm text-gray-700">任意（個別上書き）</span>
                                </label>
                            </div>

                            <!-- 適用後の結果 -->
                            <div class="mt-3 rounded bg-orange-50 p-2 text-xs text-orange-700">
                                この設定を保存すると：日報は
                                <strong>{{ effectiveDiaryRequired ? '必須' : '任意' }}</strong>
                                になります。
                                <span v-if="form.diary_required !== null" class="ml-1 text-orange-500">（個別上書きあり）</span>
                            </div>
                        </div>
                    </div>

                    <!-- 派遣プロフィール（dispatch / outsource のみ） -->
                    <div v-if="isDispatchType">
                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            {{ form.employment_type === 'dispatch' ? '派遣会社情報' : '業務委託先情報' }}
                        </label>
                        <div class="space-y-3 rounded-lg border border-orange-100 bg-orange-50 p-4">
                            <!-- 派遣会社 / 委託先名 -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">
                                    {{ form.employment_type === 'dispatch' ? '派遣会社名' : '委託先名' }}
                                </label>
                                <input
                                    type="text"
                                    v-model="form.agency_name"
                                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-orange-400 focus:outline-none"
                                    placeholder="例: 〇〇派遣株式会社"
                                />
                            </div>

                            <!-- 契約期間 -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">契約開始日</label>
                                    <input
                                        type="date"
                                        v-model="form.contract_start"
                                        class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-orange-400 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">契約終了日</label>
                                    <input
                                        type="date"
                                        v-model="form.contract_end"
                                        class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-orange-400 focus:outline-none"
                                    />
                                    <!-- 終了日警告 -->
                                    <p
                                        v-if="contractEndWarning"
                                        class="mt-1 text-xs"
                                        :class="contractEndWarning.level === 'error' ? 'text-red-600' : 'text-orange-500'"
                                    >
                                        {{ contractEndWarning.msg }}
                                    </p>
                                </div>
                            </div>

                            <!-- 備考 -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">備考</label>
                                <textarea
                                    v-model="form.dispatch_notes"
                                    rows="2"
                                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-orange-400 focus:outline-none"
                                    placeholder="契約条件・注意事項など"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- エラー表示 -->
                    <div v-if="Object.keys(form.errors).length > 0" class="rounded bg-red-50 p-3 text-sm text-red-600">
                        <p v-for="(err, key) in form.errors" :key="key">{{ err }}</p>
                    </div>

                    <!-- ボタン -->
                    <div class="flex items-center justify-between">
                        <Link
                            :href="route('leader.dispatch_management.index')"
                            class="text-sm text-gray-500 hover:text-gray-700"
                        >
                            ← 一覧に戻る
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded bg-orange-500 px-5 py-2 text-sm font-medium text-white hover:bg-orange-600 disabled:opacity-50"
                        >
                            保存
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
