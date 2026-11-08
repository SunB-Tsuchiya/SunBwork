<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import PortfolioAdminTabs from '@/Components/Portfolio/PortfolioAdminTabs.vue';
import MemberCard from '@/Components/Portfolio/MemberCard.vue';

// ポートフォリオ管理（admin エリア）タブ1「メンバーのポートフォリオ」。
// 登録者のカードを縦に並べ、数点のサムネイルで、どんな作品を登録しているかを一覧できる。クリックでその人のページへ。
// 並び順はサーバー側（雇用区分 → 役職 → 氏名。申請者管理と同じ）。作品のある人が上、まだ無い人は下に名前だけ薄く出す。
const props = defineProps({
    members:              { type: Array, default: () => [] },       // 作品のある人（サムネイル付きのカード）
    withoutWorks:         { type: Array, default: () => [] },       // まだ作品が 1 つも無い人
    departments:          { type: Array, default: () => [] },
    canManagePermissions: { type: Boolean, default: false },
    canEditOwn:           { type: Boolean, default: false },
    routePrefix:          { type: String, default: 'admin' },
});

const selectedDept = ref(null);
const keyword = ref('');

const matches = (m) =>
    (selectedDept.value === null || m.department_id === selectedDept.value)
    && (!keyword.value.trim() || m.name.toLowerCase().includes(keyword.value.trim().toLowerCase()));

const visibleMembers = computed(() => props.members.filter(matches));
const visibleWithout = computed(() => props.withoutWorks.filter(matches));
const problemCount = computed(() => props.members.filter((m) => m.problem.failed || m.problem.uploading).length);

// チップは、他の「ユーザー選択」画面（申請者管理など）と同じ形
const chip = (active) => ['rounded-full px-3 py-1 text-xs font-medium transition-colors', active ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'];
</script>

<template>
    <AppLayout title="ポートフォリオ管理">
        <template #header>
            <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">ポートフォリオ管理</h2>
        </template>

        <div class="mx-auto max-w-5xl">
            <PortfolioAdminTabs active="members" :can-manage-permissions="canManagePermissions" />

            <div class="rounded-b bg-white px-6 pb-6 pt-5 shadow-md">
                <p class="mb-4 text-sm text-gray-500">
                    ポートフォリオを使っているメンバーの作品です。カードかサムネイルをクリックすると、その人のページ（閲覧のみ）が開きます。
                    作品の追加・修正・削除はご本人が行います。問題のある作品があれば、本人に修正・削除を依頼してください。
                </p>

                <!-- 部署の絞り込み・氏名検索 -->
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <template v-if="departments.length">
                        <button type="button" :class="chip(selectedDept === null)" @click="selectedDept = null">すべての部署</button>
                        <button v-for="d in departments" :key="d.id" type="button" :class="chip(selectedDept === d.id)" @click="selectedDept = d.id">{{ d.name }}</button>
                    </template>
                    <input v-model="keyword" type="search" placeholder="氏名で検索" class="ml-auto w-44 rounded border-gray-300 py-1 text-sm" />
                </div>
                <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <span>作品のある人: <strong class="text-gray-800">{{ visibleMembers.length }}</strong> / {{ members.length }}人</span>
                    <span v-if="problemCount" class="rounded bg-red-50 px-2 py-0.5 font-medium text-red-700">⚠ 要確認の作品がある人 {{ problemCount }}人</span>
                </div>

                <!-- 登録者のカード（縦に並ぶ） -->
                <div v-if="visibleMembers.length" class="space-y-3">
                    <MemberCard v-for="m in visibleMembers" :key="m.id" :member="m" />
                </div>
                <p v-else class="py-10 text-center text-sm text-gray-400">
                    {{ members.length ? '該当する人がいません。' : 'まだ作品を登録している人がいません。' }}
                </p>

                <!-- まだ作品がない人（名前だけ薄く） -->
                <div v-if="visibleWithout.length" class="mt-6 border-t border-gray-100 pt-4">
                    <h3 class="mb-2 text-xs font-semibold text-gray-400">まだ作品がない人（{{ visibleWithout.length }}人）</h3>
                    <ul class="flex flex-wrap gap-2">
                        <li v-for="m in visibleWithout" :key="m.id" class="flex items-center gap-1.5 rounded-full bg-gray-50 px-3 py-1 text-xs text-gray-400">
                            <span>{{ m.name }}</span>
                            <span v-if="m.role_label" class="rounded px-1 text-[10px]" :class="m.role_label === 'SuperAdmin' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-600'">{{ m.role_label }}</span>
                            <Link v-if="m.can_edit" :href="route('user.portfolio.manage')" class="font-medium text-red-600 hover:underline">作品を追加する →</Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
