<script setup>
// お知らせ・リマインダー共通の簡易リッチテキスト（文字色・太字など）。v-model は HTML 文字列。空のときは '' を返す。
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const props = defineProps({
    modelValue: { type: String, default: '' },
    minHeight: { type: String, default: '160px' },
    placeholder: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const toolbar = [
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['clean'],
];

let quill = null;

function onReady(editor) {
    quill = editor;

    // 貼り付けでは文字色・背景色を取り込まない（他サイトや AI の出力に付く白背景などを防ぐ）。
    // 色はツールバーで選んだときだけ付く。初期表示（保存済み HTML の読み込み）には影響させない。
    let pasting = false;
    editor.root.addEventListener('paste', () => {
        pasting = true;
        setTimeout(() => { pasting = false; }, 0);
    }, true);
    editor.clipboard.addMatcher(Node.ELEMENT_NODE, (node, delta) => {
        if (!pasting) return delta;
        delta.ops.forEach((op) => {
            if (op.attributes) {
                delete op.attributes.color;
                delete op.attributes.background;
            }
        });
        return delta;
    });
}

function onInput(html) {
    // 何も書かれていない状態（<p><br></p>）は空文字にする
    const empty = quill ? quill.getText().trim() === '' : !html;
    emit('update:modelValue', empty ? '' : html);
}

// 旧データ（プレーンテキスト）は改行を段落に直して渡す
function toHtml(value) {
    if (!value || /<[a-z][\s\S]*>/i.test(value)) return value ?? '';
    return value.split('\n').map((l) => `<p>${l.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') || '<br>'}</p>`).join('');
}

// 初期値のみ渡す（入力のたびに content を書き戻すとカーソルが飛ぶため）
const initialContent = toHtml(props.modelValue);
</script>

<template>
    <div class="rounded border border-gray-300 bg-white">
        <QuillEditor
            :content="initialContent"
            :toolbar="toolbar"
            :placeholder="placeholder"
            theme="snow"
            content-type="html"
            :style="{ minHeight }"
            @ready="onReady"
            @update:content="onInput"
        />
    </div>
</template>
