<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    editUser:     { type: Object, required: true },
    assignments:  { type: Array,  default: () => [] },
    leaderTitles: { type: Array,  default: () => [] },
});

const EMPLOYMENT_TYPE_OPTIONS = [
    { value: 'regular',   label: '正社員' },
    { value: 'contract',  label: '契約社員' },
    { value: 'dispatch',  label: '派遣社員' },
    { value: 'outsource', label: '業務委託' },
];

const USER_ROLE_OPTIONS = [
    { value: 'leader',      label: 'リーダー',  description: 'チーム管理・各種管理機能' },
    { value: 'coordinator', label: '進行管理',  description: '案件・割当管理' },
    { value: 'user',        label: 'ユーザー',  description: '基本機能のみ' },
];

const form = useForm({
    name:              props.editUser.name,
    email:             props.editUser.email,
    assignment_id:     props.editUser.assignment_id ?? '',
    user_role:         props.editUser.user_role,
    employment_type:   props.editUser.employment_type ?? 'regular',
    position_title_id: props.editUser.position_title_id ?? '',
});

const availablePositionTitles = computed(() =>
    form.user_role === 'leader' ? props.leaderTitles : []
);

watch(() => form.user_role, () => { form.position_title_id = ''; });

const submit = () => {
    form.put(route('leader.user_management.update', { user: props.editUser.id }));
};
</script>

<template>
    <AppLayout title="ユーザー編集（部署）">
        <template #tabs>
            <LeaderNavigationTabs active="user_management" />
        </template>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">ユーザー編集（部署）</h2>
                <Link :href="route('leader.user_management.index')" class="text-sm text-gray-600 hover:text-gray-900">
                    ← ユーザー一覧に戻る
                </Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-6 text-sm text-gray-500">対象: <strong>{{ editUser.name }}</strong>（{{ editUser.email }}）</p>

            <form @submit.prevent="submit" class="max-w-lg space-y-4">
                <div>
                    <InputLabel for="name" value="名前" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" value="メールアドレス" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="assignment_id" value="担当" />
                    <select
                        id="assignment_id"
                        v-model="form.assignment_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        required
                    >
                        <option value="">-- 担当を選択 --</option>
                        <option v-for="a in assignments" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.assignment_id" />
                </div>

                <div>
                    <InputLabel for="user_role" value="権限レベル" />
                    <select
                        id="user_role"
                        v-model="form.user_role"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        required
                    >
                        <option v-for="opt in USER_ROLE_OPTIONS" :key="opt.value" :value="opt.value">
                            {{ opt.label }} — {{ opt.description }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.user_role" />
                </div>

                <div>
                    <InputLabel for="employment_type" value="雇用形態" />
                    <select
                        id="employment_type"
                        v-model="form.employment_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                    >
                        <option v-for="opt in EMPLOYMENT_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.employment_type" />
                </div>

                <div v-if="availablePositionTitles.length > 0">
                    <InputLabel for="position_title_id" value="役職称号" />
                    <select
                        id="position_title_id"
                        v-model="form.position_title_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                    >
                        <option value="">-- なし --</option>
                        <option v-for="t in availablePositionTitles" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.position_title_id" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link :href="route('leader.user_management.index')" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        キャンセル
                    </Link>
                    <PrimaryButton
                        class="bg-orange-600 hover:bg-orange-700"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        更新
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
