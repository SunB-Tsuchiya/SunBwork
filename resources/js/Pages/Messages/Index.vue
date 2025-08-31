<template>
    <AppLayout title="メールボックス">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight">メール</h2>
        </template>

        <main>
            <div class="py-6">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <!-- Top toolbar: compose, address book, search -->
                    <div class="mb-4 flex items-center gap-3">
                        <button @click="showCompose = true" class="inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-2 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                            </svg>
                            作成
                        </button>
                        <button @click="showAddress = true" class="inline-flex items-center gap-2 rounded bg-gray-100 px-3 py-2 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM6 11c1.66 0 2.99-1.34 2.99-3S7.66 5 6 5 3 6.34 3 8s1.34 3 3 3zM6 13c-2.33 0-7 1.17-7 3.5V20h13v-3.5C12 14.17 7.33 13 6 13zM16 13c-.29 0-.62.02-.97.05C15.35 13.36 16 14.14 16 15v3h5v-3.5c0-2.33-4.67-3.5-5-3.5z"
                                />
                            </svg>
                            アドレス帳
                        </button>
                        <div class="flex-none">
                            <input type="search" placeholder="検索" class="w-[10em] rounded border px-3 py-2 text-left" />
                        </div>
                    </div>

                    <div class="flex gap-6">
                        <!-- Left: folders -->
                        <aside class="w-64 flex-shrink-0">
                            <div class="rounded-lg border bg-white shadow">
                                <div class="border-b p-4">
                                    <h4 class="font-medium">フォルダ</h4>
                                </div>
                                <nav class="p-2">
                                    <Link
                                        :href="route('messages.index', { folder: 'inbox' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v9a2 2 0 002 2z"
                                            /></svg
                                        >受信トレイ</Link
                                    >
                                    <Link
                                        :href="route('messages.index', { folder: 'sent' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v13a1 1 0 001 1h14a1 1 0 001-1V4"
                                            /></svg
                                        >送信済み</Link
                                    >
                                    <Link
                                        :href="route('messages.index', { folder: 'drafts' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" /></svg
                                        >下書き</Link
                                    >
                                    <Link
                                        :href="route('messages.index', { folder: 'trash' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 6h18M9 6V4h6v2M19 6l-1 14H6L5 6"
                                            /></svg
                                        >削除済み</Link
                                    >
                                </nav>
                            </div>
                        </aside>

                        <!-- Right: index (top) + preview (bottom) attached boxes -->
                        <section class="flex flex-1 flex-col gap-0">
                            <!-- Index box (top) -->
                            <div class="rounded-t-lg border-l border-r border-t bg-white shadow">
                                <div class="p-4">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                        件名
                                                    </th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                        差出人
                                                    </th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                        日時
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr
                                                    v-for="m in messages.data || []"
                                                    :key="m.id"
                                                    @click="selectMessage(m)"
                                                    :class="{ 'bg-gray-50': selected && selected.id === m.id, 'cursor-pointer': true }"
                                                >
                                                    <td class="px-4 py-2">{{ m.subject || '(件名なし)' }}</td>
                                                    <td class="px-4 py-2">{{ m.from_user_name || (m.from_user?.name ?? '') }}</td>
                                                    <td class="px-4 py-2">{{ m.sent_at || m.created_at }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview box (bottom) attached to index -->
                            <div class="mt-0 flex-1 overflow-auto rounded-b-lg border-b border-l border-r bg-white shadow">
                                <div class="p-4">
                                    <div v-if="selected">
                                        <div class="flex items-start justify-between">
                                            <h3 class="text-lg font-semibold">{{ selected.subject || '(件名なし)' }}</h3>
                                            <Link :href="route('messages.show', selected.id)" class="text-sm text-blue-600">全文を見る</Link>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500">
                                            差出人: {{ selected.from_user?.name || selected.from_user_name }}
                                        </div>
                                        <div class="mt-4 text-sm text-gray-700" v-html="sanitize(selected.body)"></div>
                                    </div>
                                    <div v-else class="text-sm text-gray-500">プレビューを選択してください</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </main>
        <AddressBookModal :show="showAddress" :companyId="currentUser.company_id" @close="showAddress = false" @select="onAddressSelect" />
        <ComposeModal
            :show="showCompose"
            :initialTo="composeInitial"
            @close="showCompose = false"
            @sent="
                () => {
                    /* refresh list if needed */
                }
            "
        />
    </AppLayout>
</template>

<script setup>
import AddressBookModal from '@/components/AddressBookModal.vue';
import ComposeModal from '@/components/ComposeModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import DOMPurify from 'dompurify';
const props = defineProps({ messages: Object, folder: String });
const { messages, folder } = props;
const page = usePage();
const currentUser = page.props.user || {};
// ensure unread_messages_count fallback
if (typeof currentUser.unread_messages_count === 'undefined') {
    currentUser.unread_messages_count = page.props.unread_messages_count || 0;
}
const selected = ref(null);
const showAddress = ref(false);
const showCompose = ref(false);
const composeInitial = ref([]);

function onAddressSelect(u) {
    // open compose and pass selected user via event (we'll emit a custom event)
    showCompose.value = true;
    composeInitial.value = [u];
    // close address book modal after selection
    showAddress.value = false;
    // TODO: we might want to pass the selected user to the compose modal via a global store or prop/event
}

function selectMessage(m) {
    selected.value = m;
    // optimistically mark as read for this recipient via API
    if (m && m.id) {
        axios.post(route('messages.read', m.id)).catch(() => {});
    }
}

function sanitize(html) {
    return DOMPurify.sanitize(html || '');
}
</script>
