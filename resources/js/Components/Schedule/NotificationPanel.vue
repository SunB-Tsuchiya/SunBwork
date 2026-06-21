<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits(['read']);

const open         = ref(false);
const notifications = ref([]);
const loading      = ref(false);

const unread = computed(() => notifications.value.filter(n => !n.read_at));
const unreadCount = computed(() => unread.value.length);

async function fetchNotifications() {
    loading.value = true;
    try {
        const res = await axios.get(route('schedule.notifications.index'));
        notifications.value = res.data;
    } catch (e) {
        console.error('通知取得失敗', e);
    } finally {
        loading.value = false;
    }
}

async function markRead(notification) {
    if (notification.read_at) return;
    try {
        await axios.put(
            route('schedule.notifications.read', { notification: notification.id }),
            {},
            { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') } }
        );
        notification.read_at = new Date().toISOString();
        emit('read');
    } catch (e) {
        console.error('既読失敗', e);
    }
}

async function markAllRead() {
    const unread = notifications.value.filter(n => !n.read_at);
    await Promise.all(unread.map(n => markRead(n)));
}

function toggle() {
    open.value = !open.value;
    if (open.value && notifications.value.length === 0) {
        fetchNotifications();
    }
}

function formatDate(isoStr) {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    return `${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}

function typeLabel(type) {
    if (type === 'morning_summary')      return '朝の予定';
    if (type === 'invitation_declined')  return '辞退';
    return 'リマインダー';
}

onMounted(fetchNotifications);
</script>

<template>
    <div class="relative">
        <!-- ベルボタン -->
        <button
            class="relative flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700"
            :title="open ? '通知を閉じる' : '通知を開く'"
            @click="toggle"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <span v-if="unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-[10px] text-white">
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <!-- ドロップダウンパネル -->
        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-40" @click.self="open = false" />
            <div v-if="open"
                class="fixed right-4 top-14 z-50 w-80 rounded-lg border border-gray-200 bg-white shadow-xl"
                style="max-height: 420px; display: flex; flex-direction: column;"
            >
                <!-- ヘッダー -->
                <div class="flex items-center justify-between border-b px-4 py-2.5">
                    <span class="text-sm font-semibold text-gray-800">予定通知</span>
                    <div class="flex items-center gap-2">
                        <button v-if="unreadCount > 0"
                            class="text-xs text-blue-600 hover:underline"
                            @click="markAllRead">すべて既読</button>
                        <button class="text-gray-400 hover:text-gray-600 text-xs" @click="open = false">✕</button>
                    </div>
                </div>

                <!-- リスト（未読のみ表示） -->
                <div class="overflow-y-auto flex-1">
                    <div v-if="loading" class="py-8 text-center text-xs text-gray-400">読み込み中…</div>
                    <div v-else-if="unreadCount === 0" class="py-8 text-center text-xs text-gray-400">未読の通知はありません</div>
                    <ul v-else class="divide-y divide-gray-100">
                        <li
                            v-for="n in unread" :key="n.id"
                            class="flex cursor-pointer gap-3 bg-green-50 px-4 py-3 hover:bg-green-100 transition-colors"
                            @click="markRead(n)"
                        >
                            <div class="mt-1.5 shrink-0">
                                <div class="h-2 w-2 rounded-full bg-green-500" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-800">{{ n.event?.title ?? '(タイトルなし)' }}</p>
                                <p class="text-xs" :class="n.type === 'invitation_declined' ? 'text-red-500' : 'text-gray-500'">
                                    <template v-if="n.type === 'invitation_declined'">
                                        {{ n.from_user?.name ?? '参加者' }}が辞退 &mdash; {{ formatDate(n.event?.starts_at) }}〜{{ formatDate(n.event?.ends_at) }}
                                    </template>
                                    <template v-else>
                                        {{ typeLabel(n.type) }} &mdash; {{ formatDate(n.event?.starts_at) }}〜{{ formatDate(n.event?.ends_at) }}
                                    </template>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">クリックで既読にする</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </Teleport>
    </div>
</template>
