<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const req = page.props.request;

const accept = () => {
    // Use messages-based accept endpoint which updates JobRequest and linked assignment.
    router.post(route('messages.job_requests.accept', req.id));
};
</script>

<template>
    <Head :title="`依頼 ${req.id}`" />

    <div class="prose">
        <h1>依頼詳細</h1>
    </div>

    <div class="mt-4 rounded border p-4">
        <div class="text-sm text-gray-500">from: {{ req.from_user?.name || req.from_user_id }}</div>
        <div class="mt-2">{{ req.message }}</div>
        <div class="mt-2 text-xs text-gray-400">status: {{ req.status }}</div>

        <div class="mt-4">
            <button v-if="req.status !== 'accepted'" @click="accept" class="rounded bg-blue-600 px-3 py-1 text-white">受諾する</button>
            <Link :href="route('job_requests.index')" class="ms-2 text-gray-600">一覧へ</Link>
        </div>
    </div>
</template>
