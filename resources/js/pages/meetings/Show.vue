<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    Pencil,
    Sparkles,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MeetingController from '@/actions/App/Http/Controllers/MeetingController';
import Markdown from '@/components/todai/Markdown.vue';
import MeetingForm from '@/components/todai/MeetingForm.vue';
import Meta from '@/components/todai/Meta.vue';
import VoiceCard from '@/components/todai/VoiceCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { shortDate } from '@/lib/dates';
import type { Meeting } from '@/types';

const props = defineProps<{
    meeting: Meeting;
}>();

const editing = ref(false);

const suggestions = computed(() => props.meeting.task_suggestions ?? []);

const isProcessing = computed(() => props.meeting.status === 'processing');

// While the suggestions job runs, poll the server so the page refreshes itself
// the moment generation finishes. Only the meeting prop is refetched, and
// polling stops as soon as the status leaves "processing".
const { start: startPolling, stop: stopPolling } = usePoll(
    2500,
    { only: ['meeting'] },
    { autoStart: false },
);

watch(
    isProcessing,
    (processing) => {
        if (processing) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

const sourceLabel = computed(() =>
    props.meeting.source === 'fireflies' ? 'Fireflies' : 'Manual',
);

const projectSuggestionName = computed(
    () =>
        props.meeting.suggested_project?.name ??
        props.meeting.suggested_project_name ??
        '',
);

const isNewProjectSuggestion = computed(
    () =>
        props.meeting.suggested_project_id === null &&
        props.meeting.suggested_project_name !== null,
);

const confidenceLabel: Record<string, string> = {
    low: 'low confidence',
    medium: 'medium confidence',
    high: 'high confidence',
};

const generate = () => {
    router.post(
        MeetingController.generate(props.meeting).url,
        {},
        { preserveScroll: true },
    );
};

const acceptSuggestion = (suggestionId: string) => {
    router.patch(
        MeetingController.acceptSuggestion([props.meeting.id, suggestionId])
            .url,
        {},
        { preserveScroll: true },
    );
};

const dismissSuggestion = (suggestionId: string) => {
    router.patch(
        MeetingController.dismissSuggestion([props.meeting.id, suggestionId])
            .url,
        {},
        { preserveScroll: true },
    );
};

const acceptAll = () => {
    router.patch(
        MeetingController.acceptAllSuggestions(props.meeting).url,
        {},
        { preserveScroll: true },
    );
};

const acceptProject = () => {
    router.patch(
        MeetingController.acceptProject(props.meeting).url,
        {},
        { preserveScroll: true },
    );
};

const dismissProject = () => {
    router.patch(
        MeetingController.dismissProject(props.meeting).url,
        {},
        { preserveScroll: true },
    );
};

const destroy = () => {
    if (!window.confirm('Delete this meeting and its suggestions?')) {
        return;
    }

    router.delete(MeetingController.destroy(props.meeting).url);
};
</script>

<template>
    <Head :title="meeting.title ?? 'Meeting'" />

    <div class="space-y-8">
        <Link
            :href="MeetingController.index().url"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="h-4 w-4" />
            All meetings
        </Link>

        <header class="flex flex-col gap-4">
            <div class="min-w-0 space-y-1">
                <h1 class="font-display text-4xl font-semibold tracking-tight">
                    {{ meeting.title ?? 'Untitled meeting' }}
                </h1>
                <Meta class="flex flex-wrap items-center gap-x-2">
                    <span>{{ sourceLabel }}</span>
                    <span v-if="meeting.meeting_date">
                        · {{ shortDate(meeting.meeting_date) }}
                    </span>
                    <span
                        v-if="meeting.project"
                        class="inline-flex items-center gap-1"
                    >
                        ·
                        <span
                            class="inline-block h-2 w-2 rounded-full"
                            :style="{
                                backgroundColor:
                                    meeting.project.color ?? '#6B7280',
                            }"
                        />
                        {{ meeting.project.name }}
                    </span>
                </Meta>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button size="sm" :disabled="isProcessing" @click="generate">
                    <Sparkles class="mr-1.5 h-4 w-4" />
                    {{
                        suggestions.length > 0 || meeting.status === 'ready'
                            ? 'Regenerate'
                            : 'Generate todos'
                    }}
                </Button>
                <Button variant="outline" size="sm" @click="editing = true">
                    <Pencil class="mr-1.5 h-4 w-4" />
                    Edit
                </Button>
                <Button variant="outline" size="sm" @click="destroy">
                    <Trash2 class="mr-1.5 h-4 w-4" />
                    Delete
                </Button>
            </div>
        </header>

        <div
            v-if="meeting.status === 'failed'"
            class="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm text-foreground"
        >
            Generating suggestions failed.
            <span v-if="meeting.error" class="text-muted-foreground">
                {{ meeting.error }}
            </span>
        </div>

        <!-- Project suggestion -->
        <VoiceCard
            v-if="meeting.suggestion_confidence && projectSuggestionName"
            voice="ai"
            reveal
            class="p-3"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm text-foreground">
                        Todai suggests filing under:
                        <span class="font-medium text-aqua-strong">
                            {{ projectSuggestionName }}
                        </span>
                        <Badge
                            v-if="isNewProjectSuggestion"
                            variant="outline"
                            class="ml-1"
                        >
                            new project
                        </Badge>
                    </p>
                    <Meta class="mt-0.5 block">
                        {{ confidenceLabel[meeting.suggestion_confidence] }}
                        <template v-if="meeting.suggestion_reasoning">
                            · {{ meeting.suggestion_reasoning }}
                        </template>
                    </Meta>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <Button size="sm" variant="ghost" @click="dismissProject">
                        Dismiss
                    </Button>
                    <Button
                        size="sm"
                        class="bg-aqua text-aqua-foreground hover:bg-aqua/90"
                        @click="acceptProject"
                    >
                        Link project
                    </Button>
                </div>
            </div>
        </VoiceCard>

        <!-- Todo suggestions -->
        <section aria-label="Todo suggestions" class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-foreground">
                    Todo suggestions
                </h2>
                <Button
                    v-if="suggestions.length > 0"
                    size="sm"
                    variant="ghost"
                    @click="acceptAll"
                >
                    Accept all
                </Button>
            </div>

            <div v-if="isProcessing" class="space-y-2" aria-live="polite">
                <Skeleton class="h-14 w-full rounded-xl" />
                <Skeleton class="h-14 w-full rounded-xl" />
            </div>

            <div
                v-else-if="suggestions.length === 0"
                class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                No pending suggestions.
                {{
                    meeting.status === 'ready'
                        ? 'Everything here has been handled.'
                        : 'Generate todos to see what Todai finds.'
                }}
            </div>

            <ul v-else class="space-y-2">
                <li
                    v-for="suggestion in suggestions"
                    :key="suggestion.id"
                    class="flex items-start gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p
                            class="flex items-center gap-2 font-medium text-foreground"
                        >
                            <span class="min-w-0 truncate">{{
                                suggestion.title
                            }}</span>
                            <Badge v-if="suggestion.for_me" variant="outline">
                                For you
                            </Badge>
                        </p>
                        <p
                            v-if="suggestion.description"
                            class="mt-0.5 text-sm text-muted-foreground"
                        >
                            {{ suggestion.description }}
                        </p>
                        <Meta v-if="suggestion.due_date" class="mt-0.5 block">
                            due {{ shortDate(suggestion.due_date) }}
                        </Meta>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <Button
                            size="sm"
                            variant="ghost"
                            aria-label="Dismiss suggestion"
                            @click="dismissSuggestion(suggestion.id)"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                        <Button
                            size="sm"
                            class="bg-aqua text-aqua-foreground hover:bg-aqua/90"
                            aria-label="Accept suggestion"
                            @click="acceptSuggestion(suggestion.id)"
                        >
                            <Check class="mr-1 h-4 w-4" />
                            Add
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Content -->
        <section aria-label="Meeting content" class="space-y-3">
            <div v-if="meeting.notes" class="space-y-2">
                <h2 class="text-sm font-semibold text-foreground">Notes</h2>
                <Markdown :content="meeting.notes" />
            </div>

            <Collapsible v-if="meeting.summary" :default-open="true">
                <CollapsibleTrigger
                    class="group flex w-full items-center justify-between text-sm font-semibold text-foreground"
                >
                    Summary
                    <ChevronDown
                        class="h-4 w-4 transition-transform group-data-[state=open]:rotate-180"
                    />
                </CollapsibleTrigger>
                <CollapsibleContent class="pt-2">
                    <Markdown :content="meeting.summary" />
                </CollapsibleContent>
            </Collapsible>

            <Collapsible v-if="meeting.action_items">
                <CollapsibleTrigger
                    class="group flex w-full items-center justify-between text-sm font-semibold text-foreground"
                >
                    Action items
                    <ChevronDown
                        class="h-4 w-4 transition-transform group-data-[state=open]:rotate-180"
                    />
                </CollapsibleTrigger>
                <CollapsibleContent class="pt-2">
                    <Markdown :content="meeting.action_items" />
                </CollapsibleContent>
            </Collapsible>

            <Collapsible v-if="meeting.transcript">
                <CollapsibleTrigger
                    class="group flex w-full items-center justify-between text-sm font-semibold text-foreground"
                >
                    Transcript
                    <ChevronDown
                        class="h-4 w-4 transition-transform group-data-[state=open]:rotate-180"
                    />
                </CollapsibleTrigger>
                <CollapsibleContent class="pt-2">
                    <pre
                        class="max-h-96 overflow-auto rounded-lg border border-border bg-muted/40 p-3 text-sm whitespace-pre-wrap text-foreground"
                        >{{ meeting.transcript }}</pre>
                </CollapsibleContent>
            </Collapsible>
        </section>

        <Dialog v-model:open="editing">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit meeting</DialogTitle>
                </DialogHeader>
                <MeetingForm
                    :meeting="meeting"
                    submit-label="Save"
                    @saved="editing = false"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
