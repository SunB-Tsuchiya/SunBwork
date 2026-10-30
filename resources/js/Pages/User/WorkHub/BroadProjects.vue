<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import Modal from '@/Components/Modal.vue';
import BroadProjectForm from '@/Pages/SuperAdmin/BroadProjects/BroadProjectForm.vue';

const props = defineProps({
    projects: { type: Object, required: true },
    filters: { type: Object, required: true },
});
const query = ref(props.filters.q || '');
const showInactive = ref(props.filters.show_inactive);
const showCreate = ref(false);
function search() {
    router.get(route('user.work.broad_projects.index'), { q: query.value, show_inactive: showInactive.value ? 1 : 0 }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout title="大まかな案件">
        <template #header><h2 class="text-base font-semibold text-gray-800 sm:text-xl">大まかな案件</h2></template>
        <template #tabs><UserNavigationTabs active="broad_projects" /></template>
        <div class="rounded bg-white p-4 shadow sm:p-6">
            <p class="mb-4 text-sm text-gray-600">同じ部署で作業先として選ぶ案件です。追加する前に、同じ名前・取引先・年度の案件がないか確認してください。</p>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <form class="flex flex-wrap items-center gap-2" @submit.prevent="search">
                    <input v-model="query" type="search" maxlength="100" placeholder="案件名・取引先・年度で検索" aria-label="大まかな案件を検索" class="w-full rounded border px-3 py-2 text-sm sm:w-72" />
                    <button type="submit" class="rounded bg-blue-600 px-3 py-2 text-sm text-white">検索</button>
                    <label class="flex items-center gap-2 text-sm"><input v-model="showInactive" type="checkbox" @change="search" />休止中も表示</label>
                </form>
                <div class="flex items-center gap-3">
                    <Link :href="route('superadmin.broad_projects.index')" class="text-sm text-blue-700 underline">編集・休止</Link>
                    <button type="button" class="rounded bg-blue-600 px-4 py-2 text-sm text-white" @click="showCreate = true">大まかな案件を追加</button>
                </div>
            </div>
            <p class="mt-4 text-sm text-gray-500">{{ projects.total }}件</p>
            <p v-if="!projects.data.length" class="py-8 text-center text-sm text-gray-500">該当する大まかな案件はありません。</p>
            <ul v-else class="mt-2 divide-y">
                <li v-for="project in projects.data" :key="project.id" class="py-3">
                    <div class="flex items-center gap-2"><span class="font-medium">{{ project.name }}</span><span v-if="!project.is_active" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">休止中</span></div>
                    <p class="mt-1 text-sm text-gray-500">{{ [project.client?.name, project.fiscal_year ? `${project.fiscal_year}年度` : null].filter(Boolean).join(' ／ ') || '取引先・年度未設定' }}</p>
                </li>
            </ul>
            <nav v-if="projects.last_page > 1" aria-label="大まかな案件のページ" class="mt-4 flex items-center justify-center gap-4 text-sm">
                <Link v-if="projects.prev_page_url" :href="projects.prev_page_url" class="text-blue-700 underline">前へ</Link>
                <span>{{ projects.current_page }} / {{ projects.last_page }}</span>
                <Link v-if="projects.next_page_url" :href="projects.next_page_url" class="text-blue-700 underline">次へ</Link>
            </nav>
        </div>
        <Modal :show="showCreate" max-width="lg" aria-label="大まかな案件を追加" @close="showCreate = false">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h3 class="font-semibold">大まかな案件を追加</h3>
                <button type="button" class="text-sm text-gray-600 underline" @click="showCreate = false">閉じる</button>
            </div>
            <div class="p-6"><BroadProjectForm v-if="showCreate" route-prefix="user.work.broad_projects" :allow-status="false" @saved="showCreate = false" /></div>
        </Modal>
    </AppLayout>
</template>
