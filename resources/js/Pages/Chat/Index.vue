<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = defineProps({
    rooms: Array,
    auth: Object,
    success: String,
});
// obtain current user robustly: prefer shared auth.user, then page.props.user, then passed auth prop
const user = page.props?.auth?.user ?? page.props?.user ?? props.auth?.user ?? null;
const userId = user?.id;

function createRoom() {
    router.visit('/chat/rooms/create'); // ルートはそのまま（コントローラのみ変更）
}

function getRoomName(room) {
    if (room.type === 'private') {
        // nameがnullなら自分以外のユーザー名
        if (!room.name) {
            const other = room.users.find((u) => u.id !== userId);
            return other ? other.name : '(相手なし)';
        }
        return room.name;
    }
    return room.name;
}
function getMemberNames(room) {
    // 自分を先頭に、その後他のメンバー名を「・」区切り
    if (!room.users) return '';
    const self = room.users.find((u) => u.id === userId);
    const others = room.users.filter((u) => u.id !== userId);
    const names = self ? [self.name, ...others.map((u) => u.name)] : others.map((u) => u.name);
    return names.join('・');
}
</script>
<template>
    <AppLayout title="チャットルーム一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チャットルーム一覧</h2>
        </template>
        <div class="mx-auto max-w-4xl rounded bg-white p-6 shadow">
                <div v-if="success" class="mb-4 rounded border border-green-300 bg-green-100 p-3 text-green-800">
                    {{ success }}
                </div>
                <button class="mb-4 rounded bg-blue-600 px-4 py-2 text-white" @click="createRoom">新しいチャットルームを作成</button>
                <table class="mb-4 min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">未読</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">ルーム名</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">メンバー</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">種別</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="room in rooms" :key="room.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-center">
                                <span v-if="room.unread_count > 0" class="rounded bg-purple-100 px-2 py-0.5 text-xs font-bold text-purple-700">
                                    未読{{ room.unread_count }}件
                                </span>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-2">{{ getRoomName(room) }}</td>
                            <td class="px-4 py-2">{{ getMemberNames(room) }}</td>
                            <td class="px-4 py-2">{{ room.type === 'group' ? 'グループ' : 'パーソナル' }}</td>
                            <td class="px-4 py-2">
                                <button class="rounded bg-blue-500 px-3 py-1 text-white" @click="router.visit(`/chat/rooms/${room.id}`)">
                                    ルームへ
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-6">
                    <a href="/bot/chat" class="text-purple-600 hover:underline"> AIチャットはこちら </a>
                </div>
            </div>
    </AppLayout>
</template>
