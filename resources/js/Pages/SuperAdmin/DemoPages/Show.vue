<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    demoPage: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

// ── 基本情報フォーム ──
const infoForm = useForm({
    name:        props.demoPage.name,
    description: props.demoPage.description ?? '',
    is_active:   props.demoPage.is_active,
    expires_at:  props.demoPage.expires_at ?? '',
});

function saveInfo() {
    infoForm.patch(route('superadmin.demo_pages.update', props.demoPage.id));
}

// ── パスワードフォーム ──
const pwForm = useForm({
    password:              '',
    password_confirmation: '',
});

function savePassword() {
    pwForm.patch(route('superadmin.demo_pages.update_password', props.demoPage.id), {
        onSuccess: () => { pwForm.reset(); },
    });
}

// ── メールアドレスフォーム ──
const emailForm = useForm({ email: '', label: '' });

function addEmail() {
    emailForm.post(route('superadmin.demo_pages.emails.store', props.demoPage.id), {
        onSuccess: () => emailForm.reset(),
    });
}

function removeEmail(emailId) {
    if (!confirm('このメールアドレスを削除しますか？')) return;
    useForm({}).delete(route('superadmin.demo_pages.emails.destroy', {
        demoPage: props.demoPage.id,
        email: emailId,
    }));
}

// パスワード表示切替
const showPw = ref(false);
const showPwConfirm = ref(false);
</script>

