<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const form = useForm({
    name:       props.user.name,
    email:      props.user.email,
    assignment: props.user.assignment ?? '',
    user_role:  props.user.user_role,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? page.props.user ?? null);

const userRoleOptions = [
    { value: 'admin',       label: '管理者',   description: '全ての機能にアクセス可能' },
    { value: 'leader',      label: 'リーダー', description: 'コンテンツ管理とユーザー機能にアクセス可能' },
    { value: 'coordinator', label: '進行管理', description: 'タスク管理とユーザー機能にアクセス可能' },
    { value: 'user',        label: 'ユーザー', description: '基本機能のみアクセス可能' },
];

const filteredRoleOptions = computed(() => {
    if (currentUser.value && currentUser.value.user_role === 'superadmin') return userRoleOptions;
    return userRoleOptions.filter(o => o.value !== 'admin');
});

const submit = () => {
    if (form.user_role === 'admin' && !(currentUser.value && currentUser.value.user_role === 'superadmin')) {
        alert('管理者への昇格は superadmin のみ許可されています。');
        return;
    }
    form.put(route('admin.users.update', { user: props.user.id }));
};
</script>

<template>
    <Head title="ユーザー編集" />
    <AppLayout title="ユーザー編集">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    ユーザー編集
                </h2>
                <Link :href="route('admin.users.index')" class="text-gray-600 hover:text-gray-900">
                    ← ユーザー一覧に戻る
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="名前" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="email" value="メールアドレス" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <InputLabel for="assignment" value="担当" />
                    <TextInput
                        id="assignment"
                        v-model="form.assignment"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.assignment" />
                </div>

                <div class="mt-4">
                    <InputLabel for="user_role" value="権限レベル" />
                    <select
                        id="user_role"
                        v-model="form.user_role"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        required
                    >
                        <option
                            v-for="option in filteredRoleOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }} - {{ option.description }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.user_role" />
                </div>

                <div class="flex items-center justify-end mt-6 space-x-3">
                    <Link :href="route('admin.users.index')">
                        <SecondaryButton type="button">キャンセル</SecondaryButton>
                    </Link>
                    <PrimaryButton
                        class="bg-red-600 hover:bg-red-700"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        更新する
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
