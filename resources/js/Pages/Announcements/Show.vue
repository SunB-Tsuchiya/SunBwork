<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    recipient: Object,
});

const targetLabel = (type) => ({
    all: '全員',
    employees_only: '社員のみ',
    individual: '個別選択',
}[type] ?? type);
</script>

<template>
    <AppLayout title="お知らせ詳細">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">お知らせ詳細</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 戻るリンク -->
            <div class="mb-4">
                <Link :href="route('announcements.index')" class="text-sm text-gray-500 hover:text-gray-700">
                    ← お知らせ一覧に戻る
                </Link>
            </div>

            <div class="mx-auto max-w-2xl">
                <!-- メタ情報 -->
                <div class="mb-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    <div class="flex flex-wrap gap-x-6 gap-y-1">
                        <span><span class="font-medium">送信者:</span> {{ recipient.sender }}</span>
                        <span><span class="font-medium">宛先:</span> {{ targetLabel(recipient.target_type) }}</span>
                        <span><span class="font-medium">送信日時:</span> {{ recipient.created_at }}</span>
                        <span v-if="recipient.read_at">
                            <span class="font-medium">既読:</span> {{ recipient.read_at }}
                        </span>
                    </div>
                </div>

                <!-- タイトル -->
                <h3 class="mb-4 text-xl font-bold text-gray-900">{{ recipient.title }}</h3>

                <!-- 内容 -->
                <div class="min-h-[200px] whitespace-pre-wrap rounded border border-gray-200 bg-white p-4 text-sm text-gray-800 leading-relaxed">
                    {{ recipient.content }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>
