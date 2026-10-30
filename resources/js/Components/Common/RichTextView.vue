<script setup>
// SimpleRichText の内容を表示する。HTML は DOMPurify を通す。プレーンテキスト（旧データ・自動生成）は改行を保って表示する。
import DOMPurify from 'dompurify';
import { computed } from 'vue';

const props = defineProps({ content: { type: String, default: '' } });

const isHtml = computed(() => /<[a-z][\s\S]*>/i.test(props.content ?? ''));
const safeHtml = computed(() =>
    DOMPurify.sanitize(props.content ?? '', {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'span', 'ul', 'ol', 'li'],
        ALLOWED_ATTR: ['style'],
    })
);
</script>

<template>
    <div v-if="isHtml" class="rich-text-view break-words" v-html="safeHtml"></div>
    <div v-else class="whitespace-pre-wrap break-words">{{ content }}</div>
</template>

<style scoped>
.rich-text-view :deep(p) { margin: 0; min-height: 1em; }
.rich-text-view :deep(ul) { list-style: disc; padding-left: 1.5rem; }
.rich-text-view :deep(ol) { list-style: decimal; padding-left: 1.5rem; }
</style>
