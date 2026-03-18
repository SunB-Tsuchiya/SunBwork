<template>
    <AppLayout title="ガント作成（PoC）">
        <div class="rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-xl font-bold">タスク作成（PoC）</h1>
            <form @submit.prevent="submit">
                <div class="mb-2">
                    <label class="mb-1 block">名前</label>
                    <input v-model="form.name" class="w-full border px-2 py-1" />
                </div>
                <div class="mb-2">
                    <label class="mb-1 block">開始</label>
                    <input type="date" v-model="form.start_date" class="border px-2 py-1" />
                </div>
                <div class="mb-2">
                    <label class="mb-1 block">終了</label>
                    <input type="date" v-model="form.end_date" class="border px-2 py-1" />
                </div>
                <div class="mt-4">
                    <button class="rounded bg-blue-600 px-4 py-2 text-white">作成</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
const page = usePage();
const form = useForm({ project_job_id: page.props.project_job_id, name: '', start_date: '', end_date: '' });
function submit() {
    form.post(route('coordinator.project_schedules.store'));
}
</script>
