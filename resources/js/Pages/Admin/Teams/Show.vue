<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const currentTeam = ref(props.team || {});

const goBack = () => {
    $inertia.visit(route('admin.teams.index'));
};

const goEdit = () => {
    $inertia.visit(route('admin.teams.edit', { team: currentTeam.value.id }));
};
</script>

<template>
    <AppLayout :title="`チーム：${currentTeam.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム詳細</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-sm text-gray-500">ID</div>
                            <div class="text-lg font-medium">{{ currentTeam.id }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">チーム名</div>
                            <div class="text-lg font-medium">{{ currentTeam.name }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">会社</div>
                            <div class="text-lg">{{ currentTeam.company?.name || '未設定' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">部署</div>
                            <div class="text-lg">{{ currentTeam.department?.name || '未設定' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">種別</div>
                            <div class="text-lg">{{ currentTeam.team_type || '' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">作成日</div>
                            <div class="text-lg">{{ currentTeam.created_at || '' }}</div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-medium text-gray-700">メンバー</h3>
                        <ul class="mt-2 list-inside list-disc text-sm text-gray-800">
                            <li v-if="!currentTeam.users || currentTeam.users.length === 0">メンバーが登録されていません</li>
                            <li v-for="u in currentTeam.users || []" :key="u.id">{{ u.name }}</li>
                        </ul>
                    </div>

                    <div class="mt-6 flex gap-2">
                        <button @click="goBack" class="rounded border px-4 py-2 text-sm">一覧へ戻る</button>
                        <button @click="goEdit" class="rounded bg-blue-500 px-4 py-2 text-sm text-white hover:bg-blue-600">編集</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
