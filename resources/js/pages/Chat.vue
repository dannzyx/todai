<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowUp } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import ChatController from '@/actions/App/Http/Controllers/ChatController';
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

const form = useForm<{ message: string }>({ message: '' });
const thread = ref<HTMLElement | null>(null);

const scrollToEnd = async () => {
    await nextTick();
    thread.value?.scrollTo({ top: thread.value.scrollHeight });
};

watch(() => props.messages.length, scrollToEnd, { immediate: true });

const send = () => {
    if (form.message.trim() === '') {
        return;
    }

    form.post(ChatController.send().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const startNew = () => {
    router.post(ChatController.reset().url);
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
                        Praat met Todai
                    </h1>
                </div>
                <Button
                    v-if="messages.length > 0"
                    variant="ghost"
                    size="sm"
                    @click="startNew"
                >
                    Nieuw gesprek
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
                    Vertel Todai wat er moet gebeuren. Bijvoorbeeld: "morgen Remy
                    bellen, en offerte TalentSquare afmaken deze week".
                </div>

                <template v-for="message in messages" :key="message.id">
                    <!-- Human voice: right-aligned solar. -->
                    <div v-if="message.role === 'user'" class="flex justify-end">
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
                            <p class="text-sm whitespace-pre-wrap text-foreground">
                                {{ message.content }}
                            </p>
                        </VoiceCard>
                    </div>
                </template>

                <div
                    v-if="form.processing"
                    class="flex justify-start"
                    aria-live="polite"
                >
                    <VoiceCard voice="ai" class="rounded-2xl rounded-bl-sm px-4 py-2.5">
                        <Meta>Todai denkt na…</Meta>
                    </VoiceCard>
                </div>
            </div>

            <form
                class="mt-4 flex items-end gap-2"
                @submit.prevent="send"
            >
                <textarea
                    v-model="form.message"
                    rows="1"
                    placeholder="Typ een taak, of praat met Todai..."
                    aria-label="Bericht aan Todai"
                    class="max-h-40 flex-1 resize-none rounded-2xl border border-input bg-card px-4 py-2.5 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:border-aqua focus-visible:ring-2 focus-visible:ring-aqua/40 focus-visible:outline-none"
                    @keydown.enter.exact.prevent="send"
                />
                <button
                    type="submit"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-aqua text-aqua-foreground shadow-sm transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
                    :disabled="form.processing || form.message.trim() === ''"
                    aria-label="Verstuur"
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
                Aangemaakt
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
                Naar de inbox →
            </Link>
        </aside>
    </div>
</template>
