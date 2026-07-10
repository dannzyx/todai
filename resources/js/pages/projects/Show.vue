<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, ArrowLeft, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Meta from '@/components/todai/Meta.vue';
import ProjectForm from '@/components/todai/ProjectForm.vue';
import TaskList from '@/components/todai/TaskList.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTaskModal } from '@/composables/useTaskModal';
import type { Project, Task } from '@/types';

const props = defineProps<{
    project: Project;
    tasks: Task[];
}>();

const { openTaskModal } = useTaskModal();

const editing = ref(false);

const isArchived = () => props.project.archived_at !== null;

const toggleArchive = () => {
    const action = isArchived()
        ? ProjectController.unarchive(props.project)
        : ProjectController.archive(props.project);

    router.patch(action.url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="project.name" />

    <div class="space-y-8">
        <Link
            :href="ProjectController.index().url"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="h-4 w-4" />
            All projects
        </Link>

        <header class="flex flex-col gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="h-4 w-4 shrink-0 rounded-full"
                    :style="{ backgroundColor: project.color ?? '#6B7280' }"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <h1
                        class="font-display text-3xl font-semibold tracking-tight break-words sm:text-4xl"
                    >
                        {{ project.name }}
                    </h1>
                    <Meta>{{ project.open_tasks_count ?? 0 }} open tasks</Meta>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button size="sm" @click="openTaskModal(project.id)">
                    <Plus class="mr-1.5 h-4 w-4" />
                    New task
                </Button>
                <Button variant="outline" size="sm" @click="editing = true">
                    <Pencil class="mr-1.5 h-4 w-4" />
                    Edit
                </Button>
                <Button variant="outline" size="sm" @click="toggleArchive">
                    <component
                        :is="isArchived() ? ArchiveRestore : Archive"
                        class="mr-1.5 h-4 w-4"
                    />
                    {{ isArchived() ? 'Restore' : 'Archive' }}
                </Button>
            </div>
        </header>

        <section aria-label="Tasks" class="space-y-4">
            <h2 class="text-sm font-semibold text-foreground">Tasks</h2>

            <div
                v-if="tasks.length === 0"
                class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                No tasks in this project yet.
            </div>
            <TaskList v-else :tasks="tasks" :show-project="false" />
        </section>

        <Dialog v-model:open="editing">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit project</DialogTitle>
                </DialogHeader>
                <ProjectForm
                    :project="project"
                    submit-label="Save"
                    @saved="editing = false"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
