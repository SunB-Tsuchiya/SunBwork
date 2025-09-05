<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ diary: Object });
const showComment = ref(false);
const commentText = ref('');
const form = useForm({ comment: '' });

function toggleComment() {
    showComment.value = !showComment.value;
}

function submitRead() {
    form.comment = commentText.value;
    form.post(route('leader.diaries.mark_read', props.diary.id), {
        onSuccess: () => {
            history.back();
        },
    });
}
</script>

<template>
    <AppLayout title="日報表示">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">日報表示</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow sm:px-6 lg:px-8">
                <h1 class="mb-4 text-2xl font-bold">{{ props.diary.user.name }} — {{ props.diary.date }}</h1>
                <div class="prose mb-4" v-html="props.diary.content"></div>

                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" @change="toggleComment" class="form-checkbox" />
                        <span class="ml-2">下にコメントをつける</span>
                    </label>
                </div>

                <div v-if="showComment" class="mb-4">
                    <textarea v-model="commentText" rows="3" class="w-full rounded border p-2"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button @click.prevent="submitRead" class="rounded bg-green-600 px-4 py-2 text-white">既読にする</button>
                    <a href="javascript:history.back()" class="rounded bg-gray-200 px-4 py-2 text-gray-700">閉じる</a>
                </div>

                <div class="mt-6">
                    <h3 class="font-semibold">既読ユーザー</h3>
                    <ul class="list-disc pl-6">
                        <li v-for="(name, idx) in props.diary.read_by_names || props.diary.read_by || []" :key="idx">{{ name }}</li>
                    </ul>

                    <h3 class="mt-4 font-semibold">コメント</h3>
                    <div v-if="(props.diary.comments || []).length === 0">コメントはありません</div>
                    <div v-for="(c, idx) in props.diary.comments || []" :key="idx" class="mb-2 rounded border p-3">
                        <div class="text-sm text-gray-600">{{ c.user_name }} - {{ c.created_at }}</div>
                        <div class="mt-1">{{ c.comment }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
