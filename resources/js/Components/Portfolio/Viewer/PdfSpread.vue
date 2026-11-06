<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// PDF のページ画像を「本」のように見せる。
//  - 横長の画面: 見開き（1 ページ目は表紙として右側だけ）。めくるときは 3D のページめくりアニメ
//  - 縦長・狭い画面: 1 ページずつ表示（フェードで切り替え）
// next() / prev() は「このコンポーネントが処理できたか」を返す。端まで来たら false を返し、親が次/前の作品へ進める。
const props = defineProps({
    pages: { type: Array, required: true }, // ページ画像の URL（1 ページ目から順）
    title: { type: String, default: '' },
});
const emit = defineEmits(['zoom']);

const box = ref(null);
const availW = ref(800);
const availH = ref(600);
const ratio = ref(0.707); // ページの幅/高さ。1 ページ目の実寸で更新する（初期値は A4 縦）
const FLIP_MS = 800;

const twoPage = computed(() => availW.value >= 720 && availW.value / availH.value >= 1.15 && props.pages.length > 1);

// 見開きの組み立て: [左ページ番号, 右ページ番号]（無いところは null）。1 始まり
const spreads = computed(() => {
    const n = props.pages.length;
    if (!twoPage.value) return Array.from({ length: n }, (_, i) => [null, i + 1]);
    const list = [[null, 1]]; // 表紙は右側だけ
    for (let p = 2; p <= n; p += 2) list.push([p, p + 1 <= n ? p + 1 : null]);
    return list;
});

const index = ref(0);
const flip = ref(null); // { dir: 'next'|'prev', to: number, angle: number }
const urlOf = (p) => (p ? props.pages[p - 1] : null);

// ---- 大きさ: 親の領域にぴったり収まる本のサイズを計算する ----
const pageH = computed(() => {
    const cols = twoPage.value ? 2 : 1;
    return Math.max(120, Math.min(availH.value, availW.value / (cols * ratio.value)));
});
const bookStyle = computed(() => {
    const cols = twoPage.value ? 2 : 1;
    return { width: `${pageH.value * ratio.value * cols}px`, height: `${pageH.value}px` };
});

let observer = null;
function measure() {
    if (!box.value) return;
    availW.value = box.value.clientWidth;
    availH.value = box.value.clientHeight;
}
onMounted(() => {
    measure();
    observer = new ResizeObserver(measure);
    observer.observe(box.value);
    // 1 ページ目の実寸から縦横比を取る
    const img = new Image();
    img.onload = () => { if (img.naturalHeight) ratio.value = img.naturalWidth / img.naturalHeight; };
    img.src = props.pages[0];
});
onBeforeUnmount(() => observer?.disconnect());

// 表示形式（見開き/1 ページ）が変わったら、いま見ているページを保ったまま位置を合わせ直す
let lastPage = 1; // 表示形式が変わっても同じページを見続けるために、最後に見ていたページ番号を覚えておく
watch([index, spreads], () => {
    const s = spreads.value[Math.min(index.value, spreads.value.length - 1)];
    lastPage = s?.[1] ?? s?.[0] ?? 1;
});
watch(twoPage, () => {
    flip.value = null;
    const target = lastPage;
    const found = spreads.value.findIndex((s) => s.includes(target));
    index.value = found >= 0 ? found : 0;
});

const current = computed(() => spreads.value[Math.min(index.value, spreads.value.length - 1)] ?? [null, 1]);

// ---- ページ送り ----
function finishFlip(to) {
    index.value = to;
    flip.value = null;
}

function startFlip(dir, to) {
    flip.value = { dir, to, angle: 0 };
    // 初期状態を描画してから角度を変えて、CSS transition を効かせる
    nextTick(() => requestAnimationFrame(() => requestAnimationFrame(() => {
        if (flip.value) flip.value.angle = dir === 'next' ? -180 : 180;
    })));
    setTimeout(() => { if (flip.value && flip.value.to === to) finishFlip(to); }, FLIP_MS + 120);
}

function next() {
    if (flip.value) return true; // めくり中の操作は無視（処理済みとして扱う）
    if (index.value >= spreads.value.length - 1) return false;
    if (twoPage.value) startFlip('next', index.value + 1);
    else index.value++;
    return true;
}
function prev() {
    if (flip.value) return true;
    if (index.value <= 0) return false;
    if (twoPage.value) startFlip('prev', index.value - 1);
    else index.value--;
    return true;
}

