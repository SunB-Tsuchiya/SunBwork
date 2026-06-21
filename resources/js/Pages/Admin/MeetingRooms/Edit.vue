<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    room: { type: Object, required: true },
});

const form = useForm({
    name:           props.room.name,
    capacity:       props.room.capacity ?? '',
    description:    props.room.description ?? '',
    color:          props.room.color ?? '#6b7280',
    active:         props.room.active,
    sort_order:     props.room.sort_order ?? 0,
    available_from: props.room.available_from ? props.room.available_from.slice(0, 5) : '',
    available_to:   props.room.available_to   ? props.room.available_to.slice(0, 5)   : '',
});

function submit() {
    form.put(route('admin.meeting-rooms.update', { room: props.room.id }));
}
</script>

<template>
    <AppLayout title="会議室編集">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">会議室編集</h2>
        </template>

        <div class="mx-auto max-w-xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel for="name" value="会議室名 *" />
                    <TextInput
                        id="name" v-model="form.name" type="text"
                        class="mt-1 block w-full" required autofocus
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

                <div>
                    <InputLabel value="予約可能時間（空欄=制限なし）" />
                    <div class="mt-1 flex items-center gap-2">
                        <input v-model="form.available_from" type="time" step="900"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500" />
                        <span class="text-sm text-gray-500">〜</span>
                        <input v-model="form.available_to" type="time" step="900"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500" />
                    </div>
                    <p v-if="form.errors.available_from" class="mt-1 text-xs text-red-600">{{ form.errors.available_from }}</p>
                    <p v-if="form.errors.available_to" class="mt-1 text-xs text-red-600">{{ form.errors.available_to }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <input id="active" v-model="form.active" type="checkbox" class="rounded border-gray-300 text-red-600" />
                    <InputLabel for="active" value="有効（予定表で予約可能）" class="!mb-0" />
                </div>

                <div class="flex items-center justify-end gap-4 pt-2">
                    <Link :href="route('admin.meeting-rooms.index')" class="text-sm text-gray-600 hover:underline">キャンセル</Link>
                    <PrimaryButton type="submit" :disabled="form.processing">更新</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
