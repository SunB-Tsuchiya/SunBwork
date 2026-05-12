<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    subcontractor: Object,
    coordinators: Array,
});

const form = useForm({
    name: props.subcontractor.name,
    email: props.subcontractor.email ?? '',
    phone: props.subcontractor.phone ?? '',
    notes: props.subcontractor.notes ?? '',
    coordinator_ids: props.subcontractor.coordinators?.map((c) => c.id) ?? [],
});

function toggleCoordinator(id) {
    const idx = form.coordinator_ids.indexOf(id);
    if (idx >= 0) form.coordinator_ids.splice(idx, 1);
    else form.coordinator_ids.push(id);
}

function submit() {
    form.put(route('coordinator.subcontractors.update', props.subcontractor.id));
}
</script>

<template>
    <AppLayout title="外注先編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('coordinator.subcontractors.show', props.subcontractor.id)"
                      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 詳細に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">外注先 編集</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">名前 / 会社名 <span class="text-red-500">*</span></label>
                    <p class="mb-1 text-xs text-gray-400">個人名・会社名どちらでも可</p>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-2 py-1 text-sm" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">メールアドレス</label>
                    <input v-model="form.email" type="email" class="w-full rounded border px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">電話番号</label>
                    <input v-model="form.phone" type="text" class="w-full rounded border px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">備考</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded border px-2 py-1 text-sm"></textarea>
                </div>

                <!-- 管理Coordinator -->
                <div v-if="props.coordinators && props.coordinators.length">
                    <label class="mb-1 block text-sm font-medium text-gray-700">管理担当 Coordinator</label>
                    <div class="flex flex-wrap gap-2 rounded border p-2">
                        <label
                            v-for="co in props.coordinators"
                            :key="co.id"
                            class="flex items-center gap-1 cursor-pointer select-none text-sm"
                        >
                            <input
                                type="checkbox"
                                :value="co.id"
                                :checked="form.coordinator_ids.includes(co.id)"
                                @change="toggleCoordinator(co.id)"
                                class="rounded"
                            />
                            {{ co.name }}
                        </label>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-green-600 px-4 py-2 font-bold text-white hover:bg-green-700 disabled:opacity-60"
                >
                    保存
                </button>
            </form>
        </div>
    </AppLayout>
</template>