// 見ているページの表示（例: "2–3 / 12"）
const label = computed(() => {
    const [l, r] = current.value;
    const pages = [l, r].filter(Boolean);
    const text = pages.length === 2 ? `${pages[0]}–${pages[1]}` : `${pages[0] ?? 1}`;
    return `${text} / ${props.pages.length}`;
});
defineExpose({ next, prev, label });

// ---- めくり中に見せる面の組み立て ----
const base = computed(() => {
    const f = flip.value;
    const cur = current.value;
    if (!f) return { left: cur[0], right: cur[1] };
    const to = spreads.value[f.to];
    // next: 左は今の左のまま、右は次の右が下から現れる / prev: 左は前の左が現れ、右は今の右のまま
    return f.dir === 'next' ? { left: cur[0], right: to[1] } : { left: to[0], right: cur[1] };
});
const leaf = computed(() => {
    const f = flip.value;
    if (!f) return null;
    const cur = current.value;
    const to = spreads.value[f.to];
    return f.dir === 'next'
        ? { front: cur[1], back: to[0], side: 'right' }
        : { front: cur[0], back: to[1], side: 'left' };
});
const leafStyle = computed(() => {
    const l = leaf.value;
    if (!l) return {};
    return {
        left: l.side === 'right' ? '50%' : '0',
        transformOrigin: l.side === 'right' ? 'left center' : 'right center',
        transform: `rotateY(${flip.value.angle}deg)`,
        transition: flip.value.angle === 0 ? 'none' : `transform ${FLIP_MS}ms cubic-bezier(.45,.05,.25,1)`,
    };
});
function onLeafEnd() {
    if (flip.value && flip.value.angle !== 0) finishFlip(flip.value.to);
}

function zoom(p) {
    if (p) emit('zoom', p - 1);
}
</script>

<template>
    <div ref="box" class="flex h-full w-full items-center justify-center" style="perspective: 2400px">
        <!-- 見開き -->
        <div v-if="twoPage" class="relative" :style="bookStyle">
            <!-- 本の綴じ目の影 -->
            <div class="pointer-events-none absolute inset-y-0 left-1/2 z-10 w-8 -translate-x-1/2 bg-gradient-to-r from-transparent via-black/10 to-transparent" />

            <div class="absolute inset-y-0 left-0 w-1/2">
                <img v-if="urlOf(base.left)" :src="urlOf(base.left)" :alt="`${title} ${base.left}ページ`" class="h-full w-full cursor-zoom-in bg-white object-fill shadow-lg" draggable="false" @click="zoom(base.left)" />
            </div>
            <div class="absolute inset-y-0 right-0 w-1/2">
                <img v-if="urlOf(base.right)" :src="urlOf(base.right)" :alt="`${title} ${base.right}ページ`" class="h-full w-full cursor-zoom-in bg-white object-fill shadow-lg" draggable="false" @click="zoom(base.right)" />
            </div>

            <!-- めくっている 1 枚（表と裏） -->
            <div v-if="leaf" class="absolute inset-y-0 z-20 w-1/2" style="transform-style: preserve-3d" :style="leafStyle" @transitionend="onLeafEnd">
                <div class="absolute inset-0 bg-white shadow-xl" style="backface-visibility: hidden">
                    <img v-if="urlOf(leaf.front)" :src="urlOf(leaf.front)" alt="" class="h-full w-full object-fill" draggable="false" />
                </div>
                <div class="absolute inset-0 bg-white shadow-xl" style="backface-visibility: hidden; transform: rotateY(180deg)">
                    <img v-if="urlOf(leaf.back)" :src="urlOf(leaf.back)" alt="" class="h-full w-full object-fill" draggable="false" />
                </div>
            </div>
        </div>

        <!-- 1 ページ -->
        <Transition v-else name="pdf-fade" mode="out-in">
            <img
                :key="current[1]"
                :src="urlOf(current[1])"
                :alt="`${title} ${current[1]}ページ`"
                class="cursor-zoom-in bg-white object-fill shadow-lg"
                :style="bookStyle"
                draggable="false"
                @click="zoom(current[1])"
            />
        </Transition>
    </div>
</template>

<style scoped>
.pdf-fade-enter-active,
.pdf-fade-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.pdf-fade-enter-from {
    opacity: 0;
    transform: translateX(14px);
}
.pdf-fade-leave-to {
    opacity: 0;
    transform: translateX(-14px);
}
</style>
