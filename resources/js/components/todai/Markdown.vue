<script setup lang="ts">
import { computed } from 'vue';
import { renderMarkdown } from '@/lib/markdown';

/**
 * Renders trusted-subset Markdown as HTML. The source is fully HTML-escaped in
 * renderMarkdown() before any markup is re-introduced, so the resulting string
 * is safe to pass to v-html.
 */
const props = defineProps<{ content: string }>();

const html = computed(() => renderMarkdown(props.content));
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -- sanitised in renderMarkdown -->
    <div class="markdown text-sm text-foreground" v-html="html" />
</template>

<style scoped>
.markdown :deep(p) {
    margin: 0;
}

.markdown :deep(p + *),
.markdown :deep(ul + *),
.markdown :deep(ol + *),
.markdown :deep(h1 + *),
.markdown :deep(h2 + *),
.markdown :deep(h3 + *) {
    margin-top: 0.5rem;
}

.markdown :deep(ul),
.markdown :deep(ol) {
    margin: 0;
    padding-left: 1.25rem;
}

.markdown :deep(ul) {
    list-style: disc;
}

.markdown :deep(ol) {
    list-style: decimal;
}

.markdown :deep(li) {
    margin: 0.125rem 0;
}

.markdown :deep(strong) {
    font-weight: 600;
}

.markdown :deep(em) {
    font-style: italic;
}

.markdown :deep(a) {
    text-decoration: underline;
    text-underline-offset: 2px;
}

.markdown :deep(h1),
.markdown :deep(h2),
.markdown :deep(h3),
.markdown :deep(h4) {
    font-weight: 600;
    margin: 0;
}

.markdown :deep(h1) {
    font-size: 1.1rem;
}

.markdown :deep(h2) {
    font-size: 1.05rem;
}

.markdown :deep(h3),
.markdown :deep(h4) {
    font-size: 1rem;
}

.markdown :deep(code) {
    font-family: ui-monospace, 'SFMono-Regular', monospace;
    font-size: 0.85em;
    padding: 0.1em 0.3em;
    border-radius: 0.25rem;
    background: color-mix(in oklab, currentColor 12%, transparent);
}
</style>