<template>
    <AppLayout :title="demoPage.name + ' — デモページ管理'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('superadmin.demo_pages.index')" class="text-yellow-600 hover:text-yellow-800 text-sm">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                    {{ demoPage.name }}
                </h2>
            </div>
        </template>
        <template #tabs>
            <SuperAdminNavigationTabs active="demo_pages" />
        </template>

        <!-- フラッシュ -->
        <div v-if="flash.success" class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-start gap-2">
            <span class="mt-0.5">✔</span>
            <span>{{ flash.success }}</span>
        </div>

        <div class="space-y-6">

            <!-- ── セクション 1: 基本情報 ── -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <h3 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">基本情報</h3>

                <form @submit.prevent="saveInfo" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ページ名 <span class="text-red-500">*</span></label>
                        <input v-model="infoForm.name" type="text" required
                            class="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-yellow-400 focus:outline-none focus:ring-1 focus:ring-yellow-400">
                        <p v-if="infoForm.errors.name" class="mt-1 text-xs text-red-500">{{ infoForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">スラッグ</label>
                        <p class="text-sm font-mono text-gray-500 bg-gray-50 inline-block px-3 py-1.5 rounded border border-gray-200">
                            {{ demoPage.slug }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1">スラッグは作成後変更できません（URLに使用）</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">説明・メモ</label>
                        <textarea v-model="infoForm.description" rows="3"
                            class="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-yellow-400 focus:outline-none focus:ring-1 focus:ring-yellow-400"
                            placeholder="用途・対象クライアントなど"></textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="text-sm font-medium text-gray-700">公開状態</label>
                        <button type="button"
                            :class="infoForm.is_active
                                ? 'bg-green-500 hover:bg-green-600'
                                : 'bg-gray-300 hover:bg-gray-400'"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            @click="infoForm.is_active = !infoForm.is_active">
                            <span :class="infoForm.is_active ? 'translate-x-6' : 'translate-x-1'"
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"></span>
                        </button>
                        <span :class="infoForm.is_active ? 'text-green-700' : 'text-gray-400'" class="text-sm font-semibold">
                            {{ infoForm.is_active ? '公開中' : '非公開' }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">公開期限</label>
                        <input v-model="infoForm.expires_at" type="datetime-local"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-yellow-400 focus:outline-none focus:ring-1 focus:ring-yellow-400">
                        <p class="text-xs text-gray-400 mt-1">未設定の場合は無期限。期限を過ぎると自動的にアクセス不可になります。</p>
                        <button v-if="infoForm.expires_at" type="button"
                            class="mt-1 text-xs text-gray-400 hover:text-red-500 underline"
                            @click="infoForm.expires_at = ''">
                            期限をクリア（無期限にする）
                        </button>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="infoForm.processing"
                            class="rounded-md bg-yellow-500 px-5 py-2 text-sm font-semibold text-white hover:bg-yellow-600 disabled:opacity-50">
                            {{ infoForm.processing ? '保存中...' : '基本情報を保存' }}
                        </button>
                        <p class="text-xs text-gray-400 mt-1">
                            ※ 公開状態・期限を変更すると SuperAdmin にメール通知が送信されます。
                        </p>
                    </div>
                </form>
            </div>

            <!-- ── セクション 2: パスワード変更 ── -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <h3 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">パスワード変更</h3>
                <p class="text-sm text-gray-500 mb-4">
                    クライアントがデモページにログインする際に使用するパスワードです（全許可メールアドレス共通）。
                    変更すると SuperAdmin にメール通知が送信されます。
                </p>

                <form @submit.prevent="savePassword" class="space-y-4 max-w-md">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">新しいパスワード <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input v-model="pwForm.password" :type="showPw ? 'text' : 'password'" required minlength="8"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 pr-10 text-sm focus:border-yellow-400 focus:outline-none focus:ring-1 focus:ring-yellow-400">
                            <button type="button" @click="showPw = !showPw"
                                class="absolute right-2 top-2 text-gray-400 hover:text-gray-600 text-xs">
                                {{ showPw ? '隠す' : '表示' }}
                            </button>
                        </div>
                        <p v-if="pwForm.errors.password" class="mt-1 text-xs text-red-500">{{ pwForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">確認用パスワード <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input v-model="pwForm.password_confirmation" :type="showPwConfirm ? 'text' : 'password'" required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 pr-10 text-sm focus:border-yellow-400 focus:outline-none focus:ring-1 focus:ring-yellow-400">
                            <button type="button" @click="showPwConfirm = !showPwConfirm"
                                class="absolute right-2 top-2 text-gray-400 hover:text-gray-600 text-xs">
                                {{ showPwConfirm ? '隠す' : '表示' }}
                            </button>
                        </div>
                    </div>

                    <button type="submit" :disabled="pwForm.processing"
                        class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50">
                        {{ pwForm.processing ? '変更中...' : 'パスワードを変更する' }}
                    </button>
                </form>
            </div>

            <!-- ── セクション 3: 許可メールアドレス ── -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <h3 class="text-base font-semibold text-gray-800 mb-1 pb-2 border-b border-gray-200">
                    許可メールアドレス
                    <span class="ml-2 text-sm font-normal text-gray-400">（{{ demoPage.emails.length }} 件）</span>
                </h3>
                <p class="text-sm text-gray-500 mb-4">
                    ここに登録されたメールアドレスのみがログインできます。追加・削除ごとに SuperAdmin にメール通知が送信されます。
                </p>

                <!-- 追加フォーム -->
                <form @submit.prevent="addEmail" class="flex flex-wrap gap-2 items-end mb-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">メールアドレス <span class="text-red-500">*</span></label>
                        <input v-model="emailForm.email" type="email" required placeholder="client@example.com"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm w-64 focus:border-yellow-400 focus:outline-none focus:ring-1 focus:ring-yellow-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">メモ（任意）</label>
                        <input v-model="emailForm.label" type="text" placeholder="〇〇社 山田様"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm w-44 focus:border-yellow-400 focus:outline-none focus:ring-1 focus:ring-yellow-400">
                    </div>
                    <button type="submit" :disabled="emailForm.processing"
                        class="rounded-md bg-yellow-500 px-4 py-2 text-sm font-semibold text-white hover:bg-yellow-600 disabled:opacity-50">
                        追加
                    </button>
                    <p v-if="emailForm.errors.email" class="w-full text-xs text-red-500 mt-1">{{ emailForm.errors.email }}</p>
                </form>

                <!-- メール一覧 -->
                <div v-if="demoPage.emails.length === 0" class="text-sm text-gray-400 py-4 text-center">
                    許可メールアドレスが登録されていません。
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">メールアドレス</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">メモ</th>
                            <th class="px-4 py-2 text-xs font-medium text-gray-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="e in demoPage.emails" :key="e.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-800 font-mono text-xs">{{ e.email }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ e.label ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" @click="removeEmail(e.id)"
                                    class="text-red-400 hover:text-red-600 text-xs font-medium">
                                    削除
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
