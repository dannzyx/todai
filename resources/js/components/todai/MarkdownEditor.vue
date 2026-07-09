<script setup lang="ts">
import {
    Bold,
    Code,
    Heading,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
} from '@lucide/vue';
import { nextTick, ref } from 'vue';
import Markdown from '@/components/todai/Markdown.vue';

/**
 * A lightweight, dependency-free Markdown editor: a textarea with a formatting
 * toolbar plus a Write/Preview toggle. The toolbar only offers the subset of
 * Markdown that renderMarkdown() (see @/lib/markdown) actually renders.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string;
        id?: string;
        rows?: number;
        placeholder?: string;
    }>(),
    {
        id: undefined,
        rows: 6,
        placeholder: 'Markdown supported.',
    },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const preview = ref(false);
const textarea = ref<HTMLTextAreaElement | null>(null);

/** Emit a new value and restore focus + selection after Vue re-renders. */
const commit = (value: string, selStart: number, selEnd: number): void => {
    emit('update:modelValue', value);

    nextTick(() => {
        const el = textarea.value;

        if (el) {
            el.focus();
            el.setSelectionRange(selStart, selEnd);
        }
    });
};

/** Wrap the current selection in a marker on both sides (bold, italic, code). */
const wrap = (marker: string, placeholder: string): void => {
    const el = textarea.value;

    if (!el) {
        return;
    }

    const { selectionStart: start, selectionEnd: end } = el;
    const value = props.modelValue;
    const selected = value.slice(start, end) || placeholder;
    const next =
        value.slice(0, start) + marker + selected + marker + value.slice(end);

    commit(
        next,
        start + marker.length,
        start + marker.length + selected.length,
    );
};

/** Prefix each line of the selection (headings, lists). */
const prefixLines = (make: (line: string, index: number) => string): void => {
    const el = textarea.value;

    if (!el) {
        return;
    }

    const value = props.modelValue;
    const lineStart = value.lastIndexOf('\n', el.selectionStart - 1) + 1;
    const newlineAfter = value.indexOf('\n', el.selectionEnd);
    const end = newlineAfter === -1 ? value.length : newlineAfter;

    const transformed = value
        .slice(lineStart, end)
        .split('\n')
        .map(make)
        .join('\n');

    const next = value.slice(0, lineStart) + transformed + value.slice(end);

    commit(next, lineStart, lineStart + transformed.length);
};

/** Insert a link, leaving the "url" placeholder selected for quick editing. */
const insertLink = (): void => {
    const el = textarea.value;

    if (!el) {
        return;
    }

    const { selectionStart: start, selectionEnd: end } = el;
    const value = props.modelValue;
    const label = value.slice(start, end) || 'text';
    const snippet = `[${label}](url)`;
    const next = value.slice(0, start) + snippet + value.slice(end);
    const urlStart = start + label.length + 3;

    commit(next, urlStart, urlStart + 3);
};

const tools = [
    { label: 'Bold', icon: Bold, run: () => wrap('**', 'bold') },
    { label: 'Italic', icon: Italic, run: () => wrap('*', 'italic') },
    { label: 'Inline code', icon: Code, run: () => wrap('`', 'code') },
    {
        label: 'Heading',
        icon: Heading,
        run: () => prefixLines((line) => `# ${line}`),
    },
    {
        label: 'Bullet list',
        icon: List,
        run: () => prefixLines((line) => `- ${line}`),
    },
    {
        label: 'Numbered list',
        icon: ListOrdered,
        run: () => prefixLines((line, index) => `${index + 1}. ${line}`),
    },
    { label: 'Link', icon: LinkIcon, run: insertLink },
];
</script>

<template>
    <div
        class="rounded-md border border-input bg-transparent shadow-sm focus-within:ring-2 focus-within:ring-ring"
    >
        <div
            class="flex flex-wrap items-center gap-0.5 border-b border-border/70 p-1"
        >
            <button
                v-for="tool in tools"
                :key="tool.label"
                type="button"
                :title="tool.label"
                :aria-label="tool.label"
                :disabled="preview"
                class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
                @click="tool.run"
            >
                <component :is="tool.icon" class="h-4 w-4" />
            </button>

            <div class="ml-auto flex items-center gap-1 text-xs" role="tablist">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="!preview"
                    class="rounded px-2 py-0.5 font-medium transition-colors"
                    :class="
                        !preview
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="preview = false"
                >
                    Write
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="preview"
                    class="rounded px-2 py-0.5 font-medium transition-colors"
                    :class="
                        preview
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="preview = true"
                >
                    Preview
                </button>
            </div>
        </div>

        <textarea
            v-if="!preview"
            :id="id"
            ref="textarea"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            class="block w-full resize-y bg-transparent px-3 py-2 font-mono text-sm placeholder:text-muted-foreground focus:outline-none"
            @input="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLTextAreaElement).value,
                )
            "
        />
        <div
            v-else
            class="px-3 py-2"
            :style="{ minHeight: `${rows * 1.5}rem` }"
        >
            <Markdown v-if="modelValue.trim()" :content="modelValue" />
            <p v-else class="text-sm text-muted-foreground">
                Nothing to preview yet.
            </p>
        </div>
    </div>
</template>
