<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import InputError from '@/components/InputError.vue';
import ProjectPicker from '@/components/todai/ProjectPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Task } from '@/types';

const props = withDefaults(
    defineProps<{
        task?: Task | null;
        defaultProjectId?: string | null;
    }>(),
    { task: null, defaultProjectId: null },
);

const emit = defineEmits<{ (e: 'saved'): void }>();

const toDateInput = (iso: string | null): string =>
    iso ? iso.slice(0, 10) : '';

const form = useForm({
    title: props.task?.title ?? '',
    description: props.task?.description ?? '',
    due_date: toDateInput(props.task?.due_date ?? null),
    project_id: props.task?.project_id ?? props.defaultProjectId,
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.task) {
                form.reset();
            }

            emit('saved');
        },
    };

    if (props.task) {
        form.put(TaskController.update(props.task).url, options);

        return;
    }

    form.post(TaskController.store().url, options);
};
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-1.5">
            <Label for="task-title">Title</Label>
            <Input
                id="task-title"
                v-model="form.title"
                type="text"
                placeholder="What needs to happen?"
                autocomplete="off"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="space-y-1.5">
            <Label for="task-description">Description (optional)</Label>
            <textarea
                id="task-description"
                v-model="form.description"
                rows="3"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <Label for="task-due">Deadline (optional)</Label>
                <Input id="task-due" v-model="form.due_date" type="date" />
                <InputError :message="form.errors.due_date" />
            </div>
            <div class="space-y-1.5">
                <Label for="task-project">Project</Label>
                <ProjectPicker id="task-project" v-model="form.project_id" />
                <InputError :message="form.errors.project_id" />
            </div>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
                {{ task ? 'Save' : 'Add' }}
            </Button>
        </div>
    </form>
</template>
