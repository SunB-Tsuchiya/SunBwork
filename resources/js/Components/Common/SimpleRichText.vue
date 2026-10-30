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

// ツールバーのボタンに付けるマウスオーバー表示（Quill は標準では付けない）
const TOOLBAR_TITLES = {
    '.ql-bold': '太字',
    '.ql-italic': '斜体',
    '.ql-underline': '下線',
    '.ql-strike': '取り消し線',
    '.ql-color': '文字色',
    '.ql-background': '背景色（マーカー）',
    '.ql-list[value="ordered"]': '番号付きリスト',
    '.ql-list[value="bullet"]': '箇条書き',
    '.ql-clean': '書式クリア（選択した文字の装飾を消す）',
};

function setToolbarTitles(editor) {
    const container = editor.getModule('toolbar')?.container;
    if (!container) return;
    Object.entries(TOOLBAR_TITLES).forEach(([selector, title]) => {
        container.querySelectorAll(selector).forEach((el) => el.setAttribute('title', title));
    });
}

function onReady(editor) {
    quill = editor;
    setToolbarTitles(editor);

    // 貼り付けでは文字色・背景色を取り込まない（他サイトや AI の出力に付く白背景などを防ぐ）。
    // Quill に渡す前の HTML から色の指定を取り除く。色はツールバーで選んだときだけ付く。
    // 保存済み HTML の読み込みは別経路なので影響しない。
    editor.root.addEventListener('paste', (e) => {
        const html = e.clipboardData?.getData('text/html');
        if (!html) return; // プレーンテキストは Quill の標準処理
        e.preventDefault();
        e.stopPropagation();

        const doc = new DOMParser().parseFromString(html, 'text/html');
        doc.querySelectorAll('[style]').forEach((el) => {
            el.style.removeProperty('color');
            el.style.removeProperty('background');
            el.style.removeProperty('background-color');
            if (!el.getAttribute('style')?.trim()) el.removeAttribute('style');
        });
        doc.querySelectorAll('[color], [bgcolor]').forEach((el) => {
            el.removeAttribute('color');
            el.removeAttribute('bgcolor');
        });

        const range = editor.getSelection(true);
        if (range.length) editor.deleteText(range.index, range.length, 'user');
        const before = editor.getLength();
        editor.clipboard.dangerouslyPasteHTML(range.index, doc.body.innerHTML, 'user');
        editor.setSelection(range.index + (editor.getLength() - before), 0, 'silent');
    }, true);
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
