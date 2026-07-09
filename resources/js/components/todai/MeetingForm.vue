<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import MeetingController from '@/actions/App/Http/Controllers/MeetingController';
import InputError from '@/components/InputError.vue';
import MarkdownEditor from '@/components/todai/MarkdownEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Meeting } from '@/types';

const props = withDefaults(
    defineProps<{
        meeting?: Meeting | null;
        submitLabel?: string;
    }>(),
    { meeting: null, submitLabel: 'Create' },
);

const emit = defineEmits<{ (e: 'saved'): void }>();

const toDateInput = (value: string | null): string =>
    value ? value.slice(0, 10) : '';

const form = useForm({
    title: props.meeting?.title ?? '',
    meeting_date: toDateInput(props.meeting?.meeting_date ?? null),
    notes: props.meeting?.notes ?? '',
    transcript: props.meeting?.transcript ?? '',
});

const submit = () => {
    if (props.meeting) {
        form.put(MeetingController.update(props.meeting).url, {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });

        return;
    }

    form.post(MeetingController.store().url, {
        onSuccess: () => {
            form.reset();
            emit('saved');
        },
    });
};
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-1.5">
            <Label for="meeting-title">Title</Label>
            <Input
                id="meeting-title"
                v-model="form.title"
                type="text"
                placeholder="e.g. Weekly sync"
                autocomplete="off"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="space-y-1.5">
            <Label for="meeting-date">Date (optional)</Label>
            <Input id="meeting-date" v-model="form.meeting_date" type="date" />
            <InputError :message="form.errors.meeting_date" />
        </div>

        <div class="space-y-1.5">
            <Label for="meeting-notes">Notes (optional)</Label>
            <MarkdownEditor
                id="meeting-notes"
                v-model="form.notes"
                :rows="6"
                placeholder="Jot down what happened, decisions, follow-ups… Markdown supported."
            />
            <InputError :message="form.errors.notes" />
        </div>

        <div class="space-y-1.5">
            <Label for="meeting-transcript">Transcript (optional)</Label>
            <textarea
                id="meeting-transcript"
                v-model="form.transcript"
                :rows="10"
                placeholder="Paste the full meeting transcript here…"
                class="block w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-sm placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none"
            />
            <InputError :message="form.errors.transcript" />
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>
