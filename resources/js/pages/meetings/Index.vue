<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import MeetingController from '@/actions/App/Http/Controllers/MeetingController';
import MeetingForm from '@/components/todai/MeetingForm.vue';
import Meta from '@/components/todai/Meta.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { shortDate } from '@/lib/dates';
import type { Meeting } from '@/types';

defineProps<{
    meetings: Meeting[];
}>();

const creating = ref(false);

const sourceLabel = (source: Meeting['source']): string =>
    source === 'fireflies' ? 'Fireflies' : 'Manual';

const statusLabel: Record<Meeting['status'], string> = {
    draft: 'Draft',
    processing: 'Generating…',
    ready: 'Ready',
    failed: 'Failed',
};
</script>

<template>
    <Head title="Meetings" />

    <div class="space-y-10">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="font-display text-4xl font-semibold tracking-tight">
                    Meetings
                </h1>
                <p class="text-sm text-muted-foreground">
                    Every meeting lands here. Fireflies imports get todo
                    suggestions automatically; add your own and generate todos on
                    demand.
                </p>
            </div>
            <Button class="shrink-0" @click="creating = true">
                <Plus class="mr-1.5 h-4 w-4" />
                New meeting
            </Button>
        </header>

        <section aria-label="Meetings" class="space-y-2">
            <div
                v-if="meetings.length === 0"
                class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                No meetings yet. Create one, or connect Fireflies in settings.
            </div>

            <ul v-else class="space-y-2">
                <li
                    v-for="meeting in meetings"
                    :key="meeting.id"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors hover:border-solar/50"
                >
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="MeetingController.show(meeting).url"
                            class="block truncate font-medium text-foreground hover:underline"
                        >
                            {{ meeting.title ?? 'Untitled meeting' }}
                        </Link>
                        <Meta class="flex flex-wrap items-center gap-x-2">
                            <span>{{ sourceLabel(meeting.source) }}</span>
                            <span v-if="meeting.meeting_date">
                                · {{ shortDate(meeting.meeting_date) }}
                            </span>
                            <span>· {{ statusLabel[meeting.status] }}</span>
                            <span
                                v-if="(meeting.pending_suggestions_count ?? 0) > 0"
                                class="text-aqua-strong"
                            >
                                · {{ meeting.pending_suggestions_count }} todo
                                suggestion{{
                                    meeting.pending_suggestions_count === 1
                                        ? ''
                                        : 's'
                                }}
                            </span>
                            <span v-if="meeting.project">
                                ·
                                <span
                                    class="inline-block h-2 w-2 rounded-full align-middle"
                                    :style="{
                                        backgroundColor:
                                            meeting.project.color ?? '#6B7280',
                                    }"
                                />
                                {{ meeting.project.name }}
                            </span>
                        </Meta>
                    </div>
                </li>
            </ul>
        </section>

        <Dialog v-model:open="creating">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New meeting</DialogTitle>
                </DialogHeader>
                <MeetingForm @saved="creating = false" />
            </DialogContent>
        </Dialog>
    </div>
</template>
