<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowUp } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import ChatController from '@/actions/App/Http/Controllers/ChatController';
import Markdown from '@/components/todai/Markdown.vue';
import Meta from '@/components/todai/Meta.vue';
import VoiceCard from '@/components/todai/VoiceCard.vue';
import { Button } from '@/components/ui/button';

type ChatMessage = { id: string; role: string; content: string };
type CreatedTask = {
    id: string;
    title: string;
    due_date: string | null;
    project: string | null;
};

const props = defineProps<{
    messages: ChatMessage[];
    createdTasks: CreatedTask[];
}>();

// Local, mutable copies so we can update the thread in place without a page
// visit. They stay in sync when the server props change (e.g. after reset).
const messages = ref<ChatMessage[]>([...props.messages]);
const createdTasks = ref<CreatedTask[]>([...props.createdTasks]);
const draft = ref('');
const thinking = ref(false);
const error = ref<string | null>(null);
const thread = ref<HTMLElement | null>(null);

watch(
    () => props.messages,
    (value) => {
        messages.value = [...value];
    },
);

watch(
    () => props.createdTasks,
    (value) => {
        createdTasks.value = [...value];
    },
);

const scrollToEnd = async () => {
    await nextTick();
    thread.value?.scrollTo({ top: thread.value.scrollHeight });
};

watch(() => messages.value.length, scrollToEnd, { immediate: true });
watch(thinking, scrollToEnd);

/** Read Laravel's XSRF-TOKEN cookie for the CSRF header. */
const xsrfToken = (): string => {
    const match = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
};

const send = async () => {
    const text = draft.value.trim();

    if (text === '' || thinking.value) {
        return;
    }

    // Optimistically show the message, then clear the composer.
    const optimisticId = `local-${messages.value.length}-${text.length}`;
    messages.value.push({ id: optimisticId, role: 'user', content: text });
    draft.value = '';
    error.value = null;
    thinking.value = true;

    try {
        const response = await fetch(ChatController.send().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: text }),
        });

        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }

        const data: { messages: ChatMessage[]; createdTasks: CreatedTask[] } =
            await response.json();

        messages.value = data.messages;
        createdTasks.value = data.createdTasks;
    } catch {
        // Roll back the optimistic message and let the user retry.
        messages.value = messages.value.filter((m) => m.id !== optimisticId);
        draft.value = text;
        error.value = 'Something went wrong. Please try again.';
    } finally {
        thinking.value = false;
    }
};

const startNew = () => {
    messages.value = [];
    createdTasks.value = [];
    error.value = null;

    router.post(
        ChatController.reset().url,
        {},
        { preserveScroll: true, preserveState: true },
    );
};
</script>

<template>
    <Head title="Chat" />

    <div class="flex flex-col gap-6 lg:flex-row">
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="mb-6 flex items-end justify-between">
                <div>
                    <p
                        class="font-mono text-xs tracking-tight text-muted-foreground"
                    >
                        Chat
                    </p>
                    <h1
                        class="mt-1 font-display text-4xl font-semibold tracking-tight"
                    >
                        Talk to Todai
                    </h1>
                </div>
                <Button
                    v-if="messages.length > 0"
                    variant="ghost"
                    size="sm"
                    @click="startNew"
                >
                    New conversation
                </Button>
            </header>

            <div
                ref="thread"
                class="min-h-[40vh] flex-1 space-y-4 overflow-y-auto"
            >
                <div
                    v-if="messages.length === 0"
                    class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
                >
                    Tell Todai what needs to happen. For example: "call Remy
                    tomorrow, and finish the TalentSquare quote this week".
                </div>

                <template v-for="message in messages" :key="message.id">
                    <!-- Human voice: right-aligned solar. -->
                    <div
                        v-if="message.role === 'user'"
                        class="flex justify-end"
                    >
                        <div
                            class="max-w-[85%] rounded-2xl rounded-br-sm border border-solar/40 bg-solar-surface px-4 py-2.5 text-sm whitespace-pre-wrap text-foreground"
                        >
                            {{ message.content }}
                        </div>
                    </div>
                    <!-- AI voice: left-aligned aqua. -->
                    <div v-else class="flex justify-start">
                        <VoiceCard
                            voice="ai"
                            class="max-w-[85%] rounded-2xl rounded-bl-sm px-4 py-2.5"
                        >
                            <Markdown :content="message.content" />
                        </VoiceCard>
                    </div>
                </template>

                <div
                    v-if="thinking"
                    class="flex justify-start"
                    aria-live="polite"
                >
                    <VoiceCard
                        voice="ai"
                        class="rounded-2xl rounded-bl-sm px-4 py-2.5"
                    >
                        <Meta class="inline-flex items-center">
                            Todai is thinking<span class="typing-dots">
                                <span></span><span></span><span></span> </span
                            ><span class="sr-only">…</span>
                        </Meta>
                    </VoiceCard>
                </div>
            </div>

            <p v-if="error" class="mt-3 text-sm text-destructive" role="alert">
                {{ error }}
            </p>

            <form class="mt-4 flex items-end gap-2" @submit.prevent="send">
                <textarea
                    v-model="draft"
                    rows="1"
                    placeholder="Type a task, or talk to Todai..."
                    aria-label="Message to Todai"
                    class="max-h-40 flex-1 resize-none rounded-2xl border border-input bg-card px-4 py-2.5 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:border-aqua focus-visible:ring-2 focus-visible:ring-aqua/40 focus-visible:outline-none"
                    @keydown.enter.exact.prevent="send"
                />
                <button
                    type="submit"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-aqua text-aqua-foreground shadow-sm transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
                    :disabled="thinking || draft.trim() === ''"
                    aria-label="Send"
                >
                    <ArrowUp class="h-4 w-4" />
                </button>
            </form>
        </div>

        <!-- Side list: tasks Todai created this turn. -->
        <aside v-if="createdTasks.length > 0" class="lg:w-64 lg:shrink-0">
            <h2
                class="mb-3 font-mono text-xs tracking-wide text-aqua-strong uppercase"
            >
                Created
            </h2>
            <ul class="space-y-2">
                <li
                    v-for="task in createdTasks"
                    :key="task.id"
                    class="rounded-xl border border-aqua/40 bg-aqua-surface px-3 py-2"
                >
                    <p class="text-sm text-foreground">{{ task.title }}</p>
                    <Meta class="mt-0.5 block">
                        {{ task.project ?? 'inbox'
                        }}{{ task.due_date ? ` · ${task.due_date}` : '' }}
                    </Meta>
                </li>
            </ul>
            <Link
                href="/inbox"
                class="mt-3 inline-block text-xs text-muted-foreground hover:text-foreground"
            >
                To the inbox →
            </Link>
        </aside>
    </div>
</template>

<style scoped>
/* Animated ellipsis for the "Todai is thinking" indicator. */
.typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    margin-left: 0.15rem;
}

.typing-dots span {
    width: 0.25rem;
    height: 0.25rem;
    border-radius: 9999px;
    background-color: currentColor;
    opacity: 0.35;
    animation: typing-blink 1.4s infinite both;
}

.typing-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing-blink {
    0%,
    60%,
    100% {
        opacity: 0.35;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-1px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .typing-dots span {
        animation: none;
    }
}
</style>
