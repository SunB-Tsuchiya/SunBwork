<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name:        '',
    capacity:    '',
    description: '',
    color:       '#6b7280',
    active:      true,
    sort_order:  0,
});

function submit() {
    form.post(route('admin.meeting-rooms.store'));
}
</script>

<template>
    <AppLayout title="会議室登録">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">会議室登録</h2>
        </template>

        <AdminNavigationTabs active="meeting_rooms" />

        <div class="mx-auto max-w-xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel for="name" value="会議室名 *" />
                    <TextInput
                        id="name" v-model="form.name" type="text"
                        class="mt-1 block w-full" required autofocus
                        placeholder="例: 田端会議室"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <InputLabel for="capacity" value="定員（名）" />
                    <TextInput
                        id="capacity" v-model="form.capacity" type="number"
                        class="mt-1 block w-32" min="1" max="999"
                        placeholder="任意"
                    />
                    <p v-if="form.errors.capacity" class="mt-1 text-xs text-red-600">{{ form.errors.capacity }}</p>
                </div>

                <div>
                    <InputLabel for="description" value="備考" />
                    <textarea
                        id="description" v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                        placeholder="任意"
                    ></textarea>
                </div>

                <div>
                    <InputLabel for="color" value="カレンダー色" />
                    <div class="mt-1 flex items-center gap-3">
                        <input id="color" v-model="form.color" type="color" class="h-9 w-16 cursor-pointer rounded border border-gray-300" />
                        <span class="text-sm text-gray-500">{{ form.color }}</span>
                    </div>
                </div>

                <div>
                    <InputLabel for="sort_order" value="表示順" />
                    <TextInput
                        id="sort_order" v-model="form.sort_order" type="number"
                        class="mt-1 block w-24" min="0"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <input id="active" v-model="form.active" type="checkbox" class="rounded border-gray-300 text-red-600" />
                    <InputLabel for="active" value="有効（予定表で予約可能）" class="!mb-0" />
                </div>

                <div class="flex items-center justify-end gap-4 pt-2">
                    <Link :href="route('admin.meeting-rooms.index')" class="text-sm text-gray-600 hover:underline">キャンセル</Link>
                    <PrimaryButton type="submit" :disabled="form.processing">登録</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
