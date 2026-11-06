<script setup>
import { computed, onBeforeUnmount, ref, reactive } from 'vue';
import axios from 'axios';
import { resizeImageForUpload } from '@/lib/portfolio/imageResize';
import { uploadPdf, uploadVideo } from '@/lib/portfolio/chunkUpload';
import { MAX_ORIGINAL_BYTES, compressVideo, inspectVideo } from '@/lib/portfolio/videoCompress';

// 作品の新規追加。「タイトル → 説明 → ファイル」の順に入力して、最後に追加する。
// （目的をはっきりさせるため、常時表示のドロップ枠ではなくモーダルにしている）
const props = defineProps({
    collections: { type: Array, default: () => [] },
    allTags: { type: Array, default: () => [] },
    defaultCollectionId: { type: Number, default: null },
    accent: { type: String, default: '#2563eb' },
});
const emit = defineEmits(['created', 'close']);

const form = reactive({
    title: '',
    description: '',
    collection_id: props.defaultCollectionId ?? '',
    tags: [],
});
const tagInput = ref('');
const file = ref(null);
const previewUrl = ref(null);
const dragging = ref(false);
const sending = ref(false);
const errorMessage = ref('');
// 「動画がアップできないとき」の補助メモ（トグル）。動画でエラーが出たときは自動で開く
const showVideoHelp = ref(false);
const input = ref(null);
// 送信の進み具合（PDF は「アップロード → ページ変換」の 2 段階）
const progress = ref(null); // { phase, ratio, done, total }
const isPdf = computed(() => file.value?.type === 'application/pdf');
const isVideo = computed(() => /^video\/(mp4|quicktime)$/.test(file.value?.type ?? ''));
const MAX_VIDEO_INPUT_MB = 2048;
const MAX_PDF_MB = 300;

const suggestions = computed(() =>
    props.allTags.filter((t) => !form.tags.includes(t) && t.includes(tagInput.value.trim())).slice(0, 6),
);
const canSubmit = computed(() => !sending.value && form.title.trim() && file.value);

function pickFile(f) {
    if (!f) return;
    const isVid = /^video\/(mp4|quicktime)$/.test(f.type);
    if (!/^image\/(jpeg|png|webp)$/.test(f.type) && f.type !== 'application/pdf' && !isVid) {
        errorMessage.value = '画像（JPG・PNG・WebP）、PDF、動画（MP4・MOV）のいずれかを選んでください。';
        return;
    }
    if (isVid && f.size > MAX_VIDEO_INPUT_MB * 1024 * 1024) {
        errorMessage.value = `動画が大きすぎます（上限 ${MAX_VIDEO_INPUT_MB / 1024}GB）。`;
        return;
    }
    if (f.type === 'application/pdf' && f.size > MAX_PDF_MB * 1024 * 1024) {
        errorMessage.value = `PDF が大きすぎます（上限 ${MAX_PDF_MB}MB）。`;
        return;
    }
    errorMessage.value = '';
    file.value = f;
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    // PDF はブラウザでプレビューしない（変換後に管理画面で確認できる）
    previewUrl.value = f.type === 'application/pdf' ? null : URL.createObjectURL(f); // 画像・動画はそのままプレビュー
    // タイトルが未入力ならファイル名を仮のタイトルにする（あとで変更できる）
    if (!form.title.trim()) form.title = f.name.replace(/\.[^.]+$/, '');
}
function onDrop(e) {
    dragging.value = false;
    pickFile(e.dataTransfer?.files?.[0]);
}
function onPick(e) {
    pickFile(e.target.files?.[0]);
    e.target.value = '';
}
function clearFile() {
    file.value = null;
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
}

function addTag(name = tagInput.value) {
    const t = name.trim();
    if (t && !form.tags.includes(t) && form.tags.length < 20) form.tags.push(t);
    tagInput.value = '';
}
function removeTag(t) {
    form.tags = form.tags.filter((x) => x !== t);
}

// 動画: ブラウザで圧縮 → 寸法・ポスターを取り出す → 分割アップロード
async function submitVideo() {
    const meta = { title: form.title.trim(), description: form.description, collection_id: form.collection_id, tags: form.tags };
    let blob = file.value;

    // 1) MP4 への変換（軽い H.264 は詰め替えだけ＝エンコーダ不要、重い動画は再エンコード）。
    //    変換できなくても、原本が小さければそのまま送れる
    progress.value = { phase: 'compress', ratio: 0 };
    try {
        blob = (await compressVideo(file.value, (r) => { progress.value = { phase: 'compress', ratio: r }; })).blob;
    } catch (e) {
        console.warn('[portfolio] 動画の変換に失敗しました。原本のまま送ります', e);
        blob = file.value;
    }
    if (blob === file.value && file.value.size > MAX_ORIGINAL_BYTES) {
        throw new Error('この動画は軽量化できず、そのままでは大きすぎます（上限 300MB）。Chrome または Edge の最新版で開くか、H.264 の MP4 に書き出してからお試しください。');
    }

    // 2) 寸法・長さ・ポスター画像。変換後の動画が読めなければ、原本で再試行する
    progress.value = { phase: 'upload', ratio: 0 };
    let info;
    try {
        info = await inspectVideo(blob);
    } catch (e) {
        if (blob !== file.value && file.value.size <= MAX_ORIGINAL_BYTES) {
            console.warn('[portfolio] 変換後の動画を読めないため、原本で再試行します', e);
            blob = file.value;
            try {
                info = await inspectVideo(blob);
            } catch (e2) {
                throw new Error(`動画の内容を読み取れませんでした（${e2?.message || '不明なエラー'}）。`);
            }
        } else {
            throw new Error(`動画の内容を読み取れませんでした（${e?.message || '不明なエラー'}）。`);
        }
    }
    await uploadVideo(blob, file.value.name, meta, info, (p) => { progress.value = p; });
}

