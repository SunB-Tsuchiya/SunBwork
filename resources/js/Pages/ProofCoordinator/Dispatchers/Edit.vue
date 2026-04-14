<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    dispatcher: { type: Object, required: true },
});

const form = useForm({
    name:  props.dispatcher.name,
    email: props.dispatcher.email ?? '',
    phone: props.dispatcher.phone ?? '',
    notes: props.dispatcher.notes ?? '',
});

function submit() {
    form.put(route('proof_coordinator.dispatchers.update', props.dispatcher.id));
}
</script>

<template>
    <AppLayout title="単発派遣 編集">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">単発派遣 編集</h2>
                <Link :href="route('proof_coordinator.dispatchers.show', props.dispatcher.id)" class="text-gray-600 hover:text-gray-900">← 詳細に戻る</Link>
            </div>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="dispatchers" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="max-w-lg space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">名前 / 会社名 <span class="text-red-500">*</span></label>
                    <p class="mb-1 text-xs text-gray-400">個人名・会社名どちらでも可</p>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-2 py-1 text-sm" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">メールアドレス</label>
                    <input v-model="form.email" type="email" class="w-full rounded border px-2 py-1 text-sm" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">電話番号</label>
                    <input v-model="form.phone" type="text" class="w-full rounded border px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">備考</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded border px-2 py-1 text-sm"></textarea>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-pink-600 px-4 py-2 font-bold text-white hover:bg-pink-700 disabled:opacity-60"
                >
                    保存
                </button>
            </form>
        </div>
    </AppLayout>
</template>
