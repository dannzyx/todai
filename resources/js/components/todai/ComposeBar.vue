<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ArrowUp } from '@lucide/vue';
import { computed } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';

/**
 * The persistent compose bar — Todai's signature quick-add: type a task and it
 * drops into the Inbox. Hidden on the Chat page, which has its own conversation
 * input.
 */
const page = usePage();
const onChatPage = computed(() => {
    const path = page.url.split('?')[0];

    return path === '/chat' || path.startsWith('/chat/');
});

const form = useForm<{ title: string }>({ title: '' });

const submit = () => {
    if (form.title.trim() === '') {
        return;
    }

    form.post(TaskController.store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <div
        v-if="!onChatPage"
        class="sticky bottom-0 z-30 border-t border-border/70 bg-background/85 backdrop-blur"
    >
        <form
            class="mx-auto flex max-w-3xl items-center gap-2 px-4 py-3 sm:px-6"
            @submit.prevent="submit"
        >
            <input
                v-model="form.title"
                type="text"
                placeholder="Type a task..."
                aria-label="New task"
                class="flex-1 rounded-full border border-input bg-card px-4 py-2.5 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:border-solar focus-visible:ring-2 focus-visible:ring-solar/40 focus-visible:outline-none"
            />
            <button
                type="submit"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-solar text-solar-foreground shadow-sm transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
                :disabled="form.processing || form.title.trim() === ''"
                aria-label="Add task"
            >
                <ArrowUp class="h-4 w-4" />
            </button>
        </form>
    </div>
</template>