async function submit() {
    if (tagInput.value.trim()) addTag();
    if (!canSubmit.value) return;
    sending.value = true;
    errorMessage.value = '';
    progress.value = null;
    try {
        if (isVideo.value) {
            await submitVideo();
            emit('created');
            return;
        }
        if (isPdf.value) {
            progress.value = { phase: 'upload', ratio: 0 };
            await uploadPdf(
                file.value,
                { title: form.title.trim(), description: form.description, collection_id: form.collection_id, tags: form.tags },
                (p) => { progress.value = p; },
            );
            emit('created');
            return;
        }
        const prepared = await resizeImageForUpload(file.value);
        const body = new FormData();
        body.append('file', prepared);
        body.append('title', form.title.trim());
        if (form.description.trim()) body.append('description', form.description);
        if (form.collection_id !== '') body.append('collection_id', form.collection_id);
        form.tags.forEach((t) => body.append('tags[]', t));
        await axios.post(route('portfolio.api.items.store'), body);
        emit('created');
    } catch (e) {
        // 422 などはサーバーの具体的な理由を優先して表示する
        const d = e?.response?.data;
        errorMessage.value = d?.errors ? Object.values(d.errors).flat()[0] : (d?.message || e?.message || '追加に失敗しました。通信状態を確認してもう一度お試しください。');
        if (isVideo.value) showVideoHelp.value = true;
    } finally {
        sending.value = false;
        progress.value = null;
    }
}

onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="!sending && emit('close')">
        <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <h3 class="mb-4 text-base font-semibold text-gray-900">作品を追加</h3>

            <!-- ① タイトル -->
            <label class="mb-4 block text-xs text-gray-600">
                <span class="mb-1 flex items-center gap-1"><b class="text-gray-900">① タイトル</b><span class="rounded bg-red-50 px-1 text-[10px] text-red-600">必須</span></span>
                <input v-model="form.title" type="text" maxlength="150" class="block w-full rounded border-gray-300 text-sm" placeholder="例: 春のセールチラシ" />
            </label>

            <!-- ② 説明 -->
            <label class="mb-4 block text-xs text-gray-600">
                <span class="mb-1 block"><b class="text-gray-900">② 説明</b>（コンセプトや工夫した点。あとから変更できます）</span>
                <textarea v-model="form.description" rows="3" maxlength="2000" class="block w-full rounded border-gray-300 text-sm" />
            </label>

            <!-- ③ ファイル -->
            <div class="mb-4 text-xs text-gray-600">
                <span class="mb-1 flex items-center gap-1"><b class="text-gray-900">③ ファイル</b><span class="rounded bg-red-50 px-1 text-[10px] text-red-600">必須</span></span>
                <div
                    v-if="!file"
                    class="cursor-pointer rounded-xl border-2 border-dashed px-4 py-8 text-center text-sm transition-colors"
                    :class="dragging ? 'border-gray-900 bg-gray-50 text-gray-900' : 'border-gray-300 text-gray-500 hover:border-gray-500'"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                    @click="input.click()"
                >
                    ここに画像・PDF・動画をドロップ、またはクリックして選択
                    <div class="mt-1 text-xs text-gray-400">JPG・PNG・WebP・PDF（60ページ・300MBまで）・動画 MP4/MOV（アップ前に自動で軽量化します）</div>
                    <input ref="input" type="file" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf,video/mp4,video/quicktime" @change="onPick" />
                </div>
                <div v-else class="relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                    <video v-if="previewUrl && isVideo" :src="previewUrl" class="mx-auto max-h-48" controls muted preload="metadata" />
                    <img v-else-if="previewUrl" :src="previewUrl" alt="" class="mx-auto max-h-48 object-contain" />
                    <div v-else class="flex items-center justify-center gap-3 py-8 text-gray-500">
                        <span class="rounded bg-red-50 px-2 py-1 text-xs font-bold text-red-600">PDF</span>
                        <span class="text-sm">{{ (file.size / 1024 / 1024).toFixed(1) }} MB ・追加するとページごとの画像に変換します</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t border-gray-200 bg-white px-3 py-1.5">
                        <span class="min-w-0 flex-1 overflow-hidden text-ellipsis whitespace-nowrap text-gray-600">{{ file.name }}</span>
                        <button type="button" :disabled="sending" class="shrink-0 text-gray-500 hover:text-red-600" @click="clearFile">選び直す</button>
                    </div>
                </div>
            </div>

            <!-- 動画のアップで困ったときのメモ（トグル。エラーが出た人が読めるように） -->
            <div class="mb-4 text-xs">
                <button type="button" class="flex items-center gap-1 text-gray-500 hover:text-gray-800" @click="showVideoHelp = !showVideoHelp">
                    <span class="inline-block w-3 transition-transform" :class="showVideoHelp ? 'rotate-90' : ''">▸</span>
                    動画がアップできないとき
                </button>
                <div v-if="showVideoHelp" class="mt-2 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-gray-600">
                    <div>
                        <div class="mb-0.5 font-medium text-gray-800">① iPhone の撮影形式を「互換性優先」にする</div>
                        <p>「設定」→「カメラ」→「フォーマット」→「互換性優先」を選んで撮影し直すと、変換が速く、確実に再生できます。「高効率」（HEVC）で撮った動画は、変換できずエラーになることがあります。</p>
                    </div>
                    <div>
                        <div class="mb-0.5 font-medium text-gray-800">② ファイル形式と大きさ</div>
                        <ul class="list-disc space-y-0.5 pl-4">
                            <li>使えるのは <b>MP4・MOV</b> です（iPhone の動画は MOV）。</li>
                            <li>アップ前に、画面上で自動的に軽量化（MP4 へ変換）します。</li>
                            <li>軽量化できなかった場合は、元の動画が <b>300MB 以下</b> ならそのままアップできます。</li>
                            <li>軽量化後の上限は <b>1GB</b> です。</li>
                        </ul>
                    </div>
                    <div>
                        <div class="mb-0.5 font-medium text-gray-800">③ アップするときの注意</div>
                        <ul class="list-disc space-y-0.5 pl-4">
                            <li>「軽量化中」「アップロード中」は、<b>画面を閉じたり、別のページに移動したりしないでください</b>。</li>
                            <li>長い動画や重い動画は、変換に数分かかることがあります。</li>
                            <li><b>Chrome または Edge の最新版</b>での利用をおすすめします。</li>
                        </ul>
                    </div>
                    <div>
                        <div class="mb-0.5 font-medium text-gray-800">④ それでもエラーが出るとき</div>
                        <p>画面に出たエラーの文章を控えて、管理者に伝えてください。</p>
                    </div>
                </div>
            </div>

            <!-- 分類（任意） -->
            <div class="mb-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                <div class="mb-2 font-medium text-gray-700">分類（任意）</div>
                <label class="mb-3 block">
                    コレクション
                    <select v-model="form.collection_id" class="mt-1 block w-full rounded border-gray-300 text-sm">
                        <option value="">未分類</option>
                        <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </label>
                <div>
                    タグ
                    <div class="mt-1 flex flex-wrap items-center gap-1 rounded border border-gray-300 bg-white p-1.5">
                        <span v-for="t in form.tags" :key="t" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-gray-800">
                            {{ t }}<button type="button" class="text-gray-400 hover:text-gray-700" @click="removeTag(t)">×</button>
                        </span>
                        <input v-model="tagInput" type="text" maxlength="50" class="min-w-[6rem] flex-1 border-0 p-0.5 text-sm focus:ring-0" placeholder="入力して Enter" @keydown.enter.prevent="addTag()" />
                    </div>
                    <div v-if="suggestions.length" class="mt-1 flex flex-wrap gap-1">
                        <button v-for="s in suggestions" :key="s" type="button" class="rounded-full border border-gray-300 bg-white px-2 py-0.5 text-gray-600 hover:bg-gray-50" @click="addTag(s)">+ {{ s }}</button>
                    </div>
                </div>
            </div>

            <div v-if="sending && progress" class="mb-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                <div class="mb-1.5 flex justify-between">
                    <span v-if="progress.phase === 'compress'">動画を MP4 に変換中… {{ Math.round(progress.ratio * 100) }}%（重い動画は数分かかることがあります）</span>
                    <span v-else-if="progress.phase === 'upload'">アップロード中… {{ Math.round(progress.ratio * 100) }}%</span>
                    <span v-else>ページを画像に変換中… {{ progress.done }} / {{ progress.total }} ページ</span>
                    <span class="text-gray-400">閉じずにお待ちください</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full transition-all" :style="{ width: Math.round(progress.ratio * 100) + '%', backgroundColor: accent }" />
                </div>
            </div>

            <p v-if="errorMessage" class="mb-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

            <div class="flex justify-end gap-2">
                <button type="button" :disabled="sending" class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 disabled:opacity-40" @click="emit('close')">キャンセル</button>
                <button
                    type="button"
                    :disabled="!canSubmit"
                    class="rounded px-5 py-1.5 text-sm font-medium text-white disabled:opacity-40"
                    :style="{ backgroundColor: accent }"
                    @click="submit"
                >{{ sending ? '追加しています…' : '追加する' }}</button>
            </div>
        </div>
    </div>
</template>
