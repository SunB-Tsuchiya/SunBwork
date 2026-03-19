<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ client: Object });

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    return ['admin', 'superadmin'].includes(role) ? 'admin' : 'leader';
});

const form = useForm({
    name: props.client.name,
    detail: props.client.notes,
});

function submit() {
    form.put(route(`${routePrefix.value}.clients.update`, props.client.id));
}
</script>

<template>
    <AppLayout title="クライアント編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアント編集</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block">名前</label>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-2 py-1" />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-2 py-1"></textarea>
                </div>
                <div class="mt-6 flex gap-4">
                    <button type="submit" class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700">更新</button>
                    <Link :href="route(`${routePrefix}.clients.index`)" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300">一覧へ戻る</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
