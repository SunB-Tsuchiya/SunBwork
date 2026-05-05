<template>
    <div class="flex gap-4">
        <!-- 左：週ナビ＋日別スケジュール -->
        <div class="min-w-0 flex-1">
            <div class="mb-3 flex items-center gap-3">
                <button @click="prevWeek" class="rounded bg-gray-200 px-3 py-1 text-sm hover:bg-gray-300">← 前の週</button>
                <span class="font-semibold text-gray-700">{{ currentYear }}年 第{{ currentWeek }}週（{{ weekLabel }}）</span>
                <button @click="nextWeek" class="rounded bg-gray-200 px-3 py-1 text-sm hover:bg-gray-300">次の週 →</button>
            </div>

            <div class="overflow-hidden rounded border border-gray-200">
                <div
                    v-for="(day, i) in weekDays"
                    :key="i"
                    class="flex border-b last:border-b-0"
                    :class="day.isToday ? 'bg-blue-50' : 'bg-white'"
                >
                    <div class="w-20 flex-shrink-0 border-r px-2 py-2">
                        <div class="text-xs font-semibold" :class="day.isWeekend ? 'text-red-600' : 'text-gray-700'">
                            {{ day.dayName }}
                        </div>
                        <div class="text-xs text-gray-500">{{ day.label }}</div>
                    </div>
                    <div class="flex-1 px-2 py-2">
                        <div v-if="day.schedules.length === 0" class="text-xs text-gray-300">予定なし</div>
                        <div
                            v-for="s in day.schedules"
                            :key="s.id"
                            class="mb-1 inline-block rounded px-2 py-0.5 text-xs font-medium"
                            :style="{
                                backgroundColor: scheduleStatusColor(s.end_date, !!s.completed_at || (s.progress ?? 0) >= 100).bg,
                                borderColor: scheduleStatusColor(s.end_date, !!s.completed_at || (s.progress ?? 0) >= 100).border,
                                color: scheduleStatusColor(s.end_date, !!s.completed_at || (s.progress ?? 0) >= 100).text,
                                border: '1px solid',
                            }"
                        >
                            {{ s.name || '（タイトルなし）' }}
                            <span class="ml-1 font-normal opacity-80">〜{{ formatDate(s.end_date) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右：週の掲示板（約2/5幅） -->
        <div v-if="weekPostsUrl" class="flex min-w-0 flex-col rounded border border-gray-200 bg-gray-50 p-3" style="width: 42%">
            <h4 class="mb-3 text-sm font-semibold text-gray-700">週の掲示板</h4>

            <div v-if="postsLoading" class="text-xs text-gray-400">読込中...</div>

            <!-- スレッド一覧（フラットリストをツリー表示） -->
            <div v-else class="mb-3 max-h-[520px] flex-1 space-y-0.5 overflow-y-auto pr-1">
                <div v-if="displayList.length === 0" class="text-xs text-gray-400">まだ投稿はありません</div>

                <template v-for="item in displayList" :key="item.id">
                    <!-- 投稿カード -->
                    <div
                        class="rounded border bg-white"
                        :class="item.depth === 0 ? 'border-gray-200 mt-2 first:mt-0' : 'border-indigo-100'"
                        :style="{ marginLeft: Math.min(item.depth, 4) * 14 + 'px' }"
                    >
                        <div class="px-3 py-2">
                            <!-- ヘッダー行 -->
                            <div class="mb-1 flex items-center justify-between gap-1">
                                <span class="flex items-center gap-1 text-xs font-semibold" :class="roleColorClass(item.user?.user_role)">
                                    <span v-if="item.depth > 0" class="text-gray-400">↳</span>
                                    {{ item.user?.name || '不明' }}
                                    <span class="ml-0.5 text-[10px] font-normal opacity-70">{{ roleLabel(item.user?.user_role) }}</span>
                                </span>
                                <span class="flex-shrink-0 text-xs text-gray-400">{{ formatDatetime(item.created_at) }}</span>
                            </div>
                            <!-- 本文 -->
                            <div class="whitespace-pre-wrap text-sm text-gray-800">{{ item.body }}</div>
                            <!-- 返信ボタン -->
                            <div class="mt-1 text-right">
                                <button
                                    @click="toggleReplyForm(item.id)"
                                    class="text-xs hover:underline"
                                    :class="replyTargetId === item.id ? 'text-gray-400' : 'text-indigo-500 hover:text-indigo-700'"
                                >
                                    {{ replyTargetId === item.id ? 'キャンセル' : '返信する' }}
                                </button>
                            </div>
                        </div>

                        <!-- 返信フォーム（この投稿の直下に展開） -->
                        <div v-if="replyTargetId === item.id" class="border-t border-indigo-100 bg-indigo-50/60 px-3 py-2">
                            <textarea
                                v-model="replyBody"
                                class="w-full rounded border border-gray-300 p-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                rows="2"
                                placeholder="返信内容を入力... (Ctrl+Enter で送信)"
                                @keydown.ctrl.enter="submitReply(item)"
                                ref="replyTextarea"
                            ></textarea>
                            <div class="mt-1 flex justify-end gap-2">
                                <button
                                    @click="toggleReplyForm(null)"
                                    class="rounded bg-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-300"
                                >
                                    キャンセル
                                </button>
                                <button
                                    @click="submitReply(item)"
                                    :disabled="!replyBody.trim() || replySubmitting"
                                    class="rounded bg-indigo-600 px-3 py-1 text-xs text-white hover:bg-indigo-700 disabled:opacity-40"
                                >
                                    {{ replySubmitting ? '送信中...' : '返信する' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- 新規投稿フォーム -->
            <div class="border-t border-gray-200 pt-3">
                <p class="mb-1 text-xs font-medium text-gray-600">新規投稿 <span class="text-gray-400 font-normal">（Ctrl+Enter で送信）</span></p>
                <textarea
                    v-model="newPostBody"
                    class="w-full rounded border border-gray-300 p-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    rows="3"
                    placeholder="投稿内容を入力..."
                    @keydown.ctrl.enter="submitPost"
                ></textarea>
                <button
                    @click="submitPost"
                    :disabled="!newPostBody.trim() || postSubmitting"
                    class="mt-1 w-full rounded bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700 disabled:opacity-40"
                >
                    {{ postSubmitting ? '送信中...' : '投稿する' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { scheduleStatusColor } from '@/Helpers/scheduleColor.js';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
    project: { type: Object, default: null },
    weekPostsUrl: { type: String, default: null },
});

// ─── ISO週ユーティリティ ────────────────────────────────────
function getISOWeek(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
    const week1 = new Date(d.getFullYear(), 0, 4);
    return [d.getFullYear(), 1 + Math.round(((d - week1) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7)];
}

function getISOWeekStart(year, week) {
    const jan4 = new Date(year, 0, 4);
    const startOfWeek1 = new Date(jan4);
    startOfWeek1.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7));
    const start = new Date(startOfWeek1);
    start.setDate(startOfWeek1.getDate() + (week - 1) * 7);
    return start;
}

function dayStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
// ─────────────────────────────────────────────────────────────

const today = new Date();
const todayStr = dayStr(today);
const [initYear, initWeek] = getISOWeek(today);

const currentYear = ref(initYear);
const currentWeek = ref(initWeek);

const weekStart = computed(() => getISOWeekStart(currentYear.value, currentWeek.value));

const weekLabel = computed(() => {
    const start = weekStart.value;
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return `${start.getMonth() + 1}/${start.getDate()} 〜 ${end.getMonth() + 1}/${end.getDate()}`;
});

const DAY_NAMES = ['月', '火', '水', '木', '金', '土', '日'];

const weekDays = computed(() => {
    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(weekStart.value);
        d.setDate(weekStart.value.getDate() + i);
        const ds = dayStr(d);
        days.push({
            label: `${d.getMonth() + 1}/${d.getDate()}`,
            dayName: DAY_NAMES[i],
            isToday: ds === todayStr,
            isWeekend: i >= 5,
            schedules: (props.schedules || []).filter((s) => {
                if (!s.start_date || !s.end_date) return false;
                const startDs = String(s.start_date).split('T')[0];
                const endDs = String(s.end_date).split('T')[0];
                return ds >= startDs && ds <= endDs;
            }),
        });
    }
    return days;
});

// ─── 週ナビゲーション ─────────────────────────────────────────
function prevWeek() {
    let w = currentWeek.value - 1;
    let y = currentYear.value;
    if (w < 1) {
        y--;
        w = getISOWeek(new Date(y, 11, 28))[1];
    }
    currentYear.value = y;
    currentWeek.value = w;
}

function nextWeek() {
    const start = getISOWeekStart(currentYear.value, currentWeek.value);
    const nextStart = new Date(start);
    nextStart.setDate(start.getDate() + 7);
    const [ny, nw] = getISOWeek(nextStart);
    currentYear.value = ny;
    currentWeek.value = nw;
}

// ─── ロールカラー（CLAUDE.md 定義）────────────────────────────
const ROLE_COLOR = {
    superadmin: 'text-yellow-600',
    admin:      'text-red-600',
    leader:     'text-orange-600',
    coordinator:'text-green-600',
    clerk:      'text-purple-600',
    user:       'text-blue-600',
};

const ROLE_LABEL = {
    superadmin: 'SAdmin',
    admin:      'Admin',
    leader:     'Leader',
    coordinator:'Co',
    clerk:      'Clerk',
    user:       'User',
};

function roleColorClass(userRole) {
    return ROLE_COLOR[(userRole || '').toLowerCase()] || 'text-gray-700';
}

function roleLabel(userRole) {
    return ROLE_LABEL[(userRole || '').toLowerCase()] || '';
}

// ─── 掲示板（フラットリスト → ツリー表示）────────────────────
// flatPosts: APIから返ってきたフラットな投稿配列（parent_id付き）
const flatPosts = ref([]);
const postsLoading = ref(false);

// フラットリストから深さ付き表示リストを生成（深さ優先順）
const displayList = computed(() => {
    const map = {};
    flatPosts.value.forEach((p) => {
        map[p.id] = { ...p, _children: [] };
    });
    const roots = [];
    flatPosts.value.forEach((p) => {
        if (p.parent_id && map[p.parent_id]) {
            map[p.parent_id]._children.push(map[p.id]);
        } else {
            roots.push(map[p.id]);
        }
    });

    const result = [];
    function traverse(items, depth) {
        items.forEach((item) => {
            result.push({ ...item, depth });
            traverse(item._children, depth + 1);
        });
    }
    traverse(roots, 0);
    return result;
});

// 新規投稿
const newPostBody = ref('');
const postSubmitting = ref(false);

// 返信
const replyTargetId = ref(null);
const replyBody = ref('');
const replySubmitting = ref(false);
const replyTextarea = ref(null);

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

function toggleReplyForm(id) {
    if (replyTargetId.value === id) {
        replyTargetId.value = null;
        replyBody.value = '';
    } else {
        replyTargetId.value = id;
        replyBody.value = '';
        nextTick(() => {
            if (replyTextarea.value) {
                const el = Array.isArray(replyTextarea.value) ? replyTextarea.value[0] : replyTextarea.value;
                el?.focus();
            }
        });
    }
}

async function fetchPosts() {
    if (!props.weekPostsUrl) return;
    postsLoading.value = true;
    try {
        const res = await axios.get(props.weekPostsUrl, {
            params: { year: currentYear.value, week: currentWeek.value },
        });
        flatPosts.value = res.data;
    } catch (e) {
        console.error('[ProjectWeekPlanner] fetchPosts error', e);
    } finally {
        postsLoading.value = false;
    }
}

async function submitPost() {
    if (!newPostBody.value.trim() || !props.weekPostsUrl) return;
    postSubmitting.value = true;
    try {
        const res = await axios.post(
            props.weekPostsUrl,
            { year: currentYear.value, week: currentWeek.value, body: newPostBody.value.trim() },
            { headers: { 'X-CSRF-TOKEN': getCsrf() } },
        );
        flatPosts.value.push(res.data);
        newPostBody.value = '';
    } catch (e) {
        console.error('[ProjectWeekPlanner] submitPost error', e);
    } finally {
        postSubmitting.value = false;
    }
}

async function submitReply(targetItem) {
    if (!replyBody.value.trim() || !props.weekPostsUrl) return;
    replySubmitting.value = true;
    try {
        const res = await axios.post(
            props.weekPostsUrl,
            {
                year: currentYear.value,
                week: currentWeek.value,
                body: replyBody.value.trim(),
                parent_id: targetItem.id,
            },
            { headers: { 'X-CSRF-TOKEN': getCsrf() } },
        );
        flatPosts.value.push(res.data);
        replyBody.value = '';
        replyTargetId.value = null;
    } catch (e) {
        console.error('[ProjectWeekPlanner] submitReply error', e);
    } finally {
        replySubmitting.value = false;
    }
}

// ─── フォーマット ─────────────────────────────────────────────
function formatDate(ds) {
    if (!ds) return '';
    const s = String(ds).split('T')[0];
    const [, m, d] = s.split('-');
    return `${parseInt(m)}/${parseInt(d)}`;
}

function formatDatetime(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    return `${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

onMounted(fetchPosts);
watch([currentYear, currentWeek], () => {
    replyTargetId.value = null;
    replyBody.value = '';
    fetchPosts();
});
</script>
